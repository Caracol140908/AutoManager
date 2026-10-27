<?php
session_start();
require_once __DIR__ . '/ligar.php';
require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';

/**
 * Permite apenas URLs internas relativas (sem scheme) para o botão Voltar.
 */
function rastreamento_sanitize_return(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (strpos($raw, '://') !== false || strpos($raw, '//') === 0) {
        return '';
    }
    if (!preg_match('~^[a-zA-Z0-9_\-\.]+\.php(\?[^#]*)?(#[\w\-]*)?$~', $raw)) {
        return '';
    }
    return $raw;
}

$is_admin_sess = isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$utilizador_ok = !empty($_SESSION['user']['id_utilizador']);

if (!$is_admin_sess && !$utilizador_ok) {
    header('Location: login.php');
    exit;
}

$voltar_href = rastreamento_sanitize_return((string)($_GET['return'] ?? ''));
if ($voltar_href === '') {
    $voltar_href = $is_admin_sess ? 'indexadmin.php?tab=marcacoes#marcacoes' : 'configuracoes.php?seccao=marcacoes';
}

$id_utilizador = $utilizador_ok ? (filter_var($_SESSION['user']['id_utilizador'], FILTER_VALIDATE_INT) ?: 0) : 0;
$user_email = $utilizador_ok
    ? am_cliente_email_canonico($con, $id_utilizador, (string) ($_SESSION['user']['email'] ?? ''))
    : '';
$servico_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

$colIdMecanico = false;
$colMecanicoId = false;
$chkMec = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_mecanico'");
if ($chkMec && $chkMec->num_rows > 0) {
    $colIdMecanico = true;
}
$chkMec = @$con->query("SHOW COLUMNS FROM servico LIKE 'mecanico_id'");
if ($chkMec && $chkMec->num_rows > 0) {
    $colMecanicoId = true;
}
$mecanicoJoinExpr = 'NULL';
if ($colIdMecanico && $colMecanicoId) {
    $mecanicoJoinExpr = 'COALESCE(s.id_mecanico, s.mecanico_id)';
} elseif ($colIdMecanico) {
    $mecanicoJoinExpr = 's.id_mecanico';
} elseif ($colMecanicoId) {
    $mecanicoJoinExpr = 's.mecanico_id';
}

function rastreamento_mecanico_nome(mysqli $con, array $servico): string
{
    $nome = trim((string) ($servico['nome_mecanico'] ?? ''));
    if ($nome !== '') {
        return $nome;
    }
    $idMec = (int) ($servico['mecanico_id'] ?? $servico['id_mecanico'] ?? 0);
    if ($idMec <= 0) {
        return 'Ainda não atribuído';
    }
    $st = $con->prepare('SELECT nome_mecanico FROM mecanicos WHERE id_mecanico = ? LIMIT 1');
    if (!$st) {
        return 'Mecânico #' . $idMec;
    }
    $st->bind_param('i', $idMec);
    if ($st->execute()) {
        $row = $st->get_result()->fetch_assoc();
        $nomeDb = trim((string) ($row['nome_mecanico'] ?? ''));
        if ($nomeDb !== '') {
            $st->close();

            return $nomeDb;
        }
    }
    $st->close();

    return 'Mecânico #' . $idMec;
}

function rastreamento_servico_id_mecanico(array $servico): int
{
    return (int) ($servico['mecanico_id'] ?? $servico['id_mecanico'] ?? 0);
}

