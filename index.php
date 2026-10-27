<?php
session_start();
require_once('check_session.php');
require_once('ligar.php');
require_once __DIR__ . '/inc_marcas_modelos.php';
require_once __DIR__ . '/inc_nif.php';
require_once __DIR__ . '/inc_telemovel.php';
require_once __DIR__ . '/inc_horarios_marcacao.php';
require_once __DIR__ . '/inc_configuracoes_kv.php';
require_once __DIR__ . '/inc_admin_notificacoes.php';

am_garantir_nif_unico_schema($con);

// Staff autenticado não deve ver a homepage pública (exceto pré-visualização explícita)
$preview_publico = isset($_GET['preview']) && (string) $_GET['preview'] === '1';
if (!$preview_publico) {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) {
        header('Location: indexadmin.php');
        exit;
    }
    if (!empty($_SESSION['mecanico_id'])) {
        header('Location: funcionarios.php');
        exit;
    }
    if (!empty($_SESSION['chefe_id']) || !empty($_SESSION['is_chefe'])) {
        header('Location: indexchefeoficina.php');
        exit;
    }
}

$am_marcas = am_marcas_listar($con);
$am_modelos_por_marca = am_modelos_por_marca_map($con);

// Verificar se expirou - se sim, mostrar pop-up em vez de redirecionar
$mostrar_popup = false;
if (isset($_SESSION['temp_expired']) && $_SESSION['temp_expired'] === true) {
    $mostrar_popup = true;
    // NÃO redirecionar, deixar o pop-up aparecer
}

// Verificar se já avaliou (para não mostrar o pop-up novamente)
if (isset($_SESSION['avaliou_temp']) && $_SESSION['avaliou_temp'] === true) {
    $mostrar_popup = false;
}


// Verificar se é conta temporária
$is_temp_account = isset($_SESSION['user']['conta_temporaria']) && $_SESSION['user']['conta_temporaria'] === true;

// Verificar se o usuário é admin
$is_admin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    $is_admin = true;
}

// Verificar se é mecânico/funcionário
$is_mecanico = !empty($_SESSION['mecanico_id']);

// Verificar se é chefe de oficina (pré-visualização do site)
$is_chefe = !empty($_SESSION['chefe_id']) || !empty($_SESSION['is_chefe']);

// A página principal deve ficar acessível sem login.
// Regras de autenticação aplicam-se apenas às áreas privadas.

// Verificar se existe mensagem de atualização
$mensagem_atual = am_site_banner_get($con);

// Buscar carros do utilizador logado para pré-preencher matrícula na marcação
$carros_utilizador = [];
if (isset($_SESSION['user']) && !empty($_SESSION['user']['id_utilizador'])) {
    $id_utilizador_sessao = (int) $_SESSION['user']['id_utilizador'];
    // garantir coluna de carro principal (não falha se já existir)
    if (isset($con) && $con) {
        @$con->query("ALTER TABLE carro ADD COLUMN is_principal TINYINT(1) NOT NULL DEFAULT 0");
    }
    $carros_stmt = $con->prepare("
        SELECT marca, modelo, matricula, is_principal
        FROM carro
        WHERE id_utilizador = ?
        ORDER BY is_principal DESC, created_at DESC
    ");
    if ($carros_stmt) {
        $carros_stmt->bind_param("i", $id_utilizador_sessao);
        $carros_stmt->execute();
        $carros_result = $carros_stmt->get_result();
        while ($carro_row = $carros_result->fetch_assoc()) {
            $carros_utilizador[] = $carro_row;
        }
        $carros_stmt->close();
    }
}

// Dados do utilizador logado para pré-preencher passo 1
$dados_utilizador_form = [
    'nome' => '',
    'email' => '',
    'telefone' => '',
    'nif' => ''
];
if (isset($_SESSION['user']) && !empty($_SESSION['user']['id_utilizador'])) {
    $dados_utilizador_form['nome'] = (string)($_SESSION['user']['nome_utilizador'] ?? '');
    $dados_utilizador_form['email'] = (string)($_SESSION['user']['email'] ?? '');
    $id_utilizador_sessao = (int) $_SESSION['user']['id_utilizador'];
    $user_form_stmt = $con->prepare("SELECT telefone, nif FROM utilizador WHERE id_utilizador = ? LIMIT 1");
    if ($user_form_stmt) {
        $user_form_stmt->bind_param("i", $id_utilizador_sessao);
        $user_form_stmt->execute();
        $user_form_result = $user_form_stmt->get_result();
        if ($user_form_result && ($user_form_row = $user_form_result->fetch_assoc())) {
            $dados_utilizador_form['telefone'] = (string)($user_form_row['telefone'] ?? '');
            $dados_utilizador_form['nif'] = (string)($user_form_row['nif'] ?? '');
        }
        $user_form_stmt->close();
    }
}

// (secção "Minhas Marcações" removida do index.php)

// Verificar se já avaliou
$ja_avaliou = false;
if (isset($_SESSION['user']['email'])) {
    $ja_avaliou = am_avaliacao_temp_ja_feita($con, (string) $_SESSION['user']['email'], null);
} elseif (isset($_SESSION['temp_ip'])) {
    $ja_avaliou = am_avaliacao_temp_ja_feita($con, null, (string) $_SESSION['temp_ip']);
}

// Se já avaliou, não mostrar pop-up
if ($ja_avaliou) {
    $mostrar_popup = false;
}

$client_notif_count = 0;
$client_notif_pulse = '';
$client_notif_raw = 0;
$client_notif_sections = ['dados' => 0, 'veiculos' => 0, 'marcacoes' => 0];
$client_notif_section_pulses = ['dados' => '', 'veiculos' => '', 'marcacoes' => ''];
$client_notif_uid = 0;
if (isset($_SESSION['user']['id_utilizador']) && !empty($_SESSION['user']['id_utilizador'])) {
    require_once __DIR__ . '/inc_utilizador_notificacoes.php';
    $client_notif_uid = (int) $_SESSION['user']['id_utilizador'];
    $__cn = am_utilizador_notificacoes_metrics($con, $client_notif_uid);
    $client_notif_count = $__cn['count'];
    $client_notif_raw = (int) ($__cn['raw_count'] ?? $__cn['count']);
    $client_notif_pulse = $__cn['pulse'];
    $client_notif_sections = is_array($__cn['sections'] ?? null) ? $__cn['sections'] : $client_notif_sections;
    $client_notif_section_pulses = is_array($__cn['section_pulses'] ?? null) ? $__cn['section_pulses'] : $client_notif_section_pulses;
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>AUTOMANAGER. — Oficina Automóvel</title>
<meta name="description" content="AUTOMANAGER — oficina automóvel. Marque reparações, veja serviços e contacte-nos." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="booking-clean.css?v=7">
<link rel="stylesheet" href="notificacoes.css?v=2">
<style>
    /* Estilos específicos para o AI */
    .card-ai {
        background: linear-gradient(135deg, rgba(10,10,10,0.95) 0%, rgba(26,26,26,0.95) 100%);
        border: 2px solid rgba(255, 107, 0, 0.3);
        transition: all 0.3s;
    }
    
    .card-ai:hover {
        border-color: #FF6B00;
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(255, 107, 0, 0.2);
    }
    
    .card-ai .icon {
        font-size: 3em;
        animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .ai-link {
        display: inline-block;
        background: linear-gradient(135deg, #FF6B00 0%, #FF8C42 100%);
        color: white;
        padding: 12px 25px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-top: 15px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        font-size: 1em;
    }
    
    .ai-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 107, 0, 0.4);
    }
    
    /* Modal do AI - SIMPLIFICADO */
    .ai-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.95);
        z-index: 9999;
    }
    
    .ai-modal iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    
    .ai-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #ff4444;
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 20px;
        cursor: pointer;
        z-index: 10000;
    }
    
    /* Estilos do Timer */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .timer-warning {
        animation: pulse 1s infinite !important;
        background: linear-gradient(135deg, rgba(255,100,0,0.95), rgba(255,0,0,0.95)) !important;
    }
    
    .timer-critical {
        background: linear-gradient(135deg, rgba(255,0,0,0.95), rgba(200,0,0,0.95)) !important;
        animation: pulse 0.5s infinite !important;
    }
    
    #temp-timer {
        transition: all 0.3s ease;
        z-index: 9999;
    }
    
    #temp-timer:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px -5px rgba(255,69,0,0.7);
    }
    
    /* Pop-up de avaliação */
    @keyframes popupAppear {
        from {
            opacity: 0;
            transform: scale(0.8) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    .star:hover,
    .star.active {
        transform: scale(1.2);
        text-shadow: 0 0 20px #ff4500;
    }
    
    .star.active {
        color: #ff4500;
    }
    
    #avaliacao-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(5px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #avaliacao-popup {
        background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
        border-radius: 20px;
        padding: 40px;
        max-width: 500px;
        width: 90%;
        border: 1px solid rgba(255,69,0,0.3);
        box-shadow: 0 20px 60px rgba(255,69,0,0.3);
        position: relative;
        animation: popupAppear 0.5s ease-out;
        max-height: 90vh;
        overflow-y: auto;
    }
</style>
</head>
<body>

<!-- Timer com ícone expansível -->
<?php if (false && !isset($_SESSION['user']) && isset($_SESSION['temp_allowed'])): ?>
<!-- Ícone do timer (sempre visível) -->
<div id="timer-icon" style="position: fixed; top: 80px; right: 20px; background: linear-gradient(135deg, #ff4500, #ff6b00); color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 5px 15px rgba(255,69,0,0.5); z-index: 9998; transition: all 0.3s; border: 2px solid white;" onclick="toggleTimer()">
    <i class="fas fa-clock" style="font-size: 24px;"></i>
    <span id="timer-icon-minutes" style="position: absolute; top: -5px; right: -5px; background: white; color: #ff4500; border-radius: 50%; width: 22px; height: 22px; font-size: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #ff4500;">2</span>
</div>

<!-- Timer expandido (inicialmente escondido) -->
<div id="temp-timer" style="position: fixed; top: 140px; right: 20px; background: linear-gradient(135deg, rgba(255,69,0,0.95), rgba(255,107,0,0.95)); color: white; padding: 15px 25px; border-radius: 50px; font-weight: 600; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 10px 30px -5px rgba(255,69,0,0.5); display: none; align-items: center; gap: 12px; font-size: 1.1em; z-index: 9999; animation: slideIn 0.3s ease-out;">
    <i class="fas fa-hourglass-half"></i>
    <span id="timer-display">2:00</span>
    <span>restantes</span>
    <button onclick="window.location.href='login.php'" style="background: white; color: #ff4500; border: none; padding: 5px 15px; border-radius: 30px; font-weight: 600; cursor: pointer; font-size: 0.9em;">Login</button>
    <button onclick="toggleTimer()" style="background: rgba(255,255,255,0.2); color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Fechar">
        <i class="fas fa-times"></i>
    </button>
</div>

<div id="avaliacao-popup-container" style="display: none;"></div>

<style>
#timer-icon {
    transition: all 0.3s ease;
}

#timer-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 20px rgba(255,69,0,0.7);
}

#timer-icon.timer-critical-icon {
    background: linear-gradient(135deg, rgba(255,0,0,0.95), rgba(200,0,0,0.95)) !important;
    animation: pulse 0.5s infinite;
}

#timer-icon.timer-warning-icon {
    background: linear-gradient(135deg, rgba(255,100,0,0.95), rgba(255,0,0,0.95)) !important;
}

.timer-critical {
    background: linear-gradient(135deg, rgba(255,0,0,0.95), rgba(200,0,0,0.95)) !important;
    animation: pulse 0.5s infinite;
}

