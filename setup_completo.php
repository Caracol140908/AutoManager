<?php
/**
 * [INICIAR] SETUP COMPLETO - AUTOMANAGER
 * Garante que TUDO funciona a 100%
 * Executa: verificações, criações de tabelas, dados de teste
 */

session_start();
require 'ligar.php';

// CORES PARA OUTPUT
$cores = [
    'sucesso' => '#28a745',
    'erro' => '#dc3545',
    'aviso' => '#ffc107',
    'info' => '#17a2b8'
];

function log_msg($msg, $tipo = 'info') {
    $cores = [
        'sucesso' => '#28a745',
        'erro' => '#dc3545',
        'aviso' => '#ffc107',
        'info' => '#17a2b8'
    ];
    $cor = $cores[$tipo] ?? '#17a2b8';
    echo "<p style='color: $cor; font-weight: bold;'>$msg</p>";
}

echo "<h1>[INICIAR] SETUP COMPLETO - AUTOMANAGER</h1>";
echo "<hr>";

// ============================================
// 1. VERIFICAR CONEXÃO
// ============================================
echo "<h2>1⃣ Verificando Conexão...</h2>";
if ($con && mysqli_connect_errno() === 0) {
    log_msg("[OK] Conexão com sucesso", 'sucesso');
} else {
    log_msg("[ERRO] ERRO: Falha ao conectar à BD", 'erro');
    die();
}

// ============================================
// 2. CRIAR TABELA MECANICOS
// ============================================
echo "<h2>2⃣ Tabela MECANICOS</h2>";

$sql_mecanicos = "CREATE TABLE IF NOT EXISTS mecanicos (
    id_mecanico INT AUTO_INCREMENT PRIMARY KEY,
    nome_mecanico VARCHAR(100) NOT NULL,
    especialidade VARCHAR(100),
    contacto VARCHAR(20),
    email VARCHAR(100),
    ativo INT DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($con, $sql_mecanicos)) {
    log_msg("[OK] Tabela 'mecanicos' OK", 'sucesso');
    
    // Verificar se há dados
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM mecanicos");
    $row = mysqli_fetch_assoc($result);
    $total_mecanicos = $row['total'];
    
    if ($total_mecanicos == 0) {
        echo "<p>[INSERIR] Inserindo dados de teste...</p>";
        $mecanicos_dados = [
            ['João Silva', 'Motor', '912345678', 'joao@AUTOMANAGER.com'],
            ['Miguel Santos', 'Suspensão', '923456789', 'miguel@AUTOMANAGER.com'],
            ['Ana Costa', 'Elétrica', '934567890', 'ana@AUTOMANAGER.com'],
            ['Carlos Mendes', 'Travões', '945678901', 'carlos@AUTOMANAGER.com'],
            ['Pedro Costa', 'Transmissão', '956789012', 'pedro@AUTOMANAGER.com'],
            ['Sofia Martins', 'Carroçaria', '967890123', 'sofia@AUTOMANAGER.com'],
            ['Ricardo Oliveira', 'Ar Condicionado', '978901234', 'ricardo@AUTOMANAGER.com'],
            ['Joana Pereira', 'Pneus', '989012345', 'joana@AUTOMANAGER.com'],
            ['Tiago Ferreira', 'Bateria', '990123456', 'tiago@AUTOMANAGER.com'],
        ];
        
        foreach ($mecanicos_dados as $m) {
            $sql = "INSERT INTO mecanicos (nome_mecanico, especialidade, contacto, email, ativo) 
                    VALUES ('{$m[0]}', '{$m[1]}', '{$m[2]}', '{$m[3]}', 1)";
            mysqli_query($con, $sql);
        }
        log_msg("[OK] Inseridos 9 mecânicos", 'sucesso');
    } else {
        log_msg("ℹ Já existem $total_mecanicos mecânicos", 'info');
    }
} else {
    log_msg("[ERRO] Erro ao criar tabela: " . mysqli_error($con), 'erro');
}

// ============================================
// 3. CRIAR TABELA REPARACOES
// ============================================
echo "<h2>3⃣ Tabela REPARACOES</h2>";

$sql_reparacoes = "CREATE TABLE IF NOT EXISTS reparacoes (
    id_reparacao INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    estado VARCHAR(50) DEFAULT 'pendente',
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    mecanico_atribuido VARCHAR(100),
    cliente_id INT,
    prioridade VARCHAR(20) DEFAULT 'normal',
    data_conclusao DATETIME NULL,
    observacoes TEXT,
    KEY idx_estado (estado)
)";

