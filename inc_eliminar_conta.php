<?php

declare(strict_types=1);

/**
 * Eliminação total da conta de cliente (serviços, veículos, chat, etc.).
 */

if (!function_exists('am_tabela_existe')) {
    function am_tabela_existe(mysqli $con, string $tabela): bool
    {
        $tabela = trim($tabela);
        if ($tabela === '') {
            return false;
        }
        $esc = $con->real_escape_string($tabela);
        $res = @$con->query("SHOW TABLES LIKE '$esc'");

        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('am_coluna_existe')) {
    function am_coluna_existe(mysqli $con, string $tabela, string $coluna): bool
    {
        $tabela = trim($tabela);
        $coluna = trim($coluna);
        if ($tabela === '' || $coluna === '') {
            return false;
        }
        $escT = $con->real_escape_string($tabela);
        $escC = $con->real_escape_string($coluna);
        $res = @$con->query("SHOW COLUMNS FROM `$escT` LIKE '$escC'");

        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('am_utilizador_dados_eliminacao')) {
    /** @return array{email: string, email_norm: string, nif: string, telefone: string, nome: string} */
    function am_utilizador_dados_eliminacao(mysqli $con, int $id_utilizador, string $email_fallback = ''): array
    {
        $out = [
            'email' => trim($email_fallback),
            'email_norm' => strtolower(trim($email_fallback)),
            'nif' => '',
            'telefone' => '',
            'nome' => '',
        ];

        if ($id_utilizador <= 0) {
            return $out;
        }

        $cols = ['email', 'nome_utilizador'];
        if (am_coluna_existe($con, 'utilizador', 'nif')) {
            $cols[] = 'nif';
        }
        if (am_coluna_existe($con, 'utilizador', 'telefone')) {
            $cols[] = 'telefone';
        }

        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM utilizador WHERE id_utilizador = ? LIMIT 1';
        $st = $con->prepare($sql);
        if (!$st) {
            return $out;
        }
        $st->bind_param('i', $id_utilizador);
        if ($st->execute()) {
            $row = $st->get_result()->fetch_assoc();
            if (is_array($row)) {
                $emailDb = trim((string) ($row['email'] ?? ''));
                if ($emailDb !== '') {
                    $out['email'] = $emailDb;
                    $out['email_norm'] = strtolower($emailDb);
                }
                $out['nome'] = trim((string) ($row['nome_utilizador'] ?? ''));
                $out['nif'] = preg_replace('/\D+/', '', (string) ($row['nif'] ?? ''));
                $out['telefone'] = preg_replace('/\D+/', '', (string) ($row['telefone'] ?? ''));
            }
        }
        $st->close();

        return $out;
    }
}

if (!function_exists('am_utilizador_ids_carros')) {
    /** @return list<int> */
    function am_utilizador_ids_carros(mysqli $con, int $id_utilizador): array
    {
        $ids = [];
        if ($id_utilizador <= 0 || !am_tabela_existe($con, 'carro')) {
            return $ids;
        }
        $st = $con->prepare('SELECT id_carro FROM carro WHERE id_utilizador = ?');
        if (!$st) {
            return $ids;
        }
        $st->bind_param('i', $id_utilizador);
        if ($st->execute()) {
            $res = $st->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $ids[] = (int) ($row['id_carro'] ?? 0);
            }
        }
        $st->close();

        return array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
    }
}

if (!function_exists('am_utilizador_matriculas_carros')) {
    /** @return list<string> */
    function am_utilizador_matriculas_carros(mysqli $con, int $id_utilizador): array
    {
        $mats = [];
        if ($id_utilizador <= 0 || !am_tabela_existe($con, 'carro')) {
            return $mats;
        }
        $st = $con->prepare('SELECT matricula FROM carro WHERE id_utilizador = ?');
        if (!$st) {
            return $mats;
        }
        $st->bind_param('i', $id_utilizador);
        if ($st->execute()) {
            $res = $st->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $mat = strtoupper(str_replace(['-', ' '], '', trim((string) ($row['matricula'] ?? ''))));
                if ($mat !== '') {
                    $mats[] = $mat;
                }
            }
        }
        $st->close();

        return array_values(array_unique($mats));
    }
}

