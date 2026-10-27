<?php
session_start();
require('ligar.php');
require_once __DIR__ . '/inc_veiculo_perfil.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';
require_once __DIR__ . '/inc_excesso_orcamento.php';
require_once __DIR__ . '/inc_cancelamento_marcacao.php';
require_once __DIR__ . '/inc_vin_ajuda.php';
require_once __DIR__ . '/inc_password_padrao.php';
require_once __DIR__ . '/inc_codigo_levantamento.php';
am_ensure_veiculo_perfil_tables($con);
am_ensure_servico_fecho_cols($con);
am_garantir_colunas_codigo_levantamento($con);
am_servico_sincronizar_cancelados_excesso_recusado($con);
$am_catalogo_pecas = am_pecas_substituidas_catalogo();
$am_valor_hora_padrao = AM_VALOR_HORA_PADRAO;

if (!isset($_SESSION['mecanico_id'])) {
    header('Location: login.php');
    exit;
}

$mecanico_id = $_SESSION['mecanico_id'];

if (!empty($_SESSION['flash_password_ok'])) {
    $success_password = (string) $_SESSION['flash_password_ok'];
    unset($_SESSION['flash_password_ok']);
}

// Processar mudança de password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mudar_password'])) {
    $nova_password = $_POST['nova_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    if (empty($nova_password) || empty($confirmar_password)) {
        $error_password = "Por favor, preencha ambos os campos.";
    } elseif ($nova_password !== $confirmar_password) {
        $error_password = "As palavras-chave não correspondem.";
    } elseif (strlen($nova_password) < 6) {
        $error_password = "A palavra-chave deve ter pelo menos 6 caracteres.";
    } elseif ($nova_password === am_password_padrao_funcionario()) {
        $error_password = "Escolha uma palavra-passe diferente da palavra-passe inicial da oficina.";
    } else {
        $hash = password_hash($nova_password, PASSWORD_DEFAULT);
        $stmt = $con->prepare("UPDATE mecanicos SET password = ?, primeiro_login = 0 WHERE id_mecanico = ?");
        $stmt->bind_param("si", $hash, $mecanico_id);
        
        if ($stmt->execute()) {
            $_SESSION['primeiro_login'] = 0;
            $_SESSION['flash_password_ok'] = 'Palavra-chave alterada com sucesso!';
            header('Location: funcionarios.php?tab=perfil');
            exit;
        } else {
            $error_password = "Erro ao atualizar a palavra-chave. Tente novamente.";
        }
        $stmt->close();
    }
}

// Obter informações do mecânico
$stmt = $con->prepare("SELECT * FROM mecanicos WHERE id_mecanico = ?");
$stmt->bind_param("i", $mecanico_id);
$stmt->execute();
$mecanico = $stmt->get_result()->fetch_assoc();
$stmt->close();

$primeiro_login = isset($_SESSION['primeiro_login']) && $_SESSION['primeiro_login'] == 1;

// Determinar filtro atual
$filtro_estado = $_GET['filtro'] ?? 'todos';
$tab = $_GET['tab'] ?? 'ativos';
if (!in_array($tab, ['ativos', 'entregas', 'historico', 'estatisticas'], true)) {
    $tab = 'ativos';
}

$colIdMecanico = false;
$colMecanicoId = false;
$colDataDesejada = false;
$colDataRegisto = false;
$colCreatedAt = false;
$colDataConclusao = false;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_mecanico'");
if ($chk && $chk->num_rows > 0) $colIdMecanico = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'mecanico_id'");
if ($chk && $chk->num_rows > 0) $colMecanicoId = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_desejada'");
if ($chk && $chk->num_rows > 0) $colDataDesejada = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_registo'");
if ($chk && $chk->num_rows > 0) $colDataRegisto = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'created_at'");
if ($chk && $chk->num_rows > 0) $colCreatedAt = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_conclusao'");
if ($chk && $chk->num_rows > 0) $colDataConclusao = true;
$servicoMecExpr = $colIdMecanico && $colMecanicoId ? "COALESCE(s.id_mecanico, s.mecanico_id)" : ($colIdMecanico ? "s.id_mecanico" : ($colMecanicoId ? "s.mecanico_id" : "NULL"));

$colIdUtilizadorServ = false;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_utilizador'");
if ($chk && $chk->num_rows > 0) {
    $colIdUtilizadorServ = true;
}
$servicoSelAtivos = 's.id, s.nome, s.tipo_servico, s.matricula, s.email, s.telefone, s.estado_do_trabalho, s.data_desejada, s.hora_desejada, s.custo, s.estado_pagamento, s.orcamento_aprovado, s.fatura_linhas_json, s.valor_total_com_iva, s.subtotal_sem_iva, s.valor_iva, s.horas_trabalhadas, s.valor_hora, s.pecas_substituidas';
if ($colIdUtilizadorServ) {
    $servicoSelAtivos .= ', s.id_utilizador';
}

$dataBaseParts = [];
if ($colDataDesejada) $dataBaseParts[] = "s.data_desejada";
if ($colDataRegisto) $dataBaseParts[] = "s.data_registo";
if ($colCreatedAt) $dataBaseParts[] = "s.created_at";
$dataBaseParts[] = "NOW()";
$dataBaseExpr = "COALESCE(" . implode(", ", $dataBaseParts) . ")";

$dataConclusaoParts = [];
if ($colDataConclusao) $dataConclusaoParts[] = "s.data_conclusao";
if ($colDataDesejada) $dataConclusaoParts[] = "s.data_desejada";
if ($colDataRegisto) $dataConclusaoParts[] = "s.data_registo";
if ($colCreatedAt) $dataConclusaoParts[] = "s.created_at";
$dataConclusaoParts[] = "NOW()";
$dataConclusaoExpr = "COALESCE(" . implode(", ", $dataConclusaoParts) . ")";

$ativosRows = [];
$trabalhosCursoRows = [];
$historicoRows = [];
$entregasRows = [];
$estatConcluidos = 0;
$estatMes = 0;
$estatReceitaTotal = 0.0;
$estatReceitaMes = 0.0;
$diasLabels = [];
$diasVals = [];

