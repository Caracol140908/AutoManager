<?php
session_start();

// Verificar se o utilizador está logado e é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

@include_once 'config_telegram.php';

$mensagem = '';
$sucesso = false;

// Testar envio de notificação
if (isset($_POST['testar_notificacao'])) {
    $tipo = $_POST['tipo'] ?? 'chat';
    
    $telegramBot = new TelegramBot();
    
    if (!$telegramBot->configurado()) {
        $mensagem = "Telegram não configurado! Configure primeiro em 'Configurar Telegram Bot'.";
    } else {
        switch ($tipo) {
            case 'chat':
                $sucesso = $telegramBot->enviarNotificacaoChat(
                    'Utilizador Teste', 
                    'Esta é uma mensagem de teste do sistema de chat.', 
                    'http://' . $_SERVER['HTTP_HOST'] . '/chat_admin.php'
                );
                break;
                
            case 'feedback':
                $sucesso = $telegramBot->enviarNotificacaoFeedback(
                    'João Silva',
                    'joao@teste.pt',
                    'Teste de Feedback',
                    'Esta é uma mensagem de teste do sistema de feedback.',
                    'http://' . $_SERVER['HTTP_HOST'] . '/indexadmin.php#feedbacks'
                );
                break;
                
            case 'agendamento':
                $sucesso = $telegramBot->enviarNotificacaoAgendamento(
                    'Maria Santos',
                    'maria@teste.pt',
                    'Revisão completa',
                    date('Y-m-d H:i:s', strtotime('+1 day')),
                    'http://' . $_SERVER['HTTP_HOST'] . '/agendamentos.php'
                );
                break;
                
            case 'sistema':
                $sucesso = $telegramBot->enviarNotificacaoSistema(
                    'Teste do Sistema',
                    'Esta é uma notificação de teste do sistema AUTOMANAGER.',
                    'sucesso'
                );
                break;
        }
        
        if ($sucesso) {
            $mensagem = "Notificação de teste enviada com sucesso! Verifique seu Telegram.";
        } else {
            $mensagem = "Falha ao enviar notificação. Verifique a configuração do Telegram.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testar Notificações Telegram</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #fff;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            border: 2px solid #ff6b00;
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.3);
        }
        
        .back-btn {
            display: inline-block;
            background: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 30px;
        }
        
        .back-btn:hover {
            background: #444;
        }
        
        h1 {
            color: #ff6b00;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }
        
        .sucesso {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            color: #4CAF50;
        }
        
        .erro {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #F44336;
            color: #F44336;
        }
        
        .status-config {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .status-on {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .status-off {
            color: #F44336;
            font-weight: bold;
        }
        
        .testes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .teste-card {
            background: #2a2a2a;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            border: 1px solid #333;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .teste-card:hover {
            border-color: #ff6b00;
            transform: translateY(-5px);
        }
        
        .teste-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .teste-chat .teste-icon { color: #2196F3; }
        .teste-feedback .teste-icon { color: #FF9800; }
        .teste-agendamento .teste-icon { color: #4CAF50; }
        .teste-sistema .teste-icon { color: #9C27B0; }
        
        .teste-titulo {
            color: #fff;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .teste-desc {
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .btn-testar {
            background: #ff6b00;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }
        
        .btn-testar:hover {
            background: #ff8533;
        }
        
        form {
            margin: 0;
        }
        
        .log {
            margin-top: 40px;
            padding: 20px;
            background: #2a2a2a;
            border-radius: 10px;
            border: 1px solid #333;
        }
        
        .log h3 {
            color: #ff6b00;
            margin-bottom: 15px;
        }
        
        .log-conteudo {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.9rem;
            color: #aaa;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .container {
                margin: 20px auto;
                padding: 15px;
            }
            .testes-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .teste-card {
                padding: 20px;
            }
            h1 {
                font-size: 1.4rem;
            }
            .back-btn {
                display: block;
                width: 100%;
                text-align: center;
                padding: 12px;
                box-sizing: border-box;
                margin-bottom: 20px;
            }
            .status-config {
                padding: 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="indexadmin.php" class="back-btn">
            ← Voltar ao Painel Admin
        </a>
        
        <h1><i class="fas fa-bell"></i> Testar Notificações Telegram</h1>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?= $sucesso ? 'sucesso' : 'erro' ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>
        
        <?php
        $telegramBot = new TelegramBot();
        $configurado = $telegramBot->configurado();
        ?>
        
        <div class="status-config">
            <h3>Status da Configuração</h3>
            <p>Telegram Bot: 
                <span class="<?= $configurado ? 'status-on' : 'status-off' ?>">
                    <?= $configurado ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> CONFIGURADO' : '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> NÃO CONFIGURADO' ?>
                </span>
            </p>
            <?php if (!$configurado): ?>
                <p style="color: #ff9800; margin-top: 10px;">
                    Configure o Telegram primeiro em <strong>Configurar Telegram Bot</strong>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="testes-grid">
            <div class="teste-card teste-chat">
                <div class="teste-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></div>
                <div class="teste-titulo">Teste de Chat</div>
                <div class="teste-desc">Simula uma nova mensagem no chat</div>
                <form method="POST">
                    <input type="hidden" name="tipo" value="chat">
                    <button type="submit" name="testar_notificacao" class="btn-testar" <?= !$configurado ? 'disabled' : '' ?>>
                        Testar Notificação
                    </button>
                </form>
            </div>
            
            <div class="teste-card teste-feedback">
                <div class="teste-icon">[NOTAS]</div>
                <div class="teste-titulo">Teste de Feedback</div>
                <div class="teste-desc">Simula novo feedback/duvida</div>
                <form method="POST">
                    <input type="hidden" name="tipo" value="feedback">
                    <button type="submit" name="testar_notificacao" class="btn-testar" <?= !$configurado ? 'disabled' : '' ?>>
                        Testar Notificação
                    </button>
                </form>
            </div>
            
            <div class="teste-card teste-agendamento">
                <div class="teste-icon">[CALENDARIO]</div>
                <div class="teste-titulo">Teste de Agendamento</div>
                <div class="teste-desc">Simula novo agendamento</div>
                <form method="POST">
                    <input type="hidden" name="tipo" value="agendamento">
                    <button type="submit" name="testar_notificacao" class="btn-testar" <?= !$configurado ? 'disabled' : '' ?>>
                        Testar Notificação
                    </button>
                </form>
            </div>
            
            <div class="teste-card teste-sistema">
                <div class="teste-icon">[CONFIG]</div>
                <div class="teste-titulo">Teste do Sistema</div>
                <div class="teste-desc">Notificação genérica do sistema</div>
                <form method="POST">
                    <input type="hidden" name="tipo" value="sistema">
                    <button type="submit" name="testar_notificacao" class="btn-testar" <?= !$configurado ? 'disabled' : '' ?>>
                        Testar Notificação
                    </button>
                </form>
            </div>
        </div>
        
        <div class="log">
            <h3><i class="fas fa-history"></i> Últimos Envios</h3>
            <div class="log-conteudo">
                <?php
                $log_file = 'telegram_log.txt';
                if (file_exists($log_file)) {
                    echo htmlspecialchars(file_get_contents($log_file));
                } else {
                    echo "Nenhum log disponível.";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>