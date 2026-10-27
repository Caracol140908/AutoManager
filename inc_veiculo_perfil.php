<?php

declare(strict_types=1);

/**
 * Perfil técnico do veículo (ligado a carro.id_carro) + pedidos "completar dados".
 */

if (!function_exists('am_veiculo_perfil_normalizar_matricula')) {
    function am_veiculo_perfil_normalizar_matricula(string $matricula): string
    {
        if (function_exists('am_normalizar_matricula')) {
            return am_normalizar_matricula($matricula);
        }
        $limpo = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($matricula)));
        $limpo = substr($limpo, 0, 6);
        if ($limpo === '') {
            return '';
        }

        return implode('-', [
            substr($limpo, 0, 2),
            substr($limpo, 2, 2),
            substr($limpo, 4, 2),
        ]);
    }
}

if (!function_exists('am_veiculo_perfil_matricula_limpa')) {
    function am_veiculo_perfil_matricula_limpa(string $matricula): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($matricula)));
    }
}

if (!function_exists('am_veiculo_perfil_erro_store')) {
    /** Guarda ou devolve a última mensagem de erro do perfil do veículo. */
    function am_veiculo_perfil_erro_store(string $msg = ''): string
    {
        static $erro = '';
        if ($msg !== '') {
            $erro = $msg;
        }

        return $erro;
    }
}

