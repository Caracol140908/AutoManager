<?php
session_start();
require 'ai-database.php';
require 'ai-estimativa.php';
require 'ai-agendamento.php';

$ai = new AutoAssistAI();
$estimativa_ai = new EstimativaAI();
$agendamento_ai = new AgendamentoAI();

// Estatísticas
$total_diagnosticos = count($_SESSION['ai_conversa'] ?? []) / 2;
$problemas_identificados = array_unique(array_column($_SESSION['ai_conversa'] ?? [], 'diagnostico'));
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AutoAssist AI</title>
    <style>
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .stat-card {
            background: rgba(255, 107, 0, 0.1);
            border: 1px solid #FF6B00;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5em;
            color: #FF6B00;
            font-weight: bold;
        }
        
        .stat-label {
            color: #aaa;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="stat-card">
            <div class="stat-number"><?= $total_diagnosticos ?></div>
            <div class="stat-label">Diagnósticos Realizados</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number">24/7</div>
            <div class="stat-label">Disponibilidade</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number">85%</div>
            <div class="stat-label">Precisão Estimada</div>
        </div>
    </div>
</body>
</html>