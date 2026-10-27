<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);

$mensagem = '';
$resultado = [];

// Limpar conversas antigas
if (isset($_POST['limpar_chats'])) {
    $dias = (int) ($_POST['dias'] ?? 30);
    $data_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
    $total = 0;
    foreach (chat_fetch_all_messages($con) as $msg) {
        if ((string) ($msg['data_envio'] ?? '') !== '' && $msg['data_envio'] < $data_limite) {
            $total++;
        }
    }
    if ($total > 0) {
        $deleted = chat_delete_older_than($con, $data_limite);
        $mensagem = $deleted > 0
            ? "[OK] Foram excluídas {$deleted} mensagens com mais de {$dias} dias."
            : '[ERRO] Erro ao excluir mensagens antigas.';
    } else {
        $mensagem = "ℹ Não há mensagens com mais de {$dias} dias para excluir.";
    }
}

$all = chat_fetch_all_messages($con);
$users = [];
$maisAntiga = null;
$maisRecente = null;
foreach ($all as $msg) {
    $uid = (int) ($msg['de_usuario_id'] ?? 0);
    if ($uid > 0) {
        $users[$uid] = true;
    }
    $when = (string) ($msg['data_envio'] ?? '');
    if ($when !== '') {
        if ($maisAntiga === null || $when < $maisAntiga) {
            $maisAntiga = $when;
        }
        if ($maisRecente === null || $when > $maisRecente) {
            $maisRecente = $when;
        }
    }
}
$stats = [
    'total_mensagens' => count($all),
    'utilizadores' => count($users),
    'mais_antiga' => $maisAntiga,
    'mais_recente' => $maisRecente,
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpar Chats Antigos - AUTOMANAGER Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0a0a0a;
            color: white;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: #1a1a1a;
            padding: 30px;
            border-radius: 10px;
            border: 2px solid #ff6b00;
        }
        
        h1 {
            color: #ff6b00;
            text-align: center;
        }
        
        .mensagem {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
        }
        
        .sucesso {
            background: #2e7d32;
            color: white;
        }
        
        .erro {
            background: #c62828;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b00;
        }
        
        .stat-label {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #ff6b00;
            font-weight: bold;
        }
        
        select, input {
            width: 100%;
            padding: 12px;
            background: #333;
            border: 1px solid #444;
            border-radius: 5px;
            color: white;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background: #ff6b00;
            color: white;
            width: 100%;
        }
        
        .btn-secondary {
            background: #333;
            color: white;
        }
        
        .btn-danger {
            background: #ff4444;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .acoes {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .warning-box {
            background: rgba(255, 152, 0, 0.1);
            border: 1px solid #ff9800;
            color: #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1> Limpar Chats Antigos</h1>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?= strpos($mensagem, '[OK]') !== false ? 'sucesso' : (strpos($mensagem, '[ERRO]') !== false ? 'erro' : '') ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_mensagens'] ?></div>
                <div class="stat-label">Total Mensagens</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['utilizadores'] ?></div>
                <div class="stat-label">Utilizadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['mais_antiga'] ? date('d/m/Y', strtotime($stats['mais_antiga'])) : 'N/A' ?></div>
                <div class="stat-label">Mais Antiga</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['mais_recente'] ? date('d/m/Y', strtotime($stats['mais_recente'])) : 'N/A' ?></div>
                <div class="stat-label">Mais Recente</div>
            </div>
        </div>
        
        <div class="warning-box">
            [AVISO] Esta ação não pode ser desfeita! Faça backup antes de prosseguir.
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Excluir mensagens com mais de:</label>
                <select name="dias" required>
                    <option value="7">7 dias</option>
                    <option value="30" selected>30 dias</option>
                    <option value="60">60 dias</option>
                    <option value="90">90 dias</option>
                    <option value="180">180 dias</option>
                    <option value="365">1 ano</option>
                </select>
            </div>
            
            <button type="submit" name="limpar_chats" class="btn btn-primary" 
                    onclick="return confirm('Tem certeza que deseja excluir mensagens antigas?')">
                 Limpar Mensagens Antigas
            </button>
        </form>
        
        <div class="acoes">
            <a href="indexadmin.php" class="btn btn-secondary">← Voltar ao Painel</a>
            <a href="chat_admin.php" class="btn btn-secondary">Ir para Chat Admin</a>
            <a href="exportar_chats.php" class="btn btn-secondary" onclick="alert('Funcionalidade em desenvolvimento')">
                [INSERIR] Exportar Chats
            </a>
        </div>
    </div>
</body>
</html>