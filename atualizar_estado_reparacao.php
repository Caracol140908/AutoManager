<?php
session_start();
require('ligar.php');
require_once __DIR__ . '/inc_veiculo_perfil.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada']);
    exit;
}

am_ensure_servico_fecho_cols($con);

$servico_id = (int) ($_POST['servico_id'] ?? 0);
$novo_estado = $_POST['novo_estado'] ?? '';
$notas = $_POST['notas'] ?? '';
$mecanico_id = (int) $_SESSION['mecanico_id'];

if ($servico_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID não especificado']);
    exit;
}

$cols = am_servico_colunas($con);
$idCol = am_servico_id_col($con);
$mecExpr = am_servico_mec_col_expr($con);
$estadoCol = isset($cols['estado_do_trabalho']) ? 'estado_do_trabalho' : (isset($cols['estado']) ? 'estado' : '');
$notasCol = '';
foreach (['notas_mecanico', 'notas_internas', 'notas', 'relatorio_mecanico', 'observacoes'] as $notasCandidate) {
    if (isset($cols[$notasCandidate])) {
        $notasCol = $notasCandidate;
        break;
    }
}
$horasCol = isset($cols['horas_trabalhadas']) ? 'horas_trabalhadas' : '';
$valorHoraCol = isset($cols['valor_hora']) ? 'valor_hora' : '';
$dataConclusaoCol = isset($cols['data_conclusao']) ? 'data_conclusao' : '';

if ($idCol === '' || $mecExpr === '' || $estadoCol === '') {
    echo json_encode(['success' => false, 'error' => 'Estrutura da base de dados inválida']);
    exit;
}

$sqlCheck = "SELECT $idCol AS id_ref, $mecExpr AS mec_ref FROM servico WHERE $idCol = ? AND $mecExpr = ? LIMIT 1";
$stmt = $con->prepare($sqlCheck);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare falhou: ' . $con->error]);
    exit;
}
$stmt->bind_param('ii', $servico_id, $mecanico_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Serviço não encontrado ou não autorizado']);
    exit;
}
$stmt->close();

$setParts = [];
$types = '';
$vals = [];

$setParts[] = "$estadoCol = ?";
$types .= 's';
$vals[] = $novo_estado;

if ($notasCol !== '') {
    $setParts[] = "$notasCol = ?";
    $types .= 's';
    $vals[] = $notas;
}

if ($novo_estado === 'Concluído' && $horasCol !== '') {
    $horas = isset($_POST['horas']) ? (float) $_POST['horas'] : 0;
    if ($horas <= 0) {
        echo json_encode(['success' => false, 'error' => 'Indique as horas trabalhadas.']);
        exit;
    }
    $setParts[] = "$horasCol = ?";
    $types .= 'd';
    $vals[] = $horas;
}

if ($novo_estado === 'Concluído' && $valorHoraCol !== '') {
    $valorHora = isset($_POST['valor_hora']) ? (float) $_POST['valor_hora'] : (float) AM_VALOR_HORA_PADRAO;
    if ($valorHora <= 0) {
        echo json_encode(['success' => false, 'error' => 'Indique o valor por hora.']);
        exit;
    }
    $setParts[] = "$valorHoraCol = ?";
    $types .= 'd';
    $vals[] = $valorHora;
}

if ($novo_estado === 'Concluído' && $dataConclusaoCol !== '') {
    $setParts[] = "$dataConclusaoCol = NOW()";
}

$sqlUpdate = "UPDATE servico SET " . implode(', ', $setParts) . " WHERE $idCol = ?";
$types .= 'i';
$vals[] = $servico_id;

$upd = $con->prepare($sqlUpdate);
if (!$upd) {
    echo json_encode(['success' => false, 'error' => 'Erro a preparar UPDATE: ' . $con->error]);
    exit;
}

$bindParams = array_merge([$types], $vals);
$refs = [];
foreach ($bindParams as $k => $v) {
    $refs[$k] = &$bindParams[$k];
}
call_user_func_array([$upd, 'bind_param'], $refs);

