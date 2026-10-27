<?php

declare(strict_types=1);

require_once __DIR__ . '/inc_tabelas_bd.php';

if (!function_exists('am_cfg_ensure_kv')) {
  function am_cfg_ensure_kv(mysqli $con, string $tabela): void
  {
    if (!am_tabela_existe($con, $tabela)) {
      return;
    }
    $cols = am_colunas_tabela($con, $tabela);
    if (!in_array('chave', $cols, true)) {
      @$con->query("ALTER TABLE `$tabela` ADD COLUMN `chave` VARCHAR(100) NULL");
    }
    if (!in_array('valor', $cols, true)) {
      @$con->query("ALTER TABLE `$tabela` ADD COLUMN `valor` LONGTEXT NULL");
    }
    if (!in_array('atualizado_em', $cols, true)) {
      @$con->query("ALTER TABLE `$tabela` ADD COLUMN `atualizado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
  }
}

if (!function_exists('am_cfg_get')) {
  function am_cfg_get(mysqli $con, string $chave, string $default = ''): string
  {
    am_cfg_ensure_kv($con, 'configuracoes');
    if (!am_tabela_existe($con, 'configuracoes')) {
      return $default;
    }
    if (am_coluna_existe($con, 'configuracoes', 'chave')) {
      $stmt = $con->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
      if ($stmt) {
        $stmt->bind_param('s', $chave);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['valor'])) {
          return (string) $row['valor'];
        }
      }
    }
    return $default;
  }
}

if (!function_exists('am_cfg_set')) {
  function am_cfg_set(mysqli $con, string $chave, string $valor): bool
  {
    am_cfg_ensure_kv($con, 'configuracoes');
    if (!am_tabela_existe($con, 'configuracoes') || !am_coluna_existe($con, 'configuracoes', 'chave')) {
      return false;
    }
    $stmt = $con->prepare('SELECT chave FROM configuracoes WHERE chave = ? LIMIT 1');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('s', $chave);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($exists) {
      $stmt = $con->prepare('UPDATE configuracoes SET valor = ?, atualizado_em = NOW() WHERE chave = ?');
      if (!$stmt) {
        return false;
      }
      $stmt->bind_param('ss', $valor, $chave);
      $ok = $stmt->execute();
      $stmt->close();
      return $ok;
    }
    $stmt = $con->prepare('INSERT INTO configuracoes (chave, valor, atualizado_em) VALUES (?, ?, NOW())');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('ss', $chave, $valor);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

if (!function_exists('am_site_banner_get')) {
  /** @return array{id:int,mensagem:string,data_publicacao:string}|null */
  function am_site_banner_get(mysqli $con): ?array
  {
    if (!am_tabela_existe($con, 'configuracoes_site')) {
      return null;
    }
    $cols = am_colunas_tabela($con, 'configuracoes_site');
    if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
      $chave = 'banner_atualizacao';
      $stmt = $con->prepare('SELECT valor, atualizado_em FROM configuracoes_site WHERE chave = ? LIMIT 1');
      if ($stmt) {
        $stmt->bind_param('s', $chave);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['valor'])) {
          $data = json_decode((string) $row['valor'], true);
          if (is_array($data) && !empty($data['mensagem'])) {
            return [
              'id' => 1,
              'mensagem' => (string) $data['mensagem'],
              'data_publicacao' => (string) ($data['data_publicacao'] ?? $row['atualizado_em'] ?? date('Y-m-d H:i:s')),
            ];
          }
        }
      }
    }
    if (in_array('mensagem', $cols, true)) {
      $dateCol = am_primeira_coluna($cols, ['data_publicacao', 'atualizado_em', 'criado_em'], 'id');
      $idCol = am_primeira_coluna($cols, ['id', 'id_config'], 'id');
      $res = $con->query("SELECT `$idCol` AS id, mensagem, `$dateCol` AS data_publicacao FROM configuracoes_site ORDER BY `$idCol` DESC LIMIT 1");
      if ($res && ($row = $res->fetch_assoc()) && !empty($row['mensagem'])) {
        return [
          'id' => (int) ($row['id'] ?? 1),
          'mensagem' => (string) $row['mensagem'],
          'data_publicacao' => (string) ($row['data_publicacao'] ?? date('Y-m-d H:i:s')),
        ];
      }
    }
    return null;
  }
}

if (!function_exists('am_site_banner_mark_seen')) {
  function am_site_banner_mark_seen(mysqli $con, int $userId, string $dataPublicacao): void
  {
    if ($userId <= 0) {
      return;
    }
    $key = 'banner_vistos';
    $raw = am_cfg_get($con, $key, '{}');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      $data = [];
    }
    $data[(string) $userId] = $dataPublicacao;
    am_cfg_set($con, $key, json_encode($data, JSON_UNESCAPED_UNICODE));
  }
}

if (!function_exists('am_site_banner_user_saw')) {
  function am_site_banner_user_saw(mysqli $con, int $userId, string $dataPublicacao): bool
  {
    if ($userId <= 0) {
      return false;
    }
    $raw = am_cfg_get($con, 'banner_vistos', '{}');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      return false;
    }
    return isset($data[(string) $userId]) && (string) $data[(string) $userId] === $dataPublicacao;
  }
}