if (!function_exists('am_utilizador_ids_servicos')) {
    /** @param list<int> $carIds @param list<string> $matriculasNorm */
    function am_utilizador_ids_servicos(
        mysqli $con,
        int $id_utilizador,
        array $dados,
        array $carIds = [],
        array $matriculasNorm = []
    ): array {
        if (!am_tabela_existe($con, 'servico')) {
            return [];
        }

        $ids = [];
        $email = (string) ($dados['email'] ?? '');
        $emailNorm = (string) ($dados['email_norm'] ?? '');
        $nif = (string) ($dados['nif'] ?? '');
        $telefone = (string) ($dados['telefone'] ?? '');

        $queries = [];

        if ($id_utilizador > 0 && am_coluna_existe($con, 'servico', 'id_utilizador')) {
            $queries[] = ['sql' => 'SELECT id FROM servico WHERE id_utilizador = ?', 'types' => 'i', 'params' => [$id_utilizador]];
        }
        if ($email !== '') {
            $queries[] = ['sql' => 'SELECT id FROM servico WHERE email = ?', 'types' => 's', 'params' => [$email]];
        }
        if ($emailNorm !== '') {
            $queries[] = ['sql' => "SELECT id FROM servico WHERE LOWER(TRIM(COALESCE(email, ''))) = ?", 'types' => 's', 'params' => [$emailNorm]];
        }
        if ($nif !== '' && am_coluna_existe($con, 'servico', 'nif')) {
            $queries[] = ['sql' => "SELECT id FROM servico WHERE REPLACE(REPLACE(COALESCE(nif, ''), ' ', ''), '-', '') = ?", 'types' => 's', 'params' => [$nif]];
        }
        if ($telefone !== '' && am_coluna_existe($con, 'servico', 'telefone')) {
            $queries[] = ['sql' => "SELECT id FROM servico WHERE REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), ' ', ''), '-', ''), '+', '') LIKE ?", 'types' => 's', 'params' => ['%' . $telefone]];
        }

        foreach ($carIds as $carId) {
            if ($carId > 0 && am_coluna_existe($con, 'servico', 'id_carro')) {
                $queries[] = ['sql' => 'SELECT id FROM servico WHERE id_carro = ?', 'types' => 'i', 'params' => [$carId]];
            }
        }

        if ($matriculasNorm !== [] && am_coluna_existe($con, 'servico', 'matricula')) {
            foreach ($matriculasNorm as $mat) {
                $queries[] = [
                    'sql' => "SELECT id FROM servico WHERE REPLACE(REPLACE(UPPER(TRIM(COALESCE(matricula, ''))), '-', ''), ' ', '') = ?",
                    'types' => 's',
                    'params' => [$mat],
                ];
            }
        }

        foreach ($queries as $q) {
            $st = $con->prepare($q['sql']);
            if (!$st) {
                continue;
            }
            $types = $q['types'];
            $params = $q['params'];
            $bind = [$types];
            foreach ($params as $i => $valor) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$st, 'bind_param'], $bind);
            if ($st->execute()) {
                $res = $st->get_result();
                while ($res && ($row = $res->fetch_assoc())) {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            }
            $st->close();
        }

        return array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
    }
}

if (!function_exists('am_utilizador_apagar_ficheiros_carros')) {
    /** @param list<int> $carIds */
    function am_utilizador_apagar_ficheiros_carros(mysqli $con, array $carIds, int $id_utilizador): void
    {
        if (am_tabela_existe($con, 'carro_perfil')) {
            foreach ($carIds as $idCarro) {
                $st = $con->prepare('SELECT foto_relativa FROM carro_perfil WHERE id_carro = ? LIMIT 1');
                if (!$st) {
                    continue;
                }
                $st->bind_param('i', $idCarro);
                if ($st->execute()) {
                    $row = $st->get_result()->fetch_assoc();
                    $foto = trim((string) ($row['foto_relativa'] ?? ''));
                    if ($foto !== '' && is_file(__DIR__ . '/' . $foto)) {
                        @unlink(__DIR__ . '/' . $foto);
                    }
                }
                $st->close();
            }
        }

        if ($id_utilizador > 0) {
            $dirs = [__DIR__ . '/uploads', __DIR__ . '/img'];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                $pattern = $dir . '/v_' . $id_utilizador . '_*';
                foreach (glob($pattern) ?: [] as $ficheiro) {
                    if (is_file($ficheiro)) {
                        @unlink($ficheiro);
                    }
                }
            }
        }
    }
}

