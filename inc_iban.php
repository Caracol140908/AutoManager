<?php

declare(strict_types=1);

/**
 * Validação de IBAN (ISO 13616) — foco em IBAN português (PT, 25 caracteres).
 */

if (!function_exists('am_iban_comprimentos_pais')) {
  /** @return array<string,int> */
  function am_iban_comprimentos_pais(): array
  {
    return [
      'PT' => 25,
      'ES' => 24,
      'FR' => 27,
      'DE' => 22,
      'IT' => 27,
      'BE' => 16,
      'NL' => 18,
      'LU' => 20,
      'IE' => 22,
      'AT' => 20,
      'CH' => 21,
      'GB' => 22,
    ];
  }
}

if (!function_exists('am_iban_comprimento_pais')) {
  function am_iban_comprimento_pais(string $limpo): ?int
  {
    if (strlen($limpo) < 2 || !preg_match('/^[A-Z]{2}/', $limpo)) {
      return null;
    }

    $pais = substr($limpo, 0, 2);
    $comprimentos = am_iban_comprimentos_pais();

    return $comprimentos[$pais] ?? null;
  }
}

if (!function_exists('am_normalizar_iban')) {
  function am_normalizar_iban(string $iban): string
  {
    $limpo = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($iban)));
    if ($limpo === '') {
      return '';
    }

    $max = am_iban_comprimento_pais($limpo) ?? 34;

    return substr($limpo, 0, $max);
  }
}

if (!function_exists('am_formatar_iban')) {
  function am_formatar_iban(string $iban): string
  {
    $limpo = am_normalizar_iban($iban);
    if ($limpo === '') {
      return '';
    }

    return trim(chunk_split($limpo, 4, ' '));
  }
}

if (!function_exists('am_iban_mod97')) {
  function am_iban_mod97(string $iban): bool
  {
    $iban = am_normalizar_iban($iban);
    if (strlen($iban) < 5) {
      return false;
    }

    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';

    $len = strlen($rearranged);
    for ($i = 0; $i < $len; $i++) {
      $ch = $rearranged[$i];
      if ($ch >= '0' && $ch <= '9') {
        $numeric .= $ch;
      } elseif ($ch >= 'A' && $ch <= 'Z') {
        $numeric .= (string) (ord($ch) - 55);
      } else {
        return false;
      }
    }

    $remainder = 0;
    $numLen = strlen($numeric);
    for ($i = 0; $i < $numLen; $i++) {
      $remainder = ($remainder * 10 + (int) $numeric[$i]) % 97;
    }

    return $remainder === 1;
  }
}

if (!function_exists('am_mensagem_comprimento_iban')) {
  function am_mensagem_comprimento_iban(string $pais, int $atual, int $esperado): string
  {
    $exemplo = $pais === 'PT' ? 'PT50 0000 0000 0000 0000 0000 0' : '';

    if ($atual > $esperado) {
      $msg = $pais === 'PT'
        ? 'IBAN português demasiado longo (' . $atual . ' caracteres, máximo ' . $esperado . ').'
        : "IBAN de {$pais} demasiado longo ({$atual} caracteres, máximo {$esperado}).";
    } else {
      $msg = $pais === 'PT'
        ? 'IBAN português incompleto (' . $atual . '/' . $esperado . ' caracteres).'
        : "IBAN de {$pais} incompleto ({$atual}/{$esperado} caracteres).";
    }

    if ($exemplo !== '') {
      $msg .= ' Formato: ' . $exemplo . '.';
    }

    return $msg;
  }
}

if (!function_exists('am_validar_iban_portugues')) {
  /** @return array{0:bool,1:string} */
  function am_validar_iban_portugues(string $iban): array
  {
    if (!preg_match('/^PT\d{23}$/', $iban)) {
      return [false, 'IBAN português inválido. Após PT50 devem seguir 23 dígitos (NIB).'];
    }

    $banco = substr($iban, 4, 4);
    if ($banco === '0000' || !ctype_digit($banco)) {
      return [false, 'Código de banco inválido no IBAN português.'];
    }

    return [true, ''];
  }
}

if (!function_exists('am_validar_iban')) {
  /** @return array{0:bool,1:string} */
  function am_validar_iban(string $iban, bool $parcial = false): array
  {
    $iban = am_normalizar_iban($iban);
    if ($iban === '') {
      return [true, ''];
    }

    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
      return [false, 'Formato de IBAN inválido. Comece por PT50 e use apenas letras e números.'];
    }

    $pais = substr($iban, 0, 2);
    $comprimentos = am_iban_comprimentos_pais();
    $esperado = $comprimentos[$pais] ?? null;

    if ($esperado !== null) {
      if (strlen($iban) < $esperado) {
        if ($parcial) {
          return [true, ''];
        }

        return [false, am_mensagem_comprimento_iban($pais, strlen($iban), $esperado)];
      }
      if (strlen($iban) !== $esperado) {
        return [false, am_mensagem_comprimento_iban($pais, strlen($iban), $esperado)];
      }
    } elseif (strlen($iban) < 15 || strlen($iban) > 34) {
      return [false, 'Comprimento de IBAN inválido.'];
    }

    if ($pais === 'PT') {
      [$ptOk, $ptMsg] = am_validar_iban_portugues($iban);
      if (!$ptOk) {
        return [false, $ptMsg];
      }
    }

    if (!am_iban_mod97($iban)) {
      return [false, 'IBAN inválido (dígitos de controlo incorretos). Verifique todos os números.'];
    }

    return [true, ''];
  }
}

