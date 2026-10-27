<?php
session_start();

// Verificar conexão antes de usar
if (!isset($con)) {
    require 'ligar.php';
}

$is_admin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    $is_admin = true;
}

$is_mecanico = false;
if (isset($_SESSION['mecanico_id'])) {
    $is_mecanico = true;
}

$hdr_user_pulse = '';
$notificacoes_pendentes = 0;
$notificacoes_raw = 0;
$hdr_sections = ['dados' => 0, 'veiculos' => 0, 'marcacoes' => 0];
$hdr_section_pulses = ['dados' => '', 'veiculos' => '', 'marcacoes' => ''];
$id_utilizador_notif = 0;
$utilizador_logado = isset($_SESSION['user']) && !empty($_SESSION['user']['id_utilizador']);

if ($utilizador_logado && isset($con)) {
    require_once __DIR__ . '/inc_utilizador_notificacoes.php';
    $id_utilizador_notif = (int) $_SESSION['user']['id_utilizador'];
    $m = am_utilizador_notificacoes_metrics($con, $id_utilizador_notif);
    $notificacoes_pendentes = $m['count'];
    $notificacoes_raw = (int) ($m['raw_count'] ?? $m['count']);
    $hdr_user_pulse = $m['pulse'];
    $hdr_sections = is_array($m['sections'] ?? null) ? $m['sections'] : $hdr_sections;
    $hdr_section_pulses = is_array($m['section_pulses'] ?? null) ? $m['section_pulses'] : $hdr_section_pulses;
}

?>

<header align="left">
  <div class="container nav">
    <div class="brand">
        <img src="automanagerlogowhite.png" alt="AUTOMANAGER Logo" class="logo" >
    </div>
    <button class="nav-toggle" id="navToggle" style="display: none; background: transparent; border: 1px solid rgba(255,255,255,0.2); color: white; padding: 10px; border-radius: 5px; cursor: pointer;">
        <i class="fas fa-bars"></i>
    </button>
    <nav id="navbar">
      <ul>
        <li><a href="#home">Início</a></li>
        <li><a href="#services">Serviços</a></li>
        <li><a href="#about">Sobre</a></li>
        <li><a href="#contact">Contactos</a></li>
        <li><a href="#suporte">Suporte</a></li>
      </ul>
    </nav>
   <div class="nav-actions">
    <?php if($is_admin): ?>
        <button class="btn-ghost" onclick="window.location.href='indexadmin.php'">Administração</button>
    <?php elseif($is_mecanico): ?>
        <button class="btn-ghost" onclick="window.location.href='funcionarios.php'">Área Funcionário</button>
    <?php endif; ?>
    
    <?php if($is_admin || $is_mecanico || $utilizador_logado): ?>
        <button
            class="btn-ghost <?= $utilizador_logado ? 'btn-user-notif' : '' ?><?= $utilizador_logado && $notificacoes_pendentes > 0 ? ' has-notif' : '' ?>"
            onclick="window.location.href='configuracoes.php<?= $utilizador_logado && $notificacoes_pendentes > 0 ? '?seccao=marcacoes' : '' ?>'"
            <?= $utilizador_logado ? 'aria-label="' . ($notificacoes_pendentes > 0 ? ($notificacoes_pendentes === 1 ? '1 notificação por ver' : (int)$notificacoes_pendentes . ' notificações por ver') : 'Configurações e perfil') . '"' : '' ?>
            <?= $utilizador_logado ? 'title="' . ($notificacoes_pendentes > 0 ? ($notificacoes_pendentes === 1 ? '1 notificação por ver' : (int)$notificacoes_pendentes . ' notificações por ver') : 'Configurações e perfil') . '"' : '' ?>
            <?= $utilizador_logado ? 'id="hdrUserNotifBtn"' : '' ?>>
            <?php if ($utilizador_logado): ?>
                <span class="btn-user-icon"><i class="fa-solid <?= $notificacoes_pendentes > 0 ? 'fa-bell' : 'fa-user' ?>" aria-hidden="true"></i></span>
                <span class="notif-pill<?= $notificacoes_pendentes > 0 ? ' is-on' : '' ?>" id="hdrUserNotifPill"><?= $notificacoes_pendentes > 99 ? '99+' : (int) $notificacoes_pendentes ?></span>
            <?php else: ?>
                Configurações
            <?php endif; ?>
        </button>
        <button class="btn-ghost" onclick="window.location.href='logout.php'">Sair</button>
    <?php else: ?>
        <button class="btn-ghost" onclick="window.location.href='login.php'">Iniciar Sessão</button>
        <button class="btn-ghost" onclick="window.location.href='login.php?show=register'">Criar Conta</button>
    <?php endif; ?>
</div>
  </div>
</header>

<style>
@import url('notificacoes.css?v=2');
@media (max-width: 900px) {
    .btn-user-notif {
        width: 90%;
        min-width: 0;
        justify-content: center;
    }
}
</style>

<script>
document.getElementById('navToggle').addEventListener('click', function() {
    const nav = document.getElementById('navbar');
    const actions = document.querySelector('.nav-actions');
    nav.classList.toggle('active');
    if (nav.classList.contains('active')) {
        nav.style.display = 'block';
        actions.style.display = 'flex';
    } else {
        nav.style.display = 'none';
        actions.style.display = 'none';
    }
});

// Reset display on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 900) {
        document.getElementById('navbar').style.display = 'block';
        document.querySelector('.nav-actions').style.display = 'flex';
    } else {
        if (!document.getElementById('navbar').classList.contains('active')) {
            document.getElementById('navbar').style.display = 'none';
            document.querySelector('.nav-actions').style.display = 'none';
        }
    }
});
</script>
<?php if ($utilizador_logado): ?>
<script>
(function () {
    var LS = 'user_pulse_ack_' + <?= (int)($id_utilizador_notif ?? 0) ?>;
    var userId = <?= (int)($id_utilizador_notif ?? 0) ?>;
    var initialPulse = <?= json_encode($hdr_user_pulse, JSON_UNESCAPED_UNICODE) ?>;
    var prevPoll = initialPulse;
    var pollCount = 0;
    var pill = document.getElementById('hdrUserNotifPill');
    var btn = document.getElementById('hdrUserNotifBtn');
    var initialSections = <?= json_encode($hdr_sections, JSON_UNESCAPED_UNICODE) ?>;
    var initialSectionPulses = <?= json_encode($hdr_section_pulses, JSON_UNESCAPED_UNICODE) ?>;

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
                icon.className = c > 0 ? 'fa-solid fa-bell' : 'fa-solid fa-user';
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
    applyPulse(initialPulse, <?= (int) $notificacoes_pendentes ?>);

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
</script>
<?php endif; ?>
