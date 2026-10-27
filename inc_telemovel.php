<?php

declare(strict_types=1);

/**
 * Telemóvel português — normalização, formatação e validação.
 */

if (!function_exists('am_normalizar_telemovel')) {
    function am_normalizar_telemovel(string $telemovel): string
    {
        return substr(preg_replace('/\D+/', '', $telemovel), 0, 9);
    }
}

if (!function_exists('am_formatar_telemovel')) {
    function am_formatar_telemovel(string $telemovel): string
    {
        $limpo = am_normalizar_telemovel($telemovel);
        if ($limpo === '') {
            return '';
        }

        return trim(chunk_split($limpo, 3, ' '));
    }
}

if (!function_exists('am_validar_telemovel_portugues')) {
    /** @return array{0:bool,1:string} */
    function am_validar_telemovel_portugues(string $telemovel, bool $obrigatorio = false): array
    {
        $telemovel = am_normalizar_telemovel($telemovel);

        if ($telemovel === '') {
            return $obrigatorio
                ? [false, 'O telemóvel é obrigatório.']
                : [true, ''];
        }

        if (strlen($telemovel) !== 9) {
            return [false, 'O telemóvel deve ter exatamente 9 dígitos.'];
        }

        if (preg_match('/^(\d)\1{8}$/', $telemovel)) {
            return [false, 'Telemóvel inválido.'];
        }

        if (!preg_match('/^9[1-6]\d{7}$/', $telemovel)) {
            return [false, 'Indique um telemóvel português válido (deve começar por 91, 92, 93 ou 96).'];
        }

        return [true, ''];
    }
}
