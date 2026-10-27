<?php
require_once('ligar.php');

echo '<h2>Sessões Temporárias na BD</h2>';

$result = $con->query('SELECT * FROM sessoes_temporarias ORDER BY primeiro_acesso DESC');

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo '<tr><th>ID</th><th>IP</th><th>Primeiro Acesso</th><th>Último Acesso</th><th>Tempo Restante</th><th>Visitas</th><th>Página</th><th>Expirou</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) ($row['id'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['ip_address'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['primeiro_acesso'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['ultimo_acesso'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['tempo_restante'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['visitas'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['pagina_atual'] ?? '')) . '</td>';
        echo '<td>' . (!empty($row['expirou']) ? 'SIM' : 'NÃO') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>Nenhuma sessão encontrada na BD.</p>';
}

echo '<p><em>Histórico de visitas trial integrado em <code>sessoes_temporarias</code> (colunas visitas / pagina_atual).</em></p>';
