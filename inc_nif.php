<?php

declare(strict_types=1);

/**
 * NIF português — normalização, validação e unicidade no sistema.
 */

if (!function_exists('am_normalizar_nif')) {
    function am_normalizar_nif(string $nif): string
    {
        return substr(preg_replace('/\D+/', '', $nif), 0, 9);
    }
}

if (!function_exists('am_validar_nif_portugues')) {
    /** @return array{0:bool,1:string} */
    function am_validar_nif_portugues(string $nif): array
    {
        $nif = am_normalizar_nif($nif);

        if (strlen($nif) !== 9) {
            return [false, 'O NIF deve ter exatamente 9 dígitos.'];
        }

        if (preg_match('/^(\d)\1{8}$/', $nif)) {
            return [false, 'O NIF não pode ter todos os dígitos iguais.'];
        }

        if (!preg_match('/^[1235689]/', $nif)) {
            return [false, 'O NIF tem um prefixo inválido.'];
        }

        $soma = 0;
        for ($i = 0; $i < 8; $i++) {
            $soma += ((int) $nif[$i]) * (9 - $i);
        }
        $resto = $soma % 11;
        $digito = 11 - $resto;
        if ($digito >= 10) {
            $digito = 0;
        }

        if ((int) $nif[8] !== $digito) {
            return [false, 'NIF inválido (dígito de controlo incorreto).'];
        }

        return [true, ''];
    }
}

if (!function_exists('am_garantir_nif_unico_schema')) {
    function am_garantir_nif_unico_schema(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $res = @$con->query("SHOW COLUMNS FROM utilizador LIKE 'nif'");
        if (!$res || $res->num_rows === 0) {
            @$con->query('ALTER TABLE utilizador ADD COLUMN nif VARCHAR(9) NULL AFTER telefone');
        }

        @$con->query("UPDATE utilizador SET nif = NULL WHERE nif IS NOT NULL AND TRIM(nif) = ''");

        $idx = @$con->query("SHOW INDEX FROM utilizador WHERE Key_name = 'uk_utilizador_nif'");
        if ($idx && $idx->num_rows === 0) {
            @$con->query('ALTER TABLE utilizador ADD UNIQUE KEY uk_utilizador_nif (nif)');
        }

        foreach (['mecanicos' => 'uk_mecanicos_nif', 'chefes_oficina' => 'uk_chefes_nif'] as $tabela => $indice) {
            $res = @$con->query("SHOW TABLES LIKE '$tabela'");
            if (!$res || $res->num_rows === 0) {
                continue;
            }

            if (!am_tabela_tem_coluna_nif($con, $tabela)) {
                @$con->query("ALTER TABLE `$tabela` ADD COLUMN nif VARCHAR(9) NULL");
            }

            @$con->query("UPDATE `$tabela` SET nif = NULL WHERE nif IS NOT NULL AND TRIM(nif) = ''");

            $idxTabela = @$con->query("SHOW INDEX FROM `$tabela` WHERE Key_name = '$indice'");
            if ($idxTabela && $idxTabela->num_rows === 0) {
                @$con->query("ALTER TABLE `$tabela` ADD UNIQUE KEY `$indice` (nif)");
            }
        }
    }
}

if (!function_exists('am_tabela_tem_coluna_nif')) {
    function am_tabela_tem_coluna_nif(mysqli $con, string $tabela): bool
    {
        static $cache = [];
        if (isset($cache[$tabela])) {
            return $cache[$tabela];
        }
        $ok = false;
        $res = @$con->query("SHOW COLUMNS FROM `$tabela` LIKE 'nif'");
        if ($res && $res->num_rows > 0) {
            $ok = true;
            $cache[$tabela] = true;
        }

        return $ok;
    }
}

