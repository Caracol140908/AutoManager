<?php
// ====================================================
// CONFIGURAÇÃO DO BOT TELEGRAM
// Arquivo: configurar_telegram.php
// ====================================================

// Se não estiver logado, redireciona para login
session_start();
require 'ligar.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['is_admin']) ||
    $_SESSION['is_admin'] !== true
) {
    header('Location: login.php');
    exit();
}


// Nome do arquivo onde salvar as configurações
$config_file = 'telegram_config.json';

// Carregar configurações existentes
$bot_token = '';
$chat_id = '';
$ultima_atualizacao = '';

if (file_exists($config_file)) {
    $config_json = file_get_contents($config_file);
    $config = json_decode($config_json, true);
    
    if ($config && is_array($config)) {
        $bot_token = $config['bot_token'] ?? '';
        $chat_id = $config['chat_id'] ?? '';
        $ultima_atualizacao = $config['ultima_atualizacao'] ?? '';
    }
}

// Variáveis de mensagem
$mensagem = '';
$tipo_mensagem = '';

// ================== SALVAR CONFIGURAÇÕES ==================
if (isset($_POST['salvar_config'])) {
    $bot_token = trim($_POST['bot_token'] ?? '');
    $chat_id = trim($_POST['chat_id'] ?? '');
    
    // Remover espaços extras
    $bot_token = preg_replace('/\s+/', '', $bot_token);
    $chat_id = preg_replace('/\s+/', '', $chat_id);
    
    // Validar campos
    if (empty($bot_token) || empty($chat_id)) {
        $mensagem = "Preencha todos os campos!";
        $tipo_mensagem = 'erro';
    } 
    // Validar formato básico do token
    elseif (!preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $bot_token)) {
        $mensagem = "Token inválido! O formato deve ser: NÚMEROS:letrasENúmeros";
        $tipo_mensagem = 'erro';
    }
    // Validar que chat_id é numérico
    elseif (!is_numeric($chat_id)) {
        $mensagem = "Chat ID deve conter apenas números!";
        $tipo_mensagem = 'erro';
    }
    // Tudo validado, salvar
    else {
        $config_data = [
            'bot_token' => $bot_token,
            'chat_id' => $chat_id,
            'ultima_atualizacao' => date('d/m/Y H:i:s')
        ];
        
        // Tentar salvar no arquivo JSON
        $resultado = file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT));
        
        if ($resultado !== false) {
            require_once __DIR__ . '/inc_configuracoes_kv.php';
            if (isset($con)) {
                am_cfg_set($con, 'telegram_bot_token', $bot_token);
                am_cfg_set($con, 'telegram_chat_id_admin', $chat_id);
            }
            $mensagem = "Configurações salvas com sucesso!";
            $tipo_mensagem = 'sucesso';
            $ultima_atualizacao = $config_data['ultima_atualizacao'];
        } else {
            // Tentar criar o arquivo se não existir
            $resultado = file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT), LOCK_EX);
            if ($resultado !== false) {
                $mensagem = "Configurações salvas com sucesso!";
                $tipo_mensagem = 'sucesso';
                $ultima_atualizacao = $config_data['ultima_atualizacao'];
            } else {
                $mensagem = "Erro ao salvar. Verifique permissões do servidor.";
                $tipo_mensagem = 'erro';
            }
        }
    }
}

