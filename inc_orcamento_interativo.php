<?php

declare(strict_types=1);

/**
 * Orçamento por linhas (peças / mão de obra / tempo) + decisão por item no cliente.
 * Fatura: cópia imutável em JSON no momento do pagamento (fatura_linhas_json).
 */
if (!function_exists('am_ensure_orcamento_interativo')) {
    function am_ensure_orcamento_interativo(mysqli $con): void
    {
        $con->query(
            'CREATE TABLE IF NOT EXISTS servico_orcamento_item (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                servico_id INT NOT NULL,
                orden SMALLINT NOT NULL DEFAULT 0,
                categoria VARCHAR(24) NOT NULL DEFAULT \'outro\',
                descricao VARCHAR(500) NOT NULL,
                quantidade DECIMAL(10,3) NOT NULL DEFAULT 1.000,
                preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                minutos_trabalho SMALLINT UNSIGNED NULL,
                grupo_alternativa VARCHAR(40) NULL,
                decisao_cliente VARCHAR(20) NOT NULL DEFAULT \'aceite\',
                PRIMARY KEY (id),
                KEY idx_servico_orc (servico_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $chk = @$con->query("SHOW COLUMNS FROM servico LIKE 'fatura_linhas_json'");
        if ($chk && $chk->num_rows === 0) {
            @$con->query('ALTER TABLE servico ADD COLUMN fatura_linhas_json LONGTEXT NULL');
        }
    }

    function am_servico_orcamento_itens_count(mysqli $con, int $servicoId): int
    {
        am_ensure_orcamento_interativo($con);
        $st = $con->prepare('SELECT COUNT(*) AS c FROM servico_orcamento_item WHERE servico_id = ?');
        if (!$st) {
            return 0;
        }
        $st->bind_param('i', $servicoId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();

        return (int) ($r['c'] ?? 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    function am_servico_orcamento_itens_list(mysqli $con, int $servicoId): array
    {
        am_ensure_orcamento_interativo($con);
        $st = $con->prepare(
            'SELECT id, servico_id, orden, categoria, descricao, quantidade, preco_unitario, minutos_trabalho, grupo_alternativa, decisao_cliente
             FROM servico_orcamento_item WHERE servico_id = ? ORDER BY orden ASC, id ASC'
        );
        if (!$st) {
            return [];
        }
        $st->bind_param('i', $servicoId);
        $st->execute();
        $res = $st->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $st->close();

        return $out;
    }

    /**
     * @param list<array{descricao:string,categoria:string,quantidade:float|int,preco_unitario:float,minutos:?int,grupo:?string}> $linhas
     */
    function am_servico_orcamento_itens_replace(mysqli $con, int $servicoId, array $linhas): bool
    {
        am_ensure_orcamento_interativo($con);
        $catsOk = ['peca', 'mao_obra', 'outro', 'taxa'];
        $con->begin_transaction();
        try {
            $del = $con->prepare('DELETE FROM servico_orcamento_item WHERE servico_id = ?');
            if (!$del) {
                throw new RuntimeException('del');
            }
            $del->bind_param('i', $servicoId);
            $del->execute();
            $del->close();

            $ins = $con->prepare(
                'INSERT INTO servico_orcamento_item
                (servico_id, orden, categoria, descricao, quantidade, preco_unitario, minutos_trabalho, grupo_alternativa, decisao_cliente)
                VALUES (?,?,?,?,?,?,?,?,\'aceite\')'
            );
            if (!$ins) {
                throw new RuntimeException('ins');
            }
            $ord = 0;
            foreach ($linhas as $ln) {
                $ord++;
                $desc = substr(trim((string) ($ln['descricao'] ?? '')), 0, 500);
                if ($desc === '') {
                    continue;
                }
                $cat = strtolower(trim((string) ($ln['categoria'] ?? 'outro')));
                if (!in_array($cat, $catsOk, true)) {
                    $cat = 'outro';
                }
                $qty = (float) ($ln['quantidade'] ?? 1);
                if ($qty <= 0) {
                    $qty = 1.0;
                }
                $pu = round((float) ($ln['preco_unitario'] ?? 0), 2);
                if ($pu < 0) {
                    $pu = 0.0;
                }
                $min = isset($ln['minutos']) && $ln['minutos'] !== null && $ln['minutos'] !== ''
                    ? max(0, min(32000, (int) $ln['minutos']))
                    : 0;
                $gr = isset($ln['grupo']) ? trim((string) $ln['grupo']) : '';
                $gr = $gr !== '' ? substr($gr, 0, 40) : null;

                $minBind = $min;
                $grBind = $gr;
                $ins->bind_param(
                    'iissddis',
                    $servicoId,
                    $ord,
                    $cat,
                    $desc,
                    $qty,
                    $pu,
                    $minBind,
                    $grBind
                );
                $ins->execute();
            }
            $ins->close();
            $con->commit();

            return true;
        } catch (Throwable $e) {
            $con->rollback();

            return false;
        }
    }

    function am_servico_orcamento_total_aceites_sem_iva(mysqli $con, int $servicoId): float
    {
        $st = $con->prepare(
            "SELECT COALESCE(SUM(quantidade * preco_unitario), 0) AS t
             FROM servico_orcamento_item
             WHERE servico_id = ? AND LOWER(decisao_cliente) = 'aceite'"
        );
        if (!$st) {
            return 0.0;
        }
        $st->bind_param('i', $servicoId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();

        return round((float) ($r['t'] ?? 0), 2);
    }

    /**
     * @param array<int,string> $decisaoPorId id => aceite|recusado
     * @param array<string,int> $radioGrupo grupo_alternativa => id escolhido (só para grupos com 2+ linhas)
     * @return array{ok:bool,error?:string,total?:float}
     */
    function am_servico_orcamento_cliente_aplicar_decisoes(
        mysqli $con,
        int $servicoId,
        string $emailCliente,
        array $decisaoPorId,
        array $radioGrupo,
        int $idUtilizador = 0
    ): array {
        am_ensure_orcamento_interativo($con);
        if (!function_exists('am_cliente_carregar_servico_por_id')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }
        $emailCliente = function_exists('am_cliente_email_canonico')
            ? am_cliente_email_canonico($con, $idUtilizador, trim($emailCliente))
            : trim($emailCliente);
        $servicoOwn = am_cliente_carregar_servico_por_id($con, $servicoId, $idUtilizador, $emailCliente);
        if (!$servicoOwn) {
            return ['ok' => false, 'error' => 'Serviço não encontrado.'];
        }

        $itens = am_servico_orcamento_itens_list($con, $servicoId);
        if ($itens === []) {
            return ['ok' => true, 'total' => 0.0];
        }

        $byId = [];
        foreach ($itens as $it) {
            $byId[(int) $it['id']] = $it;
        }

        $grupos = [];
        foreach ($itens as $it) {
            $g = trim((string) ($it['grupo_alternativa'] ?? ''));
            if ($g !== '') {
                $grupos[$g][] = (int) $it['id'];
            }
        }

        $final = [];
        foreach ($itens as $it) {
            $id = (int) $it['id'];
            $g = trim((string) ($it['grupo_alternativa'] ?? ''));
            if ($g !== '' && isset($grupos[$g]) && count($grupos[$g]) >= 2) {
                $chosen = isset($radioGrupo[$g]) ? (int) $radioGrupo[$g] : 0;
                if (!in_array($chosen, $grupos[$g], true)) {
                    return ['ok' => false, 'error' => 'Em cada grupo de alternativas tens de escolher uma opção.'];
                }
                $final[$id] = ($chosen === $id) ? 'aceite' : 'recusado';
            } else {
                $d = strtolower(trim((string) ($decisaoPorId[$id] ?? 'recusado')));
                $final[$id] = ($d === 'aceite') ? 'aceite' : 'recusado';
            }
        }

        $aceites = 0;
        foreach ($final as $id => $dec) {
            if ($dec === 'aceite') {
                $aceites++;
            }
        }
        if ($aceites === 0) {
            return ['ok' => false, 'error' => 'Tens de manter pelo menos uma linha aceite no orçamento.'];
        }

        $up = $con->prepare('UPDATE servico_orcamento_item SET decisao_cliente = ? WHERE id = ? AND servico_id = ?');
        if (!$up) {
            return ['ok' => false, 'error' => 'Erro ao guardar decisões.'];
        }
        foreach ($final as $id => $dec) {
            $up->bind_param('sii', $dec, $id, $servicoId);
            $up->execute();
        }
        $up->close();

        $total = am_servico_orcamento_total_aceites_sem_iva($con, $servicoId);

        $upS = $con->prepare('UPDATE servico SET orcamento_aprovado = ? WHERE id = ?');
        if ($upS) {
            $upS->bind_param('di', $total, $servicoId);
            $upS->execute();
            $upS->close();
        }

        return ['ok' => true, 'total' => $total];
    }

    /**
     * JSON para coluna fatura_linhas_json (linhas aceites no momento do pagamento).
     */
    function am_servico_orcamento_fatura_snapshot_json(mysqli $con, int $servicoId): string
    {
        $itens = am_servico_orcamento_itens_list($con, $servicoId);
        $linhas = [];
        foreach ($itens as $it) {
            if (strtolower((string) $it['decisao_cliente']) !== 'aceite') {
                continue;
            }
            $qty = (float) $it['quantidade'];
            $pu = (float) $it['preco_unitario'];
            $linhas[] = [
                'descricao' => (string) $it['descricao'],
                'categoria' => (string) $it['categoria'],
                'quantidade' => $qty,
                'preco_unitario' => $pu,
                'total_sem_iva' => round($qty * $pu, 2),
                'minutos_trabalho' => ($it['minutos_trabalho'] !== null && (int) $it['minutos_trabalho'] > 0)
                    ? (int) $it['minutos_trabalho']
                    : null,
            ];
        }

        return json_encode(['versao' => 1, 'linhas' => $linhas], JSON_UNESCAPED_UNICODE);
    }
}
