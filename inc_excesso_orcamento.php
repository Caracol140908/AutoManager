<?php
/**
 * Total final muito acima do orçamento — o cliente deve confirmar se quer continuar.
 */
if (!defined('AM_EXCESSO_ORCAMENTO_PERCENTAGEM')) {
    define('AM_EXCESSO_ORCAMENTO_PERCENTAGEM', 15.0);
}
if (!defined('AM_EXCESSO_ORCAMENTO_MIN_EUR')) {
    define('AM_EXCESSO_ORCAMENTO_MIN_EUR', 25.0);
}

if (!function_exists('am_ensure_excesso_orcamento_cols')) {
    function am_ensure_excesso_orcamento_cols(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $campos = [];
        $res = @$con->query('SHOW COLUMNS FROM servico');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $campos[] = $row['Field'];
            }
        }
        if (!in_array('estado_excesso_orcamento_cliente', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN estado_excesso_orcamento_cliente VARCHAR(20) NULL AFTER estado_orcamento_cliente");
        }
        if (!in_array('data_excesso_orcamento_notificado', $campos, true)) {
            @$con->query('ALTER TABLE servico ADD COLUMN data_excesso_orcamento_notificado DATETIME NULL');
        }
        if (!in_array('data_excesso_orcamento_resposta', $campos, true)) {
            @$con->query('ALTER TABLE servico ADD COLUMN data_excesso_orcamento_resposta DATETIME NULL');
        }
    }
}

if (!function_exists('am_servico_excesso_orcamento_comparacao')) {
    /**
     * @return array<string, mixed>|null null se não há orçamento ou total
     */
    function am_servico_excesso_orcamento_comparacao(array $servico): ?array
    {
        if (!function_exists('am_servico_comparacao_orcamento_total')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }
        $total = am_servico_total_fatura_final($servico);
        if ($total <= 0.009) {
            return null;
        }
        $cmp = am_servico_comparacao_orcamento_total($servico, $total);
        if (empty($cmp['tem_orcamento'])) {
            return null;
        }

        return $cmp;
    }
}

if (!function_exists('am_servico_total_muito_acima_orcamento')) {
    function am_servico_total_muito_acima_orcamento(array $servico): bool
    {
        $cmp = am_servico_excesso_orcamento_comparacao($servico);
        if ($cmp === null || ($cmp['status'] ?? '') !== 'acima') {
            return false;
        }
        $diferenca = (float) ($cmp['diferenca'] ?? 0);
        $orcamento = (float) ($cmp['orcamento_com_iva'] ?? 0);
        if ($diferenca < AM_EXCESSO_ORCAMENTO_MIN_EUR) {
            return false;
        }
        if ($orcamento > 0 && ($diferenca / $orcamento) * 100 < AM_EXCESSO_ORCAMENTO_PERCENTAGEM) {
            return false;
        }

        return true;
    }
}

if (!function_exists('am_servico_excesso_orcamento_estado_norm')) {
    function am_servico_excesso_orcamento_estado_norm(?string $estado): string
    {
        return strtolower(trim((string) $estado));
    }
}

if (!function_exists('am_servico_excesso_orcamento_pendente')) {
    function am_servico_excesso_orcamento_pendente(array $servico): bool
    {
        return am_servico_excesso_orcamento_estado_norm($servico['estado_excesso_orcamento_cliente'] ?? null) === 'pendente';
    }
}

if (!function_exists('am_servico_excesso_orcamento_aceite')) {
    function am_servico_excesso_orcamento_aceite(array $servico): bool
    {
        $e = am_servico_excesso_orcamento_estado_norm($servico['estado_excesso_orcamento_cliente'] ?? null);

        return in_array($e, ['aceite', 'aceito'], true);
    }
}

if (!function_exists('am_servico_excesso_orcamento_recusado')) {
    function am_servico_excesso_orcamento_recusado(array $servico): bool
    {
        return am_servico_excesso_orcamento_estado_norm($servico['estado_excesso_orcamento_cliente'] ?? null) === 'recusado';
    }
}

if (!function_exists('am_servico_registar_excesso_orcamento_se_aplicavel')) {
    /**
     * Após fecho do mecânico: marca pendente e notifica se o total for muito acima do orçamento.
     */
    function am_servico_registar_excesso_orcamento_se_aplicavel(mysqli $con, int $servicoId, string $idCol = 'id'): bool
    {
        if ($servicoId <= 0 || ($idCol !== 'id' && $idCol !== 'id_servico')) {
            return false;
        }

        am_ensure_excesso_orcamento_cols($con);
        if (!function_exists('am_servico_esta_concluido')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }

        $st = $con->prepare("SELECT * FROM servico WHERE $idCol = ? LIMIT 1");
        if (!$st) {
            return false;
        }
        $st->bind_param('i', $servicoId);
        if (!$st->execute()) {
            $st->close();

            return false;
        }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!is_array($row) || !am_servico_esta_concluido($row)) {
            return false;
        }

        $estadoAtual = am_servico_excesso_orcamento_estado_norm($row['estado_excesso_orcamento_cliente'] ?? null);
        $muitoAcima = am_servico_total_muito_acima_orcamento($row);

        if (!$muitoAcima) {
            if ($estadoAtual === 'pendente') {
                $clr = $con->prepare("UPDATE servico SET estado_excesso_orcamento_cliente = NULL WHERE $idCol = ? AND LOWER(COALESCE(estado_excesso_orcamento_cliente, '')) = 'pendente'");
                if ($clr) {
                    $clr->bind_param('i', $servicoId);
                    $clr->execute();
                    $clr->close();
                }
            }

            return true;
        }

        if (in_array($estadoAtual, ['aceite', 'aceito', 'recusado'], true)) {
            return true;
        }

        $jaNotificado = !empty($row['data_excesso_orcamento_notificado']);
        $up = $con->prepare("
            UPDATE servico
            SET estado_excesso_orcamento_cliente = 'pendente',
                data_excesso_orcamento_notificado = COALESCE(data_excesso_orcamento_notificado, NOW())
            WHERE $idCol = ?
              AND LOWER(COALESCE(estado_excesso_orcamento_cliente, '')) NOT IN ('aceite', 'aceito', 'recusado')
        ");
        if (!$up) {
            return false;
        }
        $up->bind_param('i', $servicoId);
        $ok = $up->execute() && $up->affected_rows > 0;
        $up->close();

        if ($ok && !$jaNotificado) {
            require_once __DIR__ . '/inc_notificar_cliente_email.php';
            am_notificar_cliente_excesso_orcamento($con, $servicoId, $idCol);
        }

        return true;
    }
}

