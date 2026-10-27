<?php
// ai-assistant/gerar_relatorio.php
session_start();
require '../ligar.php';

// Verificar se é admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

// Criar diretório para relatórios se não existir
$relatorios_dir = 'relatorios/';
if (!file_exists($relatorios_dir)) {
    mkdir($relatorios_dir, 0777, true);
}

// Buscar dados para relatório
$data_hoje = date('Y-m-d');
$data_mes = date('Y-m-01');

// Estatísticas via orcamento_simulacoes (tabela permitida)
$conversas_hoje = 0;
$diagnosticos_hoje = 0;
$conversas_mes = 0;
$diagnosticos_mes = 0;
$top_problemas = [];

if (am_tabela_existe($con, 'orcamento_simulacoes')) {
    $cols = am_colunas_tabela($con, 'orcamento_simulacoes');
    $dateCol = am_primeira_coluna($cols, ['criado_em', 'data_criacao', 'data_registro'], 'criado_em');
    $res = $con->query("SELECT COUNT(*) AS total FROM orcamento_simulacoes WHERE DATE(`$dateCol`) = '$data_hoje'");
    if ($res) {
        $conversas_hoje = (int) ($res->fetch_assoc()['total'] ?? 0);
        $diagnosticos_hoje = $conversas_hoje;
    }
    $res = $con->query("SELECT COUNT(*) AS total FROM orcamento_simulacoes WHERE DATE(`$dateCol`) >= '$data_mes'");
    if ($res) {
        $conversas_mes = (int) ($res->fetch_assoc()['total'] ?? 0);
        $diagnosticos_mes = $conversas_mes;
    }
}

// Gerar conteúdo HTML do relatório
$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="theme.css">
    <title>Relatório AutoAssist AI</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #ff6b00;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #ff6b00;
            margin: 0 0 10px 0;
            font-size: 1.8em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #ff6b00;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.85em;
        }
        .table-container {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .table th {
            background: #ff6b00;
            color: white;
        }
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #888;
            font-size: 0.85em;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        @media (max-width: 600px) {
            body { padding: 10px; }
            .container { padding: 15px; }
            .header h1 { font-size: 1.5em; }
            .stat-number { font-size: 1.5em; }
            .table { font-size: 0.8em; }
            .table th, .table td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>[RELATORIO] Relatório AutoAssist AI</h1>
            <p>Período: {$data_mes} até {$data_hoje}</p>
            <p>Gerado em: {$data_hoje}</p>
        </div>
        
        <h2>[GRAFICO] Estatísticas Gerais</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">{$conversas_hoje}</div>
                <div class="stat-label">Conversas Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{$diagnosticos_hoje}</div>
                <div class="stat-label">Diagnósticos Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{$conversas_mes}</div>
                <div class="stat-label">Conversas Mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{$diagnosticos_mes}</div>
                <div class="stat-label">Diagnósticos Mês</div>
            </div>
        </div>
        
        <h2>[PESQUISA] Problemas Mais Comuns</h2>
        <div class="table-container">
HTML;

if (!empty($top_problemas)) {
    $html .= '<table class="table">';
    $html .= '<tr><th>Problema</th><th>Ocorrências</th><th>Percentagem</th></tr>';
    
    foreach ($top_problemas as $problema) {
        $percentagem = round(($problema['qtd'] / max($diagnosticos_mes, 1)) * 100, 1);
        $html .= "<tr>
                    <td>{$problema['problema_identificado']}</td>
                    <td>{$problema['qtd']}</td>
                    <td>{$percentagem}%</td>
                  </tr>";
    }
    
    $html .= '</table>';
} else {
    $html .= '<p style="color: #888; text-align: center;">Sem dados de problemas registados.</p>';
}

$html .= '</div>';

$html .= <<<HTML
        <div class="footer">
            <p>AUTOMANAGER - AutoAssist AI • Relatório gerado automaticamente</p>
            <p>© 2025 AUTOMANAGER. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
HTML;

// Salvar relatório como arquivo
$filename = "relatorio_ai_" . date('Y-m-d_H-i-s') . ".html";
$filepath = $relatorios_dir . $filename;

if (file_put_contents($filepath, $html)) {
    echo json_encode([
        'success' => true,
        'message' => 'Relatório gerado com sucesso',
        'filename' => $filename
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar relatório'
    ]);
}
?>