if (mysqli_query($con, $sql_reparacoes)) {
    log_msg("[OK] Tabela 'reparacoes' OK", 'sucesso');
    
    // Verificar dados
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM reparacoes");
    $row = mysqli_fetch_assoc($result);
    $total_reparacoes = $row['total'];
    
    if ($total_reparacoes == 0) {
        echo "<p>[INSERIR] Inserindo dados de teste...</p>";
        $reparacoes_dados = [
            ['Troca de óleo', 'ativa', 'João Silva', 'normal'],
            ['Reparação de travões', 'ativa', 'Pedro Costa', 'alta'],
            ['Alinhamento das rodas', 'pendente', 'Carlos Mendes', 'normal'],
            ['Substituição de pneus', 'ativa', 'João Silva', 'normal'],
            ['Reparação do sistema de arrefecimento', 'pendente', 'Pedro Costa', 'alta'],
        ];
        
        foreach ($reparacoes_dados as $r) {
            $sql = "INSERT INTO reparacoes (descricao, estado, mecanico_atribuido, prioridade) 
                    VALUES ('{$r[0]}', '{$r[1]}', '{$r[2]}', '{$r[3]}')";
            mysqli_query($con, $sql);
        }
        log_msg("[OK] Inseridas 5 reparações", 'sucesso');
    } else {
        log_msg("ℹ Já existem $total_reparacoes reparações", 'info');
    }
} else {
    log_msg("[ERRO] Erro ao criar tabela: " . mysqli_error($con), 'erro');
}

// ============================================
// 4. CRIAR TABELA CLIENTES
// ============================================
echo "<h2>4⃣ Tabela CLIENTES</h2>";

$sql_clientes = "CREATE TABLE IF NOT EXISTS clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefone VARCHAR(20),
    ativo INT DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($con, $sql_clientes)) {
    log_msg("[OK] Tabela 'clientes' OK", 'sucesso');
    
    // Verificar dados
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM clientes WHERE ativo = 1");
    $row = mysqli_fetch_assoc($result);
    $total_clientes = $row['total'];
    
    if ($total_clientes < 5) {
        echo "<p>[INSERIR] Inserindo dados de teste...</p>";
        $clientes_dados = [
            ['João Alves', 'joao.alves@email.com', '912345678'],
            ['Maria Silva', 'maria.silva@email.com', '923456789'],
            ['Pedro Santos', 'pedro.santos@email.com', '934567890'],
            ['Ana Costa', 'ana.costa@email.com', '945678901'],
            ['Carlos Mendes', 'carlos.mendes@email.com', '956789012'],
        ];
        
        foreach ($clientes_dados as $c) {
            $sql = "INSERT INTO clientes (nome, email, telefone, ativo) 
                    VALUES ('{$c[0]}', '{$c[1]}', '{$c[2]}', 1)";
            mysqli_query($con, $sql);
        }
        log_msg("[OK] Inseridos 5+ clientes", 'sucesso');
    } else {
        log_msg("ℹ Já existem $total_clientes clientes ativos", 'info');
    }
} else {
    log_msg("[ERRO] Erro ao criar tabela: " . mysqli_error($con), 'erro');
}

// ============================================
// 5. CRIAR TABELA CHEFES_OFICINA
// ============================================
echo "<h2>5⃣ Tabela CHEFES_OFICINA</h2>";

$sql_chefes = "CREATE TABLE IF NOT EXISTS chefes_oficina (
    id_chefe INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    nivel_acesso VARCHAR(50) DEFAULT 'chefe',
    primeiro_login INT DEFAULT 1,
    ativo INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($con, $sql_chefes)) {
    log_msg("[OK] Tabela 'chefes_oficina' OK", 'sucesso');
} else {
    log_msg("[ERRO] Erro ao criar tabela: " . mysqli_error($con), 'erro');
}

// ============================================
// 5. CRIAR TABELA SERVICO
// ============================================
echo "<h2>5⃣ Tabela SERVICO</h2>";

$sql_servico = "CREATE TABLE IF NOT EXISTS servico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tipo_servico VARCHAR(255),
    data_desejada DATETIME,
    estado VARCHAR(50) DEFAULT 'pendente',
    email VARCHAR(255),
    custo DECIMAL(10,2) DEFAULT 0.00,
    estado_do_trabalho VARCHAR(50) DEFAULT 'Por Fazer',
    token_pagamento VARCHAR(255),
    estado_pagamento VARCHAR(50) DEFAULT 'Pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($con, $sql_servico)) {
    log_msg("[OK] Tabela 'servico' OK", 'sucesso');
} else {
    log_msg("[ERRO] Erro ao criar tabela: " . mysqli_error($con), 'erro');
}

// ============================================
// 6. RESUMO FINAL
// ============================================
echo "<h2>[RELATORIO] RESUMO FINAL</h2>";
echo "<hr>";

$tabelas = ['mecanicos', 'reparacoes', 'clientes', 'chefes_oficina', 'servico'];
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #333;'>";
echo "<th>Tabela</th><th>Registos</th><th>Status</th>";
echo "</tr>";

foreach ($tabelas as $tabela) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM $tabela");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total = $row['total'];
        $status = $total > 0 ? '[OK] OK' : '[AVISO] VAZIA';
        $cor = $total > 0 ? '#28a745' : '#ffc107';
        echo "<tr>";
        echo "<td><strong>$tabela</strong></td>";
        echo "<td>$total</td>";
        echo "<td style='color: $cor;'><strong>$status</strong></td>";
        echo "</tr>";
    }
}
echo "</table>";

echo "<hr>";
log_msg("[OK] SETUP COMPLETO COM SUCESSO!", 'sucesso');
echo "<p><a href='indexchefedeoficina.php' style='color: #ff6b00; font-weight: bold; font-size: 1.2em;'>[INICIAR] Ir para o Painel do Chefe →</a></p>";

mysqli_close($con);
?>