if (!function_exists('am_tabela_tem_coluna_iban')) {
  function am_tabela_tem_coluna_iban(mysqli $con, string $tabela): bool
  {
    static $cache = [];
    if (isset($cache[$tabela])) {
      return $cache[$tabela];
    }

    $ok = false;
    $res = @$con->query("SHOW COLUMNS FROM `$tabela` LIKE 'iban'");
    if ($res && $res->num_rows > 0) {
      $ok = true;
      $cache[$tabela] = true;
    }

    return $ok;
  }
}

if (!function_exists('am_garantir_iban_unico_schema')) {
  function am_garantir_iban_unico_schema(mysqli $con): void
  {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;

    foreach (['mecanicos', 'chefes_oficina', 'orcamentista'] as $tabela) {
      $res = @$con->query("SHOW TABLES LIKE '$tabela'");
      if (!$res || $res->num_rows === 0) {
        continue;
      }

      if (!am_tabela_tem_coluna_iban($con, $tabela)) {
        @$con->query("ALTER TABLE `$tabela` ADD COLUMN iban VARCHAR(34) NULL");
      }

      @$con->query("UPDATE `$tabela` SET iban = NULL WHERE iban IS NOT NULL AND TRIM(iban) = ''");

      $indice = 'uk_' . $tabela . '_iban';
      $idx = @$con->query("SHOW INDEX FROM `$tabela` WHERE Key_name = '$indice'");
      if ($idx && $idx->num_rows === 0) {
        @$con->query("ALTER TABLE `$tabela` ADD UNIQUE KEY `$indice` (iban)");
      }
    }
  }
}

if (!function_exists('am_iban_existe_no_sistema')) {
  /**
   * @param array{utilizador?:int,mecanicos?:int,orcamentista?:int,chefes_oficina?:int} $excluir
   * @return array{origem:string,id:int,nome:string}|null
   */
  function am_iban_existe_no_sistema(mysqli $con, string $iban, array $excluir = []): ?array
  {
    am_garantir_iban_unico_schema($con);

    $iban = am_normalizar_iban($iban);
    if ($iban === '') {
      return null;
    }

    $mapas = [
      ['tabela' => 'mecanicos', 'id' => 'id_mecanico', 'nome' => 'nome_mecanico', 'chave' => 'mecanicos'],
      ['tabela' => 'chefes_oficina', 'id' => 'id_chefe', 'nome' => 'nome', 'chave' => 'chefes_oficina'],
      ['tabela' => 'orcamentista', 'id' => 'id_orcamentista', 'nome' => 'nome_orcamentista', 'chave' => 'orcamentista'],
    ];

    $ibanSqlExpr = "UPPER(REPLACE(REPLACE(REPLACE(iban, ' ', ''), '-', ''), '.', ''))";

    foreach ($mapas as $mapa) {
      $tabela = $mapa['tabela'];
      if (!am_tabela_tem_coluna_iban($con, $tabela)) {
        continue;
      }

      $excludeId = (int) ($excluir[$mapa['chave']] ?? 0);
      $sql = "SELECT {$mapa['id']} AS id_ref, {$mapa['nome']} AS nome_ref FROM `$tabela`"
          . " WHERE {$ibanSqlExpr} = ? AND iban IS NOT NULL AND TRIM(iban) <> ''";
      if ($excludeId > 0) {
        $sql .= " AND {$mapa['id']} <> ?";
      }
      $sql .= ' LIMIT 1';

      $stmt = $con->prepare($sql);
      if (!$stmt) {
        continue;
      }

      if ($excludeId > 0) {
        $stmt->bind_param('si', $iban, $excludeId);
      } else {
        $stmt->bind_param('s', $iban);
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

if (!function_exists('am_validar_iban_unico')) {
  /** @return array{0:bool,1:string} */
  function am_validar_iban_unico(mysqli $con, string $iban, array $excluir = []): array
  {
    $iban = am_normalizar_iban($iban);
    if ($iban === '') {
      return [true, ''];
    }

    [$valido, $msgValidacao] = am_validar_iban($iban);
    if (!$valido) {
      return [false, $msgValidacao];
    }

    $dup = am_iban_existe_no_sistema($con, $iban, $excluir);
    if ($dup === null) {
      return [true, ''];
    }

    $msg = 'Este IBAN já está registado no sistema';
    if ($dup['nome'] !== '') {
      $msg .= ' (' . $dup['nome'] . ')';
    }
    $origem = function_exists('am_etiqueta_origem_registo')
      ? am_etiqueta_origem_registo((string) ($dup['origem'] ?? ''))
      : '';
    if ($origem !== '') {
      $msg .= ' — já usado por um ' . $origem;
    }
    $msg .= '.';

    return [false, $msg];
  }
}