if ($upd->execute()) {
    if (stripos((string) $novo_estado, 'concl') !== false) {
        $rowFecho = null;
        $stFull = $con->prepare("SELECT * FROM servico WHERE $idCol = ? LIMIT 1");
        if ($stFull) {
            $stFull->bind_param('i', $servico_id);
            if ($stFull->execute()) {
                $resFull = $stFull->get_result();
                $rowFecho = $resFull ? $resFull->fetch_assoc() : null;
            }
            $stFull->close();
        }
        $horasFecho = am_servico_sanitizar_horas_fecho(isset($_POST['horas']) ? (float) $_POST['horas'] : 0, is_array($rowFecho) ? $rowFecho : []);
        $valorHoraFecho = am_servico_sanitizar_valor_hora_fecho(
            isset($_POST['valor_hora']) ? (float) $_POST['valor_hora'] : (float) AM_VALOR_HORA_PADRAO,
            is_array($rowFecho) ? $rowFecho : []
        );
        if ($horasFecho <= 0) {
            echo json_encode(['success' => false, 'error' => 'Indique horas trabalhadas válidas (máx. 24h por serviço).']);
            $upd->close();
            exit;
        }
        $pecasFecho = am_pecas_substituidas_parse_struct(
            am_pecas_substituidas_from_json_post((string) ($_POST['pecas_json'] ?? ''))
        );
        am_servico_guardar_fecho_fatura($con, $servico_id, $idCol, $horasFecho, $valorHoraFecho, $pecasFecho);
        am_servico_sincronizar_divida_pagamento($con, $servico_id, $idCol);
        am_servico_atribuir_numero_fatura($con, $servico_id, $idCol);
        require_once __DIR__ . '/inc_notificar_cliente_email.php';
        $stDif = $con->prepare("SELECT * FROM servico WHERE $idCol = ? LIMIT 1");
        if ($stDif) {
            $stDif->bind_param('i', $servico_id);
            if ($stDif->execute()) {
                $rDif = $stDif->get_result();
                $rowDif = $rDif ? $rDif->fetch_assoc() : null;
                if ($rowDif && am_servico_precisa_pagar_trabalho_mecanico($rowDif)) {
                    @am_notificar_cliente_pagamento_complementar(
                        $con,
                        $servico_id,
                        am_servico_valor_trabalho_mecanico_a_pagar($rowDif)
                    );
                }
            }
            $stDif->close();
        }
    }

    $matriculaResp = '';
    $tipoServicoResp = '';
    $enviarEmailCliente = stripos((string) $novo_estado, 'concl') !== false && isset($cols['email']);

    if (stripos((string) $novo_estado, 'concl') !== false) {
        $infoCols = [];
        foreach (['matricula', 'tipo_servico'] as $colName) {
            if (isset($cols[$colName])) {
                $infoCols[] = $colName;
            }
        }
        if ($infoCols !== []) {
            $infoSql = 'SELECT ' . implode(', ', $infoCols) . " FROM servico WHERE $idCol = ? LIMIT 1";
            $infoStmt = $con->prepare($infoSql);
            if ($infoStmt) {
                $infoStmt->bind_param('i', $servico_id);
                if ($infoStmt->execute()) {
                    $infoRes = $infoStmt->get_result();
                    $infoRow = $infoRes ? $infoRes->fetch_assoc() : null;
                    if ($infoRow) {
                        $matriculaResp = trim((string) ($infoRow['matricula'] ?? ''));
                        $tipoServicoResp = trim((string) ($infoRow['tipo_servico'] ?? ''));
                    }
                }
                $infoStmt->close();
            }
        }
    }

    $msgResp = 'Serviço concluído com sucesso!';
    if ($tipoServicoResp !== '' && $matriculaResp !== '') {
        $msgResp = 'Serviço «' . $tipoServicoResp . '» (' . $matriculaResp . ') concluído com sucesso!';
    } elseif ($matriculaResp !== '') {
        $msgResp = 'Serviço do veículo ' . $matriculaResp . ' concluído com sucesso!';
    } elseif ($tipoServicoResp !== '') {
        $msgResp = 'Serviço «' . $tipoServicoResp . '» concluído com sucesso!';
    }
    if ($enviarEmailCliente) {
        $msgResp .= ' O cliente será notificado.';
        $stComp = $con->prepare("SELECT * FROM servico WHERE $idCol = ? LIMIT 1");
        if ($stComp) {
            $stComp->bind_param('i', $servico_id);
            if ($stComp->execute()) {
                $rowComp = $stComp->get_result()->fetch_assoc();
                if ($rowComp && am_servico_precisa_pagar_trabalho_mecanico($rowComp)) {
                    $msgResp .= ' O cliente deve pagar €'
                        . number_format(am_servico_valor_trabalho_mecanico_a_pagar($rowComp), 2, ',', ' ')
                        . ' pelo trabalho realizado (total: €'
                        . number_format(am_servico_total_fatura_final($rowComp), 2, ',', ' ')
                        . ').';
                }
            }
            $stComp->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $msgResp,
        'matricula' => $matriculaResp,
        'tipo_servico' => $tipoServicoResp,
        'cliente_notificado' => $enviarEmailCliente,
    ], JSON_UNESCAPED_UNICODE);

    if ($enviarEmailCliente) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        @am_notificar_cliente_trabalho_concluido($con, (string) $servico_id, $idCol);
    }
} else {
    echo json_encode(['success' => false, 'error' => $upd->error]);
}
$upd->close();

