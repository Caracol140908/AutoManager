<?php
/**
 * Documento de fatura de serviço — layout profissional (impressão / PDF).
 */
declare(strict_types=1);

if (!function_exists('am_fatura_empresa_config')) {
    /** @return array<string, string> */
    function am_fatura_empresa_config(): array
    {
        return [
            'nome' => 'AUTOMANAGER',
            'razao' => 'AUTOMANAGER — Oficina Automóvel',
            'nif' => '123 456 789',
            'morada' => 'Avenida Doutor Teófilo Carvalho dos Santos',
            'cidade' => '2580-318 Alenquer',
            'telefone' => '912 964 022',
            'email' => 'automanagergeral@gmail.com',
            'horario' => 'Seg–Sex 08:00 – 18:00',
        ];
    }
}

if (!function_exists('am_fatura_metodo_legivel')) {
    function am_fatura_metodo_legivel(string $metodo): string
    {
        $map = [
            'mbway' => 'MB Way',
            'cartao' => 'Cartão',
            'referencia_mb' => 'Referência Multibanco',
        ];
        $metodo = strtolower(trim($metodo));

        return $map[$metodo] ?? ($metodo !== '' ? ucfirst($metodo) : '—');
    }
}

if (!function_exists('am_fatura_historico_cliente')) {
    /**
     * @return list<array{data:string,servico:string,total:float}>
     */
    function am_fatura_historico_cliente(mysqli $con, array $servico, int $limite = 3): array
    {
        $email = trim((string) ($servico['email'] ?? ''));
        $idAtual = (int) ($servico['id'] ?? 0);
        if ($email === '' || $idAtual <= 0) {
            return [];
        }

        $limite = max(1, min(5, $limite));
        $st = $con->prepare("
            SELECT id, tipo_servico,
                   COALESCE(data_conclusao, data_desejada, data_registo) AS dt,
                   valor_total_com_iva, custo, subtotal_sem_iva, valor_iva
            FROM servico
            WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
              AND id <> ?
              AND (
                LOWER(COALESCE(estado_pagamento, '')) = 'pago'
                OR numero_fatura IS NOT NULL AND numero_fatura <> ''
              )
            ORDER BY dt DESC
            LIMIT ?
        ");
        if (!$st) {
            return [];
        }
        $st->bind_param('sii', $email, $idAtual, $limite);
        $st->execute();
        $res = $st->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $dt = $row['dt'] ?? null;
            $dataTxt = '—';
            if ($dt) {
                $ts = strtotime((string) $dt);
                if ($ts) {
                    $dataTxt = date('M Y', $ts);
                }
            }
            // Para o histórico recente queremos o valor final do serviço
            // (armazenado em `servico.valor_total_com_iva`). Evita recalcular a partir de
            // `fatura_linhas_json`/fecho, que nem sempre está disponível no SELECT do histórico.
            $out[] = [
                'data' => $dataTxt,
                'servico' => trim((string) ($row['tipo_servico'] ?? 'Serviço')),
                'total' => (float) ($row['valor_total_com_iva']
                    ?? ($row['subtotal_sem_iva'] ?? 0) + ($row['valor_iva'] ?? 0)),
            ];
        }
        $st->close();

        return $out;
    }
}

