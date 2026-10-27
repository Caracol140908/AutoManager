<style>
.am-footer {
  margin-top: 28px;
  background: linear-gradient(180deg, #0a0a0a, #000000);
  border-top: 1px solid rgba(255,255,255,.08);
}
.am-footer-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr;
  gap: 26px;
  padding: 36px 24px 24px;
}
.am-footer-col h3 {
  margin: 0 0 10px;
  font-size: 42px;
  line-height: 1;
  letter-spacing: -.02em;
  color: #fafafa;
}
.am-footer-col h3 span { color: #ff6a00; }
.am-footer-col h4 {
  margin: 0 0 10px;
  color: #ff6a00;
  font-size: 22px;
}
.am-footer-col p {
  margin: 0 0 8px;
  color: #a3a3a3;
}
.am-footer-col ul {
  list-style: none;
  margin: 0;
  padding: 0;
}
.am-footer-col li { margin-bottom: 7px; }
.am-footer-col a {
  color: #e4e4e7;
  text-decoration: none;
  transition: color .2s ease;
}
.am-footer-col a:hover { color: #ff6a00; }
.am-footer-bottom {
  text-align: center;
  border-top: 1px solid rgba(255,255,255,.08);
  padding: 14px 12px 20px;
}
.am-footer-bottom p {
  margin: 0;
  color: #737373;
}
@media (max-width: 900px) {
  .am-footer-grid {
    grid-template-columns: 1fr;
    padding: 28px 16px 18px;
    gap: 16px;
  }
  .am-footer-col h3 { font-size: 34px; }
  .am-footer-col h4 { font-size: 18px; }
}
</style>

<footer class="am-footer">
  <div class="container am-footer-grid">
    <div class="am-footer-col">
      <h3>AutoManager<span>.</span></h3>
      <p>Oficina automóvel com foco em rapidez, transparência e qualidade no serviço.</p>
    </div>

    <div class="am-footer-col">
      <h4>Navegação</h4>
      <ul>
        <li><a href="index.php#home">Início</a></li>
        <li><a href="index.php#services">Serviços</a></li>
        <li><a href="index.php#book-form">Marcar Reparação</a></li>
        <li><a href="index.php#about">Sobre</a></li>
        <li><a href="index.php#contact">Contactos</a></li>
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

<?php if (!defined('AM_HIDE_FLOATING_CLOCK')): ?>
<!-- Botão Flutuante do Relógio -->
<div id="floating-clock-btn" style="position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: linear-gradient(135deg, #ff4500, #ff6b00); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 15px rgba(255,69,0,0.4); z-index: 999; transition: all 0.3s ease; font-size: 1.8em; border: none; color: white; hover-state: scale;">
    <i class="fas fa-clock"></i>
</div>

<!-- Modal do Relógio -->
<div id="clock-modal" style="display: none; position: fixed; bottom: 110px; right: 30px; background: linear-gradient(135deg, rgba(255,69,0,0.95), rgba(255,107,0,0.95)); color: white; padding: 20px 30px; border-radius: 15px; box-shadow: 0 8px 25px rgba(255,69,0,0.5); z-index: 1000; font-family: 'Courier New', monospace; border: 1px solid rgba(255,255,255,0.2); min-width: 280px; backdrop-filter: blur(10px);">
    <div style="text-align: center;">
        <h3 style="margin: 0 0 15px 0; font-size: 1.1em; opacity: 0.9;">Hora Atual</h3>
        <div id="clock-display" style="font-size: 2.5em; font-weight: bold; margin-bottom: 10px; letter-spacing: 2px;">00:00:00</div>
        <div id="clock-date" style="font-size: 0.95em; opacity: 0.9; margin-bottom: 15px;">--/--/----</div>
        <button onclick="document.getElementById('clock-modal').style.display='none'" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 8px 15px; border-radius: 20px; cursor: pointer; font-size: 0.9em; transition: all 0.3s;">
            Fechar
        </button>
    </div>
</div>

<style>
    #floating-clock-btn {
        animation: float-bounce 3s ease-in-out infinite;
        outline: none;
    }
    
    #floating-clock-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(255,69,0,0.6);
    }
    
    #floating-clock-btn:active {
        transform: scale(0.95);
    }
    
    @keyframes float-bounce {
        0%, 100% {
            transform: translateY(0px);
        }
        50% {
            transform: translateY(-10px);
        }
    }
    
    #clock-modal {
        animation: slideUpIn 0.3s ease-out;
    }
    
    @keyframes slideUpIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 768px) {
        #floating-clock-btn {
            width: 50px;
            height: 50px;
            bottom: 20px;
            right: 20px;
            font-size: 1.5em;
        }
        
        #clock-modal {
            bottom: 80px;
            right: 20px;
            min-width: 250px;
            padding: 15px 20px;
        }
        
        #clock-modal h3 {
            font-size: 1em;
        }
        
        #clock-display {
            font-size: 2em;
        }
        
        #expired-modal div {
            max-width: 90%;
            padding: 30px 20px;
        }
    }
</style>

<script>
    // Configurar botão do relógio
    const floatingClockBtn = document.getElementById('floating-clock-btn');
    const clockModal = document.getElementById('clock-modal');
    const clockDisplay = document.getElementById('clock-display');
    const clockDate = document.getElementById('clock-date');
    
    // Toggle do modal
    floatingClockBtn.addEventListener('click', function() {
        if (clockModal.style.display === 'none') {
            clockModal.style.display = 'block';
            atualizarRelogio(); // Atualizar imediatamente
        } else {
            clockModal.style.display = 'none';
        }
    });
    
    // Atualizar relógio cada segundo
    function atualizarRelogio() {
        const agora = new Date();
        
        // Formato de hora: HH:MM:SS
        const horas = String(agora.getHours()).padStart(2, '0');
        const minutos = String(agora.getMinutes()).padStart(2, '0');
        const segundos = String(agora.getSeconds()).padStart(2, '0');
        clockDisplay.textContent = horas + ':' + minutos + ':' + segundos;
        
        // Formato de data: DD/MM/YYYY
        const dia = String(agora.getDate()).padStart(2, '0');
        const mes = String(agora.getMonth() + 1).padStart(2, '0');
        const ano = agora.getFullYear();
        clockDate.textContent = dia + '/' + mes + '/' + ano;
    }
    
    // Atualizar relógio quando modal está visível
    setInterval(function() {
        if (clockModal.style.display === 'block') {
            atualizarRelogio();
        }
    }, 1000);
    
    // Fechar modal se clicar fora dele
    document.addEventListener('click', function(event) {
        if (!event.target.closest('#floating-clock-btn') && !event.target.closest('#clock-modal')) {
            clockModal.style.display = 'none';
        }
    });
</script>
<?php endif; ?>