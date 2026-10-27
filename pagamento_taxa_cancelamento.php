<?php
session_start();
require_once 'check_session.php';
require_once 'ligar.php';
require_once __DIR__ . '/inc_cancelamento_marcacao.php';

if (empty($_SESSION['user']['email'])) {
    header('Location: login.php');
    exit;
}

$email = (string) $_SESSION['user']['email'];
$nome = (string) ($_SESSION['user']['nome_utilizador'] ?? 'Cliente');
$servico_id = (int) ($_GET['servico_id'] ?? $_POST['servico_id'] ?? 0);

if ($servico_id <= 0) {
    die('Pedido inválido.');
}

$servico = am_marcacao_carregar_cliente($con, $servico_id, $email);
if (!$servico) {
    die('Marcação não encontrada.');
}

$chk = am_marcacao_pode_cancelar($servico);
if (!$chk['ok']) {
    header('Location: configuracoes.php?seccao=marcacoes');
    exit;
}

$resumo = am_cancelamento_resumo_taxa($servico);
if (!$resumo['requer_taxa']) {
    header('Location: configuracoes.php?seccao=marcacoes');
    exit;
}

if (am_marcacao_esta_cancelada($servico)) {
    header('Location: configuracoes.php?seccao=marcacoes');
    exit;
}

$valor = (float) $resumo['valor'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo = trim((string) ($_POST['metodo_pagamento_srv'] ?? ''));
    $metodos = ['mbway', 'cartao', 'referencia_mb'];
    if (!in_array($metodo, $metodos, true)) {
        $erro = 'Selecione um método de pagamento.';
    } else {
        am_ensure_cancelamento_marcacao_columns($con);
        $up = $con->prepare("
            UPDATE servico
            SET metodo_pagamento_taxa_cancel = ?,
                taxa_cancelamento = ?,
                taxa_cancelamento_paga = 1
            WHERE id = ? AND email = ?
        ");
        if ($up) {
            $up->bind_param('sdis', $metodo, $valor, $servico_id, $email);
            if ($up->execute()) {
                $up->close();
                if (am_marcacao_executar_cancelamento($con, $servico_id, $email, $valor, true)) {
                    header('Location: configuracoes.php?seccao=marcacoes&ver=canceladas');
                    exit;
                }
                $erro = 'Pagamento registado, mas não foi possível concluir o cancelamento. Contacte o apoio.';
            } else {
                $erro = 'Não foi possível registar o pagamento da taxa.';
                $up->close();
            }
        } else {
            $erro = 'Erro interno ao preparar pagamento.';
        }
    }
}

$dtAg = am_marcacao_datetime_agendada($servico);
$dtAgTxt = $dtAg ? $dtAg->format('d/m/Y H:i') : '—';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxa de cancelamento — AUTOMANAGER</title>
    <?php require __DIR__ . '/inc_pagamento_head.php'; ?>
</head>
<body>
<main class="pay-wrap">
    <header class="pay-head">
        <a href="configuracoes.php?seccao=marcacoes" class="pay-back"><i class="fas fa-arrow-left"></i> Voltar</a>
        <h1>Taxa de cancelamento</h1>
    </header>

    <section class="pay-card">
        <h2>Marcação #<?= (int) $servico_id ?></h2>
        <div class="pay-grid">
            <div><span>Serviço</span><strong><?= htmlspecialchars((string) $servico['tipo_servico']) ?></strong></div>
            <div><span>Matrícula</span><strong><?= htmlspecialchars((string) $servico['matricula']) ?></strong></div>
            <div><span>Data agendada</span><strong><?= htmlspecialchars($dtAgTxt) ?></strong></div>
            <div><span>Cliente</span><strong><?= htmlspecialchars($nome) ?></strong></div>
        </div>
        <p class="pay-hint pay-hint--below"><?= htmlspecialchars($resumo['texto']) ?></p>
        <div class="totals totals--spaced">
            <div class="grand">Taxa administrativa <strong>€<?= number_format($valor, 2, ',', ' ') ?></strong></div>
        </div>
    </section>

    <?php if ($erro !== ''): ?><div class="msg err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <form method="post" class="pay-card">
        <input type="hidden" name="servico_id" value="<?= (int) $servico_id ?>">
        <h2>Pagamento da taxa</h2>
        <p class="pay-hint">Após confirmar o pagamento, a marcação será cancelada automaticamente.</p>
        <div class="field">
            <label for="metodoPagamentoSel">Método de pagamento</label>
            <select name="metodo_pagamento_srv" id="metodoPagamentoSel" required>
                <option value="">Selecionar método</option>
                <option value="mbway">MB WAY</option>
                <option value="cartao">Cartão</option>
                <option value="referencia_mb">Referência Multibanco</option>
            </select>
        </div>
        <button class="btn primary" type="submit">Pagar taxa e cancelar marcação</button>
    </form>
</main>
</body>
</html>
