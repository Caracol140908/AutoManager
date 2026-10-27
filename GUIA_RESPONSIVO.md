# [DISPOSITIVO] GUIA DE SUPORTE RESPONSIVO - AUTOMANAGER

## [OK] O que foi implementado

Este projeto agora tem **suporte completo para todos os tipos de telas**:
- [OK] **Desktop** (1200px+)
- [OK] **Tablets** (768px - 1024px)
- [OK] **Telemóvel Grande** (600px - 767px)
- [OK] **Telemóvel Pequeno** (480px - 599px)
- [OK] **Extra Pequeno** (<480px)

## [PASTA] Ficheiros Novos Criados

### CSS
- **`responsive-helpers.css`** - Estilos universais de responsividade
  - Classes de utilidade responsivas
  - Support para safe areas (notch/island)
  - Viewport height fix para móvel
  - Touch-friendly sizes

### JavaScript
- **`responsive.js`** - Sistema completo de responsividade
  - Detecção de dispositivo (móvel, tablet, desktop)
  - Gerenciamento de menu móvel
  - Handlers de touch (swipe)
  - Carregamento responsivo de imagens
  - Modais responsivos
  - Tabelas responsivas

## [FERRAMENTAS] Ficheiros Melhorados

### CSS
- **`style.css`** - Adicionados 5 breakpoints completos
  - 1024px (Tablets médios)
  - 900px (Tablets)
  - 768px (Telemóvel grande)
  - 600px (Telemóvel pequeno)
  - 480px (Extra pequeno)

- **`dashboard.css`** - Media queries responsivas
  - Sidebar colapsável em móvel
  - Tabelas com scroll horizontal
  - Stats em grid responsivo

- **`stylelogin.css`** - Melhorado com 5 breakpoints
- **`metodopagamento.css`** - Completamente responsivo

### JavaScript
- **`script.js`** - Melhorado com:
  - Toggle de menu em mobile
  - Form validation
  - Smooth scroll
  - Lazy loading de imagens
  - Paralax effect apenas em desktop

- **`scriptadmin.js`** - Mantém funcionalidade mobile

## [INICIAR] Como Usar

### 1. Adicionar à Página HTML/PHP (Header)

```html
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AUTOMANAGER - Sistema de Reparações">
    
    <!-- Estilos Responsivos -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="responsive-helpers.css">
    
    <!-- Estilos específicos da página -->
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <!-- Conteúdo -->
    
    <!-- Scripts -->
    <script src="responsive.js"></script>
    <script src="script.js"></script>
</body>
</html>
```

### 2. Usar Classes Responsivas

#### Tipografia Responsiva
```html
<!-- Tamanhos automáticos baseados na tela -->
<h1>Título que escala com a tela</h1>
<p>Parágrafo responsivo</p>
```

#### Grid Responsivo
```html
<div class="grid-responsive grid-auto">
    <div class="card">Card 1</div>
    <div class="card">Card 2</div>
    <div class="card">Card 3</div>
</div>
```

#### Flex Responsivo
```html
<div class="flex-responsive">
    <div>Item 1</div>
    <div>Item 2</div>
    <div>Item 3</div>
</div>
```

#### Buttons Touch-Friendly
```html
<!-- Automático: 44x44px mínimo em móvel -->
<button>Clique-me</button>
```

#### Tabelas Responsivas
```html
<div class="table-wrapper">
    <table>
        <!-- Conteúdo -->
    </table>
</div>
```

### 3. Usar Funções JavaScript

```javascript
// Detectar tipo de dispositivo
if (ResponsiveHelpers.isMobile()) {
    console.log("É móvel!");
}

if (ResponsiveHelpers.isTablet()) {
    console.log("É tablet!");
}

// Obter breakpoint atual
const breakpoint = ResponsiveHelpers.getCurrentBreakpoint();
console.log(breakpoint); // 'xs', 'sm', 'md', 'lg', 'xl', 'xxl'

// Detectar orientação
if (ResponsiveHelpers.isPortrait()) {
    console.log("Retrato");
} else {
    console.log("Paisagem");
}
```

### 4. Menu Responsivo em Header

```html
<button class="menu-toggle" id="navToggle">
    <i class="fas fa-bars"></i>
</button>
<nav id="navbar" class="navbar">
    <ul>
        <li><a href="#home">Home</a></li>
        <li><a href="#about">Sobre</a></li>
    </ul>
</nav>
```

## [DISPOSITIVO] Breakpoints Utilizados

| Dispositivo | Largura | Breakpoint |
|-------------|---------|-----------|
| Extra Pequeno | < 480px | `xs` |
| Pequeno (Mobile) | 480-599px | `sm` |
| Médio (Tablet) | 600-767px | `md` |
| Grande (Tablet) | 768-1023px | `lg` |
| Extra Grande | 1024-1199px | `xl` |
| Desktop | 1200px+ | `xxl` |

##  Classes CSS Úteis

### Visibilidade
```html
<!-- Mostrar apenas em móvel -->
<div class="show-mobile">Visível apenas em móvel</div>

<!-- Esconder em móvel -->
<div class="hide-mobile">Escondido em móvel</div>

<!-- Mostrar apenas em tablet -->
<div class="show-tablet">Visível apenas em tablet</div>
```

