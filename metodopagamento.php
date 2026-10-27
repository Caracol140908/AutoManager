<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_validacao_cartao.php';

// Verificar se o token e serviço_id estão presentes
if (!isset($_GET['token']) || !isset($_GET['servico_id'])) {
    die("Link de pagamento inválido.");
}

$token = $_GET['token'];
$servico_id = (int)$_GET['servico_id'];

// Verificar se o token é válido na base de dados
$stmt = $con->prepare("SELECT s.* FROM servico s WHERE s.id = ? AND s.token_pagamento = ?");
if (!$stmt) {
    die("Erro na preparação da query: " . $con->error);
}
$stmt->bind_param("is", $servico_id, $token);
$stmt->execute();
$result = $stmt->get_result();
$servico = $result->fetch_assoc();

if (!$servico) {
    die("Link de pagamento inválido ou expirado.");
}

// Processar pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo_pagamento = $_POST['metodo_pagamento'] ?? '';
    $numero_cartao = $_POST['numero_cartao'] ?? '';
    $validade = $_POST['validade'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $nome_titular = $_POST['nome_titular'] ?? '';
    $telemovel_mbway = $_POST['telemovel_mbway'] ?? '';
    
    if ($metodo_pagamento === 'mbway') {
        // Validar MB Way
        if (!$telemovel_mbway) {
            $erro_pagamento = "Por favor, insira o seu número de telemóvel para MB Way.";
        } elseif (!preg_match('/^9[1236][0-9]{7}$/', $telemovel_mbway)) {
            $erro_pagamento = "Por favor, insira um número de telemóvel português válido.";
        } else {
            // Gerar número de fatura
            $numero_fatura = 'FAT-' . date('Y') . '-' . str_pad($servico_id, 6, '0', STR_PAD_LEFT);
            
            // Atualizar serviço com estado de pagamento e número da fatura
            $stmt_update = $con->prepare("UPDATE servico SET estado_pagamento = 'Pago', data_pagamento = NOW(), numero_fatura = ? WHERE id = ?");
            if (!$stmt_update) {
                die("Erro na preparação da query de update: " . $con->error);
            }
            $stmt_update->bind_param("si", $numero_fatura, $servico_id);
            if (!$stmt_update->execute()) {
                die("Erro ao executar update: " . $stmt_update->error);
            }
            $stmt_update->close();
            
            // Enviar fatura por email
            enviarFatura($servico, $metodo_pagamento, $telemovel_mbway, $numero_fatura);
            
            $pagamento_sucesso = true;
            $_SESSION['numero_fatura'] = $numero_fatura;
            $_SESSION['servico_id'] = $servico_id;
        }
    } else {
        $erroCartao = am_cartao_validar_dados([
            'numero_cartao' => $numero_cartao,
            'validade' => $validade,
            'cvv' => $cvv,
            'nome_titular' => $nome_titular,
        ]);
        if ($erroCartao !== null) {
            $erro_pagamento = $erroCartao;
        } else {
            // Gerar número de fatura
            $numero_fatura = 'FAT-' . date('Y') . '-' . str_pad($servico_id, 6, '0', STR_PAD_LEFT);
            
            // Atualizar serviço com estado de pagamento e número da fatura
            $stmt_update = $con->prepare("UPDATE servico SET estado_pagamento = 'Pago', data_pagamento = NOW(), numero_fatura = ? WHERE id = ?");
            if (!$stmt_update) {
                die("Erro na preparação da query de update: " . $con->error);
            }
            $stmt_update->bind_param("si", $numero_fatura, $servico_id);
            if (!$stmt_update->execute()) {
                die("Erro ao executar update: " . $stmt_update->error);
            }
            $stmt_update->close();
            
            // Enviar fatura por email
            enviarFatura($servico, $metodo_pagamento, '', $numero_fatura);
            
            $pagamento_sucesso = true;
            $_SESSION['numero_fatura'] = $numero_fatura;
            $_SESSION['servico_id'] = $servico_id;
        }
    }
}

