<?php
session_start();
require 'ligar.php';

echo "<h2>[FERRAMENTAS] CRIANDO TABELA REPARACOES</h2>";
echo "<hr>";

// 1. Criar tabela reparacoes
$sql_criar = "CREATE TABLE IF NOT EXISTS reparacoes (
    id_reparacao INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    estado VARCHAR(50) DEFAULT 'pendente',
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    mecanico_atribuido VARCHAR(100),
    cliente_id INT,
    prioridade VARCHAR(20) DEFAULT 'normal',
    data_conclusao DATETIME NULL,
    observacoes TEXT
)";

if (mysqli_query($con, $sql_criar)) {
    echo "<p style='color: green;'>[OK] Tabela 'reparacoes' criada com sucesso!</p>";
} else {
    echo "<p style='color: red;'>[ERRO] Erro ao criar tabela: " . mysqli_error($con) . "</p>";
}

echo "<br>";

// 2. Inserir dados de exemplo
$inserires = [
    "INSERT INTO reparacoes (descricao, estado, mecanico_atribuido, prioridade) VALUES ('Troca de óleo', 'ativa', 'João Silva', 'normal')",
    "INSERT INTO reparacoes (descricao, estado, mecanico_atribuido, prioridade) VALUES ('Reparação de travões', 'ativa', 'Pedro Costa', 'alta')",
    "INSERT INTO reparacoes (descricao, estado, mecanico_atribuido, prioridade) VALUES ('Alinhamento das rodas', 'pendente', 'Carlos Mendes', 'normal')",
    "INSERT INTO reparacoes (descricao, estado, mecanico_atribuido, prioridade) VALUES ('Substituição de pneus', 'ativa', 'João Silva', 'normal')",
    "INSERT INTO reparacoes (descricao, estado, mecanico_atribuido, prioridade) VALUES ('Reparação do sistema de arrefecimento', 'pendente', 'Pedro Costa', 'alta')",
];

foreach ($inserires as $sql) {
    if (mysqli_query($con, $sql)) {
        echo "<p>[OK] Registo inserido</p>";
    } else {
        // Se já existe, ignorar
        if (mysqli_errno($con) != 1062) {
            echo "<p style='color: orange;'>[AVISO] " . mysqli_error($con) . "</p>";
        }
    }
}

echo "<br><br>";

// 3. Contar total
$sql_count = "SELECT COUNT(*) as total FROM reparacoes";
if ($result = mysqli_query($con, $sql_count)) {
    $row = mysqli_fetch_assoc($result);
    echo "<p style='color: green; font-size: 18px;'>[OK] <strong>" . $row['total'] . " reparações</strong> agora estão disponíveis!</p>";
}

echo "<br>";
echo "<a href='indexchefedeoficina.php' style='padding: 10px 20px; background: #ff6b00; color: white; text-decoration: none; border-radius: 5px;'>[VOLTAR] Voltar ao Painel</a>";

mysqli_close($con);
?>