if (!function_exists('am_ensure_veiculo_perfil_tables')) {
    function am_ensure_veiculo_perfil_tables(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        @$con->query("
            CREATE TABLE IF NOT EXISTS carro_perfil (
                id_carro INT NOT NULL PRIMARY KEY,
                propulsao VARCHAR(40) NULL,
                motorizacao VARCHAR(120) NULL,
                vin VARCHAR(32) NULL,
                km_atual INT UNSIGNED NULL,
                consumo_l_100 DECIMAL(5,2) NULL,
                autonomia_media_km INT UNSIGNED NULL,
                capacidade_deposito_litros DECIMAL(6,2) NULL,
                kwh_bateria DECIMAL(8,2) NULL,
                tipo_oleo VARCHAR(120) NULL,
                pneus_medida VARCHAR(40) NULL,
                pneus_pressao VARCHAR(80) NULL,
                data_ultima_inspecao DATE NULL,
                data_ultimo_servico DATE NULL,
                seguro_seguradora VARCHAR(100) NULL,
                seguro_apolice VARCHAR(80) NULL,
                seguro_validade DATE NULL,
                foto_relativa VARCHAR(255) NULL,
                notas_oficina TEXT NULL,
                atualizado_por VARCHAR(20) NULL,
                atualizado_em DATETIME NULL,
                KEY idx_atualizado (atualizado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        @$con->query("
            CREATE TABLE IF NOT EXISTS carro_perfil_pedido (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_carro INT NOT NULL,
                id_utilizador INT NOT NULL,
                id_servico INT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
                id_mecanico INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                KEY idx_user_estado (id_utilizador, estado),
                KEY idx_carro_estado (id_carro, estado),
                KEY idx_servico (id_servico)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $cols = [];
        $qCols = @$con->query('SHOW COLUMNS FROM carro_perfil');
        if ($qCols) {
            while ($row = $qCols->fetch_assoc()) {
                if (!empty($row['Field'])) {
                    $cols[$row['Field']] = true;
                }
            }
        }
        $add = static function (string $name, string $def) use ($con, &$cols): void {
            if (isset($cols[$name])) {
                return;
            }
            @$con->query("ALTER TABLE carro_perfil ADD COLUMN $name $def");
            $cols[$name] = true;
        };
        $add('propulsao', 'VARCHAR(40) NULL');
        $add('motorizacao', 'VARCHAR(120) NULL');
        $add('vin', 'VARCHAR(32) NULL');
        $add('km_atual', 'INT UNSIGNED NULL');
        $add('consumo_l_100', 'DECIMAL(5,2) NULL');
        $add('autonomia_media_km', 'INT UNSIGNED NULL');
        $add('capacidade_deposito_litros', 'DECIMAL(6,2) NULL');
        $add('kwh_bateria', 'DECIMAL(8,2) NULL');
        $add('tipo_oleo', 'VARCHAR(120) NULL');
        $add('pneus_medida', 'VARCHAR(40) NULL');
        $add('pneus_pressao', 'VARCHAR(80) NULL');
        $add('data_ultima_inspecao', 'DATE NULL');
        $add('data_ultimo_servico', 'DATE NULL');
        $add('seguro_seguradora', 'VARCHAR(100) NULL');
        $add('seguro_apolice', 'VARCHAR(80) NULL');
        $add('seguro_validade', 'DATE NULL');
        $add('foto_relativa', 'VARCHAR(255) NULL');
        $add('notas_oficina', 'TEXT NULL');
        $add('atualizado_por', 'VARCHAR(20) NULL');
        $add('atualizado_em', 'DATETIME NULL');
    }
}

if (!function_exists('am_servico_colunas')) {
    function am_servico_colunas(mysqli $con): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $q = @$con->query("SHOW COLUMNS FROM servico");
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                if (!empty($row['Field'])) {
                    $cache[$row['Field']] = true;
                }
            }
        }

        return $cache;
    }
}

if (!function_exists('am_servico_id_col')) {
    function am_servico_id_col(mysqli $con): string
    {
        $cols = am_servico_colunas($con);
        if (isset($cols['id'])) {
            return 'id';
        }
        if (isset($cols['id_servico'])) {
            return 'id_servico';
        }

        return '';
    }
}

if (!function_exists('am_servico_mec_col_expr')) {
    /**
     * Expressão SQL do mecânico atribuído (id_mecanico e/ou mecanico_id).
     */
    function am_servico_mec_col_expr(mysqli $con, string $alias = ''): string
    {
        $cols = am_servico_colunas($con);
        $p = $alias !== '' ? $alias . '.' : '';
        $hasId = isset($cols['id_mecanico']);
        $hasAlt = isset($cols['mecanico_id']);
        if ($hasId && $hasAlt) {
            return 'COALESCE(' . $p . 'id_mecanico, ' . $p . 'mecanico_id)';
        }
        if ($hasId) {
            return $p . 'id_mecanico';
        }
        if ($hasAlt) {
            return $p . 'mecanico_id';
        }

        return '';
    }
}

if (!function_exists('am_servico_fetch_para_mecanico')) {
    /**
     * Carrega um serviço se pertencer ao mecânico (COALESCE id_mecanico/mecanico_id).
     *
     * @return array<string, mixed>|null
     */
    function am_servico_fetch_para_mecanico(mysqli $con, int $servico_id, int $mecanico_id): ?array
    {
        $idCol = am_servico_id_col($con);
        $mecExpr = am_servico_mec_col_expr($con);
        if ($idCol === '' || $mecExpr === '' || $servico_id <= 0 || $mecanico_id <= 0) {
            return null;
        }

        $cols = am_servico_colunas($con);
        $sel = ["$idCol AS id", "$mecExpr AS mec_ref", 'matricula', 'email'];
        if (isset($cols['id_utilizador'])) {
            $sel[] = 'id_utilizador';
        }
        foreach (['orcamento_aprovado', 'valor_total_com_iva', 'valor_pago_total', 'estado_pagamento', 'estado_do_trabalho'] as $extraCol) {
            if (isset($cols[$extraCol])) {
                $sel[] = $extraCol;
            }
        }
        foreach (['horas_trabalhadas', 'tempo_trabalho_minutos'] as $extraCol) {
            if (isset($cols[$extraCol])) {
                $sel[] = $extraCol;
            }
        }

        $sql = 'SELECT ' . implode(', ', $sel) . " FROM servico WHERE $idCol = ? AND $mecExpr = ? LIMIT 1";
        $st = $con->prepare($sql);
        if (!$st) {
            return null;
        }
        $st->bind_param('ii', $servico_id, $mecanico_id);
        $st->execute();
        $r = $st->get_result();
        $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
        $st->close();

        return $row ?: null;
    }
}

if (!function_exists('am_servico_resolver_id_carro')) {
    /**
     * Liga um registo de serviço a carro.id_carro do cliente (matrícula + utilizador/email).
     */
    function am_servico_resolver_id_carro(mysqli $con, array $servico): int
    {
        am_ensure_veiculo_perfil_tables($con);
        $mat = am_veiculo_perfil_normalizar_matricula((string) ($servico['matricula'] ?? ''));
        if ($mat === '' || $mat === '--') {
            return 0;
        }

        $cols = am_servico_colunas($con);
        $uid = 0;
        if (!empty($cols['id_utilizador']) && isset($servico['id_utilizador'])) {
            $uid = (int) $servico['id_utilizador'];
        }
        if ($uid > 0) {
            $st = $con->prepare('SELECT id_carro FROM carro WHERE id_utilizador = ? AND matricula = ? LIMIT 1');
            if ($st) {
                $st->bind_param('is', $uid, $mat);
                if ($st->execute()) {
                    $r = $st->get_result();
                    if ($r && ($row = $r->fetch_assoc())) {
                        $id = (int) ($row['id_carro'] ?? 0);
                        $st->close();

                        return $id;
                    }
                }
                $st->close();
            }
            $limpo = am_veiculo_perfil_matricula_limpa((string) ($servico['matricula'] ?? ''));
            if ($limpo !== '') {
                $st = $con->prepare('SELECT id_carro FROM carro WHERE id_utilizador = ? AND REPLACE(REPLACE(UPPER(matricula), \'-\', \'\'), \' \', \'\') = ? LIMIT 1');
                if ($st) {
                    $st->bind_param('is', $uid, $limpo);
                    if ($st->execute()) {
                        $r = $st->get_result();
                        if ($r && ($row = $r->fetch_assoc())) {
                            $id = (int) ($row['id_carro'] ?? 0);
                            $st->close();

                            return $id;
                        }
                    }
                    $st->close();
                }
            }
        }

        $email = strtolower(trim((string) ($servico['email'] ?? '')));
        if ($email === '') {
            return 0;
        }
        $st = $con->prepare('
            SELECT c.id_carro
            FROM carro c
            INNER JOIN utilizador u ON u.id_utilizador = c.id_utilizador
            WHERE LOWER(TRIM(u.email)) = ? AND c.matricula = ?
            LIMIT 1
        ');
        if ($st) {
            $st->bind_param('ss', $email, $mat);
            if ($st->execute()) {
                $r = $st->get_result();
                if ($r && ($row = $r->fetch_assoc())) {
                    $id = (int) ($row['id_carro'] ?? 0);
                    $st->close();

                    return $id;
                }
            }
            $st->close();
        }

        $limpo = am_veiculo_perfil_matricula_limpa((string) ($servico['matricula'] ?? ''));
        if ($limpo !== '') {
            $st = $con->prepare('
                SELECT c.id_carro
                FROM carro c
                INNER JOIN utilizador u ON u.id_utilizador = c.id_utilizador
                WHERE LOWER(TRIM(u.email)) = ? AND REPLACE(REPLACE(UPPER(c.matricula), \'-\', \'\'), \' \', \'\') = ?
                LIMIT 1
            ');
            if ($st) {
                $st->bind_param('ss', $email, $limpo);
                if ($st->execute()) {
                    $r = $st->get_result();
                    if ($r && ($row = $r->fetch_assoc())) {
                        $id = (int) ($row['id_carro'] ?? 0);
                        $st->close();

                        return $id;
                    }
                }
                $st->close();
            }
        }

        return 0;
    }
}

if (!function_exists('am_veiculo_perfil_get')) {
    function am_veiculo_perfil_get(mysqli $con, int $id_carro): ?array
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_carro <= 0) {
            return null;
        }
        $st = $con->prepare('SELECT * FROM carro_perfil WHERE id_carro = ? LIMIT 1');
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $id_carro);
        $st->execute();
        $r = $st->get_result();
        $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
        $st->close();

        return $row ?: null;
    }
}

if (!function_exists('am_veiculo_perfil_pedidos_pendentes_ids')) {
    /** @return int[] */
    function am_veiculo_perfil_pedidos_pendentes_ids(mysqli $con, int $id_utilizador): array
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_utilizador <= 0) {
            return [];
        }
        $ids = [];
        $st = $con->prepare("SELECT id FROM carro_perfil_pedido WHERE id_utilizador = ? AND estado = 'pendente' ORDER BY id ASC");
        if ($st) {
            $st->bind_param('i', $id_utilizador);
            if ($st->execute()) {
                $r = $st->get_result();
                if ($r) {
                    while ($row = $r->fetch_assoc()) {
                        $ids[] = (int) $row['id'];
                    }
                }
            }
            $st->close();
        }

        return $ids;
    }
}

if (!function_exists('am_veiculo_perfil_pedido_pendente_servico')) {
    function am_veiculo_perfil_pedido_pendente_servico(mysqli $con, int $id_servico): bool
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_servico <= 0) {
            return false;
        }
        $st = $con->prepare("SELECT id FROM carro_perfil_pedido WHERE id_servico = ? AND estado = 'pendente' LIMIT 1");
        if (!$st) {
            return false;
        }
        $st->bind_param('i', $id_servico);
        $st->execute();
        $r = $st->get_result();
        $ok = $r && $r->num_rows > 0;
        $st->close();

        return $ok;
    }
}

