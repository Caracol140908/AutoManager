<?php
session_start();

// Verificar se o utilizador está logado e é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Conexão à base de dados
require('ligar.php');
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);

$mensagem = '';

// Processar nova mensagem do admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensagem'])) {
    $mensagem_texto = trim($_POST['mensagem'] ?? '');
    $usuario_id = intval($_POST['usuario_id'] ?? 0);
    
    if (!empty($mensagem_texto) && $usuario_id > 0) {
        $result = chat_insert_admin_message($con, $usuario_id, $mensagem_texto);
        if ($result['ok']) {
            $mensagem = "<div class='chat-toast chat-toast--ok'>Mensagem enviada.</div>";
            @include_once 'config_telegram.php';
            if (isset($telegramBot) && method_exists($telegramBot, 'enviarNotificacaoChat')) {
                $linkChat = 'http://' . $_SERVER['HTTP_HOST'] . '/chat.php';
                $telegramBot->enviarNotificacaoChat('Administrador', $mensagem_texto, $linkChat);
            }
        } else {
            $mensagem = "<div class='chat-toast chat-toast--err'>" . htmlspecialchars($result['error']) . '</div>';
        }
    } else {
        $mensagem = "<div class='chat-toast chat-toast--err'>Escreve uma mensagem antes de enviar.</div>";
    }
}

// Marcar mensagem como lida
if (isset($_GET['marcar_lida'])) {
    chat_mark_admin_message_read($con, (int) $_GET['marcar_lida']);
}

$usuarios = chat_list_conversations($con);

// Buscar mensagens se houver usuário selecionado
$usuario_selecionado = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$mensagens = [];

if ($usuario_selecionado > 0 && isset($usuarios[$usuario_selecionado])) {
    chat_mark_read_admin_thread($con, $usuario_selecionado);
    $mensagens = chat_fetch_thread($con, $usuario_selecionado);
}

