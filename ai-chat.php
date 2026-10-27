<?php
session_start();
require 'config.php';

// Verificação de login (mantenha seu código atual aqui)
if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    // ... seu código de login aqui ...
}

// Se chegou aqui, está logado
// Inicializar conversa do AI
if (!isset($_SESSION['ai_conversa'])) {
    $_SESSION['ai_conversa'] = [
        [
            'tipo' => 'ai',
            'texto' => ' **Olá! Sou o AutoAssist AI, seu mecânico virtual.**<br><br>
                       Posso ajudar a diagnosticar problemas no seu veículo, dar estimativas de custo e recomendações.<br><br>
                       **Exemplos do que posso ajudar:**<br>
                       • Problemas de freios e suspensão<br>
                       • Diagnosticar barulhos estranhos<br>
                       • Consumo excessivo de combustível<br>
                       • Problemas elétricos e de bateria<br><br>
                       Descreva o problema em detalhes e eu ajudarei!',
            'hora' => date('H:i')
        ]
    ];
}

// Configuração da API Groq
define('GROQ_API_KEY', 'openai/gpt-oss-20b'); // Coloque sua chave real aqui
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');

// Classe para comunicação com Groq
class GroqAssistant {
    private $apiKey;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    public function getChatResponse($messages) {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
        
        // Preparar histórico para a API
        $apiMessages = [];
        foreach ($messages as $msg) {
            $role = ($msg['tipo'] == 'usuario') ? 'user' : 'assistant';
            $content = strip_tags($msg['texto']); // Remover HTML para a API
            
            $apiMessages[] = [
                'role' => $role,
                'content' => $content
            ];
        }
        
        // Adicionar sistema de contexto
        $systemMessage = [
            'role' => 'system',
            'content' => 'Você é o AutoAssist AI, um assistente virtual especializado em mecânica automóvel para a empresa AUTOMANAGER em Portugal.
            
            **INFORMAÇÕES IMPORTANTES:**
            - A AUTOMANAGER é uma oficina de reparação automóvel
            - Responda sempre em português de Portugal
            - Seja profissional mas amigável
            - Use emojis relevantes ocasionalmente
            - Formate respostas com markdown simples
            - Se não souber algo, seja honesto e sugira trazer o carro para diagnóstico
            
            **ESPECIALIDADES:**
            - Diagnóstico de problemas automóveis
            - Estimativas de custo (em Euros €)
            - Recomendações de manutenção
            - Explicações técnicas em linguagem simples
            
            **DIRETRIZES:**
            1. Sempre pergunte por mais detalhes se necessário
            2. Forneça gravidade (baixa, média, alta)
            3. Dê tempo estimado de reparação
            4. Sugira se é seguro continuar a circular
            5. Ofereça para agendar visita se for caso disso'
        ];
        
        // Adicionar mensagem do sistema no início
        array_unshift($apiMessages, $systemMessage);
        
        $data = [
            'model' => 'llama3-70b-8192', // Modelo recomendado para Groq
            'messages' => $apiMessages,
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'top_p' => 0.9,
            'stream' => false
        ];
        
        $ch = curl_init(GROQ_API_URL);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log('Groq API Error: ' . curl_error($ch));
            return 'Desculpe, estou com problemas técnicos momentaneamente. Por favor, tente novamente em alguns instantes.';
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log('Groq API HTTP Error: ' . $httpCode . ' - ' . $response);
            return 'Houve um erro ao processar sua solicitação. Código: ' . $httpCode;
        }
        
        $decoded = json_decode($response, true);
        
        if (isset($decoded['choices'][0]['message']['content'])) {
            return $this->formatResponse($decoded['choices'][0]['message']['content']);
        }
        