if (!function_exists('am_veiculo_perfil_resolver_pedidos_carro')) {
    function am_veiculo_perfil_resolver_pedidos_carro(mysqli $con, int $id_carro): void
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_carro <= 0) {
            return;
        }
        $st = $con->prepare("UPDATE carro_perfil_pedido SET estado = 'resolvido', resolved_at = NOW() WHERE id_carro = ? AND estado = 'pendente'");
        if ($st) {
            $st->bind_param('i', $id_carro);
            $st->execute();
            $st->close();
        }
    }
}

if (!function_exists('am_veiculo_perfil_upsert')) {
    /**
     * @param array<string, mixed> $d
     */
    function am_veiculo_perfil_upsert(mysqli $con, int $id_carro, array $d, string $por): bool
    {
        am_ensure_veiculo_perfil_tables($con);
        am_veiculo_perfil_erro_store('');
        if ($id_carro <= 0 || ($por !== 'cliente' && $por !== 'oficina')) {
            am_veiculo_perfil_erro_store('Veículo inválido');

            return false;
        }

        $ex = $con->prepare('SELECT id_carro FROM carro_perfil WHERE id_carro = ? LIMIT 1');
        if (!$ex) {
            am_veiculo_perfil_erro_store($con->error ?: 'Erro ao verificar perfil');

            return false;
        }
        $ex->bind_param('i', $id_carro);
        $ex->execute();
        $exists = $ex->get_result()->num_rows > 0;
        $ex->close();

        if ($exists) {
            $prev = am_veiculo_perfil_get($con, $id_carro);
            if (is_array($prev)) {
                $mergeIfMissing = [
                    'consumo_l_100', 'autonomia_media_km', 'capacidade_deposito_litros', 'kwh_bateria',
                    'pneus_pressao', 'tipo_oleo', 'pneus_medida',
                    'seguro_seguradora', 'seguro_apolice', 'seguro_validade',
                ];
                foreach ($mergeIfMissing as $mk) {
                    if (!array_key_exists($mk, $d)) {
                        $d[$mk] = $prev[$mk] ?? null;
                    }
                }
            }
        }

        $propulsao = isset($d['propulsao']) ? substr(trim((string) $d['propulsao']), 0, 40) : null;
        $motorizacao = isset($d['motorizacao']) ? substr(trim((string) $d['motorizacao']), 0, 120) : null;
        $vin = isset($d['vin']) ? strtoupper(preg_replace('/[^A-HJ-NPR-Z0-9]/i', '', (string) $d['vin'])) : '';
        $vin = $vin !== '' ? substr($vin, 0, 32) : null;
        if ($vin !== null) {
            $stVin = $con->prepare('SELECT id_carro FROM carro_perfil WHERE vin = ? AND id_carro <> ? LIMIT 1');
            if (!$stVin) {
                am_veiculo_perfil_erro_store($con->error ?: 'Erro ao validar VIN');

                return false;
            }
            $stVin->bind_param('si', $vin, $id_carro);
            $stVin->execute();
            $vinDup = $stVin->get_result();
            $vinJaExiste = $vinDup && $vinDup->num_rows > 0;
            $stVin->close();
            if ($vinJaExiste) {
                am_veiculo_perfil_erro_store('Este VIN já está associado a outro veículo');

                return false;
            }
        }

        $km = isset($d['km_atual']) ? (int) $d['km_atual'] : null;
        if ($km !== null && $km < 0) {
            $km = null;
        }
        $kmAnterior = null;
        if ($exists && isset($prev) && is_array($prev) && isset($prev['km_atual']) && $prev['km_atual'] !== null && $prev['km_atual'] !== '') {
            $kmAnterior = (int) $prev['km_atual'];
        }
        if ($por === 'oficina' && $km !== null && $kmAnterior !== null && $km < $kmAnterior) {
            am_veiculo_perfil_erro_store('O mecânico não pode baixar os km de saída já registados');

            return false;
        }

        $consumo = isset($d['consumo_l_100']) && $d['consumo_l_100'] !== '' ? (float) str_replace(',', '.', (string) $d['consumo_l_100']) : null;
        $autonomia = isset($d['autonomia_media_km']) && $d['autonomia_media_km'] !== '' ? (int) $d['autonomia_media_km'] : null;
        $cap_dep = isset($d['capacidade_deposito_litros']) && $d['capacidade_deposito_litros'] !== '' ? (float) str_replace(',', '.', (string) $d['capacidade_deposito_litros']) : null;
        $kwh = isset($d['kwh_bateria']) && $d['kwh_bateria'] !== '' ? (float) str_replace(',', '.', (string) $d['kwh_bateria']) : null;
        $tipo_oleo = isset($d['tipo_oleo']) ? substr(trim((string) $d['tipo_oleo']), 0, 120) : null;
        $pneus = isset($d['pneus_medida']) ? substr(trim((string) $d['pneus_medida']), 0, 40) : null;
        $press = isset($d['pneus_pressao']) ? substr(trim((string) $d['pneus_pressao']), 0, 80) : null;

        $d_ins = isset($d['data_ultima_inspecao']) ? trim((string) $d['data_ultima_inspecao']) : '';
        $d_srv = isset($d['data_ultimo_servico']) ? trim((string) $d['data_ultimo_servico']) : '';
        $data_insp = ($d_ins !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d_ins)) ? $d_ins : null;
        $data_us = ($d_srv !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d_srv)) ? $d_srv : null;

        $todayCut = date('Y-m-d');
        if ($data_insp !== null && $data_insp > $todayCut) {
            am_veiculo_perfil_erro_store('A data da inspeção não pode ser futura');

            return false;
        }
        if ($data_us !== null && $data_us > $todayCut) {
            am_veiculo_perfil_erro_store('A data do último serviço não pode ser futura');

            return false;
        }

        $seg_nome = isset($d['seguro_seguradora']) ? substr(trim((string) $d['seguro_seguradora']), 0, 100) : null;
        $seg_ap = isset($d['seguro_apolice']) ? substr(trim((string) $d['seguro_apolice']), 0, 80) : null;
        $seg_val = isset($d['seguro_validade']) ? trim((string) $d['seguro_validade']) : '';
        $seguro_validade = ($seg_val !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $seg_val)) ? $seg_val : null;

        $foto = isset($d['foto_relativa']) && $d['foto_relativa'] !== '' ? substr((string) $d['foto_relativa'], 0, 255) : null;
        $notas = isset($d['notas_oficina']) ? trim((string) $d['notas_oficina']) : null;
        if ($notas === '') {
            $notas = null;
        }

        if ($exists) {
            if ($por === 'cliente') {
                $sql = 'UPDATE carro_perfil SET
                    propulsao = ?, motorizacao = ?, vin = ?, km_atual = ?, consumo_l_100 = ?, autonomia_media_km = ?,
                    capacidade_deposito_litros = ?, kwh_bateria = ?, tipo_oleo = ?, pneus_medida = ?, pneus_pressao = ?,
                    data_ultima_inspecao = ?, data_ultimo_servico = ?, seguro_seguradora = ?, seguro_apolice = ?, seguro_validade = ?,
                    foto_relativa = COALESCE(?, foto_relativa),
                    atualizado_por = ?, atualizado_em = NOW()
                    WHERE id_carro = ?';
                $st = $con->prepare($sql);
                if (!$st) {
                    am_veiculo_perfil_erro_store($con->error ?: 'Erro ao preparar atualização');

                    return false;
                }
                $st->bind_param(
                    'sssididdssssssssssi',
                    $propulsao,
                    $motorizacao,
                    $vin,
                    $km,
                    $consumo,
                    $autonomia,
                    $cap_dep,
                    $kwh,
                    $tipo_oleo,
                    $pneus,
                    $press,
                    $data_insp,
                    $data_us,
                    $seg_nome,
                    $seg_ap,
                    $seguro_validade,
                    $foto,
                    $por,
                    $id_carro
                );
            } else {
                $sql = 'UPDATE carro_perfil SET
                    propulsao = ?, motorizacao = ?, vin = ?, km_atual = ?, consumo_l_100 = ?, autonomia_media_km = ?,
                    capacidade_deposito_litros = ?, kwh_bateria = ?, tipo_oleo = ?, pneus_medida = ?, pneus_pressao = ?,
                    data_ultima_inspecao = ?, data_ultimo_servico = ?, seguro_seguradora = ?, seguro_apolice = ?, seguro_validade = ?,
                    foto_relativa = COALESCE(?, foto_relativa), notas_oficina = ?,
                    atualizado_por = ?, atualizado_em = NOW()
                    WHERE id_carro = ?';
                $st = $con->prepare($sql);
                if (!$st) {
                    am_veiculo_perfil_erro_store($con->error ?: 'Erro ao preparar atualização');

                    return false;
                }
                $st->bind_param(
                    'sssididdsssssssssssi',
                    $propulsao,
                    $motorizacao,
                    $vin,
                    $km,
                    $consumo,
                    $autonomia,
                    $cap_dep,
                    $kwh,
                    $tipo_oleo,
                    $pneus,
                    $press,
                    $data_insp,
                    $data_us,
                    $seg_nome,
                    $seg_ap,
                    $seguro_validade,
                    $foto,
                    $notas,
                    $por,
                    $id_carro
                );
            }
        } else {
            $sql = 'INSERT INTO carro_perfil (
                id_carro, propulsao, motorizacao, vin, km_atual, consumo_l_100, autonomia_media_km,
                capacidade_deposito_litros, kwh_bateria, tipo_oleo, pneus_medida, pneus_pressao,
                data_ultima_inspecao, data_ultimo_servico, seguro_seguradora, seguro_apolice, seguro_validade,
                foto_relativa, notas_oficina, atualizado_por, atualizado_em
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
            $st = $con->prepare($sql);
            if (!$st) {
                am_veiculo_perfil_erro_store($con->error ?: 'Erro ao preparar inserção');

                return false;
            }
            $st->bind_param(
                'isssididdsssssssssss',
                $id_carro,
                $propulsao,
                $motorizacao,
                $vin,
                $km,
                $consumo,
                $autonomia,
                $cap_dep,
                $kwh,
                $tipo_oleo,
                $pneus,
                $press,
                $data_insp,
                $data_us,
                $seg_nome,
                $seg_ap,
                $seguro_validade,
                $foto,
                $notas,
                $por
            );
        }

        $ok = $st->execute();
        if (!$ok) {
            am_veiculo_perfil_erro_store($st->error ?: $con->error ?: 'Erro ao guardar na base de dados');
        }
        $st->close();

        if ($ok && ($por === 'cliente' || $por === 'oficina')) {
            am_veiculo_perfil_resolver_pedidos_carro($con, $id_carro);
        }

        return $ok;
    }
}

