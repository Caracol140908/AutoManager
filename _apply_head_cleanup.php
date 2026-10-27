<?php
/**
 * Remove links duplicados (já emitidos por inc_am_head.php). Executar uma vez.
 */
$root = __DIR__;
$removePatterns = [
    '/\s*<link rel="preconnect" href="https:\/\/fonts\.googleapis\.com">\s*\n/i',
    '/\s*<link rel="preconnect" href="https:\/\/fonts\.gstatic\.com" crossorigin>\s*\n/i',
    '/\s*<link href="https:\/\/fonts\.googleapis\.com\/css2\?family=Poppins[^"]*" rel="stylesheet">\s*\n/i',
    '/\s*<link href="https:\/\/fonts\.googleapis\.com\/css2\?family=Inter[^"]*" rel="stylesheet">\s*\n/i',
    '/\s*<link rel="stylesheet" href="https:\/\/cdnjs\.cloudflare\.com\/ajax\/libs\/font-awesome[^"]+">\s*\n/i',
    '/\s*<link rel="stylesheet" href="https:\/\/cdn\.jsdelivr\.net\/npm\/bootstrap-icons[^"]+">\s*\n/i',
    '/\s*<link rel="stylesheet" href="theme\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="am-base\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="style\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="booking-clean\.css[^"]*">\s*\n/i',
    '/\s*<link rel="stylesheet" href="styleconfiguracoes\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="dashboard\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="faq\.css[^"]*">\s*\n/i',
    '/\s*<link rel="stylesheet" href="stylefeedback\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="styletermos\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="styleorcamentista\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="stylefuncionarios\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="styleadmin\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="metodopagamento\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="pagamento_orcamento\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="solicitar_desbloqueio\.css">\s*\n/i',
    '/\s*<link rel="stylesheet" href="fatura_pdf\.css">\s*\n/i',
];

foreach (glob($root . '/*.php') as $file) {
    $base = basename($file);
    if ($base === '_apply_head_cleanup.php' || $base === 'theme_link.php') {
        continue;
    }
    $content = file_get_contents($file);
    if ($content === false || strpos($content, 'inc_am_head.php') === false) {
        continue;
    }
    $orig = $content;
    foreach ($removePatterns as $pat) {
        $content = preg_replace($pat, '', $content);
    }
    if ($content !== $orig) {
        file_put_contents($file, $content);
        echo "Cleaned: $base\n";
    }
}

// passaporte — adicionar inc_am_head
$pass = $root . '/passaporte_veiculo.php';
$p = file_get_contents($pass);
if ($p && strpos($p, 'inc_am_head.php') === false) {
    $p = preg_replace(
        '/(<head>\s*\n)/i',
        "$1    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n",
        $p,
        1
    );
    $p = preg_replace(
        '/<title>[^<]+<\/title>\s*\n\s*<link rel="stylesheet" href="styleconfiguracoes\.css">/',
        "<title>Passaporte Digital</title>\n    <?php require __DIR__ . '/inc_am_head.php'; ?>",
        $p,
        1
    );
    // fix duplicate meta if we messed up - read passaporte again
    file_put_contents($pass, $p);
    echo "Updated: passaporte_veiculo.php\n";
}

echo "Done.\n";
