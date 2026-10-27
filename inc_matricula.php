<?php
/**
 * Normalização e validação de matrículas portuguesas.
 *
 * Formatos aceites:
 * - AA-00-AA (atual, desde 2005)
 * - 00-AA-00 (1992–2005)
 * - 00-00-AA (anterior a 1992)
 */

function am_normalizar_matricula(string $matricula): string
{
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

/**
 * @return array{0: bool, 1: string, 2: string} [válida, mensagem erro, matrícula normalizada]
 */
function am_validar_matricula(string $matricula): array
{
    $normalizada = am_normalizar_matricula($matricula);
    $limpo = preg_replace('/[^A-Z0-9]/', '', $normalizada);

    if ($limpo === '') {
        return [false, 'Introduza a matrícula do veículo.', $normalizada];
    }

    if (strlen($limpo) !== 6) {
        return [false, 'A matrícula deve ter 6 caracteres (ex.: AA-00-AA).', $normalizada];
    }

    if (preg_match('/^(.)\1{5}$/', $limpo)) {
        return [false, 'Matrícula inválida.', $normalizada];
    }

    $formatos = [
        '/^[A-Z]{2}[0-9]{2}[A-Z]{2}$/', // AA-00-AA
        '/^[0-9]{2}[A-Z]{2}[0-9]{2}$/', // 00-AA-00
        '/^[0-9]{2}[0-9]{2}[A-Z]{2}$/', // 00-00-AA
    ];

    foreach ($formatos as $regex) {
        if (preg_match($regex, $limpo)) {
            return [true, '', $normalizada];
        }
    }

    return [false, 'Formato inválido. Use AA-00-AA, 00-AA-00 ou 00-00-AA.', $normalizada];
}

/** @deprecated Use am_normalizar_matricula() */
function normalizarMatricula(string $matricula): string
{
    return am_normalizar_matricula($matricula);
}

if (!function_exists('am_matricula_limpo')) {
    function am_matricula_limpo(string $matricula): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($matricula)));
    }
}

if (!function_exists('am_carro_matricula_existe')) {
    /**
     * Procura veículo com a mesma matrícula (comparação sem hífens, case-insensitive).
     *
     * @return array{id_carro:int,id_utilizador:int,marca:string,modelo:string,matricula:string,nome_utilizador:string}|null
     */
    function am_carro_matricula_existe(mysqli $con, string $matricula, int $excluirIdCarro = 0): ?array
    {
        $limpo = am_matricula_limpo(am_normalizar_matricula($matricula));
        if ($limpo === '') {
            return null;
        }

        $sql = "SELECT c.id_carro, c.id_utilizador, c.marca, c.modelo, c.matricula,
                       COALESCE(u.nome_utilizador, '') AS nome_utilizador
                FROM carro c
                LEFT JOIN utilizador u ON u.id_utilizador = c.id_utilizador
                WHERE REPLACE(UPPER(c.matricula), '-', '') = ?
                  AND TRIM(c.matricula) <> ''";
        if ($excluirIdCarro > 0) {
            $sql .= ' AND c.id_carro <> ?';
        }
        $sql .= ' LIMIT 1';

        $st = $con->prepare($sql);
        if (!$st) {
            return null;
        }
        if ($excluirIdCarro > 0) {
            $st->bind_param('si', $limpo, $excluirIdCarro);
        } else {
            $st->bind_param('s', $limpo);
        }
        if (!$st->execute()) {
            $st->close();

            return null;
        }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return null;
        }

        return [
            'id_carro' => (int) ($row['id_carro'] ?? 0),
            'id_utilizador' => (int) ($row['id_utilizador'] ?? 0),
            'marca' => (string) ($row['marca'] ?? ''),
            'modelo' => (string) ($row['modelo'] ?? ''),
            'matricula' => (string) ($row['matricula'] ?? ''),
            'nome_utilizador' => (string) ($row['nome_utilizador'] ?? ''),
        ];
    }
}

if (!function_exists('am_validar_matricula_unica')) {
    /** @return array{0: bool, 1: string} */
    function am_validar_matricula_unica(mysqli $con, string $matricula, int $excluirIdCarro = 0): array
    {
        [$valida, $msgValidacao, $normalizada] = am_validar_matricula($matricula);
        if (!$valida) {
            return [false, $msgValidacao];
        }

        $dup = am_carro_matricula_existe($con, $normalizada, $excluirIdCarro);
        if ($dup === null) {
            return [true, ''];
        }

        $msg = 'Esta matrícula já está registada noutro veículo';
        $detalhe = trim(($dup['marca'] ?? '') . ' ' . ($dup['modelo'] ?? ''));
        if ($detalhe !== '') {
            $msg .= ' (' . $detalhe . ')';
        }
        $msg .= '.';

        return [false, $msg];
    }
}
