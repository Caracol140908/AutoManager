<?php

declare(strict_types=1);

/**
 * Eliminação de veículos do cliente (com taxa se houver trabalhos/marcações ativas).
 */

if (!defined('AM_TAXA_ELIMINAR_CARRO_EUR')) {
    define('AM_TAXA_ELIMINAR_CARRO_EUR', 0.0);
}

require_once __DIR__ . '/inc_veiculo_perfil.php';
require_once __DIR__ . '/inc_cancelamento_marcacao.php';

if (!function_exists('am_taxa_eliminar_carro_valor')) {
    function am_taxa_eliminar_carro_valor(): float
    {
        return max(0.0, round((float) AM_TAXA_ELIMINAR_CARRO_EUR, 2));
    }
}

if (!function_exists('am_carro_obter_registo_cliente')) {
    /** @return array<string, mixed>|null */
    function am_carro_obter_registo_cliente(mysqli $con, int $id_utilizador, int $id_carro): ?array
    {
        if ($id_utilizador <= 0 || $id_carro <= 0) {
            return null;
        }
        $st = $con->prepare('SELECT id_carro, marca, modelo, ano, matricula, is_principal FROM carro WHERE id_carro = ? AND id_utilizador = ? LIMIT 1');
        if (!$st) {
            return null;
        }
        $st->bind_param('ii', $id_carro, $id_utilizador);
        $st->execute();
        $r = $st->get_result();
        $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
        $st->close();

        return $row ?: null;
    }
}

