<?php
// api_despedir_funcionario.php - Sistema de Despedimento
header('Content-Type: application/json');
session_start();
require 'ligar.php';

// Verificar se é chefe
if (!isset($_SESSION['chefe_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit();
}

$chefe_id = $_SESSION['chefe_id'];
$funcionario_id = $_POST['funcionario_id'] ?? '';
$tipo = $_POST['tipo'] ?? ''; // mecanico
$motivo = $_POST['motivo'] ?? '';
$data_hoje = date('Y-m-d');

// Validações
if (!$funcionario_id || !$tipo || !$motivo) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos']);
    exit();
}

if ($tipo === 'mecanico') {
    // Buscar mecânico
    $sql = "SELECT id_mecanico, nome, email, ativo FROM mecanicos WHERE id_mecanico = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $funcionario_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $nome, $email, $ativo);
    $existe = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$existe) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Mecânico não encontrado']);
        exit();
    }
    
    if ($ativo == 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Este mecânico já foi despedido']);
        exit();
    }
    
    // Desativar mecânico
    $sql_update = "UPDATE mecanicos SET ativo = 0, data_despedimento = ?, motivo_despedimento = ? WHERE id_mecanico = ?";
    $stmt = mysqli_prepare($con, $sql_update);
    mysqli_stmt_bind_param($stmt, "ssi", $data_hoje, $motivo, $funcionario_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Enviar email
        enviarEmailDespedimento($email, $nome, $motivo);
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Mecânico despedido com sucesso'
        ]);
    } else {
        mysqli_stmt_close($stmt);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao despedir']);
    }

} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo inválido. Apenas mecânicos podem ser geridos.']);
}

function enviarEmailDespedimento($email, $nome, $motivo) {
    $assunto = "Comunicado de Rescisão de Contrato - AUTOMANAGER";
    
    $data_atual = date('d/m/Y');
    $data_hora = date('d/m/Y H:i:s');
    
    $mensagem = "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #ff6b00; margin: 0;'>AUTOMANAGER</h1>
                <p style='color: #666; margin: 5px 0 0 0;'>Oficina Automóvel</p>
            </div>
            
            <div style='border-bottom: 2px solid #ff6b00; padding-bottom: 20px; margin-bottom: 30px;'>
                <h2 style='color: #333; margin: 0; font-size: 1.3rem;'>Comunicado Importante</h2>
            </div>
            
            <p style='color: #333; font-size: 1.1rem; margin-bottom: 20px;'>
                Prezado/a <strong>$nome</strong>,
            </p>
            
            <div style='background: #f9f9f9; border-left: 4px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 5px;'>
                <p style='color: #333; margin: 0 0 15px 0;'>
                    Comunicamos que o seu contrato de trabalho com a <strong>AUTOMANAGER - Oficina Automóvel</strong> foi rescindido com efeitos a partir de <strong>$data_atual</strong>.
                </p>
                
                <h4 style='color: #333; margin-top: 15px; margin-bottom: 10px;'>Motivo da Rescisão:</h4>
                <p style='color: #555; background: white; padding: 15px; border-radius: 5px; margin: 0;'>
                    $motivo
                </p>
            </div>
            
            <h4 style='color: #333; margin-top: 30px; margin-bottom: 10px;'>Informações Importantes:</h4>
            <ul style='color: #555; line-height: 1.8;'>
                <li>Sua conta de acesso será desativada imediatamente</li>
                <li>Todos os dados de desempenho profissional foram registados</li>
                <li>Processamento salarial será realizado conforme legislação vigente</li>
                <li>Documentação final será enviada em breve por correio registado</li>
            </ul>
            
            <div style='background: #fffbf0; border: 1px solid #ff6b00; padding: 20px; margin: 30px 0; border-radius: 5px;'>
                <h4 style='color: #ff6b00; margin-top: 0;'>Próximos Passos:</h4>
                <p style='color: #333; margin: 0;'>
                    Dirija-se ao departamento de Recursos Humanos para proceder aos procedimentos administrativos finais. 
                    Encontra-se disponível para esclarecimentos pelo email <strong>rh@AUTOMANAGER.pt</strong>.
                </p>
            </div>
            
            <p style='color: #666; font-size: 0.9rem; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px; text-align: center;'>
                Este é um comunicado oficial da AUTOMANAGER - Oficina Automóvel.<br>
                <strong>Data:</strong> $data_hora
            </p>
        </div>
    </body>
    </html>
    ";
    
    // Enviar email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: rh@AUTOMANAGER.pt\r\n";
    $headers .= "Reply-To: rh@AUTOMANAGER.pt\r\n";
    
    @mail($email, $assunto, $mensagem, $headers);
}
?>