.timer-warning {
    background: linear-gradient(135deg, rgba(255,100,0,0.95), rgba(255,0,0,0.95)) !important;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>

<script>
let timerExpandido = false;
let tempoRestante = 120; // 2 minutos
let timerInterval;

function toggleTimer() {
    const timerDiv = document.getElementById('temp-timer');
    if (timerExpandido) {
        timerDiv.style.display = 'none';
    } else {
        timerDiv.style.display = 'flex';
    }
    timerExpandido = !timerExpandido;
}

function atualizarDisplay() {
    const minutos = Math.floor(tempoRestante / 60);
    const segundos = tempoRestante % 60;
    const timerDisplay = document.getElementById('timer-display');
    const timerIconMinutes = document.getElementById('timer-icon-minutes');
    const timerDiv = document.getElementById('temp-timer');
    const timerIcon = document.getElementById('timer-icon');
    
    if (timerDisplay) {
        timerDisplay.textContent = minutos + ':' + (segundos < 10 ? '0' : '') + segundos;
    }
    if (timerIconMinutes) {
        timerIconMinutes.textContent = minutos;
    }
    
    // Mudar cor conforme o tempo
    if (tempoRestante <= 30) {
        if (timerDiv) timerDiv.classList.add('timer-critical');
        if (timerIcon) {
            timerIcon.classList.add('timer-critical-icon');
            timerIcon.classList.remove('timer-warning-icon');
        }
    } else if (tempoRestante <= 60) {
        if (timerDiv) {
            timerDiv.classList.add('timer-warning');
            timerDiv.classList.remove('timer-critical');
        }
        if (timerIcon) {
            timerIcon.classList.add('timer-warning-icon');
            timerIcon.classList.remove('timer-critical-icon');
        }
    } else {
        if (timerDiv) timerDiv.classList.remove('timer-warning', 'timer-critical');
        if (timerIcon) timerIcon.classList.remove('timer-warning-icon', 'timer-critical-icon');
    }
}

// Verificar tempo a cada segundo via AJAX
function verificarTempo() {
    fetch('verificar_tempo.php')
        .then(response => response.json())
        .then(data => {
            if (data.expired) {
                // Tempo expirou
                if (timerInterval) clearInterval(timerInterval);
                document.getElementById('timer-icon').style.display = 'none';
                document.getElementById('temp-timer').style.display = 'none';
                
                if (data.mostrar_popup) {
                    mostrarPopupAvaliacao();
                } else {
                    window.location.href = 'login.php?expired=1';
                }
            } else {
                // Atualizar tempo do servidor
                tempoRestante = data.tempo_restante;
                atualizarDisplay();
            }
        })
        .catch(error => {
            // Se der erro, decrementar localmente
            tempoRestante--;
            if (tempoRestante < 0) tempoRestante = 0;
            atualizarDisplay();
        });
}

// Função para mostrar popup de avaliação
function mostrarPopupAvaliacao() {
    const popupHTML = `
        <div id="avaliacao-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 10000; display: flex; align-items: center; justify-content: center;">
            <div id="avaliacao-popup" style="background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%); border-radius: 20px; padding: 40px; max-width: 500px; width: 90%; border: 1px solid rgba(255,69,0,0.3); box-shadow: 0 20px 60px rgba(255,69,0,0.3); position: relative; animation: popupAppear 0.5s ease-out; max-height: 90vh; overflow-y: auto;">
                <button onclick="document.getElementById('avaliacao-popup-container').style.display='none'; window.location.href='login.php';" style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: rgba(255,255,255,0.5); font-size: 24px; cursor: pointer;">&times;</button>
                
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-clock" style="font-size: 50px; color: #ff4500;"></i>
                    <h2 style="color: white;"><i class="fas fa-clock" aria-hidden="true"></i> O tempo terminou!</h2>
                    <p style="color: #ccc;">Ajude-nos a melhorar avaliando a sua experiência:</p>
                </div>
                
                <form id="avaliacaoForm">
                    <div style="margin-bottom: 20px;">
                        <label style="color: white;">Avaliação:</label>
                        <div style="display: flex; gap: 10px; justify-content: center; font-size: 30px; margin-top: 10px;">
                            <span class="star" data-value="1" style="cursor: pointer;">-</span>
                            <span class="star" data-value="2" style="cursor: pointer;">-</span>
                            <span class="star" data-value="3" style="cursor: pointer;">-</span>
                            <span class="star" data-value="4" style="cursor: pointer;">-</span>
                            <span class="star" data-value="5" style="cursor: pointer;">-</span>
                        </div>
                        <input type="hidden" name="avaliacao" id="avaliacao_valor" required>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <input type="text" name="nome" placeholder="Seu nome" required style="width: 100%; padding: 10px; background: #333; border: 1px solid #ff4500; border-radius: 5px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <input type="email" name="email" placeholder="Seu email" required style="width: 100%; padding: 10px; background: #333; border: 1px solid #ff4500; border-radius: 5px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <textarea name="comentario" placeholder="Comentário (opcional)" rows="3" style="width: 100%; padding: 10px; background: #333; border: 1px solid #ff4500; border-radius: 5px; color: white;"></textarea>
                    </div>
                    
                    <button type="submit" style="width: 100%; padding: 12px; background: #ff4500; border: none; border-radius: 5px; color: white; font-weight: bold; cursor: pointer;">Enviar Avaliação</button>
                </form>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="login.php" style="color: #ff4500; text-decoration: none;">Ir para login →</a>
                </div>
            </div>
        </div>
    `;
    
    let container = document.getElementById('avaliacao-popup-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'avaliacao-popup-container';
        document.body.appendChild(container);
    }
    
    container.innerHTML = popupHTML;
    container.style.display = 'block';
    
    // Sistema de estrelas
    const stars = document.querySelectorAll('.star');
    const avaliacaoInput = document.getElementById('avaliacao_valor');
    
    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const value = this.dataset.value;
            stars.forEach((s, index) => {
                s.innerHTML = index < value ? '*' : '-';
                s.style.color = '#ff4500';
            });
        });
        
        star.addEventListener('mouseout', function() {
            const valorAtual = avaliacaoInput.value;
            stars.forEach((s, index) => {
                s.innerHTML = index < valorAtual ? '*' : '-';
            });
        });
        
        star.addEventListener('click', function() {
            const value = this.dataset.value;
            avaliacaoInput.value = value;
            stars.forEach((s, index) => {
                s.innerHTML = index < value ? '*' : '-';
            });
        });
    });
    
    // Submeter formulário
    document.getElementById('avaliacaoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('avaliacao_popup.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Avaliação registada! Obrigado.');
                window.location.href = 'login.php';
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro ao enviar. Tente novamente.');
        });
    });
}

// Inicializar display
atualizarDisplay();

// Verificar tempo a cada segundo
verificarTempo();
timerInterval = setInterval(verificarTempo, 1000);

// Backup: decrementar localmente a cada segundo
setInterval(function() {
    if (tempoRestante > 0) {
        tempoRestante--;
    }
}, 1000);
</script>
<?php endif; ?>


<!-- Modal do AI -->
<div class="ai-modal" id="aiModal">
    <button class="ai-close" onclick="fecharAI()"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <iframe id="aiFrame"></iframe>
</div>

<!-- Pop-up de avaliação (quando o tempo acaba) -->
<?php if ($mostrar_popup && !$ja_avaliou): ?>
<div id="avaliacao-overlay">
    <div id="avaliacao-popup">
        
        <button onclick="fecharPopupAgora()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: rgba(255,255,255,0.5); font-size: 24px; cursor: pointer;">&times;</button>
        
        <div style="text-align: center; margin-bottom: 30px;">
            <i class="fas fa-clock" style="font-size: 50px; color: #ff4500; margin-bottom: 15px;"></i>
            <h2 style="color: white; margin-bottom: 10px;"><i class="fas fa-clock" aria-hidden="true"></i> O tempo terminou!</h2>
            <p style="color: rgba(255,255,255,0.7);">Os seus 2 minutos de navegação gratuita terminaram.</p>
        </div>
        
        <div style="background: rgba(255,69,0,0.1); border-radius: 15px; padding: 20px; margin-bottom: 25px; border: 1px solid rgba(255,69,0,0.3);">
            <h3 style="color: #ff4500; margin-bottom: 15px;"><i class="fas fa-star" aria-hidden="true"></i> Avalie a sua experiência</h3>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 20px;">Ajude-nos a melhorar! Avalie a sua experiência no AutoManager:</p>
            
            <form id="avaliacaoForm">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: white; margin-bottom: 10px;">Avaliação:</label>
                    <div style="display: flex; gap: 10px; justify-content: center; font-size: 30px;">
                        <span class="star" data-value="1" style="cursor: pointer; color: #ff4500; transition: all 0.3s;">-</span>
                        <span class="star" data-value="2" style="cursor: pointer; color: #ff4500; transition: all 0.3s;">-</span>
                        <span class="star" data-value="3" style="cursor: pointer; color: #ff4500; transition: all 0.3s;">-</span>
                        <span class="star" data-value="4" style="cursor: pointer; color: #ff4500; transition: all 0.3s;">-</span>
                        <span class="star" data-value="5" style="cursor: pointer; color: #ff4500; transition: all 0.3s;">-</span>
                    </div>
                    <input type="hidden" name="avaliacao" id="avaliacao_valor" required>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: white; margin-bottom: 10px;">Nome:</label>
                    <input type="text" name="nome" id="avaliacao_nome" required style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,69,0,0.3); border-radius: 8px; color: white;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: white; margin-bottom: 10px;">Email:</label>
                    <input type="email" name="email" id="avaliacao_email" required style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,69,0,0.3); border-radius: 8px; color: white;">
                    <small style="color: rgba(255,255,255,0.5); display: block; margin-top: 5px;">Iremos enviar dicas e novidades para este email</small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: white; margin-bottom: 10px;">Comentário (opcional):</label>
                    <textarea name="comentario" id="avaliacao_comentario" rows="3" style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,69,0,0.3); border-radius: 8px; color: white;"></textarea>
                </div>
                
                <button type="submit" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #ff4500, #ff6b00); border: none; border-radius: 8px; color: white; font-weight: 600; font-size: 1.1em; cursor: pointer; transition: all 0.3s;">
                    Enviar Avaliação
                </button>
            </form>
        </div>
        
        <div style="text-align: center;">
            <p style="color: rgba(255,255,255,0.5); font-size: 0.9em;">
                <i class="fas fa-envelope"></i> Receberá dicas exclusivas no seu email!
            </p>
            <a href="login.php" style="color: #ff4500; text-decoration: none; font-size: 0.9em;">Ir para login →</a>
        </div>
    </div>
</div>
<?php endif; ?>

<header>
  <div class="container nav">
    <a href="#home" class="brand" aria-label="AutoManager — Início">
      <img src="img/logo-automanager-transparente.png" alt="AutoManager" class="logo" width="220" height="44" decoding="async">
    </a>
    <button class="nav-toggle" id="navToggle" style="display: none; background: transparent; border: 1px solid rgba(255,255,255,0.2); color: white; padding: 15px; border-radius: 5px; cursor: pointer;">
        <i class="fas fa-bars"></i>
    </button>
    <nav id="navbar" align="left">
      <ul>
        <li><a href="#home">Início</a></li>
        <li><a href="#services">Serviços</a></li>
        <li><a href="#book-form">Marcar</a></li>
        <li><a href="#about">Sobre</a></li>
        <li><a href="#contact">Contactos</a></li>
      </ul>
    </nav>
   <div class="nav-actions">
    <?php if ($is_admin): ?>
        <button class="btn-ghost" type="button" onclick="window.location.href='indexadmin.php'" aria-label="Administração"><i class="fas fa-shield-alt" aria-hidden="true"></i> Admin</button>
        <button class="btn-ghost" type="button" onclick="window.location.href='logout.php'" aria-label="Terminar sessão"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sair</button>
    <?php elseif ($is_mecanico): ?>
        <button class="btn-ghost" type="button" onclick="window.location.href='funcionarios.php'" aria-label="Área técnica"><i class="fas fa-tools" aria-hidden="true"></i> Oficina</button>
        <button class="btn-ghost" type="button" onclick="window.location.href='logout.php'" aria-label="Terminar sessão"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sair</button>
    <?php elseif ($is_chefe): ?>
        <button class="btn-ghost" type="button" onclick="window.location.href='indexchefeoficina.php'" aria-label="Painel da oficina"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Oficina</button>
        <button class="btn-ghost" type="button" onclick="window.location.href='logout.php'" aria-label="Terminar sessão"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sair</button>
    <?php elseif (isset($_SESSION['user']) && !empty($_SESSION['user']['id_utilizador'])): ?>
        <?php if (!empty($client_notif_uid)): ?>
        <button class="btn-ghost btn-user-notif<?= $client_notif_count > 0 ? ' has-notif' : '' ?>" type="button" onclick="window.location.href='configuracoes.php<?= $client_notif_count > 0 ? '?seccao=marcacoes' : '' ?>'" aria-label="<?= $client_notif_count > 0 ? ($client_notif_count === 1 ? '1 notificação por ver' : (int)$client_notif_count . ' notificações por ver') : 'Configurações e perfil' ?>" title="<?= $client_notif_count > 0 ? ($client_notif_count === 1 ? '1 notificação por ver' : (int)$client_notif_count . ' notificações por ver') : 'Configurações e perfil' ?>" id="indexUserNotifBtn">
            <span class="btn-user-icon"><i class="fas <?= $client_notif_count > 0 ? 'fa-bell' : 'fa-user' ?>" aria-hidden="true"></i></span>
            <span class="notif-pill<?= $client_notif_count > 0 ? ' is-on' : '' ?>" id="indexUserNotifPill"><?= $client_notif_count > 99 ? '99+' : (int) $client_notif_count ?></span>
        </button>
        <?php else: ?>
        <button class="btn-ghost" type="button" onclick="window.location.href='configuracoes.php'" aria-label="Perfil" title="Perfil"><i class="fas fa-user" aria-hidden="true"></i></button>
        <?php endif; ?>
        <button class="btn-ghost" type="button" onclick="window.location.href='logout.php'" aria-label="Terminar sessão"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sair</button>
    <?php else: ?>
        <button class="btn-ghost" onclick="window.location.href='login.php'">Iniciar Sessão</button>
        <button class="btn-ghost" onclick="window.location.href='login.php?show=register'">Criar Conta</button>
    <?php endif; ?>