if (!function_exists('am_utilizador_apagar_filhos_servicos')) {
    /** @param list<int> $servicoIds */
    function am_utilizador_apagar_filhos_servicos(mysqli $con, array $servicoIds): void
    {
        if ($servicoIds === []) {
            return;
        }

        $tabelasConhecidas = [
            ['tabela' => 'servico_orcamento_item', 'coluna' => 'servico_id'],
            ['tabela' => 'carro_perfil_pedido', 'coluna' => 'id_servico'],
        ];

        foreach ($tabelasConhecidas as $meta) {
            if (!am_tabela_existe($con, $meta['tabela']) || !am_coluna_existe($con, $meta['tabela'], $meta['coluna'])) {
                continue;
            }
            foreach ($servicoIds as $sid) {
                $sql = 'DELETE FROM `' . $meta['tabela'] . '` WHERE `' . $meta['coluna'] . '` = ?';
                $st = $con->prepare($sql);
                if ($st) {
                    $st->bind_param('i', $sid);
                    $st->execute();
                    $st->close();
                }
            }
        }

        $res = @$con->query("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME IN ('servico_id', 'id_servico')
              AND TABLE_NAME NOT IN ('servico', 'servicos')
        ");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $tabela = (string) ($row['TABLE_NAME'] ?? '');
                $coluna = (string) ($row['COLUMN_NAME'] ?? '');
                if ($tabela === '' || $coluna === '') {
                    continue;
                }
                foreach ($servicoIds as $sid) {
                    @$con->query('DELETE FROM `' . str_replace('`', '``', $tabela) . '` WHERE `' . str_replace('`', '``', $coluna) . '` = ' . (int) $sid);
                }
            }
            $res->free();
        }
    }
}

if (!function_exists('am_utilizador_apagar_servicos')) {
  /** @param list<int> $servicoIds @param list<int> $carIds @param list<string> $matriculasNorm */
    function am_utilizador_apagar_servicos(
        mysqli $con,
        int $id_utilizador,
        array $dados,
        array $servicoIds,
        array $carIds = [],
        array $matriculasNorm = []
    ): void {
        if (!am_tabela_existe($con, 'servico')) {
            return;
        }

        am_utilizador_apagar_filhos_servicos($con, $servicoIds);

        if ($servicoIds !== []) {
            $lista = implode(',', array_map('intval', $servicoIds));
            @$con->query("DELETE FROM servico WHERE id IN ($lista)");
        }

        $email = (string) ($dados['email'] ?? '');
        $emailNorm = (string) ($dados['email_norm'] ?? '');
        $nif = (string) ($dados['nif'] ?? '');
        $telefone = (string) ($dados['telefone'] ?? '');

        if ($id_utilizador > 0 && am_coluna_existe($con, 'servico', 'id_utilizador')) {
            $st = $con->prepare('DELETE FROM servico WHERE id_utilizador = ?');
            if ($st) {
                $st->bind_param('i', $id_utilizador);
                $st->execute();
                $st->close();
            }
        }
        if ($email !== '') {
            $st = $con->prepare('DELETE FROM servico WHERE email = ?');
            if ($st) {
                $st->bind_param('s', $email);
                $st->execute();
                $st->close();
            }
        }
        if ($emailNorm !== '') {
            $st = $con->prepare("DELETE FROM servico WHERE LOWER(TRIM(COALESCE(email, ''))) = ?");
            if ($st) {
                $st->bind_param('s', $emailNorm);
                $st->execute();
                $st->close();
            }
        }
        if ($nif !== '' && am_coluna_existe($con, 'servico', 'nif')) {
            $st = $con->prepare("DELETE FROM servico WHERE REPLACE(REPLACE(COALESCE(nif, ''), ' ', ''), '-', '') = ?");
            if ($st) {
                $st->bind_param('s', $nif);
                $st->execute();
                $st->close();
            }
        }
        if ($telefone !== '' && am_coluna_existe($con, 'servico', 'telefone')) {
            $likeTel = '%' . $telefone;
            $st = $con->prepare("DELETE FROM servico WHERE REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), ' ', ''), '-', ''), '+', '') LIKE ?");
            if ($st) {
                $st->bind_param('s', $likeTel);
                $st->execute();
                $st->close();
            }
        }
        if (am_coluna_existe($con, 'servico', 'id_carro')) {
            foreach ($carIds as $carId) {
                if ($carId <= 0) {
                    continue;
                }
                $st = $con->prepare('DELETE FROM servico WHERE id_carro = ?');
                if ($st) {
                    $st->bind_param('i', $carId);
                    $st->execute();
                    $st->close();
                }
            }
        }
        if ($matriculasNorm !== [] && am_coluna_existe($con, 'servico', 'matricula')) {
            foreach ($matriculasNorm as $mat) {
                $st = $con->prepare("DELETE FROM servico WHERE REPLACE(REPLACE(UPPER(TRIM(COALESCE(matricula, ''))), '-', ''), ' ', '') = ?");
                if ($st) {
                    $st->bind_param('s', $mat);
                    $st->execute();
                    $st->close();
                }
            }
        }
    }
}

