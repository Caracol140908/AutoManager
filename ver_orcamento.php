<?php
session_start();
require 'ligar.php';

$servico_id = $_GET['id'] ?? 0;

// Primeiro verificar a conexão
if (!$con) {
    die("Erro de conexão à base de dados.");
}

// Verificar se a tabela 'servico' existe - vamos fazer uma consulta mais simples primeiro
if (!am_tabela_existe($con, 'servico')) {
    die('Tabela de serviços não encontrada.');
}
$table_name = 'servico';

// Consulta mais simples para testar
$sql = "
    SELECT s.*, 
           u.nome_utilizador, 
           u.carro as carro_cliente
    FROM $table_name s
    LEFT JOIN utilizador u ON s.email = u.email
    WHERE s.id = ?
";

$stmt = $con->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar a consulta: " . $con->error);
}

$stmt->bind_param("i", $servico_id);

if (!$stmt->execute()) {
    die("Erro ao executar a consulta: " . $stmt->error);
}

$result = $stmt->get_result();
$servico = $result->fetch_assoc();
$stmt->close();

if (!$servico) {
    die("Orçamento não encontrado.");
}

// Para debug - ver os campos disponíveis
// echo "<pre>"; print_r($servico); echo "</pre>"; exit;

// Extrair variáveis para facilitar o uso no HTML
extract($servico);

