/**
 * RESPONSIVE.JS - Sistema Universal de Responsividade Móvel
 * Suporta todos os tipos de telas: Desktop, Tablet, Mobile
 */

// ===== UTILITY FUNCTIONS =====

/**
 * Detecta se o dispositivo é móvel
 */
function isMobile() {
    return window.innerWidth <= 768;
}

/**
 * Detecta se é tablet
 */
function isTablet() {
    return window.innerWidth > 768 && window.innerWidth <= 1024;
}

/**
 * Detecta se é desktop
 */
function isDesktop() {
    return window.innerWidth > 1024;
}

/**
 * Detecta orientação do dispositivo
 */
function isPortrait() {
    return window.innerHeight > window.innerWidth;
}

function isLandscape() {
    return window.innerWidth > window.innerHeight;
}

/**
 * Retorna breakpoint atual
 */
function getCurrentBreakpoint() {
    if (window.innerWidth < 480) return 'xs';
    if (window.innerWidth < 600) return 'sm';
    if (window.innerWidth < 768) return 'md';
    if (window.innerWidth < 1024) return 'lg';
    if (window.innerWidth < 1200) return 'xl';
    return 'xxl';
}

// ===== MOBILE MENU MANAGEMENT =====

class MobileMenuManager {
    constructor() {
        this.menuToggle = document.querySelector('.menu-toggle');
        this.sidebar = document.querySelector('.sidebar');
        this.navbar = document.getElementById('navbar');
        this.navActions = document.querySelector('.nav-actions');
        this.overlay = null;
        
        if (this.menuToggle || this.sidebar) {
            this.init();
        }
    }

    init() {
        // Criar overlay se não existir
        if (!document.getElementById('menuOverlay')) {
            this.overlay = document.createElement('div');
            this.overlay.id = 'menuOverlay';
            this.overlay.className = 'menu-overlay';
            document.body.appendChild(this.overlay);
        } else {
            this.overlay = document.getElementById('menuOverlay');
        }

        this.attachEventListeners();
        this.handleWindowResize();
    }

    attachEventListeners() {
        if (this.menuToggle) {
            this.menuToggle.addEventListener('click', () => this.toggleMenu());
        }

        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeMenu());
        }

        // Fechar menu ao clicar num link
        if (this.sidebar) {
            const links = this.sidebar.querySelectorAll('a');
            links.forEach(link => {
                link.addEventListener('click', () => this.closeMenu());
            });
        }

        if (this.navbar) {
            const links = this.navbar.querySelectorAll('a');
            links.forEach(link => {
                link.addEventListener('click', () => this.closeMenu());
            });
        }

        // Fechar menu ao redimensionar
        window.addEventListener('resize', () => this.handleWindowResize());
    }

    toggleMenu() {
        if (this.sidebar) {
            this.sidebar.classList.toggle('active');
        }
        if (this.navbar) {
            this.navbar.classList.toggle('active');
        }
        if (this.navActions) {
            this.navActions.classList.toggle('active');
        }
        if (this.overlay) {
            this.overlay.classList.toggle('active');
        }
    }

    openMenu() {
        if (this.sidebar) {
            this.sidebar.classList.add('active');
        }
        if (this.navbar) {
            this.navbar.classList.add('active');
        }
        if (this.navActions) {
            this.navActions.classList.add('active');
        }
        if (this.overlay) {
            this.overlay.classList.add('active');
        }
    }

    closeMenu() {
        if (this.sidebar) {
            this.sidebar.classList.remove('active');
        }
        if (this.navbar) {
            this.navbar.classList.remove('active');
        }
        if (this.navActions) {
            this.navActions.classList.remove('active');
        }
        if (this.overlay) {
            this.overlay.classList.remove('active');
        }
    }

    handleWindowResize() {
        // Fechar menu automaticamente quando sai do móvel
        if (!isMobile() && this.sidebar) {
            this.closeMenu();
        }
    }
}

// ===== TOUCH HANDLERS =====

class TouchHandler {
    constructor() {
        this.touchStartX = 0;
        this.touchEndX = 0;
        this.minSwipeDistance = 50;
        this.init();
    }

    init() {
        document.addEventListener('touchstart', (e) => this.handleTouchStart(e), false);
        document.addEventListener('touchend', (e) => this.handleTouchEnd(e), false);
    }

    handleTouchStart(e) {
        this.touchStartX = e.changedTouches[0].screenX;
    }

    handleTouchEnd(e) {
        this.touchEndX = e.changedTouches[0].screenX;
        this.handleSwipe();
    }

    handleSwipe() {
        const diff = this.touchStartX - this.touchEndX;
        
        // Swipe para esquerda (abrir menu)
        if (diff > this.minSwipeDistance && isMobile()) {
            const menuManager = window.menuManager;
            if (menuManager && this.touchStartX < 50) {
                menuManager.openMenu();
            }
        }
        
        // Swipe para direita (fechar menu)
        if (diff < -this.minSwipeDistance && isMobile()) {
            const menuManager = window.menuManager;
            if (menuManager) {
                menuManager.closeMenu();
            }
        }
    }
}