if (!function_exists('am_veiculo_perfil_criar_pedido')) {
    function am_veiculo_perfil_criar_pedido(mysqli $con, int $id_carro, int $id_utilizador, ?int $id_servico, int $id_mecanico): bool
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_carro <= 0 || $id_utilizador <= 0) {
            return false;
        }
        $dup = $con->prepare('SELECT id FROM carro_perfil_pedido WHERE id_carro = ? LIMIT 1');
        if ($dup) {
            $dup->bind_param('i', $id_carro);
            $dup->execute();
            $r = $dup->get_result();
            if ($r && $r->num_rows > 0) {
                $dup->close();

                return true;
            }
            $dup->close();
        }

        $srv = $id_servico !== null && $id_servico > 0 ? $id_servico : null;
        if ($srv === null) {
            $st = $con->prepare('INSERT INTO carro_perfil_pedido (id_carro, id_utilizador, id_servico, estado, id_mecanico) VALUES (?, ?, NULL, \'pendente\', ?)');
            if (!$st) {
                return false;
            }
            $st->bind_param('iii', $id_carro, $id_utilizador, $id_mecanico);
        } else {
            $st = $con->prepare('INSERT INTO carro_perfil_pedido (id_carro, id_utilizador, id_servico, estado, id_mecanico) VALUES (?, ?, ?, \'pendente\', ?)');
            if (!$st) {
                return false;
            }
            $st->bind_param('iiii', $id_carro, $id_utilizador, $srv, $id_mecanico);
        }
        $ok = $st->execute();
        $st->close();

        return $ok;
    }
}

