<?php
/**
 * E-mail ao cliente (mail() nativo), alinhado com index.php (AUTOMANAGER).
 */
if (!function_exists('am_enviar_email_cliente_automanager')) {
    function am_enviar_email_cliente_automanager(string $toEmail, string $nomeDestinatario, string $assunto, string $htmlBodyFragment): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $nomeEsc = htmlspecialchars($nomeDestinatario !== '' ? $nomeDestinatario : 'Cliente', ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#333;line-height:1.5">';
        $html .= '<p>Olá ' . $nomeEsc . ',</p>';
        $html .= $htmlBodyFragment;
        $html .= '<p style="margin-top:24px;color:#999;font-size:12px;">Obrigado pela sua preferência.<br><strong>AUTOMANAGER</strong></p>';
        $html .= '</body></html>';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: AUTOMANAGER <no-reply@AUTOMANAGER.pt>\r\n";
        return (bool) @mail($toEmail, $assunto, $html, $headers);
    }
}

if (!function_exists('am_notificar_cliente_trabalho_concluido')) {
    /**
     * E-mail ao cliente quando o serviço fica concluído (mecânico / mesmo fluxo que atualizar_estado_reparacao).
     *
     * @param string $idColumn Apenas 'id' ou 'id_servico' (nome de coluna na tabela servico).
     */
    function am_notificar_cliente_trabalho_concluido(mysqli $con, string $servicoRef, string $idColumn = 'id'): void
    {
        if ($idColumn !== 'id' && $idColumn !== 'id_servico') {
            return;
        }
        if (!function_exists('am_pecas_substituidas_parse')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }
        am_ensure_servico_fecho_cols($con);

        $sql = "SELECT * FROM servico WHERE $idColumn = ? LIMIT 1";
        $st = $con->prepare($sql);
        if (!$st) {
            return;
        }
        $st->bind_param('s', $servicoRef);
        if (!$st->execute()) {
            $st->close();
            return;
        }
        $res = $st->get_result();
        $srv = $res ? $res->fetch_assoc() : null;
        $st->close();
        if (empty($srv['email'])) {
            return;
        }
        $nomeC = trim((string) ($srv['nome'] ?? ''));
        $tipo = htmlspecialchars(trim((string) ($srv['tipo_servico'] ?? '')), ENT_QUOTES, 'UTF-8');
        $mat = htmlspecialchars(trim((string) ($srv['matricula'] ?? '')), ENT_QUOTES, 'UTF-8');
        $lista = '';
        if ($tipo !== '' || $mat !== '') {
            $lista = '<ul style="padding-left:20px">';
            if ($tipo !== '') {
                $lista .= '<li><strong>Serviço:</strong> ' . $tipo . '</li>';
            }
            if ($mat !== '') {
                $lista .= '<li><strong>Matrícula:</strong> ' . $mat . '</li>';
            }
            $lista .= '</ul>';
        }

        $horas = am_servico_horas_para_fatura($srv);
        $valorHora = am_servico_valor_hora($srv);
        $pecasStruct = am_pecas_substituidas_parse_struct($srv['pecas_substituidas'] ?? null);
        $calc = am_fecho_fatura_montar($horas ?? 0.0, $valorHora, $pecasStruct);
        $resumo = '';
        if ($horas !== null && $valorHora > 0) {
            $totalMo = round($horas * $valorHora, 2);
            $resumo .= '<p><strong>Mão de obra:</strong> '
                . htmlspecialchars(am_formatar_horas_trabalhadas($horas), ENT_QUOTES, 'UTF-8')
                . ' × ' . htmlspecialchars(am_formatar_euro($valorHora), ENT_QUOTES, 'UTF-8') . '/h'
                . ' = <strong>' . htmlspecialchars(am_formatar_euro($totalMo), ENT_QUOTES, 'UTF-8') . '</strong></p>';
        }
        if ($pecasStruct !== []) {
            $resumo .= '<p><strong>Peças substituídas:</strong></p><ul style="padding-left:20px">';
            foreach ($pecasStruct as $peca) {
                $precoTxt = $peca['preco'] !== null ? ' — ' . am_formatar_euro((float) $peca['preco']) : '';
                $resumo .= '<li>' . htmlspecialchars($peca['nome'], ENT_QUOTES, 'UTF-8') . htmlspecialchars($precoTxt, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $resumo .= '</ul>';
        }
        if ($calc['valor_total_com_iva'] > 0) {
            $resumo .= '<p style="margin-top:10px;"><strong>Total da fatura (c/ IVA):</strong> '
                . htmlspecialchars(am_formatar_euro($calc['valor_total_com_iva']), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $sid = (int) $servicoRef;
        $linkFatura = 'configuracoes.php?seccao=marcacoes&ver_fatura=' . $sid;
        $linkPagamento = 'pagamento_orcamento.php?servico_id=' . $sid;
        $numFat = trim((string) ($srv['numero_fatura'] ?? ''));
        $estadoPag = strtolower(trim((string) ($srv['estado_pagamento'] ?? '')));
        $valorPago = round((float) ($srv['valor_pago_total'] ?? 0), 2);
        $valorDivida = round((float) ($srv['valor_em_divida'] ?? 0), 2);

        if (!function_exists('am_servico_precisa_pagar_trabalho_mecanico')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }
        if (!function_exists('am_servico_excesso_orcamento_pendente')) {
            require_once __DIR__ . '/inc_excesso_orcamento.php';
        }
        $excessoPendenteEmail = am_servico_excesso_orcamento_pendente($srv);

        $corpo = '<p>O seu trabalho de reparação foi <strong>marcado como concluído</strong> pela oficina.</p>' . $lista;
        if ($resumo !== '') {
            $corpo .= '<div style="margin:16px 0;padding:12px 14px;background:#f8fafc;border-left:4px solid #ff7a1a;border-radius:6px;">'
                . '<p style="margin:0 0 8px;font-weight:700;">Resumo da intervenção</p>' . $resumo . '</div>';
        }
        if ($numFat !== '') {
            $corpo .= '<p><strong>Fatura:</strong> ' . htmlspecialchars($numFat, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($excessoPendenteEmail) {
            $cmpExc = am_servico_excesso_orcamento_comparacao($srv);
            $corpo .= '<div style="margin:16px 0;padding:12px 14px;background:#fff7ed;border-left:4px solid #f59e0b;border-radius:6px;">';
            $corpo .= '<p style="margin:0 0 8px;font-weight:700;">Total acima do orçamento</p>';
            if (is_array($cmpExc)) {
                $corpo .= '<p style="margin:0 0 6px;">Orçamento aceite (c/ IVA): <strong>'
                    . htmlspecialchars(am_formatar_euro((float) ($cmpExc['orcamento_com_iva'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</strong></p>';
                $corpo .= '<p style="margin:0 0 6px;">Total final (c/ IVA): <strong>'
                    . htmlspecialchars(am_formatar_euro((float) ($cmpExc['total_com_iva'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</strong></p>';
            }
            $corpo .= '<p style="margin:0;">Confirme em <strong>Minhas Marcações</strong> se deseja continuar com este valor antes de pagar.</p>';
            $corpo .= '</div>';
            $corpo .= '<p><a href="' . htmlspecialchars('configuracoes.php?seccao=marcacoes', ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:8px;padding:10px 16px;background:#ff7a1a;color:#111;text-decoration:none;border-radius:6px;font-weight:700;">Responder em Minhas Marcações</a></p>';
        } elseif (($estadoPag === 'pendente_complemento' || $valorDivida > 0) && $valorDivida > 0) {
            $corpo .= '<div style="margin:16px 0;padding:12px 14px;background:#fff7ed;border-left:4px solid #f97316;border-radius:6px;">';
            $corpo .= '<p style="margin:0 0 8px;font-weight:700;">Pagamento do trabalho realizado</p>';
            $totalTrabalho = am_servico_total_fatura_final($srv);
            if ($totalTrabalho > 0) {
                $corpo .= '<p style="margin:0 0 6px;">Total do trabalho: <strong>' . htmlspecialchars(am_formatar_euro($totalTrabalho), ENT_QUOTES, 'UTF-8') . '</strong></p>';
            }
            if ($valorPago > 0) {
                $corpo .= '<p style="margin:0 0 6px;">Já pagou: <strong>' . htmlspecialchars(am_formatar_euro($valorPago), ENT_QUOTES, 'UTF-8') . '</strong></p>';
            }
            $corpo .= '<p style="margin:0;">A pagar agora: <strong>' . htmlspecialchars(am_formatar_euro($valorDivida), ENT_QUOTES, 'UTF-8') . '</strong></p>';
            $corpo .= '</div>';
            $corpo .= '<p><a href="' . htmlspecialchars($linkPagamento, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:8px;padding:10px 16px;background:#ff7a1a;color:#111;text-decoration:none;border-radius:6px;font-weight:700;">Pagar trabalho realizado</a></p>';
        }
        $corpo .= '<p><a href="' . htmlspecialchars($linkFatura, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:8px;padding:10px 16px;background:#ff7a1a;color:#111;text-decoration:none;border-radius:6px;font-weight:700;">Ver fatura e detalhes</a></p>';
        $corpo .= '<p style="font-size:13px;color:#666;">Também pode consultar em Configurações → Marcações na sua área de cliente.</p>';
        am_enviar_email_cliente_automanager(
            (string) $srv['email'],
            $nomeC,
            'AUTOMANAGER — Trabalho concluído' . ($numFat !== '' ? ' — ' . $numFat : ''),
            $corpo
        );
    }
}

if (!function_exists('am_notificar_cliente_pagamento_complementar')) {
    function am_notificar_cliente_pagamento_complementar(mysqli $con, int $servicoId, float $valorEmDivida): void
    {
        if ($servicoId <= 0 || $valorEmDivida <= 0) {
            return;
        }
        if (!function_exists('am_formatar_euro')) {
            require_once __DIR__ . '/inc_fatura_intervencao.php';
        }
        $st = $con->prepare("SELECT * FROM servico WHERE id = ? LIMIT 1");
        if (!$st) {
            return;
        }
        $st->bind_param('i', $servicoId);
        if (!$st->execute()) {
            $st->close();
            return;
        }
        $r = $st->get_result();
        $srv = $r ? $r->fetch_assoc() : null;
        $st->close();
        if (!$srv || empty($srv['email'])) {
            return;
        }
        $nome = trim((string) ($srv['nome'] ?? ''));
        $tipo = htmlspecialchars(trim((string) ($srv['tipo_servico'] ?? 'Serviço')), ENT_QUOTES, 'UTF-8');
        $mat = htmlspecialchars(trim((string) ($srv['matricula'] ?? '')), ENT_QUOTES, 'UTF-8');
        $jaPago = am_servico_valor_ja_pago($srv);
        $totalTrabalho = am_servico_total_fatura_final($srv);
        $linkPagamento = 'pagamento_orcamento.php?servico_id=' . $servicoId;
        $linkFatura = 'configuracoes.php?seccao=marcacoes&ver_fatura=' . $servicoId;

        $corpo = '<p>O mecânico concluiu o trabalho no seu veículo. Falta liquidar o valor do serviço realizado.</p>';
        $corpo .= '<ul style="padding-left:20px">';
        $corpo .= '<li><strong>Serviço:</strong> ' . $tipo . '</li>';
        if ($mat !== '') {
            $corpo .= '<li><strong>Matrícula:</strong> ' . $mat . '</li>';
        }
        if ($totalTrabalho > 0) {
            $corpo .= '<li><strong>Total do trabalho:</strong> ' . htmlspecialchars(am_formatar_euro($totalTrabalho), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        if ($jaPago > 0) {
            $corpo .= '<li><strong>Já pago:</strong> ' . htmlspecialchars(am_formatar_euro($jaPago), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $corpo .= '<li><strong>A pagar agora:</strong> ' . htmlspecialchars(am_formatar_euro($valorEmDivida), ENT_QUOTES, 'UTF-8') . '</li>';
        $corpo .= '</ul>';
        $corpo .= '<p>Pode pagar em Configurações → Marcações na sua área de cliente.</p>';
        $corpo .= '<p><a href="' . htmlspecialchars($linkPagamento, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:8px;padding:10px 16px;background:#ff7a1a;color:#111;text-decoration:none;border-radius:6px;font-weight:700;">Pagar trabalho realizado</a></p>';
        $corpo .= '<p><a href="' . htmlspecialchars($linkFatura, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:8px;padding:10px 16px;background:#2f2f35;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;">Ver fatura</a></p>';
        $corpo .= '<p style="font-size:13px;color:#666;">Também pode consultar esta informação em Configurações → Marcações.</p>';

        am_enviar_email_cliente_automanager(
            (string) $srv['email'],
            $nome,
            'AUTOMANAGER — Pagamento do trabalho realizado',
            $corpo
        );
    }
}

if (!function_exists('am_notificar_cliente_excesso_orcamento')) {
    function am_notificar_cliente_excesso_orcamento(mysqli $con, int $servicoId, string $idColumn = 'id'): void
    {
        if ($servicoId <= 0 || ($idColumn !== 'id' && $idColumn !== 'id_servico')) {
            return;
        }
        if (!function_exists('am_servico_excesso_orcamento_comparacao')) {
            require_once __DIR__ . '/inc_excesso_orcamento.php';
        }
        require_once __DIR__ . '/inc_fatura_intervencao.php';

        $st = $con->prepare("SELECT * FROM servico WHERE $idColumn = ? LIMIT 1");
        if (!$st) {
            return;
        }
        $st->bind_param('i', $servicoId);
        if (!$st->execute()) {
            $st->close();

            return;
        }
        $srv = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$srv || empty($srv['email'])) {
            return;
        }

        $cmp = am_servico_excesso_orcamento_comparacao($srv);
        if ($cmp === null) {
            return;
        }

        $nome = trim((string) ($srv['nome'] ?? ''));
        $tipo = htmlspecialchars(trim((string) ($srv['tipo_servico'] ?? 'Serviço')), ENT_QUOTES, 'UTF-8');
        $mat = htmlspecialchars(trim((string) ($srv['matricula'] ?? '')), ENT_QUOTES, 'UTF-8');
        $orcFmt = htmlspecialchars(am_formatar_euro((float) ($cmp['orcamento_com_iva'] ?? 0)), ENT_QUOTES, 'UTF-8');
        $totalFmt = htmlspecialchars(am_formatar_euro((float) ($cmp['total_com_iva'] ?? 0)), ENT_QUOTES, 'UTF-8');
        $diffFmt = htmlspecialchars(am_formatar_euro((float) ($cmp['diferenca'] ?? 0)), ENT_QUOTES, 'UTF-8');
        $linkMarcacoes = 'configuracoes.php?seccao=marcacoes';

        $corpo = '<p>O mecânico concluiu o trabalho, mas o <strong>valor final ficou significativamente acima do orçamento</strong> que tinha aceite.</p>';
        $corpo .= '<ul style="padding-left:20px">';
        $corpo .= '<li><strong>Serviço:</strong> ' . $tipo . '</li>';
        if ($mat !== '') {
            $corpo .= '<li><strong>Matrícula:</strong> ' . $mat . '</li>';
        }
        $corpo .= '<li><strong>Orçamento aceite (c/ IVA):</strong> ' . $orcFmt . '</li>';
        $corpo .= '<li><strong>Total final (c/ IVA):</strong> ' . $totalFmt . '</li>';
        $corpo .= '<li><strong>Diferença:</strong> +' . $diffFmt . '</li>';
        $corpo .= '</ul>';
        $corpo .= '<p>Indique na sua área de cliente se <strong>deseja continuar</strong> com este valor ou se <strong>não aceita</strong> o trabalho ao preço final.</p>';
        $corpo .= '<p><a href="' . htmlspecialchars($linkMarcacoes, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:8px;padding:10px 16px;background:#ff7a1a;color:#111;text-decoration:none;border-radius:6px;font-weight:700;">Responder em Minhas Marcações</a></p>';

        am_enviar_email_cliente_automanager(
            (string) $srv['email'],
            $nome,
            'AUTOMANAGER — Total acima do orçamento — confirme',
            $corpo
        );
    }
}
