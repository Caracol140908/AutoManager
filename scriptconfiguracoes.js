(function () {
    var form = document.getElementById('excluirContaForm');
    if (!form) {
        return;
    }
    var bypass = false;
    form.addEventListener('submit', function (e) {
        if (bypass) {
            bypass = false;
            return;
        }
        if (typeof amConfirm !== 'function') {
            return;
        }
        e.preventDefault();
        amConfirm('Tem certeza que deseja excluir a conta? Esta ação não pode ser revertida.', {
            title: 'Eliminar conta',
            variant: 'danger'
        }).then(function (ok) {
            if (ok) {
                bypass = true;
                form.submit();
            }
        });
    });
})();

// Menu Toggle para Mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (menuToggle && sidebar) {
    // Toggle ao clicar no botão
    menuToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
    });

    // Fechar ao clicar no overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Fechar ao clicar num link
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            sidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
        });
    });

    // Fechar ao clicar no botão footer
    const btnFooter = sidebar.querySelector('.btn');
    if (btnFooter) {
        btnFooter.addEventListener('click', () => {
            sidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
        });
    }
}
