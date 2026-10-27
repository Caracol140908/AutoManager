<?php
/**
 * Cabeçalho de assets unificado (theme + base + fontes + ícones + CSS da página).
 * Incluir dentro de <head>, depois de charset/viewport/title.
 *
 * Opcional antes do require:
 *   $am_head_font = 'poppins'|'inter'|'';  (auto por preset se omitido)
 *   $am_head_icons = 'fa'|'bi'|'both'|''; (auto por preset se omitido)
 *   $am_head_stylesheets = ['ficheiro.css', ...]; (merge com preset)
 *   $am_head_body_class = 'am-font-inter';
 */

if (!defined('AM_THEME_LINK_INCLUDED')) {
    define('AM_THEME_LINK_INCLUDED', true);
    echo '<link rel="stylesheet" href="theme.css">' . "\n";
    echo '<link rel="stylesheet" href="am-base.css">' . "\n";
    echo '<link rel="stylesheet" href="am-dialogs.css?v=1">' . "\n";
    echo '<script src="am-dialogs.js?v=1" defer></script>' . "\n";
}

if (empty($am_skip_loading)) {
    echo '<link rel="preload" href="img/logo-automanager.png" as="image">' . "\n";
    echo '<link rel="stylesheet" href="loading.css?v=1">' . "\n";
    echo '<style>html.am-is-loading{background:rgba(0,0,0,0.92)}</style>' . "\n";
    echo '<script src="loading.js?v=1"></script>' . "\n";
}

$am_script = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');

$am_head_presets = [
    'index' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['style.css', 'booking-clean.css?v=4']],
    'faq' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['style.css', 'faq.css?v=9']],
    'chat' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['chat.css?v=2']],
    'configuracoes' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['styleconfiguracoes.css']],
    'passaporte_veiculo' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['styleconfiguracoes.css']],
    'dashboard' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['dashboard.css']],
    'feedback' => ['font' => 'poppins', 'icons' => '', 'css' => ['stylefeedback.css']],
    'termos' => ['font' => 'poppins', 'icons' => '', 'css' => ['styletermos.css']],
    'rastreamento_reparacao' => ['font' => 'poppins', 'icons' => 'fa', 'css' => []],
    'login' => ['font' => 'inter', 'icons' => 'fa', 'css' => []],
    'login_chefe' => ['font' => 'inter', 'icons' => 'bi', 'css' => []],
    'mudar_password' => ['font' => 'inter', 'icons' => 'fa', 'css' => []],
    'alterar_password_chefe' => ['font' => 'inter', 'icons' => 'bi', 'css' => []],
    'alterar_password_orcamentista' => ['font' => 'poppins', 'icons' => '', 'css' => []],
    'orcamentista' => ['font' => 'poppins', 'icons' => '', 'css' => []],
    'ver_orcamento' => ['font' => 'poppins', 'icons' => '', 'css' => []],
    'indexadmin' => ['font' => 'inter', 'icons' => 'bi', 'css' => ['indexadmin.css?v=23']],
    'indexchefeoficina' => ['font' => 'inter', 'icons' => 'bi', 'css' => ['indexchefeoficina.css?v=9']],
    'indexchefedeoficina' => ['font' => 'inter', 'icons' => 'bi', 'css' => []],
    'gestao_funcionarios_chefe' => ['font' => 'inter', 'icons' => 'bi', 'css' => []],
    'bate_ponto' => ['font' => 'inter', 'icons' => 'bi', 'css' => []],
    'adicionar_funcionario' => ['font' => 'inter', 'icons' => 'bi', 'css' => ['styleadmin.css']],
    'chat_admin' => ['font' => 'inter', 'icons' => 'bi', 'css' => ['chat.css?v=2']],
    'funcionarios' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['stylefuncionarios.css']],
    'notificacoes' => ['font' => 'poppins', 'icons' => 'fa', 'css' => []],
    'configurar_telegram' => ['font' => 'poppins', 'icons' => 'fa', 'css' => []],
    'metodopagamento' => ['font' => 'poppins', 'icons' => 'fa', 'css' => ['metodopagamento.css']],
    'download_fatura' => ['font' => 'poppins', 'icons' => '', 'css' => ['fatura_pdf.css']],
    'ai-chat' => ['font' => 'poppins', 'icons' => 'fa', 'css' => []],
    'ai-dashboard' => ['font' => 'poppins', 'icons' => '', 'css' => []],
    'gerar_relatorio' => ['font' => 'inter', 'icons' => '', 'css' => []],
    'corrigir_tabelas' => ['font' => 'inter', 'icons' => '', 'css' => []],
    'ERROS_CORRIGIDOS' => ['font' => 'inter', 'icons' => '', 'css' => []],
    'fix_contador' => ['font' => 'inter', 'icons' => '', 'css' => []],
    'limpar_chats_antigos' => ['font' => 'inter', 'icons' => '', 'css' => []],
    'obter_chat_id' => ['font' => 'inter', 'icons' => '', 'css' => []],
    'test-ai' => ['font' => 'poppins', 'icons' => '', 'css' => []],
];

$preset = $am_head_presets[$am_script] ?? ['font' => 'poppins', 'icons' => 'fa', 'css' => []];

$font = $am_head_font ?? $preset['font'];
$icons = $am_head_icons ?? $preset['icons'];
$stylesheets = array_values(array_unique(array_merge(
    $preset['css'],
    isset($am_head_stylesheets) && is_array($am_head_stylesheets) ? $am_head_stylesheets : []
)));

echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";

if ($font === 'poppins') {
    echo '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">' . "\n";
} elseif ($font === 'inter') {
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">' . "\n";
}

if ($icons === 'fa' || $icons === 'both') {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' . "\n";
}
if ($icons === 'bi' || $icons === 'both') {
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">' . "\n";
}

foreach ($stylesheets as $href) {
    $href = trim((string) $href);
    if ($href === '') {
        continue;
    }
    echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

if (!empty($am_head_body_class)) {
    echo '<script>document.documentElement.classList.add("am-head-ready");</script>' . "\n";
}
