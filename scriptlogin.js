document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const resetRequestForm = document.getElementById('resetRequestForm');
    const resetCodeForm = document.getElementById('resetCodeForm');
    const newPasswordForm = document.getElementById('newPasswordForm');
    const formTitle = document.getElementById('formTitle');

    // Função para mostrar apenas um formulário
    function showForm(formToShow, title) {
        // Esconder todos os formulários
        [loginForm, registerForm, resetRequestForm, resetCodeForm, newPasswordForm].forEach(form => {
            if (form) form.style.display = 'none';
        });
        
        // Mostrar o formulário especificado
        if (formToShow) formToShow.style.display = 'block';
        if (formTitle) formTitle.textContent = title;
    }

    // Navegação entre formulários
    document.getElementById('showRegister').addEventListener('click', function(e) {
        e.preventDefault();
        showForm(registerForm, 'Criar conta na AUTOMANAGER');
    });

    document.getElementById('showLogin').addEventListener('click', function(e) {
        e.preventDefault();
        showForm(loginForm, 'Entrar na AUTOMANAGER');
    });

    document.getElementById('showResetRequest').addEventListener('click', function(e) {
        e.preventDefault();
        showForm(resetRequestForm, 'Recuperar senha');
    });

    document.getElementById('backToLogin').addEventListener('click', function(e) {
        e.preventDefault();
        showForm(loginForm, 'Entrar na AUTOMANAGER');
    });

    document.getElementById('backToResetRequest').addEventListener('click', function(e) {
        e.preventDefault();
        showForm(resetRequestForm, 'Recuperar senha');
    });

    // Processar pedido de reset - guardar email para os próximos passos
    if (resetRequestForm) {
        resetRequestForm.addEventListener('submit', function() {
            const email = this.querySelector('input[name="email"]').value;
            if (document.getElementById('resetEmail')) {
                document.getElementById('resetEmail').value = email;
            }
        });
    }

    // Processar verificação de código - passar email para o próximo formulário
    if (resetCodeForm) {
        resetCodeForm.addEventListener('submit', function() {
            const email = document.getElementById('resetEmail').value;
            if (document.getElementById('newPasswordEmail')) {
                document.getElementById('newPasswordEmail').value = email;
            }
        });
    }

    // CORREÇÃO: Quando o formulário de nova senha é submetido com sucesso,
    // mostrar automaticamente o formulário de login
    if (newPasswordForm) {
        newPasswordForm.addEventListener('submit', function() {
            // Verificar se há uma mensagem de sucesso após a submissão
            setTimeout(function() {
                const successMessage = document.querySelector('.message.success');
                if (successMessage && successMessage.textContent.includes('Senha atualizada com sucesso')) {
                    showForm(loginForm, 'Entrar na AUTOMANAGER');
                }
            }, 100);
        });
    }

    // Verificar estado inicial - se deve mostrar login após redefinição de password
    function checkInitialState() {
        const successMessage = document.querySelector('.message.success');
        
        // Se há uma mensagem de sucesso de redefinição de password, mostrar login
        if (successMessage && successMessage.textContent.includes('Senha atualizada com sucesso')) {
            showForm(loginForm, 'Entrar na AUTOMANAGER');
        }
        // Se o formulário de reset request estiver visível (por PHP), atualizar o título
        else if (resetRequestForm && resetRequestForm.style.display === 'block') {
            formTitle.textContent = 'Recuperar senha';
        }
        // Se o formulário de código estiver visível (por PHP), atualizar o título
        else if (resetCodeForm && resetCodeForm.style.display === 'block') {
            formTitle.textContent = 'Verificar código';
        }
        // Se o formulário de nova senha estiver visível (por PHP), atualizar o título
        else if (newPasswordForm && newPasswordForm.style.display === 'block') {
            formTitle.textContent = 'Nova senha';
        }
    }

    checkInitialState();
});