$ativosQ = @$con->query("
    SELECT {$servicoSelAtivos}
    FROM servico s
    WHERE $servicoMecExpr = " . (int)$mecanico_id . "
      AND " . am_sql_servico_ativo_mecanico($colDataConclusao) . "
    ORDER BY $dataBaseExpr ASC
");
if ($ativosQ) {
    while ($r = $ativosQ->fetch_assoc()) {
        if (am_servico_esta_concluido($r) || am_servico_tem_fecho_json_mecanico($r)) {
            continue;
        }
        $trabalhosCursoRows[] = $r;
        $estadoN = strtolower((string) ($r['estado_do_trabalho'] ?? ''));
        if (strpos($estadoN, 'andamento') !== false) {
            $ativosRows[] = $r;
        }
    }
}
$trabalhosCursoGrupos = am_mecanico_agrupar_servicos_por_veiculo($trabalhosCursoRows);

$historicoQ = @$con->query("
    SELECT s.id, s.nome, s.tipo_servico, s.matricula, s.data_desejada, s.hora_desejada, s.custo, s.numero_fatura,
           s.estado_pagamento, s.codigo_levantamento, s.codigo_levantamento_usado_em, s.estado_do_trabalho,
           s.estado_marcacao, s.estado_excesso_orcamento_cliente,
           s.orcamento_aprovado, s.fatura_linhas_json, s.valor_total_com_iva, s.subtotal_sem_iva, s.valor_iva,
           s.horas_trabalhadas, s.valor_hora, s.pecas_substituidas
    FROM servico s
    WHERE $servicoMecExpr = " . (int)$mecanico_id . "
      AND (
            LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%'
         OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%'
         OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%entreg%'
      )
    ORDER BY $dataConclusaoExpr DESC
");
if ($historicoQ) {
    while ($r = $historicoQ->fetch_assoc()) {
        if (am_servico_tem_fecho_mecanico($r)) {
            $repHist = am_servico_reparar_valores_fiscais_se_inconsistentes($con, (int) $r['id'], $r);
            if (is_array($repHist)) {
                $r = array_merge($r, $repHist);
            }
        }
        $historicoRows[] = $r;
    }
}
$historicoGrupos = am_mecanico_agrupar_servicos_por_veiculo($historicoRows);

foreach ($historicoRows as $r) {
    if (am_marcacao_esta_cancelada($r) || am_servico_excesso_orcamento_recusado($r)) {
        continue;
    }
    $estadoPagamentoEntrega = strtolower(trim((string) ($r['estado_pagamento'] ?? 'pendente')));
    $codigoEntrega = am_codigo_levantamento_formatado((string) ($r['codigo_levantamento'] ?? ''));
    $codigoUsadoEntrega = !empty($r['codigo_levantamento_usado_em']);
    if ($estadoPagamentoEntrega === 'pago' && $codigoEntrega !== '' && !$codigoUsadoEntrega) {
        $entregasRows[] = $r;
    }
}
$entregasGrupos = am_mecanico_agrupar_servicos_por_veiculo($entregasRows);

$pulseIdsCurso = [];
foreach ($trabalhosCursoRows as $r) {
    $pulseIdsCurso[] = (int) $r['id'] . ':' . strtolower((string) ($r['estado_do_trabalho'] ?? ''));
}
$pulseIdsConcl = [];
foreach (array_slice($historicoRows, 0, 20) as $r) {
    $pulseIdsConcl[] = (int) $r['id'];
}
$mecanico_pulse_inicial = md5(implode(',', $pulseIdsCurso) . '|' . implode(',', $pulseIdsConcl));

$estatConcluidos = count($historicoRows);
$estatMesQ = @$con->query("
    SELECT COUNT(*) AS total, SUM(COALESCE(NULLIF(s.valor_total_com_iva, 0), s.custo, 0)) AS receita
    FROM servico s
    WHERE $servicoMecExpr = " . (int)$mecanico_id . "
      AND (LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%')
      AND MONTH($dataConclusaoExpr) = MONTH(CURRENT_DATE())
      AND YEAR($dataConclusaoExpr) = YEAR(CURRENT_DATE())
");
if ($estatMesQ) {
    $rowM = $estatMesQ->fetch_assoc();
    $estatMes = (int)($rowM['total'] ?? 0);
    $estatReceitaMes = (float)($rowM['receita'] ?? 0);
}
$estatTotalQ = @$con->query("
    SELECT SUM(COALESCE(NULLIF(s.valor_total_com_iva, 0), s.custo, 0)) AS receita
    FROM servico s
    WHERE $servicoMecExpr = " . (int)$mecanico_id . "
      AND (LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%')
");
if ($estatTotalQ) $estatReceitaTotal = (float)(($estatTotalQ->fetch_assoc())['receita'] ?? 0);

for ($i = 6; $i >= 0; $i--) {
    $diasLabels[] = date('d/m', strtotime("-$i day"));
    $diasVals[] = 0;
}
$idx = [];
foreach ($diasLabels as $k => $d) $idx[$d] = $k;
$diasQ = @$con->query("
    SELECT DATE($dataConclusaoExpr) AS d, COUNT(*) AS total
    FROM servico s
    WHERE $servicoMecExpr = " . (int)$mecanico_id . "
      AND (LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%')
      AND DATE($dataConclusaoExpr) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE($dataConclusaoExpr)
");
if ($diasQ) {
    while ($r = $diasQ->fetch_assoc()) {
        $lb = date('d/m', strtotime($r['d']));
        if (isset($idx[$lb])) $diasVals[$idx[$lb]] = (int)$r['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Mecânico - AUTOMANAGER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="stylefuncionarios.css">
    <style>
        :root {
            --bg: var(--am-black-2, #0a0a0a);
            --card: var(--am-surface, #121212);
            --card-2: var(--am-elevated, #1c1c1c);
            --line: var(--am-border-strong, rgba(255, 255, 255, 0.1));
            --muted: var(--am-text-muted, #a1a1aa);
            --text: var(--am-text, #f4f4f5);
            --accent: var(--am-accent, #ea580c);
            --accent-hi: var(--am-accent-hover, #f97316);
            --ok: var(--success, #22c55e);
            --warn: var(--warning, #eab308);
        }

        body {
            background:
                radial-gradient(1200px 500px at 10% -20%, rgba(234, 88, 12, 0.12), transparent 60%),
                radial-gradient(900px 400px at 100% 0%, rgba(249, 115, 22, 0.08), transparent 55%),
                var(--bg);
            color: var(--text);
            font-family: var(--am-font, Inter, system-ui, sans-serif);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 18px;
            background: linear-gradient(180deg, rgba(22, 22, 22, 0.98), rgba(10, 10, 10, 0.98));
            border-radius: 16px;
            color: var(--text);
            border: 1px solid var(--line);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255,255,255,.03);
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid rgba(249, 115, 22, 0.22);
            border-radius: 14px;
            background: linear-gradient(120deg, rgba(234, 88, 12, 0.14), rgba(234, 88, 12, 0.04) 50%, rgba(18, 18, 18, 0.6));
        }
        
        .header h1 {
            color: var(--text);
            margin: 0;
            font-size: 1.65rem;
            letter-spacing: .2px;
        }
        .header p {
            margin: 2px 0 0;
            color: var(--muted);
        }
        
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 9px 14px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        
        .btn-primary {
            background: linear-gradient(180deg, var(--accent-hi), var(--accent));
            color: #0a0a0a;
            border: 1px solid rgba(255,255,255,.12);
        }
        
        .btn-primary:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--card-2);
            color: var(--text);
            border: 1px solid rgba(249, 115, 22, 0.35);
        }
        
        .btn-secondary:hover {
            background: #252525;
            border-color: var(--accent-hi);
        }
        
        .filtro-section {
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
        }
        
        .filtro-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .filtro-btn {
            padding: 8px 16px;
            border-radius: 6px;
            background: var(--card-2);
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filtro-btn:hover {
            background: #222;
            border-color: var(--accent);
        }
        
        .filtro-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        .search-inline {
            margin-top: 12px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
        }
        .search-inline input {
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            color: var(--text);
            padding: 10px 12px;
        }
        
        .table-container {
            margin-top: 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th {
            background: var(--card-2);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--accent-hi);
            border-bottom: 2px solid rgba(249, 115, 22, 0.25);
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        
        tr:hover {
            background: rgba(255, 107, 0, 0.05);
        }
        
        .estado-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .estado-Por_Fazer { background: #6c757d; color: white; }
        .estado-Em_Andamento { background: rgba(249, 115, 22, 0.25); color: #ffedd5; border: 1px solid rgba(249, 115, 22, 0.45); }
        .estado-Pronto { background: var(--ok); color: #0a0a0a; }
        .estado-Concluído { background: rgba(34, 197, 94, 0.2); color: #bbf7d0; border: 1px solid rgba(34, 197, 94, 0.45); }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-assumir {
            background: linear-gradient(180deg, var(--accent-hi), var(--accent));
            color: #0a0a0a;
        }
        
        .btn-assumir:hover {
            filter: brightness(1.06);
        }
        
        .btn-atualizar {
            background: #ffc107;
            color: #333;
        }
        
        .btn-atualizar:hover {
            background: #e0a800;
        }
        
        .btn-concluir {
            background: linear-gradient(180deg, var(--accent-hi), var(--accent));
            color: #0a0a0a;
        }
        
        .btn-concluir:hover {
            filter: brightness(1.05);
        }
        
        .btn-disabled {
            padding: 6px 12px;
            background: #555;
            color: #999;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .hidden {
            display: none !important;
        }
        
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .popup-content {
            background: linear-gradient(180deg, #161616, #0f0f0f);
            padding: 26px;
            border-radius: 16px;
            width: 100%;
            max-width: 560px;
            border: 1px solid rgba(249, 115, 22, 0.4);
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255,255,255,.04);
            margin: auto;
        }
        
        .popup-large {
            max-width: 720px;
        }

        .popup-conclusao {
            max-height: min(92dvh, 900px);
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
        }

        .popup-conclusao .popup-header {
            flex-shrink: 0;
            margin: 0;
            padding: 18px 56px 14px 20px;
            border-bottom: 1px solid rgba(255,255,255,.12);
            position: relative;
        }

        .popup-conclusao .popup-header h3 {
            font-size: 1.15rem;
            line-height: 1.35;
            padding-right: 4px;
        }

        .btn-close-modal {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 40px;
            height: 40px;
            min-width: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            color: #f3f4f6;
            cursor: pointer;
        }

        .btn-close-modal:hover {
            background: rgba(255,255,255,.14);
        }

        .popup-conclusao #formConclusao {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }

        .popup-conclusao .popup-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding: 16px 20px 8px;
        }

        .popup-conclusao .popup-actions {
            flex-shrink: 0;
            margin: 0;
            padding: 14px 20px calc(14px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(180deg, rgba(15,15,15,.92), rgba(10,10,10,.98));
        }

        .mx-perfil-fecho {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,.12);
        }

        .mx-perfil-fecho-titulo {
            margin: 0 0 10px;
            color: #fdba74;
            font-weight: 600;
            font-size: 13px;
        }

        .mx-perfil-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .mx-perfil-grid .form-group--full {
            grid-column: 1 / -1;
        }

        body.mx-modal-open {
            overflow: hidden;
        }
        
        .popup-header {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }
        
        .popup-header h2, .popup-header h3 {
            color: var(--accent-hi);
            margin: 0 0 10px 0;
            letter-spacing: .2px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: var(--text);
            font-weight: 600;
            font-size: 1.02rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 13px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: var(--am-black, #050505);
            color: var(--text);
            font-size: 1rem;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.22);
        }
        .field-error {
            margin-top: 6px;
            color: #ffb4be;
            font-size: 0.88rem;
        }
        .mx-mo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .mx-pecas-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        .mx-pecas-toolbar input {
            flex: 1;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.18);
            background: #1a1a1f;
            color: #f3f4f6;
        }
        .mx-pecas-painel {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 10px;
            background: rgba(0,0,0,.18);
            padding: 8px;
        }
        .mx-pecas-grupo {
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 8px;
            overflow: hidden;
        }
        .mx-pecas-grupo summary {
            cursor: pointer;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #fdba74;
            background: rgba(255,122,26,.08);
            list-style: none;
        }
        .mx-pecas-grupo summary::-webkit-details-marker { display: none; }
        .mx-peca-linha {
            display: grid;
            grid-template-columns: auto 1fr 88px;
            gap: 8px;
            align-items: center;
            padding: 8px 10px;
            border-top: 1px solid rgba(255,255,255,.06);
            cursor: pointer;
        }
        .mx-peca-linha.is-hidden { display: none; }
        .mx-peca-linha span {
            font-size: 13px;
            color: #e5e7eb;
        }
        .mx-peca-linha input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: var(--accent);
            cursor: pointer;
        }
        .mx-peca-linha input[type="number"] {
            width: 100%;
            padding: 7px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,.15);
            background: #121214;
            color: #f3f4f6;
        }
        .mx-peca-linha input[type="number"].mx-preco-sugerido {
            color: #fdba74;
        }
        .mx-peca-linha input[type="number"]::placeholder {
            color: #71717a;
            opacity: 1;
        }
        .mx-peca-linha input[type="number"]:disabled {
            opacity: .45;
        }
        .mx-pecas-custom {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 10px;
        }
        .mx-peca-custom-linha {
            display: grid;
            grid-template-columns: 1fr 88px auto;
            gap: 8px;
            align-items: center;
        }
        .mx-peca-custom-linha input {
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.15);
            background: #1a1a1f;
            color: #f3f4f6;
        }
        .mx-btn-peca-add, .mx-btn-peca-del {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .mx-btn-peca-add {
            background: rgba(255,122,26,.18);
            color: #fdba74;
            align-self: flex-start;
        }
        .mx-btn-peca-del {
            background: rgba(239,68,68,.15);
            color: #fca5a5;
        }
        .mx-resumo-fecho {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(34,197,94,.35);
            background: rgba(34,197,94,.08);
        }
        .mx-resumo-fecho h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #bbf7d0;
        }
        .mx-resumo-linha {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            color: #d1fae5;
            margin: 4px 0;
        }
        .mx-resumo-linha.total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,.12);
            font-size: 15px;
            font-weight: 800;
            color: #86efac;
        }
        .mx-resumo-fecho-nota {
            margin: 10px 0 0;
            font-size: 11px;
            line-height: 1.45;
            color: #a7f3d0;
        }
        .mx-resumo-linha.orcamento {
            color: #a7f3d0;
            font-size: 12px;
        }
        .mx-resumo-comparacao {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.45;
        }
        .mx-resumo-comparacao--dentro {
            background: rgba(34,197,94,.18);
            color: #bbf7d0;
        }
        .mx-resumo-comparacao--acima {
            background: rgba(249,115,22,.18);
            color: #fed7aa;
        }
        .mx-resumo-comparacao--abaixo {
            background: rgba(59,130,246,.18);
            color: #bfdbfe;
        }
        @media (max-width: 768px) {
            .popup-overlay-conclusao {
                align-items: flex-end;
                padding: 0;
            }
            .popup-overlay-conclusao .popup-content {
                border-radius: 16px 16px 0 0;
                max-width: 100%;
            }
            .popup-conclusao {
                max-height: 94dvh;
                border-radius: 18px 18px 0 0;
            }
            .popup-conclusao .popup-header {
                padding: 16px 52px 12px 16px;
            }
            .popup-conclusao .popup-body {
                padding: 14px 16px 8px;
            }
            .popup-conclusao .popup-actions {
                padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
            }
            .mx-mo-grid,
            .mx-perfil-grid {
                grid-template-columns: 1fr;
            }
            .mx-pecas-painel {
                max-height: min(38vh, 280px);
            }
            .mx-peca-linha input[type="checkbox"] {
                width: 22px;
                height: 22px;
            }
            .mx-peca-linha {
                grid-template-columns: 22px 1fr;
                gap: 8px 10px;
                padding: 10px;
                border-radius: 8px;
                margin-top: 4px;
                background: rgba(255,255,255,.03);
                border-top: none;
            }
            .mx-peca-linha input[type="number"] {
                grid-column: 1 / -1;
                min-height: 44px;
                font-size: 16px;
            }
            .mx-peca-custom-linha {
                grid-template-columns: 1fr;
            }
            .mx-peca-custom-linha input,
            .mx-pecas-toolbar input,
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px;
            }
            .mx-btn-peca-add {
                width: 100%;
                min-height: 44px;
            }
            .mx-btn-peca-del {
                min-height: 44px;
                width: 100%;
            }
            .popup-conclusao .popup-actions {
                flex-direction: column-reverse;
                gap: 8px;
            }
            .popup-conclusao .popup-actions .btn-cancel,
            .popup-conclusao .popup-actions .btn-success {
                width: 100%;
                min-width: 0;
                min-height: 48px;
            }
        }

        @media (max-width: 640px) {
            .mx-mo-grid { grid-template-columns: 1fr; }
        }

        @media (min-width: 769px) {
            .popup-conclusao {
                max-height: min(88dvh, 860px);
            }
        }
        
        .popup-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }
        
        .btn-cancel {
            background: #6f7a87;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            min-width: 120px;
        }
        
        .btn-cancel:hover {
            background: #7d8895;
        }
        
        .btn-success {
            background: linear-gradient(180deg, var(--accent-hi), var(--accent));
            color: #0a0a0a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 11px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            min-width: 140px;
        }
        
        .btn-success:hover {
            filter: brightness(1.06);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
        }
        
        .pagination button {
            padding: 8px 15px;
            background: #333;
            color: white;
            border: 1px solid #444;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #444;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-info {
            padding: 8px 15px;
            color: #ccc;
        }
        
        #loading {
            text-align: center;
            padding: 40px;
            color: #ff6b00;
        }
        
        .no-services {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #f8d7da;
        }
        
        .alert-success {
            background: rgba(249, 115, 22, 0.12);
            border: 1px solid rgba(249, 115, 22, 0.45);
            color: #ffedd5;
        }
        
        .error-box {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .error-box button {
            margin-top: 10px;
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .mx-tabs { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin: 6px 0 16px; }
        .mx-tab { display:block; text-align:center; padding:11px; border-radius:10px; background:var(--card-2); border:1px solid rgba(255,255,255,0.1); color:var(--muted); text-decoration:none; font-weight:600; font-size:13px; transition:.2s ease; }
        .mx-tab:hover { border-color:rgba(249,115,22,0.45); color:var(--text); }
        .mx-tab.active { border-color:var(--accent); box-shadow: inset 0 0 0 1px var(--accent); color:var(--accent-hi); background:rgba(249,115,22,0.08); }
        .mx-kpi { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:14px; }
        .mx-kpi .item { background:var(--card-2); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px; }
        .mx-kpi .n { font-size:30px; font-weight:800; color:var(--accent-hi); line-height:1.05; }
        .mx-kpi .l { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.45px; }
        .mx-section { background:var(--card-2); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:14px; margin-bottom:12px; }
        .mx-title { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .mx-badge { padding:4px 10px; border-radius:999px; font-size:11px; background:rgba(249,115,22,0.1); color:#fdba74; border:1px solid rgba(249,115,22,0.35); }
        .mx-row { display:grid; grid-template-columns:2fr 1.4fr 1fr; gap:10px; align-items:center; background:var(--card); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px; margin-bottom:10px; }
        .mx-row--veiculo { display:block; padding:14px 14px 8px; }
        .mx-veiculo-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .mx-veiculo-head strong { font-size:1.05rem; }
        .mx-veiculo-plate { display:inline-block; margin-left:8px; padding:2px 8px; border-radius:6px; background:rgba(249,115,22,0.15); color:#ffedd5; font-size:0.85rem; letter-spacing:0.04em; }
        .mx-servico-item { display:grid; grid-template-columns:2fr 1.4fr 1fr; gap:10px; align-items:center; padding:10px 0; border-top:1px solid rgba(255,255,255,0.06); }
        .mx-servico-item:first-of-type { border-top:none; padding-top:0; }
        @media (max-width: 900px) { .mx-servico-item { grid-template-columns:1fr; } }
        .mx-meta { color:var(--muted); font-size:13px; line-height:1.55; }
        .mx-actions { display:flex; gap:8px; justify-content:flex-end; }
        .mx-pill { padding:3px 9px; border-radius:999px; font-size:11px; font-weight:600; border:1px solid rgba(249,115,22,0.45); background:rgba(249,115,22,0.12); color:#fed7aa; margin-left:8px; }
        .mx-pill--pendente { border-color:rgba(234,179,8,0.45); background:rgba(234,179,8,0.1); color:#fef08a; }
        .mx-pill--concluido { border-color:rgba(249,115,22,0.55); background:rgba(249,115,22,0.14); color:#ffedd5; }
        .mx-pill--iniciar { border-color:rgba(161,161,170,0.45); background:rgba(255,255,255,0.06); color:#e4e4e7; }
        .mx-entrega-help { margin-top:8px; color:var(--muted); font-size:12px; }
        .mec-header-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: flex-end; }
        .mec-perfil { display: flex; align-items: center; gap: 10px; }
        .mec-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(145deg, var(--accent-hi), var(--accent));
            color: #0a0a0a; font-weight: 800; font-size: 15px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid rgba(255,255,255,0.12);
        }
        .mx-chart-wrap {
            position: relative;
            height: 280px;
            max-height: 280px;
            overflow: hidden;
        }
        .mx-chart-wrap canvas {
            width: 100% !important;
            height: 100% !important;
            max-height: 280px !important;
            display: block;
        }
        @media (max-width: 900px) { .mx-kpi{grid-template-columns:1fr 1fr;} .mx-row{grid-template-columns:1fr;} .mx-actions{justify-content:flex-start;} .mx-tabs{grid-template-columns:1fr 1fr;} }
        @media (max-width: 560px) { .mx-tabs{grid-template-columns:1fr;} }

        .mx-toast-stack {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 12000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .mx-toast {
            min-width: 280px;
            max-width: min(92vw, 440px);
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid rgba(34, 197, 94, 0.5);
            background: rgba(12, 20, 12, 0.97);
            color: #dcfce7;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
            font-size: 0.92rem;
            line-height: 1.45;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            pointer-events: auto;
            animation: mxToastIn 0.25s ease;
        }
        .mx-toast i { color: #4ade80; margin-top: 2px; flex-shrink: 0; }
        @keyframes mxToastIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="mx-toast-stack" id="mxToastStack" aria-live="polite" aria-atomic="true"></div>
    <div class="container">
        <div class="header">
            <div>
                <h1>Área do Mecânico</h1>
                <p>Bem-vindo, <strong><?= htmlspecialchars($mecanico['nome_mecanico'] ?? 'Mecânico') ?></strong></p>
            </div>
            <div class="mec-header-right">
                <div class="mec-perfil">
                    <span class="mec-avatar" title="Perfil"><?php
                        $nm = (string) ($mecanico['nome_mecanico'] ?? 'M');
                        echo htmlspecialchars(function_exists('mb_substr')
                            ? mb_strtoupper(mb_substr($nm, 0, 1, 'UTF-8'), 'UTF-8')
                            : strtoupper(substr($nm, 0, 1)));
                    ?></span>
                </div>
                <div class="header-buttons">
                    <a href="index.php?preview=1" target="_blank" rel="noopener" class="btn btn-primary"> Ir para o Site</a>
                    <a href="logout.php" class="btn btn-secondary"> Terminar Sessão</a>
                </div>
            </div>
        </div>

        <div class="mx-tabs">
            <a class="mx-tab <?= $tab === 'ativos' ? 'active' : '' ?>" href="?tab=ativos">Trabalhos Ativos</a>
            <a class="mx-tab <?= $tab === 'entregas' ? 'active' : '' ?>" href="?tab=entregas">Entregas<?= count($entregasRows) > 0 ? ' (' . count($entregasRows) . ')' : '' ?></a>
            <a class="mx-tab <?= $tab === 'historico' ? 'active' : '' ?>" href="?tab=historico">Histórico</a>
            <a class="mx-tab <?= $tab === 'estatisticas' ? 'active' : '' ?>" href="?tab=estatisticas">Estatísticas</a>
        </div>

        <?php if ($tab === 'ativos'): ?>
            <div class="mx-kpi">
                <div class="item"><div class="l">Veículos em curso</div><div class="n"><?= count($trabalhosCursoGrupos) ?></div></div>
                <div class="item"><div class="l">Em Andamento</div><div class="n"><?= count($ativosRows) ?></div></div>
                <div class="item"><div class="l">Trabalhos Hoje</div><div class="n"><?= (int)array_reduce($trabalhosCursoRows, function ($c, $r) {
                    return $c + ((isset($r['data_desejada']) && date('Y-m-d', strtotime($r['data_desejada'])) === date('Y-m-d')) ? 1 : 0);
                }, 0) ?></div></div>
                <div class="item"><div class="l">Atrasados</div><div class="n"><?= (int)array_reduce($trabalhosCursoRows, function ($c, $r) {
                    return $c + ((isset($r['data_desejada']) && strtotime($r['data_desejada']) < strtotime(date('Y-m-d'))) ? 1 : 0);
                }, 0) ?></div></div>
            </div>

            <div class="mx-section">
                <div class="mx-title">
                    <h2 style="margin:0;">Trabalhos em curso</h2>
                    <span class="mx-badge"><?= count($trabalhosCursoGrupos) ?> veículo(s) · <?= count($trabalhosCursoRows) ?> serviço(s)</span>
                </div>
                <?php if (!$trabalhosCursoGrupos): ?>
                    <p class="mx-meta">Sem trabalhos atribuídos em curso.</p>
                <?php endif; ?>
                <?php foreach ($trabalhosCursoGrupos as $grupo): ?>
                    <div class="mx-row mx-row--veiculo">
                        <div class="mx-veiculo-head">
                            <div>
                                <strong><?= htmlspecialchars($grupo['nome'] ?: 'Cliente') ?></strong>
                                <span class="mx-veiculo-plate"><?= htmlspecialchars($grupo['matricula'] ?: '-') ?></span>
                            </div>
                            <div class="mx-meta">
                                <?= count($grupo['servicos']) ?> serviço(s) neste veículo
                            </div>
                        </div>
                        <?php foreach ($grupo['servicos'] as $s):
                    $estadoNorm = strtolower(str_replace('_', ' ', (string) ($s['estado_do_trabalho'] ?? '')));
                    $id_carro_srv = am_servico_resolver_id_carro($con, $s);
                    $ped_perfil_srv = $id_carro_srv > 0 && am_veiculo_perfil_ja_foi_solicitado_carro($con, $id_carro_srv);
                    if (strpos($estadoNorm, 'andamento') !== false) {
                        $pillTxt = 'EM ANDAMENTO';
                        $pillClass = 'mx-pill';
                    } else {
                        $pillTxt = 'A INICIAR';
                        $pillClass = 'mx-pill mx-pill--iniciar';
                    }
                    ?>
                    <div class="mx-servico-item">
                        <div>
                            <strong><?= htmlspecialchars($s['tipo_servico'] ?: 'Serviço') ?></strong>
                            <span class="<?= htmlspecialchars($pillClass) ?>"><?= htmlspecialchars($pillTxt) ?></span>
                            <div class="mx-meta">#<?= (int) $s['id'] ?></div>
                        </div>
                        <div class="mx-meta">
                            Data: <?= !empty($s['data_desejada']) ? date('d/m/Y', strtotime($s['data_desejada'])) : '-' ?> <?= !empty($s['hora_desejada']) ? 'às ' . htmlspecialchars($s['hora_desejada']) : '' ?><br>
                            Email: <?= htmlspecialchars($grupo['email'] ?: '-') ?><br>
                            Tel: <?= htmlspecialchars($grupo['telefone'] ?: '-') ?><br>
                            Custo: <?= number_format(am_servico_valor_exibicao_site($s), 2, ',', '.') ?> EUR
                        </div>
                        <div class="mx-actions">
                            <button type="button" class="btn btn-secondary btn-pedir-perfil" data-id="<?= (int) $s['id'] ?>"<?= $id_carro_srv > 0 ? ' data-id-carro="' . (int) $id_carro_srv . '"' : '' ?> title="Notifica o cliente no site para completar o perfil técnico" <?= ($id_carro_srv <= 0 || $ped_perfil_srv) ? 'disabled' : '' ?>><?= $ped_perfil_srv ? 'Dados já pedidos' : 'Pedir dados do carro' ?></button>
                            <button class="btn btn-secondary btn-action-nota" data-id="<?= (int) $s['id'] ?>" data-estado="<?= htmlspecialchars($s['estado_do_trabalho'] ?: 'Por Fazer') ?>">Nota</button>
                            <button class="btn btn-success btn-action-concluir" data-id="<?= (int) $s['id'] ?>">Marcar como Concluído</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($tab === 'entregas'): ?>
            <div class="mx-section">
                <div class="mx-title">
                    <h2 style="margin:0;">Entregas pendentes</h2>
                    <span class="mx-badge"><?= count($entregasGrupos) ?> veículo(s) · <?= count($entregasRows) ?> entrega(s)</span>
                </div>
                <?php if (!$entregasGrupos): ?>
                    <p class="mx-meta">Não há veículos pagos a aguardar validação de código.</p>
                <?php endif; ?>
                <?php foreach ($entregasGrupos as $grupo): ?>
                    <div class="mx-row mx-row--veiculo">
                        <div class="mx-veiculo-head">
                            <div>
                                <strong><?= htmlspecialchars($grupo['nome'] ?: 'Cliente') ?></strong>
                                <span class="mx-veiculo-plate"><?= htmlspecialchars($grupo['matricula'] ?: '-') ?></span>
                            </div>
                        </div>
                        <?php foreach ($grupo['servicos'] as $s): ?>
                    <div class="mx-servico-item">
                        <div>
                            <strong><?= htmlspecialchars($s['tipo_servico'] ?: 'Serviço') ?></strong>
                            <span class="mx-pill mx-pill--concluido">PAGO</span>
                        </div>
                        <div class="mx-meta">
                            Data: <?= !empty($s['data_desejada']) ? date('d/m/Y', strtotime($s['data_desejada'])) : '-' ?> <?= !empty($s['hora_desejada']) ? 'às ' . htmlspecialchars($s['hora_desejada']) : '' ?><br>
                            Preço: <?= number_format(am_servico_valor_exibicao_site($s), 2, ',', '.') ?> EUR
                        </div>
                        <div class="mx-actions">
                            <button type="button" class="btn btn-success btn-validar-codigo" data-id="<?= (int) $s['id'] ?>" data-matricula="<?= htmlspecialchars($grupo['matricula'] ?: '-', ENT_QUOTES) ?>">Validar código</button>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($tab === 'historico'): ?>
            <div class="mx-section">
                <div class="mx-title">
                    <h2 style="margin:0;">Trabalhos Concluídos</h2>
                    <span class="mx-badge"><?= count($historicoGrupos) ?> veículo(s) · <?= count($historicoRows) ?> registo(s)</span>
                </div>
                <?php if (!$historicoGrupos): ?>
                    <p class="mx-meta">Ainda não existem trabalhos concluídos.</p>
                <?php endif; ?>
                <?php foreach ($historicoGrupos as $grupo): ?>
                    <div class="mx-row mx-row--veiculo">
                        <div class="mx-veiculo-head">
                            <div>
                                <strong><?= htmlspecialchars($grupo['nome'] ?: 'Cliente') ?></strong>
                                <span class="mx-veiculo-plate"><?= htmlspecialchars($grupo['matricula'] ?: '-') ?></span>
                            </div>
                            <div class="mx-meta"><?= count($grupo['servicos']) ?> serviço(s)</div>
                        </div>
                        <?php foreach ($grupo['servicos'] as $s): ?>
                    <?php
                        $codigoLevUsadoHist = !empty($s['codigo_levantamento_usado_em']);
                        $estadoHistoricoLabel = $codigoLevUsadoHist ? 'ENTREGUE' : 'CONCLUÍDO';
                    ?>
                    <div class="mx-servico-item">
                        <div>
                            <strong><?= htmlspecialchars($s['tipo_servico'] ?: 'Serviço') ?></strong>
                            <span class="mx-pill mx-pill--concluido"><?= $estadoHistoricoLabel ?></span>
                        </div>
                        <div class="mx-meta">
                            Data: <?= !empty($s['data_desejada']) ? date('d/m/Y', strtotime($s['data_desejada'])) : '-' ?> <?= !empty($s['hora_desejada']) ? 'às '.htmlspecialchars($s['hora_desejada']) : '' ?><br>
                            Preço: <?= number_format(am_servico_valor_exibicao_site($s), 2, ',', '.') ?> EUR
                        </div>
                        <div class="mx-actions">
                            <a href="download_fatura.php?servico_id=<?= (int) $s['id'] ?>" class="btn btn-secondary" target="_blank" rel="noopener">Ver Fatura</a>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mx-kpi">
                <div class="item"><div class="l">Total Concluídos</div><div class="n"><?= $estatConcluidos ?></div></div>
                <div class="item"><div class="l">Este Mês</div><div class="n"><?= $estatMes ?></div></div>
                <div class="item"><div class="l">Receita Total</div><div class="n" style="font-size:28px;"><?= number_format($estatReceitaTotal, 2, ',', '.') ?> EUR</div></div>
                <div class="item"><div class="l">Receita Mensal</div><div class="n" style="font-size:28px;"><?= number_format($estatReceitaMes, 2, ',', '.') ?> EUR</div></div>
            </div>
            <div class="mx-section">
                <h2 style="margin:0 0 12px;">Trabalhos (7 dias)</h2>
                <div class="mx-chart-wrap">
                    <canvas id="chart7dias"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($primeiro_login): ?>
    <div class="popup-overlay" id="passwordPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2>[SEGURANCA] Mudança de Palavra-Chave Obrigatória</h2>
                <p>É a sua primeira vez a entrar. Utilize a palavra-passe inicial <code><?= htmlspecialchars(am_password_padrao_funcionario()) ?></code> e defina uma nova palavra-passe pessoal.</p>
            </div>
            
            <?php if (isset($error_password)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_password) ?></div>
            <?php endif; ?>
            
            <?php if (isset($success_password)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_password) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="nova_password">Nova Palavra-Chave</label>
                    <input type="password" id="nova_password" name="nova_password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirmar_password">Confirmar Palavra-Chave</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required minlength="6">
                </div>

                <div class="popup-actions">
                    <button type="submit" name="mudar_password" class="btn-success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Alterar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Atualização -->
    <div id="modalAtualizacao" class="popup-overlay hidden">
        <div class="popup-content">
            <div class="popup-header">
                <h3><i class="fa-solid fa-tools" aria-hidden="true"></i> Atualizar Reparação</h3>
                <button type="button" onclick="fecharModalAtualizacao()" class="btn-cancel" style="position: absolute; right: 20px; top: 20px;"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            
            <form id="formAtualizacao">
                <input type="hidden" id="servicoIdInput">
                
                <div class="form-group">
                    <label>Estado Atual:</label>
                    <p id="estadoAtualLabel" style="color: #ff6b00; font-weight: bold; padding: 8px; background: rgba(255, 107, 0, 0.1); border-radius: 6px;"></p>
                </div>
                
                <div class="form-group">
                    <label>Novo Estado:</label>
                    <select id="novoEstadoSelect" required>
                        <option value="">-- Selecione --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notas (Obrigatório):</label>
                    <textarea id="notasModal" rows="4" required></textarea>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="fecharModalAtualizacao()">Cancelar</button>
                    <button type="button" class="btn-success" onclick="enviarAtualizacao()"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Conclusão -->
    <div id="modalConclusao" class="popup-overlay popup-overlay-conclusao hidden">
        <div class="popup-content popup-large popup-conclusao" role="dialog" aria-modal="true" aria-labelledby="modalConclusaoTitulo">
            <div class="popup-header">
                <h3 id="modalConclusaoTitulo"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Concluir Reparação</h3>
                <button type="button" onclick="fecharModalConclusao()" class="btn-close-modal" aria-label="Fechar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            
            <form id="formConclusao">
                <input type="hidden" id="servicoIdConclusao">

                <div class="popup-body">
                <div class="form-group">
                    <label for="notasConclusao">Notas (opcional)</label>
                    <textarea id="notasConclusao" rows="3"></textarea>
                </div>
                
                <div class="mx-mo-grid">
                    <div class="form-group">
                        <label for="horasConclusao">Horas trabalhadas</label>
                        <input id="horasConclusao" type="number" step="0.25" min="0.25" required>
                    </div>
                    <div class="form-group">
                        <label for="valorHoraConclusao">Valor por hora (€)</label>
                        <input id="valorHoraConclusao" type="number" step="0.01" min="0.01" value="<?= htmlspecialchars((string) $am_valor_hora_padrao, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Peças e componentes substituídos</label>
                    <div class="mx-pecas-toolbar">
                        <input type="search" id="pecasPesquisa" placeholder="Pesquisar peça…" autocomplete="off">
                    </div>
                    <div class="mx-pecas-painel" id="pecasPainel">
                        <?php foreach ($am_catalogo_pecas as $grupo => $itens): ?>
                            <details class="mx-pecas-grupo" open>
                                <summary><?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?></summary>
                                <?php foreach ($itens as $pecaItem):
                                    $peca = (string) ($pecaItem['nome'] ?? '');
                                    $precoSug = (float) ($pecaItem['preco'] ?? am_peca_preco_sugerido($peca));
                                    $precoSugFmt = number_format($precoSug, 2, '.', '');
                                    $precoSugLabel = number_format($precoSug, 2, ',', '');
                                    ?>
                                    <label class="mx-peca-linha" data-peca-nome="<?= htmlspecialchars($peca, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="checkbox" class="mx-peca-check" value="<?= htmlspecialchars($peca, ENT_QUOTES, 'UTF-8') ?>">
                                        <span><?= htmlspecialchars($peca, ENT_QUOTES, 'UTF-8') ?></span>
                                        <input type="number" class="mx-peca-preco" min="0" step="0.01"
                                            data-preco-sugerido="<?= htmlspecialchars($precoSugFmt, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="≈ <?= htmlspecialchars($precoSugLabel, ENT_QUOTES, 'UTF-8') ?>"
                                            title="Valor sugerido: €<?= htmlspecialchars($precoSugLabel, ENT_QUOTES, 'UTF-8') ?>"
                                            disabled>
                                    </label>
                                <?php endforeach; ?>
                            </details>
                        <?php endforeach; ?>
                    </div>
                    <div class="mx-pecas-custom" id="pecasCustomWrap">
                        <button type="button" class="mx-btn-peca-add" id="btnAddPecaCustom"><i class="fa-solid fa-plus"></i> Adicionar peça à medida</button>
                    </div>
                </div>

                <div id="resumoFechoConclusao" class="mx-resumo-fecho">
                    <h4><i class="fa-solid fa-calculator"></i> Resumo da fatura</h4>
                    <div class="mx-resumo-linha"><span>Mão de obra</span><strong id="resumoMaoObra">€0,00</strong></div>
                    <div class="mx-resumo-linha"><span>Peças</span><strong id="resumoPecas">€0,00</strong></div>
                    <div class="mx-resumo-linha"><span>Subtotal (s/ IVA)</span><strong id="resumoSubtotal">€0,00</strong></div>
                    <div class="mx-resumo-linha"><span>IVA (23%)</span><strong id="resumoIva">€0,00</strong></div>
                    <div class="mx-resumo-linha orcamento" id="resumoOrcamentoRow" hidden><span>Orçamento ao cliente (c/ IVA)</span><strong id="resumoOrcamento">€0,00</strong></div>
                    <div class="mx-resumo-linha total"><span>Total a pagar pelo cliente</span><strong id="resumoTotal">€0,00</strong></div>
                    <p class="mx-resumo-fecho-nota">Horas + peças + IVA. Este é o valor que o cliente terá de liquidar após concluir o serviço.</p>
                    <div id="resumoComparacaoOrcamento" class="mx-resumo-comparacao" hidden></div>
                </div>

                <div id="perfilFechoWrap" class="hidden mx-perfil-fecho">
                    <p class="mx-perfil-fecho-titulo"><i class="fa-solid fa-car-side"></i> Confirmar dados do veículo (ficam no perfil do cliente)</p>
                    <input type="hidden" id="perfilFechoIdCarro" value="">
                    <div class="mx-perfil-grid">
                        <div class="form-group">
                            <label for="pf_prop">Propulsão</label>
                            <select id="pf_prop" disabled aria-disabled="true">
                                <option value="">—</option>
                                <option value="gasolina">Gasolina</option>
                                <option value="gasoleo">Gasóleo</option>
                                <option value="gpl">GPL</option>
                                <option value="hibrido">Híbrido</option>
                                <option value="eletrico">Elétrico</option>
                                <option value="hibrido_plugin">Híbrido plug-in</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pf_km">Km na saída *</label>
                            <input id="pf_km" type="number" min="0" step="1" required>
                        </div>
                        <div class="form-group form-group--full">
                            <label for="pf_motor">Motorização</label>
                            <input id="pf_motor" type="text" placeholder="ex.: 1.5 dCi 110 cv" readonly aria-readonly="true">
                        </div>
                        <div class="form-group form-group--full">
                            <label for="pf_vin">VIN</label>
                            <input id="pf_vin" type="text" maxlength="32" style="text-transform:uppercase;" readonly aria-readonly="true">
                            <?php am_render_bloco_ajuda_vin('mec'); ?>
                        </div>
                        <div class="form-group">
                            <label for="pf_insp">Última inspeção</label>
                            <input id="pf_insp" type="date" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" readonly aria-readonly="true">
                        </div>
                        <div class="form-group form-group--full">
                            <label for="pf_notas">Notas oficina</label>
                            <textarea id="pf_notas" rows="2" readonly aria-readonly="true"></textarea>
                        </div>
                    </div>
                </div>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="fecharModalConclusao()">Cancelar</button>
                    <button type="button" class="btn-success" onclick="enviarConclusao()"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Concluir</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEntregaCodigo" class="popup-overlay hidden">
        <div class="popup-content" role="dialog" aria-modal="true" aria-labelledby="modalEntregaCodigoTitulo">
            <div class="popup-header">
                <h3 id="modalEntregaCodigoTitulo"><i class="fa-solid fa-key" aria-hidden="true"></i> Validar código de levantamento</h3>
                <p>Introduza o código de 6 dígitos fornecido pelo cliente para confirmar a entrega do veículo.</p>
            </div>
            <input type="hidden" id="servicoIdEntregaCodigo">
            <div class="form-group">
                <label for="codigoEntregaInput">Código do cliente</label>
                <input id="codigoEntregaInput" type="text" inputmode="numeric" maxlength="7" placeholder="123 456" autocomplete="one-time-code">
                <div class="mx-entrega-help" id="entregaCodigoHint"></div>
            </div>
            <div class="popup-actions">
                <button type="button" class="btn-cancel" onclick="fecharModalEntregaCodigo()">Cancelar</button>
                <button type="button" class="btn-success" onclick="confirmarEntregaCodigo()"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Confirmar entrega</button>
            </div>
        </div>
    </div>

    <script>
    var AM_VALOR_HORA_PADRAO = <?= json_encode((float) $am_valor_hora_padrao) ?>;
    var AM_ORCAMENTO_CONCLUSAO = 0;

    function comparacaoOrcamentoConclusao(total, orcamentoComIva) {
        if (!orcamentoComIva || orcamentoComIva <= 0) {
            return null;
        }
        var diff = Math.round((total - orcamentoComIva) * 100) / 100;
        var tol = 0.5;
        if (Math.abs(diff) <= tol) {
            return {
                status: 'dentro',
                rotulo: 'Dentro do orçamento',
                mensagem: 'O total está alinhado com o orçamento apresentado ao cliente.'
            };
        }
        if (diff > 0) {
            return {
                status: 'acima',
                rotulo: 'Acima do orçamento',
                mensagem: 'O total ficou ' + formatEuroConclusao(diff) + ' acima do orçamento ao cliente.'
            };
        }
        return {
            status: 'abaixo',
            rotulo: 'Abaixo do orçamento',
            mensagem: 'O total ficou ' + formatEuroConclusao(Math.abs(diff)) + ' abaixo do orçamento ao cliente.'
        };
    }

    function formatEuroConclusao(valor) {
        return '€' + (Math.round(valor * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function precoEfetivoCampoPeca(precoEl) {
        if (!precoEl) return null;
        var raw = precoEl.value.trim();
        if (raw !== '') {
            var n = parseFloat(raw.replace(',', '.'));
            return isNaN(n) ? null : n;
        }
        var sug = parseFloat(precoEl.getAttribute('data-preco-sugerido'));
        return isNaN(sug) ? null : sug;
    }

    function aplicarPrecoSugeridoPeca(precoEl) {
        if (!precoEl || precoEl.value.trim() !== '') return;
        var sug = parseFloat(precoEl.getAttribute('data-preco-sugerido'));
        if (!isNaN(sug) && sug > 0) {
            precoEl.value = sug.toFixed(2);
            precoEl.classList.add('mx-preco-sugerido');
        }
    }

    function coletarPecasConclusao() {
        var lista = [];
        document.querySelectorAll('.mx-peca-linha .mx-peca-check:checked').forEach(function (chk) {
            var linha = chk.closest('.mx-peca-linha');
            var precoEl = linha ? linha.querySelector('.mx-peca-preco') : null;
            lista.push({ nome: chk.value, preco: precoEfetivoCampoPeca(precoEl) });
        });
        document.querySelectorAll('.mx-peca-custom-linha').forEach(function (linha) {
            var nomeEl = linha.querySelector('.mx-peca-custom-nome');
            var precoEl = linha.querySelector('.mx-peca-custom-preco');
            var nome = nomeEl ? nomeEl.value.trim() : '';
            if (!nome) return;
            var precoRaw = precoEl ? precoEl.value.trim() : '';
            var preco = precoRaw === '' ? null : parseFloat(precoRaw.replace(',', '.'));
            lista.push({ nome: nome, preco: preco });
        });
        return lista;
    }

    function atualizarResumoConclusao() {
        var horas = parseFloat((document.getElementById('horasConclusao') || {}).value) || 0;
        var valorHora = parseFloat((document.getElementById('valorHoraConclusao') || {}).value) || 0;
        var pecas = coletarPecasConclusao();
        var totalMo = horas > 0 && valorHora > 0 ? Math.round(horas * valorHora * 100) / 100 : 0;
        var totalPecas = pecas.reduce(function (s, p) {
            var v = p.preco === null || isNaN(p.preco) ? 0 : parseFloat(p.preco);
            return s + v;
        }, 0);
        totalPecas = Math.round(totalPecas * 100) / 100;
        var subtotal = Math.round((totalMo + totalPecas) * 100) / 100;
        var iva = Math.round(subtotal * 0.23 * 100) / 100;
        var total = Math.round((subtotal + iva) * 100) / 100;
        function setTxt(id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = formatEuroConclusao(val);
        }
        setTxt('resumoMaoObra', totalMo);
        setTxt('resumoPecas', totalPecas);
        setTxt('resumoSubtotal', subtotal);
        setTxt('resumoIva', iva);
        setTxt('resumoTotal', total);
        var orcRow = document.getElementById('resumoOrcamentoRow');
        var orcEl = document.getElementById('resumoOrcamento');
        var cmpEl = document.getElementById('resumoComparacaoOrcamento');
        if (AM_ORCAMENTO_CONCLUSAO > 0 && orcRow && orcEl) {
            orcRow.hidden = false;
            orcEl.textContent = formatEuroConclusao(AM_ORCAMENTO_CONCLUSAO);
            var cmp = comparacaoOrcamentoConclusao(total, AM_ORCAMENTO_CONCLUSAO);
            if (cmpEl && cmp) {
                cmpEl.hidden = false;
                cmpEl.className = 'mx-resumo-comparacao mx-resumo-comparacao--' + cmp.status;
                cmpEl.innerHTML = '<strong>' + cmp.rotulo + '</strong> — ' + cmp.mensagem;
            }
        } else {
            if (orcRow) orcRow.hidden = true;
            if (cmpEl) {
                cmpEl.hidden = true;
                cmpEl.className = 'mx-resumo-comparacao';
                cmpEl.textContent = '';
            }
        }
    }

    function resetPecasConclusao() {
        document.querySelectorAll('.mx-peca-check').forEach(function (chk) {
            chk.checked = false;
            var linha = chk.closest('.mx-peca-linha');
            var preco = linha ? linha.querySelector('.mx-peca-preco') : null;
            if (preco) {
                preco.value = '';
                preco.disabled = true;
                preco.classList.remove('mx-preco-sugerido');
            }
        });
        var customWrap = document.getElementById('pecasCustomWrap');
        if (customWrap) {
            customWrap.querySelectorAll('.mx-peca-custom-linha').forEach(function (el) { el.remove(); });
        }
        var pesquisa = document.getElementById('pecasPesquisa');
        if (pesquisa) pesquisa.value = '';
        document.querySelectorAll('.mx-peca-linha.is-hidden').forEach(function (el) { el.classList.remove('is-hidden'); });
        var valorHora = document.getElementById('valorHoraConclusao');
        if (valorHora) valorHora.value = String(AM_VALOR_HORA_PADRAO);
        atualizarResumoConclusao();
    }

    function adicionarPecaCustomConclusao() {
        var wrap = document.getElementById('pecasCustomWrap');
        var btn = document.getElementById('btnAddPecaCustom');
        if (!wrap || !btn) return;
        var linha = document.createElement('div');
        linha.className = 'mx-peca-custom-linha';
        linha.innerHTML = '<input type="text" class="mx-peca-custom-nome" placeholder="Nome da peça">'
            + '<input type="number" class="mx-peca-custom-preco" min="0" step="0.01" placeholder="€">'
            + '<button type="button" class="mx-btn-peca-del" title="Remover">×</button>';
        wrap.insertBefore(linha, btn);
        linha.querySelector('.mx-peca-custom-nome').addEventListener('input', atualizarResumoConclusao);
        linha.querySelector('.mx-peca-custom-preco').addEventListener('input', atualizarResumoConclusao);
        linha.querySelector('.mx-btn-peca-del').addEventListener('click', function () {
            linha.remove();
            atualizarResumoConclusao();
        });
        linha.querySelector('.mx-peca-custom-nome').focus();
        atualizarResumoConclusao();
    }

    function filtrarPecasConclusao(termo) {
        var q = (termo || '').toLowerCase().trim();
        document.querySelectorAll('.mx-peca-linha').forEach(function (linha) {
            var nome = (linha.getAttribute('data-peca-nome') || '').toLowerCase();
            linha.classList.toggle('is-hidden', q !== '' && nome.indexOf(q) === -1);
        });
    }

    function abrirModalAtualizacao(servicoId, estadoAtual) {
        document.getElementById('servicoIdInput').value = servicoId;
        // normaliza apresentação do estado (substitui _ por espaço)
        const estadoApresentacao = (estadoAtual || '').replace(/_/g, ' ');
        document.getElementById('estadoAtualLabel').textContent = estadoApresentacao;
        
        const select = document.getElementById('novoEstadoSelect');
        select.innerHTML = '<option value="">-- Selecione --</option>';
        
        // comparar de forma case-insensitive e tratar underscores
        const estadoNorm = (estadoAtual || '').replace(/_/g, ' ').toLowerCase();
        if (estadoNorm.includes('andamento')) {
            select.appendChild(new Option('Pronto', 'Pronto'));
        }
        
        if (estadoNorm.includes('pronto')) {
            select.appendChild(new Option('Concluído', 'Concluído'));
        }
        
        document.getElementById('modalAtualizacao').classList.remove('hidden');
    }

    function fecharModalAtualizacao() {
        document.getElementById('modalAtualizacao').classList.add('hidden');
        document.getElementById('formAtualizacao').reset();
    }

    function abrirModalConclusao(servicoId) {
        document.getElementById('servicoIdConclusao').value = servicoId;
        AM_ORCAMENTO_CONCLUSAO = 0;
        resetPecasConclusao();
        var horasEl = document.getElementById('horasConclusao');
        if (horasEl) horasEl.value = '';
        var wrap = document.getElementById('perfilFechoWrap');
        var pfKm = document.getElementById('pf_km');
        if (wrap) wrap.classList.add('hidden');
        if (pfKm) {
            pfKm.removeAttribute('required');
            pfKm.removeAttribute('min');
            pfKm.dataset.kmAtual = '';
        }
        var hid = document.getElementById('perfilFechoIdCarro');
        if (hid) hid.value = '';
        document.getElementById('modalConclusao').classList.remove('hidden');
        document.body.classList.add('mx-modal-open');
        var bodyEl = document.querySelector('#modalConclusao .popup-body');
        if (bodyEl) bodyEl.scrollTop = 0;
        fetch('mecanico_perfil_prefill.php?servico_id=' + encodeURIComponent(servicoId), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.ok) {
                    AM_ORCAMENTO_CONCLUSAO = parseFloat(j.orcamento_estimado_com_iva) || 0;
                    atualizarResumoConclusao();
                }
                if (j && j.ok && horasEl && j.horas_sugeridas > 0) {
                    horasEl.value = String(j.horas_sugeridas);
                    atualizarResumoConclusao();
                }
                if (!j || !j.ok || !j.id_carro) return;
                if (wrap) wrap.classList.remove('hidden');
                if (pfKm) pfKm.setAttribute('required', 'required');
                if (hid) hid.value = String(j.id_carro);
                var p = j.perfil || {};
                function setV(id, val) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.value = (val != null && val !== undefined && val !== '') ? String(val) : '';
                }
                setV('pf_prop', p.propulsao);
                setV('pf_motor', p.motorizacao);
                setV('pf_vin', p.vin);
                setV('pf_km', p.km_atual);
                if (pfKm) {
                    var kmAtual = (p && p.km_atual !== null && p.km_atual !== undefined && p.km_atual !== '') ? parseInt(p.km_atual, 10) : NaN;
                    if (!isNaN(kmAtual) && kmAtual >= 0) {
                        pfKm.setAttribute('min', String(kmAtual));
                        pfKm.dataset.kmAtual = String(kmAtual);
                    } else {
                        pfKm.removeAttribute('min');
                        pfKm.dataset.kmAtual = '';
                    }
                }
                setV('pf_insp', p.data_ultima_inspecao ? String(p.data_ultima_inspecao).slice(0, 10) : '');
                setV('pf_notas', p.notas_oficina);
            })
            .catch(function () {});
    }

    function fecharModalConclusao() {
        document.getElementById('modalConclusao').classList.add('hidden');
        document.body.classList.remove('mx-modal-open');
        document.getElementById('formConclusao').reset();
        resetPecasConclusao();
        var w = document.getElementById('perfilFechoWrap');
        if (w) w.classList.add('hidden');
        var k = document.getElementById('pf_km');
        if (k) {
            k.removeAttribute('required');
            k.removeAttribute('min');
            k.dataset.kmAtual = '';
        }
        var hid = document.getElementById('perfilFechoIdCarro');
        if (hid) hid.value = '';
    }

    function enviarAtualizacao() {
        const servicoId = document.getElementById('servicoIdInput').value;
        const novoEstado = document.getElementById('novoEstadoSelect').value;
        const notas = document.getElementById('notasModal').value;
        const notasField = document.getElementById('notasModal');
        
        if (!novoEstado) {
            return;
        }
        if (!notas.trim()) {
            setFieldError(notasField, 'As notas são obrigatórias.');
            return;
        }
        clearFieldError(notasField);
        
        const formData = new FormData();
        formData.append('servico_id', servicoId);
        formData.append('novo_estado', novoEstado);
        formData.append('notas', notas);
        
        fetch('atualizar_estado_reparacao.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fecharModalAtualizacao();
                window.location.reload();
            } else {
                setFieldError(notasField, data.error || 'Não foi possível atualizar.');
            }
        })
        .catch(error => {
            setFieldError(notasField, 'Erro de rede. Tente novamente.');
        });
    }

    function mostrarToastMecanico(message) {
        var stack = document.getElementById('mxToastStack');
        if (!stack || !message) return;
        var el = document.createElement('div');
        el.className = 'mx-toast';
        el.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i><span></span>';
        el.querySelector('span').textContent = message;
        stack.appendChild(el);
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 5000);
    }

    function abrirModalEntregaCodigo(servicoId, matricula) {
        document.getElementById('servicoIdEntregaCodigo').value = servicoId || '';
        document.getElementById('codigoEntregaInput').value = '';
        document.getElementById('entregaCodigoHint').textContent = matricula ? ('Veículo: ' + matricula) : '';
        document.getElementById('modalEntregaCodigo').classList.remove('hidden');
        document.body.classList.add('mx-modal-open');
        setTimeout(function () {
            var inp = document.getElementById('codigoEntregaInput');
            if (inp) inp.focus();
        }, 30);
    }

    function fecharModalEntregaCodigo() {
        document.getElementById('modalEntregaCodigo').classList.add('hidden');
        document.body.classList.remove('mx-modal-open');
    }

    function confirmarEntregaCodigo() {
        var servicoId = document.getElementById('servicoIdEntregaCodigo').value;
        var codigoField = document.getElementById('codigoEntregaInput');
        var codigo = (codigoField.value || '').replace(/\D+/g, '');
        clearFieldError(codigoField);

        if (codigo.length !== 6) {
            setFieldError(codigoField, 'Indique o código de 6 dígitos.');
            return;
        }

        var fd = new FormData();
        fd.append('servico_id', servicoId);
        fd.append('codigo', codigo);

        fetch('mecanico_validar_levantamento.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data && data.success) {
                fecharModalEntregaCodigo();
                mostrarToastMecanico(data.message || 'Entrega confirmada com sucesso.');
                setTimeout(function () { window.location.reload(); }, 1800);
                return;
            }
            setFieldError(codigoField, (data && data.error) ? data.error : 'Não foi possível validar o código.');
        })
        .catch(function () {
            setFieldError(codigoField, 'Erro de rede. Tente novamente.');
        });
    }

    function enviarConclusao() {
        const servicoId = document.getElementById('servicoIdConclusao').value;
        const notas = document.getElementById('notasConclusao').value;
        const horas = document.getElementById('horasConclusao').value;
        const horasField = document.getElementById('horasConclusao');
        const valorHoraField = document.getElementById('valorHoraConclusao');
        const valorHora = valorHoraField ? valorHoraField.value : '';
        clearFieldError(horasField);
        if (valorHoraField) clearFieldError(valorHoraField);
        var pfKmEl = document.getElementById('pf_km');
        if (pfKmEl) clearFieldError(pfKmEl);
        
        if (!horas || parseFloat(horas) <= 0) {
            setFieldError(horasField, 'Horas inválidas.');
            return;
        }
        if (!valorHora || parseFloat(valorHora) <= 0) {
            setFieldError(valorHoraField, 'Indique o valor por hora.');
            return;
        }

        var pecasLista = coletarPecasConclusao();
        var pecaSemPreco = pecasLista.find(function (p) {
            return p.nome && (p.preco === null || isNaN(p.preco) || p.preco < 0);
        });
        if (pecaSemPreco) {
            setFieldError(horasField, 'Indique o preço de todas as peças selecionadas.');
            return;
        }

        var wrap = document.getElementById('perfilFechoWrap');
        var idCarro = (document.getElementById('perfilFechoIdCarro') || {}).value || '';
        var usarPerfil = wrap && !wrap.classList.contains('hidden') && idCarro;
        if (usarPerfil && pfKmEl && (!pfKmEl.value.trim() || parseInt(pfKmEl.value, 10) < 1)) {
            setFieldError(pfKmEl, 'Indica os quilómetros na saída (mín. 1).');
            return;
        }
        if (usarPerfil && pfKmEl) {
            var kmBase = parseInt(pfKmEl.dataset.kmAtual || '', 10);
            var kmNovo = parseInt(pfKmEl.value, 10);
            if (!isNaN(kmBase) && !isNaN(kmNovo) && kmNovo < kmBase) {
                setFieldError(pfKmEl, 'Os km na saída não podem ser inferiores aos já registados.');
                return;
            }
        }
        
        function doConcluirState() {
            const formData = new FormData();
            formData.append('servico_id', servicoId);
            formData.append('novo_estado', 'Concluído');
            formData.append('notas', notas);
            formData.append('horas', horas);
            formData.append('valor_hora', valorHora);
            formData.append('pecas_json', JSON.stringify(pecasLista));
            
            fetch('atualizar_estado_reparacao.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        throw new Error(text ? text.slice(0, 180) : 'Resposta inválida do servidor.');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    fecharModalConclusao();
                    mostrarToastMecanico(data.message || 'Serviço concluído com sucesso! O cliente foi notificado.');
                    setTimeout(function () {
                        window.location.reload();
                    }, 2200);
                } else {
                    setFieldError(horasField, data.error || 'Não foi possível concluir.');
                }
            })
            .catch(function (err) {
                setFieldError(horasField, (err && err.message) ? err.message : 'Erro de rede. Tente novamente.');
            });
        }

        if (usarPerfil) {
            const fd2 = new FormData();
            fd2.append('servico_id', servicoId);
            fd2.append('km_atual', document.getElementById('pf_km').value);
            fetch('mecanico_guardar_perfil_fecho.php', { method: 'POST', body: fd2, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j2) {
                    if (!j2 || !j2.ok) {
                        setFieldError(horasField, (j2 && j2.error) ? j2.error : 'Erro ao guardar perfil do veículo.');
                        return;
                    }
                    doConcluirState();
                })
                .catch(function () {
                    setFieldError(horasField, 'Erro de rede ao guardar perfil.');
                });
        } else {
            doConcluirState();
        }
    }

    function setFieldError(field, message) {
        if (!field) return;
        field.style.borderColor = '#dc3545';
        let err = field.parentElement.querySelector('.field-error');
        if (!err) {
            err = document.createElement('div');
            err.className = 'field-error';
            field.parentElement.appendChild(err);
        }
        err.textContent = message;
    }

    function clearFieldError(field) {
        if (!field) return;
        field.style.borderColor = '';
        const err = field.parentElement.querySelector('.field-error');
        if (err) err.remove();
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        var horasEl = document.getElementById('horasConclusao');
        var valorHoraEl = document.getElementById('valorHoraConclusao');
        if (horasEl) horasEl.addEventListener('input', atualizarResumoConclusao);
        if (valorHoraEl) valorHoraEl.addEventListener('input', atualizarResumoConclusao);
        var pesquisaPecas = document.getElementById('pecasPesquisa');
        if (pesquisaPecas) pesquisaPecas.addEventListener('input', function () { filtrarPecasConclusao(pesquisaPecas.value); });
        var btnAddPeca = document.getElementById('btnAddPecaCustom');
        if (btnAddPeca) btnAddPeca.addEventListener('click', adicionarPecaCustomConclusao);
        document.querySelectorAll('.mx-peca-check').forEach(function (chk) {
            chk.addEventListener('change', function () {
                var linha = chk.closest('.mx-peca-linha');
                var preco = linha ? linha.querySelector('.mx-peca-preco') : null;
                if (!preco) return;
                preco.disabled = !chk.checked;
                if (chk.checked) {
                    aplicarPrecoSugeridoPeca(preco);
                    preco.focus();
                    preco.select();
                } else {
                    preco.value = '';
                    preco.classList.remove('mx-preco-sugerido');
                }
                atualizarResumoConclusao();
            });
        });
        document.querySelectorAll('.mx-peca-preco').forEach(function (inp) {
            inp.addEventListener('input', function () {
                inp.classList.remove('mx-preco-sugerido');
                atualizarResumoConclusao();
            });
        });
        document.querySelectorAll('.btn-validar-codigo').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModalEntregaCodigo(btn.getAttribute('data-id'), btn.getAttribute('data-matricula') || '');
            });
        });
        var codigoEntregaInput = document.getElementById('codigoEntregaInput');
        if (codigoEntregaInput) {
            codigoEntregaInput.addEventListener('input', function () {
                var digits = codigoEntregaInput.value.replace(/\D+/g, '').slice(0, 6);
                codigoEntregaInput.value = digits.length > 3 ? (digits.slice(0, 3) + ' ' + digits.slice(3)) : digits;
            });
            codigoEntregaInput.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    confirmarEntregaCodigo();
                }
            });
        }
        atualizarResumoConclusao();

        const initialPulse = <?= json_encode($mecanico_pulse_inicial, JSON_UNESCAPED_UNICODE) ?>;
        let prevPollPulse = initialPulse;
        let pollCount = 0;

        const tabAtivos = <?= $tab === 'ativos' ? 'true' : 'false' ?>;

        function pollPulse() {
            fetch('funcionarios_pulse.php', { credentials: 'same-origin', cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (!j || !j.ok || !j.pulse) return;
                    pollCount += 1;
                    const mudou = pollCount > 1 && j.pulse !== prevPollPulse;
                    if (mudou && tabAtivos) {
                        const modalAberto = document.querySelector('.popup-overlay:not(.hidden)');
                        if (!modalAberto) {
                            window.location.reload();
                            return;
                        }
                    }
                    prevPollPulse = j.pulse;
                })
                .catch(function() {});
        }

        setInterval(pollPulse, 25000);
        setTimeout(pollPulse, 4000);

        document.addEventListener('click', function(e) {
            const btnPedir = e.target.closest('.btn-pedir-perfil');
            if (btnPedir && !btnPedir.disabled) {
                e.preventDefault();
                const origText = btnPedir.textContent;
                btnPedir.disabled = true;
                btnPedir.textContent = 'A enviar…';
                const fd = new FormData();
                fd.append('servico_id', btnPedir.getAttribute('data-id'));
                fetch('mecanico_pedir_perfil.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        const actions = btnPedir.closest('.mx-actions') || btnPedir.parentElement;
                        const errEl = actions ? actions.querySelector('.mx-pedir-perfil-erro') : null;
                        if (j && j.ok) {
                            const idCarro = btnPedir.getAttribute('data-id-carro');
                            const alvos = idCarro
                                ? document.querySelectorAll('.btn-pedir-perfil[data-id-carro="' + idCarro + '"]')
                                : [btnPedir];
                            alvos.forEach(function (btn) {
                                btn.disabled = true;
                                btn.textContent = 'Dados já pedidos';
                                btn.title = 'O cliente foi notificado para completar o perfil técnico';
                            });
                            if (errEl) errEl.remove();
                            return;
                        }
                        btnPedir.disabled = false;
                        btnPedir.textContent = origText;
                        const msg = (j && j.error) ? j.error : 'Erro ao enviar pedido.';
                        if (actions) {
                            let el = errEl;
                            if (!el) {
                                el = document.createElement('p');
                                el.className = 'mx-pedir-perfil-erro mx-meta';
                                el.style.cssText = 'margin:8px 0 0;color:#fca5a5;';
                                actions.appendChild(el);
                            }
                            el.textContent = msg;
                        }
                    })
                    .catch(function () {
                        btnPedir.disabled = false;
                        btnPedir.textContent = origText;
                    });
                return;
            }

            const btnAtualizar = e.target.closest('.btn-action-nota');
            if (btnAtualizar) {
                e.preventDefault();
                abrirModalAtualizacao(btnAtualizar.dataset.id, btnAtualizar.dataset.estado);
            }

            const btnConcluir = e.target.closest('.btn-action-concluir');
            if (btnConcluir) {
                e.preventDefault();
                abrirModalConclusao(btnConcluir.dataset.id);
            }
        });

        const chartEl = document.getElementById('chart7dias');
        if (chartEl && window.Chart) {
            const labels = <?= json_encode($diasLabels) ?>;
            const values = <?= json_encode($diasVals) ?>;
            new Chart(chartEl, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Concluídos',
                        data: values,
                        borderColor: '#ff7a1a',
                        backgroundColor: 'rgba(255, 122, 26, 0.15)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#a1a1aa', precision: 0, stepSize: 1 }, grid: { color: 'rgba(255,255,255,.08)' } },
                        x: { ticks: { color: '#a1a1aa' }, grid: { color: 'rgba(255,255,255,.06)' } }
                    }
                }
            });
        }
        
        // Fechar modais
        document.querySelectorAll('.popup-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    if (this.id === 'modalConclusao') {
                        document.body.classList.remove('mx-modal-open');
                    }
                }
            });
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.popup-overlay').forEach(modal => {
                    modal.classList.add('hidden');
                });
                document.body.classList.remove('mx-modal-open');
            }
        });
    });
    </script>
</body>
</html>