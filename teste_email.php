<?php
// Teste de envio de email

require 'ligar.php';
require_once 'mailer.php';

// Simular dados
$dados = [
    'nome' => 'Teste Usuario',
    'email' => 'teste@AUTOMANAGER.pt',
    'salario' => 1500,
    'data_contratacao' => date('Y-m-d'),
    'enviar_email' => true
];

$password = bin2hex(random_bytes(8));
$tipo = 'mecanico';
$tipo_pt = 'Mecânico';

// Preparar email
$assunto = "Bem-vindo à AUTOMANAGER - Credenciais de Acesso";

$salario_info = '';
if (!empty($dados['salario'])) {
    $salario_formatado = number_format($dados['salario'], 2, ',', '.');
    $salario_info = "<li><strong>Salário Bruto:</strong> €$salario_formatado/mês</li>";
}

$data_contratacao = $dados['data_contratacao'] ?? date('Y-m-d');

$mensagem_email = "
<html>
<body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
    <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h1 style='color: #ff6b00; margin: 0;'>AUTOMANAGER</h1>
            <p style='color: #666; margin: 5px 0 0 0;'>Oficina Automóvel</p>
        </div>
        
        <h2 style='color: #333;'>Olá, " . $dados['nome'] . "!</h2>
        <p style='color: #666; line-height: 1.6;'>Bem-vindo(a) à equipa da <strong style='color: #ff6b00;'>AUTOMANAGER</strong> como <strong>$tipo_pt</strong>.</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff6b00;'>
            <h3 style='color: #333; margin-top: 0;'>[SEGURANCA] Credenciais de Acesso</h3>
            <ul style='color: #666; line-height: 1.8; list-style: none; padding: 0;'>
                <li><strong>Email:</strong> <code style='background: #e8e8e8; padding: 5px 10px; border-radius: 3px;'>" . $dados['email'] . "</code></li>
                <li><strong>Password:</strong> <code style='background: #e8e8e8; padding: 5px 10px; border-radius: 3px;'>$password</code></li>
                <li><strong>URL de Acesso:</strong> <a href='http://" . $_SERVER['HTTP_HOST'] . "/login.php' style='color: #ff6b00;'>http://" . $_SERVER['HTTP_HOST'] . "/login.php</a></li>
            </ul>
        </div>

        <div style='background: #f3f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #333; margin-top: 0;'>[LISTA] Informações do Contrato</h3>
            <ul style='color: #666; line-height: 1.8; list-style: none; padding: 0;'>
                <li><strong>Função:</strong> " . htmlspecialchars($tipo_pt) . "</li>
                <li><strong>Data de Contratação:</strong> " . date('d/m/Y', strtotime($data_contratacao)) . "</li>
                " . $salario_info . "
            </ul>
        </div>

        <div style='background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #333; margin-top: 0;'>[AVISO] Importante</h3>
            <p style='color: #666; margin-bottom: 15px;'>
                <strong>Esta é a tua password temporária.</strong> No primeiro login serás solicitado(a) a alterar a tua password.
            </p>
        </div>

        <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
            <p style='color: #999; font-size: 12px;'>Bem-vindo(a) à equipa <strong>AUTOMANAGER</strong> </p>
        </div>
    </div>
</body>
</html>";

$resultado = enviarEmailAutoManager([
    'to' => $dados['email'],
    'to_name' => $dados['nome'],
    'subject' => $assunto,
    'html' => $mensagem_email,
    'text' => "Bem-vindo à equipa AutoManager. As suas credenciais foram criadas.",
]);

echo "<h1>Teste de Envio de Email</h1>";
echo "<p>Email para: " . $dados['email'] . "</p>";
echo "<p>Assunto: " . $assunto . "</p>";
echo "<p>Password: " . $password . "</p>";

if (!empty($resultado['ok'])) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "<strong>[OK] Email enviado com sucesso!</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<strong>[ERRO] Falha ao enviar email.</strong>";
    echo "<p>Erro: " . htmlspecialchars($resultado['error'] ?? 'Falha desconhecida', ENT_QUOTES, 'UTF-8') . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>HTML do Email:</h2>";
echo "<pre>" . htmlspecialchars($mensagem_email) . "</pre>";
?>
