/**
 * MAIN SCRIPT.JS - Funcionalidades gerais do site
 */

// Hero image parallax effect (apenas em desktop/tablet)
const heroImage = document.getElementById("hero-image");

if (heroImage && window.innerWidth > 768) {
    document.addEventListener("mousemove", e => {
        // Desabilitar em móvel para melhor performance
        if (window.innerWidth > 768) {
            const x = (e.clientX / window.innerWidth - 0.5) * 10;
            const y = (e.clientY / window.innerHeight - 0.5) * 5;
            heroImage.style.transform = `translate(${x}px, ${y}px)`;
        }
    });
}

// ===== MOBILE NAVIGATION TOGGLE =====

document.addEventListener('DOMContentLoaded', () => {
    // Menu toggle para header em mobile
    const navToggle = document.querySelector('.nav-toggle');
    const navbar = document.getElementById('navbar');
    const navActions = document.querySelector('.nav-actions');

    if (navToggle) {
        navToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            if (navbar) navbar.classList.toggle('active');
            if (navActions) navActions.classList.toggle('active');
            navToggle.classList.toggle('active');
            
            // Debug
            console.log('Menu toggled - navbar active:', navbar?.classList.contains('active'));
        });

        // Fechar menu ao clicar num link
        const navLinks = document.querySelectorAll('nav a, .nav-actions a, .nav-actions button');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (navbar) navbar.classList.remove('active');
                if (navActions) navActions.classList.remove('active');
                if (navToggle) navToggle.classList.remove('active');
            });
        });

        // Fechar menu ao clicar fora
        document.addEventListener('click', (e) => {
            const isNavClick = e.target.closest('nav') || e.target.closest('.nav-toggle') || e.target.closest('.nav-actions');
            
            if (!isNavClick) {
                if (navbar) navbar.classList.remove('active');
                if (navActions) navActions.classList.remove('active');
                if (navToggle) navToggle.classList.remove('active');
            }
        });

        // Fechar menu ao redimensionar para desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                if (navbar) navbar.classList.remove('active');
                if (navActions) navActions.classList.remove('active');
                if (navToggle) navToggle.classList.remove('active');
            }
        });
    }
});

// ===== FORM HANDLING RESPONSIVO =====

document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Validação simples em cliente
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required], textarea[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                    input.style.borderColor = '#ff4444';
                } else {
                    input.classList.remove('error');
                    input.style.borderColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios');
            }
        });

        // Remove erro ao editar
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.classList.remove('error');
                input.style.borderColor = '';
            });
        });
    });
});

// ===== SMOOTH SCROLL =====

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href === '#') return;
        
        e.preventDefault();
        const target = document.querySelector(href);
        
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ===== LAZY LOADING DE IMAGENS =====

if ('IntersectionObserver' in window) {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}

console.log('[OK] script.js loaded successfully');

