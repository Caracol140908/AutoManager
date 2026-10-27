<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_admin_notificacoes.php';

// Verificar se o utilizador está logado
$utilizador_logado = isset($_SESSION['user']) || isset($_SESSION['user_id']) || isset($_SESSION['mecanico_id']);

// Processar envio do formulário
$mensagem_sucesso = '';
$mensagem_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_feedback'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $tipo = $_POST['tipo'];
    $assunto = trim($_POST['assunto']);
    $mensagem = trim($_POST['mensagem']);
    
    // Validação
    if (empty($nome) || empty($email) || empty($assunto) || empty($mensagem)) {
        $mensagem_erro = "Por favor, preencha todos os campos obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem_erro = "Por favor, insira um email válido.";
    } else {
        $ok = am_feedback_insert($con, $nome, $email, $tipo, $assunto, $mensagem);
        if ($ok) {
            $mensagem_sucesso = "Obrigado pelo seu feedback/dúvida! Entraremos em contacto em breve.";
            // Limpar formulário
            $_POST = array();
        } else {
            $mensagem_erro = "Erro ao enviar a mensagem. Tente novamente.";
        }
    }
}

// Se o utilizador estiver logado, preencher automaticamente nome e email
$nome_predefinido = '';
$email_predefinido = '';

if ($utilizador_logado) {
    if (isset($_SESSION['user'])) {
        $nome_predefinido = $_SESSION['user']['nome_utilizador'];
        $email_predefinido = $_SESSION['user']['email'];
    } elseif (isset($_SESSION['mecanico_nome'])) {
        $nome_predefinido = $_SESSION['mecanico_nome'];
        $email_predefinido = $_SESSION['mecanico_email'];
    } elseif (isset($_SESSION['username'])) {
        $nome_predefinido = $_SESSION['username'];
        // Para admin, pode não ter email na sessão
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Dúvidas - AUTOMANAGER</title>
    <link rel="stylesheet" href="stylefeedback.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1> Feedback & Dúvidas</h1>
            <p>Partilhe a sua opinião ou coloque as suas questões. Estamos aqui para ajudar!</p>
        </div>

        <?php if ($mensagem_sucesso): ?>
            <div class="mensagem sucesso">
                <?php echo htmlspecialchars($mensagem_sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="mensagem erro">
                <?php echo htmlspecialchars($mensagem_erro); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" class="feedback-form">
                <div class="form-group">
                    <label for="nome">Nome *</label>
                    <input type="text" id="nome" name="nome" 
                           value="<?php echo htmlspecialchars($nome_predefinido ?: ($_POST['nome'] ?? '')); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email_predefinido ?: ($_POST['email'] ?? '')); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de Mensagem *</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">Selecione o tipo...</option>
                        <option value="feedback" <?php echo ($_POST['tipo'] ?? '') === 'feedback' ? 'selected' : ''; ?>>[RELATORIO] Feedback</option>
                        <option value="duvida" <?php echo ($_POST['tipo'] ?? '') === 'duvida' ? 'selected' : ''; ?>>[DUVIDA] Dúvida</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assunto">Assunto *</label>
                    <input type="text" id="assunto" name="assunto" 
                           value="<?php echo htmlspecialchars($_POST['assunto'] ?? ''); ?>" 
                           placeholder="Ex: Problema com agendamento, Sugestão de melhoria..." 
                           required>
                </div>

                <div class="form-group">
                    <label for="mensagem">Mensagem *</label>
                    <textarea id="mensagem" name="mensagem" rows="6" 
                              placeholder="Descreva o seu feedback ou dúvida em detalhe..." 
                              required><?php echo htmlspecialchars($_POST['mensagem'] ?? ''); ?></textarea>
                </div>

                <button type="submit" name="enviar_feedback" class="btn-enviar">
                     Enviar Mensagem
                </button>
            </form>
        </div>

        <div class="info-cards">
            <div class="info-card">
                <div class="info-icon"></div>
                <h3>Feedback</h3>
                <p>Partilhe a sua experiência connosco. O seu feedback ajuda-nos a melhorar os nossos serviços.</p>
            </div>

            <div class="info-card">
                <div class="info-icon"></div>
                <h3>Dúvidas</h3>
                <p>Tem questões sobre os nossos serviços? Estamos aqui para esclarecer todas as suas dúvidas.</p>
            </div>

            <div class="info-card">
                <div class="info-icon"></div>
                <h3>Resposta Rápida</h3>
                <p>Respondemos a todas as mensagens no prazo máximo de 24 horas durante dias úteis.</p>
            </div>
        </div>

        <div class="voltar">
            <a href="index.php" class="btn-voltar">← Voltar à Página Inicial</a>
        </div>
    </div>
</body>
</html>