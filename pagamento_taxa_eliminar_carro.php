<?php
session_start();
require_once 'check_session.php';
require_once 'ligar.php';
require_once __DIR__ . '/inc_eliminar_carro.php';

if (empty($_SESSION['user']['id_utilizador']) || empty($_SESSION['user']['email'])) {
    header('Location: login.php');
    exit;
}

$id_utilizador = (int) $_SESSION['user']['id_utilizador'];
$email = (string) $_SESSION['user']['email'];
$nome = (string) ($_SESSION['user']['nome_utilizador'] ?? 'Cliente');
$id_carro = (int) ($_GET['id_carro'] ?? $_POST['id_carro'] ?? 0);

if ($id_carro <= 0) {
    header('Location: configuracoes.php?seccao=veiculos');
    exit;
}

$resumo = am_carro_resumo_eliminacao($con, $id_utilizador, $id_carro, $email);
if (!$resumo['ok'] || !$resumo['requer_taxa']) {
    header('Location: configuracoes.php?seccao=veiculos');
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
        $del = am_carro_executar_eliminacao($con, $id_utilizador, $id_carro, $email, $valor, true);
        if ($del['ok']) {
            header('Location: configuracoes.php?seccao=veiculos&eliminado=1');
            exit;
        }
        $erro = $del['error'] ?: 'Não foi possível eliminar o veículo após o pagamento.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxa de eliminação de veículo — AUTOMANAGER</title>
    <?php require __DIR__ . '/inc_pagamento_head.php'; ?>
</head>
<body>
<main class="pay-wrap">
    <header class="pay-head">
        <a href="configuracoes.php?seccao=veiculos" class="pay-back"><i class="fas fa-arrow-left"></i> Voltar</a>
        <h1>Taxa de eliminação de veículo</h1>
    </header>

    <section class="pay-card">
        <h2><?= htmlspecialchars($resumo['label']) ?></h2>
        <div class="pay-grid">
            <div><span>Matrícula</span><strong><?= htmlspecialchars($resumo['matricula']) ?></strong></div>
            <div><span>Trabalhos ativos</span><strong><?= (int) $resumo['trabalhos_ativos'] ?></strong></div>
            <div><span>Cliente</span><strong><?= htmlspecialchars($nome) ?></strong></div>
        </div>
        <p class="pay-hint pay-hint--below">
            Este veículo tem <?= (int) $resumo['trabalhos_ativos'] ?> marcação(ões) ou trabalho(s) em curso.
            Para o remover da sua conta, pague a taxa administrativa. As marcações ativas serão canceladas e o veículo deixará de aparecer em todo o site.
        </p>
        <div class="totals totals--spaced">
            <div class="grand">Taxa de eliminação <strong>€<?= number_format($valor, 2, ',', ' ') ?></strong></div>
        </div>
    </section>

    <?php if ($erro !== ''): ?><div class="msg err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <form method="post" class="pay-card">
        <input type="hidden" name="id_carro" value="<?= (int) $id_carro ?>">
        <h2>Pagamento da taxa</h2>
        <p class="pay-hint">Após confirmar, o veículo é eliminado da base de dados e desaparece das suas listas.</p>
        <div class="field">
            <label for="metodoPagamentoSel">Método de pagamento</label>
            <select name="metodo_pagamento_srv" id="metodoPagamentoSel" required>
                <option value="">Selecionar...</option>
                <option value="mbway">MB WAY</option>
                <option value="cartao">Cartão</option>
                <option value="referencia_mb">Referência Multibanco</option>
            </select>
        </div>
        <button type="submit" class="btn primary">Pagar e eliminar veículo</button>
    </form>
</main>
</body>
</html>
