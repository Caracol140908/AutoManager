<?php
session_start();

// Verifica se o utilizador está logado e é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Conexão à base de dados
require('ligar.php');
require_once __DIR__ . '/inc_password_padrao.php';
require_once __DIR__ . '/inc_telemovel.php';

// Especialidades disponíveis
$especialidades = [
    'Motor',
    'Transmissão',
    'Suspensão',
    'Freios',
    'Elétrica',
    'Ar Condicionado',
    'Injeção Eletrônica',
    'Direção',
    'Pintura',
    'Funilaria',
    'Mecânica Geral',
    'Diagnóstico'
];

$error_msg = '';
$success_msg = '';

// Adicionar novo mecânico
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_mecanico'])) {
    $nome = trim($_POST['nome_mecanico']);
    $especialidade = $_POST['especialidade'];
    $contacto = am_normalizar_telemovel((string) ($_POST['contacto'] ?? ''));
    $email = trim($_POST['email']);
    $morada = trim($_POST['morada']);
    $ordenado_bruto = floatval($_POST['ordenado_bruto']);
    
    $password = am_password_padrao_funcionario();
    $password_hash = am_hash_password_padrao_funcionario();

    [$telOk, $telMsg] = am_validar_telemovel_portugues($contacto, true);
    if (!$telOk) {
        $error_msg = $telMsg;
    } else {
    
    // Verificar se email já existe
    $check_stmt = $con->prepare("SELECT id_mecanico FROM mecanicos WHERE email = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_msg = "Erro: Já existe um mecânico com este email.";
        } else {
            // Inserir com primeiro_login = 1
            $stmt = $con->prepare("INSERT INTO mecanicos (nome_mecanico, especialidade, contacto, email, morada, ordenado_bruto, password, primeiro_login) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            
            if ($stmt) {
                $stmt->bind_param("sssssds", $nome, $especialidade, $contacto, $email, $morada, $ordenado_bruto, $password_hash);
                
                if ($stmt->execute()) {
                    // Enviar email com os dados da conta
                    if (enviarEmailMecanico($email, $nome, $password, $ordenado_bruto)) {
                        $success_msg = "Mecânico adicionado com sucesso! Email enviado com os dados da conta. O funcionário será obrigado a mudar a password no primeiro login.";
                    } else {
                        $success_msg = "Mecânico adicionado com sucesso! Mas houve um erro ao enviar o email. O funcionário será obrigado a mudar a password no primeiro login.";
                    }
                } else {
                    $error_msg = "Erro ao adicionar mecânico: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_msg = "Erro na preparação da query de inserção: " . $con->error;
            }
        }
        $check_stmt->close();
    } else {
        $error_msg = "Erro na preparação da query de verificação: " . $con->error;
    }
    }
}

// Função para enviar email ao mecânico
function enviarEmailMecanico($email, $nome, $password, $ordenado_bruto) {
    $assunto = "Bem-vindo à AUTOMANAGER - Sua Conta de Mecânico";
    
    $mensagem = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .header { background: #ff4500; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .dados { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
            .password { color: #ff4500; font-weight: bold; font-size: 18px; }
            .footer { background: #333; color: white; padding: 15px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1> AUTOMANAGER - Oficina Mecânica</h1>
        </div>
        <div class='content'>
            <h2>Olá $nome!</h2>
            <p>A sua conta de mecânico foi criada com sucesso.</p>
            
            <div class='dados'>
                <h3> Seus Dados de Acesso:</h3>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Password:</strong> <span class='password'>$password</span></p>
                <p><strong>Ordenado Bruto:</strong> € " . number_format($ordenado_bruto, 2, ',', ' ') . "</p>
            </div>
            
            <p><strong> Importante:</strong> É o seu primeiro login. Será obrigado a alterar a password após entrar.</p>
            
            <p>Bem-vindo à equipa AUTOMANAGER!</p>
        </div>
        <div class='footer'>
            <p>AUTOMANAGER &copy; " . date('Y') . " - Todos os direitos reservados</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: AUTOMANAGER <noreply@AUTOMANAGER.com>" . "\r\n";
    $headers .= "Reply-To: suporte@AUTOMANAGER.com" . "\r\n";
    
    return mail($email, $assunto, $mensagem, $headers);
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Adicionar Funcionário - AUTOMANAGER</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styleadmin.css">
</head>
<body>

<div class="mobile-header">
    <h2>AUTOMANAGER Admin</h2>
    <button class="menu-toggle" id="menuToggle">[MENU]</button>
</div>

<div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2> AUTOMANAGER Admin</h2>
        </div>
        
        <div class="sidebar-nav">
            <a href="indexadmin.php" class="nav-link">[RELATORIO] Dashboard</a>
            <a href="adicionar_funcionario.php" class="nav-link active"> Adicionar Funcionário</a>
            <a href="indexadmin.php" class="nav-link">[FERRAMENTAS] Gerir Mecânicos</a>
            <a href="indexadmin.php" class="nav-link"> Gerir Utilizadores</a>
        </div>
        
        <div class="sidebar-footer">
            <a href="index.php" class="btn btn-primary btn-full"> Ir para o Site</a>
            <a href="indexadmin.php?logout=1" class="btn btn-secondary btn-full"> Terminar Sessão</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1> Adicionar Funcionário</h1>
            <p>Adicione novos mecânicos à equipa AUTOMANAGER</p>
        </div>

        <!-- Mensagens de sucesso/erro -->
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success"><?= $success_msg ?></div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-error"><?= $error_msg ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-header">
                <h2> Novo Mecânico</h2>
                <p>Preencha os dados do novo funcionário</p>
            </div>

            <form method="POST" action="" class="sidebar-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_mecanico">Nome do Mecânico *</label>
                        <input type="text" id="nome_mecanico" name="nome_mecanico" required placeholder="Ex: João Silva">
                    </div>
                    
                    <div class="form-group">
                        <label for="especialidade">Especialidade *</label>
                        <select id="especialidade" name="especialidade" required>
                            <option value="">Selecione uma especialidade</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= $esp ?>"><?= $esp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="contacto">Contacto *</label>
                        <input type="text" class="am-telemovel-input" id="contacto" name="contacto" required placeholder="912 345 678" maxlength="11" autocomplete="tel">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required placeholder="Ex: joao.silva@AUTOMANAGER.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="ordenado_bruto">Ordenado Bruto (€) *</label>
                        <input type="number" id="ordenado_bruto" name="ordenado_bruto" step="0.01" min="0" required placeholder="Ex: 1500.00">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="morada">Morada</label>
                        <textarea id="morada" name="morada" rows="3" placeholder="Ex: Rua Principal, 123 - Lisboa"></textarea>
                    </div>
                </div>
                
                <div class="form-info">
                    <p> Um email automático será enviado com os dados de acesso (password gerada automaticamente)</p>
                    <p> O funcionário será obrigado a mudar a password no primeiro login</p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_mecanico" class="btn btn-success btn-large"> Adicionar Mecânico</button>
                    <a href="indexadmin.php" class="btn btn-secondary">↩ Voltar ao Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="scriptadmin.js"></script>
<script src="telemovel.js?v=1"></script>
</body>
</html>