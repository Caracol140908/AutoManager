<?php
session_start();
unset($_SESSION['user_bloqueado_email'], $_SESSION['user_bloqueado_nome'], $_SESSION['user_bloqueado_id']);
require('ligar.php');
require_once __DIR__ . '/inc_marcas_modelos.php';

$am_marcas = am_marcas_listar($con);
$am_modelos_por_marca = am_modelos_por_marca_map($con);

if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $error = "A sua sessão expirou. Inicie sessão novamente para continuar.";
}

// Verificar modo manutenção
$modo_manutencao = false;

// Verificar se o site está em manutenção
$estado_site_stmt = $con->prepare("SELECT estado FROM estado_site WHERE id = 1");
if ($estado_site_stmt) {
    $estado_site_stmt->execute();
    $estado_site_result = $estado_site_stmt->get_result();
    $estado_site = $estado_site_result->fetch_assoc();
    $estado_site_stmt->close();
    $modo_manutencao = ($estado_site['estado'] == '1');
}

// Se o utilizador já estiver com sessão iniciada (utilizador normal), redireciona para o index
if (!empty($_SESSION['user']['id_utilizador'])) {
    header('Location: index.php');
    exit;
}

// Se já estiver logado como admin, redireciona para admin
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: indexadmin.php');
    exit;
}

// Se já estiver logado como mecânico, redireciona para funcionarios
if (isset($_SESSION['mecanico_id'])) {
    header('Location: funcionarios.php');
    exit;
}

// Se já estiver logado como chefe de oficina, redireciona
if (isset($_SESSION['chefe_id'])) {
    header('Location: indexchefeoficina.php');
    exit;
}

// Variáveis de mensagens de erro e sucesso
$error = '';
$success = '';
$login_blocked = false;

$contas_master = [
    'chefeoficina@automanager.pt' => ['tipo' => 'chefe', 'show' => 'selecionar_perfil'],
    'mecanico@automanager.pt' => ['tipo' => 'mecanico', 'show' => 'selecionar_perfil'],
];

/** Função simples para obter dados de um campo do formulário sem espaços desnecessários */
function input($key) {
    return trim($_POST[$key] ?? '');
}

/** Tratar ações do formulário */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];

