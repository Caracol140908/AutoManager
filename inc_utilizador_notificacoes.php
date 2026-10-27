<?php
/**
 * Notificações do cliente (orçamento, pagamento, pronto, trabalho em curso, concluído).
 * Devolve contagem para badge e "pulse" para atualização em tempo quase real.
 *
 * Os serviços podem estar ligados por id_utilizador e/ou pelo mesmo email do utilizador
 * (as marcações em index.php gravam email e nem sempre preenchiam id_utilizador).
 *
 * Quando o utilizador abre Configurações, usar $marcar_vistas = true para gravar o pulse
 * atual em utilizador.notif_cliente_pulse_ack — o badge deixa de contar até o estado mudar.
 *
 * @return array{
 *   count:int,
 *   pulse:string,
 *   sections:array{dados:int,veiculos:int,marcacoes:int},
 *   section_pulses:array{dados:string,veiculos:string,marcacoes:string},
 *   raw_count:int
 * }
 */
if (!function_exists('am_garantir_coluna_notif_cliente_pulse_ack')) {
    function am_garantir_coluna_notif_cliente_pulse_ack(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $r = @$con->query("SHOW COLUMNS FROM utilizador LIKE 'notif_cliente_pulse_ack'");
        if ($r && $r->num_rows === 0) {
            @$con->query("ALTER TABLE utilizador ADD COLUMN notif_cliente_pulse_ack VARCHAR(32) NULL DEFAULT NULL");
        }
    }
}

if (!function_exists('am_servico_marcacao_cancelada_notif')) {
    function am_servico_marcacao_cancelada_notif(array $row): bool
    {
        if (isset($row['estado_marcacao'])) {
            if (strtolower(trim((string) ($row['estado_marcacao'] ?? 'ativa'))) === 'cancelada') {
                return true;
            }
        }
        $estado = strtolower(trim((string) ($row['estado_do_trabalho'] ?? '')));

        return strpos($estado, 'cancel') !== false;
    }
}

if (!function_exists('am_servico_notificacao_cliente_tipo')) {
    /**
     * Tipo de notificação acionável para o cliente (null = nada a fazer).
     *
     * @return null|'orcamento_pendente'|'pagamento_pendente'|'pagamento_complemento'|'carro_pronto'
     */
    function am_servico_notificacao_cliente_tipo(array $row): ?string
    {
        if (am_servico_marcacao_cancelada_notif($row)) {
            return null;
        }

        $orcCliente = strtolower(trim((string) ($row['estado_orcamento_cliente'] ?? '')));
        $orcEstado = strtolower(trim((string) ($row['estado_orcamento'] ?? '')));
        $orcAprovado = $orcEstado === 'aprovado' || !empty($row['orcamento_aprovado']);
        $estadoTrab = strtolower(trim((string) ($row['estado_do_trabalho'] ?? '')));
        $estadoPag = strtolower(trim((string) ($row['estado_pagamento'] ?? 'pendente')));

        if ($orcAprovado && $orcCliente === 'pendente') {
            return 'orcamento_pendente';
        }

        if (in_array($orcCliente, ['aceite', 'aceito'], true)) {
            static $faturaLoaded = false;
            if (!$faturaLoaded) {
                require_once __DIR__ . '/inc_fatura_intervencao.php';
                require_once __DIR__ . '/inc_excesso_orcamento.php';
                $faturaLoaded = true;
            }

            if (am_servico_excesso_orcamento_pendente($row)) {
                return 'excesso_orcamento';
            }

            if (am_servico_excesso_orcamento_recusado($row)) {
                return null;
            }

            if (am_servico_esta_concluido($row)) {
                if ($estadoPag === 'pendente_complemento') {
                    return 'pagamento_complemento';
                }

                if (am_servico_aguarda_pagamento_trabalho($row)) {
                    return 'pagamento_pendente';
                }
            }
        }

        if ($estadoTrab === 'pronto') {
            return 'carro_pronto';
        }

        return null;
    }
}

if (!function_exists('am_servico_notificacao_cliente_label')) {
    function am_servico_notificacao_cliente_label($tipo): string
    {
        $labels = [
            'orcamento_pendente' => 'Orçamento por responder',
            'excesso_orcamento' => 'Total acima do orçamento — confirme',
            'pagamento_pendente' => 'Pagamento pendente',
            'pagamento_complemento' => 'Pagamento complementar',
            'carro_pronto' => 'Veículo pronto para levantar',
        ];

        return $labels[$tipo] ?? '';
    }
}

