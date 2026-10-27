<?php
session_start();
require('ligar.php');
require_once __DIR__ . '/inc_veiculo_perfil.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servico_id = (int) ($_POST['servico_id'] ?? 0);
    $mecanico_id = (int) $_SESSION['mecanico_id'];
    $idCol = am_servico_id_col($con);
    $mecExpr = am_servico_mec_col_expr($con);

    if ($servico_id > 0 && $idCol !== '' && $mecExpr !== '') {
        $check_stmt = $con->prepare("SELECT $idCol AS id_ref FROM servico WHERE $idCol = ? AND $mecExpr = ? LIMIT 1");
        $check_stmt->bind_param('ii', $servico_id, $mecanico_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $stmt = $con->prepare("UPDATE servico SET estado_do_trabalho = 'Concluído', data_conclusao = NOW() WHERE $idCol = ?");
            $stmt->bind_param('i', $servico_id);

            if ($stmt->execute()) {
                $stSrv = $con->prepare("SELECT * FROM servico WHERE $idCol = ? LIMIT 1");
                $rowSrv = null;
                if ($stSrv) {
                    $stSrv->bind_param('i', $servico_id);
                    if ($stSrv->execute()) {
                        $resSrv = $stSrv->get_result();
                        $rowSrv = $resSrv ? $resSrv->fetch_assoc() : null;
                    }
                    $stSrv->close();
                }
                if (is_array($rowSrv) && am_fatura_intervencao_tem_dados($rowSrv)) {
                    $horas = am_servico_horas_para_fatura($rowSrv) ?? 0.0;
                    $valorHora = am_servico_valor_hora($rowSrv);
                    $pecas = am_pecas_substituidas_parse_struct($rowSrv['pecas_substituidas'] ?? null);
                    am_servico_guardar_fecho_fatura($con, $servico_id, $idCol, $horas, $valorHora, $pecas);
                }
                am_servico_sincronizar_divida_pagamento($con, $servico_id, $idCol);
                require_once __DIR__ . '/inc_notificar_cliente_email.php';
                am_notificar_cliente_trabalho_concluido($con, (string) $servico_id, $idCol);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Serviço não encontrado ou não pertence a si']);
        }
        $check_stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'ID de serviço inválido']);
    }
}
?>