</div>
  </div>
</header>

<section class="hero" id="home">
  <div class="hero-image" id="hero-image"></div>
  <div class="hero-inner">
    <div class="hero-left">
      <h1>Força e confiança para o seu automóvel.</h1>
      <p>Na AUTOMANAGER tratamos do seu veículo com rapidez, transparência e qualidade — marque online em segundos.</p>
      <div class="hero-actions">
        <button class="hero-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Marcar Reparação</button>
        <button class="hero-btn" onclick="document.getElementById('services').scrollIntoView({behavior:'smooth'})">Ver Serviços</button>
        <?php if ($is_admin): ?>
          <button class="hero-btn" onclick="window.location.href='indexadmin.php?tab=marcacoes#marcacoes'">Marcações</button>
        <?php elseif ($is_mecanico): ?>
          <button class="hero-btn" onclick="window.location.href='funcionarios.php'">Área técnica</button>
        <?php elseif ($is_chefe): ?>
          <button class="hero-btn" onclick="window.location.href='indexchefeoficina.php'">Painel da oficina</button>
        <?php elseif (isset($_SESSION['user']) && !empty($_SESSION['user']['id_utilizador'])): ?>
          <button class="hero-btn" onclick="window.location.href='configuracoes.php?seccao=marcacoes'">As minhas marcações</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" id="services">
  <div class="container">
    <h2>Serviços AUTOMANAGER.</h2>
    <div class="cards">
      <div class="card">
          <div class="icon"><i class="fas fa-tools" aria-hidden="true"></i></div>
          <h4>Revisões gerais</h4>
          <p>Manutenção preventiva completa para garantir desempenho e segurança do seu veículo.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-laptop-medical" aria-hidden="true"></i></div>
          <h4>Diagnóstico eletrónico</h4>
          <p>Leitura de falhas e calibrações com equipamentos modernos e precisos.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-circle-notch" aria-hidden="true"></i></div>
          <h4>Pneus & alinhamento</h4>
          <p>Troca, balanceamento e alinhamento para uma condução mais segura e confortável.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></div>
          <h4>Travões & suspensão</h4>
          <p>Serviços especializados para fiabilidade e conforto em todas as viagens.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-bolt" aria-hidden="true"></i></div>
          <h4>Bateria & elétrica</h4>
          <p>Verificação e substituição de baterias, luzes e sistemas elétricos do veículo.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-temperature-half" aria-hidden="true"></i></div>
          <h4>Ar condicionado & climatização</h4>
          <p>Manutenção do sistema de ar condicionado para conforto em todas as estações.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-oil-can" aria-hidden="true"></i></div>
          <h4>Óleo & filtros</h4>
          <p>Troca de óleo, filtros de motor e cabine para prolongar a vida do veículo.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-soap" aria-hidden="true"></i></div>
          <h4>Limpeza & detalhamento</h4>
          <p>Lavagem, polimento e higienização completa do interior e exterior do carro.</p>
          <button class="small-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar</button>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2>Porquê escolher a AUTOMANAGER?</h2>
    <div class="reasons">
      <div class="reason"><div class="reason-icon"><i class="fas fa-bolt" aria-hidden="true"></i></div><h3>Rapidez</h3><p>Agendamentos eficientes e processos optimizados.</p></div>
      <div class="reason"><div class="reason-icon"><i class="fas fa-user-gear" aria-hidden="true"></i></div><h3>Mecânicos certificados</h3><p>Profissionais qualificados e experientes.</p></div>
      <div class="reason"><div class="reason-icon"><i class="fas fa-comments" aria-hidden="true"></i></div><h3>Transparência</h3><p>Orçamentos claros e comunicação constante.</p></div>
    </div>

    <div class="cta-strip">
      <h3>Marque online a sua reparação em 3 minutos!</h3>
      <button class="cta-btn" onclick="document.getElementById('book-form').scrollIntoView({behavior:'smooth'})">Agendar Agora</button>
    </div>
  </div>
</section>

<section class="section" id="about">
  <div class="container about-section">
    <div class="about-header">
      <h2>Sobre a AUTOMANAGER</h2>
      <p class="about-subtext">
        A AUTOMANAGER é uma oficina automóvel moderna, focada em confiança, inovação e excelência técnica.  
        Unimos experiência e tecnologia para garantir o melhor desempenho, segurança e longevidade do seu automóvel.
      </p>
      <div class="about-stats">
        <div><strong>+23</strong><span>Mecânicos certificados</span></div>
        <div><strong>+500</strong><span>Clientes satisfeitos</span></div>
        <div><strong>Desde 2025</strong><span>Experiência e dedicação</span></div>
      </div>
    </div>

    <div class="about-bottom-grid">
      <div class="testimonials-section">
        <h3>O que os clientes dizem</h3>
        <p class="testimonials-subtext">Quem já passou pela AutoManager destaca a transparência, a rapidez e a qualidade nas reparações.</p>
        <div class="testimonials">
          <div class="tcard">
            <div class="tcard-head"><strong>Iara Ponte</strong><span class="tcard-stars">★★★★★</span></div>
            <span class="tcard-service">- Revisão Geral e Travões</span>
            <p>Serviço rápido, explicaram tudo antes de avançar e o carro ficou impecável.</p>
          </div>
          <div class="tcard">
            <div class="tcard-head"><strong>Luís Caracol</strong><span class="tcard-stars">★★★★★</span></div>
            <span class="tcard-service">- Diagnóstico Eletrónico</span>
            <p>Descobriram o problema logo à primeira e cumpriram o orçamento combinado.</p>
          </div>
          <div class="tcard">
            <div class="tcard-head"><strong>Martim Oliveira</strong><span class="tcard-stars">★★★★☆</span></div>
            <span class="tcard-service">- Pneus & Alinhamento</span>
            <p>Bom atendimento e explicações claras, voltarei para as próximas manutenções.</p>
          </div>
          <div class="tcard">
            <div class="tcard-head"><strong>Joana Tavares</strong><span class="tcard-stars">★★★★★</span></div>
            <span class="tcard-service">- Limpeza & Climatização</span>
            <p>Voltou como novo, trabalho impecável.</p>
          </div>
          <div class="tcard">
            <div class="tcard-head"><strong>Carlos Mendes</strong><span class="tcard-stars">★★★★★</span></div>
            <span class="tcard-service">- Óleo & Filtros</span>
            <p>Atendimento pontual e fizeram uma revisão completa sem complicar.</p>
          </div>
          <div class="tcard">
            <div class="tcard-head"><strong>Sofia Almeida</strong><span class="tcard-stars">★★★★☆</span></div>
            <span class="tcard-service">- Bateria & Elétrica</span>
            <p>Diagnóstico rápido e solução definitiva. Recomendo pela clareza.</p>
          </div>
        </div>
      </div>

      <!-- FORMULÁRIO -->