        return 'Não consegui processar sua pergunta. Poderia reformular?';
    }
    
    private function formatResponse($text) {
        // Converter markdown para HTML simples
        $text = htmlspecialchars($text);
        
        // Converter quebras de linha
        $text = nl2br($text);
        
        // Estilizar títulos
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        
        // Adicionar emojis baseados no conteúdo
        $emojis = [
            '/\bfreio(s)?\b/i' => '[FERRAMENTAS]',
            '/\bmotor\b/i' => '[CONFIG]',
            '/\bpneu(s)?\b/i' => '',
            '/\bbateria\b/i' => '',
            '/\bóleo\b/i' => '',
            '/\bperig(o|oso)\b/i' => '[AVISO]',
            '/\burgent(e|emente)\b/i' => '',
            '/\brecomend(ação|o)\b/i' => '[OBS]',
            '/\bcusto\b/i' => '',
            '/\btempo\b/i' => '⏱',
            '/\bdiagnóstico\b/i' => '[PESQUISA]'
        ];
        
        foreach ($emojis as $pattern => $emoji) {
            $text = preg_replace($pattern, $emoji . ' $0', $text);
        }
        
        return $text;
    }
}

// Processar mensagens do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
    $mensagem = trim($_POST['mensagem']);
    
    if (!empty($mensagem)) {
        // Adicionar mensagem do usuário
        $_SESSION['ai_conversa'][] = [
            'tipo' => 'usuario',
            'texto' => htmlspecialchars($mensagem),
            'hora' => date('H:i')
        ];
        
        // Limitar histórico a 10 mensagens para contexto
        if (count($_SESSION['ai_conversa']) > 20) {
            $_SESSION['ai_conversa'] = array_slice($_SESSION['ai_conversa'], -20);
        }
        
        // Usar a API Groq
        $groq = new GroqAssistant(GROQ_API_KEY);
        
        // Preparar últimas 10 mensagens para contexto
        $recentMessages = array_slice($_SESSION['ai_conversa'], -10);
        
        // Obter resposta da API
        $resposta = $groq->getChatResponse($recentMessages);
        
        // Adicionar resposta do AI
        $_SESSION['ai_conversa'][] = [
            'tipo' => 'ai',
            'texto' => $resposta,
            'hora' => date('H:i')
        ];
        
        // Redirecionar para evitar reenvio do formulário
        header('Location: ai-chat.php');
        exit;
    }
}

