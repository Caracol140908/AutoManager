<?php
session_start();
require 'ligar.php';
require_once 'mailer.php';
require_once __DIR__ . '/inc_orcamento_interativo.php';
require_once __DIR__ . '/inc_cancelamento_marcacao.php';
am_ensure_cancelamento_marcacao_columns($con);

if (!isset($_SESSION['chefe_id']) || !isset($_SESSION['is_chefe'])) {
    header('Location: login.php');
    exit;
}

$chefe_nome = trim((string) ($_SESSION['chefe_nome'] ?? 'Chefe de Oficina'));
$chefe_id = (int) ($_SESSION['chefe_id'] ?? 0);

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

@$con->query("ALTER TABLE servico ADD COLUMN estado_orcamento VARCHAR(30) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN orcamento_aprovado DECIMAL(10,2) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN data_orcamento DATETIME NULL");
@$con->query("ALTER TABLE servico ADD COLUMN observacoes_orcamento TEXT NULL");
@$con->query("ALTER TABLE servico ADD COLUMN estado_orcamento_cliente VARCHAR(20) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN data_orcamento_aceite DATETIME NULL");
@$con->query("ALTER TABLE servico ADD COLUMN aviso_chefe_orcamento_aceite TINYINT(1) NOT NULL DEFAULT 1");
@$con->query("ALTER TABLE servico ADD COLUMN data_orcamento_recusado DATETIME NULL");
@$con->query("ALTER TABLE servico ADD COLUMN aviso_chefe_orcamento_recusado TINYINT(1) NOT NULL DEFAULT 1");
@$con->query("ALTER TABLE servico ADD COLUMN estado_pagamento VARCHAR(20) NULL");
@$con->query("ALTER TABLE chefes_oficina ADD COLUMN kpis_operacionais_json LONGTEXT NULL");

$mensagem = '';

function chefe_kpis_default_map(
    int $agendaHoje,
    int $atrasados,
    int $equipaOcupada,
    int $taxaConclusaoMes,
    float $ticketMedioMes,
    float $ticketMedioHist
): array {
    return [
        'agenda_hoje' => $agendaHoje,
        'servicos_atrasados' => $atrasados,
        'equipa_ocupada' => $equipaOcupada,
        'taxa_conclusao_mes' => $taxaConclusaoMes,
        'ticket_medio_mes' => $ticketMedioMes,
        'ticket_medio_historico' => $ticketMedioHist,
    ];
}

function chefe_kpis_carregar(mysqli $con, int $chefeId): array
{
    if ($chefeId <= 0) return [];
    $st = $con->prepare("SELECT kpis_operacionais_json FROM chefes_oficina WHERE id_chefe = ? LIMIT 1");
    if (!$st) return [];
    $st->bind_param('i', $chefeId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    $json = (string) ($row['kpis_operacionais_json'] ?? '');
    if ($json === '') return [];
    $dec = json_decode($json, true);
    return is_array($dec) ? $dec : [];
}

function chefe_kpis_guardar(mysqli $con, int $chefeId, ?array $data): bool
{
    if ($chefeId <= 0) return false;
    $json = $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
    $st = $con->prepare("UPDATE chefes_oficina SET kpis_operacionais_json = ? WHERE id_chefe = ?");
    if (!$st) return false;
    $st->bind_param('si', $json, $chefeId);
    $ok = $st->execute();
    $st->close();
    return $ok;
}

function chefe_servico_pode_gerir_atribuicao(mysqli $con, int $servicoId): bool
{
    if ($servicoId <= 0) {
        return false;
    }
    $st = $con->prepare("SELECT estado_orcamento, estado_orcamento_cliente, estado_do_trabalho FROM servico WHERE id = ? LIMIT 1");
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $servicoId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    if (!$row) {
        return false;
    }
    $orcAprovado = strtolower(trim((string) ($row['estado_orcamento'] ?? ''))) === 'aprovado';
    $orcCliente = strtolower(trim((string) ($row['estado_orcamento_cliente'] ?? '')));
    $orcAceite = in_array($orcCliente, ['aceite', 'aceito'], true);
    $estadoTrabalho = (string) ($row['estado_do_trabalho'] ?? 'por fazer');

    return $orcAprovado && $orcAceite && chefe_servico_estado_aguarda_atribuicao($estadoTrabalho);
}

function chefe_estado_permite_reatribuir(string $estadoTrabalho): bool
{
    $estado = strtolower(trim($estadoTrabalho));
    if ($estado === '') {
        return true;
    }
    if (strpos($estado, 'cancel') !== false || strpos($estado, 'conclu') !== false || strpos($estado, 'andamento') !== false) {
        return false;
    }

    return $estado === 'por fazer' || $estado === 'pendente' || $estado === 'atribuido' || strpos($estado, 'por fazer') !== false;
}

function chefe_servico_pode_reatribuir(mysqli $con, int $servicoId): bool
{
    if ($servicoId <= 0) {
        return false;
    }
    $st = $con->prepare('SELECT estado_do_trabalho FROM servico WHERE id = ? LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $servicoId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    if (!$row) {
        return false;
    }

    return chefe_estado_permite_reatribuir((string) ($row['estado_do_trabalho'] ?? 'por fazer'));
}

function chefe_servico_estado_aguarda_atribuicao(string $estadoTrabalho): bool
{
    $estado = strtolower(trim($estadoTrabalho));
    if ($estado === '' || $estado === 'por fazer' || $estado === 'pendente' || strpos($estado, 'por fazer') === 0) {
        return strpos($estado, 'andamento') === false
            && strpos($estado, 'conclu') === false
            && strpos($estado, 'pronto') === false
            && strpos($estado, 'atribu') === false
            && strpos($estado, 'cancel') === false;
    }

    return false;
}

function chefe_sql_estado_aguarda_atribuicao(string $alias = 's'): string
{
    $col = $alias !== '' ? "{$alias}.estado_do_trabalho" : 'estado_do_trabalho';
    $c = "LOWER(TRIM(COALESCE({$col}, '')))";
    $raw = "LOWER(COALESCE({$col}, ''))";

    return "(
        ({$c} = '' OR {$c} = 'por fazer' OR {$c} = 'pendente' OR {$c} LIKE 'por fazer%')
        AND {$raw} NOT LIKE '%andamento%'
        AND {$raw} NOT LIKE '%conclu%'
        AND {$raw} NOT LIKE '%pronto%'
        AND {$raw} NOT LIKE '%atribu%'
        AND {$raw} NOT LIKE '%cancel%'
    )";
}

function chefe_sql_cond_por_atribuir(string $mecExpr, string $alias = 's'): string
{
    $p = $alias !== '' ? "{$alias}." : '';

    return "{$mecExpr} IS NULL
      AND LOWER(COALESCE({$p}estado_orcamento, '')) = 'aprovado'
      AND LOWER(COALESCE({$p}estado_orcamento_cliente, 'pendente')) IN ('aceite', 'aceito')
      AND " . chefe_sql_estado_aguarda_atribuicao($alias) . "
      AND LOWER(COALESCE({$p}estado_marcacao, 'ativa')) <> 'cancelada'";
}

$colIdMecanico = false;
$colMecanicoId = false;
$colNotasInternas = false;
$colDataDesejada = false;
$colDataRegisto = false;
$colCreatedAt = false;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_mecanico'");
if ($chk && $chk->num_rows > 0) $colIdMecanico = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'mecanico_id'");
if ($chk && $chk->num_rows > 0) $colMecanicoId = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'notas_internas'");
if ($chk && $chk->num_rows > 0) $colNotasInternas = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_desejada'");
if ($chk && $chk->num_rows > 0) $colDataDesejada = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_registo'");
if ($chk && $chk->num_rows > 0) $colDataRegisto = true;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'created_at'");
if ($chk && $chk->num_rows > 0) $colCreatedAt = true;