if (!function_exists('am_utilizador_apagar_por_email')) {
    function am_utilizador_apagar_por_email(mysqli $con, string $email, string $emailNorm): void
    {
        if ($emailNorm === '' && $email === '') {
            return;
        }

        $tabelasEmail = [
            'passaporte_eventos',
            'passaporte_links_partilha',
        ];

        foreach ($tabelasEmail as $tabela) {
            if (!am_tabela_existe($con, $tabela) || !am_coluna_existe($con, $tabela, 'email')) {
                continue;
            }
            if ($email !== '') {
                $st = $con->prepare("DELETE FROM `$tabela` WHERE email = ?");
                if ($st) {
                    $st->bind_param('s', $email);
                    $st->execute();
                    $st->close();
                }
            }
            if ($emailNorm !== '' && $emailNorm !== strtolower($email)) {
                $st = $con->prepare("DELETE FROM `$tabela` WHERE LOWER(TRIM(COALESCE(email, ''))) = ?");
                if ($st) {
                    $st->bind_param('s', $emailNorm);
                    $st->execute();
                    $st->close();
                }
            }
        }
    }
}

if (!function_exists('am_utilizador_apagar_legado')) {
    function am_utilizador_apagar_legado(mysqli $con, string $email, string $emailNorm): void
    {
        if (!am_tabela_existe($con, 'servicos')) {
            return;
        }

        if ($email !== '' && am_coluna_existe($con, 'servicos', 'email')) {
            $st = $con->prepare('DELETE FROM servicos WHERE email = ?');
            if ($st) {
                $st->bind_param('s', $email);
                $st->execute();
                $st->close();
            }
        }
        if ($emailNorm !== '' && am_coluna_existe($con, 'servicos', 'email')) {
            $st = $con->prepare("DELETE FROM servicos WHERE LOWER(TRIM(COALESCE(email, ''))) = ?");
            if ($st) {
                $st->bind_param('s', $emailNorm);
                $st->execute();
                $st->close();
            }
        }

        if (am_tabela_existe($con, 'clientes') && am_coluna_existe($con, 'clientes', 'id_cliente') && am_tabela_existe($con, 'servicos') && am_coluna_existe($con, 'servicos', 'id_cliente')) {
            $idsClientes = [];
            if ($emailNorm !== '') {
                $st = $con->prepare("SELECT id_cliente FROM clientes WHERE LOWER(TRIM(COALESCE(email, ''))) = ?");
                if ($st) {
                    $st->bind_param('s', $emailNorm);
                    if ($st->execute()) {
                        $res = $st->get_result();
                        while ($res && ($row = $res->fetch_assoc())) {
                            $idsClientes[] = (int) ($row['id_cliente'] ?? 0);
                        }
                    }
                    $st->close();
                }
            }
            foreach ($idsClientes as $idCliente) {
                if ($idCliente <= 0) {
                    continue;
                }
                @$con->query('DELETE FROM servicos WHERE id_cliente = ' . (int) $idCliente);
            }
        }
    }
}