function enviarFatura($servico, $metodo_pagamento, $telemovel_mbway = '', $numero_fatura) {
    $data_emissao = date('d/m/Y');
    $data_vencimento = date('d/m/Y', strtotime('+30 days'));
    $subtotal = $servico['custo'];
    $iva = $subtotal * 0.23;
    $total = $subtotal + $iva;
    
    $assunto = "Fatura {$numero_fatura} - AUTOMANAGER";
    
    // Informação adicional para MB Way
    $info_pagamento_extra = '';
    if ($metodo_pagamento === 'mbway' && $telemovel_mbway) {
        $info_pagamento_extra = "<p><strong>Telemóvel MB Way:</strong> $telemovel_mbway</p>";
    }
    
    $mensagem = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .fatura-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #ff6b00; }
            .logo { height: 50px; }
            .fatura-info { text-align: right; }
            .detalhes { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
            .cliente, .empresa { background: #f8f9fa; padding: 20px; border-radius: 8px; }
            .tabela-servicos { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .tabela-servicos th, .tabela-servicos td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            .tabela-servicos th { background: #ff6b00; color: white; }
            .total { text-align: right; font-size: 1.2em; font-weight: bold; margin-top: 20px; }
            .pagamento-info { background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 0.9em; }
            .destaque { color: #ff6b00; font-weight: bold; }
            .download-link { text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='fatura-container'>
            <div class='header'>
                <div>
                    <h1 style='color: #ff6b00; margin: 0;'>AUTOMANAGER</h1>
                    <p style='margin: 5px 0 0 0; color: #666;'>Oficina Automóvel</p>
                </div>
                <div class='fatura-info'>
                    <h2 style='margin: 0; color: #333;'>Fatura {$numero_fatura}</h2>
                    <p style='margin: 5px 0 0 0; color: #666;'>Data: {$data_emissao}</p>
                </div>
            </div>
            
            <div class='detalhes'>
                <div class='empresa'>
                    <h3>Emitente</h3>
                    <p><strong>AUTOMANAGER, Lda</strong></p>
                    <p>Avenida Doutor Teófilo Carvalho dos Santos</p>
                    <p>2580-318 Alenquer</p>
                    <p>NIF: 123 456 789</p>
                    <p>Telefone: 912 964 022</p>
                    <p>Email: automanagergeral@gmail.com</p>
                </div>
                
                <div class='cliente'>
                    <h3>Cliente</h3>
                    <p><strong>{$servico['nome']}</strong></p>
                    <p>Email: {$servico['email']}</p>
                    <p>Telefone: {$servico['telefone']}</p>
                    <p>Matrícula: {$servico['matricula']}</p>
                </div>
            </div>
            
            <h3>Descrição dos Serviços</h3>
            <table class='tabela-servicos'>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Data do Serviço</th>
                        <th>Quantidade</th>
                        <th>Preço Unitário</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{$servico['tipo_servico']}</td>
                        <td>" . ($servico['data_desejada'] ? date('d/m/Y', strtotime($servico['data_desejada'])) : 'A confirmar') . "</td>
                        <td>1</td>
                        <td>€{$subtotal}</td>
                        <td>€{$subtotal}</td>
                    </tr>
                </tbody>
            </table>
            
            <div class='total'>
                <p>Subtotal: €" . number_format($subtotal, 2) . "</p>
                <p>IVA (23%): €" . number_format($iva, 2) . "</p>
                <p style='font-size: 1.4em; color: #ff6b00;'>Total: €" . number_format($total, 2) . "</p>
            </div>
            
            <div class='pagamento-info'>
                <h4>Informação de Pagamento</h4>
                <p><strong>Método de Pagamento:</strong> " . ucfirst(str_replace('_', ' ', $metodo_pagamento)) . "</p>
                {$info_pagamento_extra}
                <p><strong>Data de Pagamento:</strong> " . date('d/m/Y') . "</p>
                <p><strong>Data de Vencimento:</strong> {$data_vencimento}</p>
                <p><strong>Estado:</strong> <span style='color: #28a745;'>PAGO</span></p>
            </div>

            <div class='download-link'>
                <p><strong> Pode fazer download da fatura em PDF através do nosso website.</strong></p>
            </div>
            
            <div class='footer'>
                <p><strong>AUTOMANAGER - Oficina Automóvel</strong></p>
                <p>Avenida Doutor Teófilo Carvalho dos Santos, Alenquer | 912 964 022 | automanagergeral@gmail.com</p>
                <p>Obrigado pela sua preferência! </p>
            </div>
        </div>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: AUTOMANAGER Faturas <faturas@AUTOMANAGER.pt>\r\n";
    $headers .= "Bcc: faturas@AUTOMANAGER.pt\r\n"; // Cópia para arquivo
    
    @mail($servico['email'], $assunto, $mensagem, $headers);
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento — AUTOMANAGER</title>
    <?php require __DIR__ . '/inc_am_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="metodopagamento.css">
</head>
<body>
    <div class="pagamento-container">
        <div class="pagamento-header">
            <img src="automanagerlogowhite.png" alt="AUTOMANAGER Logo" class="logo">
            <h1>Finalizar Pagamento</h1>
        </div>

        <div class="pagamento-content">
            <div class="resumo-servico">
                <h2><i class="fa-solid fa-bars" aria-hidden="true"></i> Resumo do Serviço</h2>
                <div class="detalhes-servico">
                    <div class="detalhe-item">
                        <span>Cliente:</span>
                        <strong><?php echo htmlspecialchars($servico['nome']); ?></strong>
                    </div>
                    <div class="detalhe-item">
                        <span>Serviço:</span>
                        <strong><?php echo htmlspecialchars($servico['tipo_servico']); ?></strong>
                    </div>
                    <div class="detalhe-item">
                        <span>Matrícula:</span>
                        <strong><?php echo htmlspecialchars($servico['matricula']); ?></strong>
                    </div>
                    <div class="detalhe-item">
                        <span>Data desejada:</span>
                        <strong><?php echo $servico['data_desejada'] ? date('d/m/Y', strtotime($servico['data_desejada'])) : 'A confirmar'; ?></strong>
                    </div>
                    <div class="detalhe-item total">
                        <span>Total a pagar:</span>
                        <strong class="valor-total">€<?php echo $servico['custo']; ?></strong>
                    </div>
                </div>
            </div>

            <?php if (isset($pagamento_sucesso)): ?>
                <div class="sucesso-pagamento">
                    <div class="icone-sucesso"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <h2>Pagamento Efetuado com Sucesso!</h2>
                    <p>O seu pagamento foi processado com sucesso. A sua marcação está agora confirmada.</p>
                    <p><strong>Uma fatura foi enviada para o seu email.</strong></p>
                    <p>Verifique a sua caixa de entrada e pasta de spam.</p>
                    
                    <div class="download-fatura">
                        <h3>[INSERIR] Download da Fatura</h3>
                        <p>Pode fazer download da sua fatura em formato PDF:</p>
                        <a href="download_fatura.php?servico_id=<?php echo $servico_id; ?>&token=<?php echo $token; ?>" class="btn-download-pdf">
                             Descarregar Fatura PDF
                        </a>
                    </div>

                    <div class="acoes-pos-pagamento">
                        <a href="index.php" class="btn-voltar">Voltar à Página Principal</a>
                        <button onclick="window.print()" class="btn-imprimir"> Imprimir Comprovativo</button>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" class="form-pagamento" id="form-pagamento">
                    <div class="metodo-pagamento">
                        <h3> Método de Pagamento</h3>
                        
                        <?php if (isset($erro_pagamento)): ?>
                            <div class="erro-pagamento"><?php echo $erro_pagamento; ?></div>
                        <?php endif; ?>

                        <div class="opcoes-pagamento">
                            <label class="opcao-pagamento">
                                <input type="radio" name="metodo_pagamento" value="cartao_credito" checked>
                                <span>Cartão de Crédito</span>
                            </label>
                            <label class="opcao-pagamento">
                                <input type="radio" name="metodo_pagamento" value="cartao_debito">
                                <span>Cartão de Débito</span>
                            </label>
                            <label class="opcao-pagamento">
                                <input type="radio" name="metodo_pagamento" value="mbway">
                                <span>MB Way</span>
                            </label>
                        </div>

                        <!-- Campos para Cartão -->
                        <div class="dados-cartao" id="dados-cartao">
                            <div class="input-group">
                                <label>Número do Cartão</label>
                                <input type="text" name="numero_cartao" placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric" autocomplete="cc-number">
                            </div>
                            
                            <div class="input-group-duplo">
                                <div class="input-group">
                                    <label>Validade</label>
                                    <input type="text" name="validade" placeholder="MM/AA" maxlength="5" inputmode="numeric" autocomplete="cc-exp">
                                </div>
                                <div class="input-group">
                                    <label>CVV</label>
                                    <input type="text" name="cvv" placeholder="123" maxlength="4" inputmode="numeric" autocomplete="cc-csc">
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <label>Nome do Titular</label>
                                <input type="text" name="nome_titular" placeholder="Como está no cartão">
                            </div>
                        </div>

                        <!-- Campo para MB Way -->
                        <div class="dados-mbway" id="dados-mbway" style="display: none;">
                            <div class="input-group">
                                <label>[DISPOSITIVO] Número de Telemóvel</label>
                                <input type="tel" name="telemovel_mbway" placeholder="912 345 678" maxlength="9">
                                <small style="color: #888; font-size: 0.8rem;">Receberá um pedido de confirmação no seu telemóvel</small>
                            </div>
                        </div>
                    </div>

                    <div class="acoes-pagamento">
                        <button type="submit" class="btn-pagar">
                             Efetuar Pagamento de €<?php echo $servico['custo']; ?>
                        </button>
                        <a href="index.php" class="btn-cancelar">Cancelar</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="pagamento-footer">
            <p>Pagamento seguro • Dados encriptados • Suporte 24/7</p>
        </div>
    </div>

    <script src="pagamento_cartao.js"></script>
    <script>
        // Alternar entre métodos de pagamento
        document.querySelectorAll('input[name="metodo_pagamento"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const metodo = this.value;
                const dadosCartao = document.getElementById('dados-cartao');
                const dadosMbway = document.getElementById('dados-mbway');
                
                if (metodo === 'mbway') {
                    dadosCartao.style.display = 'none';
                    dadosMbway.style.display = 'block';
                    document.querySelectorAll('#dados-cartao input').forEach(input => {
                        input.removeAttribute('required');
                    });
                    document.querySelector('input[name="telemovel_mbway"]').setAttribute('required', 'required');
                } else {
                    dadosCartao.style.display = 'block';
                    dadosMbway.style.display = 'none';
                    document.querySelectorAll('#dados-cartao input').forEach(input => {
                        input.setAttribute('required', 'required');
                    });
                    document.querySelector('input[name="telemovel_mbway"]').removeAttribute('required');
                }
            });
        });

        if (window.AMValidacaoCartao) {
            AMValidacaoCartao.bindCartaoFields(document.getElementById('form-pagamento'));
        }

        document.querySelector('input[name="telemovel_mbway"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/gi, '');
        });

        document.getElementById('form-pagamento').addEventListener('submit', function(e) {
            const metodo = document.querySelector('input[name="metodo_pagamento"]:checked').value;
            
            if (metodo === 'mbway') {
                const telemovel = document.querySelector('input[name="telemovel_mbway"]').value;
                const regexTelefone = /^9[1236][0-9]{7}$/;
                
                if (!regexTelefone.test(telemovel)) {
                    e.preventDefault();
                    alert('Por favor, insira um número de telemóvel português válido (9XXXXXXXX).');
                    return false;
                }
                return true;
            }

            if (window.AMValidacaoCartao) {
                const err = AMValidacaoCartao.validateCartao({
                    numero_cartao: document.querySelector('input[name="numero_cartao"]').value,
                    validade: document.querySelector('input[name="validade"]').value,
                    cvv: document.querySelector('input[name="cvv"]').value,
                    nome_titular: document.querySelector('input[name="nome_titular"]').value
                });
                if (err) {
                    e.preventDefault();
                    alert(err);
                    return false;
                }
            }
        });
    </script>
</body>
</html>