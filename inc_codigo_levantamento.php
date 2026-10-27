<?php

if (!function_exists('am_garantir_colunas_codigo_levantamento')) {
    function am_garantir_colunas_codigo_levantamento(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $cols = [
            'codigo_levantamento' => "ALTER TABLE servico ADD COLUMN codigo_levantamento VARCHAR(6) NULL",
            'codigo_levantamento_emitido_em' => "ALTER TABLE servico ADD COLUMN codigo_levantamento_emitido_em DATETIME NULL",
            'codigo_levantamento_usado_em' => "ALTER TABLE servico ADD COLUMN codigo_levantamento_usado_em DATETIME NULL",
        ];
        foreach ($cols as $col => $sql) {
            $r = @$con->query("SHOW COLUMNS FROM servico LIKE '" . $con->real_escape_string($col) . "'");
            if ($r && $r->num_rows === 0) {
                @$con->query($sql);
            }
        }
    }
}

if (!function_exists('am_codigo_levantamento_gerar')) {
    function am_codigo_levantamento_gerar(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('am_codigo_levantamento_emitir')) {
    function am_codigo_levantamento_emitir(mysqli $con, int $servicoId, string $email): ?string
    {
        am_garantir_colunas_codigo_levantamento($con);
        if ($servicoId <= 0 || trim($email) === '') {
            return null;
        }

        $codigo = am_codigo_levantamento_gerar();
        $up = $con->prepare("
            UPDATE servico
            SET codigo_levantamento = ?,
                codigo_levantamento_emitido_em = NOW(),
                codigo_levantamento_usado_em = NULL
            WHERE id = ? AND email = ?
        ");
        if (!$up) {
            return null;
        }
        $up->bind_param('sis', $codigo, $servicoId, $email);
        $ok = $up->execute() && $up->affected_rows > 0;
        $up->close();

        return $ok ? $codigo : null;
    }
}

if (!function_exists('am_codigo_levantamento_formatado')) {
    function am_codigo_levantamento_formatado(?string $codigo): string
    {
        $codigo = preg_replace('/\D+/', '', (string) $codigo);
        if (strlen($codigo) !== 6) {
            return '';
        }

        return substr($codigo, 0, 3) . ' ' . substr($codigo, 3, 3);
    }
}