// ---------------- LOGIN ----------------
    if ($action === 'login') {
        $email = input('email');
        $password = input('password');

        if (!$email || !$password) {
            $error = "Preenche todos os campos.";
        } else {
            $email_lower = strtolower(trim($email));
            if (isset($contas_master[$email_lower]) && $password === 'Automanager26!') {
                $_SESSION['master_login_tipo'] = $contas_master[$email_lower]['tipo'];
                header("Location: login.php?show=" . $contas_master[$email_lower]['show']);
                exit;
            }

            // 1) Tentar login como ADMIN (primeiro) - SEMPRE permite login de admin mesmo em manutenção
            $stmt = $con->prepare("SELECT id_admin, username, password, nivel FROM administrador WHERE email=? LIMIT 1");
            if (!$stmt) {
                $error = "Erro na preparação da query admin: " . $con->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // Verifica se é admin e se a password está correta
                if ($admin && (password_verify($password, $admin['password']) || $password === $admin['password'])) {
                    // Login admin bem-sucedido
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $admin['id_admin'];
                    $_SESSION['username']  = $admin['username'];
                    $_SESSION['is_admin']  = true;

                    header("Location: indexadmin.php");
                    exit;
                }

                // 2) Se estiver em manutenção, bloqueia outros logins
                if ($modo_manutencao) {
                    $error = " O site está em manutenção. Apenas administradores podem fazer login. Tente mais tarde.";
                } else {
                    // 3) Tentar login como MECÂNICO/FUNCIONÁRIO
                    $stmt = $con->prepare("SELECT id_mecanico, nome_mecanico, email, password, primeiro_login FROM mecanicos WHERE email=? LIMIT 1");
                    if (!$stmt) {
                        $error = "Erro na preparação da query mecânico: " . $con->error;
                    } else {
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $mecanico = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($mecanico && password_verify($password, $mecanico['password'])) {
                            // Login mecânico bem-sucedido
                            session_regenerate_id(true);
                            $_SESSION['mecanico_id'] = $mecanico['id_mecanico'];
                            $_SESSION['mecanico_nome'] = $mecanico['nome_mecanico'];
                            $_SESSION['mecanico_email'] = $mecanico['email'];
                            $_SESSION['primeiro_login'] = $mecanico['primeiro_login'];

                            // Se for primeiro login, redireciona para funcionarios com popup
                            if ($mecanico['primeiro_login']) {
                                header("Location: funcionarios.php?primeiro_login=1");
                                exit;
                            } else {
                                header("Location: funcionarios.php");
                                exit;
                            }
                        }

                        // 4) Tentar login como CHEFE DE OFICINA
                        $stmt = $con->prepare("SELECT id_chefe, nome, email, password, primeiro_login FROM chefes_oficina WHERE email=? LIMIT 1");
                        if (!$stmt) {
                            $error = "Erro na preparação da query chefe: " . $con->error;
                        } else {
                            $stmt->bind_param("s", $email);
                            $stmt->execute();
                            $chefe = $stmt->get_result()->fetch_assoc();
                            $stmt->close();

                            if ($chefe && password_verify($password, $chefe['password'])) {
                                session_regenerate_id(true);
                                $_SESSION['chefe_id'] = $chefe['id_chefe'];
                                $_SESSION['chefe_nome'] = $chefe['nome'];
                                $_SESSION['chefe_email'] = $chefe['email'];
                                $_SESSION['is_chefe'] = true;
                                $_SESSION['primeiro_login'] = $chefe['primeiro_login'];

                                if ($chefe['primeiro_login'] == 1) {
                                    header("Location: alterar_password_chefe.php");
                                    exit;
                                }
                                header("Location: indexchefeoficina.php");
                                exit;
                            }

                            // 5) Login como UTILIZADOR normal
                            $stmt = $con->prepare("SELECT id_utilizador, nome_utilizador, carro, email, password, bloqueado FROM utilizador WHERE email=? LIMIT 1");
                            if (!$stmt) {
                                $error = "Erro na preparação da query utilizador: " . $con->error;
                            } else {
                                $stmt->bind_param("s", $email);
                                $stmt->execute();
                                $user = $stmt->get_result()->fetch_assoc();
                                $stmt->close();

                                if ($user) {
                                    if (password_verify($password, $user['password'])) {
                                        if (isset($user['bloqueado']) && (int) $user['bloqueado'] === 1) {
                                            $login_blocked = true;
                                            $error = "A sua conta está bloqueada.";
                                        } else {
                                            session_regenerate_id(true);
                                            $_SESSION['user'] = [
                                                'id_utilizador'   => $user['id_utilizador'],
                                                'nome_utilizador' => $user['nome_utilizador'],
                                                'email'           => $user['email'],
                                                'carro'           => $user['carro'] ?? ''
                                            ];

                                            $ip_address = $_SERVER['REMOTE_ADDR'];
                                            $check_sessao = $con->query("SELECT id FROM sessoes_temporarias WHERE ip_address = '$ip_address'");
                                            if ($check_sessao && $check_sessao->num_rows > 0) {
                                                $sessao = $check_sessao->fetch_assoc();
                                                $con->query("UPDATE sessoes_temporarias SET utilizador_id = {$user['id_utilizador']} WHERE id = " . $sessao['id']);
                                            }

                                            header("Location: index.php");
                                            exit;
                                        }
                                    } else {
                                        $error = "Email ou palavra-passe incorretos.";
                                    }
                                } else {
                                    $error = "Email ou palavra-passe incorretos.";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    elseif ($action === 'login_master_utilizador') {
        $tipo = $_POST['tipo_master'] ?? '';
        $id = intval($_POST['id_master'] ?? 0);
        $password = input('password');

        if (!$tipo || !$id || !$password) {
            $error = "Seleciona um utilizador e insere a palavra-passe.";
        } else {
            if ($tipo === 'chefe') {
                $stmt = $con->prepare("SELECT id_chefe, nome, email, password, primeiro_login FROM chefes_oficina WHERE id_chefe = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $u = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($u && password_verify($password, $u['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['chefe_id'] = $u['id_chefe'];
                        $_SESSION['chefe_nome'] = $u['nome'];
                        $_SESSION['chefe_email'] = $u['email'];
                        $_SESSION['is_chefe'] = true;
                        $_SESSION['primeiro_login'] = $u['primeiro_login'];
                        unset($_SESSION['master_login_tipo']);
                        if ($u['primeiro_login'] == 1) {
                            header("Location: alterar_password_chefe.php");
                        } else {
                            header("Location: indexchefeoficina.php");
                        }
                        exit;
                    }
                }
                $error = "Palavra-passe inválida para o utilizador selecionado.";
            } elseif ($tipo === 'mecanico') {
                $stmt = $con->prepare("SELECT id_mecanico, nome_mecanico, email, password, primeiro_login FROM mecanicos WHERE id_mecanico = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $u = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($u && password_verify($password, $u['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['mecanico_id'] = $u['id_mecanico'];
                        $_SESSION['mecanico_nome'] = $u['nome_mecanico'];
                        $_SESSION['mecanico_email'] = $u['email'];
                        $_SESSION['primeiro_login'] = $u['primeiro_login'];
                        unset($_SESSION['master_login_tipo']);
                        if ($u['primeiro_login']) {
                            header("Location: funcionarios.php?primeiro_login=1");
                        } else {
                            header("Location: funcionarios.php");
                        }
                        exit;
                    }
                }
                $error = "Palavra-passe inválida para o utilizador selecionado.";
            }
        }
    }
    // ---------------- REGISTO ----------------
    elseif ($action === 'register') {
        // Bloquear registos durante manutenção
        if ($modo_manutencao) {
            $error = "[FERRAMENTAS] O site está em manutenção. Não é possível criar novas contas. Tente mais tarde.";
        } else {
            $nome = input('nome');
            $carro = input('carro');
            $email = input('email');
            $password = input('password');
            $termos = isset($_POST['termos']);

            if (!$nome || !$email || !$password || !$termos) {
                $error = "Preenche todos os campos e aceita os termos.";
            } else {
                // Verifica se o email já existe
                $stmt = $con->prepare("SELECT id_utilizador FROM utilizador WHERE email=?");
                if (!$stmt) {
                    $error = "Erro na preparação da query: " . $con->error;
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $exists = $stmt->get_result()->num_rows > 0;
                    $stmt->close();

                    if ($exists) {
                        $error = "Este email já está registado.";
                    } else {
                        // Cria a conta com a password encriptada
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $ins = $con->prepare("
                            INSERT INTO utilizador (nome_utilizador, carro, email, password, created_at, termos_aceite, data_aceite)
                            VALUES (?, ?, ?, ?, NOW(), 1, NOW())
                        ");
                        if (!$ins) {
                            $error = "Erro na preparação da query de inserção: " . $con->error;
                        } else {
                            $ins->bind_param("ssss", $nome, $carro, $email, $hash);

                            if ($ins->execute()) {
                                $novo_user_id = (int) $con->insert_id;

                                // Registar também na tabela "carro" (quando foi selecionado no registo)
                                if (!empty($carro)) {
                                    $partes_carro = preg_split('/\s+/', trim($carro), 2);
                                    $marca_carro = $partes_carro[0] ?? '';
                                    $modelo_carro = $partes_carro[1] ?? '';

                                    if ($marca_carro !== '') {
                                        $ano_padrao = (int) date('Y');
                                        $matricula_padrao = '';

                                        $ins_carro = $con->prepare("
                                            INSERT INTO carro (id_utilizador, marca, modelo, ano, matricula, created_at)
                                            VALUES (?, ?, ?, ?, ?, NOW())
                                        ");

                                        if ($ins_carro) {
                                            $ins_carro->bind_param("issis", $novo_user_id, $marca_carro, $modelo_carro, $ano_padrao, $matricula_padrao);
                                            if (!$ins_carro->execute()) {
                                                error_log("Falha ao registar carro no registo: " . $ins_carro->error);
                                            }
                                            $ins_carro->close();
                                        }
                                    }
                                }

                                $success = "Conta criada com sucesso! Agora podes entrar.";
                            } else {
                                $error = "Erro ao criar conta. Tenta novamente.";
                            }
                            $ins->close();
                        }
                    }
                }
            }
        }
    }

    // ---------------- PEDIDO DE RESET DE SENHA ----------------
    elseif ($action === 'reset_request') {
        // Bloquear reset durante manutenção
        if ($modo_manutencao) {
            $error = "[FERRAMENTAS] O site está em manutenção. Não é possível recuperar senhas. Tente mais tarde.";
        } else {
            $email = input('email');

            if (!$email) {
                $error = "Insere um email.";
            } else {
                $found_user = null;
                $user_type = null;
                $nome_campo = null;

                // 1) Verifica se o email existe na tabela administrador
                $stmt = $con->prepare("SELECT username FROM administrador WHERE email=? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $admin = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($admin) {
                        $found_user = $admin['username'];
                        $user_type = 'administrador';
                        $nome_campo = 'username';
                    }
                }

                // 2) Se não encontrou, verifica na tabela chefes_oficina
                if (!$found_user) {
                    $stmt = $con->prepare("SELECT nome FROM chefes_oficina WHERE email=? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $chefe = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($chefe) {
                            $found_user = $chefe['nome'];
                            $user_type = 'chefes_oficina';
                            $nome_campo = 'nome';
                        }
                    }
                }

                // 3) Se não encontrou, verifica na tabela mecanicos
                if (!$found_user) {
                    $stmt = $con->prepare("SELECT nome_mecanico FROM mecanicos WHERE email=? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $mecanico = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($mecanico) {
                            $found_user = $mecanico['nome_mecanico'];
                            $user_type = 'mecanicos';
                            $nome_campo = 'nome_mecanico';
                        }
                    }
                }

                // 4) Se não encontrou, verifica na tabela utilizador
                if (!$found_user) {
                    $stmt = $con->prepare("SELECT nome_utilizador FROM utilizador WHERE email=? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($user) {
                            $found_user = $user['nome_utilizador'];
                            $user_type = 'utilizador';
                            $nome_campo = 'nome_utilizador';
                        }
                    }
                }

                if ($found_user) {
                    // Gera um código aleatório e define tempo de validade (15 minutos)
                    $code = rand(100000, 999999);
                    $expire = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                    // Guarda o código e o tempo limite na base de dados
                    $upd = $con->prepare("UPDATE {$user_type} SET reset_code=?, reset_expire=? WHERE email=?");
                    if (!$upd) {
                        $error = "Erro na preparação da query de atualização: " . $con->error;
                    } else {
                        $upd->bind_param("sss", $code, $expire, $email);
                        $upd->execute();
                        $upd->close();

                        // Prepara o email com o código de recuperação
                        $mensagem = "
                            <h2>Recuperação de senha</h2>
                            <p>Olá <strong>" . htmlspecialchars($found_user) . "</strong>,</p>
                            <p>O teu código de recuperação é: <strong>{$code}</strong></p>
                            <p>Válido por 15 minutos.</p>
                        ";
                        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: geral@automanager.pt\r\n";

                        // Envia o email
                        if (mail($email, "Recuperação de senha - AutoManager", $mensagem, $headers)) {
                            // Guarda o email, o código e o tipo de utilizador na sessão
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['reset_code'] = (string)$code;
                            $_SESSION['reset_user_type'] = $user_type;
                            // Redireciona para a página onde se insere o código
                            header("Location: login.php?show=code");
                            exit;
                        } else {
                            $error = "Erro ao enviar email.";
                        }
                    }
                } else {
                    $error = "Email não encontrado em nenhuma conta.";
                }
            }
        }
    }

    // ---------------- VERIFICAÇÃO DO CÓDIGO DE RESET ----------------
    elseif ($action === 'verify_code') {
        // Bloquear verificação durante manutenção
        if ($modo_manutencao) {
            $error = " O site está em manutenção. Não é possível recuperar senhas. Tente mais tarde.";
        } else {
            // Compara o código inserido com o guardado na sessão
            if (input('code') === ($_SESSION['reset_code'] ?? '')) {
                $_SESSION['verified_email'] = $_SESSION['reset_email'];
                // Redireciona para a página de nova palavra-passe
                header("Location: login.php?show=newpassword");
                exit;
            } else {
                $error = "Código inválido.";
            }
        }
    }

    // ---------------- DEFINIR NOVA SENHA ----------------
    elseif ($action === 'set_new_password') {
        // Bloquear alteração durante manutenção
        if ($modo_manutencao) {
            $error = " O site está em manutenção. Não é possível alterar senhas. Tente mais tarde.";
        } else {
            $email = $_SESSION['verified_email'] ?? '';
            $user_type = $_SESSION['reset_user_type'] ?? 'utilizador';
            $newpass = input('new_password');

            if ($email && $newpass) {
                // Atualiza a password encriptada e remove os dados de recuperação
                $hash = password_hash($newpass, PASSWORD_DEFAULT);
                $stmt = $con->prepare("UPDATE {$user_type} SET password=?, reset_code=NULL, reset_expire=NULL WHERE email=?");
                if (!$stmt) {
                    $error = "Erro na preparação da query: " . $con->error;
                } else {
                    $stmt->bind_param("ss", $hash, $email);

                    if ($stmt->execute()) {
                        $stmt->close();
                        // Limpa apenas os dados de reset da sessão, mantém a sessão em marcha
                        unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['verified_email'], $_SESSION['reset_user_type']);
                        // Redireciona para o login com mensagem de sucesso
                        header("Location: login.php?show=login&success=1");
                        exit;
                    } else {
                        $error = "Erro ao atualizar senha.";
                        $stmt->close();
                    }
                }
            } else {
                $error = "Preenche a nova senha.";
            }
        }
    }
}

// Define qual formulário mostrar (login, registo, código, nova senha)
$show_form = $_GET['show'] ?? 'login';

$master_tipo = $_SESSION['master_login_tipo'] ?? '';
$master_usuarios = [];
if ($show_form === 'selecionar_perfil') {
    $master_tipo = $_SESSION['master_login_tipo'] ?? '';
}

if ($master_tipo === 'chefe') {
    $rs = $con->query("SELECT id_chefe AS id, nome AS nome, email, 'chefe' AS tipo FROM chefes_oficina ORDER BY nome ASC");
    if ($rs) while ($r = $rs->fetch_assoc()) $master_usuarios[] = $r;
} elseif ($master_tipo === 'mecanico') {
    $rs = $con->query("SELECT id_mecanico AS id, nome_mecanico AS nome, email, 'mecanico' AS tipo FROM mecanicos ORDER BY nome_mecanico ASC");
    if ($rs) while ($r = $rs->fetch_assoc()) $master_usuarios[] = $r;
}

// Se acabou de alterar a senha, mostra mensagem de sucesso
if (!empty($_GET['success'])) {
    $success = "Senha atualizada com sucesso! Podes entrar agora.";
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login / Registo - AutoManager</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Reset e estilos base */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #030712;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }

    /* Background animado com gradientes */
    .animated-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
    }

    .bg-gradient {
        position: absolute;
        width: 150%;
        height: 150%;
        background: radial-gradient(circle at 30% 50%, rgba(255, 69, 0, 0.15) 0%, transparent 50%),
                    radial-gradient(circle at 70% 80%, rgba(255, 107, 0, 0.12) 0%, transparent 50%),
                    linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #000000 100%);
        animation: gradientShift 20s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { transform: scale(1) rotate(0deg); }
        33% { transform: scale(1.1) rotate(1deg); }
        66% { transform: scale(0.95) rotate(-1deg); }
    }

    /* Partículas flutuantes */
    .particles {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
    }

    .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: rgba(255, 69, 0, 0.3);
        border-radius: 50%;
        animation: floatParticle 15s infinite linear;
    }

    @keyframes floatParticle {
        0% {
            transform: translateY(100vh) translateX(0);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translateY(-100vh) translateX(100px);
            opacity: 0;
        }
    }

    /* Container principal com layout dividido */
    .main-container {
        display: flex;
        max-width: 1300px;
        width: 100%;
        background: rgba(10, 10, 20, 0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 40px;
        border: 1px solid rgba(255, 69, 0, 0.2);
        box-shadow: 
            0 50px 100px -20px rgba(0, 0, 0, 0.5),
            0 0 0 1px rgba(255, 69, 0, 0.1),
            inset 0 1px 1px rgba(255, 255, 255, 0.1);
        overflow: hidden;
        position: relative;
        animation: containerAppear 0.8s cubic-bezier(0.23, 1, 0.32, 1);
    }

    @keyframes containerAppear {
        0% {
            opacity: 0;
            transform: scale(0.95) translateY(30px);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .main-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(255, 69, 0, 0.1),
            transparent
        );
        transition: left 1s ease;
        pointer-events: none;
    }

    .main-container:hover::before {
        left: 100%;
    }

    /* Lado esquerdo - Info com glassmorphism */
    .info-side {
        flex: 1.2;
        padding: 60px 50px;
        position: relative;
        overflow: hidden;
        background: rgba(255, 69, 0, 0.03);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 69, 0, 0.2);
        display: flex;
        flex-direction: column;
    }

    .info-side::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 20% 30%, rgba(255, 69, 0, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 80% 70%, rgba(255, 69, 0, 0.08) 0%, transparent 50%);
        pointer-events: none;
    }

    .info-side > * {
        position: relative;
        z-index: 1;
    }

    .logo-area {
        margin-bottom: 40px;
    }

    .brand-name {
        font-size: 3.5em;
        font-weight: 800;
        background: linear-gradient(135deg, #fff 0%, #ff4500 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1.2;
        letter-spacing: -1px;
    }

    .brand-tagline {
        font-size: 1.1em;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 10px;
        font-weight: 300;
    }

    .timer-card {
        background: rgba(255, 69, 0, 0.1);
        border-radius: 30px;
        padding: 30px;
        margin: 30px 0;
        border: 1px solid rgba(255, 69, 0, 0.3);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
    }

    .timer-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 69, 0, 0.2) 0%, transparent 70%);
        animation: rotate 15s linear infinite;
    }

    .timer-label {
        font-size: 1.2em;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .timer-value {
        display: flex;
        align-items: baseline;
        gap: 5px;
    }

    .timer-number {
        font-size: 5em;
        font-weight: 800;
        color: #ff4500;
        text-shadow: 0 0 30px rgba(255, 69, 0, 0.5);
        line-height: 1;
    }

    .timer-unit {
        font-size: 1.2em;
        color: rgba(255, 255, 255, 0.6);
        font-weight: 500;
    }

    .timer-subtitle {
        font-size: 1.1em;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 10px;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin: 30px 0;
    }

    .feature-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 69, 0, 0.2);
        border-radius: 20px;
        padding: 20px;
        transition: all 0.3s ease;
        cursor: default;
    }

    .feature-item:hover {
        background: rgba(255, 69, 0, 0.1);
        transform: translateY(-5px);
        border-color: rgba(255, 69, 0, 0.4);
    }

    .feature-icon {
        font-size: 2em;
        margin-bottom: 10px;
    }

    .feature-title {
        font-weight: 600;
        color: #fff;
        margin-bottom: 5px;
    }

    .feature-desc {
        font-size: 0.9em;
        color: rgba(255, 255, 255, 0.6);
    }

    .dica-box {
        background: linear-gradient(135deg, rgba(255, 69, 0, 0.2), rgba(255, 107, 0, 0.1));
        border-radius: 20px;
        padding: 25px;
        margin-top: auto;
        border: 1px solid rgba(255, 69, 0, 0.3);
        position: relative;
        overflow: hidden;
    }

    .dica-box::after {
        content: '[DESTAQUE]';
        position: absolute;
        bottom: -20px;
        right: -20px;
        font-size: 8em;
        opacity: 0.1;
        transform: rotate(15deg);
    }

    .dica-text {
        font-size: 1.1em;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.9);
    }

    .dica-text strong {
        color: #ff4500;
        font-weight: 700;
    }

    /* Lado direito - Formulários com glassmorphism */
    .container {
        flex: 0.9;
        padding: 60px 50px;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        max-width: 500px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .container h2 {
        font-size: 2.2em;
        font-weight: 700;
        margin-bottom: 40px;
        background: linear-gradient(135deg, #fff 0%, #ff4500 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        position: relative;
    }

    .container h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, #ff4500, #ff6b00);
        border-radius: 2px;
    }

    .divider {
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 30px 0;
        color: rgba(255, 255, 255, 0.3);
    }

    .divider-line {
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 69, 0, 0.3), transparent);
    }

    .divider-text {
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: rgba(255, 255, 255, 0.5);
    }

    .message {
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 25px;
        font-weight: 500;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: messageSlide 0.4s ease-out;
        border: 1px solid;
    }

    @keyframes messageSlide {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .error {
        background: rgba(255, 69, 0, 0.15);
        color: #ff4500;
        border-color: rgba(255, 69, 0, 0.3);
    }

    .success {
        background: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border-color: rgba(0, 255, 136, 0.3);
    }

    .warning {
        background: rgba(255, 165, 0, 0.15);
        color: #ffa500;
        border-color: rgba(255, 165, 0, 0.3);
    }

    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.7);
        font-weight: 500;
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 16px;
        color: rgba(255, 69, 0, 0.5);
        font-size: 1.1em;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="date"],
    select,
    textarea {
        width: 100%;
        padding: 16px 20px 16px 45px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 69, 0, 0.2);
        border-radius: 16px;
        color: white;
        font-family: 'Inter', sans-serif;
        font-size: 1em;
        transition: all 0.3s ease;
    }

    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: #ff4500;
        background: rgba(255, 69, 0, 0.05);
        box-shadow: 0 0 0 4px rgba(255, 69, 0, 0.1);
    }

    input::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }

    .btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #ff4500, #ff6b00);
        border: none;
        border-radius: 16px;
        color: white;
        font-weight: 600;
        font-size: 1.1em;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 20px 0;
        position: relative;
        overflow: hidden;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px -8px rgba(255, 69, 0, 0.4);
    }

    .btn:hover::before {
        left: 100%;
    }

    .btn:active {
        transform: translateY(0);
    }

    .toggle {
        text-align: center;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.95em;
        margin-top: 15px;
    }

    .toggle a {
        color: #ff4500;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        position: relative;
    }

    .toggle a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 1px;
        background: linear-gradient(90deg, #ff4500, #ff6b00);
        transition: width 0.3s ease;
    }

    .toggle a:hover {
        color: #ff6b00;
    }

    .toggle a:hover::after {
        width: 100%;
    }

    .forgot-password {
        display: block;
        margin-top: 15px;
    }

    .termos {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 20px 0;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95em;
        padding: 15px;
        background: rgba(255, 69, 0, 0.03);
        border-radius: 12px;
        border: 1px solid rgba(255, 69, 0, 0.1);
    }

    .termos input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #ff4500;
    }

    .termos a {
        color: #ff4500;
        text-decoration: none;
        font-weight: 600;
    }

    .termos a:hover {
        text-decoration: underline;
    }

    /* Loading state */
    .btn.loading {
        position: relative;
        color: transparent;
    }

    .btn.loading::after {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin: -10px 0 0 -10px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Scrollbar personalizado */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.3);
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #ff4500, #ff6b00);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #ff5500, #ff7b00);
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .features-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1000px) {
        .main-container {
            flex-direction: column;
            max-width: 600px;
        }
        
        .info-side {
            border-right: none;
            border-bottom: 1px solid rgba(255, 69, 0, 0.2);
            padding: 40px;
        }
        
        .container {
            max-width: 100%;
            padding: 40px;
        }
        
        .brand-name {
            font-size: 2.5em;
        }
        
    }

    @media (max-width: 600px) {
        body {
            padding: 10px;
        }
        
        .info-side,
        .container {
            padding: 30px 20px;
        }
        
        .timer-number {
            font-size: 3.5em;
        }
        
        .brand-name {
            font-size: 2em;
        }
        
        .container h2 {
            font-size: 1.8em;
        }
        
        .feature-item {
            padding: 15px;
        }
        
    }

    /* Login — preto & laranja (clean, uma coluna) */
    body.login-page {
        background:
            radial-gradient(ellipse 90% 60% at 50% -15%, rgba(249, 115, 22, 0.14), transparent 55%),
            linear-gradient(165deg, var(--am-black, #0e0e12) 0%, var(--am-black-2, #121214) 100%);
        color: var(--am-text, #f4f4f8);
    }

    .animated-bg,
    .particles,
    .info-side,
    .main-container::before {
        display: none !important;
    }

    .main-container {
        max-width: 420px;
        border-radius: 20px;
        border: 1px solid var(--am-border-strong, rgba(255, 255, 255, 0.12));
        background: var(--am-elevated, #2c2c36);
        box-shadow:
            0 24px 56px rgba(0, 0, 0, 0.5),
            0 0 0 1px rgba(249, 115, 22, 0.06);
        animation: none;
    }

    .container {
        max-width: 100%;
        flex: 1;
        padding: 36px 32px 28px;
        background: transparent;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }

    .login-brand {
        text-align: center;
        margin-bottom: 8px;
    }

    .login-brand-name {
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: var(--am-text, #f4f4f8);
    }

    .login-brand-name span {
        color: var(--am-accent, #ea580c);
    }

    .login-brand-tag {
        margin-top: 4px;
        font-size: 0.8rem;
        color: var(--am-text-muted, #b4b4c0);
    }

    .container h2 {
        font-size: 1.35rem;
        font-weight: 700;
        margin-bottom: 22px;
        text-align: center;
        color: var(--am-text, #f4f4f8);
        background: none;
        -webkit-text-fill-color: unset;
    }

    .container h2::after {
        display: none;
    }

    .divider {
        margin: 0 0 16px;
    }

    .divider-line {
        background: var(--am-border, rgba(255, 255, 255, 0.09));
    }

    .divider-text {
        color: var(--am-text-muted, #b4b4c0);
        font-size: 0.72rem;
        letter-spacing: 0.08em;
    }

    label {
        color: var(--am-text-muted, #b4b4c0);
        text-transform: none;
        letter-spacing: 0;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 6px;
    }

    .input-icon {
        color: var(--am-accent, #ea580c);
        opacity: 0.85;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="date"],
    select,
    textarea {
        background: var(--am-surface, #2a2a2a);
        color: var(--am-text, #f4f4f8);
        border: 1px solid var(--am-border-strong, rgba(255, 255, 255, 0.14));
        border-radius: 12px;
        padding: 13px 14px 13px 42px;
        font-size: 0.95rem;
    }

    input::placeholder {
        color: rgba(180, 180, 192, 0.55);
    }

    input:focus,
    select:focus,
    textarea:focus {
        border-color: var(--am-accent, #ea580c);
        background: var(--am-surface-2, #26262f);
        box-shadow: 0 0 0 3px var(--am-accent-soft, rgba(249, 115, 22, 0.14));
        transform: none;
    }

    select option {
        background: var(--am-surface-2, #26262f);
        color: var(--am-text, #f4f4f8);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border-radius: 12px;
        padding: 14px 18px;
        font-size: 1rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--am-accent, #ea580c), var(--am-accent-hover, #f97316));
        margin: 8px 0 4px;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 28px rgba(234, 88, 12, 0.35);
    }

    .btn:active {
        transform: translateY(0);
    }

    .message {
        border-radius: 12px;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        animation: none;
        font-size: 0.9rem;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .message.error {
        background: rgba(234, 88, 12, 0.12);
        color: #fdba74;
        border-color: rgba(249, 115, 22, 0.35);
    }

    .message.success {
        background: rgba(34, 197, 94, 0.12);
        color: #86efac;
        border-color: rgba(34, 197, 94, 0.35);
    }

    .message.warning {
        background: rgba(251, 191, 36, 0.1);
        color: #fde68a;
        border-color: rgba(251, 191, 36, 0.35);
    }

    .toggle {
        color: var(--am-text-muted, #b4b4c0);
        line-height: 1.65;
    }

    .toggle a {
        color: var(--am-accent-hover, #f97316);
    }

    .termos {
        background: rgba(0, 0, 0, 0.2);
        border-color: var(--am-border, rgba(255, 255, 255, 0.09));
        color: var(--am-text-muted, #b4b4c0);
    }

    .back-home {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 18px;
        color: var(--am-text-muted, #b4b4c0);
        text-decoration: none;
        font-size: 0.875rem;
        transition: color 0.2s ease;
    }

    .back-home:hover {
        color: var(--am-accent-hover, #f97316);
    }

    .master-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 16px;
        max-height: 240px;
        overflow-y: auto;
    }

    .master-user {
        border: 1px solid var(--am-border-strong, rgba(255, 255, 255, 0.14));
        border-radius: 12px;
        padding: 12px 14px;
        background: var(--am-surface, #2a2a2a);
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .master-user:hover,
    .master-user.active {
        border-color: var(--am-accent, #ea580c);
        box-shadow: 0 0 0 3px var(--am-accent-soft, rgba(249, 115, 22, 0.14));
    }

    .master-user strong {
        display: block;
        color: var(--am-text, #f4f4f8);
        font-size: 0.95rem;
    }

    .master-user small {
        color: var(--am-text-muted, #b4b4c0);
        word-break: break-word;
    }

    @media (max-width: 480px) {
        .container {
            padding: 28px 22px 22px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .btn:hover {
            transform: none;
        }
    }
</style>
</head>
<body class="login-page">
<div class="main-container">
    
    <!-- Lado esquerdo - Informação melhorada -->
    <div class="info-side">
        <div class="logo-area">
            <div class="brand-name">AutoManager</div>
            <div class="brand-tagline">Excelência em serviços automóveis</div>
        </div>
        
        <div class="timer-card">
            <div class="timer-label">
                <i class="fas fa-shield-halved" style="color: #ff4500;"></i>
                <span>Acesso seguro à sua conta</span>
            </div>
            <div class="timer-value">
                <span class="timer-number">24/7</span>
                <span class="timer-unit">disponível</span>
            </div>
            <div class="timer-subtitle">
                Faça login para gerir marcações, histórico e suporte.
            </div>
        </div>

        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-tools"></i></div>
                <div class="feature-title">Diagnóstico Rápido</div>
                <div class="feature-desc">Sistema inteligente de diagnóstico</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-headset"></i></div>
                <div class="feature-title">Apoio Especializado</div>
                <div class="feature-desc">Suporte por telefone e email</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div class="feature-title">Histórico Completo</div>
                <div class="feature-desc">Todas as reparações guardadas</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-euro-sign"></i></div>
                <div class="feature-title">Orçamentos Online</div>
                <div class="feature-desc">Simulações em tempo real</div>
            </div>
        </div>

        <div class="dica-box">
            <div class="dica-text">
                <strong><i class="fas fa-lightbulb"></i> Dica do Mecânico:</strong> Faça login para acompanhar reparações, falar com o suporte e gerir pagamentos num só lugar.
            </div>
        </div>

        <div style="margin-top: 30px; font-size: 0.85em; color: rgba(255,255,255,0.3); text-align: center;">
            <i class="fas fa-sync-alt"></i> Reset automático todas as segundas-feiras
        </div>
    </div>
    
    <div class="container">
        <div class="login-brand">
            <div class="login-brand-name">Auto<span>Manager</span></div>
            
        </div>
        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">Aceder à plataforma</span>
            <div class="divider-line"></div>
        </div>

        <h2 id="formTitle">
            <?php 
            switch($show_form) {
                case 'register': echo 'Criar Conta '; break;
                case 'reset': echo 'Recuperar Senha'; break;
                case 'code': echo 'Verificar Código'; break;
                case 'newpassword': echo 'Nova Senha'; break;
                case 'selecionar_perfil': echo 'Selecionar Perfil'; break;
                default: echo 'Entrar na sua conta';
            }
            ?>
        </h2>

        <?php if (!empty($login_blocked) && !empty($error)): ?>
            <div class="message warning" role="alert">
                <i class="fas fa-lock"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="message error" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Mensagem de manutenção -->
        <?php if ($modo_manutencao && $show_form == 'login'): ?>
            <div class="message warning">
                <i class="fas fa-tools"></i>
                <strong>Modo Manutenção Ativo</strong><br>
                Apenas administradores podem fazer login.
            </div>
        <?php endif; ?>

        <!-- Formulário de Login -->
        <form id="loginForm" method="POST" autocomplete="off" style="display: <?= $show_form == 'login' ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label>Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" required placeholder="O seu email">
                </div>
            </div>
            
            <div class="form-group">
                <label>Palavra-passe</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
            </div>
            
            <button class="btn" type="submit">
                <i class="fas fa-sign-in-alt"></i>
                Entrar
            </button>
            
            <div class="toggle">
                <?php if (!$modo_manutencao): ?>
                    Ainda não tens conta? <a href="#" id="showRegister">Criar conta </a><br>
                <?php endif; ?>
                <?php if (!$modo_manutencao): ?>
                    <a href="#" id="showResetRequest" class="forgot-password">
                        <i class="fas fa-question-circle"></i> Esqueci-me da senha
                    </a>
                <?php endif; ?>
            </div>
            <a href="index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Voltar à página inicial
            </a>
        </form>

        <!-- Formulário de Registo -->
        <form id="registerForm" method="POST" autocomplete="off" style="display: <?= $show_form == 'register' ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label>Nome de utilizador</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="nome" required placeholder="O seu nome completo">
                </div>
            </div>
            
            <div class="form-group">
                <label>Carro</label>
                <div class="input-wrapper">
                    <i class="fas fa-car input-icon"></i>
                    <select id="marcaCarro" name="marca_carro">
                        <?php am_render_marca_options($am_marcas, '', 'Escolha a marca'); ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Modelo</label>
                <div class="input-wrapper">
                    <i class="fas fa-car-side input-icon"></i>
                    <select id="modeloCarro" name="modelo_carro" disabled>
                        <option value="">Escolha primeiro a marca</option>
                    </select>
                </div>
                <input type="hidden" name="carro" id="carroCompleto">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" required placeholder="O seu email">
                </div>
            </div>
            
            <div class="form-group">
                <label>Palavra-passe</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" required placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
            </div>

            <div class="termos">
                <input type="checkbox" name="termos" id="termos" required>
                <label for="termos">Aceito os <a href="termos.php" target="_blank">termos de uso</a> e política de privacidade.</label>
            </div>

            <button class="btn" type="submit">
                <i class="fas fa-user-plus"></i>
                Criar Conta 
            </button>
            <div class="toggle">
                Já tens conta? <a href="#" id="showLogin">Entrar</a>
            </div>
            <a href="index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Voltar à página principal
            </a>
        </form>

        <!-- Formulário de pedido de reset -->
        <form id="resetRequestForm" method="POST" style="display: <?= $show_form == 'reset' ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="reset_request">
            
            <div class="form-group">
                <label>Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" required placeholder="O email da sua conta" value="<?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?>">
                </div>
            </div>
            
            <button class="btn" type="submit">
                <i class="fas fa-paper-plane"></i>
                Enviar código de recuperação
            </button>
            <div class="toggle">
                <a href="#" id="backToLogin">
                    <i class="fas fa-arrow-left"></i> Voltar ao login
                </a>
            </div>
        </form>

        <!-- Formulário de verificação de código -->
        <form id="resetCodeForm" method="POST" style="display: <?= $show_form == 'code' ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="verify_code">
            
            <div class="form-group">
                <label>Código de verificação</label>
                <div class="input-wrapper">
                    <i class="fas fa-qrcode input-icon"></i>
                    <input type="text" name="code" required maxlength="6" placeholder="123456">
                </div>
            </div>
            
            <button class="btn" type="submit">
                <i class="fas fa-check-circle"></i>
                Verificar código
            </button>
            <div class="toggle">
                <a href="#" id="backToResetRequest">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </form>

        <!-- Formulário de nova senha -->
        <form id="newPasswordForm" method="POST" style="display: <?= $show_form == 'newpassword' ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="set_new_password">
            
            <div class="form-group">
                <label>Nova senha</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="new_password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
            </div>
            
            <button class="btn" type="submit">
                <i class="fas fa-save"></i>
                Redefinir senha
            </button>
            <div class="toggle">
                <a href="#" id="backToLoginFromNewPass">
                    <i class="fas fa-arrow-left"></i> Voltar ao login
                </a>
            </div>
        </form>

        <form id="masterSelectForm" method="POST" style="display: <?= $show_form === 'selecionar_perfil' ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="login_master_utilizador">
            <input type="hidden" name="tipo_master" id="masterSelectedTipo" value="<?= htmlspecialchars($master_tipo) ?>">
            <input type="hidden" name="id_master" id="masterSelectedId" value="">

            <div class="master-grid">
                <?php foreach ($master_usuarios as $u): ?>
                    <div class="master-user" data-id="<?= (int)$u['id'] ?>">
                        <strong><?= htmlspecialchars($u['nome']) ?></strong>
                        <small><?= htmlspecialchars($u['email']) ?> • <?= htmlspecialchars(ucfirst($u['tipo'] ?? '')) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label>Palavra-passe do utilizador selecionado</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" required placeholder="Insira a password do utilizador">
                </div>
            </div>

            <button class="btn" type="submit">
                <i class="fas fa-sign-in-alt"></i>
                Entrar com utilizador selecionado
            </button>
            <div class="toggle">
                <a href="login.php?show=login"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navegação entre formulários
    function goTo(page) {
        window.location.href = 'login.php?show=' + page;
    }

    var el = function(id){ return document.getElementById(id); };

    if (el('showRegister')) el('showRegister').addEventListener('click', function(e){ e.preventDefault(); goTo('register'); });
    if (el('showLogin')) el('showLogin').addEventListener('click', function(e){ e.preventDefault(); goTo('login'); });
    if (el('showResetRequest')) el('showResetRequest').addEventListener('click', function(e){ e.preventDefault(); goTo('reset'); });
    if (el('backToLogin')) el('backToLogin').addEventListener('click', function(e){ e.preventDefault(); goTo('login'); });
    if (el('backToResetRequest')) el('backToResetRequest').addEventListener('click', function(e){ e.preventDefault(); goTo('reset'); });
    if (el('backToLoginFromNewPass')) el('backToLoginFromNewPass').addEventListener('click', function(e){ e.preventDefault(); goTo('login'); });

    var masterCards = document.querySelectorAll('.master-user');
    var masterSelectedId = document.getElementById('masterSelectedId');
    if (masterCards.length && masterSelectedId) {
        masterCards.forEach(function(card){
            card.addEventListener('click', function(){
                masterCards.forEach(function(c){ c.classList.remove('active'); });
                card.classList.add('active');
                masterSelectedId.value = card.getAttribute('data-id') || '';
            });
        });
        if (!masterSelectedId.value && masterCards[0]) {
            masterCards[0].classList.add('active');
            masterSelectedId.value = masterCards[0].getAttribute('data-id') || '';
        }
    }

    // Marca -> modelo no registo
    var modelosPorMarca = <?= json_encode($am_modelos_por_marca, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var marcaCarro = el('marcaCarro');
    var modeloCarro = el('modeloCarro');
    var carroCompleto = el('carroCompleto');
    var registerForm = el('registerForm');

    function atualizarCarroCompleto() {
        if (!carroCompleto) return;
        var marca = marcaCarro ? marcaCarro.value : '';
        var modelo = modeloCarro ? modeloCarro.value : '';
        carroCompleto.value = (marca && modelo) ? (marca + ' ' + modelo) : '';
    }

    if (marcaCarro && modeloCarro) {
        marcaCarro.addEventListener('change', function() {
            var marca = this.value;
            modeloCarro.innerHTML = '';

            if (!marca || !modelosPorMarca[marca]) {
                modeloCarro.disabled = true;
                var defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Escolha primeiro a marca';
                modeloCarro.appendChild(defaultOption);
                atualizarCarroCompleto();
                return;
            }

            modeloCarro.disabled = false;
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Escolha o modelo';
            modeloCarro.appendChild(placeholder);

            modelosPorMarca[marca].forEach(function(modelo) {
                var option = document.createElement('option');
                option.value = modelo;
                option.textContent = modelo;
                modeloCarro.appendChild(option);
            });

            atualizarCarroCompleto();
        });

        modeloCarro.addEventListener('change', atualizarCarroCompleto);
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function() {
            atualizarCarroCompleto();
        });
    }

    // Loading state nos botões
    var forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = this.querySelector('.btn');
            if (btn) {
                btn.classList.add('loading');
            }
        });
    });
});
</script>

</body>
</html>