<?php
// bate_ponto.php - Interface de Bate Ponto para Mecânicos
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_admin_notificacoes.php';

// Verificar se é mecânico
if (!isset($_SESSION['mecanico_id'])) {
    header('Location: login.php');
    exit();
}

$mecanico_id = $_SESSION['mecanico_id'];
$mecanico_nome = $_SESSION['mecanico_nome'] ?? 'Mecânico';

// Buscar status de hoje
$data_hoje = date('Y-m-d');
$reg = am_presenca_get($con, (int) $mecanico_id, $data_hoje);
$hora_entrada = $reg['hora_entrada'] ?? null;
$hora_saida = $reg['hora_saida'] ?? null;
$total_horas = $reg['total_horas'] ?? null;
$tem_registro = ($reg !== null && !empty($reg['hora_entrada']));
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bate Ponto - AUTOMANAGER</title>
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
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .card {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 30px;
            border: 2px solid var(--primary);
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.3);
            margin-bottom: 20px;
        }
        
        .hora-display {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--primary);
            text-align: center;
            margin: 30px 0;
            font-family: 'Courier New', monospace;
        }
        
        .botoes {
            display: flex;
            gap: 15px;
            margin: 30px 0;
        }
        
        button {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-entrada {
            background: var(--success);
            color: white;
        }
        
        .btn-entrada:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-saida {
            background: var(--danger);
            color: white;
        }
        
        .btn-saida:hover:not(:disabled) {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .status-box {
            background: rgba(255, 107, 0, 0.1);
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .status-box.ativo {
            background: rgba(40, 167, 69, 0.1);
            border-left-color: var(--success);
        }
        
        .status-box.finalizado {
            background: rgba(100, 100, 100, 0.1);
            border-left-color: var(--text-secondary);
        }
        
        .status-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            text-align: center;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-valor {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
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
        
        .historico {
            margin-top: 30px;
        }
        
        .historico h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .historico-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255, 107, 0, 0.1);
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .historico-data {
            font-weight: 700;
            color: var(--text);
        }
        
        .historico-horas {
            font-family: 'Courier New', monospace;
            color: var(--primary);
            font-weight: 700;
        }
        
        .historico-vazio {
            text-align: center;
            color: var(--text-secondary);
            padding: 30px;
        }
        
        .navegacao {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-voltar {
            background: rgba(255, 107, 0, 0.2);
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 12px 20px;
            flex: 1;
        }
        
        .btn-voltar:hover {
            background: rgba(255, 107, 0, 0.3);
        }
        
        .relógio {
            display: inline-block;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
            font-size: 2.5rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            background: rgba(255, 107, 0, 0.1);
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .hora-display {
                font-size: 2.5rem;
            }
            
            .botoes {
                flex-direction: column;
            }
            
            button {
                padding: 12px 15px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="bi bi-clock-history"></i> Bate Ponto</h1>
            <p>Bem-vindo, <?= htmlspecialchars($mecanico_nome) ?></p>
        </div>
        
        <div class="card">
            <div id="mensagemContainer"></div>
            
            <div class="relógio" id="relogio">00:00</div>
            
            <?php if ($tem_registro && $hora_saida === null): ?>
                <!-- Já tem entrada hoje -->
                <div class="status-box ativo">
                    <div class="status-info">
                        <div class="info-item">
                            <div class="info-label">Entrada</div>
                            <div class="info-valor"><?= substr($hora_entrada, 0, 5) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-valor">[OK] Presente</div>
                        </div>
                    </div>
                </div>
                
                <div class="botoes">
                    <button class="btn-saida" onclick="registarSaida()">
                        <i class="bi bi-box-arrow-right"></i> Registar Saída
                    </button>
                </div>
            <?php elseif ($tem_registro && $hora_saida !== null): ?>
                <!-- Dia completo -->
                <div class="status-box finalizado">
                    <div class="status-info">
                        <div class="info-item">
                            <div class="info-label">Entrada</div>
                            <div class="info-valor"><?= substr($hora_entrada, 0, 5) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Saída</div>
                            <div class="info-valor"><?= substr($hora_saida, 0, 5) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Horas</div>
                            <div class="info-valor"><?= round($total_horas, 1) ?>h</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-valor">[OK] Concluído</div>
                        </div>
                    </div>
                </div>
                
                <p style="text-align: center; color: var(--text-secondary); margin: 20px 0;">
                    <i class="bi bi-check-circle"></i> Seu dia de trabalho foi registado com sucesso.
                </p>
            <?php else: ?>
                <!-- Sem entrada -->
                <p style="text-align: center; color: var(--text-secondary); margin-bottom: 30px;">
                    <i class="bi bi-info-circle"></i> Nenhum registo para hoje
                </p>
                
                <div class="botoes">
                    <button class="btn-entrada" onclick="registarEntrada()">
                        <i class="bi bi-box-arrow-in-left"></i> Registar Entrada
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card historico">
            <h3><i class="bi bi-calendar-event"></i> Histórico (Últimos 30 Dias)</h3>
            <div id="historicoContainer">
                <div class="historico-vazio">
                    <i class="bi bi-hourglass-split" style="font-size: 2rem;"></i>
                    <p>A carregar histórico...</p>
                </div>
            </div>
        </div>
        
        <div class="navegacao">
            <button class="btn-voltar" onclick="window.history.back()">
                <i class="bi bi-arrow-left"></i> Voltar
            </button>
        </div>
    </div>
    
    <script>
        // Atualizar relógio a cada segundo
        function atualizarRelogio() {
            const agora = new Date();
            const horas = String(agora.getHours()).padStart(2, '0');
            const minutos = String(agora.getMinutes()).padStart(2, '0');
            const segundos = String(agora.getSeconds()).padStart(2, '0');
            
            document.getElementById('relogio').textContent = horas + ':' + minutos + ':' + segundos;
        }
        
        atualizarRelogio();
        setInterval(atualizarRelogio, 1000);
        
        // Registar entrada
        function registarEntrada() {
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Processando...';
            
            fetch('api_bate_ponto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'acao=entrada'
            })
            .then(response => response.json())
            .then(data => {
                mostrarMensagem(data.mensagem, data.sucesso ? 'sucesso' : 'erro');
                
                if (data.sucesso) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-box-arrow-in-left"></i> Registar Entrada';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                mostrarMensagem('Erro ao registar entrada', 'erro');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-box-arrow-in-left"></i> Registar Entrada';
            });
        }
        
        // Registar saída
        function registarSaida() {
            if (!confirm('Tem a certeza que deseja registar a saída?')) {
                return;
            }
            
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Processando...';
            
            fetch('api_bate_ponto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'acao=saida'
            })
            .then(response => response.json())
            .then(data => {
                mostrarMensagem(data.mensagem, data.sucesso ? 'sucesso' : 'erro');
                
                if (data.sucesso) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-box-arrow-right"></i> Registar Saída';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                mostrarMensagem('Erro ao registar saída', 'erro');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-box-arrow-right"></i> Registar Saída';
            });
        }
        
        // Mostrar mensagem
        function mostrarMensagem(texto, tipo) {
            const container = document.getElementById('mensagemContainer');
            const div = document.createElement('div');
            div.className = `mensagem ${tipo}`;
            div.innerHTML = `<i class="bi bi-${tipo === 'sucesso' ? 'check-circle' : 'exclamation-circle'}-fill"></i> ${texto}`;
            container.innerHTML = '';
            container.appendChild(div);
            
            if (tipo === 'sucesso') {
                setTimeout(() => {
                    div.remove();
                }, 3000);
            }
        }
        
        // Carregar histórico
        function carregarHistorico() {
            fetch('api_bate_ponto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'acao=historico'
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('historicoContainer');
                
                if (data.sucesso && data.registos.length > 0) {
                    container.innerHTML = '';
                    data.registos.forEach(registo => {
                        const div = document.createElement('div');
                        div.className = 'historico-item';
                        
                        const horasTexto = registo.total_horas ? `${registo.total_horas}h` : 'Sem saída';
                        const statusTexto = registo.falta ? '[AVISO] Falta' : (registo.total_horas ? '[OK] Presente' : '...');
                        
                        div.innerHTML = `
                            <div>
                                <div class="historico-data">${new Date(registo.data + 'T00:00:00').toLocaleDateString('pt-PT', {weekday: 'long', day: 'numeric', month: 'long'})}</div>
                                <div class="historico-horas">${registo.hora_entrada || '--:--'} - ${registo.hora_saida || '--:--'}</div>
                            </div>
                            <div style="text-align: right;">
                                <div class="historico-horas">${horasTexto}</div>
                                <div style="color: var(--text-secondary); font-size: 0.85rem;">${statusTexto}</div>
                            </div>
                        `;
                        container.appendChild(div);
                    });
                } else {
                    container.innerHTML = '<div class="historico-vazio"><i class="bi bi-inbox"></i><p>Sem registos</p></div>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            });
        }
        
        // Carregar ao abrir a página
        carregarHistorico();
    </script>
</body>
</html>