if (!function_exists('am_servico_cliente_responder_excesso_orcamento')) {
    /**
     * @param 'aceite'|'recusado' $resposta
     * @return array{ok:bool,error?:string}
     */
    function am_servico_cliente_responder_excesso_orcamento(
        mysqli $con,
        int $servicoId,
        int $idUtilizador,
        string $email,
        string $resposta
    ): array {
        $resposta = strtolower(trim($resposta));
        if (!in_array($resposta, ['aceite', 'recusado'], true)) {
            return ['ok' => false, 'error' => 'Resposta inválida.'];
        }

        am_ensure_excesso_orcamento_cols($con);
        if (!function_exists('am_cliente_carregar_servico_por_id')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }

        $email = am_cliente_email_canonico($con, $idUtilizador, trim($email));
        $servico = am_cliente_carregar_servico_por_id($con, $servicoId, $idUtilizador, $email);
        if (!$servico) {
            return ['ok' => false, 'error' => 'Serviço não encontrado.'];
        }
        if (!am_servico_excesso_orcamento_pendente($servico)) {
            if (am_servico_excesso_orcamento_aceite($servico)) {
                return ['ok' => false, 'error' => 'Já confirmou que deseja continuar com este valor.'];
            }
            if (am_servico_excesso_orcamento_recusado($servico)) {
                return ['ok' => false, 'error' => 'Já indicou que não aceita este valor.'];
            }

            return ['ok' => false, 'error' => 'Não há confirmação pendente para este serviço.'];
        }

        $up = $con->prepare("
            UPDATE servico
            SET estado_excesso_orcamento_cliente = ?,
                data_excesso_orcamento_resposta = NOW()
            WHERE id = ?
              AND LOWER(COALESCE(estado_excesso_orcamento_cliente, '')) = 'pendente'
        ");
        if (!$up) {
            return ['ok' => false, 'error' => 'Erro ao guardar a sua resposta.'];
        }
        $up->bind_param('si', $resposta, $servicoId);
        if (!$up->execute() || $up->affected_rows <= 0) {
            $up->close();

            return ['ok' => false, 'error' => 'Não foi possível registar a resposta. Tente novamente.'];
        }
        $up->close();

        if ($resposta === 'recusado') {
            am_servico_cancelar_por_valor_final_recusado($con, $servicoId);
        }

        return ['ok' => true];
    }
}

if (!function_exists('am_servico_cancelar_por_valor_final_recusado')) {
    /**
     * Cliente recusou o total acima do orçamento — cancela marcação e remove levantamento.
     */
    function am_servico_cancelar_por_valor_final_recusado(mysqli $con, int $servicoId, string $idCol = 'id'): bool
    {
        if ($servicoId <= 0 || ($idCol !== 'id' && $idCol !== 'id_servico')) {
            return false;
        }

        require_once __DIR__ . '/inc_cancelamento_marcacao.php';
        require_once __DIR__ . '/inc_codigo_levantamento.php';
        am_ensure_cancelamento_marcacao_columns($con);
        am_garantir_colunas_codigo_levantamento($con);

        $up = $con->prepare("
            UPDATE servico
            SET estado_marcacao = 'cancelada',
                data_cancelamento = COALESCE(data_cancelamento, NOW()),
                estado_do_trabalho = 'Cancelado',
                codigo_levantamento = NULL,
                codigo_levantamento_emitido_em = NULL,
                codigo_levantamento_usado_em = NULL
            WHERE $idCol = ?
              AND LOWER(COALESCE(estado_excesso_orcamento_cliente, '')) = 'recusado'
        ");
        if (!$up) {
            return false;
        }
        $up->bind_param('i', $servicoId);
        $ok = $up->execute();
        $up->close();

        return (bool) $ok;
    }
}

if (!function_exists('am_servico_sincronizar_cancelados_excesso_recusado')) {
    /** Corrige marcações antigas com excesso recusado mas ainda ativas na BD. */
    function am_servico_sincronizar_cancelados_excesso_recusado(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        require_once __DIR__ . '/inc_cancelamento_marcacao.php';
        require_once __DIR__ . '/inc_codigo_levantamento.php';
        am_ensure_cancelamento_marcacao_columns($con);
        am_ensure_excesso_orcamento_cols($con);
        am_garantir_colunas_codigo_levantamento($con);

        @$con->query("
            UPDATE servico
            SET estado_marcacao = 'cancelada',
                data_cancelamento = COALESCE(data_cancelamento, data_excesso_orcamento_resposta, NOW()),
                estado_do_trabalho = 'Cancelado',
                codigo_levantamento = NULL,
                codigo_levantamento_emitido_em = NULL,
                codigo_levantamento_usado_em = NULL
            WHERE LOWER(COALESCE(estado_excesso_orcamento_cliente, '')) = 'recusado'
              AND LOWER(COALESCE(estado_marcacao, 'ativa')) <> 'cancelada'
        ");
    }
}