$servicoMecExpr = "NULL";
if ($colIdMecanico && $colMecanicoId) {
    $servicoMecExpr = "COALESCE(s.id_mecanico, s.mecanico_id)";
} elseif ($colIdMecanico) {
    $servicoMecExpr = "s.id_mecanico";
} elseif ($colMecanicoId) {
    $servicoMecExpr = "s.mecanico_id";
}
$servicoNotasExpr = $colNotasInternas ? "s.notas_internas" : "''";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_orcamento_chefe'])) {
    am_ensure_orcamento_interativo($con);
    $servicoId = (int) ($_POST['servico_id'] ?? 0);
    $valorOrcForm = (float) ($_POST['valor_orcamento'] ?? 0);
    $obsOrc = trim((string) ($_POST['observacoes_orcamento'] ?? ''));

    $descs = $_POST['ol_desc'] ?? [];
    if (!is_array($descs)) {
        $descs = [];
    }
    $cats = $_POST['ol_cat'] ?? [];
    $qtys = $_POST['ol_qty'] ?? [];
    $precos = $_POST['ol_preco'] ?? [];
    $mins = $_POST['ol_min'] ?? [];
    $grupos = $_POST['ol_grupo'] ?? [];

    $linhas = [];
    $n = count($descs);
    for ($i = 0; $i < $n; $i++) {
        $d = trim((string) ($descs[$i] ?? ''));
        if ($d === '') {
            continue;
        }
        $cat = trim((string) ($cats[$i] ?? 'outro'));
        $q = isset($qtys[$i]) ? (float) str_replace(',', '.', (string) $qtys[$i]) : 1.0;
        if ($q <= 0) {
            $q = 1.0;
        }
        $p = round((float) str_replace(',', '.', (string) ($precos[$i] ?? 0)), 2);
        $mn = isset($mins[$i]) && trim((string) $mins[$i]) !== '' ? max(0, (int) $mins[$i]) : 0;
        $gr = trim((string) ($grupos[$i] ?? ''));
        $linhas[] = [
            'descricao' => $d,
            'categoria' => $cat,
            'quantidade' => $q,
            'preco_unitario' => $p,
            'minutos' => $mn,
            'grupo' => $gr,
        ];
    }

    $valorOrc = $valorOrcForm;
    if ($linhas === []) {
        if ($servicoId <= 0 || $valorOrc <= 0) {
            $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-exclamation-triangle'></i> Indica um valor de orçamento válido ou preenche pelo menos uma linha detalhada.</div>";
        } else {
            $ti = $con->prepare('SELECT tipo_servico FROM servico WHERE id = ? LIMIT 1');
            $tipoSrv = 'Serviço';
            if ($ti) {
                $ti->bind_param('i', $servicoId);
                $ti->execute();
                $rt = $ti->get_result()->fetch_assoc();
                $ti->close();
                if ($rt && !empty($rt['tipo_servico'])) {
                    $tipoSrv = (string) $rt['tipo_servico'];
                }
            }
            $linhas = [[
                'descricao' => 'Orçamento — ' . $tipoSrv,
                'categoria' => 'outro',
                'quantidade' => 1.0,
                'preco_unitario' => $valorOrc,
                'minutos' => 0,
                'grupo' => '',
            ]];
        }
    } else {
        $sum = 0.0;
        foreach ($linhas as $ln) {
            $sum += (float) $ln['quantidade'] * (float) $ln['preco_unitario'];
        }
        $sum = round($sum, 2);
        if ($sum <= 0) {
            $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-exclamation-triangle'></i> O total das linhas tem de ser maior que zero.</div>";
            $linhas = [];
        } else {
            $valorOrc = $sum;
        }
    }

    if ($servicoId > 0 && $linhas !== [] && ($mensagem ?? '') === '') {
        if (!am_servico_orcamento_itens_replace($con, $servicoId, $linhas)) {
            $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Erro ao guardar linhas do orçamento.</div>";
        } else {
            $up = $con->prepare("UPDATE servico SET estado_orcamento = 'aprovado', orcamento_aprovado = ?, data_orcamento = NOW(), observacoes_orcamento = ?, estado_orcamento_cliente = 'pendente', aviso_chefe_orcamento_aceite = 1 WHERE id = ?");
            if ($up) {
                $up->bind_param('dsi', $valorOrc, $obsOrc, $servicoId);
                if ($up->execute()) {
                    $info = $con->prepare('SELECT nome, email, tipo_servico FROM servico WHERE id = ? LIMIT 1');
                    if ($info) {
                        $info->bind_param('i', $servicoId);
                        $info->execute();
                        $r = $info->get_result();
                        $row = $r ? $r->fetch_assoc() : null;
                        $info->close();
                        if ($row && !empty($row['email'])) {
                            $nome = $row['nome'] ?: 'Cliente';
                            $email = $row['email'];
                            $tipo = $row['tipo_servico'] ?: 'Serviço';
                            $assunto = 'Orçamento disponível - AutoManager';
                            $mensagemEmail = "
                        <html><body style='font-family:Arial,sans-serif;background:#f6f6f6;padding:20px;'>
                        <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:12px;padding:22px;border:1px solid #eee;'>
                        <h2 style='margin:0 0 10px;color:#111'>Novo orçamento da sua reparação</h2>
                        <p>Olá <strong>" . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p>O orçamento do serviço <strong>" . htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') . "</strong> já está disponível.</p>
                        <p><strong>Valor:</strong> € " . number_format($valorOrc, 2, ',', '.') . "</p>
                        " . (!empty($obsOrc) ? '<p><strong>Observações:</strong> ' . nl2br(htmlspecialchars($obsOrc, ENT_QUOTES, 'UTF-8')) . '</p>' : '') . "
                        <p>Pode consultar na sua área de cliente em <strong>Configurações &gt; Minhas Marcações</strong>.</p>
                        <p style='color:#666'>AutoManager</p>
                        </div></body></html>";
                            $envio = enviarEmailAutoManager([
                                'to' => $email,
                                'to_name' => $nome,
                                'subject' => $assunto,
                                'html' => $mensagemEmail,
                                'text' => "O orçamento do serviço {$tipo} está disponível. Valor: € " . number_format($valorOrc, 2, ',', '.'),
                            ]);
                            $mensagem = $envio['ok']
                                ? "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> Orçamento enviado e guardado na base de dados.</div>"
                                : "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> Orçamento guardado na base de dados.</div>";
                        } else {
                            $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> Orçamento guardado na base de dados.</div>";
                        }
                    } else {
                        $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> Orçamento guardado na base de dados.</div>";
                    }
                } else {
                    $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Erro ao guardar o orçamento.</div>";
                }
                $up->close();
            } else {
                $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Erro ao preparar atualização do serviço.</div>";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atribuir_mecanico_chefe'])) {
    $servicoId = (int)($_POST['servico_id'] ?? 0);
    $mecanicoId = (int)($_POST['mecanico_id'] ?? 0);
    if ($servicoId > 0 && $mecanicoId > 0) {
        if (!chefe_servico_pode_gerir_atribuicao($con, $servicoId)) {
            $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Só é possível atribuir trabalhos já com o orçamento aceite.</div>";
        } else {
        $sets = [];
        if ($colIdMecanico) $sets[] = "id_mecanico = ?";
        if ($colMecanicoId) $sets[] = "mecanico_id = ?";
        $sets[] = "estado_do_trabalho = 'Atribuido'";
        $sets[] = "aviso_chefe_orcamento_aceite = 1";
        $sqlUp = "UPDATE servico SET " . implode(", ", $sets) . " WHERE id = ?";
        $up = $con->prepare($sqlUp);
        if ($up) {
            if ($colIdMecanico && $colMecanicoId) {
                $up->bind_param("iii", $mecanicoId, $mecanicoId, $servicoId);
            } else {
                $up->bind_param("ii", $mecanicoId, $servicoId);
            }
            if ($up->execute()) {
                $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> Trabalho atribuído com sucesso.</div>";
            } else {
                $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Erro ao atribuir o trabalho.</div>";
            }
            $up->close();
        }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reatribuir_mecanico_chefe'])) {
    $servicoId = (int)($_POST['servico_id_re'] ?? 0);
    $mecanicoId = (int)($_POST['mecanico_id_re'] ?? 0);
    if ($servicoId > 0 && $mecanicoId > 0) {
        if (!chefe_servico_pode_reatribuir($con, $servicoId)) {
            $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Só é possível reatribuir trabalhos por fazer ou pendentes.</div>";
        } else {
        $sets = [];
        if ($colIdMecanico) $sets[] = "id_mecanico = ?";
        if ($colMecanicoId) $sets[] = "mecanico_id = ?";
        $sqlUp = "UPDATE servico SET " . implode(", ", $sets) . " WHERE id = ?";
        $up = $con->prepare($sqlUp);
        if ($up) {
            if ($colIdMecanico && $colMecanicoId) {
                $up->bind_param("iii", $mecanicoId, $mecanicoId, $servicoId);
            } else {
                $up->bind_param("ii", $mecanicoId, $servicoId);
            }
            if ($up->execute()) {
                $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> Mecânico reatribuído com sucesso.</div>";
            } else {
                $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Erro ao reatribuir mecânico.</div>";
            }
            $up->close();
        }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_kpis_operacionais'])) {
    $kpiData = [
        'agenda_hoje' => max(0, (int) ($_POST['kpi_agenda_hoje'] ?? 0)),
        'servicos_atrasados' => max(0, (int) ($_POST['kpi_servicos_atrasados'] ?? 0)),
        'equipa_ocupada' => max(0, min(100, (int) ($_POST['kpi_equipa_ocupada'] ?? 0))),
        'taxa_conclusao_mes' => max(0, min(100, (int) ($_POST['kpi_taxa_conclusao_mes'] ?? 0))),
        'ticket_medio_mes' => max(0, round((float) str_replace(',', '.', (string) ($_POST['kpi_ticket_medio_mes'] ?? 0)), 2)),
        'ticket_medio_historico' => max(0, round((float) str_replace(',', '.', (string) ($_POST['kpi_ticket_medio_historico'] ?? 0)), 2)),
    ];
    if (chefe_kpis_guardar($con, $chefe_id, $kpiData)) {
        $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-check-circle'></i> KPIs operacionais atualizados com sucesso.</div>";
    } else {
        $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Não foi possível guardar os KPIs operacionais.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repor_kpis_operacionais'])) {
    if (chefe_kpis_guardar($con, $chefe_id, null)) {
        $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#183629;border:1px solid #27573f;color:#b7f0cf;'><i class='bi bi-arrow-counterclockwise'></i> KPIs repostos para os valores automáticos.</div>";
    } else {
        $mensagem = "<div style='margin-bottom:12px;padding:12px;border-radius:10px;background:#4e2329;border:1px solid #6c3139;color:#ffced5;'><i class='bi bi-x-circle'></i> Não foi possível repor os KPIs.</div>";
    }
}

$tab = $_GET['tab'] ?? 'dashboard';
$tabsValidas = ['dashboard', 'orcamentos', 'para_atribuir', 'mecanicos', 'trabalhos'];
if (!in_array($tab, $tabsValidas, true)) $tab = 'dashboard';

$chefePageIntros = [
    'dashboard' => 'Resumo da oficina, prioridades e desempenho da equipa.',
    'orcamentos' => 'Crie e envie orçamentos aos clientes — eles podem aceitar ou recusar online.',
    'para_atribuir' => 'Trabalhos com orçamento aceite, prontos para atribuir a um mecânico.',
    'mecanicos' => 'Carga de trabalho e produtividade de cada técnico.',
    'trabalhos' => 'Lista completa de serviços — pesquise, filtre e reatribua se necessário.',
];
if ($tab === 'orcamentos') {
    @$con->query("UPDATE servico SET aviso_chefe_orcamento_recusado = 1 WHERE LOWER(COALESCE(estado_orcamento_cliente, '')) = 'recusado' AND COALESCE(aviso_chefe_orcamento_recusado, 1) = 0");
}

$safe = function ($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$contar = function (string $sql, int $default = 0) use ($con): int {
    $r = @$con->query($sql);
    if (!$r) return $default;
    $row = $r->fetch_assoc();
    return (int)($row['total'] ?? $default);
};
$somar = function (string $sql, float $default = 0.0) use ($con): float {
    $r = @$con->query($sql);
    if (!$r) return $default;
    $row = $r->fetch_assoc();
    return (float)($row['total'] ?? $default);
};

$aprovados = $contar("SELECT COUNT(*) AS total FROM servico WHERE LOWER(COALESCE(estado_orcamento, '')) = 'aprovado'");
$atribuidos = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $servicoMecExpr IS NOT NULL AND LOWER(COALESCE(s.estado_do_trabalho, 'por fazer')) NOT LIKE '%andamento%' AND LOWER(COALESCE(s.estado_do_trabalho, '')) NOT LIKE '%conclu%'");
$emAndamento = $contar("SELECT COUNT(*) AS total FROM servico WHERE LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%andamento%'");
$concluidos = $contar("SELECT COUNT(*) AS total FROM servico WHERE LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%pronto%'");
$receitaTotal = $somar("SELECT SUM(COALESCE(custo, 0)) AS total FROM servico WHERE LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%pronto%'");
$receitaMes = $somar("SELECT SUM(COALESCE(custo, 0)) AS total FROM servico WHERE (LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(estado_do_trabalho, '')) LIKE '%pronto%') AND MONTH(COALESCE(data_desejada, data_registo, created_at, NOW())) = MONTH(CURRENT_DATE()) AND YEAR(COALESCE(data_desejada, data_registo, created_at, NOW())) = YEAR(CURRENT_DATE())");

$agendaHoje = $contar("SELECT COUNT(*) AS total FROM servico WHERE DATE(COALESCE(data_desejada, data_registo, created_at, NOW())) = CURRENT_DATE()");
$atrasados = $contar("SELECT COUNT(*) AS total FROM servico WHERE DATE(COALESCE(data_desejada, data_registo, created_at, NOW())) < CURRENT_DATE() AND LOWER(COALESCE(estado_do_trabalho, 'por fazer')) NOT LIKE '%conclu%'");
$equipaOcupada = max(0, min(100, (int)round(($emAndamento / max(1, $contar("SELECT COUNT(*) AS total FROM mecanicos"))) * 100)));
$taxaConclusaoMes = max(0, min(100, (int)round(($concluidos / max(1, $contar("SELECT COUNT(*) AS total FROM servico WHERE MONTH(COALESCE(data_desejada, data_registo, created_at, NOW())) = MONTH(CURRENT_DATE()) AND YEAR(COALESCE(data_desejada, data_registo, created_at, NOW())) = YEAR(CURRENT_DATE())"))) * 100)));
$ticketMedioMes = $agendaHoje > 0 ? $receitaMes / max(1, $agendaHoje) : 0;
$ticketMedioHist = $concluidos > 0 ? $receitaTotal / max(1, $concluidos) : 0;
$kpisOperacionaisAuto = chefe_kpis_default_map($agendaHoje, $atrasados, $equipaOcupada, $taxaConclusaoMes, $ticketMedioMes, $ticketMedioHist);
$kpisOperacionaisCustom = chefe_kpis_carregar($con, $chefe_id);
if ($kpisOperacionaisCustom !== []) {
    $agendaHoje = (int) ($kpisOperacionaisCustom['agenda_hoje'] ?? $agendaHoje);
    $atrasados = (int) ($kpisOperacionaisCustom['servicos_atrasados'] ?? $atrasados);
    $equipaOcupada = (int) ($kpisOperacionaisCustom['equipa_ocupada'] ?? $equipaOcupada);
    $taxaConclusaoMes = (int) ($kpisOperacionaisCustom['taxa_conclusao_mes'] ?? $taxaConclusaoMes);
    $ticketMedioMes = (float) ($kpisOperacionaisCustom['ticket_medio_mes'] ?? $ticketMedioMes);
    $ticketMedioHist = (float) ($kpisOperacionaisCustom['ticket_medio_historico'] ?? $ticketMedioHist);
}

$topServicos = @$con->query("
    SELECT COALESCE(tipo_servico, 'Serviço') AS servico, COUNT(*) AS total
    FROM servico
    GROUP BY COALESCE(tipo_servico, 'Serviço')
    ORDER BY total DESC
    LIMIT 5
");

$cargaEquipa = @$con->query("
    SELECT 
      m.id_mecanico,
      m.nome_mecanico,
      SUM(CASE WHEN LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%andamento%' THEN 1 ELSE 0 END) AS em_andamento,
      SUM(CASE WHEN LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%' THEN 1 ELSE 0 END) AS concluidas,
      SUM(CASE WHEN LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%atribu%' OR ($servicoMecExpr = m.id_mecanico AND LOWER(COALESCE(s.estado_do_trabalho, '')) NOT LIKE '%conclu%') THEN 1 ELSE 0 END) AS ativas
    FROM mecanicos m
    LEFT JOIN servico s ON $servicoMecExpr = m.id_mecanico
    GROUP BY m.id_mecanico, m.nome_mecanico
    ORDER BY m.nome_mecanico
");

$labels7 = [];
$vals7 = [];
for ($i = 6; $i >= 0; $i--) {
    $labels7[] = date('d/m', strtotime("-$i day"));
    $vals7[] = 0;
}
$idxMap = [];
foreach ($labels7 as $i => $d) $idxMap[$d] = $i;
$q7 = @$con->query("
    SELECT DATE(COALESCE(data_desejada, data_registo, created_at, NOW())) AS d, COUNT(*) AS total
    FROM servico
    WHERE DATE(COALESCE(data_desejada, data_registo, created_at, NOW())) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(COALESCE(data_desejada, data_registo, created_at, NOW()))
");
if ($q7) {
    while ($r = $q7->fetch_assoc()) {
        $k = date('d/m', strtotime($r['d']));
        if (isset($idxMap[$k])) $vals7[$idxMap[$k]] = (int)$r['total'];
    }
}

$qTexto = trim($_GET['q'] ?? '');
$qStatus = trim($_GET['status'] ?? '');
$qUrgencia = trim($_GET['urgencia'] ?? '');
$where = [];
if ($qTexto !== '') {
    $qEsc = $con->real_escape_string($qTexto);
    $where[] = "(COALESCE(s.nome,'') LIKE '%$qEsc%' OR COALESCE(s.tipo_servico,'') LIKE '%$qEsc%' OR COALESCE(s.matricula,'') LIKE '%$qEsc%')";
}
if ($qStatus !== '') {
    $stEsc = $con->real_escape_string($qStatus);
    $where[] = "LOWER(COALESCE(s.estado_do_trabalho, 'por fazer')) LIKE '%$stEsc%'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$dataExprParts = [];
if ($colDataDesejada) $dataExprParts[] = "s.data_desejada";
if ($colDataRegisto) $dataExprParts[] = "s.data_registo";
if ($colCreatedAt) $dataExprParts[] = "s.created_at";
$dataExprParts[] = "NOW()";
$dataExpr = "DATE(COALESCE(" . implode(", ", $dataExprParts) . "))";
$dataOrderExpr = "COALESCE(" . implode(", ", $dataExprParts) . ")";

$whereAtribuirBase = [];
if ($qTexto !== '') {
    $qEsc2 = $con->real_escape_string($qTexto);
    $whereAtribuirBase[] = "(COALESCE(s.nome,'') LIKE '%$qEsc2%' OR COALESCE(s.tipo_servico,'') LIKE '%$qEsc2%' OR COALESCE(s.matricula,'') LIKE '%$qEsc2%')";
}
$whereAtribuirUrg = [];
if ($qUrgencia === 'atras') $whereAtribuirUrg[] = "$dataExpr < CURRENT_DATE()";
if ($qUrgencia === 'hoje') $whereAtribuirUrg[] = "$dataExpr = CURRENT_DATE()";
if ($qUrgencia === 'futuro') $whereAtribuirUrg[] = "$dataExpr > CURRENT_DATE()";
$whereAtribuirSql = ($whereAtribuirBase || $whereAtribuirUrg) ? (' AND ' . implode(' AND ', array_merge($whereAtribuirBase, $whereAtribuirUrg))) : '';
$whereAtribuirBaseSql = $whereAtribuirBase ? (' AND ' . implode(' AND ', $whereAtribuirBase)) : '';
$avisoFiltroAtribuir = '';

$condBaseAtribuir = chefe_sql_cond_por_atribuir($servicoMecExpr, 's');
$condPagamentoEstrito = "1=1";
$condPagamentoFlex = $condPagamentoEstrito;

@$con->query("
    UPDATE servico s
    SET s.aviso_chefe_orcamento_aceite = 1
    WHERE COALESCE(s.aviso_chefe_orcamento_aceite, 1) = 0
      AND NOT ($condBaseAtribuir)
");
if ($tab === 'para_atribuir') {
    @$con->query("
        UPDATE servico s
        SET s.aviso_chefe_orcamento_aceite = 1
        WHERE COALESCE(s.aviso_chefe_orcamento_aceite, 1) = 0
          AND $condBaseAtribuir
    ");
}

$trabalhos = @$con->query("
    SELECT s.id, s.nome, s.tipo_servico, s.matricula, s.telefone, s.email, s.estado_do_trabalho, s.estado_pagamento, s.estado_orcamento_cliente, s.data_desejada, s.hora_desejada, s.custo, $servicoNotasExpr AS notas_internas,
           $servicoMecExpr AS id_mecanico_ref,
           COALESCE(m.nome_mecanico, '-') AS nome_mecanico
    FROM servico s
    LEFT JOIN mecanicos m ON m.id_mecanico = $servicoMecExpr
    $whereSql
    ORDER BY s.id DESC
    LIMIT 200
");

$orcamentosPendentes = @$con->query("
    SELECT s.id, s.nome, s.email, s.telefone, s.matricula, s.tipo_servico, s.data_desejada, s.hora_desejada,
           s.marca, s.modelo, s.custo, s.nif, $servicoNotasExpr AS notas_internas,
           s.plano_pronto, s.estado_pagamento, s.estado_do_trabalho
    FROM servico s
    WHERE COALESCE(s.estado_orcamento, 'pendente') = 'pendente' OR s.estado_orcamento IS NULL
    ORDER BY s.id DESC
    LIMIT 200
");
if (!$orcamentosPendentes) {
    $orcamentosPendentes = @$con->query("
        SELECT s.id, s.nome, s.email, s.telefone, s.matricula, s.tipo_servico, s.data_desejada, s.hora_desejada,
               s.marca, s.modelo, s.custo, $servicoNotasExpr AS notas_internas
        FROM servico s
        WHERE COALESCE(s.estado_orcamento, 'pendente') = 'pendente' OR s.estado_orcamento IS NULL
        ORDER BY s.id DESC
        LIMIT 200
    ");
}

$paraAtribuir = @$con->query("
    SELECT s.id, s.nome, s.tipo_servico, s.matricula, s.telefone, s.email, s.data_desejada, s.hora_desejada, s.estado_pagamento, s.estado_orcamento_cliente, s.estado_do_trabalho, $servicoNotasExpr AS notas_internas
    FROM servico s
    WHERE $condBaseAtribuir
      AND $condPagamentoEstrito
      $whereAtribuirSql
    ORDER BY $dataOrderExpr ASC
    LIMIT 100
");

if (!$paraAtribuir || $paraAtribuir->num_rows === 0) {
    $paraAtribuir = @$con->query("
        SELECT s.id, s.nome, s.tipo_servico, s.matricula, s.telefone, s.email, s.data_desejada, s.hora_desejada, s.estado_pagamento, s.estado_orcamento_cliente, s.estado_do_trabalho, $servicoNotasExpr AS notas_internas
        FROM servico s
        WHERE $condBaseAtribuir
          AND $condPagamentoFlex
          $whereAtribuirSql
        ORDER BY $dataOrderExpr ASC
        LIMIT 100
    ");
}

if ($qUrgencia !== '' && $paraAtribuir && $paraAtribuir->num_rows === 0) {
    $paraAtribuir = @$con->query("
        SELECT s.id, s.nome, s.tipo_servico, s.matricula, s.telefone, s.email, s.data_desejada, s.hora_desejada, s.estado_pagamento, s.estado_orcamento_cliente, s.estado_do_trabalho, $servicoNotasExpr AS notas_internas
        FROM servico s
        WHERE $condBaseAtribuir
          AND $condPagamentoFlex
          $whereAtribuirBaseSql
        ORDER BY $dataOrderExpr ASC
        LIMIT 100
    ");
    $avisoFiltroAtribuir = 'Não existem trabalhos para a urgência selecionada. Foram mostrados todos os trabalhos pendentes.';
}

$totalParaAtribuir = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $condBaseAtribuir AND $condPagamentoEstrito");
$totalParaHoje = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $condBaseAtribuir AND $condPagamentoEstrito AND $dataExpr = CURRENT_DATE()");
$totalParaAtraso = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $condBaseAtribuir AND $condPagamentoEstrito AND $dataExpr < CURRENT_DATE()");
if ($totalParaAtribuir === 0) {
    $totalParaAtribuir = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $condBaseAtribuir AND $condPagamentoFlex");
    $totalParaHoje = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $condBaseAtribuir AND $condPagamentoFlex AND $dataExpr = CURRENT_DATE()");
    $totalParaAtraso = $contar("SELECT COUNT(*) AS total FROM servico s WHERE $condBaseAtribuir AND $condPagamentoFlex AND $dataExpr < CURRENT_DATE()");
}
$totalOrcPendentes = $contar("SELECT COUNT(*) AS total FROM servico s WHERE COALESCE(s.estado_orcamento, 'pendente') = 'pendente' OR s.estado_orcamento IS NULL");
$totalOrcRecusadosAviso = $contar("SELECT COUNT(*) AS total FROM servico s WHERE LOWER(COALESCE(s.estado_orcamento, '')) = 'aprovado' AND LOWER(COALESCE(s.estado_orcamento_cliente, '')) = 'recusado' AND COALESCE(s.aviso_chefe_orcamento_recusado, 1) = 0");
$chefeNotifTotal = (int) $totalOrcPendentes + (int) $totalParaAtribuir;
$chefeNotifTab = 'orcamentos';
if ($totalParaAtribuir > 0) {
    $chefeNotifTab = 'para_atribuir';
} elseif ($totalOrcPendentes > 0) {
    $chefeNotifTab = 'orcamentos';
}

$mecanicos = @$con->query("
    SELECT m.id_mecanico, m.nome_mecanico,
           SUM(CASE WHEN LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%atribu%' THEN 1 ELSE 0 END) AS atribuidas,
           SUM(CASE WHEN LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%andamento%' THEN 1 ELSE 0 END) AS andamento,
           SUM(CASE WHEN LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%' OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%' THEN 1 ELSE 0 END) AS concluidas,
           COUNT(s.id) AS total
    FROM mecanicos m
    LEFT JOIN servico s ON $servicoMecExpr = m.id_mecanico
    GROUP BY m.id_mecanico, m.nome_mecanico
    ORDER BY m.nome_mecanico
");
$mecanicosRows = [];
if ($mecanicos) {
    while ($row = $mecanicos->fetch_assoc()) {
        $mecanicosRows[] = $row;
    }
}
$am_head_body_class = 'am-font-inter';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel Chefe de Oficina — AutoManager</title>
<?php require __DIR__ . '/inc_am_head.php'; ?>
  <link rel="stylesheet" href="indexchefeoficina.css?v=10">
  <style>
    /* Layout crítico (fallback se o CSS externo não carregar no servidor) */
    body.chefe-panel{margin:0;font-family:Inter,system-ui,sans-serif;color:#f4f4f8;background:radial-gradient(ellipse 80% 50% at 50% -12%,rgba(249,115,22,.12),transparent 52%),linear-gradient(165deg,#0e0e12,#121214);min-height:100vh}
    .chefe-panel .wrap{max-width:1240px;margin:0 auto;padding:20px}
    .chefe-panel .top{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;margin-bottom:16px;padding:16px 18px;border:1px solid rgba(255,255,255,.14);border-radius:16px;background:#2c2c36}
    .chefe-panel .logo{font-size:1.5rem;font-weight:800;color:#f4f4f8}
    .chefe-panel .logo .dot{color:#ea580c}
    .chefe-panel .chefe-brand__sub{margin:6px 0 0;font-size:.875rem;color:#b4b4c0}
    .chefe-panel .chefe-brand__sub strong{color:#f97316}
    .chefe-panel .chefe-top__actions{display:flex;gap:8px;flex-wrap:wrap}
    .chefe-panel .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,.14);background:#2a2a2a;color:#f4f4f8;cursor:pointer;font-size:.875rem}
    .chefe-panel .btn-primary,.chefe-panel a.btn-cta{background:linear-gradient(135deg,#ea580c,#f97316)!important;border-color:transparent!important;color:#121218!important}
    .chefe-panel .btn.red{background:rgba(127,29,29,.4);border-color:rgba(248,113,113,.35);color:#fecaca}
    .chefe-panel .tabs{display:flex;gap:8px;margin-bottom:12px;padding:8px;overflow-x:auto;border:1px solid rgba(255,255,255,.14);border-radius:14px;background:#2c2c36}
    .chefe-panel .tab{flex:1;min-width:100px;display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 8px;border-radius:10px;background:#2a2a2a;border:1px solid rgba(255,255,255,.09);color:#b4b4c0!important;text-decoration:none!important;font-weight:600;font-size:.75rem;position:relative}
    .chefe-panel .tab.active{border-color:#ea580c;background:rgba(249,115,22,.14);color:#f97316!important}
    .chefe-panel .chefe-tab__badge{position:absolute;top:4px;right:4px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;font-size:.65rem;font-weight:700;line-height:18px;background:#ea580c;color:#121218}
    .chefe-panel .chefe-notif-btn{position:relative;display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-color:rgba(249,115,22,.45);background:rgba(249,115,22,.12);color:#fdba74}
    .chefe-panel .chefe-notif-btn__count{min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:linear-gradient(135deg,#fb923c,#ea580c);color:#121218;font-size:.72rem;font-weight:800;line-height:20px;text-align:center}
    .chefe-panel .chefe-page-intro{margin:0 0 16px;padding:12px 14px;border-radius:10px;background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.09);color:#b4b4c0;font-size:.875rem}
    .chefe-panel .grid6{display:grid!important;grid-template-columns:repeat(6,minmax(0,1fr))!important;gap:12px;margin-bottom:16px}
    .chefe-panel .grid2{display:grid!important;grid-template-columns:1.2fr 1fr!important;gap:12px}
    .chefe-panel .section{margin-top:16px}
    .chefe-panel .card{background:#2c2c36;border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:16px;margin-bottom:0}
    .chefe-panel .card--kpi{display:flex;flex-direction:column;gap:8px}
    .chefe-panel .card--kpi .kpi-icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:rgba(249,115,22,.14);color:#f97316}
    .chefe-panel .k{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#b4b4c0}
    .chefe-panel .v{font-size:1.65rem;font-weight:800;color:#f97316;line-height:1.1}
    .chefe-panel .card h3{margin:0 0 12px;font-size:1.05rem;display:flex;align-items:center;gap:8px;color:#f4f4f8}
    .chefe-panel .card h3 i{color:#ea580c}
    .chefe-panel .list .row{display:flex!important;justify-content:space-between;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.09)}
    .chefe-panel .tbl{width:100%;border-collapse:collapse}
    .chefe-panel .tbl td,.chefe-panel .tbl th{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.09);text-align:left}
    .chefe-panel .tbl th{font-size:.7rem;text-transform:uppercase;color:#b4b4c0}
    .chefe-panel .muted{color:#b4b4c0;font-size:.85rem}
    .chefe-panel .pill{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:.72rem;font-weight:600;border:1px solid rgba(255,255,255,.12)}
    .chefe-panel .pill.red{background:rgba(239,68,68,.15);color:#fca5a5;border-color:rgba(239,68,68,.35)}
    .chefe-panel .kpi-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .chefe-panel .kpi-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(7,7,10,.68);backdrop-filter:blur(6px);z-index:10040}
    .chefe-panel .kpi-modal.is-open{display:flex}
    .chefe-panel .kpi-form{display:block;width:min(680px,100%);max-height:min(88vh,760px);overflow:auto;padding:18px;background:linear-gradient(180deg,#2f2f39,#282831);border:1px solid rgba(255,255,255,.12);border-radius:18px;box-shadow:0 22px 60px rgba(0,0,0,.45)}
    .chefe-panel .kpi-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
    .chefe-panel .kpi-modal-title{display:flex;flex-direction:column;gap:4px}
    .chefe-panel .kpi-modal-title h4{margin:0;font-size:1.02rem;color:#fff}
    .chefe-panel .kpi-modal-title p{margin:0;font-size:.8rem;color:#9ca3af}
    .chefe-panel .kpi-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .chefe-panel .kpi-field{display:flex;flex-direction:column;gap:7px;padding:10px;border:1px solid rgba(255,255,255,.08);border-radius:12px;background:rgba(0,0,0,.12);min-width:0;overflow:hidden}
    .chefe-panel .kpi-field span{font-size:.88rem;font-weight:700;color:#f2f2f7;line-height:1.2}
    .chefe-panel .kpi-field small{font-size:.68rem;color:#9ca3af;line-height:1.3;min-height:16px}
    .chefe-panel .kpi-field input{width:100%;max-width:100%;min-width:0;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:#232327;color:#f4f4f8;font-size:.95rem;outline:none;box-shadow:none;display:block;min-height:42px;box-sizing:border-box}
    .chefe-panel .kpi-field input:focus{border-color:rgba(249,115,22,.55);box-shadow:0 0 0 3px rgba(249,115,22,.14)}
    .chefe-panel .kpi-meta{margin-top:12px;padding:10px 12px;border-radius:12px;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.06);font-size:.76rem;color:#b4b4c0;line-height:1.5}
    .chefe-panel .kpi-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
    .chefe-panel .kpi-actions .btn{min-height:42px}
    .chefe-panel .kpi-note{margin:12px 0 0;font-size:.74rem;color:#9ca3af}
    .chefe-panel .kpi-table td:last-child{text-align:right}
    .chefe-panel .kpi-table strong{font-size:1rem;color:#fff}
    @media (max-width: 820px){
      .chefe-panel .kpi-grid{grid-template-columns:1fr}
      .chefe-panel .kpi-actions{flex-direction:column}
      .chefe-panel .kpi-actions .btn{width:100%}
      .chefe-panel .kpi-modal{padding:12px}
      .chefe-panel .kpi-form{padding:14px;max-height:90vh}
      .chefe-panel .kpi-field{padding:10px}
    }
    .chefe-panel .pill.blue{background:rgba(249,115,22,.14);color:#fdba74;border-color:rgba(249,115,22,.35)}
    .chefe-panel .pill.green{background:rgba(34,197,94,.12);color:#86efac;border-color:rgba(34,197,94,.35)}
    .chefe-panel .work-card{display:grid;grid-template-columns:1.4fr 1fr minmax(180px,.9fr);gap:14px;padding:14px;margin-bottom:10px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:#2a2a2a}
    .chefe-panel .mech-top-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
    .chefe-panel .mech-top-stats .mini{padding:12px;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:#2a2a2a;text-align:center}
    .chefe-panel .mech-top-stats .n{font-size:1.5rem;font-weight:800;color:#f97316}
    .chefe-panel .mech-top-stats .l{font-size:.68rem;text-transform:uppercase;color:#b4b4c0}
    .chefe-panel .filters{display:grid;grid-template-columns:1fr auto 200px;gap:10px;margin-bottom:14px;align-items:end}
    .chefe-panel input,.chefe-panel select{width:100%;min-height:44px;padding:10px 14px;border-radius:10px;background:#2a2a2a;color:#f4f4f8;border:1px solid rgba(255,255,255,.14);font-size:.9rem}
    .chefe-panel .chefe-empty{padding:28px;text-align:center;border:1px dashed rgba(249,115,22,.35);border-radius:14px;color:#b4b4c0}
    .chefe-panel .chefe-empty i{display:block;font-size:2rem;color:#ea580c;margin-bottom:8px}
    @media(max-width:980px){.chefe-panel .grid6{grid-template-columns:repeat(2,1fr)!important}.chefe-panel .grid2,.chefe-panel .work-card,.chefe-panel .filters{grid-template-columns:1fr!important}}
    @media(max-width:520px){.chefe-panel .grid6{grid-template-columns:1fr!important}}
    .chefe-panel .mech-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:10px!important;margin-top:12px!important}
    .chefe-panel .mech-list-head{display:flex!important;justify-content:space-between;align-items:center;margin-bottom:12px}
    .chefe-panel .mech-list-name{display:flex;align-items:center;gap:10px}
    .chefe-panel .mech-avatar{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;background:linear-gradient(135deg,#ea580c,#f97316);color:#121218}
    .chefe-panel .mech-box{padding:12px 8px;border-radius:10px;background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.1);text-align:center}
    .chefe-panel .mech-box .num{font-size:1.6rem;font-weight:800;line-height:1.1}
    .chefe-panel .mech-box .cap{margin-top:4px;font-size:.62rem;text-transform:uppercase;color:#b4b4c0}
    .chefe-panel .mech-box--assigned .num{color:#fdba74}
    .chefe-panel .mech-box--progress .num{color:#f97316}
    .chefe-panel .mech-box--done .num{color:#86efac}
    .chefe-panel .mech-box--total .num{color:#fed7aa}
    .chefe-panel .orc-stack{display:flex;flex-direction:column;gap:28px;padding-top:8px}
    .chefe-panel .orc-card{padding:28px;border-radius:18px;border-left:5px solid #ea580c;background:#2c2c36;margin-bottom:0}
    .chefe-panel .orc-card__compact{display:flex;flex-direction:column;gap:18px}
    .chefe-panel .orc-card__title{font-size:1.15rem;line-height:1.35;margin-top:4px}
    .chefe-panel .orc-card__badges{gap:10px;margin-top:12px}
    .chefe-panel .orc-card__service{display:flex;align-items:center;gap:10px;line-height:1.5}
    .chefe-panel .orc-card__teaser{gap:10px 14px;line-height:1.55}
    .chefe-panel .orc-card__toolbar{margin-top:8px;padding-top:8px}
    .chefe-panel .orc-btn-details{padding:12px 20px!important;min-height:44px}
    .orc-details,.orc-details-sub,.orc-line-scroll,.orc-line-table{display:none!important;visibility:hidden!important;height:0!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}
    .chefe-panel .orc-chefe-form{display:flex;flex-direction:column;gap:20px;width:100%}
.chefe-panel .orc-form-block{display:flex;flex-direction:column;gap:16px;width:100%}
.chefe-panel .orc-quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 20px;align-items:start}
.chefe-panel .orc-label{display:flex;flex-direction:column;gap:8px;margin:0}
.chefe-panel .orc-label__title{font-weight:600;color:#f4f4f8;font-size:.9rem}
.chefe-panel .orc-hint{font-size:.75rem;color:#b4b4c0;line-height:1.4}
.chefe-panel .orc-quick-row{display:flex;flex-direction:column;gap:10px}
.chefe-panel .orc-quick-presets{display:flex;flex-wrap:wrap;gap:8px}
.chefe-panel .orc-preset{padding:6px 12px;border-radius:8px;border:1px solid rgba(249,115,22,.4);background:rgba(0,0,0,.2);color:#fdba74;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit}
.chefe-panel .orc-preset:hover{background:rgba(249,115,22,.2);border-color:#ea580c}
.chefe-panel .orc-input,.chefe-panel .orc-label input{width:100%;min-height:46px;padding:10px 14px;border-radius:10px;background:#1a1a1f!important;border:1px solid rgba(255,255,255,.16)!important;color:#f4f4f8;font-size:.95rem;box-sizing:border-box}
.chefe-panel .orc-input:focus{border-color:#ea580c!important;box-shadow:0 0 0 3px rgba(249,115,22,.2)!important;outline:none}
.chefe-panel .orc-input.is-dimmed{opacity:.45}
.chefe-panel .orc-actions{display:flex!important;flex-direction:column!important;align-items:stretch!important;gap:14px!important;width:100%!important;margin-top:8px;padding-top:20px;border-top:1px solid rgba(255,255,255,.1);box-sizing:border-box}
.chefe-panel .orc-live-total{display:flex;align-items:center;gap:10px;width:100%;min-height:48px;padding:12px 14px;border-radius:10px;background:rgba(249,115,22,.1);border:1px dashed rgba(249,115,22,.35);color:#b4b4c0;font-size:.9rem;box-sizing:border-box}
.chefe-panel .orc-live-total.is-ready{background:rgba(34,197,94,.1);border-style:solid;border-color:rgba(34,197,94,.35);color:#bbf7d0}
.chefe-panel .orc-live-total.is-lines{background:rgba(249,115,22,.14);border-style:solid;border-color:rgba(249,115,22,.4);color:#ffedd5}
.chefe-panel .btn-orc-send,.chefe-panel .btn.btn-orc-send{display:inline-flex!important;align-items:center;justify-content:center;gap:10px;width:100%!important;max-width:100%!important;min-height:50px;margin:0!important;padding:14px 20px!important;background:linear-gradient(135deg,#ea580c,#f97316)!important;color:#121218!important;border:none!important;font-weight:700;font-size:1rem;box-sizing:border-box}
.chefe-panel .btn-orc-send.is-loading{opacity:.85;pointer-events:none}
    .chefe-panel .pill.orange{background:rgba(249,115,22,.18);color:#fdba74;border-color:rgba(249,115,22,.4)}
    .chefe-panel .orc-card__panel{margin-top:24px;padding-top:24px;border-top:1px solid rgba(255,255,255,.1)}
    .chefe-panel .orc-card__panel.is-hidden{display:none!important}
    .chefe-panel .orc-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 24px;margin-bottom:24px;padding:20px;border-radius:14px;background:rgba(0,0,0,.22);border:1px solid rgba(255,255,255,.1)}
    .chefe-panel .orc-detail-item--full{grid-column:1/-1}
    .chefe-panel .orc-detail-label{font-size:.68rem;text-transform:uppercase;color:#b4b4c0;font-weight:600}
    .chefe-panel .orc-detail-value{font-size:.88rem;color:#f4f4f8}
    .chefe-panel .orc-btn-details{background:rgba(249,115,22,.12)!important;border:1px solid rgba(249,115,22,.35)!important;color:#fdba74!important}
    @media(max-width:700px){.chefe-panel .mech-grid{grid-template-columns:repeat(2,1fr)!important}.chefe-panel .orc-quick-grid,.chefe-panel .orc-detail-grid{grid-template-columns:1fr!important}.chefe-panel .orc-quick-presets{justify-content:flex-start}}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="chefe-panel">
  <div class="wrap">
    <header class="top">
      <div>
        <div class="logo">Auto<span class="dot">Manager</span></div>
        <p class="chefe-brand__sub">Olá, <strong><?= $safe($chefe_nome) ?></strong> — painel da oficina</p>
      </div>
      <div class="chefe-top__actions">
        <?php if ($chefeNotifTotal > 0): ?>
        <a class="btn chefe-notif-btn" href="?tab=<?= $safe($chefeNotifTab) ?>" title="<?= (int) $chefeNotifTotal ?> ação(ões) pendente(s) na oficina" aria-label="<?= (int) $chefeNotifTotal ?> notificações da oficina">
          <i class="bi bi-bell-fill" aria-hidden="true"></i>
          <span class="chefe-notif-btn__count"><?= $chefeNotifTotal > 99 ? '99+' : (int) $chefeNotifTotal ?></span>
        </a>
        <?php endif; ?>
        <a class="btn btn-primary btn-cta" href="index.php?preview=1#book-form" target="_blank" rel="noopener"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Nova marcação</a>
        <a class="btn" href="index.php?preview=1" target="_blank" rel="noopener"><i class="bi bi-house" aria-hidden="true"></i> Site</a>
        <a class="btn red" href="?logout=1"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair</a>
      </div>
    </header>

    <nav class="tabs" aria-label="Secções do painel">
      <a class="tab <?= $tab==='dashboard'?'active':'' ?>" href="?tab=dashboard"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a>
      <a class="tab <?= $tab==='orcamentos'?'active':'' ?>" href="?tab=orcamentos"><i class="bi bi-receipt" aria-hidden="true"></i><span>Orçamentos</span><?php if ($totalOrcPendentes > 0): ?><span class="chefe-tab__badge" title="Pendentes"><?= (int)$totalOrcPendentes ?></span><?php endif; ?></a>
      <a class="tab <?= $tab==='para_atribuir'?'active':'' ?>" href="?tab=para_atribuir"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Atribuir</span><?php if ($totalParaAtribuir > 0): ?><span class="chefe-tab__badge" title="Por atribuir"><?= (int)$totalParaAtribuir ?></span><?php endif; ?></a>
      <a class="tab <?= $tab==='mecanicos'?'active':'' ?>" href="?tab=mecanicos"><i class="bi bi-people" aria-hidden="true"></i><span>Mecânicos</span></a>
      <a class="tab <?= $tab==='trabalhos'?'active':'' ?>" href="?tab=trabalhos"><i class="bi bi-list-task" aria-hidden="true"></i><span>Trabalhos</span></a>
    </nav>

    <p class="chefe-page-intro"><?= $safe($chefePageIntros[$tab] ?? '') ?></p>

    <?php if ($mensagem !== ''): ?>
    <div class="chefe-alerts"><?= $mensagem ?></div>
    <?php endif; ?>

    <?php if ($tab === 'dashboard'): ?>
      <div class="grid6">
        <div class="card card--kpi"><span class="kpi-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span><div class="k">Aprovados</div><div class="v"><?= $aprovados ?></div></div>
        <div class="card card--kpi"><span class="kpi-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span><div class="k">Atribuídos</div><div class="v"><?= $atribuidos ?></div></div>
        <div class="card card--kpi"><span class="kpi-icon"><i class="bi bi-gear-wide-connected" aria-hidden="true"></i></span><div class="k">Em andamento</div><div class="v"><?= $emAndamento ?></div></div>
        <div class="card card--kpi"><span class="kpi-icon"><i class="bi bi-flag-fill" aria-hidden="true"></i></span><div class="k">Concluídos</div><div class="v"><?= $concluidos ?></div></div>
        <div class="card card--kpi"><span class="kpi-icon"><i class="bi bi-currency-euro" aria-hidden="true"></i></span><div class="k">Receita total</div><div class="v"><?= number_format($receitaTotal,0,',','.') ?> €</div></div>
        <div class="card card--kpi"><span class="kpi-icon"><i class="bi bi-calendar-month" aria-hidden="true"></i></span><div class="k">Receita (mês)</div><div class="v"><?= number_format($receitaMes,0,',','.') ?> €</div></div>
      </div>

      <div class="grid2 section">
        <div class="card list">
          <h3><i class="bi bi-exclamation-triangle"></i> Prioridades da Oficina</h3>
          <?php if ($paraAtribuir && $paraAtribuir->num_rows): while($w=$paraAtribuir->fetch_assoc()): ?>
            <div class="row">
              <div>
                <strong><?= $safe($w['tipo_servico']) ?> - <?= $safe($w['matricula']) ?></strong>
                <div class="muted"><?= $safe($w['nome']) ?> | <?= !empty($w['data_desejada']) ? date('d/m/Y', strtotime($w['data_desejada'])) : 'Sem data' ?><?= !empty($w['hora_desejada']) ? ' às '.$safe($w['hora_desejada']) : '' ?></div>
              </div>
              <span class="pill red">Atrasado</span>
            </div>
          <?php endwhile; else: ?><div class="chefe-empty"><i class="bi bi-emoji-smile" aria-hidden="true"></i>Sem prioridades urgentes — tudo em dia.</div><?php endif; ?>
        </div>
        <div class="card">
          <div class="kpi-head">
            <h3 style="margin:0;"><i class="bi bi-speedometer2"></i> KPIs Operacionais</h3>
            <button type="button" class="btn" id="btnToggleKpis"><i class="bi bi-pencil-square" aria-hidden="true"></i> Alterar KPIs</button>
          </div>
          <table class="tbl kpi-table">
            <tr><td>Agenda de hoje</td><td><strong><?= $agendaHoje ?></strong></td></tr>
            <tr><td>Serviços atrasados</td><td><strong><?= $atrasados ?></strong></td></tr>
            <tr><td>Equipa ocupada</td><td><strong><?= $equipaOcupada ?>%</strong></td></tr>
            <tr><td>Taxa de conclusão (mês)</td><td><strong><?= $taxaConclusaoMes ?>%</strong></td></tr>
            <tr><td>Ticket médio (mês)</td><td><strong><?= number_format($ticketMedioMes,0,',','.') ?> €</strong></td></tr>
            <tr><td>Ticket médio (histórico)</td><td><strong><?= number_format($ticketMedioHist,0,',','.') ?> €</strong></td></tr>
          </table>
          <div id="kpiModal" class="kpi-modal" aria-hidden="true">
          <form method="post" id="formKpisOperacionais" class="kpi-form">
            <div class="kpi-modal-head">
              <div class="kpi-modal-title">
                <h4>Editar KPIs operacionais</h4>
                <p>Ajuste manualmente os valores mostrados no dashboard.</p>
              </div>
              <button type="button" class="btn" id="btnCloseKpis"><i class="bi bi-x-lg" aria-hidden="true"></i> Fechar</button>
            </div>
            <div class="kpi-grid">
              <label class="kpi-field">
                <span>Agenda de hoje</span>
                <small>Número manual apresentado no dashboard.</small>
                <input type="number" min="0" name="kpi_agenda_hoje" value="<?= htmlspecialchars((string) $agendaHoje, ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label class="kpi-field">
                <span>Serviços atrasados</span>
                <small>Quantidade mostrada no painel principal.</small>
                <input type="number" min="0" name="kpi_servicos_atrasados" value="<?= htmlspecialchars((string) $atrasados, ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label class="kpi-field">
                <span>Equipa ocupada (%)</span>
                <small>Valor percentual entre 0 e 100.</small>
                <input type="number" min="0" max="100" name="kpi_equipa_ocupada" value="<?= htmlspecialchars((string) $equipaOcupada, ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label class="kpi-field">
                <span>Taxa de conclusão (mês) %</span>
                <small>Percentagem de conclusão mensal.</small>
                <input type="number" min="0" max="100" name="kpi_taxa_conclusao_mes" value="<?= htmlspecialchars((string) $taxaConclusaoMes, ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label class="kpi-field">
                <span>Ticket médio (mês)</span>
                <small>Valor em euros para o mês atual.</small>
                <input type="number" min="0" step="0.01" name="kpi_ticket_medio_mes" value="<?= htmlspecialchars(number_format($ticketMedioMes, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label class="kpi-field">
                <span>Ticket médio (histórico)</span>
                <small>Valor médio acumulado em euros.</small>
                <input type="number" min="0" step="0.01" name="kpi_ticket_medio_historico" value="<?= htmlspecialchars(number_format($ticketMedioHist, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
              </label>
            </div>
            <p class="kpi-note">Se quiser voltar aos cálculos automáticos, use "Repor automáticos".</p>
            <div class="kpi-actions">
              <button type="submit" name="guardar_kpis_operacionais" class="btn"><i class="bi bi-save" aria-hidden="true"></i> Guardar KPIs</button>
              <button type="submit" name="repor_kpis_operacionais" class="btn red" data-am-confirm="Repor os KPIs para os valores automáticos?" data-am-confirm-title="Repor KPIs"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Repor automáticos</button>
            </div>
            <div class="kpi-meta">
              Sugestão automática atual: agenda <?= (int) $kpisOperacionaisAuto['agenda_hoje'] ?>, atrasados <?= (int) $kpisOperacionaisAuto['servicos_atrasados'] ?>, equipa <?= (int) $kpisOperacionaisAuto['equipa_ocupada'] ?>%, conclusão <?= (int) $kpisOperacionaisAuto['taxa_conclusao_mes'] ?>%, ticket mês <?= number_format((float) $kpisOperacionaisAuto['ticket_medio_mes'],0,',','.') ?> €, histórico <?= number_format((float) $kpisOperacionaisAuto['ticket_medio_historico'],0,',','.') ?> €.
            </div>
          </form>
          </div>
        </div>
      </div>

      <div class="grid2 section">
        <div class="card">
          <h3><i class="bi bi-list-stars"></i> Serviços Mais Pedidos</h3>
          <table class="tbl">
            <?php if ($topServicos && $topServicos->num_rows): while($s=$topServicos->fetch_assoc()): ?>
              <tr><td><?= $safe($s['servico']) ?></td><td><?= (int)$s['total'] ?> marcações</td></tr>
            <?php endwhile; endif; ?>
          </table>
        </div>
        <div class="card">
          <h3><i class="bi bi-people-fill"></i> Carga da Equipa</h3>
          <table class="tbl">
            <thead><tr><th>Mecânico</th><th>Ativos</th><th>Concluídas</th></tr></thead>
            <tbody>
            <?php if ($cargaEquipa && $cargaEquipa->num_rows): while($m=$cargaEquipa->fetch_assoc()): ?>
              <tr><td><?= $safe($m['nome_mecanico']) ?></td><td><?= (int)$m['ativas'] ?></td><td><?= (int)$m['concluidas'] ?></td></tr>
            <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card section">
        <h3><i class="bi bi-graph-up-arrow"></i> Trabalhos (7 dias)</h3>
        <div style="height:290px"><canvas id="chart7"></canvas></div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'para_atribuir'): ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
          <h3><i class="bi bi-person-plus-fill"></i> Trabalhos Para Atribuir</h3>
          <span class="pill blue"><?= (int)$totalParaAtribuir ?> trabalhos</span>
        </div>
        <?php if ($totalParaAtribuir > 0): ?>
          <div class="banner-success">
            <i class="bi bi-bell-fill"></i> <?= (int)$totalParaAtribuir ?> trabalho(s) com orçamento aceite aguardam atribuição a um mecânico.
          </div>
        <?php endif; ?>
        <div class="muted" style="margin-bottom:10px">Organize por urgência e atribua rapidamente ao mecânico disponível.</div>
        <div class="mech-top-stats" style="margin-bottom:10px;">
          <div class="mini"><div class="n"><?= (int)$totalParaAtribuir ?></div><div class="l">Total para atribuir</div></div>
          <div class="mini"><div class="n"><?= (int)$totalParaHoje ?></div><div class="l">Com data para hoje</div></div>
          <div class="mini"><div class="n"><?= (int)$totalParaAtraso ?></div><div class="l">Com data em atraso</div></div>
          <div class="mini"><div class="n"><?= (int)$totalOrcPendentes ?></div><div class="l">Orçamentos pendentes</div></div>
        </div>
        <form class="filters" method="GET">
          <input type="hidden" name="tab" value="para_atribuir">
          <input type="search" name="q" placeholder="Cliente, serviço ou matrícula…" value="<?= $safe($qTexto) ?>" aria-label="Pesquisar trabalhos">
          <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Filtrar</button>
          <select name="urgencia" aria-label="Urgência">
            <option value="">Todas as urgências</option>
            <option value="atras" <?= $qUrgencia==='atras'?'selected':'' ?>>Em atraso</option>
            <option value="hoje" <?= $qUrgencia==='hoje'?'selected':'' ?>>Para hoje</option>
            <option value="futuro" <?= $qUrgencia==='futuro'?'selected':'' ?>>Futuro</option>
          </select>
        </form>
        <?php if ($avisoFiltroAtribuir !== ''): ?>
          <div class="muted" style="margin-bottom:10px;color:#ffcf9e;"><?= $safe($avisoFiltroAtribuir) ?></div>
        <?php endif; ?>
        <?php if ($paraAtribuir && $paraAtribuir->num_rows): while($w=$paraAtribuir->fetch_assoc()): ?>
          <?php
            $estadoPag = strtolower(trim((string)($w['estado_pagamento'] ?? 'pendente')));
            $estadoOrcCliente = strtolower(trim((string)($w['estado_orcamento_cliente'] ?? 'pendente')));
            $badgePagCls = in_array($estadoOrcCliente, ['aceite', 'aceito'], true) ? 'green' : 'warn';
            $badgePagTxt = in_array($estadoOrcCliente, ['aceite', 'aceito'], true) ? 'Orçamento aceite' : (in_array($estadoPag, ['pago', 'paga', 'paid'], true) ? 'Pago' : 'Pendente');
            $estadoTrabAtrib = strtolower(trim((string)($w['estado_do_trabalho'] ?? 'por fazer')));
            $podeAtribuirUi = in_array($estadoOrcCliente, ['aceite', 'aceito'], true)
                && chefe_servico_estado_aguarda_atribuicao((string)($w['estado_do_trabalho'] ?? 'por fazer'));
          ?>
          <div class="work-card">
            <div>
              <strong><?= $safe($w['tipo_servico']) ?></strong>
              <span class="pill <?= $badgePagCls ?>" style="margin-left:8px;vertical-align:middle;"><?= $badgePagTxt ?></span>
              <div class="muted"><?= $safe($w['nome']) ?> • <?= $safe($w['email']) ?> • <?= $safe($w['telefone']) ?></div>
              <div class="muted">Veículo: <?= $safe($w['matricula']) ?> | Data: <?= !empty($w['data_desejada']) ? date('d/m/Y', strtotime($w['data_desejada'])) : '-' ?> <?= $safe($w['hora_desejada']) ?></div>
            </div>
            <div class="muted"><?= $safe($w['notas_internas'] ?: 'Sem notas do veículo') ?></div>
            <form method="POST">
              <input type="hidden" name="servico_id" value="<?= (int)$w['id'] ?>">
              <select name="mecanico_id" required <?= $podeAtribuirUi ? '' : 'disabled' ?>>
                <option value="">Escolher Mecânico</option>
                <?php foreach ($mecanicosRows as $mcOpt): ?>
                  <option value="<?= (int)$mcOpt['id_mecanico'] ?>"><?= $safe($mcOpt['nome_mecanico']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary" style="margin-top:8px;width:100%" type="submit" name="atribuir_mecanico_chefe" <?= $podeAtribuirUi ? '' : 'disabled' ?>><i class="bi bi-person-check" aria-hidden="true"></i> Atribuir mecânico</button>
              <?php if (!$podeAtribuirUi): ?>
                <div class="muted" style="margin-top:8px;">Só pode atribuir trabalhos já com o orçamento aceite.</div>
              <?php endif; ?>
            </form>
          </div>
        <?php endwhile; else: ?><div class="chefe-empty"><i class="bi bi-inbox" aria-hidden="true"></i>Nenhum trabalho à espera de atribuição.</div><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'mecanicos'): ?>
      <div class="card">
        <div class="mech-head">
          <h3><i class="bi bi-wrench-adjustable-circle"></i> Desempenho dos Mecânicos</h3>
          <div class="muted">Visão de produtividade e carga atual da equipa técnica.</div>
        </div>
        <?php
          $totalMec = count($mecanicosRows);
          $totalAtivos = 0; $totalConcl = 0; $topNome = '-'; $topConcl = 0;
          foreach ($mecanicosRows as $mx) {
            $totalAtivos += (int)$mx['total'];
            $totalConcl += (int)$mx['concluidas'];
            if ((int)$mx['concluidas'] > $topConcl) { $topConcl = (int)$mx['concluidas']; $topNome = $mx['nome_mecanico']; }
          }
        ?>
        <div class="mech-top-stats">
          <div class="mini"><div class="n"><?= $totalMec ?></div><div class="l">Mecânicos Registados</div></div>
          <div class="mini"><div class="n"><?= $totalAtivos ?></div><div class="l">Trabalhos Ativos</div></div>
          <div class="mini"><div class="n"><?= $totalConcl ?></div><div class="l">Total Concluídos</div></div>
          <div class="mini"><div class="n" style="font-size:24px"><?= $safe($topNome) ?></div><div class="l">Top Produtividade (<?= $topConcl ?>)</div></div>
        </div>

        <?php foreach($mecanicosRows as $m): ?>
          <?php
            $totalAtivo = (int)$m['total'];
            if ($totalAtivo >= 6) {
                $cargaLabel = 'Alta';
                $cargaPill = 'red';
            } elseif ($totalAtivo >= 4) {
                $cargaLabel = 'Média';
                $cargaPill = 'blue';
            } else {
                $cargaLabel = 'Baixa';
                $cargaPill = 'orange';
            }
          ?>
          <div class="mech-list-card">
            <div class="mech-list-head">
              <div class="mech-list-name">
                <span class="mech-avatar" aria-hidden="true"><?= strtoupper(substr($m['nome_mecanico'], 0, 1)) ?></span>
                <strong><?= $safe($m['nome_mecanico']) ?></strong>
              </div>
              <span class="pill <?= $cargaPill ?>">Carga <?= $cargaLabel ?></span>
            </div>
            <div class="mech-grid">
              <div class="mech-box mech-box--assigned"><div class="num"><?= (int)$m['atribuidas'] ?></div><div class="cap">Atribuídas</div></div>
              <div class="mech-box mech-box--progress"><div class="num"><?= (int)$m['andamento'] ?></div><div class="cap">Em andamento</div></div>
              <div class="mech-box mech-box--done"><div class="num"><?= (int)$m['concluidas'] ?></div><div class="cap">Concluídas</div></div>
              <div class="mech-box mech-box--total"><div class="num"><?= $totalAtivo ?></div><div class="cap">Ativas (total)</div></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($mecanicosRows)): ?><div class="chefe-empty"><i class="bi bi-people" aria-hidden="true"></i>Ainda não há mecânicos registados.</div><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'orcamentos'): ?>
      <div class="card card--section">
        <div class="section-head">
          <h3><i class="bi bi-receipt"></i> Orçamentos pendentes</h3>
          <?php if ($totalOrcPendentes > 0): ?><span class="pill blue"><?= (int)$totalOrcPendentes ?> por enviar</span><?php endif; ?>
        </div>
        <?php if ($totalOrcRecusadosAviso > 0): ?>
          <div class="chefe-banner chefe-banner--warn">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= (int)$totalOrcRecusadosAviso ?> orçamento(s) recusados pelo cliente — reveja e reenvie se necessário.
          </div>
        <?php endif; ?>
        <p class="orc-help-banner"><i class="bi bi-lightbulb" aria-hidden="true"></i> Indica o <strong>total</strong> e envia — o cliente aceita ou recusa online.</p>
        <div class="orc-stack">
          <?php if ($orcamentosPendentes && $orcamentosPendentes->num_rows): while ($o = $orcamentosPendentes->fetch_assoc()):
              $dataAg = !empty($o['data_desejada']) ? strtotime((string) $o['data_desejada']) : null;
              $dataTxt = !empty($o['data_desejada'])
                  ? date('d/m/Y', strtotime((string) $o['data_desejada'])) . (!empty($o['hora_desejada']) ? ' · ' . $safe($o['hora_desejada']) : '')
                  : '—';
              ?>
            <?php
              $orcPanelId = 'orc-panel-' . (int) $o['id'];
              $veiculoTxt = trim(($o['marca'] ?? '') . ' ' . ($o['modelo'] ?? ''));
              $planoLabels = [
                  'normal' => 'Padrão',
                  'premium_4h' => 'Premium 4h',
                  'premium_8h' => 'Premium 8h',
                  'premium_24h' => 'Premium 24h',
              ];
              $planoKey = strtolower(trim((string) ($o['plano_pronto'] ?? 'normal')));
              $planoTxt = $planoLabels[$planoKey] ?? $planoKey;
              $estadoPag = strtolower(trim((string) ($o['estado_pagamento'] ?? 'pendente')));
              $pagTxt = in_array($estadoPag, ['pago', 'paga', 'paid'], true) ? 'Pago' : 'Pagamento pendente';
              $pagPill = in_array($estadoPag, ['pago', 'paga', 'paid'], true) ? 'green' : 'warn';
            ?>
            <article class="orc-card" data-orc-id="<?= (int) $o['id'] ?>">
              <div class="orc-card__compact">
                <div class="orc-card__head">
                  <div>
                    <span class="orc-card__id">Marcação #<?= (int) $o['id'] ?></span>
                    <span class="orc-card__title"><?= $safe($o['nome']) ?></span>
                    <div class="orc-card__badges">
                      <span class="pill"><?= $safe($o['matricula']) ?></span>
                      <?php if ($dataAg && $dataAg < strtotime('today')): ?>
                        <span class="pill red">Data em atraso</span>
                      <?php elseif ($dataAg && $dataAg === strtotime('today')): ?>
                        <span class="pill blue">Hoje</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <p class="orc-card__service"><i class="bi bi-wrench-adjustable-circle" aria-hidden="true"></i> <?= $safe($o['tipo_servico']) ?></p>
                <p class="orc-card__teaser muted">
                  <i class="bi bi-calendar3" aria-hidden="true"></i> <?= $dataTxt ?>
                  <?php if ($veiculoTxt !== ''): ?> · <i class="bi bi-car-front" aria-hidden="true"></i> <?= $safe($veiculoTxt) ?><?php endif; ?>
                </p>
                <div class="orc-card__toolbar">
                  <button type="button" class="btn orc-btn-details" aria-expanded="false" aria-controls="<?= $orcPanelId ?>">
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    <span class="orc-btn-details__label">Ver detalhes</span>
                  </button>
                </div>
              </div>

              <div class="orc-card__panel is-hidden" id="<?= $orcPanelId ?>" hidden>
                <div class="orc-detail-grid">
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-calendar-event" aria-hidden="true"></i> Data agendada</span>
                    <span class="orc-detail-value"><?= $dataTxt ?></span>
                  </div>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-envelope" aria-hidden="true"></i> Email</span>
                    <span class="orc-detail-value"><a href="mailto:<?= $safe($o['email']) ?>"><?= $safe($o['email']) ?></a></span>
                  </div>
                  <?php if (!empty($o['telefone'])): ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-telephone" aria-hidden="true"></i> Telefone</span>
                    <span class="orc-detail-value"><a href="tel:<?= preg_replace('/\s+/', '', $o['telefone']) ?>"><?= $safe($o['telefone']) ?></a></span>
                  </div>
                  <?php endif; ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-card-text" aria-hidden="true"></i> Matrícula</span>
                    <span class="orc-detail-value"><?= $safe($o['matricula']) ?></span>
                  </div>
                  <?php if ($veiculoTxt !== ''): ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-car-front" aria-hidden="true"></i> Veículo</span>
                    <span class="orc-detail-value"><?= $safe($veiculoTxt) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($o['nif'])): ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-person-badge" aria-hidden="true"></i> NIF</span>
                    <span class="orc-detail-value"><?= $safe($o['nif']) ?></span>
                  </div>
                  <?php endif; ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-truck" aria-hidden="true"></i> Plano de entrega</span>
                    <span class="orc-detail-value"><?= $safe($planoTxt) ?></span>
                  </div>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-credit-card" aria-hidden="true"></i> Pagamento</span>
                    <span class="orc-detail-value"><span class="pill <?= $pagPill ?>"><?= $pagTxt ?></span></span>
                  </div>
                  <?php if (!empty($o['custo']) && (float) $o['custo'] > 0): ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-currency-euro" aria-hidden="true"></i> Custo estimado (sistema)</span>
                    <span class="orc-detail-value"><?= number_format((float) $o['custo'], 2, ',', ' ') ?> €</span>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($o['tipo_servico'])): ?>
                  <div class="orc-detail-item">
                    <span class="orc-detail-label"><i class="bi bi-wrench-adjustable-circle" aria-hidden="true"></i> Serviço escolhido</span>
                    <span class="orc-detail-value"><?= $safe($o['tipo_servico']) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty(trim((string) ($o['notas_internas'] ?? '')))): ?>
                  <div class="orc-detail-item orc-detail-item--full">
                    <span class="orc-detail-label"><i class="bi bi-journal-text" aria-hidden="true"></i> Notas internas</span>
                    <span class="orc-detail-value"><?= nl2br($safe($o['notas_internas'])) ?></span>
                  </div>
                  <?php endif; ?>
                </div>

              <form method="POST" class="orc-chefe-form" data-orc-form>
                <input type="hidden" name="servico_id" value="<?= (int) $o['id'] ?>">

                <div class="orc-form-block">
                  <div class="orc-quick-grid">
                    <label class="orc-label">
                      <span class="orc-label__title">Total rápido (€)</span>
                      <span class="orc-hint">Valor sem IVA.</span>
                      <div class="orc-quick-row">
                        <input type="number" class="orc-input" name="valor_orcamento" min="0" step="0.01" placeholder="ex.: 350" inputmode="decimal">
                        <div class="orc-quick-presets" role="group" aria-label="Valores rápidos">
                          <?php foreach ([150, 250, 350, 500] as $preset): ?>
                            <button type="button" class="orc-preset" data-preset="<?= (int) $preset ?>"><?= (int) $preset ?> €</button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </label>
                    <label class="orc-label">
                      <span class="orc-label__title">Observações ao cliente</span>
                      <span class="orc-hint">Aparecem no email e na área do cliente.</span>
                      <input type="text" class="orc-input" name="observacoes_orcamento" placeholder="Opcional — ex.: inclui diagnóstico">
                    </label>
                  </div>
                </div>

                <div class="orc-actions">
                  <div class="orc-live-total" data-orc-live aria-live="polite">
                    <i class="bi bi-calculator" aria-hidden="true"></i>
                    <span data-orc-live-text>Indica um valor para ver o total.</span>
                  </div>
                  <button class="btn btn-primary btn-orc-send" type="submit" name="enviar_orcamento_chefe">
                    <i class="bi bi-send-fill" aria-hidden="true"></i>
                    <span>Enviar orçamento ao cliente</span>
                  </button>
                </div>
              </form>
              </div>
            </article>
          <?php endwhile; else: ?>
            <div class="chefe-empty"><i class="bi bi-receipt" aria-hidden="true"></i>Sem orçamentos por enviar — está tudo em dia.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'trabalhos'): ?>
      <div class="card">
        <h3><i class="bi bi-list-check"></i> Todos os Trabalhos</h3>
        <form class="filters" method="GET">
          <input type="hidden" name="tab" value="trabalhos">
          <input type="search" name="q" placeholder="Cliente, serviço ou matrícula…" value="<?= $safe($qTexto) ?>" aria-label="Pesquisar trabalhos">
          <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Filtrar</button>
          <select name="status" aria-label="Estado">
            <option value="">Todos os estados</option>
            <option value="por fazer">Pendente</option>
            <option value="andamento">Em andamento</option>
            <option value="conclu">Concluído</option>
          </select>
        </form>
        <?php if ($trabalhos && $trabalhos->num_rows): while($t=$trabalhos->fetch_assoc()): ?>
          <?php
            $estadoTxt = strtolower(trim((string)($t['estado_do_trabalho'] ?: 'pendente')));
            $estadoCls = 'warn';
            if (strpos($estadoTxt, 'conclu') !== false || strpos($estadoTxt, 'pronto') !== false) {
              $estadoCls = 'done';
            } elseif (strpos($estadoTxt, 'andamento') !== false) {
              $estadoCls = 'progress';
            }
            $podeReatribuirUi = chefe_estado_permite_reatribuir($estadoTxt);
          ?>
          <div class="work-card">
            <div>
              <strong><?= $safe($t['tipo_servico']) ?></strong>
              <span class="pill <?= $estadoCls ?>"><?= $safe($t['estado_do_trabalho'] ?: 'Pendente') ?></span>
              <div class="muted"><?= $safe($t['nome']) ?> • <?= $safe($t['matricula']) ?></div>
            </div>
            <div class="muted">Data: <?= !empty($t['data_desejada']) ? date('d/m/Y', strtotime($t['data_desejada'])) : '-' ?> <?= $safe($t['hora_desejada']) ?></div>
            <div>
              <div class="muted" style="margin-bottom:8px;">Mecânico: <?= $safe($t['nome_mecanico']) ?></div>
              <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="servico_id_re" value="<?= (int)$t['id'] ?>">
                <select name="mecanico_id_re" required <?= $podeReatribuirUi ? '' : 'disabled' ?>>
                  <option value="">Reatribuir Mecânico</option>
                  <?php foreach ($mecanicosRows as $mcOpt): ?>
                    <option value="<?= (int)$mcOpt['id_mecanico'] ?>" <?= ((int)$t['id_mecanico_ref'] === (int)$mcOpt['id_mecanico']) ? 'selected' : '' ?>><?= $safe($mcOpt['nome_mecanico']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit" name="reatribuir_mecanico_chefe" <?= $podeReatribuirUi ? '' : 'disabled' ?>><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Reatribuir</button>
                <?php if (!$podeReatribuirUi): ?>
                  <div class="muted" style="width:100%;">Reatribuição só disponível para trabalhos por fazer ou pendentes.</div>
                <?php endif; ?>
              </form>
            </div>
          </div>
        <?php endwhile; else: ?><div class="chefe-empty"><i class="bi bi-search" aria-hidden="true"></i>Nenhum trabalho encontrado com estes filtros.</div><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

<script>
const tab = <?= json_encode($tab) ?>;
if (tab === 'dashboard') {
  const btnKpis = document.getElementById('btnToggleKpis');
  const formKpis = document.getElementById('formKpisOperacionais');
  const kpiModal = document.getElementById('kpiModal');
  const btnCloseKpis = document.getElementById('btnCloseKpis');
  function closeKpiModal() {
    if (kpiModal) {
      kpiModal.classList.remove('is-open');
      kpiModal.setAttribute('aria-hidden', 'true');
    }
    if (btnKpis) {
      btnKpis.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i> Alterar KPIs';
    }
  }
  if (btnKpis && formKpis && kpiModal) {
    btnKpis.addEventListener('click', function () {
      const aberto = kpiModal.classList.contains('is-open');
      kpiModal.classList.toggle('is-open', !aberto);
      kpiModal.setAttribute('aria-hidden', aberto ? 'true' : 'false');
      btnKpis.innerHTML = aberto
        ? '<i class="bi bi-pencil-square" aria-hidden="true"></i> Alterar KPIs'
        : '<i class="bi bi-x-lg" aria-hidden="true"></i> Fechar edição';
    });
  }
  if (btnCloseKpis) btnCloseKpis.addEventListener('click', closeKpiModal);
  if (kpiModal) {
    kpiModal.addEventListener('click', function (e) {
      if (e.target === kpiModal) closeKpiModal();
    });
  }
  new Chart(document.getElementById('chart7'), {
    type: 'line',
    data: {
      labels: <?= json_encode($labels7, JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        data: <?= json_encode($vals7) ?>,
        borderColor: '#ea580c',
        backgroundColor: 'rgba(234, 88, 12, 0.15)',
        fill: true,
        tension: .35,
        pointRadius: 4
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: {
        x: { ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#cbd5e1', precision: 0 }, grid: { color: 'rgba(255,255,255,.08)' } }
      },
      plugins: { legend: { display: false } }
    }
  });
}
(function () {
  function parseNum(el) {
    if (!el || el.value === '') return NaN;
    const v = parseFloat(String(el.value).replace(',', '.'));
    return Number.isFinite(v) ? v : NaN;
  }
  function formatEuro(n) {
    return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(n);
  }
  function updateForm(form) {
    const live = form.querySelector('[data-orc-live]');
    const liveText = form.querySelector('[data-orc-live-text]');
    const quickInput = form.querySelector('input[name="valor_orcamento"]');
    if (!live || !liveText) return;

    live.classList.remove('is-ready', 'is-lines');
    const quick = parseNum(quickInput);
    if (Number.isFinite(quick) && quick > 0) {
      live.classList.add('is-ready');
      liveText.textContent = 'Total: ' + formatEuro(quick) + ' · sem IVA';
    } else {
      liveText.textContent = 'Indica um valor para ver o total.';
    }
  }

  document.querySelectorAll('.orc-chefe-form').forEach(function (form) {
    form.addEventListener('input', function () { updateForm(form); });
    form.addEventListener('change', function () { updateForm(form); });

    form.querySelectorAll('.orc-preset').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const quickInput = form.querySelector('input[name="valor_orcamento"]');
        if (!quickInput || quickInput.disabled) return;
        quickInput.value = btn.getAttribute('data-preset');
        quickInput.focus();
        updateForm(form);
      });
    });

    form.addEventListener('submit', function () {
      const btn = form.querySelector('.btn-orc-send');
      const label = btn && btn.querySelector('span');
      if (btn) btn.classList.add('is-loading');
      if (label) label.textContent = 'A enviar…';
    });

    updateForm(form);
  });

  document.querySelectorAll('.orc-btn-details').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panelId = btn.getAttribute('aria-controls');
      var panel = panelId ? document.getElementById(panelId) : null;
      if (!panel) return;
      var open = btn.getAttribute('aria-expanded') === 'true';
      var nextOpen = !open;
      btn.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
      panel.classList.toggle('is-hidden', !nextOpen);
      panel.hidden = !nextOpen;
      var label = btn.querySelector('.orc-btn-details__label');
      var icon = btn.querySelector('i');
      if (label) label.textContent = nextOpen ? 'Ocultar detalhes' : 'Ver detalhes';
      if (icon) {
        icon.classList.toggle('bi-chevron-down', !nextOpen);
        icon.classList.toggle('bi-chevron-up', nextOpen);
      }
      if (nextOpen) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        const orcForm = panel.querySelector('[data-orc-form]');
        if (orcForm) updateForm(orcForm);
      }
    });
  });
})();
</script>
</body>
</html>
