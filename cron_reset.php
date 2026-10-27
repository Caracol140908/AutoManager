<?php
// cron_reset.php - Executar automaticamente às segundas-feiras
$ip_file = 'temp_ips.json';
file_put_contents($ip_file, json_encode([]));
echo "Reset semanal de IPs concluído em " . date('Y-m-d H:i:s');
?>