$servico = null;
if ($servico_id > 0) {
    if ($is_admin_sess) {
        $mecanicoJoinSql = $mecanicoJoinExpr !== 'NULL'
            ? "LEFT JOIN mecanicos m ON m.id_mecanico = $mecanicoJoinExpr"
            : '';
        $mecanicoSelectSql = $mecanicoJoinSql !== ''
            ? 'm.nome_mecanico, m.email_mecanico, m.telefone_mecanico'
            : 'NULL AS nome_mecanico, NULL AS email_mecanico, NULL AS telefone_mecanico';
        $sql_admin = "SELECT s.*, $mecanicoSelectSql
                FROM servico s
                $mecanicoJoinSql
                WHERE s.id = ?
                LIMIT 1";
        $stmt = $con->prepare($sql_admin);
        if ($stmt) {
            $stmt->bind_param('i', $servico_id);
            $stmt->execute();
            $servico = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } else {
        $servico = am_cliente_carregar_servico_por_id($con, $servico_id, $id_utilizador, $user_email);
        if (is_array($servico) && $mecanicoJoinExpr !== 'NULL') {
            $idMecTrack = (int) ($servico['mecanico_id'] ?? $servico['id_mecanico'] ?? 0);
            if ($idMecTrack > 0) {
                $stMec = $con->prepare('SELECT nome_mecanico, email_mecanico, telefone_mecanico FROM mecanicos WHERE id_mecanico = ? LIMIT 1');
                if ($stMec) {
                    $stMec->bind_param('i', $idMecTrack);
                    if ($stMec->execute()) {
                        $mecRow = $stMec->get_result()->fetch_assoc();
                        if (is_array($mecRow)) {
                            $servico = array_merge($servico, $mecRow);
                        }
                    }
                    $stMec->close();
                }
            }
        }
    }
}

if (!$servico) {
    $_SESSION['flash_mensagem'] = 'Serviço não encontrado ou não tem permissão para aceder.';
    $_SESSION['flash_mensagem_tipo'] = 'erro';
    $fallback = $is_admin_sess ? 'indexadmin.php?tab=marcacoes' : 'configuracoes.php?seccao=marcacoes';
    header('Location: ' . $fallback);
    exit;
}

$reparadoTrack = am_servico_reparar_valores_fiscais_se_inconsistentes($con, $servico_id, $servico);
if (is_array($reparadoTrack)) {
    $servico = array_merge($servico, $reparadoTrack);
}
$syncTrack = am_servico_sincronizar_divida_pagamento($con, $servico_id);
if (is_array($syncTrack)) {
    $servico = array_merge($servico, $syncTrack);
}

if (am_servico_esta_concluido($servico)) {
    $reparadoTrack = am_servico_reparar_valores_fiscais_se_inconsistentes($con, $servico_id, $servico);
    if (is_array($reparadoTrack)) {
        $servico = array_merge($servico, $reparadoTrack);
    }
}
$syncTrack = am_servico_sincronizar_divida_pagamento($con, $servico_id);
if (is_array($syncTrack)) {
    $servico = array_merge($servico, $syncTrack);
}

// Estados da ordem de trabalho
$estados_ordem = [
    'Por Fazer' => 1,
    'Aguardar Preparação' => 2,
    'Em Andamento' => 3,
    'Pronto' => 4,
    'Concluído' => 5
];

$estado_atual = htmlspecialchars($servico['estado_do_trabalho'] ?? 'Por Fazer');
$progresso = isset($estados_ordem[$estado_atual]) ? (($estados_ordem[$estado_atual] - 1) / 4) * 100 : 0;

$estado_icones = [
    'Por Fazer' => '📝',
    'Aguardar Preparação' => '⏳',
    'Em Andamento' => '🛠️',
    'Pronto' => '✅',
    'Concluído' => '🎉'
];

$estado_cores = [
    'Por Fazer' => '#FF6B00',
    'Aguardar Preparação' => '#FFA500',
    'Em Andamento' => '#4A90E2',
    'Pronto' => '#7ED321',
    'Concluído' => '#50E3C2'
];

// Preparar datas formatadas
$data_registo = !empty($servico['data_registo']) ? date('d/m/Y H:i', strtotime($servico['data_registo'])) : 'N/A';
$data_conclusao = !empty($servico['data_conclusao']) ? date('d/m/Y H:i', strtotime($servico['data_conclusao'])) : null;
$sla_horas = (int)($servico['sla_prometido_horas'] ?? 0);
$sla_estado = strtolower(trim((string)($servico['sla_estado'] ?? 'nao_aplicavel')));
$sla_limite = !empty($servico['sla_limite_at']) ? date('d/m/Y H:i', strtotime((string)$servico['sla_limite_at'])) : null;
$sla_comp = (float)($servico['sla_compensacao_valor'] ?? 0);

$estado_orc = strtolower(trim((string)($servico['estado_orcamento'] ?? '')));
$estado_orc_cliente = strtolower(trim((string)($servico['estado_orcamento_cliente'] ?? '')));
$estado_pagamento = strtolower(trim((string)($servico['estado_pagamento'] ?? '')));
$tem_orcamento = $estado_orc === 'aprovado' || (float)($servico['orcamento_aprovado'] ?? 0) > 0;
$orcamento_aprovado_cliente = in_array($estado_orc_cliente, ['aceite', 'aceito'], true);
$id_mecanico_srv = rastreamento_servico_id_mecanico($servico);
$mecanico_nome_srv = rastreamento_mecanico_nome($con, $servico);
$mecanico_atribuido = $id_mecanico_srv > 0 || $mecanico_nome_srv !== 'Ainda não atribuído';
$estado_trabalho_lower = strtolower(trim((string)($servico['estado_do_trabalho'] ?? $servico['estado'] ?? 'por fazer')));
$estado_trabalho_norm = str_replace(['_', '-'], ' ', $estado_trabalho_lower);
$is_concluido = am_servico_esta_concluido($servico)
    || strpos($estado_trabalho_norm, 'conclu') !== false
    || strpos($estado_trabalho_norm, 'entreg') !== false;
$is_em_andamento = !$is_concluido && (
    strpos($estado_trabalho_norm, 'andamento') !== false
    || strpos($estado_trabalho_norm, 'progresso') !== false
    || strpos($estado_trabalho_norm, 'pronto') !== false
);
$is_pago = am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null) === 'pago';