// ================== TESTAR BOT ==================
if (isset($_POST['testar_bot'])) {
    $test_token = trim($_POST['bot_token'] ?? '');
    $test_chat_id = trim($_POST['chat_id'] ?? '');
    
    // Limpar os dados
    $test_token = preg_replace('/\s+/', '', $test_token);
    $test_chat_id = preg_replace('/\s+/', '', $test_chat_id);
    
    if (empty($test_token) || empty($test_chat_id)) {
        $mensagem = "Preencha token e chat ID primeiro!";
        $tipo_mensagem = 'erro';
    } else {
        // Testar usando cURL (mais confiável)
        function testarTelegramAPI($token, $chat_id) {
            // Testar o token primeiro
            $test_url = "https://api.telegram.org/bot{$token}/getMe";
            
            // Usar cURL se disponível
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $test_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if ($response === false || $http_code != 200) {
                    curl_close($ch);
                    return false;
                }
                
                curl_close($ch);
                $data = json_decode($response, true);
                
                if (isset($data['ok']) && $data['ok']) {
                    $bot_name = $data['result']['first_name'];
                    
                    // Agora testar enviar mensagem
                    $mensagem_teste = "Teste do Bot\n" . 
                                     "Data: " . date('d/m/Y H:i:s') . "\n" .
                                     "Bot: $bot_name\n" .
                                     "Status: CONFIGURADO COM SUCESSO!";
                    
                    $send_url = "https://api.telegram.org/bot{$token}/sendMessage";
                    $send_data = [
                        'chat_id' => $chat_id,
                        'text' => $mensagem_teste,
                        'parse_mode' => 'HTML'
                    ];
                    
                    $ch = curl_init($send_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $send_data);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $send_response = curl_exec($ch);
                    $send_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($send_response !== false && $send_http_code == 200) {
                        $send_data = json_decode($send_response, true);
                        return (isset($send_data['ok']) && $send_data['ok']) ? 'sucesso' : 'erro_envio';
                    }
                    return false;
                }
            } else {
                // Fallback para file_get_contents
                $options = [
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    'http' => ['timeout' => 10]
                ];
                $context = stream_context_create($options);
                
                $response = @file_get_contents($test_url, false, $context);
                
                if ($response !== false) {
                    $data = json_decode($response, true);
                    
                    if (isset($data['ok']) && $data['ok']) {
                        $bot_name = $data['result']['first_name'];
                        
                        $mensagem_teste = "Teste do Bot\nData: " . date('d/m/Y H:i:s') . "\nBot: $bot_name\nStatus: CONFIGURADO!";
                        $send_url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($mensagem_teste);
                        
                        $send_response = @file_get_contents($send_url, false, $context);
                        
                        if ($send_response !== false) {
                            $send_data = json_decode($send_response, true);
                            return (isset($send_data['ok']) && $send_data['ok']) ? 'sucesso' : 'erro_envio';
                        }
                    }
                }
            }
            return false;
        }
        
        $resultado_teste = testarTelegramAPI($test_token, $test_chat_id);
        
        if ($resultado_teste === 'sucesso') {
            $mensagem = "Teste realizado com sucesso! Você deve ter recebido uma mensagem no Telegram.";
            $tipo_mensagem = 'sucesso';
        } elseif ($resultado_teste === 'erro_envio') {
            $mensagem = "Chat ID incorreto! Verifique se copiou o ID correto do @userinfobot.";
            $tipo_mensagem = 'erro';
        } else {
            $mensagem = "Token inválido ou problema de conexão. Verifique seu token e internet.";
            $tipo_mensagem = 'erro';
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
    <title>Configurar Telegram Bot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* ===== ESTILO PRETO E LARANJA ===== */
        :root {
            --laranja: #FF6B00;
            --laranja-claro: #FF8C42;
            --laranja-escuro: #CC5500;
            --preto: #000000;
            --cinza-escuro: #121212;
            --cinza-medio: #1E1E1E;
            --cinza-claro: #2D2D2D;
            --branco: #FFFFFF;
            --erro: #FF4444;
            --sucesso: #44FF44;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--preto);
            color: var(--branco);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--cinza-escuro);
            border-radius: 15px;
            padding: 40px;
            border: 2px solid var(--laranja);
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.3);
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--laranja);
        }
        
        .botao-voltar {
            background: var(--laranja);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .botao-voltar:hover {
            background: var(--laranja-claro);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 0, 0.4);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        h1 {
            color: var(--laranja);
            font-size: 2.8em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .subtitulo {
            color: var(--laranja-claro);
            font-size: 1.3em;
            opacity: 0.9;
        }
        
        .status-box {
            background: var(--cinza-medio);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid var(--cinza-claro);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-text {
            color: var(--laranja-claro);
            font-size: 1.1em;
        }
        
        .status-indicator {
            background: <?php echo (!empty($bot_token) && !empty($chat_id)) ? 'var(--laranja)' : '#FF4444'; ?>;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .mensagem {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: fadeIn 0.5s;
            font-size: 1.1em;
        }
        
        .erro {
            background: linear-gradient(135deg, rgba(255, 68, 68, 0.15) 0%, rgba(204, 0, 0, 0.15) 100%);
            border: 2px solid var(--erro);
            color: #FFAAAA;
        }
        
        .sucesso {
            background: linear-gradient(135deg, rgba(68, 255, 68, 0.15) 0%, rgba(0, 204, 0, 0.15) 100%);
            border: 2px solid var(--sucesso);
            color: #AAFFAA;
        }
        
        .secao {
            background: var(--cinza-medio);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 5px solid var(--laranja);
        }
        
        h2 {
            color: var(--laranja);
            margin-bottom: 20px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .campo-input {
            width: 100%;
            padding: 18px;
            background: var(--cinza-escuro);
            border: 2px solid var(--cinza-claro);
            border-radius: 10px;
            color: var(--branco);
            font-size: 1.1em;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            transition: all 0.3s;
        }
        
        .campo-input:focus {
            outline: none;
            border-color: var(--laranja);
            box-shadow: 0 0 15px rgba(255, 107, 0, 0.5);
            background: var(--cinza-claro);
        }
        
        .instrucoes {
            background: rgba(255, 107, 0, 0.15);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid var(--laranja);
            color: var(--laranja-claro);
        }
        
        .destaque {
            color: var(--laranja);
            font-weight: bold;
        }
        
        .botoes-acoes {
            display: flex;
            gap: 25px;
            margin: 40px 0;
        }
        
        .botao-acao {
            flex: 1;
            padding: 20px;
            border: none;
            border-radius: 12px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .botao-salvar {
            background: linear-gradient(135deg, var(--laranja) 0%, var(--laranja-escuro) 100%);
            color: white;
        }
        
        .botao-testar {
            background: transparent;
            color: var(--laranja);
            border: 3px solid var(--laranja);
        }
        
        .botao-acao:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 25px rgba(255, 107, 0, 0.4);
        }
        
        .botao-salvar:hover {
            background: linear-gradient(135deg, #FF8C42 0%, #FF6B00 100%);
        }
        
        .botao-testar:hover {
            background: rgba(255, 107, 0, 0.2);
        }
        
        .teste-rapido {
            background: var(--cinza-medio);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin: 40px 0;
            border: 3px dashed var(--laranja);
        }
        
        .teste-titulo {
            color: var(--laranja);
            font-size: 2em;
            margin-bottom: 15px;
        }
        
        .guia {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid var(--cinza-claro);
        }
        
        .guia-titulo {
            color: var(--laranja);
            text-align: center;
            font-size: 2em;
            margin-bottom: 30px;
        }
        
        .passos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
        }
        
        .passo {
            background: var(--cinza-medio);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s;
            border: 1px solid var(--cinza-claro);
        }
        
        .passo:hover {
            transform: translateY(-5px);
            border-color: var(--laranja);
        }
        
        .passo-numero {
            width: 60px;
            height: 60px;
            background: var(--laranja);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.5em;
            font-weight: bold;
        }
        
        .passo h3 {
            color: var(--laranja-claro);
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .info-rodape {
            margin-top: 40px;
            padding: 20px;
            background: rgba(255, 107, 0, 0.1);
            border-radius: 10px;
            text-align: center;
            color: var(--laranja-claro);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            h1 {
                font-size: 2.2em;
            }
            
            .botoes-acoes {
                flex-direction: column;
            }
            
            .passos {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- BARRA SUPERIOR COM BOTÃO VOLTAR -->
        <div class="top-bar">
            <a href="indexadmin.php" class="botao-voltar">
                ← Voltar ao Painel Admin
            </a>
            <div class="user-info">
                <span style="color: var(--laranja-claro);"> <?php echo $_SESSION['usuario_nome'] ?? 'Admin'; ?></span>
            </div>
        </div>
        
        <!-- CABEÇALHO -->
        <div class="header">
            <h1><i class="fa-solid fa-tools" aria-hidden="true"></i> Configurar Telegram Bot</h1>
            <p class="subtitulo">Receba notificações do sistema no seu Telegram</p>
        </div>
        
        <!-- STATUS -->
        <div class="status-box">
            <div class="status-text">
                [RELATORIO] Status da Configuração
            </div>
            <div class="status-indicator">
                <?php echo (!empty($bot_token) && !empty($chat_id))
                    ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> CONFIGURADO'
                    : '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> NÃO CONFIGURADO'; ?>
            </div>
        </div>
        
        <!-- MENSAGENS -->
        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>">
                <span><?php echo ($tipo_mensagem == 'sucesso')
                    ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>'
                    : '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>'; ?></span>
                <div><?php echo $mensagem; ?></div>
            </div>
        <?php endif; ?>
        
        <!-- FORMULÁRIO -->
        <form method="POST" action="">
            <div class="secao">
                <h2> Token do Bot</h2>
                <input type="text" 
                       name="bot_token" 
                       class="campo-input"
                       value="<?php echo htmlspecialchars($bot_token); ?>" 
                       placeholder="Cole aqui o token do seu bot (ex: 1234567890:ABCdefGHIJKLMNOPQRSTUVwXYZ)" 
                       required>
                <div class="instrucoes">
                    <p><span class="destaque">Obtenha com @BotFather</span> no Telegram. Envie o comando <span class="destaque">/newbot</span> e copie o token que aparecer.</p>
                </div>
            </div>
            
            <div class="secao">
                <h2> Chat ID do Administrador</h2>
                <input type="text" 
                       name="chat_id" 
                       class="campo-input"
                       value="<?php echo htmlspecialchars($chat_id); ?>" 
                       placeholder="Digite seu Chat ID (ex: 8523313068)" 
                       required>
                <div class="instrucoes">
                    <p><span class="destaque">Obtenha com @userinfobot</span> no Telegram. Clique em <span class="destaque">START</span> e copie o número em "Your ID".</p>
                </div>
            </div>
            
            <!-- BOTÕES DE AÇÃO -->
            <div class="botoes-acoes">
                <button type="submit" name="salvar_config" class="botao-acao botao-salvar">
                     SALVAR CONFIGURAÇÕES
                </button>
                
                <button type="submit" name="testar_bot" class="botao-acao botao-testar">
                    [INICIAR] TESTAR BOT
                </button>
            </div>
        </form>
        
        <!-- TESTE RÁPIDO -->
        <div class="teste-rapido">
            <div class="teste-titulo">[INICIAR] TESTE RÁPIDO</div>
            <p>Clique em "TESTAR BOT" para verificar a conexão com seu Telegram</p>
            <p style="margin-top: 15px; color: var(--laranja-claro); font-size: 0.9em;">
                Se receber a mensagem "Chat ID incorreto!", verifique se copiou corretamente do @userinfobot
            </p>
        </div>
        
        <!-- GUIA -->
        <div class="guia">
            <h2 class="guia-titulo"> Guia Rápido de Configuração</h2>
            <div class="passos">
                <div class="passo">
                    <div class="passo-numero">1</div>
                    <h3>Criar Bot</h3>
                    <p>Vá ao @BotFather no Telegram e use /newbot</p>
                </div>
                <div class="passo">
                    <div class="passo-numero">2</div>
                    <h3>Pegar Token</h3>
                    <p>Copie o token fornecido pelo @BotFather</p>
                </div>
                <div class="passo">
                    <div class="passo-numero">3</div>
                    <h3>Pegar Chat ID</h3>
                    <p>Vá ao @userinfobot e copie seu ID numérico</p>
                </div>
                <div class="passo">
                    <div class="passo-numero">4</div>
                    <h3>Testar</h3>
                    <p>Cole os dados acima e teste a conexão</p>
                </div>
            </div>
        </div>
        
        <!-- RODAPÉ COM INFORMAÇÕES -->
        <div class="info-rodape">
            <?php if ($ultima_atualizacao): ?>
                <p>[ATUAL] Última atualização: <strong><?php echo $ultima_atualizacao; ?></strong></p>
                <?php if (!empty($bot_token)): ?>
                    <p style="margin-top: 10px; font-size: 0.9em;">Token: <?php echo substr($bot_token, 0, 10) . '...' . substr($bot_token, -10); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Nenhuma configuração salva ainda</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Validação no lado do cliente
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const tokenInput = document.querySelector('input[name="bot_token"]');
        const chatInput = document.querySelector('input[name="chat_id"]');
        
        // Formatação automática - remove espaços
        tokenInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\s/g, '');
        });
        
        chatInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Validação antes de enviar
        form.addEventListener('submit', function(e) {
            const tokenValor = tokenInput.value.trim();
            const chatValor = chatInput.value.trim();
            
            // Validar token
            if (!tokenValor) {
                e.preventDefault();
                alert('Por favor, insira o token do bot.');
                tokenInput.focus();
                return false;
            }
            
            if (!tokenValor.match(/^\d+:[A-Za-z0-9_-]{10,}$/)) {
                e.preventDefault();
                alert('Formato do token inválido!\n\nDeve ser: NÚMEROS:letrasENúmeros\nExemplo: 1234567890:ABCdefGHIJKLMNOPQRSTUVwXYZ');
                tokenInput.focus();
                return false;
            }
            
            // Validar chat ID
            if (!chatValor) {
                e.preventDefault();
                alert('Por favor, insira o Chat ID.');
                chatInput.focus();
                return false;
            }
            
            if (!/^\d+$/.test(chatValor)) {
                e.preventDefault();
                alert('Chat ID deve conter apenas números!');
                chatInput.focus();
                return false;
            }
            
            return true;
        });
        
        // Efeito de foco nos campos
        const campos = document.querySelectorAll('.campo-input');
        campos.forEach(campo => {
            campo.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            campo.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    });
    </script>
</body>
</html>