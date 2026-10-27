<?php

declare(strict_types=1);

/**
 * Validação simples de cartão (demonstração / pagamento fictício).
 * Não usa Luhn nem regras bancárias reais.
 */

if (!function_exists('am_cartao_apenas_digitos')) {
    function am_cartao_apenas_digitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }
}

if (!function_exists('am_cartao_validar_numero')) {
    /** @return string|null */
    function am_cartao_validar_numero(string $numero): ?string
    {
        $d = am_cartao_apenas_digitos($numero);
        if ($d === '') {
            return 'Indique o número do cartão.';
        }
        $len = strlen($d);
        if ($len < 8 || $len > 19) {
            return 'O número do cartão deve ter entre 8 e 19 dígitos.';
        }

        return null;
    }
}

if (!function_exists('am_cartao_validar_cvv')) {
    /** @return string|null */
    function am_cartao_validar_cvv(string $cvv, string $numero = ''): ?string
    {
        $c = am_cartao_apenas_digitos($cvv);
        if ($c === '') {
            return 'Indique o CVV.';
        }
        $len = strlen($c);
        if ($len < 3 || $len > 4) {
            return 'O CVV deve ter 3 ou 4 dígitos.';
        }

        return null;
    }
}

if (!function_exists('am_cartao_validar_validade')) {
    /** @return string|null */
    function am_cartao_validar_validade(string $validade): ?string
    {
        $v = trim($validade);
        if (!preg_match('/^(\d{2})\/(\d{2})$/', $v, $m)) {
            return 'Validade inválida. Use o formato MM/AA.';
        }
        $mes = (int) $m[1];
        if ($mes < 1 || $mes > 12) {
            return 'Mês de validade inválido (01 a 12).';
        }

        return null;
    }
}

if (!function_exists('am_cartao_validar_titular')) {
    /** @return string|null */
    function am_cartao_validar_titular(string $nome): ?string
    {
        $n = trim(preg_replace('/\s+/', ' ', $nome) ?? '');
        if ($n === '' || strlen($n) < 2) {
            return 'Indique o nome do titular.';
        }

        return null;
    }
}

if (!function_exists('am_cartao_validar_dados')) {
    /**
     * @param array<string, mixed> $d
     * @return string|null
     */
    function am_cartao_validar_dados(array $d): ?string
    {
        $numero = (string) ($d['numero_cartao'] ?? $d['numero'] ?? '');
        $cvv = (string) ($d['cvv'] ?? '');
        $validade = (string) ($d['validade'] ?? '');
        $titular = (string) ($d['nome_titular'] ?? $d['titular'] ?? '');

        foreach ([
            am_cartao_validar_numero($numero),
            am_cartao_validar_validade($validade),
            am_cartao_validar_cvv($cvv, $numero),
            am_cartao_validar_titular($titular),
        ] as $erro) {
            if ($erro !== null) {
                return $erro;
            }
        }

        return null;
    }
}