if (!function_exists('am_fatura_documento_montar')) {
    /**
     * @param array<string, mixed> $servico
     * @return array<string, mixed>
     */
    function am_fatura_documento_montar(mysqli $con, array $servico): array
    {
        require_once __DIR__ . '/inc_fatura_intervencao.php';

        $servicoId = (int) ($servico['id'] ?? 0);
        $reparado = am_servico_reparar_valores_fiscais_se_inconsistentes($con, $servicoId, $servico);
        if (is_array($reparado)) {
            $servico = $reparado;
        }

        if (empty($servico['numero_fatura'])) {
            $numero = 'FAT' . str_pad((string) $servicoId, 6, '0', STR_PAD_LEFT);
            $upd = $con->prepare('UPDATE servico SET numero_fatura = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('si', $numero, $servicoId);
                $upd->execute();
                $upd->close();
            }
            $servico['numero_fatura'] = $numero;
        }

        $valores = am_servico_valores_fiscais_fatura($servico);
        $linhas = am_fatura_linhas_do_servico($servico);
        $empresa = am_fatura_empresa_config();

        $nomeCliente = trim((string) ($servico['nome_utilizador'] ?? $servico['nome'] ?? ''));
        $nifCliente = '';
        if (!empty($servico['pedir_contribuinte_fatura']) && !empty($servico['contribuinte_fatura'])) {
            $nifCliente = (string) $servico['contribuinte_fatura'];
        } elseif (!empty($servico['nif'])) {
            $nifCliente = (string) $servico['nif'];
        }

        $marca = trim((string) ($servico['marca'] ?? ''));
        $modelo = trim((string) ($servico['modelo'] ?? ''));
        if ($marca === '' && $modelo === '' && !empty($servico['carro'])) {
            $partes = preg_split('/\s+/', trim((string) $servico['carro']), 2);
            $marca = $partes[0] ?? '';
            $modelo = $partes[1] ?? '';
        }

        $dataServico = 'A confirmar';
        if (!empty($servico['data_desejada'])) {
            $dataServico = date('d/m/Y', strtotime((string) $servico['data_desejada']));
            if (!empty($servico['hora_desejada'])) {
                $dataServico .= ' · ' . trim((string) $servico['hora_desejada']);
            }
        } elseif (!empty($servico['data_conclusao'])) {
            $dataServico = date('d/m/Y', strtotime((string) $servico['data_conclusao']));
        }

        $metodo = trim((string) ($servico['metodo_pagamento'] ?? ''));
        $pagamentoConfirmado = !am_servico_precisa_pagamento($servico);
        $valorPago = am_servico_valor_ja_pago($servico);
        $valorDivida = am_servico_valor_trabalho_mecanico_a_pagar($servico);
        $precisaPagar = am_servico_precisa_pagar_trabalho_mecanico($servico);

        $itens = [];
        if ($linhas !== []) {
            foreach ($linhas as $ln) {
                $desc = trim((string) ($ln['descricao'] ?? ''));
                if ($desc === '') {
                    $desc = trim((string) ($servico['tipo_servico'] ?? 'Serviço'));
                }
                $itens[] = [
                    'descricao' => $desc,
                    'qtd' => (float) ($ln['quantidade'] ?? 1),
                    'preco_unit' => (float) ($ln['preco_unitario'] ?? 0),
                    'total' => (float) ($ln['total_sem_iva'] ?? 0),
                ];
            }
        } else {
            $itens[] = [
                'descricao' => trim((string) ($servico['tipo_servico'] ?? 'Serviço automóvel')),
                'qtd' => 1.0,
                'preco_unit' => (float) $valores['subtotal_sem_iva'],
                'total' => (float) $valores['subtotal_sem_iva'],
            ];
        }

        return [
            'servico' => $servico,
            'servico_id' => $servicoId,
            'empresa' => $empresa,
            'numero_fatura' => (string) ($servico['numero_fatura'] ?? ''),
            'data_emissao' => date('d/m/Y'),
            'cliente' => [
                'nome' => $nomeCliente !== '' ? $nomeCliente : '—',
                'telefone' => trim((string) ($servico['telefone'] ?? '')) ?: '—',
                'email' => trim((string) ($servico['email'] ?? '')) ?: '—',
                'nif' => $nifCliente,
            ],
            'veiculo' => [
                'marca' => $marca !== '' ? $marca : '—',
                'modelo' => $modelo !== '' ? $modelo : '—',
                'matricula' => trim((string) ($servico['matricula'] ?? '')) ?: '—',
                'data_servico' => $dataServico,
            ],
            'itens' => $itens,
            'subtotal' => (float) $valores['subtotal_sem_iva'],
            'iva' => (float) $valores['valor_iva'],
            'total' => (float) $valores['valor_total_com_iva'],
            'pagamento' => [
                'confirmado' => $pagamentoConfirmado,
                'metodo' => am_fatura_metodo_legivel($metodo),
                'data' => !empty($servico['data_pagamento'])
                    ? date('d/m/Y', strtotime((string) $servico['data_pagamento']))
                    : date('d/m/Y'),
                'valor_pago' => $valorPago,
                'valor_divida' => $valorDivida,
                'precisa_pagar' => $precisaPagar,
            ],
            'historico' => am_fatura_historico_cliente($con, $servico, 3),
        ];
    }
}