if (!function_exists('am_nif_existe_no_sistema')) {
    /**
     * Procura NIF duplicado em utilizador, mecanicos, orcamentista e chefes_oficina.
     *
     * @param array{utilizador?:int,mecanicos?:int,orcamentista?:int,chefes_oficina?:int} $excluir
     * @return array{origem:string,id:int,nome:string}|null
     */
    function am_nif_existe_no_sistema(mysqli $con, string $nif, array $excluir = []): ?array
    {
        am_garantir_nif_unico_schema($con);

        $nif = am_normalizar_nif($nif);
        if (strlen($nif) !== 9) {
            return null;
        }

        $mapas = [
            ['tabela' => 'utilizador', 'id' => 'id_utilizador', 'nome' => 'nome_utilizador', 'chave' => 'utilizador'],
            ['tabela' => 'mecanicos', 'id' => 'id_mecanico', 'nome' => 'nome_mecanico', 'chave' => 'mecanicos'],
            ['tabela' => 'orcamentista', 'id' => 'id_orcamentista', 'nome' => 'nome_orcamentista', 'chave' => 'orcamentista'],
            ['tabela' => 'chefes_oficina', 'id' => 'id_chefe', 'nome' => 'nome', 'chave' => 'chefes_oficina'],
        ];

        $nifSqlExpr = "REPLACE(REPLACE(REPLACE(nif, ' ', ''), '-', ''), '.', '')";

        foreach ($mapas as $mapa) {
            $tabela = $mapa['tabela'];
            if (!am_tabela_tem_coluna_nif($con, $tabela)) {
                continue;
            }

            $excludeId = (int) ($excluir[$mapa['chave']] ?? 0);
            $sql = "SELECT {$mapa['id']} AS id_ref, {$mapa['nome']} AS nome_ref FROM `$tabela`"
                . " WHERE {$nifSqlExpr} = ? AND nif IS NOT NULL AND TRIM(nif) <> ''";
            if ($excludeId > 0) {
                $sql .= " AND {$mapa['id']} <> ?";
            }
            $sql .= ' LIMIT 1';

            $stmt = $con->prepare($sql);
            if (!$stmt) {
                continue;
            }

            if ($excludeId > 0) {
                $stmt->bind_param('si', $nif, $excludeId);
            } else {
                $stmt->bind_param('s', $nif);
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                if ($row) {
                    $stmt->close();

                    return [
                        'origem' => $tabela,
                        'id' => (int) ($row['id_ref'] ?? 0),
                        'nome' => (string) ($row['nome_ref'] ?? ''),
                    ];
                }
            }
            $stmt->close();
        }

        return null;
    }
}

if (!function_exists('am_validar_nif_unico')) {
    /** @return array{0:bool,1:string} */
    function am_validar_nif_unico(mysqli $con, string $nif, array $excluir = []): array
    {
        $nif = am_normalizar_nif($nif);
        if ($nif === '') {
            return [true, ''];
        }

        [$valido, $msgValidacao] = am_validar_nif_portugues($nif);
        if (!$valido) {
            return [false, $msgValidacao];
        }

        $dup = am_nif_existe_no_sistema($con, $nif, $excluir);
        if ($dup === null) {
            return [true, ''];
        }

        $msg = 'Este NIF já está registado no sistema';
        if ($dup['nome'] !== '') {
            $msg .= ' (' . $dup['nome'] . ')';
        }
        $origem = am_etiqueta_origem_registo((string) ($dup['origem'] ?? ''));
        if ($origem !== '') {
            $msg .= ' — já usado por um ' . $origem;
        }
        $msg .= '.';

        return [false, $msg];
    }
}

if (!function_exists('am_etiqueta_origem_registo')) {
    function am_etiqueta_origem_registo(string $tabela): string
    {
        $mapa = [
            'utilizador' => 'cliente',
            'mecanicos' => 'mecânico',
            'chefes_oficina' => 'chefe de oficina',
            'orcamentista' => 'orçamentista',
        ];

        return $mapa[$tabela] ?? '';
    }
}

if (!function_exists('am_password_padrao_funcionario')) {
    function am_password_padrao_funcionario(): string
    {
        return 'Automanager26!';
    }

    function am_hash_password_padrao_funcionario(): string
    {
        return password_hash(am_password_padrao_funcionario(), PASSWORD_DEFAULT);
    }

    function am_texto_credenciais_funcionario_html(): string
    {
        $pwd = htmlspecialchars(am_password_padrao_funcionario(), ENT_QUOTES, 'UTF-8');
        return '<strong>Palavra-passe inicial:</strong> <code>' . $pwd . '</code> — '
            . 'no <strong>primeiro login</strong> será obrigatório definir uma nova palavra-passe.';
    }
}