<aside id="book-form" class="form-modern booking-flow" aria-labelledby="booking-title">
    <h3 id="booking-title">Marcar reparação</h3>
    <p class="form-subtext">
        Em poucos minutos — só precisamos dos seus dados, do veículo e da data preferida.
    </p>

    <?php
    function validarNifPortugues(string $nif): array {
        $nif = preg_replace('/\D+/', '', $nif);

        if (strlen($nif) !== 9) {
            return [false, 'O NIF deve ter exatamente 9 dígitos.'];
        }

        if (preg_match('/^(\d)\1{8}$/', $nif)) {
            return [false, 'O NIF não pode ter todos os dígitos iguais.'];
        }

        $primeiro = (int)$nif[0];
        $prefixosValidos = [1, 2, 3, 5, 6, 8, 9];
        if (!in_array($primeiro, $prefixosValidos, true)) {
            return [false, 'O NIF tem um prefixo inválido.'];
        }

        $soma = 0;
        for ($i = 0; $i < 8; $i++) {
            $soma += ((int)$nif[$i]) * (9 - $i);
        }

        $resto = $soma % 11;
        $digitoControlo = 11 - $resto;
        if ($digitoControlo >= 10) {
            $digitoControlo = 0;
        }

        if ((int)$nif[8] !== $digitoControlo) {
            return [false, 'NIF inválido (dígito de controlo incorreto).'];
        }

        return [true, ''];
    }

    function garantirColunaNif(mysqli $con): void {
        $resultado = $con->query("SHOW COLUMNS FROM servico LIKE 'nif'");
        if ($resultado && $resultado->num_rows > 0) {
            return;
        }

        $alterSql = "ALTER TABLE servico ADD COLUMN nif VARCHAR(9) NULL AFTER telefone";
        if (!$con->query($alterSql)) {
            if (strpos($con->error, 'Duplicate column name') === false) {
                throw new Exception("Não foi possível criar a coluna nif: " . $con->error);
            }
        }
    }

    function garantirColunasMarcacaoVeiculo(mysqli $con): void {
        $campos = [];
        $res = $con->query("SHOW COLUMNS FROM servico");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $campos[] = $row['Field'];
            }
        }

        if (!in_array('marca', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN marca VARCHAR(60) NULL AFTER nif");
        }
        if (!in_array('modelo', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN modelo VARCHAR(80) NULL AFTER marca");
        }
        if (!in_array('hora_desejada', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN hora_desejada VARCHAR(5) NULL AFTER data_desejada");
        }
        if (!in_array('plano_pronto', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN plano_pronto VARCHAR(30) NULL AFTER hora_desejada");
        }
        if (!in_array('sla_prometido_horas', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN sla_prometido_horas INT NULL AFTER plano_pronto");
        }
        if (!in_array('sla_inicio_at', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN sla_inicio_at DATETIME NULL AFTER sla_prometido_horas");
        }
        if (!in_array('sla_limite_at', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN sla_limite_at DATETIME NULL AFTER sla_inicio_at");
        }
        if (!in_array('sla_estado', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN sla_estado VARCHAR(20) NULL AFTER sla_limite_at");
        }
        if (!in_array('sla_compensacao_tipo', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN sla_compensacao_tipo VARCHAR(30) NULL AFTER sla_estado");
        }
        if (!in_array('sla_compensacao_valor', $campos, true)) {
            @$con->query("ALTER TABLE servico ADD COLUMN sla_compensacao_valor DECIMAL(10,2) NULL AFTER sla_compensacao_tipo");
        }
    }

    $utilizador_logado = isset($_SESSION['user']['id_utilizador']) && !empty($_SESSION['user']['id_utilizador']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$utilizador_logado) {
            echo "<p style='color:red;font-weight:bold;'><i class='fas fa-lock'></i> Para fazer uma marcação, precisa de iniciar sessão primeiro.</p>";
        } else {
        $nome = $_POST['name'] ?? '';
        $telefone = am_normalizar_telemovel((string) ($_POST['phone'] ?? ''));
        $nif = $_POST['nif'] ?? '';
        $email = $_POST['email'] ?? '';
        $marca = $_POST['marca'] ?? '';
        $modelo = $_POST['modelo'] ?? '';
        $matricula = $_POST['plate'] ?? '';
        $tipo_servico = $_POST['service'] ?? '';
        $plano_pronto = $_POST['plano_pronto'] ?? 'normal';
        $data_desejada = $_POST['date'] ?? null;
        $hora_desejada = $_POST['time'] ?? '';
        $nif = preg_replace('/\D+/', '', $nif);
        $hora_desejada = trim($hora_desejada);
        [$matriculaOk, $matriculaErro, $matricula] = am_validar_matricula($matricula);
        
        // Obter ID do utilizador se estiver logado
        $id_utilizador = null;
        if (isset($_SESSION['user']['id_utilizador'])) {
            $id_utilizador = (int)$_SESSION['user']['id_utilizador'];
            // Se estiver logado, usar os dados da sessão
            $nome = $_SESSION['user']['nome_utilizador'] ?? $nome;
            $email = $_SESSION['user']['email'] ?? $email;
        }

        // VALIDAÇÃO DA DATA
        $data_valida = true;
        $mensagem_erro_data = '';
        
        if (!empty($data_desejada)) {
            require_once __DIR__ . '/inc_horarios_marcacao.php';
            [$data_valida, $mensagem_erro_data] = am_horarios_validar_data_marcacao((string) $data_desejada);
        }

        [$nifValido, $mensagemErroNif] = validarNifPortugues($nif);
        [$telValido, $mensagemErroTel] = am_validar_telemovel_portugues($telefone, true);

        $excluirNifMarc = [];
        if (!empty($id_utilizador)) {
            $excluirNifMarc['utilizador'] = (int) $id_utilizador;
        }
        [$nifUnicoMarc, $msgNifUnicoMarc] = am_validar_nif_unico($con, $nif, $excluirNifMarc);

        if (!$nome || !$telefone || !$nif || !$email || !$marca || !$modelo || !$matricula || !$tipo_servico || !$hora_desejada) {
            echo "<p style='color:red;'> Por favor, preencha todos os campos obrigatórios.</p>";
        } elseif (!$telValido) {
            echo "<p style='color:red;'>" . htmlspecialchars($mensagemErroTel) . "</p>";
        } elseif (!$nifValido) {
            echo "<p style='color:red;'>" . htmlspecialchars($mensagemErroNif) . "</p>";
        } elseif (!$nifUnicoMarc) {
            echo "<p style='color:red;'>" . htmlspecialchars($msgNifUnicoMarc) . "</p>";
        } elseif (!$matriculaOk) {
            echo "<p style='color:red;'>" . htmlspecialchars($matriculaErro) . "</p>";
        } else {
            $carroMatricula = am_carro_matricula_existe($con, $matricula);
            if ($carroMatricula && (int) ($carroMatricula['id_utilizador'] ?? 0) !== (int) $id_utilizador) {
                echo "<p style='color:red;'>Esta matrícula já está registada noutro veículo. Use o carro da sua conta ou registe a matrícula em Configurações → Veículos.</p>";
            } elseif (!preg_match('/^\d{2}:\d{2}$/', $hora_desejada)) {
                echo "<p style='color:red;'>Hora inválida.</p>";
            } elseif (!$data_valida && !empty($data_desejada)) {
                echo "<p style='color:red;'>$mensagem_erro_data</p>";
            } else {
            require_once __DIR__ . '/inc_horarios_marcacao.php';
            [$slotOk, $slotMsg] = am_horario_slot_disponivel($con, (string) $data_desejada, (string) $hora_desejada);
            if (!$slotOk) {
                echo "<p style='color:red;'>" . htmlspecialchars($slotMsg) . "</p>";
            } else {
            require_once __DIR__ . '/inc_rate_limit_marcacao.php';
            [$rlOk, $rlMsg] = am_rate_limit_marcacao_check($con, (int) ($id_utilizador ?? 0), (string) $email);
            if (!$rlOk) {
                echo "<p style='color:red;'>" . htmlspecialchars($rlMsg) . "</p>";
            } else {

            $custos = [
                'Revisão geral' => rand(120, 250),
                'Diagnóstico eletrónico' => rand(40, 80),
                'Pneus & alinhamento' => rand(60, 180),
                'Travões & suspensão' => rand(100, 300),
                'Bateria & elétrica' => rand(70, 200),
                'Ar condicionado & climatização' => rand(60, 150),
                'Óleo & filtros' => rand(50, 120),
                'Limpeza & detalhamento' => rand(30, 100)
            ];
            $custo = $custos[$tipo_servico] ?? rand(50, 150);
            $planos_sla = [
                'normal' => ['horas' => 0, 'extra' => 0.0, 'label' => 'Padrão (sem compromisso premium)'],
                'premium_4h' => ['horas' => 4, 'extra' => 35.0, 'label' => 'Premium 4h'],
                'premium_8h' => ['horas' => 8, 'extra' => 20.0, 'label' => 'Premium 8h'],
                'premium_24h' => ['horas' => 24, 'extra' => 10.0, 'label' => 'Premium 24h']
            ];
            if (!isset($planos_sla[$plano_pronto])) {
                $plano_pronto = 'normal';
            }
            $sla_horas = (int)$planos_sla[$plano_pronto]['horas'];
            $custo = (float)$custo + (float)$planos_sla[$plano_pronto]['extra'];
            $sla_inicio_at = $sla_horas > 0 ? date('Y-m-d H:i:s') : null;
            $sla_limite_at = $sla_horas > 0 ? date('Y-m-d H:i:s', strtotime('+' . $sla_horas . ' hours')) : null;
            $sla_estado = $sla_horas > 0 ? 'ativo' : 'nao_aplicavel';
            $sla_compensacao_tipo = $sla_horas > 0 ? 'desconto' : '';
            $sla_compensacao_valor = 0.0;

            try {
                if (!isset($con) || !$con) {
                    throw new Exception("Ligação à base de dados não disponível");
                }

                garantirColunaNif($con);
                garantirColunasMarcacaoVeiculo($con);

                // Preparar statement mysqli e guardar NIF
                $stmt = $con->prepare("INSERT INTO servico (nome, telefone, nif, marca, modelo, email, matricula, tipo_servico, data_desejada, hora_desejada, custo, plano_pronto, sla_prometido_horas, sla_inicio_at, sla_limite_at, sla_estado, sla_compensacao_tipo, sla_compensacao_valor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception('Erro na preparação da query: ' . $con->error);
                }
                $data_param = isset($data_desejada) ? $data_desejada : '';
                $stmt->bind_param("ssssssssssdsissssd", $nome, $telefone, $nif, $marca, $modelo, $email, $matricula, $tipo_servico, $data_param, $hora_desejada, $custo, $plano_pronto, $sla_horas, $sla_inicio_at, $sla_limite_at, $sla_estado, $sla_compensacao_tipo, $sla_compensacao_valor);

                if (!$stmt->execute()) {
                    throw new Exception('Erro ao executar a query: ' . $stmt->error);
                }

                am_rate_limit_marcacao_registar();

                $servico_id = $con->insert_id;
                $stmt->close();

                if (!empty($id_utilizador) && $servico_id > 0) {
                    $chkUidIns = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_utilizador'");
                    if ($chkUidIns && $chkUidIns->num_rows > 0) {
                        $upUid = $con->prepare('UPDATE servico SET id_utilizador = ? WHERE id = ?');
                        if ($upUid) {
                            $upUid->bind_param('ii', $id_utilizador, $servico_id);
                            $upUid->execute();
                            $upUid->close();
                        }
                    }
                }

                // Email de confirmação (sem pagamento antecipado — o cliente paga após conclusão do trabalho)
                $assunto = "Confirmação de marcação - $tipo_servico";
                $mensagem = "
                <html>
                <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
                  <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                      <h1 style='color: #ff6b00; margin: 0;'>AUTOMANAGER</h1>
                      <p style='color: #666; margin: 5px 0 0 0;'>Oficina Automóvel</p>
                    </div>
                    
                    <h2 style='color: #333;'>Olá, $nome!</h2>
                    <p style='color: #666; line-height: 1.6;'>A sua marcação foi registada com sucesso na <strong style='color: #ff6b00;'>AUTOMANAGER</strong>.</p>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                      <h3 style='color: #333; margin-top: 0;'>[LISTA] Detalhes da Marcação</h3>
                      <ul style='color: #666; line-height: 1.8; list-style: none; padding: 0;'>
                        <li><strong>Serviço:</strong> $tipo_servico</li>
                        <li><strong>Data:</strong> " . ($data_desejada ? date('d/m/Y', strtotime($data_desejada)) : 'A confirmar') . "</li>
                        <li><strong>Hora:</strong> " . htmlspecialchars($hora_desejada) . "</li>
                        <li><strong>Matrícula:</strong> $matricula</li>
                        <li><strong>Veículo:</strong> " . htmlspecialchars(trim($marca . ' ' . $modelo)) . "</li>
                        <li><strong>Custo estimado:</strong> €" . number_format((float)$custo, 2, ',', ' ') . "</li>
                        <li><strong>Plano de prazo:</strong> " . htmlspecialchars($planos_sla[$plano_pronto]['label']) . "</li>
                        " . ($sla_horas > 0 ? "<li><strong>Compromisso:</strong> carro pronto em até {$sla_horas}h (com compensação em caso de atraso)</li>" : "") . "
                      </ul>
                    </div>

                    <div style='background: #e8f5e9; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4caf50;'>
                      <h3 style='color: #333; margin-top: 0;'>Próximos passos</h3>
                      <p style='color: #666; margin-bottom: 0; line-height: 1.6;'>A nossa equipa irá analisar o pedido e enviar-lhe um orçamento. O orçamento é apenas uma estimativa — o pagamento só é feito após o mecânico concluir o trabalho na oficina.</p>
                    </div>

                    <p style='color: #666; line-height: 1.6;'>A nossa equipa entrará em contacto para confirmar o horário exato da marcação.</p>
                    
                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                      <p style='color: #999; font-size: 12px;'>Obrigado por escolher a <strong>AUTOMANAGER</strong>.</p>
                    </div>
                  </div>
                </body>
                </html>";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                $headers .= "From: AUTOMANAGER <no-reply@AUTOMANAGER.pt>\r\n";

                if (@mail($email, $assunto, $mensagem, $headers)) {
                    echo "<p style='color:green;font-weight:bold;'>Serviço registado com sucesso.</p>";
                } else {
                    echo "<p style='color:orange;font-weight:bold;'>Serviço registado com sucesso.</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red;'>Erro ao registar: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            }
            }
        }
        }
    }
    }
    ?>

    <form method="POST" id="formReparacao">
        <?php if (!$utilizador_logado): ?>
            <div class="booking-inner booking-inner--locked" data-step="1">
                <div class="booking-stepper-shell" aria-label="Progresso da marcação">
                    <div class="booking-progress booking-progress--4 booking-progress--locked">
                        <div class="booking-progress-track"></div>
                        <div class="booking-progress-fill" style="width:0;"></div>
                        <span class="booking-progress-dot step-active" aria-hidden="true">1</span>
                        <span class="booking-progress-dot" aria-hidden="true">2</span>
                        <span class="booking-progress-dot" aria-hidden="true">3</span>
                        <span class="booking-progress-dot" aria-hidden="true">4</span>
                    </div>
                </div>
                <div class="booking-stepper-labels booking-stepper-labels--4" aria-hidden="true">
                    <span class="step-badge step-active">Identificação</span>
                    <span class="step-badge">Veículo</span>
                    <span class="step-badge">Serviço</span>
                    <span class="step-badge">Agendamento</span>
                </div>

                <p class="booking-locked-intro">Marque a sua reparação em 4 passos simples — rápido e sem complicações.</p>

                <div class="booking-locked-grid" role="list" aria-label="Passos da marcação">
                    <div class="booking-locked-card is-active" role="listitem">
                        <div class="booking-locked-card__icon"><i class="fas fa-user" aria-hidden="true"></i></div>
                        <strong>Identificação</strong>
                        <span>Nome, contacto e NIF</span>
                    </div>
                    <div class="booking-locked-card" role="listitem">
                        <div class="booking-locked-card__icon"><i class="fas fa-car" aria-hidden="true"></i></div>
                        <strong>Veículo</strong>
                        <span>Marca, modelo e matrícula</span>
                    </div>
                    <div class="booking-locked-card" role="listitem">
                        <div class="booking-locked-card__icon"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></div>
                        <strong>Serviço</strong>
                        <span>Tipo de intervenção</span>
                    </div>
                    <div class="booking-locked-card" role="listitem">
                        <div class="booking-locked-card__icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
                        <strong>Agendamento</strong>
                        <span>Data e hora preferidas</span>
                    </div>
                </div>

                <div class="booking-locked-cta">
                    <p class="booking-locked-cta__hint">Inicie sessão para preencher o formulário e confirmar a marcação.</p>
                    <div class="booking-locked-cta__actions">
                        <a href="login.php" class="btn-submit btn-booking-primary">Iniciar sessão</a>
                        <a href="login.php?show=register" class="btn-submit btn-secondary">Criar conta grátis</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
        <fieldset style="border:0; padding:0; margin:0;">
        <div class="booking-inner" id="bookingInner" data-step="1">
            <div class="booking-stepper-shell" aria-label="Progresso da marcação">
                <div class="booking-progress booking-progress--4">
                    <div class="booking-progress-track"></div>
                    <div id="bookingProgressFill" class="booking-progress-fill"></div>
                    <button type="button" id="stepDot1" class="booking-progress-dot step-active" data-step="1" aria-label="Passo 1: Identificação">1</button>
                    <button type="button" id="stepDot2" class="booking-progress-dot" data-step="2" aria-label="Passo 2: Veículo">2</button>
                    <button type="button" id="stepDot3" class="booking-progress-dot" data-step="3" aria-label="Passo 3: Serviço">3</button>
                    <button type="button" id="stepDot4" class="booking-progress-dot" data-step="4" aria-label="Passo 4: Agendamento">4</button>
                </div>
            </div>
            <div class="booking-stepper-labels booking-stepper-labels--4" aria-hidden="true">
                <span id="stepBadge1" class="step-badge step-active">Identificação</span>
                <span id="stepBadge2" class="step-badge">Veículo</span>
                <span id="stepBadge3" class="step-badge">Serviço</span>
                <span id="stepBadge4" class="step-badge">Agendamento</span>
            </div>

            <div id="step1Fields" class="booking-panel" role="group" aria-labelledby="stepTitle1">
                <p id="stepTitle1" class="booking-step-intro">Os seus dados de contacto para confirmarmos a marcação.</p>
                <div class="booking-panel-grid">
                    <div class="booking-field">
                        <label for="nameInput">Nome completo</label>
                        <input id="nameInput" required name="name" placeholder="Ex: João Silva" value="<?= $is_admin || $is_mecanico ? '' : htmlspecialchars($dados_utilizador_form['nome'] ?? '') ?>"<?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' readonly aria-readonly="true"' : '' ?>>
                    </div>
                    <div class="booking-field">
                        <label for="phoneInput">Telefone</label>
                        <input id="phoneInput" class="am-telemovel-input" required name="phone" placeholder="912 345 678" inputmode="numeric" maxlength="11" autocomplete="tel" value="<?= $is_admin || $is_mecanico ? '' : htmlspecialchars(am_formatar_telemovel((string) ($dados_utilizador_form['telefone'] ?? ''))) ?>"<?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' readonly aria-readonly="true"' : '' ?>>
                    </div>
                    <div class="booking-field">
                        <label for="nifInput">NIF</label>
                        <input required id="nifInput" class="am-nif-input" name="nif" placeholder="Ex: 123456789" inputmode="numeric" pattern="[0-9]{9}" minlength="9" maxlength="9" title="Introduza um NIF português válido (9 dígitos)" value="<?= $is_admin || $is_mecanico ? '' : htmlspecialchars($dados_utilizador_form['nif'] ?? '') ?>"<?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' readonly aria-readonly="true"' : '' ?>>
                    </div>
                    <div class="booking-field">
                        <label for="emailInput">Email</label>
                        <input id="emailInput" required name="email" type="email" placeholder="Ex: nome@gmail.com" value="<?= $is_admin || $is_mecanico ? '' : htmlspecialchars($dados_utilizador_form['email'] ?? '') ?>"<?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' readonly aria-readonly="true"' : '' ?>>
                    </div>
                </div>
                <p id="bookingIdentWarn" class="booking-inline-warn is-hidden" role="alert"></p>
            </div>

            <div id="step2Fields" class="booking-panel is-hidden" role="group" aria-labelledby="stepTitle2">
                <p id="stepTitle2" class="booking-step-intro">Indique o veículo. Se quiser, escolha primeiro um carro já registado para preencher tudo automaticamente.</p>
                <?php if ($utilizador_logado && count($carros_utilizador) > 0): ?>
                <div class="booking-saved-car">
                    <label for="carroSelecionado">Carro registado</label>
                    <select id="carroSelecionado" class="booking-car-select"<?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' disabled aria-disabled="true"' : '' ?>>
                        <option value="">Escolha um carro guardado (opcional)</option>
                        <?php foreach ($carros_utilizador as $carro): ?>
                            <option
                                value="<?= htmlspecialchars($carro['matricula']) ?>"
                                data-marca="<?= htmlspecialchars($carro['marca']) ?>"
                                data-modelo="<?= htmlspecialchars($carro['modelo']) ?>"
                            >
                                <?= htmlspecialchars($carro['marca'] . ' ' . $carro['modelo'] . ' · ' . $carro['matricula']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="booking-field-hint">Ao escolher um carro guardado, a marca, o modelo e a matrícula são preenchidos automaticamente.</p>
                </div>
                <?php endif; ?>
                <div class="booking-panel-grid booking-panel-grid--step2">
                    <div class="booking-field">
                        <label for="marcaInput">Marca</label>
                        <?php
                            $marca_prefill = ($utilizador_logado && count($carros_utilizador) >= 1)
                                ? (string) $carros_utilizador[0]['marca'] : '';
                            $modelo_prefill = ($utilizador_logado && count($carros_utilizador) >= 1)
                                ? (string) $carros_utilizador[0]['modelo'] : '';
                        ?>
                        <select id="marcaInput" name="marca" class="cfg-marca-select" required<?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' disabled aria-disabled="true"' : '' ?>>
                            <?php am_render_marca_options($am_marcas, $marca_prefill); ?>
                        </select>
                        <?php if ($utilizador_logado && !$is_admin && !$is_mecanico): ?>
                            <input type="hidden" name="marca" value="<?= htmlspecialchars($marca_prefill, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>
                    <div class="booking-field">
                        <label for="modeloInput">Modelo</label>
                        <select id="modeloInput" name="modelo" class="cfg-modelo-select" data-current-model="<?= htmlspecialchars($modelo_prefill) ?>" required disabled aria-disabled="true">
                            <option value="">Selecione marca...</option>
                        </select>
                        <?php if ($utilizador_logado && !$is_admin && !$is_mecanico): ?>
                            <input type="hidden" name="modelo" value="<?= htmlspecialchars($modelo_prefill, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>
                    <div class="booking-field booking-field--full">
                        <label for="plateInput">Matrícula</label>
                        <input
                            required
                            name="plate"
                            id="plateInput"
                            class="booking-plate-input"
                            placeholder="Ex: AA-00-AA"
                            maxlength="8"
                            title="Matrícula portuguesa: AA-00-AA, 00-AA-00 ou 00-00-AA"
                            value="<?= ($utilizador_logado && count($carros_utilizador) >= 1) ? htmlspecialchars($carros_utilizador[0]['matricula']) : '' ?>"
                            <?= $utilizador_logado && !$is_admin && !$is_mecanico ? ' readonly aria-readonly="true"' : '' ?>
                        >
                        <p class="booking-field-hint">Cada matrícula identifica apenas um veículo no sistema.</p>
                    </div>
                </div>
                <p id="bookingVehicleWarn" class="booking-inline-warn is-hidden" role="alert"></p>
            </div>

            <div id="step3Fields" class="booking-panel is-hidden" role="group" aria-labelledby="stepTitle3">
                <p id="stepTitle3" class="booking-step-intro">Escolha o tipo de intervenção e, se quiser, um plano de entrega mais rápido.</p>
                <div class="booking-panel-grid booking-panel-grid--step2">
                    <div class="booking-field booking-field--full">
                        <span class="booking-field-label" id="serviceLabel">Serviço pretendido</span>
                        <div class="booking-service-chips" role="group" aria-labelledby="serviceLabel">
                            <button type="button" class="booking-service-chip" data-service="Revisão geral"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i> Revisão geral</button>
                            <button type="button" class="booking-service-chip" data-service="Diagnóstico eletrónico"><i class="fas fa-microchip" aria-hidden="true"></i> Diagnóstico</button>
                            <button type="button" class="booking-service-chip" data-service="Pneus & alinhamento"><i class="fas fa-circle" aria-hidden="true"></i> Pneus</button>
                            <button type="button" class="booking-service-chip" data-service="Travões & suspensão"><i class="fas fa-compact-disc" aria-hidden="true"></i> Travões</button>
                            <button type="button" class="booking-service-chip" data-service="Bateria & elétrica"><i class="fas fa-battery-half" aria-hidden="true"></i> Bateria</button>
                            <button type="button" class="booking-service-chip" data-service="Ar condicionado & climatização"><i class="fas fa-snowflake" aria-hidden="true"></i> Ar cond.</button>
                            <button type="button" class="booking-service-chip" data-service="Óleo & filtros"><i class="fas fa-oil-can" aria-hidden="true"></i> Óleo</button>
                            <button type="button" class="booking-service-chip" data-service="Limpeza & detalhamento"><i class="fas fa-spray-can-sparkles" aria-hidden="true"></i> Limpeza</button>
                        </div>
                        <p id="bookingServiceWarn" class="booking-inline-warn is-hidden" role="alert">Selecione um serviço para continuar.</p>
                        <select id="serviceSelect" name="service" required class="booking-select-sr" aria-label="Serviço pretendido">
                            <option value="">Escolha o serviço…</option>
                            <option>Revisão geral</option>
                            <option>Diagnóstico eletrónico</option>
                            <option>Pneus & alinhamento</option>
                            <option>Travões & suspensão</option>
                            <option>Bateria & elétrica</option>
                            <option>Ar condicionado & climatização</option>
                            <option>Óleo & filtros</option>
                            <option>Limpeza & detalhamento</option>
                        </select>
                    </div>
                    <div class="booking-field booking-field--full">
                        <span class="booking-field-label" id="planLabel">Plano de entrega <span class="booking-optional">(opcional premium)</span></span>
                        <div class="booking-plan-cards" role="radiogroup" aria-labelledby="planLabel">
                            <label class="booking-plan-card is-selected">
                                <input type="radio" name="plano_pronto" value="normal" checked>
                                <span class="booking-plan-card__title">Padrão</span>
                                <span class="booking-plan-card__desc">Sem taxa extra · prazo habitual</span>
                            </label>
                            <label class="booking-plan-card">
                                <input type="radio" name="plano_pronto" value="premium_4h">
                                <span class="booking-plan-card__title">Premium 4h</span>
                                <span class="booking-plan-card__desc">Prioridade máxima · +35 €</span>
                            </label>
                            <label class="booking-plan-card">
                                <input type="radio" name="plano_pronto" value="premium_8h">
                                <span class="booking-plan-card__title">Premium 8h</span>
                                <span class="booking-plan-card__desc">Entrega rápida · +20 €</span>
                            </label>
                            <label class="booking-plan-card">
                                <input type="radio" name="plano_pronto" value="premium_24h">
                                <span class="booking-plan-card__title">Premium 24h</span>
                                <span class="booking-plan-card__desc">No dia · +10 €</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step4Fields" class="booking-panel is-hidden" role="group" aria-labelledby="stepTitle4">
                <p id="stepTitle4" class="booking-step-intro">Escolha quando prefere trazer o carro — confirmamos o horário exato por email.</p>
                <div class="booking-schedule-note">
                    <i class="fas fa-calendar-days" aria-hidden="true"></i>
                    <span>Segunda a sexta · 08:00 – 18:00 · marcações a partir de amanhã</span>
                </div>
                <div class="booking-panel-grid booking-panel-grid--step2">
                    <div class="booking-field booking-field--schedule booking-field--full">
                        <label for="dateInputText">Data preferida</label>
                        <input id="dateInputText" type="date" required
                               min="<?= htmlspecialchars(am_horarios_data_minima_marcacao(), ENT_QUOTES, 'UTF-8') ?>"
                               max="<?= date('Y-m-d', strtotime('+3 months')) ?>"
                               name="date">
                    </div>
                    <div class="booking-field booking-field--full">
                        <label>Horário disponível</label>
                        <input type="hidden" id="timeInputText" name="time" value="" required>
                        <p id="bookingSlotsHint" class="booking-field-hint">Escolha uma data para ver todos os horários do dia.</p>
                        <div id="bookingSlotsLoading" class="booking-slots-loading is-hidden" aria-live="polite">A carregar horários…</div>
                        <div id="bookingSlotsEmpty" class="booking-slots-empty is-hidden" role="alert"></div>
                        <div id="bookingSlotsLegend" class="booking-slots-legend is-hidden" aria-hidden="true">
                            <span class="booking-slots-legend__item booking-slots-legend__item--livre">Disponível</span>
                            <span class="booking-slots-legend__item booking-slots-legend__item--ocupado">Reservado</span>
                        </div>
                        <div id="bookingSlotsGrid" class="booking-slots-grid" role="listbox" aria-label="Horários do dia"></div>
                    </div>
                </div>
                <p id="bookingDateWarn" class="booking-inline-warn is-hidden" role="alert"></p>
            </div>
            <div class="booking-footer">
                <div class="booking-footer-actions">
                    <button class="btn-submit btn-secondary" id="btnVoltarPasso" type="button" style="display:none;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar</button>
                    <button class="btn-submit btn-booking-primary" id="btnContinuarPasso" type="button"><?= $utilizador_logado ? 'Continuar' : 'Iniciar sessão para continuar' ?></button>
                    <button class="btn-submit btn-booking-primary" id="btnSubmeterPasso" type="submit" style="display:none;"><i class="fas fa-check" aria-hidden="true"></i> Confirmar marcação</button>
                </div>
                <p class="booking-step-hint" id="stepHint">Passo 1 de 4 · Identificação</p>
            </div>
        </div>
        </fieldset>
        <?php endif; ?>
    </form>
</aside>

      <!-- secção "Minhas Marcações" removida do index.php -->


<script>
// Solução para manter a posição do scroll após submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formReparacao');
    
    if (form) {
        // Guardar a posição do scroll antes do submit
        form.addEventListener('submit', function() {
            sessionStorage.setItem('scrollPos', window.pageYOffset);
        });
    }
    
    // Restaurar a posição do scroll após carregamento
    if (sessionStorage.getItem('scrollPos')) {
        const scrollPosition = parseInt(sessionStorage.getItem('scrollPos'));
        setTimeout(function() {
            window.scrollTo(0, scrollPosition);
            sessionStorage.removeItem('scrollPos');
        }, 10);
    }
});

// Método alternativo usando um hash na URL
document.getElementById('formReparacao')?.addEventListener('submit', function(e) {
    // Adicionar um hash para manter a posição
    window.location.hash = 'manter-posicao';
});

// Se houver hash na URL, manter a posição
if (window.location.hash === '#manter-posicao') {
    window.location.hash = '';
    setTimeout(function() {
        const formElement = document.getElementById('book-form');
        if (formElement) {
            formElement.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
}
</script>
<script src="nif.js?v=2"></script>
<script src="telemovel.js?v=1"></script>
<script src="matricula.js?v=1"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const TOTAL_STEPS = 4;
    const stepPanels = [
        document.getElementById('step1Fields'),
        document.getElementById('step2Fields'),
        document.getElementById('step3Fields'),
        document.getElementById('step4Fields'),
    ];
    const stepBadges = [
        document.getElementById('stepBadge1'),
        document.getElementById('stepBadge2'),
        document.getElementById('stepBadge3'),
        document.getElementById('stepBadge4'),
    ];
    const stepDots = [
        document.getElementById('stepDot1'),
        document.getElementById('stepDot2'),
        document.getElementById('stepDot3'),
        document.getElementById('stepDot4'),
    ];
    const stepHints = {
        1: 'Passo 1 de 4 · Identificação',
        2: 'Passo 2 de 4 · Veículo',
        3: 'Passo 3 de 4 · Serviço',
        4: 'Passo 4 de 4 · Agendamento',
    };
    const btnContinuarPasso = document.getElementById('btnContinuarPasso');
    const btnVoltarPasso = document.getElementById('btnVoltarPasso');
    const btnSubmeterPasso = document.getElementById('btnSubmeterPasso');
    const bookingProgressFill = document.getElementById('bookingProgressFill');
    const nifInput = document.getElementById('nifInput');
    let currentStep = 1;

    function setStep(step) {
        if (step < 1 || step > TOTAL_STEPS) return;
        if (stepPanels.some((panel) => !panel)) return;

        currentStep = step;
        const bookingInner = document.getElementById('bookingInner');
        if (bookingInner) bookingInner.setAttribute('data-step', String(step));

        stepPanels.forEach((panel, index) => {
            panel.classList.toggle('is-hidden', index + 1 !== step);
        });

        const isFirst = step === 1;
        const isLast = step === TOTAL_STEPS;

        if (btnContinuarPasso) {
            btnContinuarPasso.hidden = isLast;
            btnContinuarPasso.style.display = isLast ? 'none' : 'inline-flex';
        }
        if (btnVoltarPasso) {
            btnVoltarPasso.hidden = isFirst;
            btnVoltarPasso.style.display = isFirst ? 'none' : 'inline-flex';
        }
        if (btnSubmeterPasso) {
            btnSubmeterPasso.hidden = !isLast;
            btnSubmeterPasso.style.display = isLast ? 'inline-flex' : 'none';
        }

        const stepHint = document.getElementById('stepHint');
        if (stepHint) stepHint.textContent = stepHints[step] || '';

        stepBadges.forEach((badge, index) => {
            if (badge) badge.classList.toggle('step-active', index + 1 === step);
        });

        stepDots.forEach((dot, index) => {
            if (!dot) return;
            const stepNum = index + 1;
            dot.classList.toggle('step-active', stepNum === step);
            dot.classList.toggle('step-done', stepNum < step);
            dot.disabled = stepNum > step;
            dot.setAttribute('aria-current', stepNum === step ? 'step' : 'false');
        });

        if (bookingProgressFill) {
            const pct = TOTAL_STEPS > 1 ? ((step - 1) / (TOTAL_STEPS - 1)) * 100 : 0;
            bookingProgressFill.style.width = pct + '%';
        }

        const panel = stepPanels[step - 1];
        const firstFocusable = panel && panel.querySelector('input:not([type="hidden"]), select, textarea, button.booking-service-chip');
        if (firstFocusable && typeof firstFocusable.focus === 'function') {
            setTimeout(() => firstFocusable.focus({ preventScroll: true }), 80);
        }

        const bookForm = document.getElementById('book-form');
        if (bookForm) {
            bookForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        if (step === 4) {
            const dateElStep = document.getElementById('dateInputText');
            if (dateElStep && dateElStep.value && validarDataUtil(dateElStep.value)) {
                carregarHorariosDisponiveis(dateElStep.value);
            }
        }
    }

    function validarNifCliente(nif) {
        const nifLimpo = (nif || '').replace(/\D+/g, '');
        if (nifLimpo.length !== 9) return false;
        if (/^(\d)\1{8}$/.test(nifLimpo)) return false;
        if (!/^[1235689]/.test(nifLimpo)) return false;

        let soma = 0;
        for (let i = 0; i < 8; i++) {
            soma += Number(nifLimpo[i]) * (9 - i);
        }
        let controlo = 11 - (soma % 11);
        if (controlo >= 10) controlo = 0;
        return Number(nifLimpo[8]) === controlo;
    }

    function validarPasso(step) {
        const panel = stepPanels[step - 1];
        if (!panel) return true;

        if (step === 1) {
            const identWarn = document.getElementById('bookingIdentWarn');
            const camposBloqueadosVazios = Array.from(panel.querySelectorAll('input[required][readonly], select[required][readonly], textarea[required][readonly]'))
                .filter((campo) => !campo.value.trim());
            if (camposBloqueadosVazios.length > 0) {
                if (identWarn) {
                    identWarn.textContent = 'Complete os dados da sua conta para continuar com a marcação.';
                    identWarn.classList.remove('is-hidden');
                }
                return false;
            }
            if (identWarn) identWarn.classList.add('is-hidden');
        }

        if (step === 2) {
            const vehicleWarn = document.getElementById('bookingVehicleWarn');
            const camposVeiculoBloqueadosVazios = [
                document.getElementById('marcaInput'),
                document.getElementById('modeloInput'),
                document.getElementById('plateInput'),
            ].filter((campo) => campo && ((campo.hasAttribute('readonly') || campo.disabled) && !campo.value.trim()));
            if (camposVeiculoBloqueadosVazios.length > 0) {
                if (vehicleWarn) {
                    vehicleWarn.textContent = 'Complete os dados do seu veículo para continuar com a marcação.';
                    vehicleWarn.classList.remove('is-hidden');
                }
                return false;
            }
            if (vehicleWarn) vehicleWarn.classList.add('is-hidden');
        }

        const campos = panel.querySelectorAll('input[required], select[required], textarea[required]');
        for (const campo of campos) {
            if (!campo.value.trim()) {
                campo.reportValidity();
                return false;
            }
        }

        if (step === 1 && nifInput) {
            const nifNormalizado = nifInput.value.replace(/\D+/g, '');
            nifInput.value = nifNormalizado;
            if (!validarNifCliente(nifNormalizado)) {
                nifInput.setCustomValidity('Introduza um NIF português válido.');
                nifInput.reportValidity();
                return false;
            }
            nifInput.setCustomValidity('');
        }

        if (step === 2) {
            const plateEl = document.getElementById('plateInput');
            if (plateEl && typeof validarInputMatricula === 'function' && !validarInputMatricula(plateEl)) {
                return false;
            }
        }

        if (step === 3) {
            const serviceSelect = document.getElementById('serviceSelect');
            const serviceWarn = document.getElementById('bookingServiceWarn');
            if (serviceSelect && !serviceSelect.value) {
                if (serviceWarn) serviceWarn.classList.remove('is-hidden');
                return false;
            }
            if (serviceWarn) serviceWarn.classList.add('is-hidden');
            if (serviceSelect) serviceSelect.setCustomValidity('');
        }

        if (step === 4) {
            const dateEl = document.getElementById('dateInputText');
            if (dateEl && dateEl.value && !validarDataUtil(dateEl.value)) {
                return false;
            }
            const timeEl = document.getElementById('timeInputText');
            const slotsWarn = document.getElementById('bookingDateWarn');
            if (!dateEl || !dateEl.value) {
                if (slotsWarn) {
                    slotsWarn.textContent = 'Selecione uma data para ver os horários disponíveis.';
                    slotsWarn.classList.remove('is-hidden');
                }
                return false;
            }
            if (!timeEl || !timeEl.value) {
                if (slotsWarn) {
                    slotsWarn.textContent = 'Selecione um horário disponível na lista.';
                    slotsWarn.classList.remove('is-hidden');
                }
                return false;
            }
            if (slotsWarn) slotsWarn.classList.add('is-hidden');
        }

        return true;
    }

    function dataIsoLocalHoje() {
        const n = new Date();
        const m = String(n.getMonth() + 1).padStart(2, '0');
        const d = String(n.getDate()).padStart(2, '0');
        return n.getFullYear() + '-' + m + '-' + d;
    }

    function validarDataUtil(isoDate) {
        const warn = document.getElementById('bookingDateWarn');
        if (!isoDate) {
            if (warn) warn.classList.add('is-hidden');
            return true;
        }
        const hoje = dataIsoLocalHoje();
        if (isoDate <= hoje) {
            if (warn) {
                warn.textContent = 'Não é possível marcar para o dia de hoje. Escolha a partir de amanhã.';
                warn.classList.remove('is-hidden');
            }
            const dateEl = document.getElementById('dateInputText');
            if (dateEl) {
                dateEl.setCustomValidity('Não é possível marcar para o dia de hoje.');
                dateEl.reportValidity();
            }
            return false;
        }
        const d = new Date(isoDate + 'T12:00:00');
        const day = d.getDay();
        if (day === 0 || day === 6) {
            if (warn) {
                warn.textContent = 'Não trabalhamos ao fim de semana. Escolha um dia útil (segunda a sexta).';
                warn.classList.remove('is-hidden');
            }
            const dateEl = document.getElementById('dateInputText');
            if (dateEl) {
                dateEl.setCustomValidity('Selecione um dia útil.');
                dateEl.reportValidity();
            }
            return false;
        }
        if (warn) warn.classList.add('is-hidden');
        const dateEl = document.getElementById('dateInputText');
        if (dateEl) dateEl.setCustomValidity('');
        return true;
    }

    if (nifInput) {
        nifInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D+/g, '').slice(0, 9);
            this.setCustomValidity('');
        });

        nifInput.addEventListener('blur', function() {
            if (!this.value) return;
            if (!validarNifCliente(this.value)) {
                this.setCustomValidity('Introduza um NIF português válido.');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });
    }

    const dateInputText = document.getElementById('dateInputText');
    const timeInputHidden = document.getElementById('timeInputText');
    const slotsGrid = document.getElementById('bookingSlotsGrid');
    const slotsLoading = document.getElementById('bookingSlotsLoading');
    const slotsEmpty = document.getElementById('bookingSlotsEmpty');
    const slotsHint = document.getElementById('bookingSlotsHint');
    const slotsLegend = document.getElementById('bookingSlotsLegend');
    let slotsFetchToken = 0;

    function limparSelecaoHorario() {
        if (timeInputHidden) timeInputHidden.value = '';
        if (slotsGrid) {
            slotsGrid.querySelectorAll('.booking-slot.is-selected').forEach(function (btn) {
                btn.classList.remove('is-selected');
                btn.setAttribute('aria-selected', 'false');
            });
        }
    }

    function renderBookingSlots(payload) {
        if (!slotsGrid) return;
        slotsGrid.innerHTML = '';
        limparSelecaoHorario();

        const slots = (payload && payload.slots) ? payload.slots : [];
        const disponiveis = slots.filter(function (s) { return s.disponivel; });
        const total = slots.length;

        if (total === 0) {
            if (slotsLegend) slotsLegend.classList.add('is-hidden');
            if (slotsEmpty) {
                slotsEmpty.textContent = 'Não foi possível obter os horários deste dia.';
                slotsEmpty.classList.remove('is-hidden');
            }
            if (slotsHint) slotsHint.textContent = 'Sem horários para mostrar.';
            return;
        }

        if (slotsLegend) {
            slotsLegend.classList.remove('is-hidden');
            slotsLegend.setAttribute('aria-hidden', 'false');
        }

        if (disponiveis.length === 0) {
            if (slotsEmpty) {
                slotsEmpty.textContent = 'Todos os horários estão reservados neste dia. Escolha outra data.';
                slotsEmpty.classList.remove('is-hidden');
            }
            if (slotsHint) slotsHint.textContent = 'Nenhum horário livre — os reservados aparecem a cinzento.';
        } else {
            if (slotsEmpty) slotsEmpty.classList.add('is-hidden');
            if (slotsHint) {
                slotsHint.textContent = disponiveis.length + ' de ' + total + ' horários disponíveis — selecione um horário livre.';
            }
        }

        slots.forEach(function (slot) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'booking-slot' + (slot.disponivel ? '' : ' is-ocupado');
            btn.textContent = slot.hora;
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.dataset.hora = slot.hora;

            if (!slot.disponivel) {
                btn.disabled = true;
                btn.setAttribute('aria-disabled', 'true');
                btn.setAttribute('title', 'Horário já reservado');
            } else {
                btn.addEventListener('click', function () {
                    slotsGrid.querySelectorAll('.booking-slot.is-selected').forEach(function (b) {
                        b.classList.remove('is-selected');
                        b.setAttribute('aria-selected', 'false');
                    });
                    btn.classList.add('is-selected');
                    btn.setAttribute('aria-selected', 'true');
                    if (timeInputHidden) timeInputHidden.value = slot.hora;
                    const warn = document.getElementById('bookingDateWarn');
                    if (warn) warn.classList.add('is-hidden');
                });
            }

            slotsGrid.appendChild(btn);
        });
    }

    function carregarHorariosDisponiveis(dataIso) {
        if (!dataIso || !slotsGrid) return;
        const token = ++slotsFetchToken;
        if (slotsLoading) slotsLoading.classList.remove('is-hidden');
        if (slotsEmpty) slotsEmpty.classList.add('is-hidden');
        if (slotsHint) slotsHint.textContent = 'A carregar horários…';

        fetch('api_horarios_disponiveis.php?data=' + encodeURIComponent(dataIso), {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (token !== slotsFetchToken) return;
                if (slotsLoading) slotsLoading.classList.add('is-hidden');
                if (!j || !j.ok) {
                    if (slotsEmpty) {
                        slotsEmpty.textContent = (j && j.erro) ? j.erro : 'Não foi possível carregar os horários.';
                        slotsEmpty.classList.remove('is-hidden');
                    }
                    return;
                }
                renderBookingSlots(j);
            })
            .catch(function () {
                if (token !== slotsFetchToken) return;
                if (slotsLoading) slotsLoading.classList.add('is-hidden');
                if (slotsEmpty) {
                    slotsEmpty.textContent = 'Erro ao carregar horários. Tente novamente.';
                    slotsEmpty.classList.remove('is-hidden');
                }
            });
    }

    if (dateInputText) {
        dateInputText.addEventListener('change', function() {
            const ok = validarDataUtil(this.value);
            limparSelecaoHorario();
            if (ok && this.value) {
                carregarHorariosDisponiveis(this.value);
            } else if (slotsGrid) {
                slotsGrid.innerHTML = '';
                if (slotsLegend) slotsLegend.classList.add('is-hidden');
                if (slotsHint) slotsHint.textContent = 'Escolha uma data para ver todos os horários do dia.';
            }
        });
    }

    const serviceSelect = document.getElementById('serviceSelect');
    document.querySelectorAll('.booking-service-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            const val = chip.getAttribute('data-service') || '';
            if (!serviceSelect || !val) return;
            serviceSelect.value = val;
            serviceSelect.setCustomValidity('');
            document.querySelectorAll('.booking-service-chip').forEach(function(c) {
                c.classList.toggle('is-selected', c === chip);
            });
            const serviceWarn = document.getElementById('bookingServiceWarn');
            if (serviceWarn) serviceWarn.classList.add('is-hidden');
        });
    });

    document.querySelectorAll('.booking-plan-card').forEach(function(card) {
        const radio = card.querySelector('input[type="radio"]');
        if (!radio) return;
        radio.addEventListener('change', function() {
            document.querySelectorAll('.booking-plan-card').forEach(function(c) {
                c.classList.toggle('is-selected', c.querySelector('input[type="radio"]')?.checked);
            });
        });
    });

    stepDots.forEach(function(dot) {
        if (!dot) return;
        dot.addEventListener('click', function() {
            const target = parseInt(dot.getAttribute('data-step') || '0', 10);
            if (!target || target >= currentStep) return;
            setStep(target);
        });
    });

    // NIF: só 9 números
    if (nifInput) {
        nifInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D+/g, '').slice(0, 9);
        });
    }

    // Carro guardado: preencher Marca/Modelo/Matrícula automaticamente
    const carroSelecionado = document.getElementById('carroSelecionado');
    const plateInput = document.getElementById('plateInput');
    const marcaInput = document.getElementById('marcaInput');
    const modeloInput = document.getElementById('modeloInput');
    const modelosPorMarca = <?= json_encode($am_modelos_por_marca, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function preencherModelosBooking(marcaSelect, modeloSelect, modeloAtual) {
        if (!marcaSelect || !modeloSelect) return;
        const marca = marcaSelect.value || '';
        modeloSelect.innerHTML = '';

        if (!marca || !modelosPorMarca[marca]) {
            modeloSelect.disabled = true;
            const op0 = document.createElement('option');
            op0.value = '';
            op0.textContent = 'Selecione marca...';
            modeloSelect.appendChild(op0);
            return;
        }

        modeloSelect.disabled = false;
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = 'Escolha o modelo';
        modeloSelect.appendChild(ph);

        modelosPorMarca[marca].forEach(function (modelo) {
            const op = document.createElement('option');
            op.value = modelo;
            op.textContent = modelo;
            if (modeloAtual && modeloAtual === modelo) {
                op.selected = true;
            }
            modeloSelect.appendChild(op);
        });
    }

    if (marcaInput && modeloInput) {
        const modeloAtualInicial = modeloInput.getAttribute('data-current-model') || '';
        preencherModelosBooking(marcaInput, modeloInput, modeloAtualInicial);
        marcaInput.addEventListener('change', function () {
            preencherModelosBooking(marcaInput, modeloInput, '');
        });
    }

    function limparSelecaoCarroGuardadoSeNecessario() {
        if (!carroSelecionado) return;
        const opt = carroSelecionado.options[carroSelecionado.selectedIndex];
        if (!opt || !opt.value) return;

        const mat = (plateInput?.value || '').trim();
        const marca = marcaInput?.value || '';
        const modelo = modeloInput?.value || '';
        const mesmaMatricula = mat === (opt.value || '');
        const mesmaMarca = marca === (opt.getAttribute('data-marca') || '');
        const mesmoModelo = modelo === (opt.getAttribute('data-modelo') || '');

        if (!mesmaMatricula || !mesmaMarca || !mesmoModelo) {
            carroSelecionado.value = '';
        }
    }

    function aplicarCarroSelecionado() {
        if (!carroSelecionado) return;
        const opt = carroSelecionado.options[carroSelecionado.selectedIndex];
        if (!opt) return;

        const mat = opt.value || '';
        const marca = opt.getAttribute('data-marca') || '';
        const modelo = opt.getAttribute('data-modelo') || '';

        if (plateInput && mat) plateInput.value = mat;
        if (marcaInput && marca) {
            marcaInput.value = marca;
            preencherModelosBooking(marcaInput, modeloInput, modelo);
        }
    }

    if (carroSelecionado) {
        carroSelecionado.addEventListener('change', aplicarCarroSelecionado);
        // Se houver carro pré-preenchido (mais recente), selecionar automaticamente no select
        if (plateInput && plateInput.value) {
            const match = Array.from(carroSelecionado.options).find(o => o.value === plateInput.value);
            if (match) {
                carroSelecionado.value = plateInput.value;
                aplicarCarroSelecionado();
            }
        }
    }

    [plateInput, marcaInput, modeloInput].forEach(function(field) {
        if (!field) return;
        field.addEventListener('change', limparSelecaoCarroGuardadoSeNecessario);
        field.addEventListener('input', limparSelecaoCarroGuardadoSeNecessario);
    });

    if (btnContinuarPasso) {
        btnContinuarPasso.addEventListener('click', function() {
            if (validarPasso(currentStep) && currentStep < TOTAL_STEPS) {
                setStep(currentStep + 1);
            }
        });
    }

    if (btnVoltarPasso) {
        btnVoltarPasso.addEventListener('click', function() {
            if (currentStep > 1) {
                setStep(currentStep - 1);
            }
        });
    }

    const formReparacao = document.getElementById('formReparacao');
    if (formReparacao) {
        formReparacao.addEventListener('submit', function(e) {
            if (currentStep !== TOTAL_STEPS) {
                e.preventDefault();
                return;
            }
            if (!validarPasso(TOTAL_STEPS)) {
                e.preventDefault();
            }
        });
    }

    setStep(1);

});
</script>
    </div>
  </div>
</section>

<section class="section" id="contact">
  <div class="container contact-grid-section">
    <div class="contact-cards">
      <div class="contact-card">
        <span class="contact-icon"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
        <h4>Morada</h4>
        <p>Avenida Doutor Teófilo Carvalho dos Santos, Alenquer</p>
      </div>
      <div class="contact-card">
        <span class="contact-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
        <h4>Telefone</h4>
        <p>912 964 022</p>
      </div>
      <div class="contact-card">
        <span class="contact-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
        <h4>Email</h4>
        <p>automanagergeral@gmail.com</p>
      </div>
      <div class="contact-card">
        <span class="contact-icon"><i class="fas fa-clock" aria-hidden="true"></i></span>
        <h4>Horário</h4>
        <p>Seg-Sex 08:00 - 18:00</p>
      </div>
    </div>
  </div>
</section>

<section class="section" id="suporte">
  <div class="container">
    <h2>Suporte Online</h2>
    <div class="cards">
      <div class="card">
          <div class="icon"><i class="fas fa-circle-question" aria-hidden="true"></i></div>
          <h4>Ver FAQs</h4>
          <p>Explore perguntas frequentes sobre serviços, preços e mais.</p>
           <button class="small-btn" onclick="window.location.href='faq.php'">Abrir FAQs</button>
      </div>
      
      <div class="card">
          <div class="icon"><i class="fas fa-phone" aria-hidden="true"></i></div>
          <h4>Telefone Direto</h4>
          <p>Ligue para nossa linha de apoio ao cliente durante o horário comercial.</p>
          <button class="small-btn" onclick="window.location.href='tel:+351912964022'">912 964 022</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-envelope" aria-hidden="true"></i></div>
          <h4>Email de Suporte</h4>
          <p>Envie-nos um email detalhado. Respondemos em até 24 horas úteis.</p>
          <button class="small-btn" onclick="window.location.href='mailto:automanagergeral@gmail.com'">automanagergeral@gmail.com</button>
      </div>
      <div class="card">
          <div class="icon"><i class="fas fa-location-dot" aria-hidden="true"></i></div>
          <h4>Morada</h4>
          <p>Avenida Doutor Teófilo Carvalho dos Santos, Alenquer.</p>
          <button class="small-btn" type="button" onclick="window.open('https://www.google.com/maps/search/?api=1&amp;query=Avenida+Doutor+Te%C3%B3filo+Carvalho+dos+Santos,+Alenquer','_blank','noopener')">Abrir no Maps</button>
      </div>
    </div>
  </div>
</section>

<footer class="am-footer">
  <div class="container am-footer-grid">
    <div class="am-footer-col">
      <h3>AutoManager<span>.</span></h3>
      <p>Oficina automóvel com foco em rapidez, transparência e qualidade no serviço.</p>
    </div>

    <div class="am-footer-col">
      <h4>Navegação</h4>
      <ul>
        <li><a href="#home">Início</a></li>
        <li><a href="#services">Serviços</a></li>
        <li><a href="#book-form">Marcar Reparação</a></li>
        <li><a href="#about">Sobre</a></li>
        <li><a href="#contact">Contactos</a></li>
      </ul>
    </div>

    <div class="am-footer-col">
      <h4>Contactos</h4>
      <p>912 964 022</p>
      <p>automanagergeral@gmail.com</p>
      <p>Seg-Sex 08:00 - 18:00</p>
    </div>
  </div>
  <div class="am-footer-bottom">
    <p>© <?= date('Y') ?> AutoManager. Todos os direitos reservados.</p>
  </div>
</footer>

<script src="script.js"></script>

<!-- filtro de "Minhas Marcações" removido do index.php -->
<script>
// Timer para visitantes temporários
document.addEventListener('DOMContentLoaded', function() {
    const timerDiv = document.getElementById('temp-timer');
    const timerDisplay = document.getElementById('timer-display');
    
    if (!timerDiv) return; // Se não existir o timer, sair
    
    let tempoRestante = 120; // 2 minutos
    let timerInterval;

    // Mostrar timer logo
    timerDiv.style.display = 'flex';
    
    // Função para atualizar o display do timer
    function atualizarDisplay() {
        const minutos = Math.floor(tempoRestante / 60);
        const segundos = tempoRestante % 60;
        if (timerDisplay) {
            timerDisplay.textContent = minutos + ':' + (segundos < 10 ? '0' : '') + segundos;
        }
        
        // Mudar cor conforme o tempo
        if (tempoRestante <= 30) {
            timerDiv.style.background = 'linear-gradient(135deg, rgba(255,0,0,0.95), rgba(200,0,0,0.95))';
            timerDiv.style.animation = 'pulse 0.5s infinite';
        } else if (tempoRestante <= 60) {
            timerDiv.style.background = 'linear-gradient(135deg, rgba(255,100,0,0.95), rgba(255,0,0,0.95))';
            timerDiv.style.animation = 'none';
        }
    }

    // Verificar tempo a cada segundo via AJAX
    function verificarTempo() {
        fetch('verificar_tempo.php')
            .then(response => response.json())
            .then(data => {
                if (data.expired) {
                    // Tempo expirou
                    if (timerInterval) clearInterval(timerInterval);
                    timerDiv.style.display = 'none';
                    
                    if (data.mostrar_popup) {
                        mostrarPopupAvaliacao();
                    } else {
                        window.location.href = 'login.php?expired=1';
                    }
                } else {
                    // Atualizar tempo do servidor
                    tempoRestante = data.tempo_restante;
                    atualizarDisplay();
                }
            })
            .catch(error => {
                // Se der erro, decrementar localmente
                tempoRestante--;
                if (tempoRestante < 0) tempoRestante = 0;
                atualizarDisplay();
            });
    }

    // Função para mostrar popup de avaliação
    function mostrarPopupAvaliacao() {
        const popupHTML = `
            <div id="avaliacao-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                <div id="avaliacao-popup" style="background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%); border-radius: 20px; padding: 40px; max-width: 500px; width: 90%; border: 1px solid rgba(255,69,0,0.3); box-shadow: 0 20px 60px rgba(255,69,0,0.3); position: relative; animation: popupAppear 0.5s ease-out; max-height: 90vh; overflow-y: auto;">
                    <button onclick="document.getElementById('avaliacao-popup-container').style.display='none'; window.location.href='login.php';" style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: rgba(255,255,255,0.5); font-size: 24px; cursor: pointer;">&times;</button>
                    
                    <div style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-clock" style="font-size: 50px; color: #ff4500;"></i>
                        <h2 style="color: white;"><i class="fas fa-clock" aria-hidden="true"></i> O tempo terminou!</h2>
                        <p style="color: #ccc;">Ajude-nos a melhorar avaliando a sua experiência:</p>
                    </div>
                    
                    <form id="avaliacaoForm">
                        <div style="margin-bottom: 20px;">
                            <label style="color: white;">Avaliação:</label>
                            <div style="display: flex; gap: 10px; justify-content: center; font-size: 30px; margin-top: 10px;">
                                <span class="star" data-value="1" style="cursor: pointer;">-</span>
                                <span class="star" data-value="2" style="cursor: pointer;">-</span>
                                <span class="star" data-value="3" style="cursor: pointer;">-</span>
                                <span class="star" data-value="4" style="cursor: pointer;">-</span>
                                <span class="star" data-value="5" style="cursor: pointer;">-</span>
                            </div>
                            <input type="hidden" name="avaliacao" id="avaliacao_valor" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <input type="text" name="nome" placeholder="Seu nome" required style="width: 100%; padding: 10px; background: #333; border: 1px solid #ff4500; border-radius: 5px; color: white;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <input type="email" name="email" placeholder="Seu email" required style="width: 100%; padding: 10px; background: #333; border: 1px solid #ff4500; border-radius: 5px; color: white;">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <textarea name="comentario" placeholder="Comentário (opcional)" rows="3" style="width: 100%; padding: 10px; background: #333; border: 1px solid #ff4500; border-radius: 5px; color: white;"></textarea>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 12px; background: #ff4500; border: none; border-radius: 5px; color: white; font-weight: bold; cursor: pointer;">Enviar Avaliação</button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="login.php" style="color: #ff4500; text-decoration: none;">Ir para login →</a>
                    </div>
                </div>
            </div>
        `;
        
        let container = document.getElementById('avaliacao-popup-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'avaliacao-popup-container';
            document.body.appendChild(container);
        }
        
        container.innerHTML = popupHTML;
        container.style.display = 'block';
        
        // Sistema de estrelas
        const stars = document.querySelectorAll('.star');
        const avaliacaoInput = document.getElementById('avaliacao_valor');
        
        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const value = this.dataset.value;
                stars.forEach((s, index) => {
                    s.innerHTML = index < value ? '*' : '-';
                    s.style.color = '#ff4500';
                });
            });
            
            star.addEventListener('mouseout', function() {
                const valorAtual = avaliacaoInput.value;
                stars.forEach((s, index) => {
                    s.innerHTML = index < valorAtual ? '*' : '-';
                });
            });
            
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                avaliacaoInput.value = value;
                stars.forEach((s, index) => {
                    s.innerHTML = index < value ? '*' : '-';
                });
            });
        });
        
        // Submeter formulário
        document.getElementById('avaliacaoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('avaliacao_popup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Avaliação registada! Obrigado.');
                    window.location.href = 'login.php';
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao enviar. Tente novamente.');
            });
        });
    }

    // Inicializar display
    atualizarDisplay();
    
    // Verificar tempo a cada segundo
    verificarTempo();
    timerInterval = setInterval(verificarTempo, 1000);
    
    // Backup: decrementar localmente a cada segundo (em caso de problemas com AJAX)
    setInterval(function() {
        if (tempoRestante > 0) {
            tempoRestante--;
        }
    }, 1000);
});

