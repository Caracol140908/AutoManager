<?php
// login_chefe.php
session_start();
require 'ligar.php';

// Se já estiver logado e for primeiro login, redirecionar para alterar password
if (isset($_SESSION['chefe_id']) && isset($_SESSION['primeiro_login']) && $_SESSION['primeiro_login'] == true) {
    header('Location: alterar_password_chefe.php');
    exit();
}

// Se já estiver logado normalmente, redirecionar para o painel
if (isset($_SESSION['chefe_id']) && isset($_SESSION['is_chefe'])) {
    header('Location: indexchefeoficina.php');
    exit();
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($email) || empty($password)) {
        $mensagem = 'error:Preencha todos os campos';
    } else {
        // Buscar o chefe
        $sql = "SELECT id_chefe, nome, email, password, primeiro_login FROM chefes_oficina WHERE email = ?";
        
        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $id, $nome, $db_email, $hash, $primeiro_login);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            
            if ($id && password_verify($password, $hash)) {
                // Iniciar sessão
                $_SESSION['chefe_id'] = $id;
                $_SESSION['chefe_nome'] = $nome;
                $_SESSION['chefe_email'] = $db_email;
                $_SESSION['is_chefe'] = true;
                $_SESSION['primeiro_login'] = ($primeiro_login == 1);
                
                // Redirecionar conforme o primeiro login
                if ($primeiro_login == 1) {
                    header('Location: alterar_password_chefe.php');
                    exit();
                } else {
                    header('Location: indexchefeoficina.php');
                    exit();
                }
            } else {
                $mensagem = 'error:Email ou password incorretos';
            }
        } else {
            $mensagem = 'error:Erro na conexão';
        }
    }
}

// Separar tipo e mensagem
$tipo_mensagem = '';
$texto_mensagem = '';
if (!empty($mensagem)) {
    list($tipo_mensagem, $texto_mensagem) = explode(':', $mensagem, 2);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Chefe de Oficina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #cc5500;
            --dark: #0a0a0a;
            --dark-light: #1a1a1a;
            --text: #ffffff;
            --text-secondary: #cccccc;
            --error: #dc3545;
            --success: #28a745;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, var(--dark) 0%, #1a1a1a 100%);
            font-family: 'Inter', sans-serif;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 2px solid var(--primary);
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.3);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 107, 0, 0.3);
            border-radius: 8px;
            color: var(--text);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.2);
        }
        
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 0, 0.4);
        }
        
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }
        
        .mensagem.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid var(--error);
            color: #ffaaaa;
        }
        
        .mensagem.success {
            background: rgba(40, 167, 69, 0.2);
            border: 2px solid var(--success);
            color: #aaffaa;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
        }
        
        .login-info {
            margin-top: 25px;
            padding: 15px;
            background: rgba(255, 107, 0, 0.1);
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .login-info strong {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="bi bi-person-badge"></i> Chefe de Oficina</h1>
            <p>Acesso ao painel de gestão</p>
        </div>

        <?php if (!empty($tipo_mensagem)): ?>
            <div class="mensagem <?= $tipo_mensagem ?>">
                <?= htmlspecialchars($texto_mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="bi bi-envelope"></i> Email</label>
                <input type="email" 
                       name="email" 
                       placeholder="seu@email.com" 
                       required
                       autofocus>
            </div>

            <div class="form-group">
                <label><i class="bi bi-lock"></i> Password</label>
                <div class="password-wrapper">
                    <input type="password" 
                           name="password" 
                           id="password"
                           placeholder="Sua password" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Entrar
            </button>
        </form>

        <div class="login-info">
            <i class="bi bi-info-circle"></i>
            <strong>Credenciais de teste:</strong> Email: admin@AUTOMANAGER.com | Password: password
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const button = document.querySelector('.toggle-password i');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.classList.remove('bi-eye');
                button.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                button.classList.remove('bi-eye-slash');
                button.classList.add('bi-eye');
            }
        }
        
        // Prevenir reenvio do formulário
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>