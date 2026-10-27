<?php
// indexchefedeoficina.php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_nif.php';
require_once __DIR__ . '/inc_iban.php';
require_once __DIR__ . '/inc_telemovel.php';
require_once __DIR__ . '/inc_password_padrao.php';

am_garantir_nif_unico_schema($con);
am_garantir_iban_unico_schema($con);

// VERIFICAR SE ESTÁ LOGADO
if (!isset($_SESSION['chefe_id']) || !isset($_SESSION['is_chefe'])) {
    header('Location: login_chefe.php');
    exit;
}

$chefe_id = $_SESSION['chefe_id'];
$chefe_email = $_SESSION['chefe_email'] ?? '';
$chefe_nome = $_SESSION['chefe_nome'] ?? 'Chefe de Oficina';

// Download de logs (do banco) se solicitado
if (isset($_GET['download_log'])) {
    $origem = $_GET['download_log'];
    $stmt = $con->prepare("SELECT criado_em, mensagem, dados FROM logs WHERE origem = ? ORDER BY criado_em DESC");
    if ($stmt) {
        $stmt->bind_param('s', $origem);
        $stmt->execute();
        $res = $stmt->get_result();

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origem) . '.log"');
        while ($row = $res->fetch_assoc()) {
            echo $row['criado_em'] . " - " . $row['mensagem'] . "\n";
            if (!empty($row['dados'])) echo $row['dados'] . "\n";
        }
        $stmt->close();
        exit;
    } else {
        $_SESSION['mensagem_erro'] = 'Ficheiro de log não encontrado.';
    }
}

// VALIDAÇÃO DE DADOS DE FUNCIONÁRIOS
function validarDadosFuncionario($con, $dados, $tipo, array $excluir = []) {
    $erros = [];
    
    if (empty($dados['nome'])) {
        $erros[] = "Nome é obrigatório";
    }
    
    if (empty($dados['email'])) {
        $erros[] = "Email é obrigatório";
    } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Email inválido";
    }
    
    if (!empty($dados['nif'])) {
        [$nifOk, $nifMsg] = am_validar_nif_unico($con, (string) $dados['nif'], $excluir);
        if (!$nifOk) {
            $erros[] = $nifMsg;
        }
    }
    
    if (!empty($dados['iban'])) {
        [$ibanOk, $ibanMsg] = am_validar_iban_unico($con, (string) $dados['iban'], $excluir);
        if (!$ibanOk) {
            $erros[] = $ibanMsg;
        }
    }
    
    if (!empty($dados['salario'])) {
        if (!is_numeric($dados['salario']) || $dados['salario'] < 0) {
            $erros[] = "Salário deve ser um número não-negativo";
        }
    }
    
    if ($tipo === 'mecanico' && empty($dados['especialidade'])) {
        $erros[] = "Especialidade é obrigatória para mecânicos";
    }
    
    if ($tipo === 'mecanico') {
        if (empty($dados['telefone'])) {
            $erros[] = "Telefone é obrigatório para mecânicos";
        } else {
            [$telOk, $telMsg] = am_validar_telemovel_portugues((string) $dados['telefone'], true);
            if (!$telOk) {
                $erros[] = $telMsg;
            }
        }
    } elseif (!empty($dados['telefone'])) {
        [$telOk, $telMsg] = am_validar_telemovel_portugues((string) $dados['telefone']);
        if (!$telOk) {
            $erros[] = $telMsg;
        }
    }
    
    return $erros;
}

