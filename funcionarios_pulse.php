<?php
/**
 * JSON leve para atualização em tempo quase real na área do mecânico.
 * Usa a mesma lógica de colunas que funcionarios.php.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

require __DIR__ . '/ligar.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';
$mecanico_id = (int) $_SESSION['mecanico_id'];

$colIdMecanico = false;
$colMecanicoId = false;
$colDataDesejada = false;
$colDataRegisto = false;
$colCreatedAt = false;
$colDataConclusao = false;
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_mecanico'");
if ($chk && $chk->num_rows > 0) {
    $colIdMecanico = true;
}
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'mecanico_id'");
if ($chk && $chk->num_rows > 0) {
    $colMecanicoId = true;
}
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_desejada'");
if ($chk && $chk->num_rows > 0) {
    $colDataDesejada = true;
}
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_registo'");
if ($chk && $chk->num_rows > 0) {
    $colDataRegisto = true;
}
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'created_at'");
if ($chk && $chk->num_rows > 0) {
    $colCreatedAt = true;
}
$chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'data_conclusao'");
if ($chk && $chk->num_rows > 0) {
    $colDataConclusao = true;
}

$servicoMecExpr = $colIdMecanico && $colMecanicoId
    ? 'COALESCE(s.id_mecanico, s.mecanico_id)'
    : ($colIdMecanico ? 's.id_mecanico' : ($colMecanicoId ? 's.mecanico_id' : 'NULL'));

$dataConclusaoParts = [];
if ($colDataConclusao) {
    $dataConclusaoParts[] = 's.data_conclusao';
}
if ($colDataDesejada) {
    $dataConclusaoParts[] = 's.data_desejada';
}
if ($colDataRegisto) {
    $dataConclusaoParts[] = 's.data_registo';
}
if ($colCreatedAt) {
    $dataConclusaoParts[] = 's.created_at';
}
$dataConclusaoParts[] = 'NOW()';
$dataConclusaoExpr = 'COALESCE(' . implode(', ', $dataConclusaoParts) . ')';

$idsCurso = [];
$q = @$con->query("
    SELECT s.id, s.estado_do_trabalho, s.matricula, s.fatura_linhas_json
    FROM servico s
    WHERE $servicoMecExpr = $mecanico_id
      AND " . am_sql_servico_ativo_mecanico($colDataConclusao) . "
    ORDER BY s.id ASC
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        if (am_servico_esta_concluido($r) || am_servico_tem_fecho_json_mecanico($r)) {
            continue;
        }
        $idsCurso[] = (int) $r['id'] . ':' . strtolower((string) ($r['estado_do_trabalho'] ?? ''));
    }
}

$idsConclTop = [];
$q2 = @$con->query("
    SELECT s.id
    FROM servico s
    WHERE $servicoMecExpr = $mecanico_id
      AND (
            LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%'
         OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%'
         OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%entreg%'
      )
    ORDER BY $dataConclusaoExpr DESC
    LIMIT 20
");
if ($q2) {
    while ($r = $q2->fetch_assoc()) {
        $idsConclTop[] = (int) $r['id'];
    }
}

$nCurso = count($idsCurso);
$nConclRecent = min(15, count($idsConclTop));
$pulse = md5(implode(',', $idsCurso) . '|' . implode(',', $idsConclTop));

echo json_encode([
    'ok' => true,
    'pulse' => $pulse,
    'n_curso' => $nCurso,
    'n_concl_recent' => $nConclRecent,
    'ts' => time(),
], JSON_UNESCAPED_UNICODE);
