<?php
// REMOVA esta linha: session_start();
require 'ligar.php';

class TelegramBot {
    private $token;
    private $adminChatId;
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
        $this->carregarConfig();
    }
    
    private function carregarConfig() {
        // Tentar carregar do arquivo JSON primeiro
        $config_file = 'telegram_config.json';
        if (file_exists($config_file)) {
            $config_json = @file_get_contents($config_file);
            if ($config_json) {
                $config = json_decode($config_json, true);
                if ($config && is_array($config)) {
                    $this->token = $config['bot_token'] ?? null;
                    $this->adminChatId = $config['chat_id'] ?? null;
                    return;
                }
            }
        }
        
        // Fallback: tabela configuracoes (chave/valor)
        require_once __DIR__ . '/inc_configuracoes_kv.php';
        $this->token = am_cfg_get($this->con, 'telegram_bot_token', '');
        $this->adminChatId = am_cfg_get($this->con, 'telegram_chat_id_admin', '');
        if ($this->token !== '' && $this->adminChatId !== '') {
            return;
        }
        $this->token = null;
        $this->adminChatId = null;
    }
    
    public function configurado() {
        return !empty($this->token) && !empty($this->adminChatId);
    }
    
    public function enviarMensagem($texto, $chatId = null) {
        if (!$this->token || !$this->adminChatId) {
            error_log("Token do bot ou Chat ID não configurado");
            return false;
        }
        
        $chatId = $chatId ?: $this->adminChatId;
        
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $texto,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Erro ao enviar para Telegram: " . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    public function enviarNotificacaoChat($deUsuario, $mensagem, $link = null) {
        $texto = "[CHAT] <b>NOVA MENSAGEM NO CHAT</b>\n\n";
        $texto .= " <b>De:</b> " . htmlspecialchars($deUsuario) . "\n";
        $texto .= "[NOTAS] <b>Mensagem:</b>\n" . htmlspecialchars($mensagem) . "\n\n";
        
        if ($link) {
            $texto .= "[LINK] <a href=\"{$link}\">Abrir Chat</a>";
        } else {
            $texto .= " " . date('H:i');
        }
        
        return $this->enviarMensagem($texto);
    }
    
    public function enviarNotificacaoFeedback($nome, $email, $assunto, $mensagem, $link = null) {
        $texto = "[LISTA] <b>NOVO FEEDBACK RECEBIDO</b>\n\n";
        $texto .= " <b>Nome:</b> " . htmlspecialchars($nome) . "\n";
        $texto .= "[EMAIL] <b>Email:</b> " . htmlspecialchars($email) . "\n";
        $texto .= " <b>Assunto:</b> " . htmlspecialchars($assunto) . "\n\n";
        $texto .= "[CHAT] <b>Mensagem:</b>\n" . htmlspecialchars($mensagem) . "\n\n";
        
        if ($link) {
            $texto .= "[LINK] <a href=\"{$link}\">Ver Feedback</a>";
        }
        
        return $this->enviarMensagem($texto);
    }
    
    public function testarConexao() {
        if (!$this->token) {
            return ['ok' => false, 'error' => 'Token não configurado'];
        }
        
        $url = "https://api.telegram.org/bot{$this->token}/getMe";
        $response = @file_get_contents($url, false, stream_context_create([
            'ssl' => ['verify_peer' => false]
        ]));
        if ($response === false) {
            return ['ok' => false, 'error' => 'Falha na conexão com API'];
        }
        return json_decode($response, true);
    }
}

// Instanciar o bot
$telegramBot = new TelegramBot($con);
?>