// FUNÇÃO PARA CRIAR FUNCIONÁRIOS
function criarFuncionario($con, $tipo, $dados) {
    $tabela_config = [
        'mecanico' => [
            'tabela' => 'mecanicos',
            'id' => 'id_mecanico',
            'nome_campo' => 'nome_mecanico',
            'email_campo' => 'email',
            'campos' => ['nome_mecanico', 'email', 'password', 'morada', 'contacto', 'ordenado_bruto', 'data_contratacao', 'nif', 'iban', 'especialidade', 'primeiro_login'],
            'tipos' => 'ssssdssssi'
        ]
    ];
    
    if (!isset($tabela_config[$tipo])) {
        return ['sucesso' => false, 'mensagem' => 'Tipo de funcionário inválido'];
    }
    
    $config = $tabela_config[$tipo];
    $tabela = $config['tabela'];
    
    // Validar dados
    $erros_validacao = validarDadosFuncionario($con, $dados, $tipo);
    if (!empty($erros_validacao)) {
        return ['sucesso' => false, 'mensagem' => 'Erros de validação: ' . implode(', ', $erros_validacao)];
    }
    
    // Verificar se email já existe
    $check_id = $config['id'];
    $check = $con->prepare("SELECT {$check_id} FROM $tabela WHERE email = ? LIMIT 1");
    if ($check) {
        $check->bind_param("s", $dados['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            return ['sucesso' => false, 'mensagem' => 'Este email já está registado no sistema'];
        }
        $check->close();
    }
    
    // Gerar password
    $password = am_password_padrao_funcionario();
    $hash = am_hash_password_padrao_funcionario();
    
    // Extrair dados base - usar o campo nome correto
    $nome_valor = $dados['nome'];
    $email = $dados['email'];
    $morada = $dados['morada'] ?? '';
    $telefone = $dados['telefone'] ?? '';
    $salario = !empty($dados['salario']) ? floatval($dados['salario']) : 1000;
    $data_contratacao = $dados['data_contratacao'] ?? date('Y-m-d');
    $nif = $dados['nif'] ?? '';
    $iban = $dados['iban'] ?? '';
    $especialidade = $dados['especialidade'] ?? '';
    
    // Preparar dados para inserção baseado na configuração
    $valores_bind = [];
    
    // Processar cada campo na ordem definida pela configuração
    foreach ($config['campos'] as $campo) {
        if ($campo === 'email') {
            $valores_bind[] = $email;
        } elseif ($campo === 'password') {
            $valores_bind[] = $hash;
        } elseif (in_array($campo, ['nome', 'nome_mecanico'])) {
            $valores_bind[] = $nome_valor;
        } elseif (in_array($campo, ['morada'])) {
            $valores_bind[] = $morada;
        } elseif (in_array($campo, ['telefone', 'contacto'])) {
            $valores_bind[] = $telefone !== '' ? am_normalizar_telemovel((string) $telefone) : '';
        } elseif (in_array($campo, ['salario_bruto', 'ordenado_bruto'])) {
            $valores_bind[] = $salario;
        } elseif ($campo === 'data_contratacao') {
            $valores_bind[] = $data_contratacao;
        } elseif ($campo === 'nif') {
            $valores_bind[] = !empty($nif) ? am_normalizar_nif((string) $nif) : '';
        } elseif ($campo === 'iban') {
            $valores_bind[] = !empty($iban) ? am_normalizar_iban((string) $iban) : '';
        } elseif ($campo === 'especialidade') {
            $valores_bind[] = $especialidade;
        } elseif ($campo === 'primeiro_login') {
            $valores_bind[] = 1;
        }
    }
    
    // Inserir na base de dados
    $placeholders = implode(', ', array_fill(0, count($config['campos']), '?'));
    $campos_sql = implode(', ', $config['campos']);
    
    $sql = "INSERT INTO $tabela ($campos_sql) VALUES ($placeholders)";
    $stmt = $con->prepare($sql);
    
    if (!$stmt) {
        return ['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $con->error];
    }
    
    // Ligar os valores dinamicamente usando bind_param corretamente
    $tipos = $config['tipos'];
    
    // Criar referências para os valores
    $refs = [];
    foreach ($valores_bind as &$valor) {
        $refs[] = &$valor;
    }
    
    // Usar call_user_func_array com as referências corretas
    call_user_func_array([$stmt, 'bind_param'], array_merge([$tipos], $refs));
    
    if (!$stmt->execute()) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao inserir: ' . $stmt->error];
    }
    
    $stmt->close();
    
    // ============= ENVIAR EMAIL DE BOAS-VINDAS =============
    $tipo_pt = 'Mecânico';
    
    // Construir mensagem HTML de email
    $assunto = "Bem-vindo à AUTOMANAGER - Credenciais de Acesso";
    
    $mensagem_email = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
            .header { background: linear-gradient(135deg, #ff6b00 0%, #e55a00 100%); color: white; padding: 40px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 32px; font-weight: 300; letter-spacing: 2px; }
            .header p { margin: 5px 0 0 0; font-size: 14px; opacity: 0.9; }
            .content { padding: 40px; }
            .welcome { font-size: 18px; color: #333; margin-bottom: 20px; }
            .credentials { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #ff6b00; margin: 20px 0; }
            .credentials h3 { color: #333; margin-top: 0; margin-bottom: 15px; }
            .credential-item { display: flex; margin-bottom: 12px; align-items: flex-start; }
            .credential-label { font-weight: 600; color: #333; min-width: 100px; }
            .credential-value { color: #666; word-break: break-all; }
            .password-box { background: #fff3e0; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #ffb41a; }
            .password-box strong { color: #d97706; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            .signature { color: #ff6b00; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1> AUTOMANAGER</h1>
                <p>Sistema de Gestão de Oficina Automóvel</p>
            </div>
            
            <div class='content'>
                <p class='welcome'>Olá <strong>$nome_valor</strong>,</p>
                
                <p style='color: #666; line-height: 1.6;'>
                    Bem-vindo(a) à equipa da <strong style='color: #ff6b00;'>AUTOMANAGER</strong> como <strong>$tipo_pt</strong>!
                </p>
                
                <p style='color: #666; line-height: 1.6;'>
                    Estamos entusiasmados por tê-lo(a) conosco. Abaixo encontra as suas credenciais de acesso ao sistema.
                </p>
                
                <div class='credentials'>
                    <h3>[SEGURANCA] Credenciais de Acesso</h3>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Email:</div>
                        <div class='credential-value'><strong>$email</strong></div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Password:</div>
                        <div class='credential-value'><code style='background: #f0f0f0; padding: 2px 6px; border-radius: 3px;'><strong>$password</strong></code></div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>URL de Acesso:</div>
                        <div class='credential-value'><a href='http://" . $_SERVER['HTTP_HOST'] . "/automanager/login.php' style='color: #ff6b00; text-decoration: none;'>http://" . $_SERVER['HTTP_HOST'] . "/automanager/login.php</a></div>
                    </div>
                </div>
                
                <div class='password-box'>
                    <strong>[AVISO] Importante:</strong> Esta é a palavra-passe inicial da oficina. No primeiro login serás obrigado(a) a definir uma nova palavra-passe pessoal.
                </div>
                
                <p style='color: #666; line-height: 1.6; margin-top: 30px;'>
                    <strong>Próximos passos:</strong>
                </p>
                <ul style='color: #666; line-height: 1.8;'>
                    <li>Aceda ao sistema com os dados fornecidos acima</li>
                    <li>Altere a sua password de segurança</li>
                    <li>Complete o seu perfil com informações adicionais</li>
                    <li>Explore as funcionalidades disponíveis</li>
                </ul>
                
                <p style='color: #666; margin-top: 30px;'>
                    Se tiver dúvidas ou encontrar alguma dificuldade, por favor contacte a equipa de administração.
                </p>
            </div>
            
            <div class='footer'>
                <p>© 2026 AUTOMANAGER - Oficina Automóvel</p>
                <p style='margin-top: 10px; color: #ccc;'>Este é um email automático, por favor não responda.</p>
            </div>
        </div>
    </body>
    </html>";

    // Enviar email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: AUTOMANAGER RH <rh@AUTOMANAGER.pt>\r\n";
    $headers .= "Reply-To: rh@AUTOMANAGER.pt\r\n";
    
    if (@mail($email, $assunto, $mensagem_email, $headers)) {
        $msg_email = " (Email de boas-vindas enviado)";
    } else {
        $msg_email = " (Aviso: Não foi possível enviar o email)";
    }
    
    return ['sucesso' => true, 'mensagem' => "[OK] $tipo_pt criado(a) com sucesso!$msg_email\n\nEmail: $email\n" . strip_tags(am_texto_credenciais_funcionario_html())];
}

// PROCESSAR AÇÕES DE FUNCIONÁRIOS E CRIAÇÃO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    // CRIAR NOVO MECÂNICO
    if ($acao === 'criar_mecanico') {
        $resultado = criarFuncionario($con, 'mecanico', [
            'nome' => $_POST['nome'] ?? '',
            'email' => $_POST['email'] ?? '',
            'morada' => $_POST['morada'] ?? '',
            'telefone' => $_POST['telefone'] ?? '',
            'salario' => $_POST['salario'] ?? 1000,
            'data_contratacao' => $_POST['data_contratacao'] ?? date('Y-m-d'),
            'nif' => $_POST['nif'] ?? '',
            'iban' => $_POST['iban'] ?? '',
            'especialidade' => $_POST['especialidade'] ?? ''
        ]);
        
        if ($resultado['sucesso']) {
            $_SESSION['mensagem_sucesso'] = $resultado['mensagem'];
        } else {
            $_SESSION['mensagem_erro'] = $resultado['mensagem'];
        }
        header('Location: indexchefedeoficina.php');
        exit();
    }
    
    $funcionario_id = $_POST['funcionario_id'] ?? 0;
    $tipo_funcionario = $_POST['tipo_funcionario'] ?? 'mecanico';
    
    if ($acao && $acao !== 'criar_mecanico' && $funcionario_id && $tipo_funcionario === 'mecanico') {
        switch ($acao) {
            case 'despedir':
                $sql_update = "UPDATE mecanicos SET ativo = 0 WHERE id_mecanico = ?";
                
                if ($stmt = mysqli_prepare($con, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "i", $funcionario_id);
                    mysqli_stmt_execute($stmt);
                    $_SESSION['mensagem_sucesso'] = "Funcionário despedido com sucesso!";
                }
                break;
                
            case 'aumentar_salario':
                $percentagem = $_POST['percentagem'] ?? 10;
                $sql_salario = "SELECT ordenado_bruto FROM mecanicos WHERE id_mecanico = ?";
                
                if ($stmt = mysqli_prepare($con, $sql_salario)) {
                    mysqli_stmt_bind_param($stmt, "i", $funcionario_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $salario_atual);
                    mysqli_stmt_fetch($stmt);
                    mysqli_stmt_close($stmt);
                    
                    $novo_salario = $salario_atual * (1 + ($percentagem / 100));
                    $sql_update = "UPDATE mecanicos SET ordenado_bruto = ? WHERE id_mecanico = ?";
                    
                    if ($stmt = mysqli_prepare($con, $sql_update)) {
                        mysqli_stmt_bind_param($stmt, "di", $novo_salario, $funcionario_id);
                        mysqli_stmt_execute($stmt);
                        $_SESSION['mensagem_sucesso'] = "Salário aumentado em $percentagem%!";
                    }
                }
                break;
                
            case 'diminuir_salario':
                $percentagem = $_POST['percentagem'] ?? 5;
                $sql_salario = "SELECT ordenado_bruto FROM mecanicos WHERE id_mecanico = ?";
                
                if ($stmt = mysqli_prepare($con, $sql_salario)) {
                    mysqli_stmt_bind_param($stmt, "i", $funcionario_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $salario_atual);
                    mysqli_stmt_fetch($stmt);
                    mysqli_stmt_close($stmt);
                    
                    $novo_salario = $salario_atual * (1 - ($percentagem / 100));
                    if ($novo_salario < 760) $novo_salario = 760;
                    $sql_update = "UPDATE mecanicos SET ordenado_bruto = ? WHERE id_mecanico = ?";
                    
                    if ($stmt = mysqli_prepare($con, $sql_update)) {
                        mysqli_stmt_bind_param($stmt, "di", $novo_salario, $funcionario_id);
                        mysqli_stmt_execute($stmt);
                        $_SESSION['mensagem_sucesso'] = "Salário diminuído em $percentagem%!";
                    }
                }
                break;
                
            case 'promover':
                $nova_funcao = $_POST['nova_funcao'] ?? 'Senior';
                $sql_update = "UPDATE mecanicos SET especialidade = ? WHERE id_mecanico = ?";
                
                if ($stmt = mysqli_prepare($con, $sql_update)) {
                    mysqli_stmt_bind_param($stmt, "si", $nova_funcao, $funcionario_id);
                    mysqli_stmt_execute($stmt);
                    $_SESSION['mensagem_sucesso'] = "Funcionário promovido para $nova_funcao!";
                }
                break;
        }
        
        header('Location: indexchefedeoficina.php');
        exit();
    }
}

// BUSCAR ESTATÍSTICAS
$stats = [
    'servicos_ativos' => 0,
    'servicos_concluidos' => 0,
    'mecanicos_ativos' => 0,
    'total_funcionarios' => 0,
    'clientes_ativos' => 0,
    'salario_medio_mecanicos' => 0,
    'total_servicos' => 0
];

// Contar serviços ativos (verifica ambos os campos estado e estado_do_trabalho)
$sql = "SELECT COUNT(*) as total FROM servico 
        WHERE (estado_do_trabalho IN ('Em Andamento', 'Por Fazer', 'Aguardar Preparação', 'emandamento', 'por fazer')
           OR estado IN ('Em Andamento', 'Por Fazer', 'Aguardar Preparação', 'emandamento', 'por fazer', 'progresso'))
        AND (estado_do_trabalho != 'Concluído' AND estado_do_trabalho != 'concluido' 
             AND estado != 'Concluído' AND estado != 'concluido' AND estado != 'concluído')";
$result = mysqli_query($con, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['servicos_ativos'] = intval($row['total'] ?? 0);
} else {
    $stats['servicos_ativos'] = 0;
}

// Contar serviços concluídos
$sql = "SELECT COUNT(*) as total FROM servico 
        WHERE (estado_do_trabalho IN ('Concluído', 'concluido', 'concluído', 'Pronto')
           OR estado IN ('Concluído', 'concluido', 'concluído', 'concluido'))";
$result = mysqli_query($con, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['servicos_concluidos'] = intval($row['total'] ?? 0);
} else {
    $stats['servicos_concluidos'] = 0;
}

// Contar mecânicos ativos
$sql = "SELECT COUNT(*) as total FROM mecanicos WHERE ativo = 1";
$result = mysqli_query($con, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['mecanicos_ativos'] = $row['total'] ?? 0;
}

// Total funcionários (apenas mecânicos)
$stats['total_funcionarios'] = $stats['mecanicos_ativos'];

// Calcular salário médio mecânicos
$sql = "SELECT AVG(ordenado_bruto) as media FROM mecanicos WHERE ativo = 1";
$result = mysqli_query($con, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['salario_medio_mecanicos'] = number_format($row['media'] ?? 0, 2, ',', '.');
}

// Calcular salário médio geral (mecânicos)
$salario_total_mecanicos = 0;
$sql_mec = "SELECT SUM(ordenado_bruto) as total FROM mecanicos WHERE ativo = 1";
$result_mec = mysqli_query($con, $sql_mec);
if ($result_mec) {
    $row = mysqli_fetch_assoc($result_mec);
    $salario_total_mecanicos = $row['total'] ?? 0;
}

$total_salarios = $salario_total_mecanicos;
$total_func = $stats['mecanicos_ativos'];
$stats['salario_medio_geral'] = $total_func > 0 ? number_format($total_salarios / $total_func, 2, ',', '.') : '0,00';

// Contar clientes ativos (assumindo tabela 'utilizador')
$sql = "SELECT COUNT(*) as total FROM utilizador WHERE ativo = 1";
$result = mysqli_query($con, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['clientes_ativos'] = $row['total'] ?? 0;
}

// Contar total de serviços
$sql = "SELECT COUNT(*) as total FROM servico";
$result = mysqli_query($con, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $stats['total_servicos'] = $row['total'] ?? 0;
}

// DADOS PARA GRÁFICOS - COM TRATAMENTO DE ERROS ROBUSTO
// Gráfico de serviços por mês (últimos 6 meses)
$grafico_servicos_mes = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $mes_formatado = date('M/Y', strtotime("$mes-01"));
    
    // Tenta com data_registo primeiro, depois created_at, depois qualquer campo de data
    $sql = "(SELECT COUNT(*) as total FROM servico WHERE DATE_FORMAT(data_registo, '%Y-%m') = ?)
            UNION ALL
            (SELECT COUNT(*) as total FROM servico WHERE DATE_FORMAT(created_at, '%Y-%m') = ?)
            LIMIT 1";
    
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) as total FROM servico WHERE DATE_FORMAT(COALESCE(data_registo, created_at, NOW()), '%Y-%m') = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $mes);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $grafico_servicos_mes[] = [
                'mes' => $mes_formatado,
                'total' => intval($row['total'] ?? 0)
            ];
        }
        mysqli_stmt_close($stmt);
    } else {
        // Fallback: busca simples
        $sql_simple = "SELECT COUNT(*) as total FROM servico";
        $result_simple = mysqli_query($con, $sql_simple);
        $grafico_servicos_mes[] = [
            'mes' => $mes_formatado,
            'total' => 0
        ];
    }
}

// Garantir que sempre temos 6 meses
if (count($grafico_servicos_mes) < 6) {
    $grafico_servicos_mes = [];
    for ($i = 5; $i >= 0; $i--) {
        $mes = date('Y-m', strtotime("-$i months"));
        $grafico_servicos_mes[] = [
            'mes' => date('M/Y', strtotime("$mes-01")),
            'total' => 0
        ];
    }
}

// Gráfico de estados de serviços (com fallback robusto)
$grafico_estados = [];
$sql = "SELECT 
            CASE 
                WHEN estado_do_trabalho IS NOT NULL AND estado_do_trabalho != '' THEN estado_do_trabalho
                WHEN estado IS NOT NULL AND estado != '' THEN estado
                ELSE 'Sem Estado'
            END as estado,
            COUNT(*) as total 
        FROM servico 
        GROUP BY 
            CASE 
                WHEN estado_do_trabalho IS NOT NULL AND estado_do_trabalho != '' THEN estado_do_trabalho
                WHEN estado IS NOT NULL AND estado != '' THEN estado
                ELSE 'Sem Estado'
            END
        ORDER BY total DESC";

$result = mysqli_query($con, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $estado = trim($row['estado'] ?? 'Sem Estado');
        if (empty($estado)) $estado = 'Sem Estado';
        $grafico_estados[] = [
            'estado' => $estado,
            'total' => intval($row['total'] ?? 0)
        ];
    }
} else {
    // Fallback: dados vazios
    $grafico_estados = [
        ['estado' => 'Sem Dados', 'total' => 0]
    ];
}

// Gráfico de distribuição de funcionários por especialidade
$grafico_especialidades = [];
$especialidades_temp = [];

// Buscar de mecanicos
$sql_mec = "SELECT especialidade, COUNT(*) as total FROM mecanicos WHERE ativo = 1 AND especialidade IS NOT NULL AND especialidade != '' GROUP BY especialidade";
$result_mec = mysqli_query($con, $sql_mec);
if ($result_mec) {
    while ($row = mysqli_fetch_assoc($result_mec)) {
        $esp = trim($row['especialidade'] ?? 'Sem Especialidade');
        if (empty($esp)) $esp = 'Sem Especialidade';
        if (!isset($especialidades_temp[$esp])) {
            $especialidades_temp[$esp] = 0;
        }
        $especialidades_temp[$esp] += intval($row['total'] ?? 0);
    }
}

// Converter para array ordenado
foreach ($especialidades_temp as $esp => $total) {
    if ($total > 0) {
        $grafico_especialidades[] = [
            'especialidade' => $esp,
            'total' => $total
        ];
    }
}

// Ordenar por total (maior primeiro)
usort($grafico_especialidades, function($a, $b) {
    return $b['total'] - $a['total'];
});

// Se não houver dados, adicionar fallback
if (empty($grafico_especialidades)) {
    $grafico_especialidades = [
        ['especialidade' => 'Sem Dados', 'total' => 0]
    ];
}

// Gráfico de salários (top 10 funcionários)
$grafico_salarios = [];
$todos_salarios = [];

// Buscar mecânicos
$sql_mec = "SELECT nome_mecanico as nome, ordenado_bruto as salario FROM mecanicos WHERE ativo = 1 AND ordenado_bruto > 0 ORDER BY ordenado_bruto DESC LIMIT 10";
$result_mec = mysqli_query($con, $sql_mec);
if ($result_mec) {
    while ($row = mysqli_fetch_assoc($result_mec)) {
        $nome = trim($row['nome'] ?? 'Sem Nome');
        if (!empty($nome)) {
            $todos_salarios[] = [
                'nome' => substr($nome, 0, 20),
                'salario' => floatval($row['salario'] ?? 0)
            ];
        }
    }
}

// Ordenar por salário e pegar top 10
usort($todos_salarios, function($a, $b) {
    return $b['salario'] - $a['salario'];
});

$grafico_salarios = array_slice($todos_salarios, 0, 10);

// Fallback se vazio
if (empty($grafico_salarios)) {
    $grafico_salarios = [
        ['nome' => 'Sem Dados', 'salario' => 0]
    ];
}

// Gráfico de serviços por mecânico
$grafico_servicos_mecanico = [];
$sql = "SELECT 
            m.nome_mecanico as nome, 
            COUNT(s.id) as total 
        FROM mecanicos m 
        LEFT JOIN servico s ON (m.id_mecanico = s.id_mecanico OR m.id_mecanico = s.mecanico_id)
        WHERE m.ativo = 1 
        GROUP BY m.id_mecanico, m.nome_mecanico 
        HAVING total > 0
        ORDER BY total DESC 
        LIMIT 10";

$result = mysqli_query($con, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $nome = trim($row['nome'] ?? 'Sem Nome');
        if (!empty($nome)) {
            $grafico_servicos_mecanico[] = [
                'nome' => substr($nome, 0, 20),
                'total' => intval($row['total'] ?? 0)
            ];
        }
    }
}

// Fallback se vazio
if (empty($grafico_servicos_mecanico)) {
    $grafico_servicos_mecanico = [
        ['nome' => 'Sem Dados', 'total' => 0]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Chefe de Oficina | AUTOMANAGER</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #cc5500;
            --primary-light: #ff8c33;
            --dark: #000000;
            --dark-light: #111111;
            --dark-lighter: #181818;
            --text: #ffffff;
            --text-secondary: #d4d4d4;
            --text-muted: #a3a3a3;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #ea580c;
            --border: rgba(255, 122, 26, 0.22);
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.45);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(180deg, #000000 0%, #0a0a0a 100%);
            font-family: 'Inter', sans-serif;
            color: var(--text);
            min-height: 100vh;
        }
        
        .chefe-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .chefe-header {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            border: 2px solid var(--primary);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .chefe-header {
                flex-direction: column;
                text-align: center;
            }
        }
        
        .header-left h1 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .subtitle {
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .subtitle strong {
            color: var(--text);
        }
        
        .user-email {
            background: rgba(255, 107, 0, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 107, 0, 0.3);
        }
        
        .btn-logout {
            background: rgba(255, 107, 0, 0.1);
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .btn-logout:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 107, 0, 0.3);
        }
        
        .btn-logout:active {
            transform: translateY(-1px);
        }
        
        .mensagem.sucesso {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.2) 0%, rgba(40, 167, 69, 0.1) 100%);
            border: 2px solid var(--success);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }
        
        .mensagem.sucesso i {
            color: var(--success);
            font-size: 1.8rem;
        }
        
        .mensagem.info {
            background: linear-gradient(135deg, rgba(255, 107, 0, 0.18) 0%, rgba(255, 107, 0, 0.06) 100%);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .mensagem.info i {
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--dark-light) 0%, var(--dark-lighter) 100%);
            border-radius: 16px;
            padding: 25px;
            border: 2px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 107, 0, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .stat-card:hover::before {
            left: 100%;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(255, 107, 0, 0.3);
            background: linear-gradient(135deg, var(--dark-lighter) 0%, var(--dark-light) 100%);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
            position: relative;
            z-index: 1;
        }
        
        .stat-card:hover .stat-icon {
            transform: rotate(5deg) scale(1.1);
            box-shadow: 0 6px 20px rgba(255, 107, 0, 0.4);
        }
        
        .stat-info h3 {
            font-size: 2.2rem;
            font-weight: 900;
            margin-bottom: 5px;
            color: var(--text);
            background: linear-gradient(135deg, var(--text) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s;
        }
        
        .stat-card:hover .stat-info h3 {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-info p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        
        .stat-change {
            font-size: 0.85rem;
            padding: 3px 10px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-change.positive {
            background: rgba(40, 167, 69, 0.2);
            color: #aaffaa;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            border-bottom: 3px solid var(--border);
            padding-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid var(--border);
            border-radius: 10px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab:hover {
            background: rgba(255, 107, 0, 0.1);
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .tab.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: var(--primary);
            box-shadow: 0 6px 20px rgba(255, 107, 0, 0.3);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .funcionario-tipo-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-mecanico {
            background: rgba(255, 107, 0, 0.18);
            color: #ffb347;
            border: 1px solid rgba(255, 107, 0, 0.45);
        }
        
        .badge-orcamentista {
            background: rgba(40, 167, 69, 0.2);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 1024px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .section-card {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 28px;
            border: 2px solid var(--border);
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .section-card:hover {
            border-color: rgba(255, 107, 0, 0.3);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .section-header h2 {
            color: var(--text);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge {
            background: var(--primary);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .mecanicos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            max-height: 800px;
            overflow-y: auto;
            padding: 10px;
            border-radius: 8px;
        }

        .mecanicos-grid::-webkit-scrollbar {
            width: 10px;
        }

        .mecanicos-grid::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .mecanicos-grid::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .mecanicos-grid::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        .funcionario-card {
            background: var(--dark-lighter);
            border-radius: 12px;
            padding: 20px;
            border: 2px solid var(--border);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .funcionario-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.2);
            background: linear-gradient(135deg, var(--dark-lighter) 0%, rgba(255, 107, 0, 0.05) 100%);
        }
        
        .funcionario-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .funcionario-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.4rem;
            color: white;
            box-shadow: 0 6px 20px rgba(255, 107, 0, 0.3);
            flex-shrink: 0;
        }
        
        .funcionario-info h4 {
            color: var(--text);
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .especialidade {
            color: var(--primary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .funcionario-details {
            margin-bottom: 15px;
        }
        
        .funcionario-details p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .salario-badge {
            background: rgba(255, 193, 7, 0.2);
            color: #ffeaa7;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .acoes-rapidas {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .btn-rapido {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-rapido-success {
            background: rgba(40, 167, 69, 0.2);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .btn-rapido-success:hover {
            background: rgba(40, 167, 69, 0.4);
        }
        
        .btn-rapido-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffeaa7;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .btn-rapido-warning:hover {
            background: rgba(255, 193, 7, 0.4);
        }
        
        .btn-rapido-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .btn-rapido-danger:hover {
            background: rgba(220, 53, 69, 0.4);
        }
        
        .servicos-list {
            margin-top: 20px;
        }
        
        .servico-item {
            background: var(--dark-lighter);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid var(--border);
            transition: all 0.3s;
        }
        
        .servico-item:hover {
            border-color: var(--primary);
            transform: translateX(5px);
        }
        
        .servico-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .servico-header h4 {
            color: var(--text);
            font-size: 1.1rem;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-em-andamento {
            background: rgba(255, 193, 7, 0.2);
            color: #ffeaa7;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-pendente {
            background: rgba(108, 117, 125, 0.2);
            color: var(--text-secondary);
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .status-concluído {
            background: rgba(40, 167, 69, 0.2);
            color: #aaffaa;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .servico-details p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .graficos-section {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
        }
        
        .grafico-card {
            background: linear-gradient(135deg, var(--dark-light) 0%, var(--dark-lighter) 100%);
            border-radius: 16px;
            padding: 25px;
            border: 2px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .grafico-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s;
        }
        
        .grafico-card:hover::before {
            transform: scaleX(1);
        }
        
        .grafico-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 107, 0, 0.3);
            background: linear-gradient(135deg, var(--dark-lighter) 0%, var(--dark-light) 100%);
        }
        
        .grafico-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .grafico-header h3 {
            color: var(--primary);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .grafico-container {
            position: relative;
            height: 300px;
            width: 100%;
            min-height: 250px;
        }
        
        .grafico-container-large {
            position: relative;
            height: 400px;
            width: 100%;
            min-height: 300px;
        }
        
        /* Loading state para gráficos */
        .grafico-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .grafico-loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 1200px) {
            .graficos-section {
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .graficos-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .grafico-card {
                padding: 20px;
            }
            
            .grafico-container,
            .grafico-container-large {
                height: 250px;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .stat-info h3 {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .grafico-container,
            .grafico-container-large {
                height: 200px;
            }
            
            .grafico-header h3 {
                font-size: 1.1rem;
            }
        }
        
        .chefe-footer {
            text-align: center;
            padding: 25px;
            color: var(--text-muted);
            font-size: 0.9rem;
            border-top: 2px solid var(--border);
            margin-top: 30px;
        }
        
        .footer-info {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 10px;
        }
        
        .footer-info i {
            margin-right: 5px;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-conteudo {
            background: var(--dark-light);
            border-radius: 16px;
            padding: 40px;
            width: 90%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 2px solid var(--primary);
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        .grafico-card {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .grafico-card:nth-child(1) { animation-delay: 0.1s; }
        .grafico-card:nth-child(2) { animation-delay: 0.2s; }
        .grafico-card:nth-child(3) { animation-delay: 0.3s; }
        .grafico-card:nth-child(4) { animation-delay: 0.4s; }
        .grafico-card:nth-child(5) { animation-delay: 0.5s; }

        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .modal-header h2 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .modal-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-group label {
            color: var(--text);
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        .form-group select,
        .form-group input.form-control,
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="text"],
        .form-group input[type="number"] {
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--dark);
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group select:focus,
        .form-group input.form-control:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="tel"]:focus,
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(255, 107, 0, 0.4);
            background: rgba(255, 107, 0, 0.05);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .modal-botoes {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn-modal {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn-confirmar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 6px 20px rgba(255, 107, 0, 0.3);
        }

        .btn-confirmar:hover {
            background: linear-gradient(135deg, var(--primary-dark), #8b3d00);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.5);
        }

        .btn-confirmar:active {
            transform: translateY(-1px);
        }

        .btn-cancelar {
            background: rgba(108, 117, 125, 0.15);
            color: var(--text);
            border: 2px solid rgba(108, 117, 125, 0.3);
        }

        .btn-cancelar:hover {
            background: rgba(108, 117, 125, 0.3);
            border-color: rgba(108, 117, 125, 0.6);
            transform: translateY(-2px);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--dark);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- MODAL CRIAR MECÂNICO -->
    <div class="modal-overlay" id="modalCriarMecanico">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-wrench"></i> Adicionar Mecânico</h2>
                <p>Preencha os dados para recrutar um novo mecânico</p>
            </div>
            
            <form id="formCriarMecanico" method="POST">
                <input type="hidden" name="acao" value="criar_mecanico">
                <p style="margin: 0 0 16px; padding: 12px 14px; border-radius: 8px; background: rgba(255,107,0,0.12); border: 1px solid rgba(255,107,0,0.35); font-size: 0.9rem; line-height: 1.5;">
                    <i class="bi bi-shield-lock"></i> <?= am_texto_credenciais_funcionario_html() ?>
                </p>
                
                <div class="modal-form">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="nomeMecanico">Nome Completo *</label>
                            <input type="text" id="nomeMecanico" name="nome" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="emailMecanico">Email *</label>
                            <input type="email" id="emailMecanico" name="email" class="form-control" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="telefoneMecanico">Telefone *</label>
                            <input type="tel" id="telefoneMecanico" name="telefone" class="form-control am-telemovel-input" placeholder="912 345 678" maxlength="11" autocomplete="tel" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nifMecanico">NIF</label>
                            <input type="text" id="nifMecanico" name="nif" class="form-control am-nif-input" inputmode="numeric" maxlength="9" placeholder="123456789" autocomplete="off">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="especialidadeMecanico">Especialidade *</label>
                            <select id="especialidadeMecanico" name="especialidade" class="form-control" required>
                                <option value="">Selecionar...</option>
                                <option value="Motor">Motor</option>
                                <option value="Suspensão">Suspensão</option>
                                <option value="Elétrica">Elétrica</option>
                                <option value="Transmissão">Transmissão</option>
                                <option value="Freios">Freios</option>
                                <option value="Ar Condicionado">Ar Condicionado</option>
                                <option value="Direção">Direção</option>
                                <option value="Pintura">Pintura</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="salarioMecanico">Salário (€) *</label>
                            <input type="number" id="salarioMecanico" name="salario" class="form-control" value="1000" step="50" min="760" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="moradaMecanico">Morada</label>
                        <input type="text" id="moradaMecanico" name="morada" class="form-control" placeholder="Rua, Nº, Cidade">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="ibanMecanico">IBAN</label>
                            <input type="text" id="ibanMecanico" name="iban" class="form-control am-iban-input" placeholder="PT50 0000 0000 0000 0000 0000 0" autocomplete="off" spellcheck="false">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="dataContracao">Data de Contratação</label>
                        <input type="date" id="dataContracao" name="data_contratacao" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="modal-botoes">
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModal('modalCriarMecanico')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal btn-confirmar">
                        <i class="bi bi-check2-circle"></i> Adicionar Mecânico
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- MODAL DESPEDIMENTO -->
    <div class="modal-overlay" id="modalDespedir">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-person-x"></i> Despedir Funcionário</h2>
                <p id="modalDespedirTexto">Tem certeza que deseja despedir este funcionário?</p>
            </div>
            
            <form id="formDespedir" method="POST">
                <input type="hidden" name="acao" value="despedir">
                <input type="hidden" id="despedirId" name="funcionario_id" value="">
                <input type="hidden" id="despedirTipo" name="tipo_funcionario" value="">
                
                <div class="modal-form">
                    <div class="form-group">
                        <label for="motivoDespedimento">Motivo do Despedimento *</label>
                        <textarea id="motivoDespedimento" name="motivo" class="form-control" placeholder="Descreva o motivo do despedimento..." required style="min-height: 120px; resize: vertical;"></textarea>
                    </div>
                </div>
                
                <div class="modal-botoes">
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModal('modalDespedir')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal btn-confirmar">
                        Confirmar Despedimento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL AUMENTAR SALÁRIO -->
    <div class="modal-overlay" id="modalAumentar">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-cash-stack"></i> Aumentar Salário</h2>
                <p id="modalAumentarTexto">Defina a percentagem de aumento:</p>
            </div>
            
            <form id="formAumentar" method="POST">
                <input type="hidden" name="acao" value="aumentar_salario">
                <input type="hidden" id="aumentarId" name="funcionario_id" value="">
                <input type="hidden" id="aumentarTipo" name="tipo_funcionario" value="">
                
                <div class="form-group">
                    <label for="percentagemAumento">Percentagem de Aumento:</label>
                    <select id="percentagemAumento" name="percentagem" class="form-control">
                        <option value="5">5%</option>
                        <option value="10" selected>10%</option>
                        <option value="15">15%</option>
                        <option value="20">20%</option>
                        <option value="25">25%</option>
                    </select>
                </div>
                
                <div class="modal-botoes">
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModal('modalAumentar')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal btn-confirmar">
                        Aplicar Aumento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DIMINUIR SALÁRIO -->
    <div class="modal-overlay" id="modalDiminuir">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-cash"></i> Diminuir Salário</h2>
                <p id="modalDiminuirTexto">Defina a percentagem de redução:</p>
            </div>
            
            <form id="formDiminuir" method="POST">
                <input type="hidden" name="acao" value="diminuir_salario">
                <input type="hidden" id="diminuirId" name="funcionario_id" value="">
                <input type="hidden" id="diminuirTipo" name="tipo_funcionario" value="">
                
                <div class="form-group">
                    <label for="percentagemDiminuir">Percentagem de Redução:</label>
                    <select id="percentagemDiminuir" name="percentagem" class="form-control">
                        <option value="5" selected>5%</option>
                        <option value="10">10%</option>
                        <option value="15">15%</option>
                        <option value="20">20%</option>
                        <option value="25">25%</option>
                    </select>
                </div>
                
                <div class="modal-botoes">
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModal('modalDiminuir')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal btn-confirmar">
                        Aplicar Redução
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PROMOVER -->
    <div class="modal-overlay" id="modalPromover">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2><i class="bi bi-award"></i> Promover Funcionário</h2>
                <p id="modalPromoverTexto">Selecione a nova especialidade:</p>
            </div>
            
            <form id="formPromover" method="POST">
                <input type="hidden" name="acao" value="promover">
                <input type="hidden" id="promoverId" name="funcionario_id" value="">
                <input type="hidden" id="promoverTipo" name="tipo_funcionario" value="">
                
                <div class="form-group">
                    <label for="novaFuncao">Nova Especialidade:</label>
                    <select id="novaFuncao" name="nova_funcao" class="form-control">
                        <option value="Motor">Motor</option>
                        <option value="Suspensão">Suspensão</option>
                        <option value="Elétrica">Elétrica</option>
                        <option value="Transmissão">Transmissão</option>
                        <option value="Freios">Freios</option>
                        <option value="Ar Condicionado">Ar Condicionado</option>
                        <option value="Direção">Direção</option>
                        <option value="Pintura">Pintura</option>
                    </select>
                </div>
                
                <div class="modal-botoes">
                    <button type="button" class="btn-modal btn-cancelar" onclick="fecharModal('modalPromover')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal btn-confirmar">
                        Promover
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="chefe-container">
        <header class="chefe-header">
            <div class="header-left">
                <h1><i class="bi bi-person-badge"></i> Gestão de Recursos Humanos</h1>
                <div class="subtitle">
                    <i class="bi bi-person-circle"></i> Bem-vindo, 
                    <strong><?php echo htmlspecialchars($chefe_nome); ?></strong>
                    <span class="user-email"><?php echo htmlspecialchars($chefe_email); ?></span>
                </div>
            </div>
            
            <button class="btn-logout" onclick="window.location.href='logout.php'">
                <i class="bi bi-box-arrow-right"></i> Terminar Sessão
            </button>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-logout" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: 2px solid var(--primary); box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);" onclick="abrirModal('modalCriarMecanico')">
                    <i class="bi bi-person-plus"></i> <span style="font-weight: 700;">Mecânico</span>
                </button>
            </div>
        </header>

        <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
        <div class="mensagem sucesso">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>[OK] Ação realizada com sucesso!</strong>
                <p><?php echo $_SESSION['mensagem_sucesso']; unset($_SESSION['mensagem_sucesso']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensagem_erro'])): ?>
        <div class="mensagem sucesso" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.2) 0%, rgba(220, 53, 69, 0.1) 100%); border: 2px solid var(--danger);">
            <i class="bi bi-exclamation-circle-fill" style="color: var(--danger);"></i>
            <div>
                <strong style="color: var(--danger);">[ERRO] Erro!</strong>
                <p><?php echo $_SESSION['mensagem_erro']; unset($_SESSION['mensagem_erro']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="mensagem info">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <strong>[RELATORIO] Gestão de Funcionários</strong>
                <p>Gerencie sua equipa: despedimentos, aumentos salariais, promoções e mais.</p>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ff6b00, #ff8c33);">
                    <i class="bi bi-wrench"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['servicos_ativos']; ?></h3>
                    <p>Serviços Ativos</p>
                    <span class="stat-change positive">
                        <i class="bi bi-arrow-up"></i> +12% este mês
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #4cd964);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['servicos_concluidos']; ?></h3>
                    <p>Serviços Concluídos</p>
                    <span class="stat-change positive">
                        <i class="bi bi-arrow-up"></i> +8% vs mês passado
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ea580c, #ffb347);">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_funcionarios']; ?></h3>
                    <p>Total Funcionários</p>
                    <span class="stat-change positive">
                        <i class="bi bi-arrow-up"></i> Sem alteração
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #c2410c, #ff8c33);">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="stat-info">
                    <h3>€<?php echo $stats['salario_medio_geral']; ?></h3>
                    <p>Salário Médio Geral</p>
                    <span class="stat-change positive">
                        <i class="bi bi-arrow-up"></i> +3% este ano
                    </span>
                </div>
            </div>
        </div>

        <div class="main-layout">
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="bi bi-people-fill"></i> Funcionários da Equipa</h2>
                    <span class="badge">
                        <i class="bi bi-person-check"></i> <?php echo $stats['total_funcionarios']; ?> Ativos
                    </span>
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="mudarTab('mecanicos')">
                        <i class="bi bi-wrench"></i> Mecânicos (<?php echo $stats['mecanicos_ativos']; ?>)
                    </button>
                </div>

                <!-- TAB MECÂNICOS (lista principal) -->
                <div class="tab-content active" id="tab-mecanicos">
                    <div class="mecanicos-grid">
                        <?php
                        // Buscar apenas mecânicos
                        $sql_mecanicos = "SELECT id_mecanico as id, nome_mecanico as nome, especialidade, contacto, email, ordenado_bruto as salario 
                                         FROM mecanicos 
                                         WHERE ativo = 1 
                                         ORDER BY nome_mecanico";
                        
                        $result = mysqli_query($con, $sql_mecanicos);
                        
                        if ($result && mysqli_num_rows($result) > 0) {
                            while ($mecanico = mysqli_fetch_assoc($result)) {
                                $salario_formatado = number_format($mecanico['salario'] ?? 0, 2, ',', '.');
                        ?>
                        <div class="funcionario-card">
                            <div class="funcionario-header">
                                <div class="funcionario-avatar">
                                    <?php echo strtoupper(substr($mecanico['nome'], 0, 1)); ?>
                                </div>
                                <div class="funcionario-info">
                                    <h4><?php echo htmlspecialchars($mecanico['nome']); ?>
                                        <span class="funcionario-tipo-badge badge-mecanico">
                                            Mecânico
                                        </span>
                                    </h4>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <span class="especialidade">
                                            <i class="bi bi-wrench"></i> 
                                            <?php echo htmlspecialchars($mecanico['especialidade']); ?>
                                        </span>
                                        <span class="salario-badge">
                                            <i class="bi bi-cash"></i> 
                                            €<?php echo $salario_formatado; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="funcionario-details">
                                <p>
                                    <i class="bi bi-telephone"></i> 
                                    <?php echo htmlspecialchars($mecanico['contacto']); ?>
                                </p>
                                <p>
                                    <i class="bi bi-envelope"></i> 
                                    <?php echo htmlspecialchars($mecanico['email']); ?>
                                </p>
                            </div>

                            <div class="acoes-rapidas">
                                <button class="btn-rapido btn-rapido-success" onclick="abrirModal('modalAumentar', <?php echo $mecanico['id']; ?>, '<?php echo addslashes($mecanico['nome']); ?>', 'mecanico')">
                                    <i class="bi bi-arrow-up"></i> Aumentar
                                </button>
                                <button class="btn-rapido btn-rapido-warning" onclick="abrirModal('modalDiminuir', <?php echo $mecanico['id']; ?>, '<?php echo addslashes($mecanico['nome']); ?>', 'mecanico')">
                                    <i class="bi bi-arrow-down"></i> Reduzir
                                </button>
                                <button class="btn-rapido btn-rapido-success" onclick="abrirModal('modalPromover', <?php echo $mecanico['id']; ?>, '<?php echo addslashes($mecanico['nome']); ?>', 'mecanico')">
                                    <i class="bi bi-award"></i> Promover
                                </button>
                                <button class="btn-rapido btn-rapido-danger" onclick="abrirModal('modalDespedir', <?php echo $mecanico['id']; ?>, '<?php echo addslashes($mecanico['nome']); ?>', 'mecanico')">
                                    <i class="bi bi-person-x"></i> Despedir
                                </button>
                            </div>
                        </div>
                        <?php
                            }
                            mysqli_free_result($result);
                        } else {
                        ?>
                        <div class="empty-state">
                            <i class="bi bi-wrench"></i>
                            <h4>Nenhum mecânico encontrado</h4>
                            <p>Adicione mecânicos à sua equipa para começar.</p>
                        </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h2><i class="bi bi-tools"></i> Serviços Recentes</h2>
                    <span class="badge">
                        <i class="bi bi-clock"></i> <?php echo $stats['servicos_ativos']; ?> Ativos
                    </span>
                </div>

                <div class="servicos-list">
                    <?php
                    // Buscar serviços da base de dados
                    $sql_servicos = "SELECT s.id, s.tipo_servico as descricao, s.estado_do_trabalho as estado, s.data_registo as data_entrada, s.id_mecanico, m.nome_mecanico as nome_mecanico
                                    FROM servico s
                                    LEFT JOIN mecanicos m ON s.id_mecanico = m.id_mecanico
                                    WHERE s.estado_do_trabalho IN ('Em Andamento', 'Por Fazer', 'Aguardar Preparação')
                                    ORDER BY s.data_registo DESC 
                                    LIMIT 5";
                    
                    $result = mysqli_query($con, $sql_servicos);
                    
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($servico = mysqli_fetch_assoc($result)) {
                            $estado = strtolower($servico['estado']);
                            $estado_class = ($estado === 'em andamento' || $estado === 'emandamento') ? 'status-em-andamento' : 
                                           (($estado === 'concluído' || $estado === 'concluido') ? 'status-concluído' : 'status-pendente');
                            
                            $nome_mecanico = $servico['nome_mecanico'] ?? 'Não atribuído';
                    ?>
                    <div class="servico-item">
                        <div class="servico-header">
                            <h4><?php echo htmlspecialchars($servico['descricao']); ?></h4>
                            <span class="status-badge <?php echo $estado_class; ?>">
                                <i class="bi bi-circle-fill"></i> 
                                <?php echo ucfirst($servico['estado']); ?>
                            </span>
                        </div>
                        <div class="servico-details">
                            <p>
                                <i class="bi bi-calendar"></i> 
                                Entrada: <?php echo date('d/m/Y H:i', strtotime($servico['data_entrada'])); ?>
                            </p>
                            <p>
                                <i class="bi bi-person"></i> 
                                Responsável: <?php echo htmlspecialchars($nome_mecanico); ?>
                            </p>
                        </div>
                    </div>
                    <?php
                        }
                        mysqli_free_result($result);
                    } else {
                    ?>
                    <div class="empty-state">
                        <i class="bi bi-tools"></i>
                        <h4>Nenhum serviço ativo</h4>
                        <p>Não há serviços em andamento no momento.</p>
                    </div>
                    <?php
                    }
                    ?>
                </div>

                <div class="section-header" style="margin-top: 30px;">
                    <h2><i class="bi bi-graph-up-arrow"></i> Análise e Gráficos</h2>
                </div>

                <div class="graficos-section">
                    <!-- Gráfico de Serviços por Mês -->
                    <div class="grafico-card">
                        <div class="grafico-header">
                            <h3><i class="bi bi-calendar-month"></i> Serviços por Mês</h3>
                        </div>
                        <div class="grafico-container">
                            <canvas id="graficoServicosMes"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Estados de Serviços -->
                    <div class="grafico-card">
                        <div class="grafico-header">
                            <h3><i class="bi bi-pie-chart"></i> Estados de Serviços</h3>
                        </div>
                        <div class="grafico-container">
                            <canvas id="graficoEstados"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Distribuição por Especialidade -->
                    <div class="grafico-card">
                        <div class="grafico-header">
                            <h3><i class="bi bi-people"></i> Distribuição por Especialidade</h3>
                        </div>
                        <div class="grafico-container">
                            <canvas id="graficoEspecialidades"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Top Salários -->
                    <div class="grafico-card">
                        <div class="grafico-header">
                            <h3><i class="bi bi-cash-stack"></i> Top 10 Salários</h3>
                        </div>
                        <div class="grafico-container-large">
                            <canvas id="graficoSalarios"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Serviços por Mecânico -->
                    <div class="grafico-card" style="grid-column: 1 / -1;">
                        <div class="grafico-header">
                            <h3><i class="bi bi-bar-chart"></i> Serviços por Mecânico</h3>
                        </div>
                        <div class="grafico-container-large">
                            <canvas id="graficoServicosMecanico"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="chefe-footer">
        <p>&copy; 2026 AUTOMANAGER - Sistema de Gestão de Oficina</p>
        <div class="footer-info">
            <span><i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($chefe_nome); ?></span>
            <span><i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i'); ?></span>
            <span><i class="bi bi-building"></i> <?php echo $stats['total_funcionarios']; ?> Funcionários</span>
        </div>
    </footer>

    <!-- Logs & Trabalhos -->
    <div class="logs-trabalhos-chefe" style="position:fixed; right:20px; bottom:20px; z-index:1200;">
        <a href="#logs_trabalhos" style="display:inline-block; background:var(--primary); color:#fff; padding:10px 14px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.25); text-decoration:none;">Logs & Trabalhos</a>
    </div>

    <div id="logs_trabalhos" style="padding:30px; background: rgba(255,255,255,0.03); margin:40px; border-radius: 8px; display: none;">
        <h2 style="margin-top:0;"><i class="bi bi-journal-text"></i> Logs & Trabalhos</h2>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:300px;">
                <h3>Logs do Sistema</h3>
                <?php
                // Buscar logs da base de dados
                $logs_q = $con->query("SELECT origem, mensagem, dados, criado_em FROM logs ORDER BY criado_em DESC LIMIT 300");
                if ($logs_q && $logs_q->num_rows > 0) {
                ?>
                <div style="max-height:320px; overflow:auto; background:#0f0f0f; color:#e8e8e8; padding:10px; border-radius:6px;">
                    <ol style="padding-left:18px;">
                        <?php
                        while ($row = $logs_q->fetch_assoc()) {
                            $orig = htmlspecialchars($row['origem']);
                            $time = htmlspecialchars($row['criado_em']);
                            $msg = htmlspecialchars($row['mensagem']);
                            $dados = htmlspecialchars($row['dados']);
                        ?>
                        <li style="margin-bottom:8px;">
                            <strong>[<?= $time ?>] <?= $orig ?></strong>
                            <div style="font-size:90%; margin-top:4px;"><?= $msg ?></div>
                            <?php if (!empty($dados)): ?>
                                <pre style="background:#0b0b0b;color:#ddd;padding:8px;border-radius:4px;margin-top:6px;"><?= $dados ?></pre>
                            <?php endif; ?>
                        </li>
                        <?php
                        }
                        ?>
                    </ol>
                </div>
                <?php
                } else {
                    echo '<div>Nenhum registo de log encontrado.</div>';
                }
                ?>
            </div>

            <div style="flex:2; min-width:420px;">
                <h3>Trabalhos (Serviços)</h3>
                <?php
                $serv = @$con->query("SELECT id, tipo_servico as descricao, estado_do_trabalho, id_mecanico, email, horas_trabalhadas, data_registo FROM servico ORDER BY id DESC LIMIT 200");
                if ($serv && $serv->num_rows > 0) {
                    echo '<div style="overflow-x:auto;"><table style="width:100%; border-collapse:collapse;"><thead><tr style="background:rgba(255,107,0,0.2);"><th style="padding:10px;border:1px solid #444;">ID</th><th style="padding:10px;border:1px solid #444;">Descrição</th><th style="padding:10px;border:1px solid #444;">Estado</th><th style="padding:10px;border:1px solid #444;">Mecânico</th><th style="padding:10px;border:1px solid #444;">Horas</th><th style="padding:10px;border:1px solid #444;">Data</th></tr></thead><tbody>';
                    while ($s = $serv->fetch_assoc()) {
                        echo '<tr style="border-bottom:1px solid #333;">';
                        echo '<td style="padding:10px;">' . htmlspecialchars($s['id']) . '</td>';
                        echo '<td style="padding:10px;">' . htmlspecialchars(substr($s['descricao'], 0, 120)) . '</td>';
                        echo '<td style="padding:10px;">' . htmlspecialchars($s['estado_do_trabalho']) . '</td>';
                        echo '<td style="padding:10px;">' . ($s['id_mecanico'] ? htmlspecialchars($s['id_mecanico']) : '-') . '</td>';
                        echo '<td style="padding:10px;">' . ($s['horas_trabalhadas'] !== null ? htmlspecialchars($s['horas_trabalhadas']) : '-') . '</td>';
                        echo '<td style="padding:10px;">' . (isset($s['data_registo']) ? htmlspecialchars($s['data_registo']) : '-') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div>Nenhum serviço encontrado.</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        // Funções de gestão de funcionários
        function abrirModal(tipo, funcionarioId, nomeFuncionario, tipoFuncionario) {
            const modal = document.getElementById(tipo);
            
            // Se for modal de criar (sem parâmetros adicionais)
            if (tipo === 'modalCriarMecanico') {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                return;
            }
            
            const textoElement = modal.querySelector('p');
            
            // Definir texto personalizado para cada modal
            switch(tipo) {
                case 'modalDespedir':
                    textoElement.textContent = `Tem certeza que deseja despedir ${nomeFuncionario}?`;
                    document.getElementById('despedirId').value = funcionarioId;
                    document.getElementById('despedirTipo').value = tipoFuncionario;
                    break;
                case 'modalAumentar':
                    textoElement.textContent = `Defina o aumento salarial para ${nomeFuncionario}:`;
                    document.getElementById('aumentarId').value = funcionarioId;
                    document.getElementById('aumentarTipo').value = tipoFuncionario;
                    break;
                case 'modalDiminuir':
                    textoElement.textContent = `Defina a redução salarial para ${nomeFuncionario}:`;
                    document.getElementById('diminuirId').value = funcionarioId;
                    document.getElementById('diminuirTipo').value = tipoFuncionario;
                    break;
                case 'modalPromover':
                    textoElement.textContent = `Alterar especialidade de ${nomeFuncionario} para:`;
                    document.getElementById('promoverId').value = funcionarioId;
                    document.getElementById('promoverTipo').value = tipoFuncionario;
                    break;
            }
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal(tipo) {
            const modal = document.getElementById(tipo);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        // Função para mudar de tab
        function mudarTab(tab) {
            // Remover active de todas as tabs
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Adicionar active à tab clicada
            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }

        // Fechar modais ao clicar fora
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });
        });

        // Toggle para mostrar/esconder logs
        document.querySelector('.logs-trabalhos-chefe a').addEventListener('click', function(e) {
            e.preventDefault();
            const logsDiv = document.getElementById('logs_trabalhos');
            logsDiv.style.display = logsDiv.style.display === 'none' ? 'block' : 'none';
        });

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Sistema de gestão de recursos humanos carregado com sucesso');
            
            // Validação de formulários de criação
            const forms = document.querySelectorAll('form[id^="formCriar"]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const inputs = form.querySelectorAll('input[required], select[required]');
                    let isValid = true;
                    
                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            input.style.borderColor = '#dc3545';
                            isValid = false;
                        } else {
                            input.style.borderColor = '';
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Por favor, preencha todos os campos obrigatórios!');
                    }
                });
            });
            
            // Inicializar primeira aba como ativa
            const firstTab = document.querySelector('.tab');
            if (firstTab) {
                firstTab.classList.add('active');
            }
            const firstTabContent = document.querySelector('.tab-content');
            if (firstTabContent) {
                firstTabContent.classList.add('active');
            }
            
            // INICIALIZAR GRÁFICOS COM TRATAMENTO DE ERROS
            const chartColors = {
                primary: '#ff6b00',
                primaryLight: '#ff8c33',
                success: '#28a745',
                info: '#ea580c',
                warning: '#ffc107',
                danger: '#dc3545',
                purple: '#c2410c',
                blue: '#fd7e14',
                teal: '#ffb347',
                orange: '#ff922b',
                indigo: '#9a3412'
            };
            
            // Função auxiliar para validar dados
            function validarDados(dados, campo) {
                if (!Array.isArray(dados) || dados.length === 0) {
                    return [{ [campo]: 'Sem Dados', total: 0 }];
                }
                return dados.filter(d => d && d[campo] && d.total !== undefined);
            }
            
            // Gráfico de Serviços por Mês
            const ctxServicosMes = document.getElementById('graficoServicosMes');
            if (ctxServicosMes && typeof Chart !== 'undefined') {
                try {
                    const dadosServicosMes = <?php echo json_encode($grafico_servicos_mes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                    const dadosValidados = validarDados(dadosServicosMes, 'mes');
                    
                    new Chart(ctxServicosMes, {
                        type: 'line',
                        data: {
                            labels: dadosValidados.map(d => d.mes || 'N/A'),
                            datasets: [{
                                label: 'Serviços',
                                data: dadosValidados.map(d => parseInt(d.total) || 0),
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(255, 107, 0, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: chartColors.primary,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: chartColors.primary,
                                borderWidth: 1,
                                padding: 12
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 11
                                    }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 11
                                    }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                }
                            }
                        }
                    }
                    });
                } catch (error) {
                    console.error('Erro ao criar gráfico de serviços por mês:', error);
                }
            }
            
            // Gráfico de Estados de Serviços (Pizza)
            const ctxEstados = document.getElementById('graficoEstados');
            if (ctxEstados && typeof Chart !== 'undefined') {
                try {
                    const dadosEstados = <?php echo json_encode($grafico_estados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                    const dadosValidados = validarDados(dadosEstados, 'estado');
                    
                    new Chart(ctxEstados, {
                        type: 'doughnut',
                        data: {
                            labels: dadosValidados.map(d => d.estado || 'N/A'),
                            datasets: [{
                                data: dadosValidados.map(d => parseInt(d.total) || 0),
                                backgroundColor: [
                                    chartColors.primary,
                                    chartColors.success,
                                    chartColors.warning,
                                    chartColors.info,
                                    chartColors.danger,
                                    chartColors.purple,
                                    chartColors.blue,
                                    chartColors.teal,
                                    chartColors.orange,
                                    chartColors.indigo
                                ],
                            borderWidth: 2,
                            borderColor: '#1a1a1a'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#cccccc',
                                    padding: 15,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: chartColors.primary,
                                borderWidth: 1,
                                padding: 12
                            }
                        }
                    }
                    });
                } catch (error) {
                    console.error('Erro ao criar gráfico de estados:', error);
                }
            }
            
            // Gráfico de Especialidades
            const ctxEspecialidades = document.getElementById('graficoEspecialidades');
            if (ctxEspecialidades && typeof Chart !== 'undefined') {
                try {
                    const dadosEspecialidades = <?php echo json_encode($grafico_especialidades, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                    const dadosValidados = validarDados(dadosEspecialidades, 'especialidade');
                    
                    new Chart(ctxEspecialidades, {
                        type: 'bar',
                        data: {
                            labels: dadosValidados.map(d => d.especialidade || 'N/A'),
                            datasets: [{
                                label: 'Funcionários',
                                data: dadosValidados.map(d => parseInt(d.total) || 0),
                            backgroundColor: chartColors.info,
                            borderColor: chartColors.primary,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: chartColors.primary,
                                borderWidth: 1,
                                padding: 12
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 11
                                    },
                                    stepSize: 1
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                    });
                } catch (error) {
                    console.error('Erro ao criar gráfico de especialidades:', error);
                }
            }
            
            // Gráfico de Salários
            const ctxSalarios = document.getElementById('graficoSalarios');
            if (ctxSalarios && typeof Chart !== 'undefined') {
                try {
                    const dadosSalarios = <?php echo json_encode($grafico_salarios, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                    const dadosValidados = validarDados(dadosSalarios, 'nome');
                    
                    new Chart(ctxSalarios, {
                        type: 'bar',
                        data: {
                            labels: dadosValidados.map(d => d.nome || 'N/A'),
                            datasets: [{
                                label: 'Salário (€)',
                                data: dadosValidados.map(d => parseFloat(d.salario) || 0),
                            backgroundColor: chartColors.success,
                            borderColor: chartColors.primary,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: chartColors.primary,
                                borderWidth: 1,
                                padding: 12,
                                callbacks: {
                                    label: function(context) {
                                        return '€' + context.parsed.x.toLocaleString('pt-PT', {minimumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return '€' + value.toLocaleString('pt-PT');
                                    }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                }
                            },
                            y: {
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                    });
                } catch (error) {
                    console.error('Erro ao criar gráfico de salários:', error);
                }
            }
            
            // Gráfico de Serviços por Mecânico
            const ctxServicosMecanico = document.getElementById('graficoServicosMecanico');
            if (ctxServicosMecanico && typeof Chart !== 'undefined') {
                try {
                    const dadosServicosMecanico = <?php echo json_encode($grafico_servicos_mecanico, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                    const dadosValidados = validarDados(dadosServicosMecanico, 'nome');
                    
                    new Chart(ctxServicosMecanico, {
                        type: 'bar',
                        data: {
                            labels: dadosValidados.map(d => d.nome || 'N/A'),
                            datasets: [{
                                label: 'Serviços',
                                data: dadosValidados.map(d => parseInt(d.total) || 0),
                            backgroundColor: [
                                chartColors.primary,
                                chartColors.info,
                                chartColors.success,
                                chartColors.warning,
                                chartColors.purple,
                                chartColors.blue,
                                chartColors.teal,
                                chartColors.danger,
                                '#ff9800',
                                '#4caf50'
                            ],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: chartColors.primary,
                                borderWidth: 1,
                                padding: 12
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 11
                                    },
                                    stepSize: 1
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#cccccc',
                                    font: {
                                        size: 10
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                    });
                } catch (error) {
                    console.error('Erro ao criar gráfico de serviços por mecânico:', error);
                }
            }
            
            console.log('[OK] Todos os gráficos inicializados com sucesso!');
        });
    </script>
<script src="nif.js?v=2"></script>
<script src="iban.js?v=3"></script>
<script src="telemovel.js?v=1"></script>
</body>
</html>