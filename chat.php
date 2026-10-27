<?php
session_start();
header('Location: configuracoes.php?seccao=marcacoes');
exit;
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);

// Verificar se está logado
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id_utilizador'])) {
    header('Location: login.php');
    exit;
}

// Bloquear visitantes temporários
if (isset($_SESSION['temp_allowed']) && $_SESSION['temp_allowed'] === true && !isset($_SESSION['user']) ) {
    header('Location: login.php?blocked=chat');
    exit;
}

// Bloquear contas temporárias
if (isset($_SESSION['user']['conta_temporaria']) && $_SESSION['user']['conta_temporaria'] === true) {
    header('Location: index.php?blocked=chat');
    exit;
}

$userId = $_SESSION['user']['id_utilizador'];
$userName = $_SESSION['user']['nome_utilizador'];

// Marcar respostas do admin como lidas ao abrir o chat
chat_mark_read_user_thread($con, (int) $userId);

if (isset($_GET['marcar_lidas'])) {
    chat_mark_read_user_thread($con, (int) $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
    $mensagem = trim($_POST['mensagem']);
    if ($mensagem !== '') {
        $result = chat_insert_user_message($con, (int) $userId, $userName, $mensagem);
        if ($result['ok']) {
            @include_once 'config_telegram.php';
            if (isset($telegramBot) && method_exists($telegramBot, 'enviarNotificacaoChat')) {
                $linkChat = 'http://' . $_SERVER['HTTP_HOST'] . '/chat_admin.php';
                $telegramBot->enviarNotificacaoChat($userName, $mensagem, $linkChat);
            }
            echo json_encode(['success' => true, 'id' => $result['id']]);
            exit;
        }
    }
}

$mensagens = chat_fetch_thread($con, (int) $userId);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php
$am_head_body_class = 'chat-page';
require __DIR__ . '/inc_am_head.php';
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat com Suporte - AUTOMANAGER</title>
<?php require __DIR__ . '/inc_chat_styles.php'; ?>
</head>
<body class="chat-page">
<div class="chat-shell">
    <header class="chat-topbar">
        <a href="configuracoes.php" class="chat-topbar__back"><i class="fas fa-arrow-left"></i> Voltar</a>
        <div class="chat-topbar__title">
            <h1><span class="chat-status-dot" aria-hidden="true"></span> Suporte AutoManager</h1>
            <p>Respondemos o mais rápido possível</p>
        </div>
        <span style="width:90px" aria-hidden="true"></span>
    </header>

    <div class="chat-card">
        <div class="chat-sync-hint"><i class="fas fa-sync-alt"></i> Mensagens atualizadas automaticamente</div>

        <div class="chat-messages" id="chatMessages">
            <?php if (!empty($mensagens)): ?>
                <?php foreach ($mensagens as $msg):
                    $isMine = !empty($msg['de_usuario_id']) && (int) $msg['de_usuario_id'] === (int) $userId;
                ?>
                <div class="chat-bubble <?= $isMine ? 'chat-bubble--mine' : 'chat-bubble--theirs' ?>">
                    <span class="chat-bubble__label"><?= $isMine ? 'Tu' : 'Suporte' ?></span>
                    <div class="chat-bubble__body"><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                    <span class="chat-bubble__time"><?= date('d/m H:i', strtotime($msg['data_envio'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <h3>Começa a conversa</h3>
                    <p>Envia a tua primeira mensagem e a nossa equipa responde aqui.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="chat-compose">
            <input type="text" id="mensagemInput" placeholder="Escreve a tua mensagem…" autocomplete="off" maxlength="2000">
            <button type="button" class="chat-btn-send" id="btnEnviar" onclick="enviarMensagem()">
                <i class="fas fa-paper-plane"></i><span>Enviar</span>
            </button>
        </div>
    </div>
</div>
<div id="chatToast" class="chat-toast" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9999;max-width:90%"></div>

<script>
let ultimaMensagemId = <?= !empty($mensagens) ? end($mensagens)['id'] : 0 ?>;

// Scroll para baixo
function scrollParaBaixo() {
    const chatDiv = document.getElementById('chatMessages');
    chatDiv.scrollTop = chatDiv.scrollHeight;
}

// Enviar mensagem
function enviarMensagem() {
    const input = document.getElementById('mensagemInput');
    const mensagem = input.value.trim();
    
    if (!mensagem) return;

    const btn = document.getElementById('btnEnviar');
    if (btn) btn.disabled = true;
    input.value = '';

    fetch('enviar_mensagem.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mensagem=' + encodeURIComponent(mensagem)
    })
    .then(response => response.json())
    .then(function (data) {
        if (data.success) {
            window.location.reload();
        } else {
            if (btn) btn.disabled = false;
            input.value = mensagem;
            mostrarToast(data.error || 'Não foi possível enviar. Tenta outra vez.', 'err');
        }
    })
    .catch(function () {
        if (btn) btn.disabled = false;
        input.value = mensagem;
        mostrarToast('Sem ligação. Verifica a internet e tenta outra vez.', 'err');
    });
}

function mostrarToast(texto, tipo) {
    const el = document.getElementById('chatToast');
    if (!el) return;
    el.textContent = texto;
    el.className = 'chat-toast chat-toast--' + (tipo === 'err' ? 'err' : 'ok');
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.style.display = 'none'; }, 4000);
}

let aRecarregarChat = false;

// Nova mensagem → recarrega a página para mostrar tudo atualizado
function verificarNovasMensagens() {
    if (aRecarregarChat) return;
    fetch('obter_novas_mensagens.php?ultima_id=' + ultimaMensagemId + '&_=' + Date.now(), { cache: 'no-store' })
    .then(function (response) { return response.json(); })
    .then(function (mensagens) {
        if (!Array.isArray(mensagens) || mensagens.length === 0) return;
        const temNova = mensagens.some(function (msg) {
            return parseInt(msg.id, 10) > ultimaMensagemId;
        });
        if (temNova) {
            aRecarregarChat = true;
            window.location.reload();
        }
    })
    .catch(function () { /* silencioso */ });
}

verificarNovasMensagens();
setInterval(verificarNovasMensagens, 3000);

// Enviar com Enter
document.getElementById('mensagemInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        enviarMensagem();
    }
});

// Scroll inicial
window.addEventListener('load', function() {
    setTimeout(scrollParaBaixo, 100);
});
</script>

</body>
</html>