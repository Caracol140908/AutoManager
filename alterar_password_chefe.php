<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_password_padrao.php';

if (!isset($_SESSION['chefe_id']) || !isset($_SESSION['is_chefe'])) {
    header('Location: login_chefe.php');
    exit();
}

$primeiro_login = isset($_SESSION['primeiro_login']) && $_SESSION['primeiro_login'] == 1;
if (!$primeiro_login) {
    header('Location: indexchefeoficina.php');
    exit();
}

$chefe_id = (int) $_SESSION['chefe_id'];
$chefe_nome = $_SESSION['chefe_nome'] ?? 'Chefe';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mudar_password'])) {
    $nova_password = $_POST['nova_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    if (empty($nova_password) || empty($confirmar_password)) {
        $error_password = 'Por favor, preencha ambos os campos.';
    } elseif ($nova_password !== $confirmar_password) {
        $error_password = 'As palavras-chave não correspondem.';
    } elseif (strlen($nova_password) < 6) {
        $error_password = 'A palavra-chave deve ter pelo menos 6 caracteres.';
    } elseif ($nova_password === am_password_padrao_funcionario()) {
        $error_password = 'Escolha uma palavra-passe diferente da palavra-passe inicial da oficina.';
    } else {
        $hash = password_hash($nova_password, PASSWORD_DEFAULT);
        $stmt = $con->prepare('UPDATE chefes_oficina SET password = ?, primeiro_login = 0 WHERE id_chefe = ?');
        $stmt->bind_param('si', $hash, $chefe_id);

        if ($stmt->execute()) {
            $_SESSION['primeiro_login'] = 0;
            header('Location: indexchefeoficina.php');
            exit();
        }

        $error_password = 'Erro ao atualizar a palavra-chave. Tente novamente.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mudança de Palavra-Chave - AUTOMANAGER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg: var(--am-black-2, #0a0a0a);
            --text: var(--am-text, #f4f4f5);
            --muted: var(--am-text-muted, #a1a1aa);
            --accent: var(--am-accent, #ea580c);
            --accent-hi: var(--am-accent-hover, #f97316);
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(1200px 500px at 10% -20%, rgba(234, 88, 12, 0.12), transparent 60%),
                radial-gradient(900px 400px at 100% 0%, rgba(249, 115, 22, 0.08), transparent 55%),
                var(--bg);
            color: var(--text);
            font-family: var(--am-font, Inter, system-ui, sans-serif);
        }

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
            overflow-y: auto;
        }

        .popup-content {
            background: linear-gradient(180deg, #161616, #0f0f0f);
            padding: 26px;
            border-radius: 16px;
            width: 100%;
            max-width: 560px;
            border: 1px solid rgba(249, 115, 22, 0.4);
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255,255,255,.04);
        }

        .popup-header {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .popup-header h2 {
            color: var(--accent-hi);
            margin: 0 0 10px 0;
            letter-spacing: .2px;
            font-size: 1.35rem;
        }

        .popup-header p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .popup-header code {
            color: var(--accent-hi);
            background: rgba(249, 115, 22, 0.12);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .user-badge {
            margin-top: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(249, 115, 22, 0.1);
            border: 1px solid rgba(249, 115, 22, 0.25);
            color: var(--text);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: var(--text);
            font-weight: 600;
            font-size: 1.02rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 13px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: var(--am-black, #050505);
            color: var(--text);
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.22);
        }

        .popup-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }

        .btn-success {
            background: linear-gradient(180deg, var(--accent-hi), var(--accent));
            color: #0a0a0a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 11px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            min-width: 140px;
        }

        .btn-success:hover {
            filter: brightness(1.06);
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="popup-overlay" id="passwordPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2>[SEGURANCA] Mudança de Palavra-Chave Obrigatória</h2>
                <p>É a sua primeira vez a entrar. Utilize a palavra-passe inicial <code><?= htmlspecialchars(am_password_padrao_funcionario()) ?></code> e defina uma nova palavra-passe pessoal.</p>
                <div class="user-badge">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <?= htmlspecialchars($chefe_nome) ?>
                </div>
            </div>

            <?php if (isset($error_password)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_password) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="nova_password">Nova Palavra-Chave</label>
                    <input type="password" id="nova_password" name="nova_password" required minlength="6" autofocus>
                </div>

                <div class="form-group">
                    <label for="confirmar_password">Confirmar Palavra-Chave</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required minlength="6">
                </div>

                <div class="popup-actions">
                    <button type="submit" name="mudar_password" class="btn-success">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Alterar
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