if (am_servico_tem_fecho_mecanico($servico)) {
    $valor_track = am_servico_total_fatura_final($servico);
    $valor_track_rotulo = $is_pago ? 'Total do trabalho (c/ IVA)' : 'Total a pagar (c/ IVA)';
} else {
    $valor_track = am_servico_valor_exibicao_site($servico);
    $valor_track_rotulo = $valor_track > 0.009 ? 'Orçamento aprovado (s/ IVA)' : 'Valor a definir';
}

$timeline_steps = [
    ['title' => 'Pedido recebido', 'desc' => 'Recebemos a sua marcação.', 'done' => true],
    ['title' => 'Orçamento enviado', 'desc' => 'O orçamento foi definido e enviado para si.', 'done' => $tem_orcamento],
    ['title' => 'Orçamento aprovado', 'desc' => 'O serviço foi autorizado para execução.', 'done' => $orcamento_aprovado_cliente],
    ['title' => 'Atribuído ao mecânico', 'desc' => $mecanico_atribuido ? ('Mecânico: ' . $mecanico_nome_srv) : 'A aguardar atribuição de mecânico.', 'done' => $mecanico_atribuido],
    ['title' => 'Em andamento', 'desc' => 'O seu veículo está a ser reparado.', 'done' => $is_em_andamento || $is_concluido],
    ['title' => 'Concluído', 'desc' => $is_pago ? 'Serviço concluído e pagamento registado.' : 'O serviço foi concluído.', 'done' => $is_concluido],
];

$ultimo_done = 0;
foreach ($timeline_steps as $i => $step) {
    if ($step['done']) {
        $ultimo_done = $i;
    }
}
$timeline_active = min($ultimo_done + 1, count($timeline_steps) - 1);
if ($is_concluido) {
    $timeline_active = count($timeline_steps) - 1;
}