if (!function_exists('am_carro_listar_marcacoes_ativas')) {
    /**
     * Marcações em curso (não canceladas nem concluídas) para a matrícula.
     *
     * @return list<array<string, mixed>>
     */
    function am_carro_listar_marcacoes_ativas(mysqli $con, string $email, string $matricula): array
    {
        $mat = am_veiculo_perfil_normalizar_matricula($matricula);
        $email = strtolower(trim($email));
        if ($mat === '' || $email === '') {
            return [];
        }

        am_ensure_cancelamento_marcacao_columns($con);

        $st = $con->prepare("
            SELECT id, tipo_servico, matricula, data_desejada, hora_desejada,
                   estado_marcacao, estado_do_trabalho, estado
            FROM servico s
            WHERE LOWER(TRIM(s.email)) = ?
              AND REPLACE(UPPER(TRIM(s.matricula)), '-', '') = REPLACE(?, '-', '')
              AND LOWER(COALESCE(s.estado_marcacao, 'ativa')) NOT IN ('cancelada', 'cancelado')
              AND LOWER(COALESCE(s.estado_do_trabalho, '')) NOT IN ('concluído', 'concluido')
              AND LOWER(COALESCE(s.estado_do_trabalho, '')) NOT LIKE '%cancel%'
        ");
        if (!$st) {
            return [];
        }
        $st->bind_param('ss', $email, $mat);
        $st->execute();
        $r = $st->get_result();
        $rows = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $st->close();

        return $rows;
    }
}

if (!function_exists('am_carro_contar_marcacoes_ativas')) {
    function am_carro_contar_marcacoes_ativas(mysqli $con, string $email, string $matricula): int
    {
        return count(am_carro_listar_marcacoes_ativas($con, $email, $matricula));
    }
}

if (!function_exists('am_carro_resumo_eliminacao')) {
    /**
     * @return array{ok:bool, error:string, requer_taxa:bool, valor:float, trabalhos_ativos:int, matricula:string, label:string}
     */
    function am_carro_resumo_eliminacao(mysqli $con, int $id_utilizador, int $id_carro, string $email): array
    {
        $base = [
            'ok' => false,
            'error' => '',
            'requer_taxa' => false,
            'valor' => 0.0,
            'trabalhos_ativos' => 0,
            'matricula' => '',
            'label' => '',
        ];

        $carro = am_carro_obter_registo_cliente($con, $id_utilizador, $id_carro);
        if (!$carro) {
            $base['error'] = 'Veículo não encontrado ou não pertence à sua conta.';

            return $base;
        }

        $matricula = (string) ($carro['matricula'] ?? '');
        $label = trim((string) ($carro['marca'] ?? '') . ' ' . (string) ($carro['modelo'] ?? ''));
        $ativos = am_carro_contar_marcacoes_ativas($con, $email, $matricula);

        $base['ok'] = true;
        $base['matricula'] = $matricula;
        $base['label'] = $label;
        $base['trabalhos_ativos'] = $ativos;
        $base['requer_taxa'] = false;
        $base['valor'] = 0.0;

        return $base;
    }
}

if (!function_exists('am_carro_cancelar_marcacoes_ativas')) {
    function am_carro_cancelar_marcacoes_ativas(mysqli $con, string $email, string $matricula, float $taxaTotal, bool $taxaPaga): bool
    {
        $lista = am_carro_listar_marcacoes_ativas($con, $email, $matricula);
        if ($lista === []) {
            return true;
        }

        $n = count($lista);
        $taxaPorMarcacao = ($taxaPaga && $taxaTotal > 0 && $n > 0) ? round($taxaTotal / $n, 2) : 0.0;

        foreach ($lista as $srv) {
            $sid = (int) ($srv['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (!am_marcacao_executar_cancelamento($con, $sid, $email, $taxaPorMarcacao, $taxaPaga)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('am_carro_atualizar_campo_utilizador')) {
    function am_carro_atualizar_campo_utilizador(mysqli $con, int $id_utilizador): void
    {
        if ($id_utilizador <= 0) {
            return;
        }

        $res = @$con->query("SHOW COLUMNS FROM utilizador LIKE 'carro'");
        if (!$res || $res->num_rows === 0) {
            return;
        }

        $st = $con->prepare('
            SELECT marca, modelo, matricula
            FROM carro
            WHERE id_utilizador = ?
            ORDER BY is_principal DESC, created_at DESC, id_carro DESC
            LIMIT 1
        ');
        if (!$st) {
            return;
        }
        $st->bind_param('i', $id_utilizador);
        $st->execute();
        $r = $st->get_result();
        $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
        $st->close();

        $texto = '';
        if ($row) {
            $texto = trim((string) ($row['marca'] ?? '') . ' ' . (string) ($row['modelo'] ?? '') . ' (' . (string) ($row['matricula'] ?? '') . ')');
        }

        $up = $con->prepare('UPDATE utilizador SET carro = ? WHERE id_utilizador = ?');
        if ($up) {
            $up->bind_param('si', $texto, $id_utilizador);
            $up->execute();
            $up->close();
        }
    }
}

if (!function_exists('am_carro_executar_eliminacao')) {
    /**
     * Cancela marcações ativas (se existirem), remove perfil e apaga o registo em `carro`.
     *
     * @return array{ok: bool, error: string}
     */
    function am_carro_executar_eliminacao(
        mysqli $con,
        int $id_utilizador,
        int $id_carro,
        string $email,
        float $taxaPaga = 0.0,
        bool $taxaFoiPaga = false
    ): array {
        $resumo = am_carro_resumo_eliminacao($con, $id_utilizador, $id_carro, $email);
        if (!$resumo['ok']) {
            return ['ok' => false, 'error' => $resumo['error']];
        }

        $matricula = (string) $resumo['matricula'];
        $taxa = $taxaFoiPaga ? max(0.0, round($taxaPaga, 2)) : 0.0;

        if ($resumo['trabalhos_ativos'] > 0) {
            if (!am_carro_cancelar_marcacoes_ativas($con, $email, $matricula, $taxa, $taxaFoiPaga)) {
                return ['ok' => false, 'error' => 'Não foi possível cancelar os trabalhos ativos deste veículo.'];
            }
        }

        am_ensure_veiculo_perfil_tables($con);

        $fotoRel = '';
        $pf = $con->prepare('SELECT foto_relativa FROM carro_perfil WHERE id_carro = ? LIMIT 1');
        if ($pf) {
            $pf->bind_param('i', $id_carro);
            $pf->execute();
            $pr = $pf->get_result();
            if ($pr && ($prow = $pr->fetch_assoc()) && !empty($prow['foto_relativa'])) {
                $fotoRel = (string) $prow['foto_relativa'];
            }
            $pf->close();
        }

        @$con->query('DELETE FROM carro_perfil_pedido WHERE id_carro = ' . (int) $id_carro);
        @$con->query('DELETE FROM carro_perfil WHERE id_carro = ' . (int) $id_carro);

        $del = $con->prepare('DELETE FROM carro WHERE id_carro = ? AND id_utilizador = ?');
        if (!$del) {
            return ['ok' => false, 'error' => 'Erro ao preparar eliminação do veículo.'];
        }
        $del->bind_param('ii', $id_carro, $id_utilizador);
        if (!$del->execute() || $del->affected_rows < 1) {
            $err = $del->error;
            $del->close();

            return ['ok' => false, 'error' => 'Não foi possível eliminar o veículo na base de dados.' . ($err ? ' (' . $err . ')' : '')];
        }
        $del->close();

        if ($fotoRel !== '' && is_file(__DIR__ . '/' . $fotoRel)) {
            @unlink(__DIR__ . '/' . $fotoRel);
        }

        if (function_exists('normalizarCarroPrincipalUsuario')) {
            normalizarCarroPrincipalUsuario($con, $id_utilizador);
        } else {
            @$con->query('UPDATE carro SET is_principal = 0 WHERE id_utilizador = ' . (int) $id_utilizador);
            $qPr = $con->prepare('SELECT id_carro FROM carro WHERE id_utilizador = ? ORDER BY created_at DESC, id_carro DESC LIMIT 1');
            if ($qPr) {
                $qPr->bind_param('i', $id_utilizador);
                $qPr->execute();
                $rPr = $qPr->get_result();
                if ($rPr && ($rowPr = $rPr->fetch_assoc())) {
                    $idPr = (int) ($rowPr['id_carro'] ?? 0);
                    if ($idPr > 0) {
                        @$con->query('UPDATE carro SET is_principal = 1 WHERE id_carro = ' . $idPr);
                    }
                }
                $qPr->close();
            }
        }
        am_carro_atualizar_campo_utilizador($con, $id_utilizador);

        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('am_eliminar_carro_cliente')) {
    /** @return array{ok: bool, error: string, requer_taxa?: bool} */
    function am_eliminar_carro_cliente(mysqli $con, int $id_utilizador, int $id_carro, string $email_utilizador): array
    {
        $resumo = am_carro_resumo_eliminacao($con, $id_utilizador, $id_carro, $email_utilizador);
        if (!$resumo['ok']) {
            return ['ok' => false, 'error' => $resumo['error']];
        }

        return am_carro_executar_eliminacao($con, $id_utilizador, $id_carro, $email_utilizador, 0.0, false);
    }
}