// Funções para o AI
function abrirAutoAssistAI() {
    <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['id_utilizador'])): ?>
        fetch('ai-assistant/ai-chat.php')
            .then(response => {
                if (response.ok) {
                    document.getElementById('aiModal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                    document.getElementById('aiFrame').src = 'ai-assistant/ai-chat.php';
                } else {
                    window.open('ai-assistant/ai-chat.php', '_blank', 'width=900,height=700');
                }
            })
            .catch(error => {
                window.open('ai-assistant/ai-chat.php', '_blank', 'width=900,height=700');
            });
    <?php else: ?>
        alert('Por favor, inicie sessão para usar o Mecânico Virtual AI.');
        window.location.href = 'login.php';
    <?php endif; ?>
}

function fecharAI() {
    document.getElementById('aiModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('aiFrame').src = '';
}

// Fechar com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharAI();
    }
});

// Fechar ao clicar fora
document.getElementById('aiModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharAI();
    }
});

// Sistema de estrelas para avaliação
<?php if ($mostrar_popup && !$ja_avaliou): ?>
const stars = document.querySelectorAll('.star');
const avaliacaoInput = document.getElementById('avaliacao_valor');

if (stars.length > 0) {
    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const value = this.dataset.value;
            stars.forEach((s, index) => {
                if (index < value) {
                    s.innerHTML = '*';
                } else {
                    s.innerHTML = '-';
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            const valorAtual = avaliacaoInput.value;
            stars.forEach((s, index) => {
                if (index < valorAtual) {
                    s.innerHTML = '*';
                } else {
                    s.innerHTML = '-';
                }
            });
        });
        
        star.addEventListener('click', function() {
            const value = this.dataset.value;
            avaliacaoInput.value = value;
            stars.forEach((s, index) => {
                if (index < value) {
                    s.innerHTML = '*';
                } else {
                    s.innerHTML = '-';
                }
            });
        });
    });
}