if (!function_exists('am_utilizador_notificacoes_metrics')) {
    function am_utilizador_notificacoes_metrics(mysqli $con, int $id_utilizador, bool $marcar_vistas = false): array
    {
        $out = [
            'count' => 0,
            'pulse' => '',
            'raw_count' => 0,
            'sections' => [
                'dados' => 0,
                'veiculos' => 0,
                'marcacoes' => 0,
            ],
            'section_pulses' => [
                'dados' => '',
                'veiculos' => '',
                'marcacoes' => '',
            ],
            'tipos' => [
                'orcamento_pendente' => 0,
                'excesso_orcamento' => 0,
                'pagamento_pendente' => 0,
                'pagamento_complemento' => 0,
                'carro_pronto' => 0,
            ],
        ];

        am_garantir_coluna_notif_cliente_pulse_ack($con);

        $colunas_servico = [];
        $cols_stmt = $con->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'servico'
    ");
        if ($cols_stmt) {
            $cols_stmt->execute();
            $cols_result = $cols_stmt->get_result();
            if ($cols_result) {
                while ($col = $cols_result->fetch_assoc()) {
                    if (!empty($col['COLUMN_NAME'])) {
                        $colunas_servico[$col['COLUMN_NAME']] = true;
                    }
                }
            }
            $cols_stmt->close();
        }

        $userEmailNorm = '';
        if ($id_utilizador > 0) {
            $ue = $con->prepare('SELECT LOWER(TRIM(email)) AS e FROM utilizador WHERE id_utilizador = ? LIMIT 1');
            if ($ue) {
                $ue->bind_param('i', $id_utilizador);
                if ($ue->execute()) {
                    $uer = $ue->get_result();
                    if ($uer && ($urow = $uer->fetch_assoc())) {
                        $userEmailNorm = (string) ($urow['e'] ?? '');
                    }
                }
                $ue->close();
            }
        }

        $has_uid_col = isset($colunas_servico['id_utilizador']);
        $has_email_col = isset($colunas_servico['email']);

        $ownerPulse = '0=1';
        $ownerTypes = '';
        $ownerVals = [];

        if ($has_uid_col && $has_email_col && $userEmailNorm !== '') {
            $ownerPulse = '(s.id_utilizador = ? OR LOWER(TRIM(COALESCE(s.email, \'\'))) = ?)';
            $ownerTypes = 'is';
            $ownerVals = [$id_utilizador, $userEmailNorm];
        } elseif ($has_email_col && $userEmailNorm !== '') {
            $ownerPulse = 'LOWER(TRIM(COALESCE(s.email, \'\'))) = ?';
            $ownerTypes = 's';
            $ownerVals = [$userEmailNorm];
        } elseif ($has_uid_col) {
            $ownerPulse = 's.id_utilizador = ?';
            $ownerTypes = 'i';
            $ownerVals = [$id_utilizador];
        }

        $notifPorServico = 0;
        $marcacoesParts = [];
        $notifTipos = [
            'orcamento_pendente' => 0,
            'excesso_orcamento' => 0,
            'pagamento_pendente' => 0,
            'pagamento_complemento' => 0,
            'carro_pronto' => 0,
        ];

        $sel = ['s.id'];
        foreach (['estado_do_trabalho', 'estado_orcamento_cliente', 'estado_excesso_orcamento_cliente', 'estado_pagamento', 'estado_orcamento', 'orcamento_aprovado', 'estado', 'data_conclusao', 'data_orcamento_aceite', 'estado_marcacao', 'valor_em_divida', 'valor_pago_total', 'valor_total_com_iva', 'fatura_linhas_json', 'horas_trabalhadas', 'valor_hora', 'pecas_substituidas', 'numero_fatura'] as $c) {
            if (isset($colunas_servico[$c])) {
                $sel[] = 's.' . $c;
            }
        }

        if ($ownerTypes !== '') {
            $sqlPulse = 'SELECT ' . implode(', ', $sel) . ' FROM servico s WHERE (' . $ownerPulse . ') ORDER BY s.id ASC';
            $pstmt = $con->prepare($sqlPulse);
            if ($pstmt) {
                if ($ownerTypes === 'is') {
                    $pid = (int) $ownerVals[0];
                    $pem = (string) $ownerVals[1];
                    $pstmt->bind_param('is', $pid, $pem);
                } elseif ($ownerTypes === 's') {
                    $pem = (string) $ownerVals[0];
                    $pstmt->bind_param('s', $pem);
                } elseif ($ownerTypes === 'i') {
                    $pid = (int) $ownerVals[0];
                    $pstmt->bind_param('i', $pid);
                }
                $pstmt->execute();
                $pr = $pstmt->get_result();
                $parts = [];
                if ($pr) {
                    while ($row = $pr->fetch_assoc()) {
                        $rowSig = implode(':', array_map('strval', $row));
                        $parts[] = $rowSig;
                        $marcacoesParts[] = $rowSig;

                        $tipoNotif = am_servico_notificacao_cliente_tipo($row);
                        if ($tipoNotif !== null) {
                            $notifPorServico++;
                            if (isset($notifTipos[$tipoNotif])) {
                                $notifTipos[$tipoNotif]++;
                            }
                        }
                    }
                }
                $pstmt->close();
                $out['pulse'] = md5(implode('|', $parts));
            }
        }

        $out['count'] = $notifPorServico;
        $out['raw_count'] = $notifPorServico;
        $out['tipos'] = $notifTipos;

        if ($id_utilizador > 0) {
            require_once __DIR__ . '/inc_veiculo_perfil.php';
            am_ensure_veiculo_perfil_tables($con);
            $pendIds = am_veiculo_perfil_pedidos_pendentes_ids($con, $id_utilizador);
            if ($pendIds !== []) {
                $out['count'] = (int) $out['count'] + count($pendIds);
                $out['raw_count'] = (int) $out['raw_count'] + count($pendIds);
                $out['pulse'] = md5((string) $out['pulse'] . '|pfped:' . implode(',', $pendIds));
            }
        } else {
            $pendIds = [];
        }

        $pulseStr = (string) $out['pulse'];
        $businessCount = (int) $out['count'];
        $out['sections']['veiculos'] = (int) count($pendIds);
        $out['sections']['marcacoes'] = max(0, (int) $out['raw_count'] - (int) $out['sections']['veiculos']);
        $out['section_pulses']['dados'] = '';
        $out['section_pulses']['veiculos'] = md5('veiculos|' . implode(',', $pendIds ?? []));
        $out['section_pulses']['marcacoes'] = md5('marcacoes|' . implode('|', $marcacoesParts));
        $businessCount = (int) $out['count'];

        if ($id_utilizador > 0) {
            if ($marcar_vistas) {
                $upAck = $con->prepare('UPDATE utilizador SET notif_cliente_pulse_ack = ? WHERE id_utilizador = ?');
                if ($upAck) {
                    $upAck->bind_param('si', $pulseStr, $id_utilizador);
                    $upAck->execute();
                    $upAck->close();
                }
                $out['count'] = 0;

                return $out;
            }

            $ack = null;
            $ackStmt = $con->prepare('SELECT notif_cliente_pulse_ack FROM utilizador WHERE id_utilizador = ? LIMIT 1');
            if ($ackStmt) {
                $ackStmt->bind_param('i', $id_utilizador);
                if ($ackStmt->execute()) {
                    $ackRes = $ackStmt->get_result();
                    if ($ackRes && ($ackRow = $ackRes->fetch_assoc())) {
                        $v = $ackRow['notif_cliente_pulse_ack'] ?? null;
                        $ack = ($v !== null && $v !== '') ? (string) $v : null;
                    }
                }
                $ackStmt->close();
            }
            if ($ack !== null && $ack === $pulseStr) {
                $out['count'] = 0;
            } else {
                $out['count'] = $businessCount;
            }
        }

        return $out;
    }
}

if (!function_exists('am_utilizador_marcar_notificacoes_lidas')) {
    /**
     * Marca o estado atual de notificações como visto (desaparecem até haver alterações novas).
     *
     * @return array<string, mixed>
     */
    function am_utilizador_marcar_notificacoes_lidas(mysqli $con, int $id_utilizador): array
    {
        return am_utilizador_notificacoes_metrics($con, $id_utilizador, true);
    }
}
