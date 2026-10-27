<?php
session_start();
require('ligar.php');

// Sempre retornar JSON
header('Content-Type: application/json');

// Verificar sessão
if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada']);
    exit;
}

$mecanico_id = $_SESSION['mecanico_id'];
$filtro = $_POST['filtro'] ?? 'todos';


// Construir query compatível com diferentes nomes de tabela/colunas usados no projeto
// Preferimos a tabela singular `servico` (é a que a maior parte do código usa). Fallback mantém compatibilidade com campos alternativos.
$query = "SELECT s.*, u.nome_utilizador, u.email FROM servico s LEFT JOIN utilizador u ON s.email = u.email WHERE 1=1";

// Aplicar filtro
if ($filtro === 'meus') {
    $query .= " AND (s.id_mecanico = $mecanico_id OR s.mecanico_id = $mecanico_id)";
} elseif ($filtro !== 'todos') {
    // aceitar estado_do_trabalho ou estado — comparar de forma normalizada (ignorar _ e case)
    $safe_filtro = $con->real_escape_string($filtro);
    $norm_cmp = "LOWER(REPLACE(IFNULL(s.estado_do_trabalho,''),'_',' ')) = LOWER(REPLACE('$safe_filtro','_',' '))";
    $norm_cmp2 = "LOWER(REPLACE(IFNULL(s.estado,''),'_',' ')) = LOWER(REPLACE('$safe_filtro','_',' '))";
    $query .= " AND (" . $norm_cmp . " OR " . $norm_cmp2 . ")";
}

$query .= " ORDER BY COALESCE(s.data_registo, s.data_entrada, s.data_criacao) DESC LIMIT 50";

$result = $con->query($query);

if (!$result) {
    // tentativa simples fallback para tabela plural `servicos`
    $query2 = "SELECT s.*, c.nome_cliente, c.matricula FROM servicos s LEFT JOIN clientes c ON s.id_cliente = c.id_cliente WHERE 1=1";
    if ($filtro === 'meus') {
        $query2 .= " AND s.id_mecanico = $mecanico_id";
    } elseif ($filtro !== 'todos') {
        $safe_filtro = $con->real_escape_string($filtro);
        // fallback: normalizar comparação também para tabela plural
        $query2 .= " AND LOWER(REPLACE(IFNULL(s.estado,''),'_',' ')) = LOWER(REPLACE('$safe_filtro','_',' '))";
    }
    $query2 .= " ORDER BY s.data_entrada DESC LIMIT 50";
    $result = $con->query($query2);
    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => 'Erro na query (fallback): ' . $con->error
        ]);
        exit;
    }
}

$html = '';
while ($servico = $result->fetch_assoc()) {
    // Normalizar campos com possíveis nomes diferentes no DB
    $id_field = $servico['id_servico'] ?? $servico['id'] ?? '';
    $estado = $servico['estado'] ?? $servico['estado_do_trabalho'] ?? '';
    $cliente = $servico['nome_utilizador'] ?? $servico['nome_cliente'] ?? ($servico['email'] ?? 'N/A');
    $tipo = $servico['tipo_servico'] ?? $servico['descricao'] ?? '';
    $matricula = $servico['matricula'] ?? '';
    $data_entrada = $servico['data_entrada'] ?? $servico['data_registo'] ?? $servico['data_criacao'] ?? '';
    $custo = $servico['custo_final'] ?? $servico['custo'] ?? 0;

    $estado_class = 'estado-' . str_replace(' ', '_', $estado);
    $estado_badge = '<span class="estado-badge ' . $estado_class . '">' . htmlspecialchars($estado) . '</span>';
    
    // Ações — verificar id_mecanico ou mecanico_id
    $acoes = '';
    $mec_do_servico = $servico['id_mecanico'] ?? $servico['mecanico_id'] ?? null;
    if (empty($mec_do_servico)) {
        $acoes = '<button class="btn-small btn-assumir" data-servico-id="' . htmlspecialchars($id_field) . '">Assumir</button>';
    } elseif ($mec_do_servico == $mecanico_id) {
        if (stripos($estado, 'andamento') !== false || stripos($estado, 'Em Andamento') !== false) {
            $acoes = '<button class="btn-small btn-atualizar" data-servico-id="' . htmlspecialchars($id_field) . '" data-estado="' . htmlspecialchars($estado) . '">Atualizar</button>';
        } elseif (stripos($estado, 'pronto') !== false) {
            $acoes = '<button class="btn-small btn-concluir" data-servico-id="' . htmlspecialchars($id_field) . '">Concluir</button>';
        } elseif (stripos($estado, 'conclu') !== false) {
            $acoes = '<span class="btn-small btn-disabled">Concluído</span>';
        }
    } else {
        $acoes = '<span class="btn-small btn-disabled">Outro Mecânico</span>';
    }

    $html .= '<tr>';
    $html .= '<td>#' . htmlspecialchars($id_field) . '</td>';
    $html .= '<td>' . htmlspecialchars($cliente) . '</td>';
    $html .= '<td>' . htmlspecialchars($tipo) . '</td>';
    $html .= '<td>' . htmlspecialchars($matricula) . '</td>';
    $html .= '<td>' . $estado_badge . '</td>';
    $html .= '<td>' . htmlspecialchars($data_entrada) . '</td>';
    $html .= '<td>€' . number_format(floatval($custo), 2) . '</td>';
    $html .= '<td class="actions">' . $acoes . '</td>';
    $html .= '</tr>';
}

if (empty($html)) {
    $html = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #888;">Nenhuma reparação encontrada</td></tr>';
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'total_pages' => 1,
    'total' => 1
]);
?>