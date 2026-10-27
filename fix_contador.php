<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$afetadas = 0;

if (isset($_POST['fix_contador'])) {
    foreach (chat_fetch_all_messages($con) as $msg) {
        if (($msg['de_usuario_nome'] ?? '') !== 'Administrador' && (int) ($msg['lida_admin'] ?? 0) === 0) {
            chat_mark_admin_message_read($con, (int) $msg['id']);
            $afetadas++;
        }
    }
    if (am_coluna_existe($con, 'utilizador', 'ultima_visualizacao_chat')) {
        $stmt2 = $con->prepare('UPDATE utilizador SET ultima_visualizacao_chat = NOW() WHERE id_utilizador = ?');
        if ($stmt2) {
            $stmt2->bind_param('i', $_SESSION['user_id']);
            $stmt2->execute();
            $stmt2->close();
        }
    }
    $mensagem = "[OK] Contador corrigido! {$afetadas} mensagens foram marcadas como lidas.";
}

$total = count(chat_fetch_all_messages($con));
$nao_lidas = chat_unread_admin_total($con);
$stats = [
    'total' => $total,
    'nao_lidas' => $nao_lidas,
    'nao_lidas_para_admin' => $nao_lidas,
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção do Contador</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0a0a0a; color: white; padding: 20px; }
        .container { max-width: 600px; margin: 100px auto; background: #1a1a1a; padding: 30px; border-radius: 10px; border: 2px solid #ff6b00; text-align: center; }
        h1 { color: #ff6b00; margin-bottom: 20px; }
        .mensagem { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .sucesso { background: #2e7d32; color: white; }
        .stats { background: #2a2a2a; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left; }
        .stat-item { margin: 10px 0; display: flex; justify-content: space-between; }
        .problema { color: #ff4444; font-weight: bold; }
        .ok { color: #4CAF50; }
        .btn { padding: 12px 24px; border-radius: 5px; border: none; cursor: pointer; font-weight: bold; margin: 10px; text-decoration: none; display: inline-block; }
        .btn-danger { background: #ff4444; color: white; }
        .btn-secondary { background: #333; color: white; }
        .btn-primary { background: #ff6b00; color: white; }
        .warning { background: rgba(255, 152, 0, 0.1); border: 1px solid #ff9800; color: #ff9800; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Correção do Contador de Chat</h1>
        <?php if ($mensagem): ?><div class="mensagem sucesso"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
        <div class="stats">
            <div class="stat-item"><span>Total de mensagens:</span><span><?= (int) $stats['total'] ?></span></div>
            <div class="stat-item"><span>Não lidas para admin:</span><span class="<?= $stats['nao_lidas_para_admin'] > 0 ? 'problema' : 'ok' ?>"><?= (int) $stats['nao_lidas_para_admin'] ?></span></div>
        </div>
        <?php if ($stats['nao_lidas_para_admin'] > 0): ?>
            <form method="POST" onsubmit="return confirm('Marcar todas como lidas?')">
                <button type="submit" name="fix_contador" class="btn btn-danger">Corrigir contador</button>
            </form>
        <?php else: ?>
            <div class="warning" style="border-color:#4CAF50;background:rgba(76,175,80,.1);color:#4CAF50;">Contador OK.</div>
        <?php endif; ?>
        <div style="margin-top:30px;">
            <a href="chat_admin.php" class="btn btn-primary">Ir para Chat</a>
            <a href="indexadmin.php" class="btn btn-secondary">Voltar ao Painel</a>
        </div>
    </div>
</body>
</html>
