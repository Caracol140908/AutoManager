<?php

declare(strict_types=1);

/**
 * Horas trabalhadas, valor/hora, peças e totais na conclusão / fatura ao cliente.
 */

if (!defined('AM_VALOR_HORA_PADRAO')) {
    define('AM_VALOR_HORA_PADRAO', 35.0);
}

if (!function_exists('am_ensure_servico_fecho_cols')) {
    function am_ensure_servico_fecho_cols(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $cols = [];
        $q = @$con->query('SHOW COLUMNS FROM servico');
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                if (!empty($row['Field'])) {
                    $cols[$row['Field']] = true;
                }
            }
        }
        if (!isset($cols['horas_trabalhadas'])) {
            @$con->query('ALTER TABLE servico ADD COLUMN horas_trabalhadas DECIMAL(6,2) NULL');
        }
        if (!isset($cols['valor_hora'])) {
            @$con->query('ALTER TABLE servico ADD COLUMN valor_hora DECIMAL(8,2) NULL');
        }
        if (!isset($cols['valor_pago_total'])) {
            @$con->query('ALTER TABLE servico ADD COLUMN valor_pago_total DECIMAL(10,2) NULL');
        }
        if (!isset($cols['valor_em_divida'])) {
            @$con->query('ALTER TABLE servico ADD COLUMN valor_em_divida DECIMAL(10,2) NULL');
        }
        if (!isset($cols['pecas_substituidas'])) {
            @$con->query('ALTER TABLE servico ADD COLUMN pecas_substituidas TEXT NULL');
        }
    }
}

if (!function_exists('am_pecas_substituidas_catalogo')) {
    /**
     * Catálogo de peças com preço sugerido (€) para pré-preencher na conclusão.
     *
     * @return array<string, array<int, array{nome: string, preco: float}>>
     */
    function am_pecas_substituidas_catalogo(): array
    {
        return [
            'Fluidos e filtros' => [
                ['nome' => 'Óleo do motor', 'preco' => 45.00],
                ['nome' => 'Filtro de óleo', 'preco' => 12.00],
                ['nome' => 'Filtro de ar', 'preco' => 18.00],
                ['nome' => 'Filtro de habitáculo', 'preco' => 15.00],
                ['nome' => 'Filtro de combustível', 'preco' => 32.00],
                ['nome' => 'Líquido de refrigeração', 'preco' => 18.00],
                ['nome' => 'Líquido de travões', 'preco' => 10.00],
                ['nome' => 'Líquido limpa-vidros', 'preco' => 5.00],
            ],
            'Travões e suspensão' => [
                ['nome' => 'Pastilhas de travão (dianteiros)', 'preco' => 55.00],
                ['nome' => 'Pastilhas de travão (traseiros)', 'preco' => 42.00],
                ['nome' => 'Discos de travão (dianteiros)', 'preco' => 95.00],
                ['nome' => 'Discos de travão (traseiros)', 'preco' => 75.00],
                ['nome' => 'Amortecedores', 'preco' => 120.00],
                ['nome' => 'Molas', 'preco' => 75.00],
                ['nome' => 'Braços de suspensão', 'preco' => 95.00],
                ['nome' => 'Pivôs / rolamentos', 'preco' => 65.00],
            ],
            'Motor e transmissão' => [
                ['nome' => 'Correia de distribuição', 'preco' => 55.00],
                ['nome' => 'Kit de distribuição', 'preco' => 320.00],
                ['nome' => 'Correia auxiliar', 'preco' => 32.00],
                ['nome' => 'Kit de embraiagem', 'preco' => 450.00],
                ['nome' => 'Velas de ignição', 'preco' => 48.00],
                ['nome' => 'Bobines de ignição', 'preco' => 45.00],
                ['nome' => 'Bomba de água', 'preco' => 85.00],
                ['nome' => 'Termóstato', 'preco' => 32.00],
                ['nome' => 'Radiador', 'preco' => 165.00],
                ['nome' => 'Escape / silenciador', 'preco' => 220.00],
            ],
            'Elétrico e pneus' => [
                ['nome' => 'Bateria', 'preco' => 110.00],
                ['nome' => 'Alternador', 'preco' => 240.00],
                ['nome' => 'Motor de arranque', 'preco' => 195.00],
                ['nome' => 'Sensores', 'preco' => 65.00],
                ['nome' => 'Lâmpadas / faróis', 'preco' => 22.00],
                ['nome' => 'Escovas de limpa-vidros', 'preco' => 16.00],
                ['nome' => 'Pneus', 'preco' => 95.00],
                ['nome' => 'Válvulas de pneu', 'preco' => 3.00],
            ],
        ];
    }
}

if (!function_exists('am_peca_preco_sugerido')) {
    function am_peca_preco_sugerido(string $nome): float
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (am_pecas_substituidas_catalogo() as $itens) {
                foreach ($itens as $item) {
                    $map[$item['nome']] = (float) $item['preco'];
                }
            }
        }
        $nome = trim($nome);
        if ($nome !== '' && isset($map[$nome])) {
            return $map[$nome];
        }

        return 25.00;
    }
}