if (!function_exists('am_veiculo_perfil_tem_pedido_pendente_carro')) {
    function am_veiculo_perfil_tem_pedido_pendente_carro(mysqli $con, int $id_carro): bool
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_carro <= 0) {
            return false;
        }
        $st = $con->prepare("SELECT id FROM carro_perfil_pedido WHERE id_carro = ? AND estado = 'pendente' LIMIT 1");
        if (!$st) {
            return false;
        }
        $st->bind_param('i', $id_carro);
        $st->execute();
        $r = $st->get_result();
        $ok = $r && $r->num_rows > 0;
        $st->close();

        return $ok;
    }
}

if (!function_exists('am_veiculo_perfil_ja_foi_solicitado_carro')) {
    function am_veiculo_perfil_ja_foi_solicitado_carro(mysqli $con, int $id_carro): bool
    {
        am_ensure_veiculo_perfil_tables($con);
        if ($id_carro <= 0) {
            return false;
        }
        $st = $con->prepare('SELECT id FROM carro_perfil_pedido WHERE id_carro = ? LIMIT 1');
        if (!$st) {
            return false;
        }
        $st->bind_param('i', $id_carro);
        $st->execute();
        $r = $st->get_result();
        $ok = $r && $r->num_rows > 0;
        $st->close();

        return $ok;
    }
}

if (!function_exists('am_veiculo_perfil_id_utilizador_do_carro')) {
    function am_veiculo_perfil_id_utilizador_do_carro(mysqli $con, int $id_carro): int
    {
        if ($id_carro <= 0) {
            return 0;
        }
        $st = $con->prepare('SELECT id_utilizador FROM carro WHERE id_carro = ? LIMIT 1');
        if (!$st) {
            return 0;
        }
        $st->bind_param('i', $id_carro);
        $st->execute();
        $r = $st->get_result();
        $uid = ($r && ($row = $r->fetch_assoc())) ? (int) ($row['id_utilizador'] ?? 0) : 0;
        $st->close();

        return $uid;
    }
}
