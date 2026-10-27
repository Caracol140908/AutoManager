<?php

declare(strict_types=1);

/**
 * Whitelist das únicas tabelas permitidas na base de dados.
 */

if (!function_exists('am_tabelas_permitidas')) {
    /** @return string[] */
    function am_tabelas_permitidas(): array
    {
        return [
            'administrador',
            'admin_notificacoes',
            'carro',
            'carro_perfil',
            'carro_perfil_pedido',
            'chefes_oficina',
            'configuracoes',
            'configuracoes_site',
            'estado_site',
            'historico_salarios',
            'marcas',
            'mecanicos',
            'modelos',
            'orcamentista',
            'orcamento_assinaturas',
            'orcamento_itens',
            'orcamento_simulacoes',
            'passaporte_eventos',
            'passaporte_links_partilha',
            'servico',
            'servico_orcamento_item',
            'sessoes_temporarias',
            'utilizador',
        ];
    }
}

if (!function_exists('am_tabela_permitida')) {
    function am_tabela_permitida(string $tabela): bool
    {
        return in_array(strtolower(trim($tabela)), am_tabelas_permitidas(), true);
    }
}

if (!function_exists('am_tabela_existe')) {
    function am_tabela_existe(mysqli $con, string $tabela): bool
    {
        $tabela = trim($tabela);
        if ($tabela === '' || !am_tabela_permitida($tabela)) {
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
        if ($tabela === '' || $coluna === '' || !am_tabela_permitida($tabela)) {
            return false;
        }
        if (!am_tabela_existe($con, $tabela)) {
            return false;
        }
        $escT = $con->real_escape_string($tabela);
        $escC = $con->real_escape_string($coluna);
        $res = @$con->query("SHOW COLUMNS FROM `$escT` LIKE '$escC'");

        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('am_colunas_tabela')) {
    /** @return string[] */
    function am_colunas_tabela(mysqli $con, string $tabela): array
    {
        if (!am_tabela_existe($con, $tabela)) {
            return [];
        }
        $esc = $con->real_escape_string($tabela);
        $res = @$con->query("SHOW COLUMNS FROM `$esc`");
        if (!$res) {
            return [];
        }
        $cols = [];
        while ($row = $res->fetch_assoc()) {
            $cols[] = (string) ($row['Field'] ?? '');
        }

        return $cols;
    }
}

if (!function_exists('am_primeira_coluna')) {
    function am_primeira_coluna(array $cols, array $candidatos, string $fallback = ''): string
    {
        foreach ($candidatos as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }

        return $fallback !== '' ? $fallback : (string) ($candidatos[0] ?? '');
    }
}
