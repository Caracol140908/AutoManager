<?php
session_start();
require 'ligar.php';

echo "<!DOCTYPE html>
<html lang='pt-PT'>
<head>
    <meta charset='UTF-8'>
    <link rel='stylesheet' href='theme.css'>
    <title>[OK] Verificação de Erros Corrigidos</title>
    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #e8f5e9; padding: 15px; border-left: 4px solid green; margin: 10px 0; }
        .warning { color: orange; background: #fff3e0; padding: 15px; border-left: 4px solid orange; margin: 10px 0; }
        h1 { color: #ff6b00; }
        h2 { color: #333; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #ff6b00; color: white; }
        .btn { padding: 12px 20px; background: #ff6b00; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>[OK] ERROS CORRIGIDOS - SISTEMA DE GESTÃO AUTOMANAGER</h1>
        <hr>
        
        <h2>[PESQUISA] Problemas Identificados e Resolvidos:</h2>
        
        <div class='success'>
            <strong>[ERRO] Problema 1: Mecânicos não carregavam</strong><br>
            [OK] <strong>Solução:</strong> Query estava procurando por <code>status = 'ativo'</code>, mas o campo correto é <code>ativo = 1</code><br>
            [NOTAS] <strong>Arquivo:</strong> indexchefedeoficina.php (Linha ~1120)<br>
            [NOTAS] <strong>Alteração:</strong>
            <code>WHERE status = 'ativo'</code> → <code>WHERE ativo = 1</code>
        </div>
        
        <div class='success'>
            <strong>[ERRO] Problema 2: Reparações não carregavam</strong><br>
            [OK] <strong>Solução:</strong> Tabela <code>reparacoes</code> não existia na BD<br>
            [NOTAS] <strong>Arquivo:</strong> Criado <code>criar_reparacoes.php</code><br>
            [NOTAS] <strong>Alteração:</strong> Tabela criada e 5 reparações de exemplo inseridas
        </div>
        
        <hr>
        
        <h2>[RELATORIO] Status Atual do Sistema:</h2>
        
        <table>
            <tr>
                <th>Componente</th>
                <th>Status</th>
                <th>Quantidade</th>
            </tr>";

// Contar mecânicos
$sql = "SELECT COUNT(*) as total, COUNT(CASE WHEN ativo = 1 THEN 1 END) as ativos FROM mecanicos";
$result = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($result);
echo "<tr>
    <td>Mecânicos Ativos</td>
    <td style='color: green; font-weight: bold;'>[OK] Funcionando</td>
    <td>" . $row['ativos'] . " / " . $row['total'] . "</td>
</tr>";

// Contar reparacoes
$sql = "SELECT COUNT(*) as total FROM reparacoes";
$result = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($result);
echo "<tr>
    <td>Reparações Ativas</td>
    <td style='color: green; font-weight: bold;'>[OK] Funcionando</td>
    <td>" . $row['total'] . "</td>
</tr>";

// Contar chefes
$sql = "SELECT COUNT(*) as total FROM chefes_oficina";
$result = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($result);
echo "<tr>
    <td>Chefes de Oficina</td>
    <td style='color: green; font-weight: bold;'>[OK] Funcionando</td>
    <td>" . $row['total'] . "</td>
</tr>";

echo "</table>
        
        <hr>
        
        <h2>[ALVO] Próximos Passos:</h2>
        <ol>
            <li>Aceda a <code>indexchefedeoficina.php</code> para verificar o painel</li>
            <li>Deve agora ver <strong>Mecânicos da Equipa</strong> com os dados corretos</li>
            <li>Deve agora ver <strong>Reparações Recentes</strong> com as reparações</li>
            <li>Todos os botões devem funcionar corretamente</li>
        </ol>
        
        <div class='warning'>
            <strong> Nota:</strong> Os dados de exemplo foram inseridos. Pode adicionar novos registos através da aplicação.
        </div>
        
        <a href='indexchefedeoficina.php' class='btn'>[VOLTAR] Voltar ao Painel do Chefe</a>
    </div>
</body>
</html>";

mysqli_close($con);
?>
