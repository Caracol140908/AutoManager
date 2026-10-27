// indexchefedeoficina.js - JavaScript para Painel do Chefe

document.addEventListener('DOMContentLoaded', function() {
    console.log('Painel do Chefe carregado com sucesso!');
    
    // Configurações iniciais
    initDashboard();
    initEventListeners();
    startLiveUpdates();
});

// Inicializar dashboard
function initDashboard() {
    // Atualizar hora em tempo real
    updateClock();
    setInterval(updateClock, 1000);
    
    // Verificar notificações
    checkNotifications();
    
    // Carregar mecânicos no modal
    loadMecanicosForModal();
}

// Atualizar relógio no footer
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('pt-PT', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const clockElements = document.querySelectorAll('.footer-info i.bi-clock');
    if (clockElements.length > 0) {
        clockElements.forEach(el => {
            const parent = el.parentElement;
            parent.textContent = timeString;
            parent.prepend(el.cloneNode(true));
        });
    }
}

// Verificar notificações
function checkNotifications() {
    // Simular notificações
    setTimeout(() => {
        showNotification('Nova mensagem recebida', 'info');
    }, 5000);
}

// Mostrar notificação
function showNotification(message, type = 'info') {
    // Implementar sistema de notificações
    console.log('Notificação:', message, type);
}

// Inicializar event listeners
function initEventListeners() {
    // Formulário de serviço
    const servicoForm = document.getElementById('servicoForm');
    if (servicoForm) {
        servicoForm.addEventListener('submit', handleServicoSubmit);
    }
    
    // Botões de ação
    document.querySelectorAll('.btn-action').forEach(btn => {
        btn.addEventListener('click', handleActionClick);
    });
    
    // Cards de estatísticas
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', handleStatCardClick);
    });
    
    // Modal de mecânicos
    const modal = document.getElementById('mecanicosModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
    }
}

// Manipular submit do formulário de serviço
function handleServicoSubmit(e) {
    e.preventDefault();
    
    const mecanicoId = document.getElementById('mecanicoId').value;
    const tipoServico = document.getElementById('tipoServico').value;
    const descricao = document.getElementById('descricaoServico').value;
    
    if (!mecanicoId) {
        showAlert('Por favor, selecione um mecânico.', 'error');
        abrirSelecaoMecanicos();
        return;
    }
    
    if (!tipoServico) {
        showAlert('Por favor, selecione o tipo de serviço.', 'error');
        return;
    }
    
    if (!descricao.trim()) {
        showAlert('Por favor, descreva o serviço.', 'error');
        return;
    }
    
    // Mostrar loading
    showLoading(true);
    
    // Simular envio
    setTimeout(() => {
        showLoading(false);
        showAlert('Serviço atribuído com sucesso!', 'success');
        servicoForm.reset();
        document.getElementById('mecanicoSelecionado').textContent = 'Clique para selecionar um mecânico';
        document.getElementById('mecanicoId').value = '';
        
        // Atualizar dashboard
        updateDashboard();
    }, 1500);
}

// Atribuir serviço a um mecânico
function atribuirServico(mecanicoId, mecanicoNome) {
    document.getElementById('mecanicoId').value = mecanicoId;
    document.getElementById('mecanicoSelecionado').textContent = mecanicoNome;
    document.getElementById('mecanicoSelecionado').innerHTML = 
        `<i class="bi bi-person-check"></i> ${mecanicoNome}`;
    
    fecharModal();
    document.getElementById('tipoServico').focus();
}