// Garantir que todas as variáveis existem para evitar warnings
$estado_orcamento = $estado_orcamento ?? 'pendente';
$nome_utilizador = $nome_utilizador ?? $nome ?? '';
$carro_cliente = $carro_cliente ?? $matricula ?? '';
$tipo_service = $tipo_service ?? $tipo_servico ?? '';
$observacoes = $observacoes ?? '';
$custo_estimado = $custo_estimado ?? 0;
$orcamento_aprovado = $orcamento_aprovado ?? 0;
$observacoes_orcamento = $observacoes_orcamento ?? '';
$data_orcamento = $data_orcamento ?? '';
$estado = $estado ?? 'pendente';
$estado_pagamento = $estado_pagamento ?? 'Pendente';
$data_conclusao = $data_conclusao ?? '';
$custo_final = $custo_final ?? 0;
$data_final = $data_final ?? '';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento #<?= $id ?> - AUTOMANAGER</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #0a0a0a; color: #f0f0f0; }
        .container { max-width: 900px; margin: 0 auto; background: #141414; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.4); color: #f0f0f0; border: 1px solid rgba(255,107,0,0.2); }
        .header { text-align: center; border-bottom: 2px solid #ff6b00; padding-bottom: 20px; margin-bottom: 20px; color: #f0f0f0; }
        h1, h2, h3, h4, h5, h6 { color: #f0f0f0; }
        p, strong, span, div { color: #e8e8e8; }
        .status-center { text-align: center; margin: 20px 0; }
        .estado-badge { padding: 8px 16px; border-radius: 20px; font-weight: bold; color: #0a0a0a !important; }
        .estado-pendente { background: #ffc107; color: #0a0a0a; }
        .estado-aprovado { background: #28a745; color: #0a0a0a; }
        .estado-recusado { background: #dc3545; color: #fff; }
        .estado-analise { background: rgba(255,107,0,0.35); color: #fff; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-box { border: 1px solid rgba(255,107,0,0.25); padding: 15px; border-radius: 5px; color: #e8e8e8; background: #1a1a1a; }
        .valor-box { background: rgba(40,167,69,0.15); border: 2px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center; color: #f0f0f0; }
        .valor { font-size: 24px; font-weight: bold; color: #ff6b00; }
        .observacoes-box { background: rgba(255,193,7,0.12); border: 1px solid rgba(255,193,7,0.5); padding: 15px; border-radius: 5px; margin: 20px 0; color: #f0f0f0; }
        .highlight { background: rgba(255,107,0,0.12); border-color: #ff6b00; color: #f0f0f0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; color: #aaa; }
        .btn-voltar { display: inline-block; padding: 10px 20px; background: #ff6b00; color: #0a0a0a; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold; }
        .btn-voltar:hover { background: #cc5500; color: #fff; }
        @media print { 
            .btn-voltar { display: none; }
            body { color: #000000 !important; }
            * { color: #0a0a0a !important; }
        }
        
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .container {
                padding: 15px;
            }
            .header h1 {
                font-size: 1.5em;
            }
            .header p {
                font-size: 0.9em;
            }
            .info-box h3 {
                font-size: 1.1em;
            }
            .info-box p {
                font-size: 0.9em;
            }
            .valor {
                font-size: 1.5em;
            }
            .btn-voltar {
                display: block;
                width: 100%;
                text-align: center;
                margin: 10px 0;
                padding: 12px;
                box-sizing: border-box;
            }
        }
        
        /* Garantir que todo o texto seja preto */
        * {
            color: #000000;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Orçamento #<?= $id ?></h1>
            <p>AUTOMANAGER Oficina - Serviços Automóveis</p>
        </div>
        
        <div class="content">
            <div class="status-center">
                <span class="estado-badge estado-<?= $estado_orcamento ?>">
                    <?= strtoupper($estado_orcamento) ?>
                </span>
            </div>
            
            <div class="info-grid">
                <div class="info-box">
                    <h3>Informações do Cliente</h3>
                    <p><strong>Nome:</strong> <?= htmlspecialchars($nome_utilizador) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($email ?? '') ?></p>
                    <p><strong>Telefone:</strong> <?= htmlspecialchars($telefone ?? '') ?></p>
                    <p><strong>Carro:</strong> <?= htmlspecialchars($carro_cliente) ?></p>
                </div>
                
                <div class="info-box">
                    <h3>Detalhes do Serviço</h3>
                    <p><strong>Tipo de Serviço:</strong> <?= htmlspecialchars($tipo_service) ?></p>
                    <p><strong>Observações:</strong> <?= htmlspecialchars($observacoes) ?></p>
                    <p><strong>Data Desejada:</strong> <?= !empty($data_desejada) ? date('d/m/Y', strtotime($data_desejada)) : 'A definir' ?></p>
                    <p><strong>Custo Estimado Inicial:</strong> € <?= number_format($custo_estimado, 2, ',', ' ') ?></p>
                </div>
            </div>
            
            <?php if ($estado_orcamento == 'aprovado' && $orcamento_aprovado > 0): ?>
                <div class="valor-box">
                    <h3>Valor Aprovado</h3>
                    <div class="valor">€ <?= number_format($orcamento_aprovado, 2, ',', ' ') ?></div>
                    <?php if ($data_orcamento): ?>
                        <p>Data: <?= date('d/m/Y H:i', strtotime($data_orcamento)) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($observacoes_orcamento)): ?>
                <div class="observacoes-box">
                    <h3>Observações do orçamento</h3>
                    <p><?= nl2br(htmlspecialchars($observacoes_orcamento)) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>Informações Adicionais</h3>
                <p><strong>ID do Serviço:</strong> #<?= $id ?></p>
                <p><strong>Responsável:</strong> Chefe de oficina</p>
                <p><strong>Estado do Serviço:</strong> <?= ucfirst($estado) ?></p>
                <p><strong>Estado do Pagamento:</strong> <?= $estado_pagamento ?></p>
                <?php if ($data_orcamento): ?>
                    <p><strong>Data do Orçamento:</strong> <?= date('d/m/Y H:i', strtotime($data_orcamento)) ?></p>
                <?php endif; ?>
                <?php if ($data_conclusao): ?>
                    <p><strong>Data de Conclusão:</strong> <?= date('d/m/Y H:i', strtotime($data_conclusao)) ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($custo_final > 0): ?>
                <div class="info-box highlight">
                    <h3>Custo Final</h3>
                    <p><strong>Valor Final:</strong> € <?= number_format($custo_final, 2, ',', ' ') ?></p>
                    <?php if ($data_final): ?>
                        <p><strong>Data de Finalização:</strong> <?= date('d/m/Y', strtotime($data_final)) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Para mais informações, contacte-nos:</p>
            <p>Email: orcamentos@AUTOMANAGER.pt | Telefone: 252 252 252</p>
            <a href="javascript:window.print()" class="btn-voltar">Imprimir Orçamento</a>
            <a href="javascript:history.back()" class="btn-voltar">Voltar</a>
        </div>
    </div>
</body>
</html>