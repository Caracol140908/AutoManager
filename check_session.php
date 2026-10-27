<?php
// check_session.php - Versão simplificada
require_once('ligar.php');

// Verificar se o utilizador já está autenticado
if (isset($_SESSION['user']) || isset($_SESSION['user_id']) || isset($_SESSION['mecanico_id']) ||
    isset($_SESSION['chefe_id'])) {
    return;
}

if (!$con instanceof mysqli) {
    return;
}

// Garantir que a tabela existe (instalação / BD nova)
$con->query("CREATE TABLE IF NOT EXISTS sessoes_temporarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    primeiro_acesso DATETIME NOT NULL,
    ultimo_acesso DATETIME NOT NULL,
    tempo_restante INT NOT NULL DEFAULT 120,
    visitas INT NOT NULL DEFAULT 1,
    pagina_atual VARCHAR(500) NULL,
    expirou TINYINT(1) NOT NULL DEFAULT 0,
    utilizador_id INT NULL,
    INDEX idx_ip_expirou (ip_address, expirou),
    INDEX idx_utilizador (utilizador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Obter IP
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$pagina_atual = $_SERVER['REQUEST_URI'] ?? '';

$tempo_limite = 120; // 2 minutos

// Verificar se já existe sessão
$stmt = $con->prepare("SELECT * FROM sessoes_temporarias WHERE ip_address = ? AND expirou = 0");
if (!$stmt) {
    error_log('check_session prepare failed: ' . $con->error);
    return;
}
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$result = $stmt->get_result();
$sessao = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$sessao) {
    // Criar nova sessão
    $agora = date('Y-m-d H:i:s');
    $visitas = 1;
    $expirou = 0;
    
    $insert = $con->prepare("
        INSERT INTO sessoes_temporarias 
        (ip_address, user_agent, primeiro_acesso, ultimo_acesso, tempo_restante, visitas, pagina_atual, expirou) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$insert) {
        error_log('check_session insert prepare failed: ' . $con->error);
        return;
    }

    $insert->bind_param("ssssiisi", $ip_address, $user_agent, $agora, $agora, $tempo_limite, $visitas, $pagina_atual, $expirou);
    $insert->execute();
    $sessao_id = $con->insert_id;
    $insert->close();
    
    $_SESSION['temp_ip'] = $ip_address;
    $_SESSION['temp_allowed'] = true;
    $_SESSION['sessao_id'] = $sessao_id;
    $_SESSION['tempo_restante'] = $tempo_limite;
    $_SESSION['temp_expired'] = false;
} else {
    if (!isset($_SESSION['tempo_restante'])) {
        $_SESSION['tempo_restante'] = isset($sessao['tempo_restante']) && $sessao['tempo_restante'] > 0
            ? (int) $sessao['tempo_restante']
            : $tempo_limite;
    }
}

// Guardar tempo limite
$_SESSION['tempo_limite'] = $tempo_limite;
?>