if (!function_exists('am_fatura_documento_emitir')) {
    /**
     * @param array<string, mixed> $doc
     * @param array{title?:string,css_href?:string,acoes?:list<array<string,string>>} $opts
     */
    function am_fatura_documento_emitir(array $doc, array $opts = []): void
    {
        $h = static function ($v): string {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $euro = static function (float $v): string {
            return number_format($v, 2, ',', ' ');
        };

        $emp = $doc['empresa'];
        $cli = $doc['cliente'];
        $vei = $doc['veiculo'];
        $pag = $doc['pagamento'];
        $title = $opts['title'] ?? ('Fatura ' . ($doc['numero_fatura'] ?? ''));
        $css = $opts['css_href'] ?? 'fatura_pdf.css?v=2';
        $acoes = $opts['acoes'] ?? [];
        ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php if (is_file(__DIR__ . '/inc_am_head.php')) { require __DIR__ . '/inc_am_head.php'; } ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?> — AUTOMANAGER</title>
    <link rel="stylesheet" href="<?= $h($css) ?>">
</head>
<body class="fat-doc">
    <div class="fat-page">
        <header class="fat-banner">
            <div class="fat-banner__brand"><?= $h($emp['nome']) ?></div>
            <div class="fat-banner__title">
                <strong>FATURA DE SERVIÇO</strong>
                <span><?= $h($emp['razao']) ?></span>
            </div>
        </header>

        <div class="fat-top">
            <section class="fat-block">
                <h2 class="fat-block__title">Dados do cliente</h2>
                <dl class="fat-dl">
                    <div><dt>Nome</dt><dd><?= $h($cli['nome']) ?></dd></div>
                    <div><dt>Telefone</dt><dd><?= $h($cli['telefone']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= $h($cli['email']) ?></dd></div>
                    <?php if ($cli['nif'] !== ''): ?>
                    <div><dt>NIF</dt><dd><?= $h($cli['nif']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="fat-block fat-block--empresa">
                <h2 class="fat-block__title"><?= $h(strtoupper($emp['nome'])) ?> — OFICINA AUTOMÓVEL</h2>
                <dl class="fat-dl fat-dl--compact">
                    <div><dt>NIF</dt><dd><?= $h($emp['nif']) ?></dd></div>
                    <div><dt>Morada</dt><dd><?= $h($emp['morada']) ?>, <?= $h($emp['cidade']) ?></dd></div>
                    <div><dt>Telefone</dt><dd><?= $h($emp['telefone']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= $h($emp['email']) ?></dd></div>
                    <div><dt>Horário</dt><dd><?= $h($emp['horario']) ?></dd></div>
                </dl>

                <aside class="fat-total-box">
                    <span class="fat-total-box__label">Total a pagar</span>
                    <span class="fat-total-box__value"><?= $euro((float) $doc['total']) ?> €</span>
                    <span class="fat-total-box__meta">Fatura n.º <?= $h($doc['numero_fatura']) ?></span>
                    <span class="fat-total-box__meta">Emissão: <?= $h($doc['data_emissao']) ?></span>
                </aside>
            </section>
        </div>

        <section class="fat-block fat-block--full">
            <h2 class="fat-block__title">Dados da viatura / marcação</h2>
            <div class="fat-vehicle-grid">
                <div><span>Marca</span><strong><?= $h($vei['marca']) ?></strong></div>
                <div><span>Modelo</span><strong><?= $h($vei['modelo']) ?></strong></div>
                <div><span>Matrícula</span><strong><?= $h($vei['matricula']) ?></strong></div>
                <div><span>Data serviço</span><strong><?= $h($vei['data_servico']) ?></strong></div>
            </div>
        </section>

        <section class="fat-table-wrap">
            <table class="fat-table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="col-qtd">Qtd</th>
                        <th class="col-money">Preço unit. (€)</th>
                        <th class="col-money">Total (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doc['itens'] as $item): ?>
                    <tr>
                        <td><?= $h($item['descricao']) ?></td>
                        <td class="col-qtd"><?= $h(rtrim(rtrim(number_format((float) $item['qtd'], 2, ',', ' '), '0'), ',')) ?></td>
                        <td class="col-money"><?= $euro((float) $item['preco_unit']) ?></td>
                        <td class="col-money"><?= $euro((float) $item['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="fat-totals">
                <div class="fat-totals__row"><span>Subtotal</span><strong><?= $euro((float) $doc['subtotal']) ?> €</strong></div>
                <div class="fat-totals__row"><span>IVA (23%)</span><strong><?= $euro((float) $doc['iva']) ?> €</strong></div>
                <div class="fat-totals__row fat-totals__row--grand"><span>TOTAL</span><strong><?= $euro((float) $doc['total']) ?> €</strong></div>
            </div>
        </section>

        <div class="fat-bottom">
            <section class="fat-block fat-block--notes">
                <h2 class="fat-block__title">Observações</h2>
                <p>Serviço solicitado pelo cliente conforme descrição acima. Conserve esta fatura para efeitos de garantia e arquivo fiscal.</p>
                <?php if ($pag['confirmado']): ?>
                <p class="fat-pay-status fat-pay-status--ok">
                    <strong>Pagamento confirmado</strong> — <?= $h($pag['metodo']) ?> em <?= $h($pag['data']) ?>.
                </p>
                <?php elseif ($pag['precisa_pagar'] && $pag['valor_divida'] > 0): ?>
                <p class="fat-pay-status fat-pay-status--pending">
                    <strong>Pagamento pendente</strong> — valor em falta: <?= $euro((float) $pag['valor_divida']) ?> €.
                </p>
                <?php endif; ?>
            </section>

            <aside class="fat-side">
                <div class="fat-side-card">
                    <h3>Precisa de ajuda?</h3>
                    <p><strong>Telefone:</strong> <?= $h($emp['telefone']) ?></p>
                    <p><strong>Email:</strong> <?= $h($emp['email']) ?></p>
                    <p><strong>Horário:</strong> <?= $h($emp['horario']) ?></p>
                </div>
                <?php if (!empty($doc['historico'])): ?>
                <div class="fat-side-card">
                    <h3>Histórico recente</h3>
                    <table class="fat-history">
                        <thead>
                            <tr><th>Data</th><th>Serviço</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doc['historico'] as $hist): ?>
                            <tr>
                                <td><?= $h($hist['data']) ?></td>
                                <td><?= $h($hist['servico']) ?></td>
                                <td><?= $euro((float) $hist['total']) ?> €</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </aside>
        </div>

        <footer class="fat-footer">
            <p><?= $h($emp['razao']) ?></p>
        </footer>

        <?php if ($acoes !== []): ?>
        <div class="fat-actions no-print">
            <?php foreach ($acoes as $acao): ?>
                <?php if (!empty($acao['href'])): ?>
                    <a href="<?= $h($acao['href']) ?>" class="fat-btn <?= $h($acao['class'] ?? '') ?>"><?= $h($acao['label'] ?? '') ?></a>
                <?php elseif (!empty($acao['onclick'])): ?>
                    <button type="button" class="fat-btn <?= $h($acao['class'] ?? '') ?>" onclick="<?= $h($acao['onclick']) ?>"><?= $h($acao['label'] ?? '') ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
    }
}
