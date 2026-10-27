<?php
echo "<h2>Log de Erros do Check Session</h2>";
echo "<pre>";

// Verificar se há erros no log do servidor
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $lines = file($log_file);
    $last_lines = array_slice($lines, -50);
    foreach ($last_lines as $line) {
        if (strpos($line, 'CHECK_SESSION') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
} else {
    echo "Ficheiro de log não encontrado: $log_file\n";
}
echo "</pre>";
?>