if (!function_exists('am_utilizador_eliminar_conta_completa')) {
    /**
     * Remove todos os dados do cliente na plataforma.
     *
     * @return array{ok: bool, error: string}
     */
    function am_utilizador_eliminar_conta_completa(mysqli $con, int $id_utilizador, string $email_utilizador = ''): array
    {
        if ($id_utilizador <= 0) {
            return ['ok' => false, 'error' => 'Utilizador inválido.'];
        }

        $dados = am_utilizador_dados_eliminacao($con, $id_utilizador, $email_utilizador);
        $carIds = am_utilizador_ids_carros($con, $id_utilizador);
        $matriculas = am_utilizador_matriculas_carros($con, $id_utilizador);
        $servicoIds = am_utilizador_ids_servicos($con, $id_utilizador, $dados, $carIds, $matriculas);

        $con->begin_transaction();

        try {
            require_once __DIR__ . '/inc_admin_notificacoes.php';
            @$con->query('SET FOREIGN_KEY_CHECKS=0');

            am_utilizador_apagar_servicos($con, $id_utilizador, $dados, $servicoIds, $carIds, $matriculas);
            am_utilizador_apagar_legado($con, $dados['email'], $dados['email_norm']);

            if (am_tabela_existe($con, 'carro_perfil_pedido')) {
                $st = $con->prepare('DELETE FROM carro_perfil_pedido WHERE id_utilizador = ?');
                if ($st) {
                    $st->bind_param('i', $id_utilizador);
                    if (!$st->execute()) {
                        throw new RuntimeException($st->error);
                    }
                    $st->close();
                }
                foreach ($carIds as $cid) {
                    $st = $con->prepare('DELETE FROM carro_perfil_pedido WHERE id_carro = ?');
                    if ($st) {
                        $st->bind_param('i', $cid);
                        $st->execute();
                        $st->close();
                    }
                }
            }

            am_utilizador_apagar_ficheiros_carros($con, $carIds, $id_utilizador);

            if (am_tabela_existe($con, 'carro_perfil')) {
                foreach ($carIds as $cid) {
                    $st = $con->prepare('DELETE FROM carro_perfil WHERE id_carro = ?');
                    if ($st) {
                        $st->bind_param('i', $cid);
                        $st->execute();
                        $st->close();
                    }
                }
            }

            if (am_tabela_existe($con, 'carro')) {
                $st = $con->prepare('DELETE FROM carro WHERE id_utilizador = ?');
                if ($st) {
                    $st->bind_param('i', $id_utilizador);
                    if (!$st->execute()) {
                        throw new RuntimeException($st->error);
                    }
                    $st->close();
                }
            }

            if (am_tabela_existe($con, 'admin_notificacoes')) {
                $st = $con->prepare("DELETE FROM admin_notificacoes WHERE id_utilizador = ? OR (tipo IN ('chat_user','chat_admin','feedback','desbloqueio','avaliacao_temp') AND id_utilizador = ?)");
                if ($st) {
                    $st->bind_param('ii', $id_utilizador, $id_utilizador);
                    $st->execute();
                    $st->close();
                }
                $meta = am_notif_meta($con);
                foreach (am_notif_list_by_tipo($con, 'chat_admin') as $row) {
                    $p = am_notif_decode_payload($row['payload_json'] ?? null);
                    if ((int) ($p['para_usuario_id'] ?? $row['id_referencia'] ?? 0) === $id_utilizador) {
                        am_notif_delete($con, (int) ($row[$meta['id_col']] ?? 0));
                    }
                }
            }

            am_utilizador_apagar_por_email($con, $dados['email'], $dados['email_norm']);

            if (am_tabela_existe($con, 'sessoes_temporarias') && am_coluna_existe($con, 'sessoes_temporarias', 'utilizador_id')) {
                $st = $con->prepare('DELETE FROM sessoes_temporarias WHERE utilizador_id = ?');
                if ($st) {
                    $st->bind_param('i', $id_utilizador);
                    $st->execute();
                    $st->close();
                }
            }

            $stUser = $con->prepare('DELETE FROM utilizador WHERE id_utilizador = ?');
            if (!$stUser) {
                throw new RuntimeException($con->error);
            }
            $stUser->bind_param('i', $id_utilizador);
            if (!$stUser->execute() || $stUser->affected_rows < 1) {
                $err = $stUser->error;
                $stUser->close();
                throw new RuntimeException($err !== '' ? $err : 'Conta não encontrada.');
            }
            $stUser->close();

            @$con->query('SET FOREIGN_KEY_CHECKS=1');
            $con->commit();

            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            @$con->query('SET FOREIGN_KEY_CHECKS=1');
            $con->rollback();

            return [
                'ok' => false,
                'error' => 'Não foi possível eliminar a conta. Tente novamente ou contacte o suporte.',
            ];
        }
    }
}