if (!function_exists('am_pecas_substituidas_parse_struct')) {
    /**
     * @return array<int, array{nome: string, preco: float|null}>
     */
    function am_pecas_substituidas_parse_struct(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];

            return array_values(array_map(static function (string $nome): array {
                return ['nome' => $nome, 'preco' => null];
            }, array_filter(array_map('trim', $parts))));
        }

        $out = [];
        foreach ($dec as $item) {
            if (is_string($item)) {
                $nome = trim($item);
                if ($nome !== '') {
                    $out[] = ['nome' => $nome, 'preco' => null];
                }
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $nome = trim((string) ($item['nome'] ?? $item['descricao'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $preco = isset($item['preco']) && $item['preco'] !== '' ? round((float) $item['preco'], 2) : null;
            $out[] = ['nome' => $nome, 'preco' => $preco];
        }

        return $out;
    }
}

if (!function_exists('am_pecas_substituidas_parse')) {
    /** @return string[] */
    function am_pecas_substituidas_parse(?string $raw): array
    {
        return array_map(static function (array $p): string {
            return (string) $p['nome'];
        }, am_pecas_substituidas_parse_struct($raw));
    }
}

if (!function_exists('am_pecas_substituidas_from_json_post')) {
    /**
     * @param mixed $jsonRaw
     */
    function am_pecas_substituidas_from_json_post($jsonRaw): string
    {
        if (is_string($jsonRaw)) {
            $dec = json_decode($jsonRaw, true);
        } elseif (is_array($jsonRaw)) {
            $dec = $jsonRaw;
        } else {
            return '';
        }
        if (!is_array($dec)) {
            return '';
        }
        $lista = [];
        foreach ($dec as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nome = trim((string) ($item['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $preco = isset($item['preco']) && $item['preco'] !== '' ? round(max(0, (float) $item['preco']), 2) : 0.0;
            $lista[] = ['nome' => $nome, 'preco' => $preco];
        }
        if ($lista === []) {
            return '';
        }

        return json_encode($lista, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('am_formatar_euro')) {
    function am_formatar_euro(float $valor): string
    {
        return '€' . number_format($valor, 2, ',', ' ');
    }
}

if (!function_exists('am_formatar_horas_trabalhadas')) {
    function am_formatar_horas_trabalhadas($horas): string
    {
        if ($horas === null || $horas === '') {
            return '—';
        }
        $h = (float) $horas;
        if ($h <= 0) {
            return '—';
        }
        $txt = rtrim(rtrim(number_format($h, 2, ',', ' '), '0'), ',');
        if ($h === 1.0) {
            return $txt . ' hora';
        }

        return $txt . ' horas';
    }
}

if (!function_exists('am_linha_fatura_horas')) {
    /**
     * Horas de trabalho numa linha de fatura/orçamento (o campo minutos_trabalho pode guardar horas ou minutos).
     */
    function am_linha_fatura_horas(array $ln): ?float
    {
        $qty = (float) ($ln['quantidade'] ?? 0);
        $minRaw = $ln['minutos_trabalho'] ?? null;
        $min = ($minRaw === null || $minRaw === '') ? 0.0 : (float) $minRaw;

        if ($min <= 0 && $qty <= 0) {
            return null;
        }

        // Fecho do mecânico: quantidade = horas, minutos_trabalho = horas × 60
        if ($min > 0 && $qty > 0 && abs($qty - ($min / 60)) < 0.02) {
            return round($qty, 2);
        }

        if ($min > 0) {
            // Orçamento: horas no campo minutos_trabalho; valores ≥ 60 são minutos convertidos
            if ($min >= 60) {
                return round($min / 60, 2);
            }

            return round($min, 2);
        }

        $cat = strtolower(trim((string) ($ln['categoria'] ?? '')));
        if ($qty > 0 && (strpos($cat, 'mao') !== false || strpos($cat, 'mão') !== false)) {
            return round($qty, 2);
        }

        return null;
    }
}

if (!function_exists('am_formatar_tempo_linha_fatura')) {
    function am_formatar_tempo_linha_fatura(array $ln): string
    {
        $horas = am_linha_fatura_horas($ln);

        return $horas !== null ? am_formatar_horas_trabalhadas($horas) : '—';
    }
}

if (!function_exists('am_servico_minutos_trabalho_reais')) {
    function am_servico_minutos_trabalho_reais(array $servico): ?int
    {
        if (isset($servico['tempo_trabalho_minutos']) && $servico['tempo_trabalho_minutos'] !== null && $servico['tempo_trabalho_minutos'] !== '') {
            $min = (int) $servico['tempo_trabalho_minutos'];
            if ($min > 0) {
                return $min;
            }
        }

        $inicio = null;
        $fim = null;
        if (!empty($servico['hora_inicio_trabalho'])) {
            $inicio = strtotime((string) $servico['hora_inicio_trabalho']);
        } elseif (!empty($servico['data_registo'])) {
            $inicio = strtotime((string) $servico['data_registo']);
        }
        if (!empty($servico['hora_fim_trabalho'])) {
            $fim = strtotime((string) $servico['hora_fim_trabalho']);
        } elseif (!empty($servico['data_conclusao'])) {
            $fim = strtotime((string) $servico['data_conclusao']);
        }
        if ($inicio && $fim && $fim > $inicio) {
            return max(1, (int) round(($fim - $inicio) / 60));
        }

        return null;
    }
}

if (!function_exists('am_servico_horas_para_fatura')) {
    function am_servico_horas_para_fatura(array $servico): ?float
    {
        if (isset($servico['horas_trabalhadas']) && $servico['horas_trabalhadas'] !== null && $servico['horas_trabalhadas'] !== '') {
            $h = (float) $servico['horas_trabalhadas'];
            if ($h > 0) {
                return round($h, 2);
            }
        }
        if (isset($servico['tempo_trabalho_minutos']) && $servico['tempo_trabalho_minutos'] !== null && $servico['tempo_trabalho_minutos'] !== '') {
            $min = (int) $servico['tempo_trabalho_minutos'];
            if ($min > 0) {
                return round($min / 60, 2);
            }
        }

        return null;
    }
}

if (!function_exists('am_servico_valor_hora')) {
    function am_servico_valor_hora(array $servico): float
    {
        if (isset($servico['valor_hora']) && $servico['valor_hora'] !== null && $servico['valor_hora'] !== '') {
            $v = (float) $servico['valor_hora'];
            if ($v > 0) {
                return round($v, 2);
            }
        }

        return round((float) AM_VALOR_HORA_PADRAO, 2);
    }
}

if (!function_exists('am_servico_sanitizar_horas_fecho')) {
    function am_servico_sanitizar_horas_fecho(float $horas, array $servico): float
    {
        $horas = round(max(0, $horas), 2);
        if ($horas <= 0) {
            return 0.0;
        }
        if ($horas > 24) {
            return 24.0;
        }

        return $horas;
    }
}

if (!function_exists('am_servico_sanitizar_valor_hora_fecho')) {
    function am_servico_sanitizar_valor_hora_fecho(float $valorHora, array $servico): float
    {
        $valorHora = round(max(0, $valorHora), 2);
        if ($valorHora <= 0) {
            return round((float) AM_VALOR_HORA_PADRAO, 2);
        }
        if ($valorHora > 500) {
            return round((float) AM_VALOR_HORA_PADRAO, 2);
        }

        return $valorHora;
    }
}

if (!function_exists('am_fecho_fatura_montar')) {
    /**
     * @param array<int, array{nome: string, preco: float|null}> $pecas
     * @return array{linhas: array<int, array<string, mixed>>, subtotal_sem_iva: float, valor_iva: float, valor_total_com_iva: float}
     */
    function am_fecho_fatura_montar(float $horas, float $valorHora, array $pecas): array
    {
        $linhas = [];
        $horas = max(0, $horas);
        $valorHora = max(0, $valorHora);

        if ($horas > 0 && $valorHora > 0) {
            $totalMo = round($horas * $valorHora, 2);
            $linhas[] = [
                'descricao' => 'Mão de obra (' . am_formatar_horas_trabalhadas($horas) . ' × ' . am_formatar_euro($valorHora) . '/h)',
                'categoria' => 'Mão de obra',
                'minutos_trabalho' => (int) round($horas * 60),
                'quantidade' => $horas,
                'preco_unitario' => $valorHora,
                'total_sem_iva' => $totalMo,
            ];
        }

        foreach ($pecas as $peca) {
            $nome = trim((string) ($peca['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $preco = round(max(0, (float) ($peca['preco'] ?? 0)), 2);
            $linhas[] = [
                'descricao' => $nome,
                'categoria' => 'Peça',
                'minutos_trabalho' => null,
                'quantidade' => 1,
                'preco_unitario' => $preco,
                'total_sem_iva' => $preco,
            ];
        }

        $subtotal = round(array_sum(array_map(static function (array $ln): float {
            return (float) ($ln['total_sem_iva'] ?? 0);
        }, $linhas)), 2);
        $iva = round($subtotal * 0.23, 2);
        $total = round($subtotal + $iva, 2);

        return [
            'linhas' => $linhas,
            'subtotal_sem_iva' => $subtotal,
            'valor_iva' => $iva,
            'valor_total_com_iva' => $total,
        ];
    }
}

if (!function_exists('am_fecho_fatura_json_snapshot')) {
    function am_fecho_fatura_json_snapshot(float $horas, float $valorHora, array $pecas): string
    {
        $calc = am_fecho_fatura_montar($horas, $valorHora, $pecas);

        return json_encode([
            'origem' => 'fecho_mecanico',
            'horas' => $horas,
            'valor_hora' => $valorHora,
            'linhas' => $calc['linhas'],
            'subtotal_sem_iva' => $calc['subtotal_sem_iva'],
            'valor_iva' => $calc['valor_iva'],
            'valor_total_com_iva' => $calc['valor_total_com_iva'],
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('am_fatura_linhas_do_servico')) {
    /** @return array<int, array<string, mixed>> */
    function am_fatura_linhas_do_servico(array $servico): array
    {
        if (am_servico_tem_fecho_mecanico($servico)) {
            $calc = am_servico_calc_fecho_fatura($servico);
            if (!empty($calc['linhas'])) {
                return $calc['linhas'];
            }
        }

        if (!empty($servico['fatura_linhas_json'])) {
            $dec = json_decode((string) $servico['fatura_linhas_json'], true);
            if (is_array($dec) && ($dec['origem'] ?? '') === 'fecho_mecanico' && !empty($dec['linhas']) && is_array($dec['linhas'])) {
                return $dec['linhas'];
            }
        }

        $horas = am_servico_horas_para_fatura($servico) ?? 0.0;
        $valorHora = am_servico_valor_hora($servico);
        $pecas = am_pecas_substituidas_parse_struct($servico['pecas_substituidas'] ?? null);

        return am_fecho_fatura_montar($horas, $valorHora, $pecas)['linhas'];
    }
}

if (!function_exists('am_servico_comparacao_orcamento_total')) {
    /**
     * Compara o total final (c/ IVA) com o orçamento aprovado ao cliente.
     *
     * @return array{
     *   tem_orcamento:bool,
     *   orcamento_com_iva:float,
     *   total_com_iva:float,
     *   diferenca:float,
     *   status:string,
     *   rotulo:string,
     *   mensagem:string
     * }
     */
    function am_servico_comparacao_orcamento_total(array $servico, float $totalComIva): array
    {
        $orcamentoComIva = am_servico_valor_orcamento_estimado_com_iva($servico);
        $totalComIva = round($totalComIva, 2);
        if ($orcamentoComIva <= 0) {
            return [
                'tem_orcamento' => false,
                'orcamento_com_iva' => 0.0,
                'total_com_iva' => $totalComIva,
                'diferenca' => 0.0,
                'status' => '',
                'rotulo' => '',
                'mensagem' => '',
            ];
        }
        $diferenca = round($totalComIva - $orcamentoComIva, 2);
        $tolerancia = 0.5;
        if (abs($diferenca) <= $tolerancia) {
            $status = 'dentro';
            $rotulo = 'Dentro do orçamento';
            $mensagem = 'O total está alinhado com o orçamento apresentado ao cliente.';
        } elseif ($diferenca > 0) {
            $status = 'acima';
            $rotulo = 'Acima do orçamento';
            $mensagem = 'O total ficou ' . am_formatar_euro($diferenca) . ' acima do orçamento ao cliente.';
        } else {
            $status = 'abaixo';
            $rotulo = 'Abaixo do orçamento';
            $mensagem = 'O total ficou ' . am_formatar_euro(abs($diferenca)) . ' abaixo do orçamento ao cliente.';
        }

        return [
            'tem_orcamento' => true,
            'orcamento_com_iva' => $orcamentoComIva,
            'total_com_iva' => $totalComIva,
            'diferenca' => $diferenca,
            'status' => $status,
            'rotulo' => $rotulo,
            'mensagem' => $mensagem,
        ];
    }
}

if (!function_exists('am_fatura_intervencao_tem_dados')) {
    function am_fatura_intervencao_tem_dados(array $servico): bool
    {
        $horas = am_servico_horas_para_fatura($servico);
        $pecas = am_pecas_substituidas_parse_struct($servico['pecas_substituidas'] ?? null);
        $notas = trim((string) ($servico['notas_mecanico'] ?? $servico['relatorio_mecanico'] ?? $servico['notas_internas'] ?? $servico['observacoes'] ?? ''));

        return $horas !== null || $pecas !== [] || $notas !== '';
    }
}

if (!function_exists('am_fatura_intervencao_html')) {
    function am_fatura_intervencao_html(array $servico, string $theme = 'light'): string
    {
        if (!am_fatura_intervencao_tem_dados($servico)) {
            return '';
        }

        $notas = trim((string) ($servico['notas_mecanico'] ?? $servico['relatorio_mecanico'] ?? $servico['notas_internas'] ?? $servico['observacoes'] ?? ''));
        $calc = am_servico_calc_fecho_fatura($servico);
        $horas = $calc['horas'];
        $valorHora = $calc['valor_hora'];
        $pecas = $calc['pecas'];

        $isDark = $theme === 'dark';
        $boxStyle = $isDark
            ? 'background:#17171b;border:1px solid #2a2a2f;border-radius:8px;padding:14px;margin-bottom:16px;'
            : 'background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #ff7a1a;border-radius:8px;padding:16px;margin-bottom:16px;';
        $titleColor = $isDark ? '#ff9a52' : '#c2410c';
        $textColor = $isDark ? '#d4d4d8' : '#334155';
        $mutedColor = $isDark ? '#a1a1aa' : '#64748b';
        $borderColor = $isDark ? '#2a2a2f' : '#e2e8f0';

        $html = '<div class="fatura-intervencao' . ($isDark ? ' fatura-intervencao--dark' : ' fatura-intervencao--light') . '" style="' . $boxStyle . '">';
        $html .= '<h3 style="margin:0 0 10px;color:' . $titleColor . ';font-size:15px;">Trabalho realizado</h3>';

        if ($horas !== null && $horas > 0 && $valorHora > 0) {
            $totalMo = round($horas * $valorHora, 2);
            $html .= '<p style="margin:0 0 8px;font-size:13px;color:' . $textColor . ';">';
            $html .= '<strong>Mão de obra:</strong> ' . htmlspecialchars(am_formatar_horas_trabalhadas($horas), ENT_QUOTES, 'UTF-8');
            $html .= ' × ' . htmlspecialchars(am_formatar_euro($valorHora), ENT_QUOTES, 'UTF-8') . '/h';
            $html .= ' = <strong>' . htmlspecialchars(am_formatar_euro($totalMo), ENT_QUOTES, 'UTF-8') . '</strong>';
            $html .= '</p>';
        }

        if ($pecas !== []) {
            $totalPecas = round(array_sum(array_map(static function (array $p): float {
                return max(0, (float) ($p['preco'] ?? 0));
            }, $pecas)), 2);
            $html .= '<table style="width:100%;border-collapse:collapse;margin:10px 0;font-size:13px;color:' . $textColor . ';">';
            $html .= '<thead><tr style="border-bottom:1px solid ' . $borderColor . ';text-align:left;">';
            $html .= '<th style="padding:6px 4px;">Peça / componente</th>';
            $html .= '<th style="padding:6px 4px;text-align:right;">Valor</th></tr></thead><tbody>';
            foreach ($pecas as $peca) {
                $preco = $peca['preco'] !== null ? am_formatar_euro((float) $peca['preco']) : '—';
                $html .= '<tr style="border-bottom:1px solid ' . $borderColor . ';">';
                $html .= '<td style="padding:6px 4px;">' . htmlspecialchars($peca['nome'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td style="padding:6px 4px;text-align:right;">' . htmlspecialchars($preco, ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            if ($totalPecas > 0) {
                $html .= '<p style="margin:0 0 8px;font-size:13px;color:' . $textColor . ';display:flex;justify-content:space-between;">';
                $html .= '<span><strong>Peças</strong></span>';
                $html .= '<strong>' . htmlspecialchars(am_formatar_euro($totalPecas), ENT_QUOTES, 'UTF-8') . '</strong>';
                $html .= '</p>';
            }
        }

        if ($calc['subtotal_sem_iva'] > 0) {
            $html .= '<div style="margin-top:10px;padding-top:10px;border-top:1px solid ' . $borderColor . ';font-size:13px;color:' . $textColor . ';">';
            $comparacao = am_servico_comparacao_orcamento_total($servico, (float) $calc['valor_total_com_iva']);
            $labelTotal = am_servico_precisa_pagar_trabalho_mecanico($servico) ? 'Total a pagar' : 'Total do trabalho';
            $html .= '<div style="display:flex;justify-content:space-between;margin:4px 0;"><span>Subtotal (s/ IVA)</span><strong>' . htmlspecialchars(am_formatar_euro($calc['subtotal_sem_iva']), ENT_QUOTES, 'UTF-8') . '</strong></div>';
            $html .= '<div style="display:flex;justify-content:space-between;margin:4px 0;"><span>IVA (23%)</span><strong>' . htmlspecialchars(am_formatar_euro($calc['valor_iva']), ENT_QUOTES, 'UTF-8') . '</strong></div>';
            if ($comparacao['tem_orcamento']) {
                $html .= '<div style="display:flex;justify-content:space-between;margin:8px 0 4px;color:' . $mutedColor . ';"><span>Orçamento ao cliente (c/ IVA)</span><strong>' . htmlspecialchars(am_formatar_euro($comparacao['orcamento_com_iva']), ENT_QUOTES, 'UTF-8') . '</strong></div>';
            }
            $html .= '<div class="fatura-intervencao-total" style="display:flex;justify-content:space-between;margin:8px 0 0;font-size:15px;color:' . $textColor . ';"><span>' . htmlspecialchars($labelTotal, ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars(am_formatar_euro($calc['valor_total_com_iva']), ENT_QUOTES, 'UTF-8') . '</strong></div>';
            if ($comparacao['tem_orcamento']) {
                $html .= '<div class="fatura-intervencao-cmp fatura-intervencao-cmp--' . htmlspecialchars($comparacao['status'], ENT_QUOTES, 'UTF-8') . '"><strong>' . htmlspecialchars($comparacao['rotulo'], ENT_QUOTES, 'UTF-8') . '</strong><span> — ' . htmlspecialchars($comparacao['mensagem'], ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            $html .= '</div>';
        }

        if ($notas !== '') {
            $html .= '<p style="margin:12px 0 0;font-size:13px;color:' . $textColor . ';"><strong>Notas da oficina:</strong> ';
            $html .= nl2br(htmlspecialchars($notas, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('am_servico_estado_pagamento_norm')) {
    function am_servico_estado_pagamento_norm(?string $estado): string
    {
        $estado = strtolower(trim((string) $estado));
        if (in_array($estado, ['pago', 'paga', 'paid'], true)) {
            return 'pago';
        }
        if ($estado === 'pendente_complemento') {
            return 'pendente_complemento';
        }
        if (in_array($estado, ['aguarda_trabalho', 'aguarda_conclusao', 'sem_pagamento'], true)) {
            return 'aguarda_trabalho';
        }

        return $estado !== '' ? $estado : 'aguarda_trabalho';
    }
}

if (!function_exists('am_orcamento_cliente_aceite')) {
    function am_orcamento_cliente_aceite(?string $estado): bool
    {
        $e = strtolower(trim((string) $estado));

        return in_array($e, ['aceite', 'aceito'], true);
    }
}

if (!function_exists('am_servico_permite_pagina_pagamento')) {
    /**
     * O cliente pode abrir pagamento_orcamento.php (orçamento aceite ou trabalho concluído com valor em dívida).
     */
    function am_servico_permite_pagina_pagamento(array $servico): bool
    {
        $estado_orc = strtolower(trim((string) ($servico['estado_orcamento'] ?? '')));
        $estado_orc_cliente = strtolower(trim((string) ($servico['estado_orcamento_cliente'] ?? '')));

        if ($estado_orc === 'aprovado' && am_orcamento_cliente_aceite($estado_orc_cliente)) {
            return true;
        }

        if (
            am_servico_esta_concluido($servico)
            && am_servico_tem_fecho_mecanico($servico)
            && am_servico_precisa_pagar_trabalho_mecanico($servico)
        ) {
            return true;
        }

        if (
            am_servico_esta_concluido($servico)
            && am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null) === 'pago'
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('am_servico_tem_fecho_mecanico')) {
    function am_servico_tem_fecho_mecanico(array $servico): bool
    {
        if (!empty($servico['fatura_linhas_json'])) {
            $dec = json_decode((string) $servico['fatura_linhas_json'], true);
            if (is_array($dec) && ($dec['origem'] ?? '') === 'fecho_mecanico') {
                return true;
            }
        }

        return am_fatura_intervencao_tem_dados($servico);
    }
}

if (!function_exists('am_servico_fecho_json_dec')) {
    function am_servico_fecho_json_dec(array $servico): ?array
    {
        if (empty($servico['fatura_linhas_json'])) {
            return null;
        }
        $dec = json_decode((string) $servico['fatura_linhas_json'], true);

        return is_array($dec) ? $dec : null;
    }
}

if (!function_exists('am_servico_tem_fecho_json_mecanico')) {
    function am_servico_tem_fecho_json_mecanico(array $servico): bool
    {
        $dec = am_servico_fecho_json_dec($servico);

        return is_array($dec) && ($dec['origem'] ?? '') === 'fecho_mecanico';
    }
}

if (!function_exists('am_servico_pecas_do_fecho')) {
    /**
     * @return array<int, array{nome: string, preco: float|null}>
     */
    function am_servico_pecas_do_fecho(array $servico): array
    {
        $pecas = am_pecas_substituidas_parse_struct($servico['pecas_substituidas'] ?? null);
        if ($pecas !== []) {
            return $pecas;
        }

        $dec = am_servico_fecho_json_dec($servico);
        if (!is_array($dec) || empty($dec['linhas']) || !is_array($dec['linhas'])) {
            return [];
        }

        $pecas = [];
        foreach ($dec['linhas'] as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $cat = strtolower(trim((string) ($ln['categoria'] ?? '')));
            if ($cat !== '' && $cat !== 'peça' && $cat !== 'peca') {
                continue;
            }
            if ($cat === '' && stripos((string) ($ln['descricao'] ?? ''), 'mão de obra') !== false) {
                continue;
            }
            $nome = trim((string) ($ln['descricao'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $preco = isset($ln['total_sem_iva']) ? round((float) $ln['total_sem_iva'], 2) : null;
            $pecas[] = ['nome' => $nome, 'preco' => $preco];
        }

        return $pecas;
    }
}

if (!function_exists('am_servico_valores_fiscais_liquidados')) {
    /**
     * Valores fiscais já liquidados (pagamento concluído) — só para estado de pagamento.
     *
     * @return array{subtotal_sem_iva: float, valor_iva: float, valor_total_com_iva: float}|null
     */
    function am_servico_valores_fiscais_liquidados(array $servico): ?array
    {
        if (am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null) !== 'pago') {
            return null;
        }

        $total = round((float) ($servico['valor_pago_total'] ?? 0), 2);
        if ($total <= 0) {
            $total = round((float) ($servico['valor_total_com_iva'] ?? 0), 2);
        }
        if ($total <= 0) {
            return null;
        }

        $subtotal = round((float) ($servico['subtotal_sem_iva'] ?? 0), 2);
        $iva = round((float) ($servico['valor_iva'] ?? 0), 2);
        if ($subtotal > 0 && abs($subtotal + $iva - $total) < 0.05) {
            return [
                'subtotal_sem_iva' => $subtotal,
                'valor_iva' => $iva,
                'valor_total_com_iva' => $total,
            ];
        }

        $subtotal = round($total / 1.23, 2);
        $iva = round($total - $subtotal, 2);

        return [
            'subtotal_sem_iva' => $subtotal,
            'valor_iva' => $iva,
            'valor_total_com_iva' => $total,
        ];
    }
}

if (!function_exists('am_servico_calc_fecho_fatura')) {
    /**
     * Total da fatura = mão de obra (horas × €/h) + peças + IVA.
     *
     * @return array{
     *   linhas: array<int, array<string, mixed>>,
     *   subtotal_sem_iva: float,
     *   valor_iva: float,
     *   valor_total_com_iva: float,
     *   horas: float|null,
     *   valor_hora: float,
     *   pecas: array<int, array{nome: string, preco: float|null}>,
     *   origem: string
     * }
     */
    function am_servico_calc_fecho_fatura(array $servico): array
    {
        $dec = am_servico_fecho_json_dec($servico);
        $temFechoJson = is_array($dec) && ($dec['origem'] ?? '') === 'fecho_mecanico';

        $horas = am_servico_horas_para_fatura($servico);
        $valorHora = am_servico_valor_hora($servico);

        if ($temFechoJson) {
            if (isset($dec['horas']) && (float) $dec['horas'] > 0) {
                $horas = round((float) $dec['horas'], 2);
            }
            if (isset($dec['valor_hora']) && (float) $dec['valor_hora'] > 0) {
                $valorHora = round((float) $dec['valor_hora'], 2);
            }
        }

        $horasCalc = $horas ?? 0.0;
        $pecas = am_servico_pecas_do_fecho($servico);

        if ($temFechoJson && isset($dec['valor_total_com_iva']) && (float) $dec['valor_total_com_iva'] > 0) {
            $linhasJson = (!empty($dec['linhas']) && is_array($dec['linhas']))
                ? $dec['linhas']
                : am_fecho_fatura_montar($horasCalc, $valorHora, $pecas)['linhas'];

            return [
                'linhas' => $linhasJson,
                'subtotal_sem_iva' => round((float) ($dec['subtotal_sem_iva'] ?? 0), 2),
                'valor_iva' => round((float) ($dec['valor_iva'] ?? 0), 2),
                'valor_total_com_iva' => round((float) $dec['valor_total_com_iva'], 2),
                'horas' => $horasCalc > 0 ? $horasCalc : null,
                'valor_hora' => $valorHora,
                'pecas' => $pecas,
                'origem' => 'fecho_mecanico',
            ];
        }

        $calc = am_fecho_fatura_montar($horasCalc, $valorHora, $pecas);

        return [
            'linhas' => $calc['linhas'],
            'subtotal_sem_iva' => $calc['subtotal_sem_iva'],
            'valor_iva' => $calc['valor_iva'],
            'valor_total_com_iva' => $calc['valor_total_com_iva'],
            'horas' => $horasCalc > 0 ? $horasCalc : null,
            'valor_hora' => $valorHora,
            'pecas' => $pecas,
            'origem' => $temFechoJson ? 'fecho_mecanico' : 'mecanico',
        ];
    }
}

if (!function_exists('am_servico_valores_fiscais_fatura')) {
    /**
     * Valores fiscais coerentes (subtotal, IVA, total) — prioriza fecho do mecânico.
     *
     * @return array{subtotal_sem_iva: float, valor_iva: float, valor_total_com_iva: float}
     */
    function am_servico_valores_fiscais_fatura(array $servico): array
    {
        $calc = am_servico_calc_fecho_fatura($servico);

        return [
            'subtotal_sem_iva' => $calc['subtotal_sem_iva'],
            'valor_iva' => $calc['valor_iva'],
            'valor_total_com_iva' => $calc['valor_total_com_iva'],
        ];
    }
}

if (!function_exists('am_servico_total_fatura_final')) {
    /**
     * Total registado pelo mecânico na conclusão (horas + peças + IVA).
     */
    function am_servico_total_fatura_final(array $servico): float
    {
        if (am_servico_tem_fecho_json_mecanico($servico)) {
            $dec = am_servico_fecho_json_dec($servico);
            if (is_array($dec) && (float) ($dec['valor_total_com_iva'] ?? 0) > 0) {
                return round((float) $dec['valor_total_com_iva'], 2);
            }
        }

        if (!am_servico_tem_fecho_mecanico($servico)) {
            return 0.0;
        }

        $valores = am_servico_valores_fiscais_fatura($servico);

        return (float) $valores['valor_total_com_iva'];
    }
}

if (!function_exists('am_servico_valor_exibicao_site')) {
    /**
     * Valor a mostrar em cards, listas e tracking: total do mecânico (c/ IVA) se existir; senão orçamento do chefe (s/ IVA).
     */
    function am_servico_valor_exibicao_site(array $servico): float
    {
        $totalMecanico = am_servico_total_fatura_final($servico);
        if ($totalMecanico > 0.009) {
            return $totalMecanico;
        }

        return am_servico_valor_orcamento_aprovado($servico);
    }
}

if (!function_exists('am_servico_reparar_valores_fiscais_se_inconsistentes')) {
    /**
     * Corrige na BD subtotal/IVA/total quando o fecho do mecânico não foi gravado nos campos fiscais.
     *
     * @return array<string, mixed>|null Dados atualizados ou null se não foi necessário reparar
     */
    function am_servico_reparar_valores_fiscais_se_inconsistentes(mysqli $con, int $servico_id, array $servico, string $idCol = 'id'): ?array
    {
        if ($servico_id <= 0) {
            return null;
        }

        if (!am_servico_tem_fecho_mecanico($servico)) {
            return null;
        }

        $corretos = am_servico_valores_fiscais_fatura($servico);
        if ($corretos['valor_total_com_iva'] <= 0) {
            return null;
        }

        $calc = am_servico_calc_fecho_fatura($servico);
        $horasOk = $calc['horas'];
        $valorHoraOk = $calc['valor_hora'];
        $pecasOk = $calc['pecas'];

        $dbSub = round((float) ($servico['subtotal_sem_iva'] ?? 0), 2);
        $dbIva = round((float) ($servico['valor_iva'] ?? 0), 2);
        $dbTotal = round((float) ($servico['valor_total_com_iva'] ?? 0), 2);
        $dbCusto = round((float) ($servico['custo'] ?? 0), 2);
        $precisaFiscal = abs($dbSub - $corretos['subtotal_sem_iva']) > 0.02
            || abs($dbIva - $corretos['valor_iva']) > 0.02
            || abs($dbTotal - $corretos['valor_total_com_iva']) > 0.02
            || abs($dbCusto - $corretos['valor_total_com_iva']) > 0.02;

        $dbHoras = round((float) ($servico['horas_trabalhadas'] ?? 0), 2);
        $dbValorHora = round((float) ($servico['valor_hora'] ?? 0), 2);
        $precisaHoras = $horasOk !== null
            && ($dbHoras <= 0 || abs($dbHoras - $horasOk) > 0.05 || abs($dbValorHora - $valorHoraOk) > 0.05);

        if (!$precisaFiscal && !$precisaHoras) {
            return null;
        }

        am_ensure_servico_fecho_cols($con);
        $sets = [];
        $types = '';
        $vals = [];

        if ($precisaFiscal || $precisaHoras) {
            $sets[] = 'subtotal_sem_iva = ?';
            $types .= 'd';
            $vals[] = $corretos['subtotal_sem_iva'];
            $sets[] = 'valor_iva = ?';
            $types .= 'd';
            $vals[] = $corretos['valor_iva'];
            $sets[] = 'valor_total_com_iva = ?';
            $types .= 'd';
            $vals[] = $corretos['valor_total_com_iva'];
            $sets[] = 'custo = ?';
            $types .= 'd';
            $vals[] = $corretos['valor_total_com_iva'];
        }
        if ($precisaHoras && $horasOk !== null) {
            $sets[] = 'horas_trabalhadas = ?';
            $types .= 'd';
            $vals[] = $horasOk;
            $sets[] = 'valor_hora = ?';
            $types .= 'd';
            $vals[] = $valorHoraOk;
        }
        if (($precisaFiscal || $precisaHoras) && $horasOk !== null) {
            $fjson = am_fecho_fatura_json_snapshot($horasOk, $valorHoraOk, $pecasOk);
            $sets[] = 'fatura_linhas_json = ?';
            $types .= 's';
            $vals[] = $fjson;
        }

        if ($sets === []) {
            return null;
        }

        $sql = 'UPDATE servico SET ' . implode(', ', $sets) . " WHERE $idCol = ? LIMIT 1";
        $types .= 'i';
        $vals[] = $servico_id;
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $bindParams = array_merge([$types], $vals);
        $refs = [];
        foreach ($bindParams as $k => $v) {
            $refs[$k] = &$bindParams[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        if (!$stmt->execute()) {
            $stmt->close();

            return null;
        }
        $stmt->close();

        $atualizado = array_merge($servico, [
            'subtotal_sem_iva' => $corretos['subtotal_sem_iva'],
            'valor_iva' => $corretos['valor_iva'],
            'valor_total_com_iva' => $corretos['valor_total_com_iva'],
            'custo' => $corretos['valor_total_com_iva'],
        ]);
        if ($precisaHoras && $horasOk !== null) {
            $atualizado['horas_trabalhadas'] = $horasOk;
            $atualizado['valor_hora'] = $valorHoraOk;
        }
        if (($precisaFiscal || $precisaHoras) && $horasOk !== null) {
            $atualizado['fatura_linhas_json'] = am_fecho_fatura_json_snapshot($horasOk, $valorHoraOk, $pecasOk);
        }

        return $atualizado;
    }
}

if (!function_exists('am_servico_esta_concluido')) {
    function am_servico_esta_concluido(array $servico): bool
    {
        $estado = strtolower(trim((string) ($servico['estado_do_trabalho'] ?? $servico['estado'] ?? '')));

        return strpos($estado, 'concl') !== false || strpos($estado, 'pronto') !== false;
    }
}

if (!function_exists('am_servico_valor_orcamento_aprovado')) {
    /** Valor do orçamento definido pelo chefe de oficina (s/ IVA), como aparece no detalhe. */
    function am_servico_valor_orcamento_aprovado(array $servico): float
    {
        $orcamento = round((float) ($servico['orcamento_aprovado'] ?? 0), 2);

        return $orcamento > 0 ? $orcamento : 0.0;
    }
}

if (!function_exists('am_servico_valor_orcamento_estimado_com_iva')) {
    /** Orçamento aprovado com IVA (apenas para comparações fiscais internas). */
    function am_servico_valor_orcamento_estimado_com_iva(array $servico): float
    {
        $orcamento = am_servico_valor_orcamento_aprovado($servico);
        if ($orcamento <= 0) {
            return 0.0;
        }

        return round($orcamento * 1.23, 2);
    }
}

if (!function_exists('am_servico_valor_orcamento_a_pagar')) {
    function am_servico_valor_orcamento_a_pagar(array $servico): float
    {
        return 0.0;
    }
}

if (!function_exists('am_servico_valor_complemento_a_pagar')) {
    /**
     * Valor em falta do trabalho que o mecânico registou na conclusão (total final − já pago).
     */
    function am_servico_valor_complemento_a_pagar(array $servico): float
    {
        if (!am_servico_esta_concluido($servico)) {
            return 0.0;
        }
        if (!am_servico_tem_fecho_json_mecanico($servico)) {
            return 0.0;
        }
        $totalFinal = am_servico_total_fatura_final($servico);
        $jaPago = am_servico_valor_ja_pago($servico);
        if ($totalFinal <= $jaPago + 0.009) {
            return 0.0;
        }

        return round($totalFinal - $jaPago, 2);
    }
}

if (!function_exists('am_servico_valor_trabalho_mecanico_a_pagar')) {
    function am_servico_valor_trabalho_mecanico_a_pagar(array $servico): float
    {
        return am_servico_valor_complemento_a_pagar($servico);
    }
}

if (!function_exists('am_servico_tem_registo_pagamento')) {
    function am_servico_tem_registo_pagamento(array $servico): bool
    {
        $metodo = trim((string) ($servico['metodo_pagamento'] ?? ''));
        if ($metodo !== '') {
            return true;
        }
        if (!empty($servico['data_pagamento'])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('am_servico_valor_ja_pago')) {
    /**
     * Valor já liquidado pelo cliente — só conta com registo real de pagamento.
     */
    function am_servico_valor_ja_pago(array $servico): float
    {
        $valor = round((float) ($servico['valor_pago_total'] ?? 0), 2);
        if ($valor <= 0) {
            return 0.0;
        }

        $estado = am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null);
        $temRegisto = am_servico_tem_registo_pagamento($servico);

        if ($temRegisto) {
            return $valor;
        }

        if ($estado === 'pendente_complemento' && $valor > 0) {
            return $valor;
        }

        return 0.0;
    }
}

if (!function_exists('am_servico_valor_a_pagar')) {
    function am_servico_valor_a_pagar(array $servico): float
    {
        return am_servico_valor_complemento_a_pagar($servico);
    }
}

if (!function_exists('am_servico_precisa_pagamento_inicial')) {
    function am_servico_precisa_pagamento_inicial(array $servico): bool
    {
        return false;
    }
}

if (!function_exists('am_servico_precisa_pagamento_complemento')) {
    function am_servico_precisa_pagamento_complemento(array $servico): bool
    {
        return am_servico_aguarda_pagamento_trabalho($servico);
    }
}

if (!function_exists('am_servico_aguarda_pagamento_trabalho')) {
    /**
     * Pagamento do trabalho na oficina — só após conclusão e fecho do mecânico.
     * A taxa de cancelamento da marcação não entra aqui.
     */
    function am_servico_aguarda_pagamento_trabalho(array $servico): bool
    {
        if (!am_servico_esta_concluido($servico)) {
            return false;
        }
        if (!am_servico_tem_fecho_json_mecanico($servico)) {
            return false;
        }
        if (function_exists('am_servico_excesso_orcamento_pendente') && am_servico_excesso_orcamento_pendente($servico)) {
            return false;
        }
        if (function_exists('am_servico_excesso_orcamento_recusado') && am_servico_excesso_orcamento_recusado($servico)) {
            return false;
        }

        return am_servico_valor_complemento_a_pagar($servico) > 0.009;
    }
}

if (!function_exists('am_servico_precisa_pagar_trabalho_mecanico')) {
    function am_servico_precisa_pagar_trabalho_mecanico(array $servico): bool
    {
        return am_servico_aguarda_pagamento_trabalho($servico);
    }
}

if (!function_exists('am_ensure_estado_pagamento_varchar')) {
    /**
     * Garante que estado_pagamento aceita valores como aguarda_trabalho (não só ENUM antigo Pendente/Pago).
     */
    function am_ensure_estado_pagamento_varchar(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $r = @$con->query("SHOW COLUMNS FROM servico LIKE 'estado_pagamento'");
        if (!$r || !($col = $r->fetch_assoc())) {
            return;
        }

        $type = strtolower((string) ($col['Type'] ?? ''));
        $needsWiden = (strpos($type, 'enum') !== false);
        if (!$needsWiden && preg_match('/varchar\((\d+)\)/', $type, $m)) {
            $needsWiden = ((int) $m[1]) < 32;
        }
        if ($needsWiden) {
            @$con->query('ALTER TABLE servico MODIFY COLUMN estado_pagamento VARCHAR(32) NULL DEFAULT NULL');
        }
    }
}

if (!function_exists('am_cliente_email_canonico')) {
  /**
   * Email atual do cliente na BD (evita divergência com a sessão).
   */
    function am_cliente_email_canonico(mysqli $con, int $id_utilizador, string $email_sessao = ''): string
    {
        $email_sessao = trim($email_sessao);
        if ($id_utilizador > 0) {
            $st = $con->prepare('SELECT email FROM utilizador WHERE id_utilizador = ? LIMIT 1');
            if ($st) {
                $st->bind_param('i', $id_utilizador);
                if ($st->execute()) {
                    $row = $st->get_result()->fetch_assoc();
                    $emailDb = trim((string) ($row['email'] ?? ''));
                    if ($emailDb !== '') {
                        $st->close();

                        return $emailDb;
                    }
                }
                $st->close();
            }
        }

        return $email_sessao;
    }
}

if (!function_exists('am_cliente_carregar_servico_por_id')) {
  /**
   * Carrega um serviço pertencente ao cliente autenticado (mesma lógica que marcações/pagamento).
   */
    function am_cliente_carregar_servico_por_id(mysqli $con, int $servico_id, int $id_utilizador, string $email_utilizador): ?array
    {
        if ($servico_id <= 0) {
            return null;
        }

        $email_utilizador = trim($email_utilizador);
        $tentativas = [];

        if ($email_utilizador !== '') {
            $tentativas[] = [
                'sql' => 'SELECT s.* FROM servico s WHERE s.id = ? AND s.email = ? LIMIT 1',
                'types' => 'is',
                'params' => [$servico_id, $email_utilizador],
            ];
            $tentativas[] = [
                'sql' => "SELECT s.* FROM servico s WHERE s.id = ? AND LOWER(TRIM(COALESCE(s.email, ''))) = LOWER(TRIM(?)) LIMIT 1",
                'types' => 'is',
                'params' => [$servico_id, $email_utilizador],
            ];
        }

        static $temColUid = null;
        if ($temColUid === null) {
            $chkUid = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_utilizador'");
            $temColUid = ($chkUid && $chkUid->num_rows > 0);
        }

        if ($temColUid && $id_utilizador > 0) {
            $tentativas[] = [
                'sql' => 'SELECT s.* FROM servico s WHERE s.id = ? AND s.id_utilizador = ? LIMIT 1',
                'types' => 'ii',
                'params' => [$servico_id, $id_utilizador],
            ];
            if ($email_utilizador !== '') {
                $tentativas[] = [
                    'sql' => 'SELECT s.* FROM servico s WHERE s.id = ? AND (s.email = ? OR s.id_utilizador = ?) LIMIT 1',
                    'types' => 'isi',
                    'params' => [$servico_id, $email_utilizador, $id_utilizador],
                ];
            }
        }

        foreach ($tentativas as $tentativa) {
            $st = $con->prepare($tentativa['sql']);
            if (!$st) {
                continue;
            }
            $types = $tentativa['types'];
            $params = $tentativa['params'];
            $bind = [$types];
            foreach ($params as $i => $valor) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$st, 'bind_param'], $bind);
            if ($st->execute()) {
                $res = $st->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $st->close();
                if (is_array($row)) {
                    return $row;
                }
            } else {
                $st->close();
            }
        }

        return null;
    }
}

if (!function_exists('am_servico_cliente_aceitar_orcamento')) {
    /**
     * Cliente aceita orçamento aprovado (com ownership por email e/ou id_utilizador).
     *
     * @return array{ok:bool,error?:string,servico?:array}
     */
    function am_servico_cliente_aceitar_orcamento(mysqli $con, int $servicoId, int $idUtilizador, string $email): array
    {
        if ($servicoId <= 0) {
            return ['ok' => false, 'error' => 'Serviço inválido.'];
        }

        am_ensure_estado_pagamento_varchar($con);

        $email = am_cliente_email_canonico($con, $idUtilizador, trim($email));
        $servico = am_cliente_carregar_servico_por_id($con, $servicoId, $idUtilizador, $email);
        if (!$servico) {
            return ['ok' => false, 'error' => 'Serviço não encontrado.'];
        }

        $estadoOrc = strtolower(trim((string) ($servico['estado_orcamento'] ?? '')));
        $orcAprovado = (float) ($servico['orcamento_aprovado'] ?? 0);
        if ($estadoOrc !== 'aprovado' && $orcAprovado <= 0) {
            return ['ok' => false, 'error' => 'Este orçamento ainda não está disponível para aceitação.'];
        }

        $estadoCliente = strtolower(trim((string) ($servico['estado_orcamento_cliente'] ?? 'pendente')));
        if (am_orcamento_cliente_aceite($estadoCliente)) {
            return ['ok' => false, 'error' => 'Este orçamento já foi aceite.'];
        }
        if ($estadoCliente === 'recusado') {
            return ['ok' => false, 'error' => 'Este orçamento foi recusado.'];
        }

        $up = $con->prepare("
            UPDATE servico
            SET estado_orcamento_cliente = 'aceite',
                data_orcamento_aceite = NOW(),
                estado_pagamento = 'aguarda_trabalho',
                aviso_chefe_orcamento_aceite = 0
            WHERE id = ?
              AND LOWER(COALESCE(estado_orcamento_cliente, 'pendente')) NOT IN ('aceite', 'aceito', 'recusado')
              AND (
                LOWER(COALESCE(estado_orcamento, '')) = 'aprovado'
                OR (orcamento_aprovado IS NOT NULL AND orcamento_aprovado > 0)
              )
        ");
        if (!$up) {
            return ['ok' => false, 'error' => 'Erro ao preparar aceitação do orçamento.'];
        }
        $up->bind_param('i', $servicoId);
        if (!$up->execute()) {
            $err = $up->error;
            $up->close();

            return ['ok' => false, 'error' => 'Erro ao aceitar orçamento: ' . $err];
        }
        if ($up->affected_rows <= 0) {
            $up->close();

            return ['ok' => false, 'error' => 'Não foi possível aceitar este orçamento. O estado pode ter sido alterado entretanto.'];
        }
        $up->close();

        $servico['estado_orcamento_cliente'] = 'aceite';
        $servico['estado_pagamento'] = 'aguarda_trabalho';

        return ['ok' => true, 'servico' => $servico];
    }
}

if (!function_exists('am_servico_aguarda_fatura_final_mecanico')) {
    function am_servico_aguarda_fatura_final_mecanico(array $servico): bool
    {
        if (!am_servico_esta_concluido($servico)) {
            return false;
        }
        if (am_servico_tem_fecho_mecanico($servico)) {
            return false;
        }
        if (am_servico_precisa_pagamento_complemento($servico)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('am_servico_sincronizar_divida_pagamento')) {
    function am_servico_sincronizar_divida_pagamento(mysqli $con, int $servicoId, string $idCol = 'id'): ?array
    {
        if ($servicoId <= 0 || ($idCol !== 'id' && $idCol !== 'id_servico')) {
            return null;
        }
        am_ensure_servico_fecho_cols($con);
        $st = $con->prepare("SELECT * FROM servico WHERE $idCol = ? LIMIT 1");
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $servicoId);
        if (!$st->execute()) {
            $st->close();

            return null;
        }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return null;
        }
        $aPagar = am_servico_valor_complemento_a_pagar($row);
        $jaPago = am_servico_valor_ja_pago($row);
        $estado = am_servico_estado_pagamento_norm($row['estado_pagamento'] ?? null);
        if ($aPagar > 0.009) {
            $novoEstado = 'pendente_complemento';
        } elseif ($jaPago > 0.009 && am_servico_tem_registo_pagamento($row)) {
            $novoEstado = 'pago';
        } elseif (am_servico_total_fatura_final($row) > 0.009 && am_servico_esta_concluido($row) && am_servico_tem_fecho_json_mecanico($row)) {
            $novoEstado = 'pendente_complemento';
        } elseif ($jaPago > 0.009) {
            $novoEstado = 'pendente_complemento';
        } elseif (!am_servico_esta_concluido($row)) {
            $novoEstado = 'aguarda_trabalho';
        } else {
            $novoEstado = $estado !== '' ? $estado : 'aguarda_trabalho';
        }
        $up = $con->prepare("UPDATE servico SET valor_pago_total = ?, valor_em_divida = ?, estado_pagamento = ? WHERE $idCol = ?");
        if ($up) {
            $divida = $aPagar > 0.009 ? $aPagar : 0.0;
            $up->bind_param('ddsi', $jaPago, $divida, $novoEstado, $servicoId);
            $up->execute();
            $up->close();
        }

        return [
            'valor_pago_total' => $jaPago,
            'valor_em_divida' => $aPagar > 0.009 ? $aPagar : 0.0,
            'estado_pagamento' => $novoEstado,
        ];
    }
}

if (!function_exists('am_servico_precisa_pagamento')) {
    function am_servico_precisa_pagamento(array $servico): bool
    {
        return am_servico_precisa_pagamento_complemento($servico);
    }
}

if (!function_exists('am_servico_pagamento_e_complementar')) {
    function am_servico_pagamento_e_complementar(array $servico): bool
    {
        return am_servico_precisa_pagamento_complemento($servico);
    }
}

if (!function_exists('am_servico_atribuir_numero_fatura')) {
    function am_servico_atribuir_numero_fatura(mysqli $con, int $servico_id, string $idCol = 'id'): ?string
    {
        if ($servico_id <= 0 || ($idCol !== 'id' && $idCol !== 'id_servico')) {
            return null;
        }
        $st = $con->prepare("SELECT numero_fatura FROM servico WHERE $idCol = ? LIMIT 1");
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $servico_id);
        $st->execute();
        $r = $st->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        $st->close();
        $atual = trim((string) ($row['numero_fatura'] ?? ''));
        if ($atual !== '') {
            return $atual;
        }
        $numero = 'F' . date('Ymd') . '-' . $servico_id;
        $up = $con->prepare("UPDATE servico SET numero_fatura = ? WHERE $idCol = ?");
        if ($up) {
            $up->bind_param('si', $numero, $servico_id);
            $up->execute();
            $up->close();
        }

        return $numero;
    }
}

if (!function_exists('am_servico_guardar_fecho_fatura')) {
    /**
     * @param array<int, array{nome: string, preco: float|null}> $pecas
     */
    function am_servico_guardar_fecho_fatura(mysqli $con, int $servico_id, string $idCol, float $horas, float $valorHora, array $pecas): bool
    {
        if ($servico_id <= 0) {
            return false;
        }
        am_ensure_servico_fecho_cols($con);
        $cols = [];
        $q = @$con->query('SHOW COLUMNS FROM servico');
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                if (!empty($row['Field'])) {
                    $cols[$row['Field']] = true;
                }
            }
        }

        $calc = am_fecho_fatura_montar($horas, $valorHora, $pecas);
        $rowAtual = null;
        $qAtual = $con->prepare("SELECT estado_pagamento, valor_pago_total, valor_total_com_iva, orcamento_aprovado FROM servico WHERE $idCol = ? LIMIT 1");
        if ($qAtual) {
            $qAtual->bind_param('i', $servico_id);
            if ($qAtual->execute()) {
                $rAtual = $qAtual->get_result();
                $rowAtual = $rAtual ? $rAtual->fetch_assoc() : null;
            }
            $qAtual->close();
        }
        $estadoPagamentoAtual = am_servico_estado_pagamento_norm($rowAtual['estado_pagamento'] ?? null);
        $valorPagoTotal = am_servico_valor_ja_pago(is_array($rowAtual) ? $rowAtual : []);
        $valorEmDivida = max(0, round((float) $calc['valor_total_com_iva'] - $valorPagoTotal, 2));
        if ($valorEmDivida > 0.009) {
            $novoEstadoPagamento = 'pendente_complemento';
        } elseif ($valorPagoTotal > 0.009 && am_servico_tem_registo_pagamento(is_array($rowAtual) ? $rowAtual : [])) {
            $novoEstadoPagamento = 'pago';
        } elseif ($calc['valor_total_com_iva'] > 0.009) {
            $novoEstadoPagamento = 'pendente_complemento';
        } elseif (in_array($estadoPagamentoAtual, ['pendente', 'pendente_complemento'], true)) {
            $novoEstadoPagamento = $estadoPagamentoAtual;
        } else {
            $novoEstadoPagamento = $estadoPagamentoAtual !== '' ? $estadoPagamentoAtual : 'pendente';
        }
        $fjson = am_fecho_fatura_json_snapshot($horas, $valorHora, $pecas);
        $pecasJson = json_encode(array_map(static function (array $p): array {
            return [
                'nome' => $p['nome'],
                'preco' => round(max(0, (float) ($p['preco'] ?? 0)), 2),
            ];
        }, $pecas), JSON_UNESCAPED_UNICODE);

        $set = [];
        $types = '';
        $vals = [];

        if (isset($cols['fatura_linhas_json'])) {
            $set[] = 'fatura_linhas_json = ?';
            $types .= 's';
            $vals[] = $fjson;
        }
        if (isset($cols['subtotal_sem_iva'])) {
            $set[] = 'subtotal_sem_iva = ?';
            $types .= 'd';
            $vals[] = $calc['subtotal_sem_iva'];
        }
        if (isset($cols['valor_iva'])) {
            $set[] = 'valor_iva = ?';
            $types .= 'd';
            $vals[] = $calc['valor_iva'];
        }
        if (isset($cols['valor_total_com_iva'])) {
            $set[] = 'valor_total_com_iva = ?';
            $types .= 'd';
            $vals[] = $calc['valor_total_com_iva'];
        }
        if (isset($cols['valor_pago_total'])) {
            $set[] = 'valor_pago_total = ?';
            $types .= 'd';
            $vals[] = $valorPagoTotal;
        }
        if (isset($cols['valor_em_divida'])) {
            $set[] = 'valor_em_divida = ?';
            $types .= 'd';
            $vals[] = $valorEmDivida;
        }
        if (isset($cols['estado_pagamento']) && $novoEstadoPagamento !== '') {
            $set[] = 'estado_pagamento = ?';
            $types .= 's';
            $vals[] = $novoEstadoPagamento;
        }
        if (isset($cols['custo'])) {
            $set[] = 'custo = ?';
            $types .= 'd';
            $vals[] = $calc['valor_total_com_iva'];
        }
        if (isset($cols['valor_hora'])) {
            $set[] = 'valor_hora = ?';
            $types .= 'd';
            $vals[] = $valorHora;
        }
        if (isset($cols['horas_trabalhadas'])) {
            $set[] = 'horas_trabalhadas = ?';
            $types .= 'd';
            $vals[] = $horas;
        }
        if (isset($cols['pecas_substituidas'])) {
            $set[] = 'pecas_substituidas = ?';
            $types .= 's';
            $vals[] = $pecasJson !== '' ? $pecasJson : null;
        }

        if ($set === []) {
            return false;
        }

        $sql = 'UPDATE servico SET ' . implode(', ', $set) . " WHERE $idCol = ?";
        $types .= 'i';
        $vals[] = $servico_id;
        $st = $con->prepare($sql);
        if (!$st) {
            return false;
        }
        $bind = array_merge([$types], $vals);
        $refs = [];
        foreach ($bind as $k => $v) {
            $refs[$k] = &$bind[$k];
        }
        call_user_func_array([$st, 'bind_param'], $refs);
        $ok = $st->execute();
        $st->close();

        if ($ok) {
            require_once __DIR__ . '/inc_excesso_orcamento.php';
            am_servico_registar_excesso_orcamento_se_aplicavel($con, $servico_id, $idCol);
        }

        return $ok;
    }
}

if (!function_exists('am_mecanico_chave_veiculo_servico')) {
    function am_mecanico_chave_veiculo_servico(array $row): string
    {
        $plate = strtoupper(preg_replace('/\s+/', '', trim((string) ($row['matricula'] ?? ''))));
        if ($plate !== '' && $plate !== '-') {
            return 'mat:' . $plate;
        }

        return 'srv:' . (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('am_mecanico_agrupar_servicos_por_veiculo')) {
    /** Agrupa linhas de serviço por matrícula para evitar o mesmo carro repetido na lista. */
    function am_mecanico_agrupar_servicos_por_veiculo(array $rows): array
    {
        $groups = [];
        $order = [];
        foreach ($rows as $row) {
            $key = am_mecanico_chave_veiculo_servico($row);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'nome' => (string) ($row['nome'] ?? 'Cliente'),
                    'matricula' => (string) ($row['matricula'] ?? '-'),
                    'email' => (string) ($row['email'] ?? ''),
                    'telefone' => (string) ($row['telefone'] ?? ''),
                    'servicos' => [],
                ];
                $order[] = $key;
            }
            $groups[$key]['servicos'][] = $row;
        }
        $out = [];
        foreach ($order as $key) {
            $out[] = $groups[$key];
        }

        return $out;
    }
}

if (!function_exists('am_sql_servico_ativo_mecanico')) {
    /** Condições SQL para serviços ainda em curso na oficina. */
    function am_sql_servico_ativo_mecanico(bool $temColDataConclusao = false): string
    {
        $sql = "
            LOWER(COALESCE(s.estado_orcamento, '')) = 'aprovado'
            AND LOWER(COALESCE(s.estado_orcamento_cliente, 'pendente')) IN ('aceite', 'aceito')
            AND LOWER(COALESCE(s.estado_marcacao, 'ativa')) <> 'cancelada'
            AND LOWER(COALESCE(s.estado_do_trabalho, '')) NOT LIKE '%cancel%'
            AND LOWER(COALESCE(s.estado_do_trabalho, 'por fazer')) NOT LIKE '%conclu%'
            AND LOWER(COALESCE(s.estado_do_trabalho, 'por fazer')) NOT LIKE '%pronto%'
            AND LOWER(COALESCE(s.estado_do_trabalho, 'por fazer')) NOT LIKE '%entreg%'
        ";
        if ($temColDataConclusao) {
            $sql .= "\n            AND s.data_conclusao IS NULL";
        }

        return $sql;
    }
}