$status_label = 'Pedido recebido';
if ($is_concluido && am_servico_precisa_pagar_trabalho_mecanico($servico)) {
    $status_label = 'Aguarda pagamento';
} elseif ($is_concluido) {
    $status_label = 'Concluído';
} elseif ($is_em_andamento) {
    $status_label = 'Em andamento';
} elseif ($mecanico_atribuido) {
    $status_label = 'Atribuído';
} elseif ($orcamento_aprovado_cliente) {
    $status_label = 'Aprovado';
} elseif ($tem_orcamento) {
    $status_label = 'Orçamento enviado';
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreamento de Reparação - AUTOMANAGER</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0d12;
            --card: #11141b;
            --card-2: #161a23;
            --line: rgba(255,255,255,0.08);
            --text: #f0f4fb;
            --muted: #96a3b8;
            --accent: #ff8a2f;
            --accent-soft: rgba(255,138,47,0.2);
            --pending: #5f6673;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background:
                radial-gradient(1200px 380px at 0% -20%, rgba(255,138,47,0.12), transparent 55%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 22px;
        }
        .container { max-width: 1060px; margin: 0 auto; }

        .track-shell {
            background: linear-gradient(180deg, var(--card), var(--card-2));
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 18px 38px rgba(0,0,0,0.4);
        }
        .track-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
        }
        .track-topbar h1 {
            font-size: 2rem;
            line-height: 1.1;
            margin-bottom: 4px;
        }
        .track-subtitle { color: var(--muted); font-size: .98rem; }
        .status-pill {
            border-radius: 999px;
            background: rgba(255,138,47,0.18);
            border: 1px solid rgba(255,138,47,0.45);
            color: #ffd7b6;
            padding: 8px 14px;
            font-weight: 700;
            font-size: .92rem;
            white-space: nowrap;
        }
        .status-pill::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            margin-right: 8px;
            box-shadow: 0 0 0 4px rgba(255,138,47,0.2);
        }
        .track-content {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 18px;
            padding: 18px;
        }
        .timeline-box {
            border-right: 1px solid var(--line);
            padding-right: 16px;
        }
        .timeline {
            position: relative;
            margin-top: 8px;
            padding-left: 22px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 4px;
            bottom: 8px;
            width: 2px;
            background: rgba(255,138,47,0.35);
        }
        .step {
            position: relative;
            margin-bottom: 16px;
        }
        .step-dot {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid var(--pending);
            background: #1e222d;
        }
        .step.done .step-dot {
            border-color: var(--accent);
            background: var(--accent);
            box-shadow: 0 0 0 4px rgba(255,138,47,0.2);
        }
        .step.active .step-dot {
            border-color: var(--accent);
            background: #1e222d;
            box-shadow: 0 0 0 4px rgba(255,138,47,0.14);
        }
        .step-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 2px; }
        .step-desc { color: var(--muted); font-size: .95rem; }
        .step:not(.done):not(.active) .step-title { color: #c7cfdd; }

        .details-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(255,255,255,0.02);
            padding: 14px 16px;
        }
        .details-title {
            color: var(--accent);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .detail-row {
            padding: 12px 0;
            border-top: 1px solid var(--line);
        }
        .detail-row:first-of-type { border-top: 0; }
        .detail-label {
            color: var(--muted);
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 4px;
        }
        .detail-value { font-size: 1.15rem; font-weight: 600; color: #e6ecf8; }
        .detail-value.price { color: var(--accent); }
        .track-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--line);
            padding: 12px 18px;
            color: #8f9db5;
            font-size: .9rem;
            gap: 12px;
        }
        .top-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }
        .btn-voltar {
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            padding: 9px 14px;
            background: rgba(255,255,255,0.04);
        }
        .btn-voltar:hover { border-color: rgba(255,255,255,0.35); }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px dashed rgba(255,138,47,0.5);
        }
        .empty-state h3 { color: var(--accent); margin-bottom: 8px; }
        .empty-state p { color: var(--muted); margin-bottom: 14px; }

        @media (max-width: 900px) {
            .track-content { grid-template-columns: 1fr; }
            .timeline-box { border-right: 0; padding-right: 0; border-bottom: 1px solid var(--line); padding-bottom: 12px; }
            .track-topbar { flex-direction: column; align-items: flex-start; }
            .track-topbar h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-actions">
            <a href="<?= htmlspecialchars($voltar_href, ENT_QUOTES, 'UTF-8') ?>" class="btn-voltar">Voltar</a>
        </div>

        <?php if ($servico): ?>
            <section class="track-shell">
                <div class="track-topbar">
                    <div>
                        <h1>Acompanhar Reparação</h1>
                        <p class="track-subtitle">
                            <?= htmlspecialchars((string)($servico['tipo_servico'] ?? 'Serviço')) ?>
                            • Matrícula: <?= htmlspecialchars((string)($servico['matricula'] ?? 'N/A')) ?>
                        </p>
                    </div>
                    <span class="status-pill"><?= htmlspecialchars($status_label) ?></span>
                </div>

                <div class="track-content">
                    <div class="timeline-box">
                        <div class="timeline">
                            <?php foreach ($timeline_steps as $index => $step): ?>
                                <article class="step <?= $step['done'] ? 'done' : '' ?> <?= (!$step['done'] && $index === $timeline_active) ? 'active' : '' ?>">
                                    <span class="step-dot"></span>
                                    <h3 class="step-title"><?= htmlspecialchars($step['title']) ?></h3>
                                    <p class="step-desc"><?= htmlspecialchars($step['desc']) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <aside class="details-card">
                        <h2 class="details-title">Detalhes da Marcação</h2>
                        <div class="detail-row">
                            <div class="detail-label">Cliente</div>
                            <div class="detail-value"><?= htmlspecialchars((string)($servico['nome'] ?? ($_SESSION['user']['nome_utilizador'] ?? 'Cliente'))) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Data / Hora</div>
                            <div class="detail-value">
                                <?php
                                    if (!empty($servico['data_desejada'])) {
                                        $data_visivel = date('d/m/Y', strtotime((string) $servico['data_desejada']));
                                        $hora_visivel = trim((string) ($servico['hora_desejada'] ?? ''));
                                    } elseif (!empty($servico['data_registo'])) {
                                        $data_visivel = date('d/m/Y', strtotime((string) $servico['data_registo']));
                                        $hora_visivel = !empty($servico['data_registo']) ? date('H:i', strtotime((string) $servico['data_registo'])) : '';
                                    } else {
                                        $data_visivel = 'N/A';
                                        $hora_visivel = '';
                                    }
                                    echo htmlspecialchars(trim($data_visivel . ($hora_visivel !== '' ? ' às ' . $hora_visivel : '')));
                                ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Mecânico</div>
                            <div class="detail-value"><?= htmlspecialchars($mecanico_nome_srv) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><?= htmlspecialchars($valor_track_rotulo) ?></div>
                            <div class="detail-value price"><?= number_format($valor_track, 2, ',', '.') ?> €</div>
                        </div>
                        <?php if ($sla_horas > 0): ?>
                        <div class="detail-row">
                            <div class="detail-label">Compromisso Premium</div>
                            <div class="detail-value">
                                Pronto em <?= (int)$sla_horas ?>h<?= $sla_limite ? ' · Limite ' . htmlspecialchars($sla_limite) : '' ?>
                                <?php if ($sla_estado === 'atrasado'): ?>
                                    <br><span style="color:#9be7c0;font-size:.95rem;">Compensação: <?= number_format($sla_comp, 2, ',', '.') ?> €</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </aside>
                </div>

                <div class="track-footer">
                    <span># ID: #<?= (int)$servico['id'] ?></span>
                    <span>AutoManager · Acompanhe o estado da sua reparação em tempo real</span>
                </div>
            </section>
        <?php else: ?>
            <div class="empty-state">
                <h3>Serviço não encontrado</h3>
                <p>Desculpe, não conseguimos encontrar este serviço.</p>
                <a href="<?= htmlspecialchars($voltar_href, ENT_QUOTES, 'UTF-8') ?>" class="btn-voltar">Ver Minhas Marcações</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>