$naoLidasTotal = chat_unread_admin_total($con);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php
$am_head_body_class = 'chat-admin-page';
require __DIR__ . '/inc_am_head.php';
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Suporte - AUTOMANAGER Admin</title>
<?php require __DIR__ . '/inc_chat_styles.php'; ?>
</head>
<body class="chat-admin-page">
<div class="chat-admin-wrap">
    <header class="chat-admin-top">
        <div>
            <h1><i class="bi bi-chat-dots-fill"></i> Chat de Suporte</h1>
            <p><?= count($usuarios) ?> conversas · <?= $naoLidasTotal ?> por ler</p>
        </div>
        <div class="chat-admin-actions">
            <a href="indexadmin.php"><i class="bi bi-arrow-left"></i> Painel</a>
            <a href="indexadmin.php?logout=1" class="is-danger"><i class="bi bi-box-arrow-right"></i> Sair</a>
        </div>
    </header>

    <div class="chat-admin-layout">
        <aside class="chat-sidebar">
            <div class="chat-sidebar__head">
                <h2>Clientes</h2>
                <p class="chat-sidebar__stats">Atualização automática a cada 3 s</p>
                <?php if (!empty($usuarios)): ?>
                <input type="search" class="chat-sidebar__search" id="filtroConversas" placeholder="Procurar por nome ou email…" autocomplete="off">
                <?php endif; ?>
            </div>
            <div class="chat-sidebar__list" id="listaConversas">
            <?php if (!empty($usuarios)): ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <a href="?usuario=<?= $usuario['id_utilizador'] ?>" class="chat-conversa" data-nome="<?= htmlspecialchars(strtolower($usuario['nome_utilizador'])) ?>" data-email="<?= htmlspecialchars(strtolower($usuario['email'])) ?>">
                        <div class="chat-conversa__item <?= $usuario_selecionado == $usuario['id_utilizador'] ? 'is-active' : '' ?>">
                            <div class="chat-conversa__row">
                                <span class="chat-conversa__nome"><?= htmlspecialchars($usuario['nome_utilizador']) ?></span>
                                <?php if ($usuario['nao_lidas'] > 0): ?>
                                    <span class="chat-badge"><?= (int) $usuario['nao_lidas'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="chat-conversa__email"><?= htmlspecialchars($usuario['email']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="chat-empty">
                    <i class="bi bi-chat"></i>
                    <h3>Sem conversas</h3>
                    <p>Quando um cliente enviar mensagem, aparece aqui.</p>
                </div>
            <?php endif; ?>
            </div>
        </aside>

        <main class="chat-main">
            <?php if ($usuario_selecionado > 0 && isset($usuarios[$usuario_selecionado])): ?>
                <div class="chat-main__head">
                    <strong><?= htmlspecialchars($usuarios[$usuario_selecionado]['nome_utilizador']) ?></strong>
                    <span><?= htmlspecialchars($usuarios[$usuario_selecionado]['email']) ?></span>
                </div>

                <div class="chat-messages" id="chat-mensagens">
                    <?php if (!empty($mensagens)): ?>
                        <?php foreach ($mensagens as $msg):
                            $isAdmin = $msg['de_usuario_nome'] === 'Administrador';
                        ?>
                            <div class="chat-bubble <?= $isAdmin ? 'chat-bubble--mine' : 'chat-bubble--theirs' ?>">
                                <span class="chat-bubble__label"><?= $isAdmin ? 'Tu (admin)' : 'Cliente' ?></span>
                                <div class="chat-bubble__body"><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                                <span class="chat-bubble__time"><?= date('d/m H:i', strtotime($msg['data_envio'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="chat-empty">
                            <i class="bi bi-chat-left-text"></i>
                            <h3>Conversa vazia</h3>
                            <p>Envia a primeira mensagem para iniciar o contacto.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($mensagem): ?>
                <div style="padding:0 16px"><?= $mensagem ?></div>
                <?php endif; ?>
                <form method="POST" class="chat-form-admin">
                    <input type="hidden" name="usuario_id" value="<?= $usuario_selecionado ?>">
                    <input type="text" name="mensagem" placeholder="Escreve a tua resposta…" required autocomplete="off" maxlength="2000">
                    <button type="submit" name="enviar_mensagem" class="chat-btn-send">
                        <i class="bi bi-send-fill"></i><span>Enviar</span>
                    </button>
                </form>
                
                <?php
                    $ultimaMsgId = 0;
                    if (!empty($mensagens)) {
                        $ultimaMsgId = (int) end($mensagens)['id'];
                    }
                ?>
                <script>
                (function () {
                    const usuarioId = <?= (int) $usuario_selecionado ?>;
                    const ultimaMensagemId = <?= (int) $ultimaMsgId ?>;
                    const naoLidasInicial = <?= (int) $naoLidasTotal ?>;
                    let aRecarregar = false;

                    function scrollChat() {
                        const el = document.getElementById('chat-mensagens');
                        if (el) el.scrollTop = el.scrollHeight;
                    }

                    function recarregarSeNovaMensagem(data) {
                        if (!data || data.error || aRecarregar) return;

                        if (usuarioId > 0 && Array.isArray(data.mensagens) && data.mensagens.length) {
                            const temNova = data.mensagens.some(function (msg) {
                                return parseInt(msg.id, 10) > ultimaMensagemId;
                            });
                            if (temNova) {
                                aRecarregar = true;
                                window.location.reload();
                            }
                            return;
                        }

                        if (usuarioId <= 0 && typeof data.nao_lidas_total === 'number' && data.nao_lidas_total > naoLidasInicial) {
                            aRecarregar = true;
                            window.location.reload();
                        }
                    }

                    function pollChat() {
                        if (aRecarregar) return;
                        const url = usuarioId > 0
                            ? 'obter_mensagens_admin.php?usuario_id=' + usuarioId + '&ultima_id=' + ultimaMensagemId + '&lista=1'
                            : 'obter_mensagens_admin.php?usuario_id=0&ultima_id=0&lista=1';
                        fetch(url, {
                            cache: 'no-store',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(function (r) { return r.json(); })
                            .then(recarregarSeNovaMensagem)
                            .catch(function () { /* silencioso */ });
                    }

                    document.addEventListener('DOMContentLoaded', function () {
                        scrollChat();
                        pollChat();
                        setInterval(pollChat, 3000);
                    });
                })();
                </script>
            <?php else: ?>
                <div class="chat-empty" style="flex:1;min-height:320px">
                    <i class="bi bi-chat-dots"></i>
                    <h3>Escolhe uma conversa</h3>
                    <p>Seleciona um cliente na lista à esquerda para ver e responder às mensagens.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
(function () {
    var filtro = document.getElementById('filtroConversas');
    if (filtro) {
        filtro.addEventListener('input', function () {
            var q = filtro.value.trim().toLowerCase();
            document.querySelectorAll('.chat-conversa').forEach(function (el) {
                var nome = el.getAttribute('data-nome') || '';
                var email = el.getAttribute('data-email') || '';
                el.style.display = (!q || nome.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
})();
</script>
</body>
</html>
