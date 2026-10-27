<?php
// gestao_funcionarios_chefe.php - Gestão de funcionários para o chefe
session_start();
require 'ligar.php';

// Verificar se é chefe
if (!isset($_SESSION['chefe_id']) || !isset($_SESSION['is_chefe'])) {
    header('Location: login_chefe.php');
    exit();
}

$chefe_id = $_SESSION['chefe_id'];
$chefe_nome = $_SESSION['chefe_nome'];

// Buscar mecanicos ativos
$sql_mecanicos = "SELECT id_mecanico, nome_mecanico, email, especialidade, ordenado_bruto, data_contratacao 
                  FROM mecanicos 
                  WHERE ativo = 1 
                  ORDER BY nome_mecanico";
$mecanicos_result = mysqli_query($con, $sql_mecanicos);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Funcionários - Chefe de Oficina</title>
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header p {
            color: var(--text-secondary);
        }
        
        .secao {
            margin-bottom: 40px;
        }
        
        .secao h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 107, 0, 0.3);
        }
        
        .tabela-container {
            background: var(--dark-light);
            border-radius: 15px;
            border: 2px solid rgba(255, 107, 0, 0.3);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: rgba(255, 107, 0, 0.1);
        }
        
        th {
            padding: 15px;
            text-align: left;
            color: var(--primary);
            font-weight: 700;
            border-bottom: 2px solid rgba(255, 107, 0, 0.3);
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        tbody tr:hover {
            background: rgba(255, 107, 0, 0.05);
        }
        
        .nome-funcionario {
            font-weight: 600;
            color: var(--text);
        }
        
        .email {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .salario {
            font-weight: 700;
            color: var(--primary);
            font-family: 'Courier New', monospace;
        }
        
        .acoes {
            display: flex;
            gap: 8px;
        }
        
        button {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-aumentar {
            background: var(--success);
            color: white;
        }
        
        .btn-aumentar:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-despedir {
            background: var(--danger);
            color: white;
        }
        
        .btn-despedir:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .vazio {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .vazio i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        /* MODALS */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.ativo {
            display: flex;
        }
        
        .modal-conteudo {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            border: 2px solid var(--primary);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .modal-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: var(--text);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 107, 0, 0.3);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.2);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .modal-botoes {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .btn-modal {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-confirmar {
            background: var(--danger);
            color: white;
        }
        
        .btn-confirmar:hover {
            background: #c82333;
        }
        
        .btn-cancelar {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text);
            border: 1px solid rgba(255, 107, 0, 0.3);
        }
        
        .btn-cancelar:hover {
            background: rgba(255, 107, 0, 0.2);
        }
        
        .mensagem {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .mensagem.sucesso {
            background: rgba(40, 167, 69, 0.2);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }
        
        .mensagem.erro {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            gap: 15px;
        }

        @media (max-width: 768px) {
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 10px;
            }
            
            .acoes {
                flex-direction: column;
            }
            
            button {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="indexchefedeoficina.php" class="back-btn">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        
        <div class="header">
            <h1><i class="bi bi-people"></i> Gestão de Funcionários</h1>
            <p>Bem-vindo, <?= htmlspecialchars($chefe_nome) ?></p>
        </div>
        
        <div id="mensagemContainer"></div>
        
        <!-- MECANICOS -->
        <div class="secao">
            <h2><i class="bi bi-wrench"></i> Mecânicos (<?= $mecanicos_result->num_rows ?>)</h2>
            
            <?php if ($mecanicos_result->num_rows > 0): ?>
                <div class="tabela-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Especialidade</th>
                                <th>Email</th>
                                <th>Salário Bruto</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($mecanico = $mecanicos_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong class="nome-funcionario"><?= htmlspecialchars($mecanico['nome_mecanico']) ?></strong><br><span class="email"><?= htmlspecialchars($mecanico['email']) ?></span></td>
                                <td><?= htmlspecialchars($mecanico['especialidade']) ?></td>
                                <td class="email"><?= htmlspecialchars($mecanico['email']) ?></td>
                                <td><span class="salario">€ <?= number_format($mecanico['ordenado_bruto'], 2, ',', '.') ?></span></td>
                                <td>
                                    <div class="acoes">
                                        <button class="btn-aumentar" onclick="abrirModalAumentoSalario('mecanico', <?= $mecanico['id_mecanico'] ?>, '<?= htmlspecialchars($mecanico['nome_mecanico']) ?>', <?= $mecanico['ordenado_bruto'] ?>)">
                                            <i class="bi bi-arrow-up"></i> Aumentar
                                        </button>
                                        <button class="btn-despedir" onclick="abrirModalDespedimento('mecanico', <?= $mecanico['id_mecanico'] ?>, '<?= htmlspecialchars($mecanico['nome_mecanico']) ?>')">
                                            <i class="bi bi-x-circle"></i> Despedir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tabela-container">
                    <div class="vazio">
                        <i class="bi bi-inbox"></i>
                        <p>Nenhum mecânico ativo</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL DE DESPEDIMENTO -->
    <div class="modal" id="modalDespedimento">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-exclamation-triangle"></i> Despedir Funcionário</h2>
                <p>Funcionário: <strong id="nomeFuncionarioDespedimento"></strong></p>
            </div>
            
            <div id="mensagemModalDespedimento"></div>
            
            <form id="formDespedimento" onsubmit="processarDespedimento(event)">
                <input type="hidden" id="despedimentoTipo" name="tipo">
                <input type="hidden" id="despedimentoId" name="funcionario_id">
                
                <div class="form-group">
                    <label for="motivo_despedimento">Motivo do Despedimento:</label>
                    <select id="motivo_despedimento" name="motivo" required>
                        <option value="">Selecione um motivo...</option>
                        <option value="Desempenho insuficiente e não cumprimento de objetivos">Desempenho insuficiente</option>
                        <option value="Violação de políticas internas da empresa">Violação de políticas internas</option>
                        <option value="Absentismo e falta de pontualidade reiterada">Absentismo reiterado</option>
                        <option value="Comportamento inadequado e desrespeito pela hierarquia">Comportamento inadequado</option>
                        <option value="Insubordinação e desobediência de ordens diretas">Insubordinação</option>
                        <option value="Redução de quadro por razões económicas">Redução de quadro</option>
                        <option value="Despedimento por justa causa - roubo ou fraude">Justa causa - roubo/fraude</option>
                        <option value="Encerramento da posição e restruturação interna">Restruturação interna</option>
                        <option value="Comportamento inadequado no local de trabalho">Inadequação profissional</option>
                        <option value="Outro motivo">Outro motivo</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="motivo_detalhes">Detalhes Adicionais (opcional):</label>
                    <textarea id="motivo_detalhes" name="motivo_detalhes" placeholder="Adicione informações relevantes..."></textarea>
                </div>
                
                <div style="background: rgba(220, 53, 69, 0.1); border-left: 4px solid var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong style="color: var(--danger);">[AVISO] Atenção:</strong>
                    <p style="margin-top: 8px; font-size: 0.9rem;">Esta ação é irreversível. A conta do funcionário será desativada e um email de notificação será enviado.</p>
                </div>
                
                <div class="modal-botoes">
                    <button type="submit" class="btn-modal btn-confirmar">
                        <i class="bi bi-check"></i> Confirmar Despedimento
                    </button>
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModalDespedimento()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MODAL DE AUMENTO DE SALÁRIO -->
    <div class="modal" id="modalAumentoSalario">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-arrow-up-circle"></i> Aumentar Salário</h2>
                <p>Funcionário: <strong id="nomeFuncionarioSalario"></strong></p>
            </div>
            
            <div id="mensagemModalSalario"></div>
            
            <form id="formAumentoSalario" onsubmit="processarAumentoSalario(event)">
                <input type="hidden" id="aumentoTipo" name="tipo">
                <input type="hidden" id="aumentoId" name="funcionario_id">
                
                <div class="form-group">
                    <label>Salário Atual:</label>
                    <input type="text" id="salarioAtual" readonly style="opacity: 0.7;">
                </div>
                
                <div class="form-group">
                    <label for="novoSalario">Novo Salário (€):</label>
                    <input type="number" id="novoSalario" name="novo_salario" step="0.01" min="0" placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">
                        <span id="percentagemAumento"></span>
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="motivo_aumento">Motivo do Aumento:</label>
                    <select id="motivo_aumento" name="motivo" required>
                        <option value="">Selecione um motivo...</option>
                        <option value="Desempenho excepcional e dedicação ao trabalho">Desempenho excepcional</option>
                        <option value="Promoção interna">Promoção interna</option>
                        <option value="Aumento de responsabilidades">Aumento de responsabilidades</option>
                        <option value="Reconhecimento de competências técnicas">Reconhecimento técnico</option>
                        <option value="Ajuste salarial por inflação">Ajuste por inflação</option>
                        <option value="Implementação de novo papel/função">Novo papel/função</option>
                        <option value="Retenção de talento">Retenção de talento</option>
                        <option value="Outro motivo">Outro motivo</option>
                    </select>
                </div>
                
                <div class="modal-botoes">
                    <button type="submit" class="btn-modal btn-confirmar" style="background: var(--success);">
                        <i class="bi bi-check"></i> Confirmar Aumento
                    </button>
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModalAumentoSalario()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let salarioAtualVal = 0;
        
        // DESPEDIMENTO
        function abrirModalDespedimento(tipo, id, nome) {
            document.getElementById('despedimentoTipo').value = tipo;
            document.getElementById('despedimentoId').value = id;
            document.getElementById('nomeFuncionarioDespedimento').textContent = nome;
            document.getElementById('modalDespedimento').classList.add('ativo');
        }
        
        function fecharModalDespedimento() {
            document.getElementById('modalDespedimento').classList.remove('ativo');
            document.getElementById('formDespedimento').reset();
            document.getElementById('mensagemModalDespedimento').innerHTML = '';
        }
        
        function processarDespedimento(event) {
            event.preventDefault();
            
            const tipo = document.getElementById('despedimentoTipo').value;
            const id = document.getElementById('despedimentoId').value;
            const motivoSelect = document.getElementById('motivo_despedimento').value;
            const detalhes = document.getElementById('motivo_detalhes').value;
            const motivo = detalhes ? motivoSelect + ': ' + detalhes : motivoSelect;
            
            fetch('api_despedir_funcionario.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tipo=${tipo}&funcionario_id=${id}&motivo=${encodeURIComponent(motivo)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    mostrarMensagem(data.mensagem, 'sucesso');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarMensagemModal(data.mensagem, 'erro', 'despedimento');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                mostrarMensagemModal('Erro ao processar despedimento', 'erro', 'despedimento');
            });
        }
        
        // AUMENTO DE SALÁRIO
        function abrirModalAumentoSalario(tipo, id, nome, salario) {
            document.getElementById('aumentoTipo').value = tipo;
            document.getElementById('aumentoId').value = id;
            document.getElementById('nomeFuncionarioSalario').textContent = nome;
            document.getElementById('salarioAtual').value = '€ ' + parseFloat(salario).toFixed(2);
            salarioAtualVal = salario;
            document.getElementById('novoSalario').value = '';
            document.getElementById('percentagemAumento').textContent = '';
            document.getElementById('modalAumentoSalario').classList.add('ativo');
        }
        
        function fecharModalAumentoSalario() {
            document.getElementById('modalAumentoSalario').classList.remove('ativo');
            document.getElementById('formAumentoSalario').reset();
            document.getElementById('mensagemModalSalario').innerHTML = '';
        }
        
        document.getElementById('novoSalario').addEventListener('input', function() {
            const novoSalario = parseFloat(this.value) || 0;
            if (novoSalario > salarioAtualVal) {
                const aumento = novoSalario - salarioAtualVal;
                const percentagem = ((aumento / salarioAtualVal) * 100).toFixed(1);
                document.getElementById('percentagemAumento').textContent = 
                    `Aumento: € ${aumento.toFixed(2)} (${percentagem}%)`;
            } else {
                document.getElementById('percentagemAumento').textContent = '';
            }
        });
        
        function processarAumentoSalario(event) {
            event.preventDefault();
            
            const tipo = document.getElementById('aumentoTipo').value;
            const id = document.getElementById('aumentoId').value;
            const novoSalario = document.getElementById('novoSalario').value;
            const motivo = document.getElementById('motivo_aumento').value;
            
            fetch('api_aumentar_salario.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tipo=${tipo}&funcionario_id=${id}&novo_salario=${novoSalario}&motivo=${encodeURIComponent(motivo)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    mostrarMensagem(
                        `Salário aumentado com sucesso! (${data.percentagem}% +€${data.aumento})`,
                        'sucesso'
                    );
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarMensagemModal(data.mensagem, 'erro', 'salario');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                mostrarMensagemModal('Erro ao processar aumento', 'erro', 'salario');
            });
        }
        
        function mostrarMensagem(texto, tipo) {
            const container = document.getElementById('mensagemContainer');
            const div = document.createElement('div');
            div.className = `mensagem ${tipo}`;
            div.innerHTML = `<i class="bi bi-${tipo === 'sucesso' ? 'check-circle' : 'exclamation-circle'}-fill"></i> ${texto}`;
            container.innerHTML = '';
            container.appendChild(div);
            
            if (tipo === 'sucesso') {
                setTimeout(() => div.remove(), 4000);
            }
        }
        
        function mostrarMensagemModal(texto, tipo, modal) {
            const container = modal === 'despedimento' 
                ? document.getElementById('mensagemModalDespedimento')
                : document.getElementById('mensagemModalSalario');
            
            const div = document.createElement('div');
            div.className = `mensagem ${tipo}`;
            div.innerHTML = `<i class="bi bi-${tipo === 'sucesso' ? 'check-circle' : 'exclamation-circle'}-fill"></i> ${texto}`;
            container.innerHTML = '';
            container.appendChild(div);
        }
    </script>
</body>
</html>
