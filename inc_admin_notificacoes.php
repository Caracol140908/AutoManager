<?php

declare(strict_types=1);

require_once __DIR__ . '/inc_tabelas_bd.php';

if (!function_exists('am_notif_meta')) {
  /** @return array{id_col:string,msg_col:string,date_col:string,cols:string[]} */
  function am_notif_meta(mysqli $con): array
  {
    static $meta = null;
    if ($meta !== null) {
      return $meta;
    }
    $cols = am_colunas_tabela($con, 'admin_notificacoes');
    $meta = [
      'cols' => $cols,
      'id_col' => am_primeira_coluna($cols, ['id_notificacao', 'id'], 'id'),
      'msg_col' => am_primeira_coluna($cols, ['mensagem', 'descricao', 'conteudo', 'texto'], 'mensagem'),
      'date_col' => am_primeira_coluna($cols, ['criado_em', 'data_notificacao', 'data_criacao', 'data_envio'], 'criado_em'),
    ];
    return $meta;
  }
}

if (!function_exists('am_notif_ensure_schema')) {
  function am_notif_ensure_schema(mysqli $con): void
  {
    static $done = false;
    if ($done || !am_tabela_existe($con, 'admin_notificacoes')) {
      $done = true;
      return;
    }
    $done = true;
    $cols = am_colunas_tabela($con, 'admin_notificacoes');
    $add = [
      'tipo' => "VARCHAR(50) NOT NULL DEFAULT 'geral'",
      'id_utilizador' => 'INT NULL',
      'id_referencia' => 'INT NULL',
      'remetente_nome' => 'VARCHAR(120) NULL',
      'titulo' => 'VARCHAR(255) NULL',
      'estado' => "VARCHAR(30) NULL DEFAULT 'pendente'",
      'lida' => 'TINYINT(1) NOT NULL DEFAULT 0',
      'payload_json' => 'LONGTEXT NULL',
      'criado_em' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($add as $col => $def) {
      if (!in_array($col, $cols, true)) {
        @$con->query("ALTER TABLE admin_notificacoes ADD COLUMN `$col` $def");
      }
    }
    $meta = am_notif_meta($con);
    if (!in_array($meta['msg_col'], $meta['cols'], true)) {
      @$con->query('ALTER TABLE admin_notificacoes ADD COLUMN `mensagem` TEXT NULL');
    }
  }
}

if (!function_exists('am_notif_decode_payload')) {
  /** @return array<string,mixed> */
  function am_notif_decode_payload(?string $json): array
  {
    if ($json === null || $json === '') {
      return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
  }
}

if (!function_exists('am_notif_insert')) {
  /** @param array<string,mixed> $data */
  function am_notif_insert(mysqli $con, array $data): int
  {
    am_notif_ensure_schema($con);
    if (!am_tabela_existe($con, 'admin_notificacoes')) {
      return 0;
    }
    $meta = am_notif_meta($con);
    $cols = $meta['cols'];
    $payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
    $fields = [];
    $placeholders = [];
    $types = '';
    $values = [];

    $map = [
      'tipo' => (string) ($data['tipo'] ?? 'geral'),
      'id_utilizador' => isset($data['id_utilizador']) ? (int) $data['id_utilizador'] : null,
      'id_referencia' => isset($data['id_referencia']) ? (int) $data['id_referencia'] : null,
      'remetente_nome' => isset($data['remetente_nome']) ? (string) $data['remetente_nome'] : null,
      'titulo' => isset($data['titulo']) ? (string) $data['titulo'] : null,
      'estado' => isset($data['estado']) ? (string) $data['estado'] : 'pendente',
      'lida' => isset($data['lida']) ? (int) $data['lida'] : 0,
    ];
    if (in_array('payload_json', $cols, true)) {
      $map['payload_json'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
    if (in_array($meta['msg_col'], $cols, true)) {
      $map[$meta['msg_col']] = (string) ($data['mensagem'] ?? '');
    }

    foreach ($map as $col => $val) {
      if (!in_array($col, $cols, true)) {
        continue;
      }
      $fields[] = "`$col`";
      $placeholders[] = '?';
      if (is_int($val)) {
        $types .= 'i';
      } else {
        $types .= 's';
        $val = $val === null ? '' : (string) $val;
      }
      $values[] = $val;
    }

    if ($fields === []) {
      return 0;
    }

    $sql = 'INSERT INTO admin_notificacoes (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
      return 0;
    }
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $id = $ok ? (int) $con->insert_id : 0;
    $stmt->close();
    return $id;
  }
}

if (!function_exists('am_notif_row_to_chat')) {
  /** @param array<string,mixed> $row */
  function am_notif_row_to_chat(array $row, array $meta): array
  {
    $payload = am_notif_decode_payload($row['payload_json'] ?? null);
    $tipo = (string) ($row['tipo'] ?? '');
    $isAdmin = ($tipo === 'chat_admin');
    $idCol = $meta['id_col'];
    $dateCol = $meta['date_col'];
    $msgCol = $meta['msg_col'];
    return [
      'id' => (int) ($row[$idCol] ?? 0),
      'de_usuario_id' => $isAdmin ? null : (int) ($row['id_utilizador'] ?? 0),
      'de_usuario_nome' => $row['remetente_nome'] ?? ($isAdmin ? 'Administrador' : ''),
      'para_usuario_id' => (int) ($payload['para_usuario_id'] ?? $row['id_referencia'] ?? $row['id_utilizador'] ?? 0),
      'mensagem' => (string) ($row[$msgCol] ?? ''),
      'data_envio' => (string) ($row[$dateCol] ?? ''),
      'lida_admin' => (int) ($payload['lida_admin'] ?? ($isAdmin ? 1 : (int) ($row['lida'] ?? 0))),
      'lida_usuario' => (int) ($payload['lida_usuario'] ?? ($isAdmin ? 0 : 1)),
      'tipo' => $tipo,
      '_raw' => $row,
    ];
  }
}

if (!function_exists('am_notif_update_payload')) {
  function am_notif_update_payload(mysqli $con, int $id, array $payloadPatch, array $extra = []): bool
  {
    am_notif_ensure_schema($con);
    $meta = am_notif_meta($con);
    $idCol = $meta['id_col'];
    $stmt = $con->prepare("SELECT payload_json, lida FROM admin_notificacoes WHERE `$idCol` = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
      return false;
    }
    $payload = am_notif_decode_payload($row['payload_json'] ?? null);
    foreach ($payloadPatch as $k => $v) {
      $payload[$k] = $v;
    }
    $sets = [];
    $types = '';
    $vals = [];
    if (am_coluna_existe($con, 'admin_notificacoes', 'payload_json')) {
      $sets[] = 'payload_json = ?';
      $types .= 's';
      $vals[] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
    if (isset($extra['lida']) && am_coluna_existe($con, 'admin_notificacoes', 'lida')) {
      $sets[] = 'lida = ?';
      $types .= 'i';
      $vals[] = (int) $extra['lida'];
    }
    if (isset($extra['estado']) && am_coluna_existe($con, 'admin_notificacoes', 'estado')) {
      $sets[] = 'estado = ?';
      $types .= 's';
      $vals[] = (string) $extra['estado'];
    }
    if ($sets === []) {
      return false;
    }
    $sql = 'UPDATE admin_notificacoes SET ' . implode(', ', $sets) . " WHERE `$idCol` = ?";
    $types .= 'i';
    $vals[] = $id;
    $stmt = $con->prepare($sql);
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param($types, ...$vals);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

if (!function_exists('am_notif_list_by_tipo')) {
  /** @return array<int,array<string,mixed>> */
  function am_notif_list_by_tipo(mysqli $con, string $tipo, ?string $order = null): array
  {
    am_notif_ensure_schema($con);
    if (!am_tabela_existe($con, 'admin_notificacoes')) {
      return [];
    }
    $meta = am_notif_meta($con);
    $dateCol = $meta['date_col'];
    $order = $order ?: "`$dateCol` DESC";
    $stmt = $con->prepare("SELECT * FROM admin_notificacoes WHERE tipo = ? ORDER BY $order");
    if (!$stmt) {
      return [];
    }
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
  }
}

if (!function_exists('am_notif_delete')) {
  function am_notif_delete(mysqli $con, int $id): bool
  {
    $meta = am_notif_meta($con);
    $idCol = $meta['id_col'];
    $stmt = $con->prepare("DELETE FROM admin_notificacoes WHERE `$idCol` = ?");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

if (!function_exists('am_feedback_insert')) {
  function am_feedback_insert(mysqli $con, string $nome, string $email, string $tipo, string $assunto, string $mensagem): bool
  {
    return am_notif_insert($con, [
      'tipo' => 'feedback',
      'titulo' => $assunto,
      'mensagem' => $mensagem,
      'estado' => 'pendente',
      'lida' => 0,
      'payload' => [
        'nome' => $nome,
        'email' => $email,
        'tipo_feedback' => $tipo,
        'data_envio' => date('Y-m-d H:i:s'),
      ],
    ]) > 0;
  }
}

if (!function_exists('am_feedback_row_normalize')) {
  /** @param array<string,mixed> $row */
  function am_feedback_row_normalize(array $row, array $meta): array
  {
    $payload = am_notif_decode_payload($row['payload_json'] ?? null);
    $idCol = $meta['id_col'];
    $dateCol = $meta['date_col'];
    $msgCol = $meta['msg_col'];
    return [
      'id' => (int) ($row[$idCol] ?? 0),
      'nome' => (string) ($payload['nome'] ?? ''),
      'email' => (string) ($payload['email'] ?? ''),
      'tipo' => (string) ($payload['tipo_feedback'] ?? 'duvida'),
      'assunto' => (string) ($row['titulo'] ?? ''),
      'mensagem' => (string) ($row[$msgCol] ?? ''),
      'estado' => (string) ($row['estado'] ?? 'pendente'),
      'data_envio' => (string) ($payload['data_envio'] ?? $row[$dateCol] ?? ''),
      'data_resposta' => (string) ($payload['data_resposta'] ?? ''),
      'resposta' => (string) ($payload['resposta'] ?? ''),
    ];
  }
}

if (!function_exists('am_feedback_list')) {
  /** @return array<int,array<string,mixed>> */
  function am_feedback_list(mysqli $con): array
  {
    $meta = am_notif_meta($con);
    $rows = am_notif_list_by_tipo($con, 'feedback');
    $out = [];
    foreach ($rows as $row) {
      $out[] = am_feedback_row_normalize($row, $meta);
    }
    return $out;
  }
}

if (!function_exists('am_feedback_count_pendentes')) {
  function am_feedback_count_pendentes(mysqli $con): int
  {
    $n = 0;
    foreach (am_feedback_list($con) as $fb) {
      if (($fb['estado'] ?? '') === 'pendente') {
        $n++;
      }
    }
    return $n;
  }
}

if (!function_exists('am_feedback_update_estado')) {
  function am_feedback_update_estado(mysqli $con, int $id, string $estado): bool
  {
    $meta = am_notif_meta($con);
    $idCol = $meta['id_col'];
    $stmt = $con->prepare("SELECT payload_json FROM admin_notificacoes WHERE `$idCol` = ? AND tipo = 'feedback' LIMIT 1");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
      return false;
    }
    $payload = am_notif_decode_payload($row['payload_json'] ?? null);
    $payload['data_resposta'] = date('Y-m-d H:i:s');
    return am_notif_update_payload($con, $id, $payload, ['estado' => $estado]);
  }
}

if (!function_exists('am_desbloqueio_pendente')) {
  function am_desbloqueio_pendente(mysqli $con, int $idUtilizador): bool
  {
    foreach (am_notif_list_by_tipo($con, 'desbloqueio') as $row) {
      if ((int) ($row['id_utilizador'] ?? 0) === $idUtilizador && ($row['estado'] ?? '') === 'pendente') {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('am_desbloqueio_insert')) {
  function am_desbloqueio_insert(mysqli $con, int $idUtilizador, string $email, string $nome, string $motivo, string $mensagem): bool
  {
    if (am_desbloqueio_pendente($con, $idUtilizador)) {
      return false;
    }
    return am_notif_insert($con, [
      'tipo' => 'desbloqueio',
      'id_utilizador' => $idUtilizador,
      'titulo' => $motivo,
      'mensagem' => $mensagem,
      'estado' => 'pendente',
      'payload' => [
        'email' => $email,
        'nome_utilizador' => $nome,
        'motivo' => $motivo,
        'data_pedido' => date('Y-m-d H:i:s'),
      ],
    ]) > 0;
  }
}

if (!function_exists('am_avaliacao_temp_ja_feita')) {
  function am_avaliacao_temp_ja_feita(mysqli $con, ?string $email, ?string $ip): bool
  {
    foreach (am_notif_list_by_tipo($con, 'avaliacao_temp') as $row) {
      $p = am_notif_decode_payload($row['payload_json'] ?? null);
      if ($email !== null && $email !== '' && isset($p['email']) && strcasecmp((string) $p['email'], $email) === 0) {
        return true;
      }
      if ($ip !== null && $ip !== '' && isset($p['ip_address']) && (string) $p['ip_address'] === $ip) {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('am_avaliacao_temp_insert')) {
  function am_avaliacao_temp_insert(mysqli $con, string $nome, string $email, int $estrelas, string $comentario, string $ip): bool
  {
    return am_notif_insert($con, [
      'tipo' => 'avaliacao_temp',
      'mensagem' => $comentario,
      'estado' => 'registada',
      'payload' => [
        'nome' => $nome,
        'email' => $email,
        'avaliacao' => $estrelas,
        'comentario' => $comentario,
        'ip_address' => $ip,
        'data_avaliacao' => date('Y-m-d H:i:s'),
      ],
    ]) > 0;
  }
}

if (!function_exists('am_presenca_get')) {
  /** @return array<string,mixed>|null */
  function am_presenca_get(mysqli $con, int $mecanicoId, string $data): ?array
  {
    $titulo = 'presenca_' . $mecanicoId . '_' . $data;
    foreach (am_notif_list_by_tipo($con, 'presenca', 'titulo ASC') as $row) {
      if (($row['titulo'] ?? '') === $titulo) {
        $p = am_notif_decode_payload($row['payload_json'] ?? null);
        $meta = am_notif_meta($con);
        return [
          'id_presenca' => (int) ($row[$meta['id_col']] ?? 0),
          'id_mecanico' => $mecanicoId,
          'data' => $data,
          'hora_entrada' => $p['hora_entrada'] ?? null,
          'hora_saida' => $p['hora_saida'] ?? null,
          'total_horas' => $p['total_horas'] ?? null,
          'timestamp_entrada' => $p['timestamp_entrada'] ?? null,
          'timestamp_saida' => $p['timestamp_saida'] ?? null,
        ];
      }
    }
    return null;
  }
}

if (!function_exists('am_presenca_historico')) {
  /** @return array<int,array<string,mixed>> */
  function am_presenca_historico(mysqli $con, int $mecanicoId, string $dataInicio): array
  {
    $out = [];
    foreach (am_notif_list_by_tipo($con, 'presenca') as $row) {
      if ((int) ($row['id_utilizador'] ?? 0) !== $mecanicoId) {
        continue;
      }
      $titulo = (string) ($row['titulo'] ?? '');
      if (strpos($titulo, 'presenca_' . $mecanicoId . '_') !== 0) {
        continue;
      }
      $data = substr($titulo, strlen('presenca_' . $mecanicoId . '_'));
      if ($data < $dataInicio) {
        continue;
      }
      $p = am_notif_decode_payload($row['payload_json'] ?? null);
      $out[] = [
        'data' => $data,
        'hora_entrada' => $p['hora_entrada'] ?? null,
        'hora_saida' => $p['hora_saida'] ?? null,
        'total_horas' => $p['total_horas'] ?? null,
        'falta' => $p['falta'] ?? 0,
        'justificacao' => $p['justificacao'] ?? null,
      ];
    }
    usort($out, static function ($a, $b) {
      return strcmp((string) $b['data'], (string) $a['data']);
    });
    return $out;
  }
}

if (!function_exists('am_presenca_save')) {
  /** @param array<string,mixed> $dados */
  function am_presenca_save(mysqli $con, int $mecanicoId, string $data, array $dados): bool
  {
    $titulo = 'presenca_' . $mecanicoId . '_' . $data;
    $existing = am_presenca_get($con, $mecanicoId, $data);
    $payload = array_merge([
      'hora_entrada' => null,
      'hora_saida' => null,
      'total_horas' => null,
      'timestamp_entrada' => null,
      'timestamp_saida' => null,
    ], $dados);
    if ($existing && !empty($existing['id_presenca'])) {
      return am_notif_update_payload($con, (int) $existing['id_presenca'], $payload);
    }
    return am_notif_insert($con, [
      'tipo' => 'presenca',
      'id_utilizador' => $mecanicoId,
      'titulo' => $titulo,
      'mensagem' => 'Registo de presença',
      'estado' => 'ativo',
      'payload' => $payload,
    ]) > 0;
  }
}
