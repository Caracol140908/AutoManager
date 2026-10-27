<?php
session_start();
// Forçar login de teste
$_SESSION['user'] = ['id_utilizador' => 1, 'nome_utilizador' => 'Teste'];
?>
<!DOCTYPE html>
<html>
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <title>Teste AI</title>
</head>
<body style="background: #1a1a1a; color: white; text-align: center; padding: 50px;">
    <h1 style="color: #FF6B00;"> Teste do AutoAssist AI</h1>
    
    <h2>Teste 1: Link Direto</h2>
    <a href="ai-assistant/ai-chat.php" target="_blank" 
       style="display: inline-block; padding: 15px 30px; background: #FF6B00; color: white; 
              text-decoration: none; border-radius: 8px; margin: 10px;">
        [LINK] Abrir AI em Nova Aba
    </a>
    
    <h2>Teste 2: JavaScript</h2>
    <button onclick="window.open('ai-assistant/ai-chat.php', '_blank', 'width=800,height=600')"
            style="padding: 15px 30px; background: #4CAF50; color: white; border: none; border-radius: 8px; margin: 10px;">
         Abrir em Nova Janela
    </button>
    
    <h2>Debug Info:</h2>
    <div style="background: #333; padding: 20px; border-radius: 8px; text-align: left; max-width: 600px; margin: 0 auto;">
        <p><strong>Session ID:</strong> <?= session_id() ?></p>
        <p><strong>User Logged:</strong> <?= isset($_SESSION['user']) ? 'SIM' : 'NÃO' ?></p>
        <p><strong>File Exists:</strong> 
            <?= file_exists('ai-assistant/ai-chat.php') ? '[OK] SIM' : '[ERRO] NÃO' ?>
        </p>
        <p><strong>File Path:</strong> <?= realpath('ai-assistant/ai-chat.php') ?: 'Não encontrado' ?></p>
    </div>
</body>
</html>