// Abrir modal de seleção de mecânicos
function abrirSelecaoMecanicos() {
    const modal = document.getElementById('mecanicosModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Fechar modal
function fecharModal() {
    const modal = document.getElementById('mecanicosModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (modal) modal.style.display = 'none';
    if (confirmModal) confirmModal.style.display = 'none';
    
    document.body.style.overflow = 'auto';
    showLoading(false);
}

// Carregar mecânicos para o modal
function loadMecanicosForModal() {
    const container = document.getElementById('modalMecanicosList');
    if (!container) return;
    
    // Simular dados (em produção, seria uma requisição AJAX)
    const mecanicos = [
        { id: 1, nome: 'João Silva', especialidade: 'Motor', disponivel: true },
        { id: 2, nome: 'Maria Santos', especialidade: 'Elétrica', disponivel: true },
        { id: 3, nome: 'Carlos Oliveira', especialidade: 'Suspensão', disponivel: false },
        { id: 4, nome: 'Ana Costa', especialidade: 'Pintura', disponivel: true },
        { id: 5, nome: 'Miguel Pereira', especialidade: 'Ar Condicionado', disponivel: true }
    ];
    
    container.innerHTML = '';
    
    mecanicos.forEach(mec => {
        const card = document.createElement('div');
        card.className = 'mecanico-card modal';
        card.innerHTML = `
            <div class="mecanico-header">
                <div class="mecanico-avatar">${mec.nome.charAt(0)}</div>
                <div class="mecanico-info">
                    <h4>${mec.nome}</h4>
                    <span class="especialidade">
                        <i class="bi bi-tools"></i> ${mec.especialidade}
                    </span>
                </div>
            </div>
            <div class="mecanico-details">
                <p><i class="bi bi-circle-fill" style="color: ${mec.disponivel ? '#28a745' : '#dc3545'}"></i> 
                   ${mec.disponivel ? 'Disponível' : 'Indisponível'}</p>
            </div>
            <button class="btn-primary" onclick="atribuirServico(${mec.id}, '${mec.nome}')" ${!mec.disponivel ? 'disabled' : ''}>
                <i class="bi bi-check-circle"></i> Selecionar
            </button>
        `;
        
        container.appendChild(card);
    });
}

// Enviar mensagem para mecânico
function enviarMensagem(mecanicoId) {
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        document.getElementById('confirmTitle').innerHTML = '<i class="bi bi-chat"></i> Enviar Mensagem';
        document.getElementById('confirmMessage').textContent = 'Abrir chat com este mecânico?';
        
        const confirmBtn = document.getElementById('confirmOk');
        confirmBtn.onclick = function() {
            // Redirecionar para chat
            window.location.href = `chat.php?mecanico=${mecanicoId}`;
        };
        
        confirmModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Enviar mensagem rápida
function enviarMensagemRapida() {
    const destinatario = document.getElementById('destinatarioMsg').value;
    const mensagem = document.getElementById('textoMensagem').value.trim();
    
    if (!mensagem) {
        showAlert('Por favor, digite uma mensagem.', 'error');
        return;
    }
    
    showLoading(true);
    
    setTimeout(() => {
        showLoading(false);
        showAlert('Mensagem enviada com sucesso!', 'success');
        
        // Adicionar ao histórico
        addToMessageHistory('Você', mensagem);
        
        // Limpar campo
        document.getElementById('textoMensagem').value = '';
    }, 1000);
}

// Adicionar mensagem ao histórico
function addToMessageHistory(autor, texto) {
    const container = document.getElementById('historicoMensagens');
    if (!container) return;
    
    const now = new Date();
    const timeString = now.toLocaleTimeString('pt-PT', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const msgItem = document.createElement('div');
    msgItem.className = 'mensagem-item';
    msgItem.innerHTML = `
        <div class="mensagem-header">
            <strong>${autor}</strong>
            <small>Hoje, ${timeString}</small>
        </div>
        <div class="mensagem-texto">${texto}</div>
    `;
    
    container.prepend(msgItem);
    
    // Limitar número de mensagens
    const allMessages = container.querySelectorAll('.mensagem-item');
    if (allMessages.length > 10) {
        allMessages[allMessages.length - 1].remove();
    }
}

// Mostrar/ocultar seções
function showSection(sectionId) {
    const sections = {
        'reparacoes': 'Mostrando reparações ativas...',
        'concluidas': 'Mostrando reparações concluídas...',
        'mecanicos': 'Mostrando lista de mecânicos...',
        'clientes': 'Mostrando lista de clientes...'
    };
    
    if (sections[sectionId]) {
        showAlert(sections[sectionId], 'info');
    }
}

// Atualizar dashboard
function updateDashboard() {
    // Simular atualização
    const stats = document.querySelectorAll('.stat-info h3');
    stats.forEach(stat => {
        const value = parseInt(stat.textContent);
        if (!isNaN(value)) {
            const newValue = value + Math.floor(Math.random() * 3);
            animateCounter(stat, value, newValue);
        }
    });
}

// Animação de contador
function animateCounter(element, start, end) {
    let current = start;
    const increment = end > start ? 1 : -1;
    const stepTime = Math.abs(Math.floor(50 / (end - start)));
    
    const timer = setInterval(() => {
        current += increment;
        element.textContent = current;
        
        if (current === end) {
            clearInterval(timer);
        }
    }, stepTime);
}

// Mostrar alerta
function showAlert(message, type = 'info') {
    // Remover alertas anteriores
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `custom-alert ${type}`;
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Estilos para o alerta
    const style = document.createElement('style');
    style.textContent = `
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'rgba(40, 167, 69, 0.9)' : 
                         type === 'error' ? 'rgba(220, 53, 69, 0.9)' : 
                         'rgba(0, 123, 255, 0.9)'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .custom-alert i {
            font-size: 1.2rem;
        }
        
        .custom-alert button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 5px;
            margin-left: 10px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
            style.remove();
        }
    }, 5000);
}

// Mostrar/ocultar loading
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
        document.body.style.overflow = show ? 'hidden' : 'auto';
    }
}

// Ver detalhes do funcionário
function verDetalhes(funcionarioId) {
    // Buscar dados do funcionário
    const formData = new FormData();
    formData.append('id', funcionarioId);
    
    fetch('get_detalhes_funcionario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            abrirModalDetalhes(data);
        } else {
            showAlert(data.mensagem || 'Erro ao carregar detalhes', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro ao carregar dados', 'error');
    });
}

// Abrir modal de detalhes
function abrirModalDetalhes(dados) {
    const modal = document.getElementById('detalhesModal');
    if (!modal) {
        criarModalDetalhes();
    }
    
    // Preencher dados
    document.getElementById('detalhesNome').textContent = dados.nome;
    document.getElementById('detalhesTipo').textContent = dados.tipo === 'mecanico' ? 'Mecânico' : 'Orçamentista';
    document.getElementById('detalhesEspecialidade').textContent = dados.especialidade || 'N/A';
    document.getElementById('detalhesEmail').textContent = dados.email;
    document.getElementById('detalhesTelefone').textContent = dados.contacto || dados.telefone || 'N/A';
    document.getElementById('detalhesData').textContent = dados.data_contratacao || 'N/A';
    
    // Guardar ID para o botão despedir
    document.getElementById('btnDespedir').setAttribute('data-funcionario-id', dados.id);
    document.getElementById('btnDespedir').setAttribute('data-tipo', dados.tipo);
    
    // Mostrar modal
    const modalElement = document.getElementById('detalhesModal');
    if (modalElement) {
        modalElement.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Criar modal de detalhes se não existir
function criarModalDetalhes() {
    const modal = document.createElement('div');
    modal.id = 'detalhesModal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content modal-detalhes">
            <div class="modal-header">
                <h2>Detalhes do Funcionário</h2>
                <button class="modal-close" onclick="fecharDetalhesModal()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="detalhes-container">
                    <div class="detalhe-grupo">
                        <label>Nome:</label>
                        <p id="detalhesNome">-</p>
                    </div>
                    
                    <div class="detalhe-grupo">
                        <label>Tipo:</label>
                        <p id="detalhesTipo">-</p>
                    </div>
                    
                    <div class="detalhe-grupo">
                        <label>Especialidade:</label>
                        <p id="detalhesEspecialidade">-</p>
                    </div>
                    
                    <div class="detalhe-grupo">
                        <label>Email:</label>
                        <p id="detalhesEmail">-</p>
                    </div>
                    
                    <div class="detalhe-grupo">
                        <label>Telefone:</label>
                        <p id="detalhesTelefone">-</p>
                    </div>
                    
                    <div class="detalhe-grupo">
                        <label>Data de Contratação:</label>
                        <p id="detalhesData">-</p>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="btn-secondary" onclick="fecharDetalhesModal()">
                    <i class="bi bi-x"></i> Fechar
                </button>
                <button id="btnDespedir" class="btn-danger" onclick="iniciarDespedimento()">
                    <i class="bi bi-exclamation-circle"></i> Despedir
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Fechar modal ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            fecharDetalhesModal();
        }
    });
}

// Fechar modal de detalhes
function fecharDetalhesModal() {
    const modal = document.getElementById('detalhesModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Iniciar processo de despedimento
function iniciarDespedimento() {
    const funcionarioId = document.getElementById('btnDespedir').getAttribute('data-funcionario-id');
    const tipo = document.getElementById('btnDespedir').getAttribute('data-tipo');
    const nome = document.getElementById('detalhesNome').textContent;
    
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        document.getElementById('confirmTitle').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Confirmar Despedimento';
        document.getElementById('confirmMessage').innerHTML = `
            <p>Tem a certeza que deseja despedir <strong>${nome}</strong>?</p>
            <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                <i class="bi bi-info-circle"></i> Esta ação não pode ser desfeita.
            </p>
        `;
        
        const confirmBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');
        
        confirmBtn.textContent = 'Sim, Despedir';
        confirmBtn.style.backgroundColor = '#dc3545';
        
        confirmBtn.onclick = function() {
            despedirFuncionario(funcionarioId, tipo);
        };
        
        cancelBtn.onclick = function() {
            fecharModal();
        };
        
        confirmModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Adicionar campo de motivo
        const messageDiv = document.getElementById('confirmMessage');
        if (!messageDiv.querySelector('textarea')) {
            const textarea = document.createElement('textarea');
            textarea.id = 'motivoDespedimento';
            textarea.placeholder = 'Motivo do despedimento (obrigatório)';
            textarea.style.cssText = `
                width: 100%;
                margin-top: 15px;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-family: inherit;
                resize: vertical;
                min-height: 80px;
            `;
            messageDiv.appendChild(textarea);
        }
    }
}

// Despedir funcionário
function despedirFuncionario(funcionarioId, tipo) {
    const motivo = document.getElementById('motivoDespedimento')?.value?.trim();
    
    if (!motivo) {
        showAlert('Por favor, indique o motivo do despedimento', 'error');
        return;
    }
    
    showLoading(true);
    
    const formData = new FormData();
    formData.append('funcionario_id', funcionarioId);
    formData.append('tipo', tipo);
    formData.append('motivo', motivo);
    
    fetch('api_despedir_funcionario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);
        
        if (data.sucesso) {
            showAlert('Funcionário despedido com sucesso', 'success');
            fecharModal();
            fecharDetalhesModal();
            
            // Recarregar página após 2 segundos
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showAlert(data.mensagem || 'Erro ao despedir funcionário', 'error');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Erro:', error);
        showAlert('Erro na comunicação com o servidor', 'error');
    });
}

// Logout
function logout() {
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        document.getElementById('confirmTitle').innerHTML = '<i class="bi bi-box-arrow-right"></i> Terminar Sessão';
        document.getElementById('confirmMessage').innerHTML = 'Tem certeza que deseja sair do sistema?';
        
        const confirmBtn = document.getElementById('confirmOk');
        confirmBtn.textContent = 'Sim, Sair';
        confirmBtn.style.backgroundColor = '';
        confirmBtn.onclick = function() {
            window.location.href = 'logout.php';
        };
        
        confirmModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Iniciar atualizações em tempo real
function startLiveUpdates() {
    // Atualizar estatísticas a cada 30 segundos
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            updateDashboard();
        }
    }, 30000);
    
    // Verificar novas mensagens a cada 15 segundos
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            checkNewMessages();
        }
    }, 15000);
}

// Verificar novas mensagens
function checkNewMessages() {
    // Simular verificação de novas mensagens
    if (Math.random() > 0.8) {
        showNotification('Nova mensagem recebida', 'info');
    }
}

// Manipular clique em cards de estatística
function handleStatCardClick(e) {
    const card = e.currentTarget;
    card.style.transform = 'translateY(-8px) scale(1.02)';
    
    setTimeout(() => {
        card.style.transform = 'translateY(-5px) scale(1.01)';
    }, 300);
}

// Manipular clique em botões de ação
function handleActionClick(e) {
    const button = e.currentTarget;
    button.style.transform = 'translateY(-2px) scale(1.05)';
    
    setTimeout(() => {
        button.style.transform = 'translateY(0) scale(1)';
    }, 200);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + L para logout
    if (e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        logout();
    }
    
    // Esc para fechar modais
    if (e.key === 'Escape') {
        fecharModal();
    }
    
    // F5 para recarregar dashboard
    if (e.key === 'F5') {
        e.preventDefault();
        updateDashboard();
        showAlert('Dashboard atualizado', 'info');
    }
});

// Detectar quando o usuário está inativo
let inactivityTime = 0;
const resetInactivity = () => {
    inactivityTime = 0;
};

document.addEventListener('mousemove', resetInactivity);
document.addEventListener('keypress', resetInactivity);

setInterval(() => {
    inactivityTime++;
    if (inactivityTime > 300) { // 5 minutos
        showAlert('Você está inativo há 5 minutos. A sessão expirará em breve.', 'warning');
    }
    if (inactivityTime > 420) { // 7 minutos
        logout();
    }
}, 1000);