### Espaçamento Responsivo
```html
<div class="p-responsive">Padding responsivo</div>
<div class="m-responsive">Margin responsivo</div>
<div class="gap-responsive">Gap responsivo</div>
```

### Utilitários
```html
<div class="fullwidth">100% de largura</div>
<div class="center-flex">Centrado com flexbox</div>
<div class="center-grid">Centrado com grid</div>
<div class="scroll-x">Scroll horizontal</div>
<div class="no-scroll">Sem scroll</div>
```

## [SEGURANCA] Safe Area Support

Para dispositivos com notch/Dynamic Island (iPhone 12+):

```html
<body class="safe-area-inset-left safe-area-inset-right">
    <!-- Conteúdo respeitará os safe areas -->
</body>
```

## [RELATORIO] Touch-Friendly Tamanhos

Todos os elementos interativos têm mínimo **44x44px** (recomendado para touch):
- Botões
- Links
- Inputs
- Checkboxes/Radios

##  Imagens Responsivas

### Lazy Loading
```html
<img src="placeholder.jpg" data-src="image.jpg" alt="Descrição">
```

### Imagens por Device
```html
<img 
    data-src-mobile="image-mobile.jpg"
    data-src-tablet="image-tablet.jpg"
    data-src-desktop="image-desktop.jpg"
    alt="Responsivo">
```

##  Animações em Mobile

Respeita preferências do utilizador:
```css
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
    }
}
```

##  Hover Effects

Automático: desabilita hover effects em devices touch:
```css
@media (hover: none) {
    /* Sem hover em touch devices */
}

@media (hover: hover) {
    /* Com hover em mouse devices */
}
```

## [FERRAMENTAS] Customização

### Ajustar Cores e Espaçamento

No início de cada ficheiro CSS:
```css
:root {
    --primary-color: #3B82F6;
    --mobile-padding: 1rem;
    /* ... mais variáveis */
}
```

### Adicionar Novos Breakpoints

Em `responsive-helpers.css`:
```css
:root {
    --custom-bp: 1400px;
}

@media (max-width: 1400px) {
    /* Estilos customizados */
}
```

## [DESTAQUE] Funcionalidades Avançadas

### Menu com Swipe
Swipe da esquerda para direita abre o menu em móvel.

### Viewport Height Fix
Corrige problema de altura de viewport em navegadores móveis (Chrome, Firefox):
```javascript
// Automático através de responsive.js
var vh = window.innerHeight * 0.01;
document.documentElement.style.setProperty('--vh', vh + 'px');
```

### Auto-adjust Font Size
Tamanhos de font ajustam automaticamente baseado na tela.

### Modal Responsivo
Modais ocupam toda a tela em móvel, centrados em desktop.

##  Testar Responsividade

### DevTools Browser
1. Pressionar `F12` ou `Ctrl+Shift+I`
2. Clicar no ícone de dispositivo móvel (canto superior esquerda)
3. Selecionar diferentes dispositivos

### Online Tools
- [Responsively App](https://responsively.app/)
- [Google Mobile-Friendly Test](https://search.google.com/test/mobile-friendly)

## [NOTAS] Checklist de Implementação

- [ ] Adicionar `<meta name="viewport">` em todos os PHP
- [ ] Incluir `responsive-helpers.css` em todas as páginas
- [ ] Incluir `responsive.js` antes do fecha `</body>`
- [ ] Testar em diferentes resoluções (use DevTools)
- [ ] Testar em dispositivos reais
- [ ] Validar touch interactions
- [ ] Testar em modo paisagem/retrato
- [ ] Verificar performance em 3G

##  Troubleshooting

### Elementos não responsivos
**Solução:** Verificar se as classes CSS responsivas estão aplicadas.

### Menu não funciona em móvel
**Solução:** Verificar se `responsive.js` está incluído antes do fecha `</body>`.

### Texto muito pequeno/grande
**Solução:** Ajustar values `clamp()` em `responsive-helpers.css`.

### Imagens pixeladas em Retina
**Solução:** Fornecer imagens @2x e usar media query:
```css
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    img {
        background-image: url('image@2x.jpg');
    }
}
```

## [DOCS] Recursos

- [MDN Responsive Design](https://developer.mozilla.org/en-US/docs/Learn/CSS/CSS_layout/Responsive_Design)
- [CSS Tricks - A Complete Guide to Grid](https://css-tricks.com/snippets/css/complete-guide-grid/)
- [Web.dev - Mobile-friendly Guide](https://web.dev/responsive-web-design-basics/)

## [INICIAR] Performance Tips

1. **Lazy Loading:** Carrega imagens apenas quando necessário
2. **Minificar CSS/JS:** Reduz tamanho de ficheiros
3. **Compress Imagens:** Use formato moderno (WebP)
4. **Caching:** Configure cache do browser
5. **CDN:** Distribua ficheiros globalmente

---

**Versão:** 1.0  
**Data:** Janeiro 2026  
**Autor:** AUTOMANAGER Development Team