// Resetar conversa
if (isset($_GET['reset'])) {
    $_SESSION['ai_conversa'] = [
        [
            'tipo' => 'ai',
            'texto' => ' **Olá! Sou o AutoAssist AI, seu mecânico virtual.**<br><br>
                       Posso ajudar a diagnosticar problemas no seu veículo, dar estimativas de custo e recomendações.<br><br>
                       **Exemplos do que posso ajudar:**<br>
                       • Problemas de freios e suspensão<br>
                       • Diagnosticar barulhos estranhos<br>
                       • Consumo excessivo de combustível<br>
                       • Problemas elétricos e de bateria<br><br>
                       Descreva o problema em detalhes e eu ajudarei!',
            'hora' => date('H:i')
        ]
    ];
    header('Location: ai-chat.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoAssist AI - AUTOMANAGER</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mantenha todo o seu CSS atual aqui */
        .ai-container {
            max-width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .message {
            margin-bottom: 20px;
            max-width: 85%;
        }
        .message.ai { align-self: flex-start; }
        .message.usuario { align-self: flex-end; text-align: right; }
        .message-content {
            padding: 12px 18px;
            border-radius: 15px;
            background: #2a2a2a;
            color: white;
            display: inline-block;
        }
        .message.ai .message-content { background: #3a3a3a; border-left: 4px solid #FF6B00; }
        .message.usuario .message-content { background: #FF6B00; color: white; }
        
        .chat-input-container {
            padding: 20px;
            background: #1a1a1a;
            border-top: 1px solid #333;
        }
        .message-input {
            width: 100%;
            background: #2a2a2a;
            border: 1px solid #444;
            color: white;
            padding: 12px;
            border-radius: 8px;
            resize: none;
        }
        .input-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .quick-examples { font-size: 0.8em; color: #888; }
        .quick-examples span { cursor: pointer; color: #FF6B00; text-decoration: underline; }
        
        @media (max-width: 600px) {
            .message { max-width: 95%; }
            .input-actions { flex-direction: column; align-items: stretch; }
            .send-btn { width: 100%; padding: 12px; }
        }

        /* Adicione este estilo para o loading */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background: #FF6B00;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="ai-container">
        <!-- Mantenha seu header atual -->
        <div class="ai-header">
            <!-- ... seu header atual ... -->
        </div>

        <div class="chat-main">
            <div class="chat-messages" id="chatMessages">
                <?php foreach ($_SESSION['ai_conversa'] as $msg): ?>
                    <div class="message <?= $msg['tipo'] ?>">
                        <div class="message-sender">
                            <?php if ($msg['tipo'] == 'ai'): ?>
                                <i class="fas fa-robot"></i> AutoAssist AI
                            <?php else: ?>
                                <i class="fas fa-user"></i> Você
                            <?php endif; ?>
                        </div>
                        <div class="message-content">
                            <?= $msg['texto'] ?>
                        </div>
                        <div class="timestamp"><?= $msg['hora'] ?></div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <!-- Indicador de digitação -->
                <div class="message ai" id="typingIndicator">
                    <div class="message-sender">
                        <i class="fas fa-robot"></i> AutoAssist AI
                    </div>
                    <div class="message-content">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="chat-input-container">
                <form method="POST" id="chatForm" onsubmit="showTypingIndicator()">
                    <textarea 
                        name="mensagem" 
                        class="message-input" 
                        id="mensagemInput" 
                        placeholder="Descreva o problema do seu veículo em detalhes... Ex: 'Meu carro faz barulho ao passar em buracos e o volante vibra acima de 80km/h'" 
                        required
                        rows="3"
                    ></textarea>
                    
                    <div class="input-actions">
                        <div class="quick-examples">
                            Exemplos rápidos: 
                            <span onclick="inserirTexto('O pedal de freio está muito mole e o carro não trava bem')">Problema de freios</span> • 
                            <span onclick="inserirTexto('O motor faz um barulho de batida quando acelero')">Barulho no motor</span> • 
                            <span onclick="inserirTexto('A bateria descarrega muito rápido, preciso dar à chave várias vezes')">Problema elétrico</span>
                        </div>
                        
                        <button type="submit" class="send-btn" id="submitBtn">
                            <i class="fas fa-paper-plane"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Função para mostrar indicador de digitação
        function showTypingIndicator() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            
            // Adicionar indicador visual
            const typingHtml = `
                <div class="message ai" id="typingIndicator">
                    <div class="message-sender">
                        <i class="fas fa-robot"></i> AutoAssist AI
                    </div>
                    <div class="message-content">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('chatMessages').insertAdjacentHTML('beforeend', typingHtml);
            scrollToBottom();
        }
        
        // Função para inserir texto
        function inserirTexto(texto) {
            document.getElementById('mensagemInput').value = texto;
            document.getElementById('mensagemInput').focus();
            document.getElementById('mensagemInput').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Enviar com Enter (sem Shift)
        document.getElementById('mensagemInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                showTypingIndicator();
                document.getElementById('chatForm').submit();
            }
        });
        
        // Scroll automático para baixo
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Focar no input
        document.getElementById('mensagemInput').focus();
        
        // Scroll inicial
        setTimeout(scrollToBottom, 100);
        
        // Se tiver mensagem de erro da API, mostrar alerta
        <?php 
        if (isset($_GET['error'])): 
        ?>
        alert('Erro na comunicação com o AI. Por favor, tente novamente.');
        <?php 
        endif; 
        ?>
    </script>
</body>
</html>