// ===== VIEWPORT HEIGHT FIX (para navegadores móveis) =====

class ViewportHeightFix {
    constructor() {
        this.setViewportHeight();
        window.addEventListener('resize', () => this.setViewportHeight());
        window.addEventListener('orientationchange', () => this.setViewportHeight());
    }

    setViewportHeight() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
}

// ===== RESPONSIVE IMAGE LOADING =====

class ResponsiveImageLoader {
    constructor() {
        this.images = document.querySelectorAll('[data-src-mobile], [data-src-tablet], [data-src-desktop]');
        this.loadImages();
        window.addEventListener('resize', () => this.loadImages());
    }

    loadImages() {
        this.images.forEach(img => {
            let src = null;

            if (isMobile() && img.dataset.srcMobile) {
                src = img.dataset.srcMobile;
            } else if (isTablet() && img.dataset.srcTablet) {
                src = img.dataset.srcTablet;
            } else if (isDesktop() && img.dataset.srcDesktop) {
                src = img.dataset.srcDesktop;
            } else if (img.dataset.src) {
                src = img.dataset.src;
            }

            if (src && img.src !== src) {
                img.src = src;
            }
        });
    }
}

// ===== MODAL RESPONSIVE =====

class ResponsiveModal {
    constructor(modalSelector = '.modal') {
        this.modals = document.querySelectorAll(modalSelector);
        this.init();
    }

    init() {
        this.modals.forEach(modal => {
            const closeButtons = modal.querySelectorAll('[data-close], .close, .modal-close');
            closeButtons.forEach(btn => {
                btn.addEventListener('click', () => this.closeModal(modal));
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal);
                }
            });
        });
    }

    openModal(modalElement) {
        if (isMobile()) {
            modalElement.style.position = 'fixed';
            modalElement.style.top = '0';
            modalElement.style.left = '0';
            modalElement.style.right = '0';
            modalElement.style.bottom = '0';
        }
        modalElement.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    closeModal(modalElement) {
        modalElement.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// ===== TABLE RESPONSIVO =====

class ResponsiveTable {
    constructor(tableSelector = 'table') {
        this.tables = document.querySelectorAll(tableSelector);
        this.wrapTables();
        window.addEventListener('resize', () => this.wrapTables());
    }

    wrapTables() {
        this.tables.forEach(table => {
            if (isMobile()) {
                // Adicionar scroll horizontal em mobile
                if (!table.parentElement.classList.contains('table-wrapper')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-wrapper';
                    wrapper.style.overflowX = 'auto';
                    wrapper.style.marginBottom = '20px';
                    table.parentElement.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            }
        });
    }
}

// ===== FONT SIZE AUTO-ADJUST =====

class ResponsiveFontSize {
    constructor() {
        this.adjustFontSizes();
        window.addEventListener('resize', () => this.adjustFontSizes());
    }

    adjustFontSizes() {
        const root = document.documentElement;
        const width = window.innerWidth;

        // Escala dinâmica baseada na largura
        let fontSize = 16;
        if (width < 480) {
            fontSize = 14;
        } else if (width < 600) {
            fontSize = 15;
        } else if (width < 768) {
            fontSize = 15;
        } else if (width < 1024) {
            fontSize = 16;
        }

        root.style.fontSize = `${fontSize}px`;
    }
}

// ===== INICIALIZAÇÃO =====

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar todos os sistemas
    window.menuManager = new MobileMenuManager();
    new TouchHandler();
    new ViewportHeightFix();
    new ResponsiveImageLoader();
    new ResponsiveModal();
    new ResponsiveTable();
    new ResponsiveFontSize();

    // Log de inicialização
    console.log(`[OK] Responsive.js initialized`);
    console.log(`- Device: ${isMobile() ? 'Mobile' : isTablet() ? 'Tablet' : 'Desktop'}`);
    console.log(`- Breakpoint: ${getCurrentBreakpoint()}`);
    console.log(`- Orientation: ${isPortrait() ? 'Portrait' : 'Landscape'}`);
});

// ===== HELPER: Detectar mudanças de orientação =====

window.addEventListener('orientationchange', () => {
    setTimeout(() => {
        console.log(`Orientation changed to: ${isPortrait() ? 'Portrait' : 'Landscape'}`);
        // Trigger resize event para re-calcular layouts
        window.dispatchEvent(new Event('resize'));
    }, 100);
});

// ===== EXPORT FUNCTIONS (para uso em outros scripts) =====

window.ResponsiveHelpers = {
    isMobile,
    isTablet,
    isDesktop,
    isPortrait,
    isLandscape,
    getCurrentBreakpoint
};
