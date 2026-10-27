<?php
session_start();
require('ligar.php');

// Verificar se o mecânico está logado e se é primeiro login
if (!isset($_SESSION['mecanico_id']) || !isset($_SESSION['primeiro_login']) || !$_SESSION['primeiro_login']) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_password = $_POST['nova_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    if (empty($nova_password) || empty($confirmar_password)) {
        $error = "Por favor, preencha ambos os campos.";
    } elseif ($nova_password !== $confirmar_password) {
        $error = "As passwords não coincidem.";
    } elseif (strlen($nova_password) < 6) {
        $error = "A password deve ter pelo menos 6 caracteres.";
    } else {
        // Atualizar a password
        $hash = password_hash($nova_password, PASSWORD_DEFAULT);
        $mecanico_id = $_SESSION['mecanico_id'];
        
        $stmt = $con->prepare("UPDATE mecanicos SET password = ?, primeiro_login = 0 WHERE id_mecanico = ?");
        $stmt->bind_param("si", $hash, $mecanico_id);
        
        if ($stmt->execute()) {
            $_SESSION['primeiro_login'] = 0;
            $success = "Password alterada com sucesso!";
            // Redirecionar para a página de funcionários após 2 segundos
            header("Refresh: 2; URL=funcionarios.php");
        } else {
            $error = "Erro ao atualizar a password. Tente novamente.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mudar Password - AUTOMANAGER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #000000;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(255, 69, 0, 0.2);
            border: 1px solid #ff4500;
            width: 100%;
            max-width: 400px;
        }

        h2 {
            color: #ff4500;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8em;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .error {
            background: rgba(255, 69, 0, 0.1);
            color: #ff4500;
            border: 1px solid #ff4500;
        }

        .success {
            background: rgba(0, 255, 136, 0.1);
            color: #00ff88;
            border: 1px solid #00ff88;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #cccccc;
            font-weight: 600;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            border-radius: 8px;
            background: #1a1a1a;
            color: white;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #00ff88;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #00ff88;
            color: #000000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #00cc6a;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.4);
            transform: translateY(-2px);
        }

        .user-info {
            text-align: center;
            margin-bottom: 20px;
            color: #cccccc;
        }

        .user-info strong {
            color: #ff4500;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fa-solid fa-tools" aria-hidden="true"></i> Mudar Password</h2>
        
        <div class="user-info">
            <p>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['mecanico_nome'] ?? 'Mecânico') ?></strong></p>
            <p>É a sua primeira vez a entrar. Por favor, altere a sua password.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="nova_password">Nova Password</label>
                <input type="password" id="nova_password" name="nova_password" required minlength="6">
            </div>

            <div class="form-group">
                <label for="confirmar_password">Confirmar Password</label>
                <input type="password" id="confirmar_password" name="confirmar_password" required minlength="6">
            </div>

            <button type="submit" class="btn">Alterar Password</button>
        </form>
    </div>
</body>
</html>