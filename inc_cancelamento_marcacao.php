<?php
/**
 * Cancelamento de marcações (sem taxa administrativa).
 */
declare(strict_types=1);

if (!defined('AM_HORAS_LIMITE_TAXA_CANCELAMENTO')) {
    define('AM_HORAS_LIMITE_TAXA_CANCELAMENTO', 24);
}
if (!defined('AM_TAXA_CANCELAMENTO_EUR')) {
    define('AM_TAXA_CANCELAMENTO_EUR', 0.0);
}

function am_ensure_cancelamento_marcacao_columns(mysqli $con): void
{
    $campos = [];
    $res = $con->query('SHOW COLUMNS FROM servico');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $campos[] = $row['Field'];
        }
    }
    if (!in_array('estado_marcacao', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN estado_marcacao VARCHAR(20) NULL DEFAULT 'ativa'");
    }
    if (!in_array('data_cancelamento', $campos, true)) {
        @$con->query('ALTER TABLE servico ADD COLUMN data_cancelamento DATETIME NULL');
    }
    if (!in_array('taxa_cancelamento', $campos, true)) {
        @$con->query('ALTER TABLE servico ADD COLUMN taxa_cancelamento DECIMAL(10,2) NULL');
    }
    if (!in_array('taxa_cancelamento_paga', $campos, true)) {
        @$con->query('ALTER TABLE servico ADD COLUMN taxa_cancelamento_paga TINYINT(1) NOT NULL DEFAULT 0');
    }
    if (!in_array('metodo_pagamento_taxa_cancel', $campos, true)) {
        @$con->query('ALTER TABLE servico ADD COLUMN metodo_pagamento_taxa_cancel VARCHAR(30) NULL');
    }
}

/** @param array<string, mixed> $servico */
function am_marcacao_datetime_agendada(array $servico): ?DateTimeImmutable
{
    $data = trim((string) ($servico['data_desejada'] ?? ''));
    if ($data === '') {
        return null;
    }
    $hora = trim((string) ($servico['hora_desejada'] ?? '09:00'));
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        $hora = '09:00';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $data . ' ' . $hora);
    if ($dt === false) {
        $ts = strtotime($data . ' ' . $hora);
        if ($ts === false) {
            return null;
        }
        return (new DateTimeImmutable())->setTimestamp($ts);
    }
    return $dt;
}

/** @param array<string, mixed> $servico */
function am_cancelamento_requer_taxa(array $servico, ?DateTimeImmutable $agora = null): bool
{
    return false;
}

function am_taxa_cancelamento_valor(): float
{
    return max(0.0, round((float) AM_TAXA_CANCELAMENTO_EUR, 2));
}

/** @param array<string, mixed> $servico */
function am_marcacao_aguarda_taxa_cancelamento(array $servico): bool
{
    return false;
}

/** @param array<string, mixed> $servico */
function am_marcacao_esta_cancelada(array $servico): bool
{
    $em = strtolower(trim((string) ($servico['estado_marcacao'] ?? '')));
    if ($em === 'cancelada') {
        return true;
    }
    $et = strtolower(trim((string) ($servico['estado_do_trabalho'] ?? '')));
    if (strpos($et, 'cancel') !== false) {
        return true;
    }
    if (!function_exists('am_servico_excesso_orcamento_recusado')) {
        require_once __DIR__ . '/inc_excesso_orcamento.php';
    }

    return am_servico_excesso_orcamento_recusado($servico);
}

/** @param array<string, mixed> $servico
 * @return array{ok:bool, error:string}
 */
function am_marcacao_pode_cancelar(array $servico): array
{
    if (am_marcacao_esta_cancelada($servico)) {
        return ['ok' => false, 'error' => 'Esta marcação já foi cancelada.'];
    }
    $et = strtolower(trim((string) ($servico['estado_do_trabalho'] ?? ($servico['estado'] ?? ''))));
    if (strpos($et, 'conclu') !== false || strpos($et, 'pronto') !== false) {
        return ['ok' => false, 'error' => 'Não é possível cancelar um serviço já concluído.'];
    }
    if (strpos($et, 'andamento') !== false || strpos($et, 'progress') !== false) {
        return ['ok' => false, 'error' => 'O serviço já está em andamento na oficina. Contacte-nos por telefone.'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * @param array<string, mixed> $servico
 * @return array{requer_taxa:bool, valor:float, horas_limite:int, texto:string}
 */
function am_cancelamento_resumo_taxa(array $servico, ?DateTimeImmutable $agora = null): array
{
    return [
        'requer_taxa' => false,
        'valor' => 0.0,
        'horas_limite' => (int) AM_HORAS_LIMITE_TAXA_CANCELAMENTO,
        'texto' => 'Cancelamento gratuito.',
    ];
}

function am_marcacao_carregar_cliente(mysqli $con, int $servicoId, string $email): ?array
{
    am_ensure_cancelamento_marcacao_columns($con);
    $st = $con->prepare('
        SELECT id, email, tipo_servico, matricula, data_desejada, hora_desejada,
               estado_marcacao, estado_do_trabalho, estado, estado_pagamento,
               taxa_cancelamento, taxa_cancelamento_paga
        FROM servico
        WHERE id = ? AND email = ?
        LIMIT 1
    ');
    if (!$st) {
        return null;
    }
    $st->bind_param('is', $servicoId, $email);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function am_marcacao_executar_cancelamento(mysqli $con, int $servicoId, string $email, float $taxaAplicada, bool $taxaPaga): bool
{
    am_ensure_cancelamento_marcacao_columns($con);
    $taxaPagaInt = $taxaPaga ? 1 : 0;
    $up = $con->prepare("
        UPDATE servico
        SET estado_marcacao = 'cancelada',
            data_cancelamento = NOW(),
            estado_do_trabalho = 'Cancelado',
            taxa_cancelamento = ?,
            taxa_cancelamento_paga = ?
        WHERE id = ? AND email = ?
          AND LOWER(COALESCE(estado_marcacao, 'ativa')) <> 'cancelada'
    ");
    if (!$up) {
        return false;
    }
    $up->bind_param('diis', $taxaAplicada, $taxaPagaInt, $servicoId, $email);
    $ok = $up->execute() && $up->affected_rows > 0;
    $up->close();
    return $ok;
}