// Submeter formulário de avaliação
document.getElementById('avaliacaoForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!avaliacaoInput.value) {
        alert('Por favor, selecione uma avaliação.');
        return;
    }
    
    const formData = new FormData(this);
    
    fetch('avaliacao_popup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Avaliação registada com sucesso! Obrigado pelo seu feedback.');
            window.location.href = 'login.php';
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao enviar avaliação. Tente novamente.');
        console.error('Error:', error);
    });
});
<?php endif; ?>

function fecharPopupAgora() {
    document.getElementById('avaliacao-overlay').style.display = 'none';
    window.location.href = 'login.php';
}

// Solução para manter a posição do scroll após submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formReparacao');
    
    if (form) {
        form.addEventListener('submit', function() {
            sessionStorage.setItem('scrollPos', window.pageYOffset);
        });
    }
    
    if (sessionStorage.getItem('scrollPos')) {
        const scrollPosition = parseInt(sessionStorage.getItem('scrollPos'));
        setTimeout(function() {
            window.scrollTo(0, scrollPosition);
            sessionStorage.removeItem('scrollPos');
        }, 10);
    }

    <?php if (!empty($client_notif_uid)): ?>
    (function () {
        var LS = 'user_pulse_ack_' + <?= (int) $client_notif_uid ?>;
        var userId = <?= (int) $client_notif_uid ?>;
        var initialPulse = <?= json_encode($client_notif_pulse, JSON_UNESCAPED_UNICODE) ?>;
        var prevPoll = initialPulse;
        var pollCount = 0;
        var pill = document.getElementById('indexUserNotifPill');
        var btn = document.getElementById('indexUserNotifBtn');
        var initialSections = <?= json_encode($client_notif_sections, JSON_UNESCAPED_UNICODE) ?>;
        var initialSectionPulses = <?= json_encode($client_notif_section_pulses, JSON_UNESCAPED_UNICODE) ?>;

        function sectionStorageKey(sec) {
            return 'cfg_notif_ack_' + userId + '_' + sec;
        }

        function visibleCountFromPayload(payload) {
            if (!payload) return 0;
            var sections = payload.sections || {};
            var sectionPulses = payload.section_pulses || {};
            var visibleDados = parseInt(sections.dados || 0, 10) || 0;
            var visibleVeiculos = parseInt(sections.veiculos || 0, 10) || 0;
            var visibleMarcacoes = parseInt(sections.marcacoes || 0, 10) || 0;
            try {
                if ((sectionPulses.dados || '') !== '' && localStorage.getItem(sectionStorageKey('dados')) === sectionPulses.dados) {
                    visibleDados = 0;
                }
                if ((sectionPulses.veiculos || '') !== '' && localStorage.getItem(sectionStorageKey('veiculos')) === sectionPulses.veiculos) {
                    visibleVeiculos = 0;
                }
                if ((sectionPulses.marcacoes || '') !== '' && localStorage.getItem(sectionStorageKey('marcacoes')) === sectionPulses.marcacoes) {
                    visibleMarcacoes = 0;
                }
            } catch (e) {}
            return visibleDados + visibleVeiculos + visibleMarcacoes;
        }

        function tooltipNotif(count) {
            var c = parseInt(count, 10) || 0;
            if (c <= 0) return 'Sem notificações por ver';
            if (c === 1) return '1 notificação por ver';
            return String(c) + ' notificações por ver';
        }

        function applyPulse(pulse, count) {
            var c = parseInt(count, 10) || 0;
            if (btn) {
                var txt = tooltipNotif(c);
                btn.title = txt;
                btn.setAttribute('aria-label', txt);
                btn.classList.toggle('has-notif', c > 0);
                btn.onclick = function () {
                    window.location.href = c > 0 ? 'configuracoes.php?seccao=marcacoes' : 'configuracoes.php';
                };
                var icon = btn.querySelector('.btn-user-icon i');
                if (icon) {
                    icon.className = c > 0 ? 'fas fa-bell' : 'fas fa-user';
                }
            }
            if (!pill) return;
            if (c === 0) {
                try { localStorage.setItem(LS, pulse); } catch (e) {}
                pill.classList.remove('is-on');
                pill.textContent = '0';
                return;
            }
            pill.classList.add('is-on');
            pill.textContent = String(c > 99 ? '99+' : c);
        }
        applyPulse(initialPulse, <?= (int) $client_notif_count ?>);

        function poll() {
            fetch('utilizador_pulse.php', { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) return;
                    pollCount += 1;
                    var mudou = pollCount > 1 && j.pulse !== prevPoll;
                    if (mudou && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                        try {
                            new Notification('AUTOMANAGER', { body: 'Tens uma nova ação nas tuas marcações (orçamento, pagamento ou veículo pronto).', tag: 'user-pulse' });
                        } catch (e) {}
                    }
                    prevPoll = j.pulse;
                    applyPulse(j.pulse, j.count);
                })
                .catch(function () {});
        }
        if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
            Notification.requestPermission().catch(function () {});
        }
        setInterval(poll, 28000);
        setTimeout(poll, 5000);
    })();
    <?php endif; ?>
});
</script>
</body>
</html>