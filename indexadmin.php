<?php
session_start();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Verifica se o utilizador está logado e é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Conexão à base de dados
require('ligar.php');
require_once __DIR__ . '/inc_fatura_intervencao.php';
require_once __DIR__ . '/inc_marcas_modelos.php';
am_ensure_servico_fecho_cols($con);

// Verificar se a tabela estado_site existe e criar se necessário
$table_check = $con->query("SHOW TABLES LIKE 'estado_site'");
if ($table_check && $table_check->num_rows == 0) {
    $con->query("CREATE TABLE estado_site (
        id INT PRIMARY KEY AUTO_INCREMENT, 
        estado ENUM('0','1') DEFAULT '0',
        mensagem TEXT NULL,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $con->query("INSERT INTO estado_site (id, estado, mensagem) VALUES (1, '0', 'Estamos em manutenção. Por favor volte mais tarde.')");
}

// Verificar e adicionar campos reset_code e reset_expire à tabela administrador se não existirem
$check_admin_cols = $con->query("SHOW COLUMNS FROM administrador LIKE 'reset_code'");
if (!$check_admin_cols || $check_admin_cols->num_rows == 0) {
    $con->query("ALTER TABLE administrador ADD COLUMN reset_code VARCHAR(10) DEFAULT NULL");
    $con->query("ALTER TABLE administrador ADD COLUMN reset_expire TIMESTAMP NULL");
}

// Verificar se a tabela mecanicos existe e criar se necessário
$table_check3 = $con->query("SHOW TABLES LIKE 'mecanicos'");
if ($table_check3 && $table_check3->num_rows == 0) {
    $con->query("CREATE TABLE mecanicos (
        id_mecanico INT PRIMARY KEY AUTO_INCREMENT,
        nome_mecanico VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255),
        contacto VARCHAR(20),
        morada VARCHAR(200),
        ordenado_bruto DECIMAL(10,2),
        data_contratacao DATE,
        nif VARCHAR(20),
        iban VARCHAR(50),
        especialidade VARCHAR(100) NOT NULL,
        ativo TINYINT DEFAULT 1,
        primeiro_login TINYINT DEFAULT 1,
        reset_code VARCHAR(10),
        reset_expire TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// Verificar e adicionar campos reset_code e reset_expire à tabela mecanicos se não existirem
$check_mec_cols = $con->query("SHOW COLUMNS FROM mecanicos LIKE 'reset_code'");
if (!$check_mec_cols || $check_mec_cols->num_rows == 0) {
    @$con->query("ALTER TABLE mecanicos ADD COLUMN reset_code VARCHAR(10) DEFAULT NULL");
    @$con->query("ALTER TABLE mecanicos ADD COLUMN reset_expire TIMESTAMP NULL");
}

// Adicionar campos de despedimento e status à tabela mecanicos
@$con->query("ALTER TABLE mecanicos ADD COLUMN data_despedimento DATE NULL");
@$con->query("ALTER TABLE mecanicos ADD COLUMN motivo_despedimento VARCHAR(255) NULL");

// Garantir colunas novas para o painel admin atualizado
@$con->query("ALTER TABLE utilizador ADD COLUMN telefone VARCHAR(30) NULL");
@$con->query("ALTER TABLE utilizador ADD COLUMN nif VARCHAR(20) NULL");
@$con->query("ALTER TABLE carro ADD COLUMN notas TEXT NULL");
@$con->query("ALTER TABLE servico ADD COLUMN notas_internas TEXT NULL");

// Criar tabela de histórico de salários (tabela permitida)
$table_check_salario = $con->query("SHOW TABLES LIKE 'historico_salarios'");
if ($table_check_salario && $table_check_salario->num_rows == 0) {
    $con->query("CREATE TABLE historico_salarios (
        id_historico INT PRIMARY KEY AUTO_INCREMENT,
        id_mecanico INT NOT NULL,
        salario_anterior DECIMAL(10,2) NOT NULL,
        salario_novo DECIMAL(10,2) NOT NULL,
        data_alteracao DATE NOT NULL,
        motivo_alteracao VARCHAR(255),
        alterado_por INT NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_mecanico) REFERENCES mecanicos(id_mecanico),
        FOREIGN KEY (alterado_por) REFERENCES chefes_oficina(id_chefe)
    )");
}

require_once __DIR__ . '/inc_admin_notificacoes.php';
require_once __DIR__ . '/chat_lib.php';
require_once __DIR__ . '/inc_chat_schema.php';
require_once __DIR__ . '/inc_nif.php';
require_once __DIR__ . '/inc_iban.php';
require_once __DIR__ . '/inc_telemovel.php';

am_garantir_nif_unico_schema($con);
am_garantir_iban_unico_schema($con);
am_ensure_chat_mensagens($con);

// Código para eliminar orçamentista
if (isset($_GET['eliminar_orcamentista']) || isset($_GET['confirmar_eliminar_orcamentista'])) {
    admin_flash_redirect('dashboard', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> O perfil de orçamentista foi descontinuado.</div>");
}

// Estado de manutenção
$modo_manutencao = false;
$mensagem_manutencao = '';
$estado_site_stmt = $con->prepare("SELECT estado, mensagem FROM estado_site WHERE id = 1");
if ($estado_site_stmt) {
    $estado_site_stmt->execute();
    $estado_site_result = $estado_site_stmt->get_result();
    if ($estado_site_result && $estado_site_result->num_rows > 0) {
        $estado_site = $estado_site_result->fetch_assoc();
        $modo_manutencao = ($estado_site['estado'] == '1');
        $mensagem_manutencao = $estado_site['mensagem'] ?? 'Estamos em manutenção. Por favor volte mais tarde.';
    }
    $estado_site_stmt->close();
}

// Processar todas as ações
$mensagem = '';

$am_especialidades_mecanico = [
    'Mecânica Geral',
    'Motor',
    'Transmissão',
    'Suspensão',
    'Freios',
    'Elétrica',
    'Ar Condicionado',
    'Injeção Eletrônica',
    'Direção',
    'Pintura',
    'Funilaria',
    'Diagnóstico',
];

$am_especialidades_chefe = [
    'Gestão de Oficina',
    'Supervisão',
];

// ==================== FUNÇÃO GENÉRICA DE VALIDAÇÃO ====================
function validarDadosFuncionario($con, $dados, $tipo, array $excluir = []) {
    global $am_especialidades_mecanico, $am_especialidades_chefe;
    $erros = [];
    
    // Validações básicas
    if (empty($dados['nome'])) {
        $erros[] = "Nome é obrigatório";
    } elseif (strlen($dados['nome']) < 3) {
        $erros[] = "Nome deve ter pelo menos 3 caracteres";
    }
    
    if (empty($dados['email'])) {
        $erros[] = "Email é obrigatório";
    } else {
        $email = strtolower(trim($dados['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = "Email inválido";
        } elseif (!preg_match('/@automanager\.pt$/i', $email)) {
            $erros[] = "Email deve terminar com @automanager.pt";
        }
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
    
    if (!empty($dados['salario']) && $dados['salario'] < 0) {
        $erros[] = "Salário não pode ser negativo";
    }
    
    if ($tipo === 'mecanico' && empty($dados['especialidade'])) {
        $erros[] = "Especialidade é obrigatória para mecânicos";
    } elseif ($tipo === 'mecanico' && !in_array($dados['especialidade'] ?? '', $am_especialidades_mecanico, true)) {
        $erros[] = "Especialidade de mecânico inválida";
    }
    
    if ($tipo === 'chefe' && empty($dados['especialidade'])) {
        $erros[] = "Especialidade é obrigatória para chefes de oficina";
    } elseif ($tipo === 'chefe' && !in_array($dados['especialidade'] ?? '', $am_especialidades_chefe, true)) {
        $erros[] = "Especialidade de chefe inválida";
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

function emailAutomanagerValido($email) {
    $email = strtolower(trim((string)$email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/@automanager\.pt$/i', $email);
}

function normalizarLocalPartEmail($email, $fallback) {
    $email = strtolower(trim((string)$email));
    $local = explode('@', $email)[0] ?? '';
    $local = preg_replace('/[^a-z0-9._-]/i', '', $local);
    $local = trim($local, '._-');
    if ($local === '') {
        $local = $fallback;
    }
    return strtolower($local);
}

function emailExisteNoSistema($con, $email) {
    $tabelas = ['utilizador', 'mecanicos', 'chefes_oficina', 'administrador'];
    foreach ($tabelas as $tabela) {
        $stmt = $con->prepare("SELECT 1 FROM $tabela WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($existe) return true;
        }
    }
    return false;
}

function forcarEmailsFuncionariosAutomanager($con) {
    $targets = [
        ['tabela' => 'mecanicos', 'id' => 'id_mecanico'],
        ['tabela' => 'chefes_oficina', 'id' => 'id_chefe'],
    ];

    $alterados = 0;
    $detalhes = [];

    foreach ($targets as $cfg) {
        $rows = $con->query("SELECT {$cfg['id']} AS id, email FROM {$cfg['tabela']}");
        if (!$rows) continue;

        while ($row = $rows->fetch_assoc()) {
            $id = (int)$row['id'];
            $emailAtual = strtolower(trim((string)$row['email']));

            if (emailAutomanagerValido($emailAtual)) {
                continue;
            }

            $base = normalizarLocalPartEmail($emailAtual, 'funcionario' . $id);
            $novo = $base . '@automanager.pt';
            $tentativa = 1;

            while (emailExisteNoSistema($con, $novo)) {
                $tentativa++;
                $novo = $base . $tentativa . '@automanager.pt';
            }

            $upd = $con->prepare("UPDATE {$cfg['tabela']} SET email = ? WHERE {$cfg['id']} = ?");
            if ($upd) {
                $upd->bind_param("si", $novo, $id);
                if ($upd->execute()) {
                    $alterados++;
                    $detalhes[] = "{$cfg['tabela']} #{$id}: {$emailAtual} -> {$novo}";
                }
                $upd->close();
            }
        }
    }

    return ['alterados' => $alterados, 'detalhes' => $detalhes];
}

// ==================== FUNÇÃO GENÉRICA DE CRIAÇÃO ====================
function criarFuncionario($con, $tipo, $dados) {
    $tabela_config = [
        'mecanico' => [
            'tabela' => 'mecanicos',
            'id' => 'id_mecanico',
            'nome_campo' => 'nome_mecanico',
            'email_campo' => 'email',
            'campos' => ['nome_mecanico', 'email', 'password', 'morada', 'contacto', 'ordenado_bruto', 'data_contratacao', 'nif', 'iban', 'especialidade', 'primeiro_login'],
            'tipos' => 'sssssdssssi',
            'login_url' => 'login.php'
        ],
        'chefe' => [
            'tabela' => 'chefes_oficina',
            'id' => 'id_chefe',
            'nome_campo' => 'nome',
            'email_campo' => 'email',
            'campos' => ['nome', 'email', 'password', 'telefone', 'nif', 'iban', 'nivel_acesso', 'primeiro_login'],
            'tipos' => 'sssssssi',
            'login_url' => 'login.php'
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
    
    // Verificar se email já existe em qualquer tabela
    $tabelas_verificacao = ['utilizador', 'mecanicos', 'chefes_oficina', 'administrador'];
    foreach ($tabelas_verificacao as $tabela_check) {
        $check = $con->prepare("SELECT id FROM $tabela_check WHERE email = ? LIMIT 1");
        if ($check) {
            $check->bind_param("s", $dados['email']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                return ['sucesso' => false, 'mensagem' => 'Este email já está registado no sistema'];
            }
            $check->close();
        }
    }

    // Verificar se email já existe em qualquer tabela
    $password = am_password_padrao_funcionario();
    $hash = am_hash_password_padrao_funcionario();
    
    // Extrair dados base
    $email = strtolower(trim($dados['email']));
    $nome = $dados['nome'];
    
    // Preparar dados para inserção baseado na configuração
    $dados_inserir = [];
    $valores_bind = [];
    
    // Processar cada campo na ordem definida pela configuração
    foreach ($config['campos'] as $campo) {
        if ($campo === 'email') {
            $valores_bind[] = $email;
        } elseif ($campo === 'password') {
            $valores_bind[] = $hash;
        } elseif (in_array($campo, ['nome', 'nome_mecanico'])) {
            $valores_bind[] = $dados['nome'];
        } elseif (in_array($campo, ['morada', 'telefone', 'contacto'])) {
            $rawTel = $dados[$campo === 'contacto' ? 'telefone' : $campo] ?? '';
            $valores_bind[] = $rawTel !== '' ? am_normalizar_telemovel((string) $rawTel) : '';
        } elseif ($campo === 'nivel_acesso') {
            // Para chefes, sempre define como 'chefe'
            $valores_bind[] = $tipo === 'chefe' ? 'chefe' : ($dados['nivel_acesso'] ?? '');
        } elseif (in_array($campo, ['salario_bruto', 'ordenado_bruto'])) {
            $valores_bind[] = !empty($dados['salario']) ? floatval($dados['salario']) : null;
        } elseif ($campo === 'data_contratacao') {
            $valores_bind[] = $dados['data_contratacao'] ?? date('Y-m-d');
        } elseif ($campo === 'nif') {
            $valores_bind[] = !empty($dados['nif']) ? am_normalizar_nif((string) $dados['nif']) : '';
        } elseif ($campo === 'iban') {
            $valores_bind[] = !empty($dados['iban']) ? am_normalizar_iban((string) $dados['iban']) : '';
        } elseif ($campo === 'especialidade') {
            $valores_bind[] = $dados['especialidade'] ?? '';
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
    
    // Enviar email se solicitado
    $email_enviado = false;
    if (!empty($dados['enviar_email'])) {
        $tipo_pt = $tipo === 'mecanico' ? 'Mecânico' : 'Chefe de Oficina';
        $assunto = "Bem-vindo à AUTOMANAGER - Credenciais de Acesso";
        
        $salario_info = '';
        if (!empty($dados['salario'])) {
            $salario_formatado = number_format($dados['salario'], 2, ',', '.');
            $salario_info = "<li><strong>Salário Bruto:</strong> €$salario_formatado/mês</li>";
        }
        
        $data_contratacao = $dados['data_contratacao'] ?? date('Y-m-d');
        
        $mensagem_email = "
        <html>
        <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #ff6b00; margin: 0;'>AUTOMANAGER</h1>
                    <p style='color: #666; margin: 5px 0 0 0;'>Oficina Automóvel</p>
                </div>
                
                <h2 style='color: #333;'>Olá, $nome!</h2>
                <p style='color: #666; line-height: 1.6;'>Bem-vindo(a) à equipa da <strong style='color: #ff6b00;'>AUTOMANAGER</strong> como <strong>$tipo_pt</strong>.</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff6b00;'>
                    <h3 style='color: #333; margin-top: 0;'><i class='bi bi-shield-lock'></i> Credenciais de Acesso</h3>
                    <ul style='color: #666; line-height: 1.8; list-style: none; padding: 0;'>
                        <li><strong>Email:</strong> <code style='background: #e8e8e8; padding: 5px 10px; border-radius: 3px;'>$email</code></li>
                        <li><strong>Password:</strong> <code style='background: #e8e8e8; padding: 5px 10px; border-radius: 3px;'>$password</code></li>
                        <li><strong>URL de Acesso:</strong> <a href='http://" . $_SERVER['HTTP_HOST'] . "/login.php' style='color: #ff6b00;'>http://" . $_SERVER['HTTP_HOST'] . "/login.php</a></li>
                    </ul>
                </div>

                <div style='background: #f3f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #333; margin-top: 0;'><i class='bi bi-card-list'></i> Informações do Contrato</h3>
                    <ul style='color: #666; line-height: 1.8; list-style: none; padding: 0;'>
                        <li><strong>Função:</strong> " . htmlspecialchars($tipo_pt) . "</li>
                        <li><strong>Data de Contratação:</strong> " . date('d/m/Y', strtotime($data_contratacao)) . "</li>
                        " . $salario_info . "
                    </ul>
                </div>

                <div style='background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #333; margin-top: 0;'><i class='bi bi-exclamation-triangle'></i> Importante</h3>
                    <p style='color: #666; margin-bottom: 15px;'>
                        <strong>Esta é a palavra-passe inicial da oficina.</strong> No primeiro login serás obrigado(a) a definir uma nova palavra-passe pessoal.
                    </p>
                </div>

                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <p style='color: #999; font-size: 12px;'>Bem-vindo(a) à equipa <strong>AUTOMANAGER</strong> </p>
                </div>
            </div>
        </body>
        </html>";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: AUTOMANAGER RH <rh@AUTOMANAGER.pt>\r\n";
        $headers .= "Reply-To: suporte@AUTOMANAGER.pt\r\n";
        
        // Usar mail com tratamento adequado
        if (mail($email, $assunto, $mensagem_email, $headers)) {
            $email_enviado = true;
        } else {
            error_log("Falha ao enviar email para: $email");
        }
    }
    
    $msg_final = "<i class='bi bi-check-circle'></i> $tipo_pt criado(a) com sucesso";
    if ($email_enviado) {
        $msg_final .= " e email enviado!";
    } else if (!empty($dados['enviar_email'])) {
        $msg_final .= ". <i class='bi bi-exclamation-triangle'></i> Email não foi enviado (verificar servidor de emails). <br/>Password: <strong>$password</strong>";
    } else {
        $msg_final .= '. ' . am_texto_credenciais_funcionario_html();
    }
    
    return ['sucesso' => true, 'mensagem' => $msg_final, 'password' => $password];
}

function admin_flash_redirect(string $tab, string $mensagemHtml): void
{
    $_SESSION['admin_flash_msg'] = $mensagemHtml;
    $tab = preg_replace('/[^a-z0-9_\-]/', '', $tab) ?: 'dashboard';
    header('Location: indexadmin.php?tab=' . rawurlencode($tab) . '#' . rawurlencode($tab));
    exit;
}

// ==================== PROCESSAMENTO DE FORMULÁRIOS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['criar_mecanico'])) {
        $resultado = criarFuncionario($con, 'mecanico', [
            'nome' => $_POST['nome_mecanico'] ?? '',
            'email' => $_POST['email_mecanico'] ?? '',
            'morada' => $_POST['morada_mecanico'] ?? '',
            'telefone' => $_POST['telefone_mecanico'] ?? '',
            'salario' => $_POST['salario_mecanico'] ?? '',
            'data_contratacao' => $_POST['data_contratacao_mecanico'] ?? date('Y-m-d'),
            'nif' => $_POST['nif_mecanico'] ?? '',
            'iban' => $_POST['iban_mecanico'] ?? '',
            'especialidade' => $_POST['especialidade_mecanico'] ?? '',
            'enviar_email' => isset($_POST['enviar_email_mecanico'])
        ]);
        admin_flash_redirect('mecanicos', $resultado['sucesso']
            ? "<div class='mensagem-sucesso'>{$resultado['mensagem']}</div>"
            : "<div class='mensagem-erro'>{$resultado['mensagem']}</div>");
    }
    
    elseif (isset($_POST['criar_chefe_oficina'])) {
        $resultado = criarFuncionario($con, 'chefe', [
            'nome' => $_POST['nome_chefe_oficina'] ?? '',
            'email' => $_POST['email_chefe_oficina'] ?? '',
            'morada' => $_POST['morada_chefe_oficina'] ?? '',
            'telefone' => $_POST['telefone_chefe_oficina'] ?? '',
            'salario' => $_POST['salario_chefe_oficina'] ?? '',
            'data_contratacao' => $_POST['data_contratacao_chefe'] ?? date('Y-m-d'),
            'nif' => $_POST['nif_chefe_oficina'] ?? '',
            'iban' => $_POST['iban_chefe_oficina'] ?? '',
            'especialidade' => $_POST['especialidade_chefe_oficina'] ?? '',
            'enviar_email' => isset($_POST['enviar_email_chefe'])
        ]);
        admin_flash_redirect('chefes_oficina', $resultado['sucesso']
            ? "<div class='mensagem-sucesso'>{$resultado['mensagem']}</div>"
            : "<div class='mensagem-erro'>{$resultado['mensagem']}</div>");
    }
}

// Ativar/Desativar modo manutenção
if (isset($_POST['toggle_manutencao'])) {
    $novo_estado = $modo_manutencao ? '0' : '1';
    $mensagem_manutencao = $_POST['mensagem_manutencao'] ?? 'Estamos em manutenção. Por favor volte mais tarde.';
    
    $update_stmt = $con->prepare("UPDATE estado_site SET estado = ?, mensagem = ? WHERE id = 1");
    if ($update_stmt) {
        $update_stmt->bind_param("ss", $novo_estado, $mensagem_manutencao);
        if ($update_stmt->execute()) {
            $modo_manutencao = !$modo_manutencao;
            $msgManut = $modo_manutencao
                ? "<div class='mensagem-sucesso'><i class='bi bi-tools'></i> Modo de manutenção ativado com sucesso!</div>"
                : "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Modo de manutenção desativado com sucesso!</div>";
            admin_flash_redirect('dashboard', $msgManut);
        }
        $update_stmt->close();
    }
    admin_flash_redirect('dashboard', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao alterar modo de manutenção.</div>");
}

if (isset($_POST['salvar_utilizador_admin'])) {
    $idUser = (int)($_POST['id_utilizador'] ?? 0);
    $nomeUser = trim($_POST['nome_utilizador'] ?? '');
    $emailUser = trim($_POST['email_utilizador'] ?? '');
    $telefoneUser = am_normalizar_telemovel((string) ($_POST['telefone_utilizador'] ?? ''));
    $nifUser = am_normalizar_nif((string) ($_POST['nif_utilizador'] ?? ''));

    if ($idUser <= 0 || $nomeUser === '' || !filter_var($emailUser, FILTER_VALIDATE_EMAIL)) {
        admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Dados do utilizador inválidos.</div>");
    }
    if ($telefoneUser !== '') {
        [$telOkUser, $telMsgUser] = am_validar_telemovel_portugues($telefoneUser);
        if (!$telOkUser) {
            admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> " . htmlspecialchars($telMsgUser) . "</div>");
        }
    }
    if ($nifUser !== '') {
        [$nifOkUser, $nifMsgUser] = am_validar_nif_unico($con, $nifUser, ['utilizador' => $idUser]);
        if (!$nifOkUser) {
            admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> " . htmlspecialchars($nifMsgUser) . "</div>");
        }
    }
    $upUser = $con->prepare("UPDATE utilizador SET nome_utilizador = ?, email = ?, telefone = ?, nif = ? WHERE id_utilizador = ?");
    if ($upUser) {
        $upUser->bind_param("ssssi", $nomeUser, $emailUser, $telefoneUser, $nifUser, $idUser);
        if ($upUser->execute()) {
            admin_flash_redirect('utilizadores', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Utilizador atualizado com sucesso.</div>");
        }
        $upUser->close();
    }
    admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao atualizar utilizador.</div>");
}

if (isset($_POST['salvar_veiculo_admin'])) {
    $idCarro = (int)($_POST['id_carro'] ?? 0);
    $idUtilizador = (int)($_POST['id_utilizador_carro'] ?? 0);
    $marcaCarro = trim($_POST['marca_carro'] ?? '');
    $modeloCarro = trim($_POST['modelo_carro'] ?? '');
    $anoCarro = (int)($_POST['ano_carro'] ?? 0);
    $notasCarro = trim($_POST['notas_carro'] ?? '');
    $principalCarro = isset($_POST['principal_carro']) ? 1 : 0;

    [$matriculaOk, $matriculaErro, $matriculaFmt] = am_validar_matricula($_POST['matricula_carro'] ?? '');

    if ($idCarro <= 0 || $idUtilizador <= 0 || $marcaCarro === '' || $modeloCarro === '' || !$matriculaOk) {
        $msgErro = $matriculaOk ? 'Dados do veículo inválidos.' : $matriculaErro;
        admin_flash_redirect('veiculos', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> " . htmlspecialchars($msgErro) . "</div>");
    }
    [$matriculaUnica, $msgMatriculaUnica] = am_validar_matricula_unica($con, $matriculaFmt, $idCarro);
    if (!$matriculaUnica) {
        admin_flash_redirect('veiculos', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> " . htmlspecialchars($msgMatriculaUnica) . "</div>");
    }
    $upCar = $con->prepare("UPDATE carro SET id_utilizador = ?, marca = ?, modelo = ?, ano = ?, matricula = ?, notas = ? WHERE id_carro = ?");
    if ($upCar) {
        $upCar->bind_param("ississi", $idUtilizador, $marcaCarro, $modeloCarro, $anoCarro, $matriculaFmt, $notasCarro, $idCarro);
        if ($upCar->execute()) {
            if ($principalCarro === 1) {
                @$con->query("UPDATE carro SET is_principal = 0 WHERE id_utilizador = " . (int)$idUtilizador);
                @$con->query("UPDATE carro SET is_principal = 1 WHERE id_carro = " . (int)$idCarro);
            }
            admin_flash_redirect('veiculos', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Veículo atualizado com sucesso.</div>");
        }
        $upCar->close();
    }
    admin_flash_redirect('veiculos', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao atualizar veículo.</div>");
}

if (isset($_POST['adicionar_marca_catalogo'])) {
    am_ensure_marcas_modelos_tables($con);
    $nomeMarcaNova = trim((string) ($_POST['nome_marca_nova'] ?? ''));
    if ($nomeMarcaNova === '') {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Indique o nome da marca.</div>");
    }
    $st = $con->prepare('INSERT INTO marcas (nome) VALUES (?)');
    if ($st) {
        $st->bind_param('s', $nomeMarcaNova);
        if ($st->execute()) {
            admin_flash_redirect('catalogo', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Marca adicionada com sucesso.</div>");
        }
        $st->close();
    }
    admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Não foi possível adicionar a marca (pode já existir).</div>");
}

if (isset($_POST['editar_marca_catalogo'])) {
    am_ensure_marcas_modelos_tables($con);
    $idMarcaEdit = (int) ($_POST['id_marca'] ?? 0);
    $nomeMarcaEdit = trim((string) ($_POST['nome_marca'] ?? ''));
    if ($idMarcaEdit <= 0 || $nomeMarcaEdit === '') {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Dados da marca inválidos.</div>");
    }
    $st = $con->prepare('UPDATE marcas SET nome = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('si', $nomeMarcaEdit, $idMarcaEdit);
        if ($st->execute()) {
            admin_flash_redirect('catalogo', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Marca atualizada.</div>");
        }
        $st->close();
    }
    admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao atualizar marca.</div>");
}

if (isset($_GET['del_marca_catalogo'])) {
    am_ensure_marcas_modelos_tables($con);
    $idMarcaDel = (int) $_GET['del_marca_catalogo'];
    if ($idMarcaDel <= 0) {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Marca inválida.</div>");
    }
    if (am_marca_contar_modelos($con, $idMarcaDel) > 0) {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-exclamation-triangle'></i> Elimine primeiro os modelos desta marca.</div>");
    }
    $st = $con->prepare('DELETE FROM marcas WHERE id = ?');
    if ($st) {
        $st->bind_param('i', $idMarcaDel);
        if ($st->execute() && $st->affected_rows > 0) {
            $st->close();
            admin_flash_redirect('catalogo', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Marca eliminada.</div>");
        }
        $st->close();
    }
    admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar marca.</div>");
}

if (isset($_POST['adicionar_modelo_catalogo'])) {
    am_ensure_marcas_modelos_tables($con);
    $idMarcaModelo = (int) ($_POST['id_marca_modelo'] ?? 0);
    $nomeModeloNovo = trim((string) ($_POST['nome_modelo_novo'] ?? ''));
    if ($idMarcaModelo <= 0 || $nomeModeloNovo === '') {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Selecione a marca e indique o modelo.</div>");
    }
    $st = $con->prepare('INSERT INTO modelos (id_marca, nome) VALUES (?, ?)');
    if ($st) {
        $st->bind_param('is', $idMarcaModelo, $nomeModeloNovo);
        if ($st->execute()) {
            admin_flash_redirect('catalogo', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Modelo adicionado com sucesso.</div>");
        }
        $st->close();
    }
    admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Não foi possível adicionar o modelo.</div>");
}

if (isset($_POST['editar_modelo_catalogo'])) {
    am_ensure_marcas_modelos_tables($con);
    $idModeloEdit = (int) ($_POST['id_modelo'] ?? 0);
    $idMarcaModeloEdit = (int) ($_POST['id_marca_modelo_edit'] ?? 0);
    $nomeModeloEdit = trim((string) ($_POST['nome_modelo'] ?? ''));
    if ($idModeloEdit <= 0 || $idMarcaModeloEdit <= 0 || $nomeModeloEdit === '') {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Dados do modelo inválidos.</div>");
    }
    $st = $con->prepare('UPDATE modelos SET id_marca = ?, nome = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('isi', $idMarcaModeloEdit, $nomeModeloEdit, $idModeloEdit);
        if ($st->execute()) {
            admin_flash_redirect('catalogo', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Modelo atualizado.</div>");
        }
        $st->close();
    }
    admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao atualizar modelo.</div>");
}

if (isset($_GET['del_modelo_catalogo'])) {
    am_ensure_marcas_modelos_tables($con);
    $idModeloDel = (int) $_GET['del_modelo_catalogo'];
    if ($idModeloDel <= 0) {
        admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Modelo inválido.</div>");
    }
    $st = $con->prepare('DELETE FROM modelos WHERE id = ?');
    if ($st) {
        $st->bind_param('i', $idModeloDel);
        if ($st->execute() && $st->affected_rows > 0) {
            $st->close();
            admin_flash_redirect('catalogo', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Modelo eliminado.</div>");
        }
        $st->close();
    }
    admin_flash_redirect('catalogo', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar modelo.</div>");
}

if (isset($_POST['guardar_notas_marcacao_admin'])) {
    $idServico = (int)($_POST['id_servico_nota'] ?? 0);
    $notaInterna = trim($_POST['nota_interna'] ?? '');
    if ($idServico > 0) {
        $upNota = $con->prepare("UPDATE servico SET notas_internas = ? WHERE id = ?");
        if ($upNota) {
            $upNota->bind_param("si", $notaInterna, $idServico);
            if ($upNota->execute()) {
                $_SESSION['admin_flash_msg'] = "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Nota interna guardada.</div>";
            } else {
                $_SESSION['admin_flash_msg'] = "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao guardar nota interna.</div>";
            }
            $upNota->close();
            header('Location: indexadmin.php?tab=marcacoes&marcacao_focus=' . $idServico . '#marcacoes');
            exit;
        }
    }
}

if (isset($_GET['force_emails_funcionarios'])) {
    $resultadoForce = forcarEmailsFuncionariosAutomanager($con);
    admin_flash_redirect('dashboard', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Correção forçada concluída. {$resultadoForce['alterados']} email(s) atualizado(s) para @automanager.pt.</div>");
}

if (isset($_GET['aplicar_credenciais_padrao'])) {
    $senhaPadrao = 'Automanager26!';
    $hashPadrao = password_hash($senhaPadrao, PASSWORD_DEFAULT);
    $sucessoCredenciais = false;

    $emailMec = 'mecanico@automanager.pt';
    $resMec = $con->query("SELECT id_mecanico FROM mecanicos ORDER BY id_mecanico ASC LIMIT 1");
    if ($resMec && $resMec->num_rows > 0) {
        $idMec = (int)$resMec->fetch_assoc()['id_mecanico'];
        $upMec = $con->prepare("UPDATE mecanicos SET nome_mecanico = 'Mecânico', email = ?, password = ?, especialidade = 'Mecânica Geral', ativo = 1, primeiro_login = 0 WHERE id_mecanico = ?");
        if ($upMec) {
            $upMec->bind_param("ssi", $emailMec, $hashPadrao, $idMec);
            $sucessoCredenciais = $upMec->execute() || $sucessoCredenciais;
            $upMec->close();
        }
    }

    $emailChefe = 'chefeoficina@automanager.pt';
    $resChefe = $con->query("SELECT id_chefe FROM chefes_oficina ORDER BY id_chefe ASC LIMIT 1");
    if ($resChefe && $resChefe->num_rows > 0) {
        $idChefe = (int)$resChefe->fetch_assoc()['id_chefe'];
        $upChefe = $con->prepare("UPDATE chefes_oficina SET nome = 'Chefe Oficina', email = ?, password = ?, nivel_acesso = 'chefe', ativo = 1, primeiro_login = 0 WHERE id_chefe = ?");
        if ($upChefe) {
            $upChefe->bind_param("ssi", $emailChefe, $hashPadrao, $idChefe);
            $sucessoCredenciais = $upChefe->execute() || $sucessoCredenciais;
            $upChefe->close();
        }
    }

    if ($sucessoCredenciais) {
        admin_flash_redirect('dashboard', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Credenciais padrão aplicadas com sucesso.<br>Mecânico: mecanico@automanager.pt<br>Chefe Oficina: chefeoficina@automanager.pt<br>Palavra-passe: Automanager26!</div>");
    }
    admin_flash_redirect('dashboard', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Não foi possível aplicar as credenciais padrão. Verifique se existem registos em cada tabela.</div>");
}

// Eliminar utilizador
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Primeiro, verificar se o utilizador tem serviços associados
    $check_servicos = $con->query("SELECT COUNT(*) as total FROM servico WHERE email IN (SELECT email FROM utilizador WHERE id_utilizador = $id)");
    $check_chat_count = chat_count_user_messages($con, $id);
    
    $result_servicos = $check_servicos ? $check_servicos->fetch_assoc() : ['total' => 0];
    $result_chat = ['total' => $check_chat_count];
    
    if ($result_servicos['total'] > 0 || $result_chat['total'] > 0) {
        admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-exclamation-triangle'></i> Não é possível eliminar — existem registos associados. Bloqueie o utilizador em vez disso.</div>");
    }
    $stmt = $con->prepare("DELETE FROM utilizador WHERE id_utilizador=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            admin_flash_redirect('utilizadores', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Utilizador eliminado com sucesso!</div>");
        }
        $stmt->close();
    }
    admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar utilizador.</div>");
}

// BLOQUEAR/DESBLOQUEAR utilizador
if (isset($_GET['toggle_block'])) {
    $id = intval($_GET['toggle_block']);
    
    $stmt = $con->prepare("SELECT bloqueado FROM utilizador WHERE id_utilizador=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        $novo_estado = $user['bloqueado'] ? 0 : 1;
        $data_bloqueio = $novo_estado ? date('Y-m-d H:i:s') : NULL;
        
        $stmt = $con->prepare("UPDATE utilizador SET bloqueado=?, data_bloqueio=? WHERE id_utilizador=?");
        if ($stmt) {
            $stmt->bind_param("isi", $novo_estado, $data_bloqueio, $id);
            if ($stmt->execute()) {
                $msgBlock = $novo_estado
                    ? "<div class='mensagem-sucesso'><i class='bi bi-shield-lock'></i> Utilizador bloqueado com sucesso!</div>"
                    : "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Utilizador desbloqueado com sucesso!</div>";
                admin_flash_redirect('utilizadores', $msgBlock);
            }
            $stmt->close();
        }
    }
    admin_flash_redirect('utilizadores', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao alterar estado do utilizador.</div>");
}

// Eliminar mecânico
if (isset($_GET['delete_mecanico'])) {
    $id = intval($_GET['delete_mecanico']);
    
    // Verificar se o mecânico tem serviços associados
    $check_servicos = $con->query("SELECT COUNT(*) as total FROM servico WHERE mecanico_id = $id");
    $result_check = $check_servicos ? $check_servicos->fetch_assoc() : ['total' => 0];
    
    if ($result_check['total'] > 0) {
        admin_flash_redirect('mecanicos', "<div class='mensagem-erro'><i class='bi bi-exclamation-triangle'></i> Não é possível eliminar — existem serviços associados. Reatribua primeiro.</div>");
    }
    $stmt = $con->prepare("DELETE FROM mecanicos WHERE id_mecanico=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            admin_flash_redirect('mecanicos', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Mecânico eliminado com sucesso!</div>");
        }
        $stmt->close();
    }
    admin_flash_redirect('mecanicos', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar mecânico.</div>");
}

// Responder feedback
if (isset($_GET['responder_feedback'])) {
    $id = intval($_GET['responder_feedback']);
    if (am_feedback_update_estado($con, $id, 'respondido')) {
        admin_flash_redirect('feedbacks', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Feedback marcado como respondido.</div>");
    }
    admin_flash_redirect('feedbacks', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao responder ao feedback.</div>");
}

// Marcar feedback como resolvido
if (isset($_GET['resolver_feedback'])) {
    $id = intval($_GET['resolver_feedback']);
    if (am_feedback_update_estado($con, $id, 'resolvido')) {
        admin_flash_redirect('feedbacks', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Mensagem marcada como resolvida!</div>");
    }
    admin_flash_redirect('feedbacks', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao marcar como resolvida.</div>");
}

// Eliminar feedback
if (isset($_GET['eliminar_feedback'])) {
    $id = intval($_GET['eliminar_feedback']);
    if (am_notif_delete($con, $id)) {
        admin_flash_redirect('feedbacks', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Mensagem eliminada com sucesso!</div>");
    }
    admin_flash_redirect('feedbacks', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar mensagem.</div>");
}

// Buscar dados
$utilizadores_result = $con->query("SELECT id_utilizador, nome_utilizador, email, telefone, nif, carro, bloqueado, data_bloqueio FROM utilizador ORDER BY nome_utilizador");
$utilizadores_para_select = $con->query("SELECT id_utilizador, nome_utilizador, email FROM utilizador ORDER BY nome_utilizador");

// Verificar e adicionar campos reset_code e reset_expire à tabela utilizador se não existirem
$check_util_cols = $con->query("SHOW COLUMNS FROM utilizador LIKE 'reset_code'");
if (!$check_util_cols || $check_util_cols->num_rows == 0) {
    @$con->query("ALTER TABLE utilizador ADD COLUMN reset_code VARCHAR(10) DEFAULT NULL");
    @$con->query("ALTER TABLE utilizador ADD COLUMN reset_expire TIMESTAMP NULL");
}

$mecanicos_result = $con->query("SELECT * FROM mecanicos ORDER BY nome_mecanico");
$feedbacks_list = am_feedback_list($con);
// Verificar se a tabela chefes_oficina existe e criar se necessário
$table_check6 = $con->query("SHOW TABLES LIKE 'chefes_oficina'");
if ($table_check6 && $table_check6->num_rows == 0) {
    $con->query("CREATE TABLE chefes_oficina (
        id_chefe INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        telefone VARCHAR(20),
        nivel_acesso VARCHAR(20),
        primeiro_login TINYINT DEFAULT 1,
        reset_code VARCHAR(10),
        reset_expire TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Verificar e adicionar campos reset_code e reset_expire à tabela chefes_oficina se não existirem
$check_chef_cols = $con->query("SHOW COLUMNS FROM chefes_oficina LIKE 'reset_code'");
if (!$check_chef_cols || $check_chef_cols->num_rows == 0) {
    @$con->query("ALTER TABLE chefes_oficina ADD COLUMN reset_code VARCHAR(10) DEFAULT NULL");
    @$con->query("ALTER TABLE chefes_oficina ADD COLUMN reset_expire TIMESTAMP NULL");
}

// Verificar e adicionar coluna ativo à tabela chefes_oficina se não existir
$check_ativo = $con->query("SHOW COLUMNS FROM chefes_oficina LIKE 'ativo'");
if (!$check_ativo || $check_ativo->num_rows == 0) {
    @$con->query("ALTER TABLE chefes_oficina ADD COLUMN ativo TINYINT DEFAULT 1");
}

// Buscar chefes de oficina
$chefes_result = $con->query("SELECT * FROM chefes_oficina ORDER BY nome");

// Eliminar chefe de oficina (confirmação via modal no browser)
if (isset($_GET['confirmar_eliminar_chefe'])) {
    $id_confirmar = (int) $_GET['confirmar_eliminar_chefe'];

    if ($id_confirmar <= 0) {
        admin_flash_redirect('chefes_oficina', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Chefe de oficina inválido.</div>");
    }

    $totalMecanicos = 0;
    $colChefe = $con->query("SHOW COLUMNS FROM mecanicos LIKE 'id_chefe'");
    if ($colChefe && $colChefe->num_rows > 0) {
        $stmtCount = $con->prepare('SELECT COUNT(*) AS total FROM mecanicos WHERE id_chefe = ?');
        if ($stmtCount) {
            $stmtCount->bind_param('i', $id_confirmar);
            $stmtCount->execute();
            $resCount = $stmtCount->get_result();
            if ($resCount) {
                $totalMecanicos = (int) ($resCount->fetch_assoc()['total'] ?? 0);
            }
            $stmtCount->close();
        }
    }

    if ($totalMecanicos > 0) {
        admin_flash_redirect(
            'chefes_oficina',
            "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Não é possível eliminar este chefe porque existem mecânicos associados. Remova ou transfira os mecânicos primeiro.</div>"
        );
    }

    $delete_stmt = $con->prepare('DELETE FROM chefes_oficina WHERE id_chefe = ?');
    if (!$delete_stmt) {
        admin_flash_redirect('chefes_oficina', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar chefe de oficina.</div>");
    }

    $delete_stmt->bind_param('i', $id_confirmar);
    if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
        $delete_stmt->close();
        admin_flash_redirect('chefes_oficina', "<div class='mensagem-sucesso'><i class='bi bi-check-circle'></i> Chefe de oficina eliminado com sucesso.</div>");
    }

    $delete_stmt->close();
    admin_flash_redirect('chefes_oficina', "<div class='mensagem-erro'><i class='bi bi-x-circle'></i> Erro ao eliminar chefe de oficina.</div>");
}

// Contar feedbacks pendentes
$feedbacks_pendentes = am_feedback_count_pendentes($con);

// Contar estatísticas
$total_utilizadores = $con->query("SELECT COUNT(*) as total FROM utilizador")->fetch_assoc()['total'];
$total_mecanicos = $con->query("SELECT COUNT(*) as total FROM mecanicos")->fetch_assoc()['total'];
$total_chefes = $con->query("SELECT COUNT(*) as total FROM chefes_oficina")->fetch_assoc()['total'];

// Contar serviços por estado
$servicos_por_fazer = $con->query("SELECT COUNT(*) as total FROM servico WHERE estado_do_trabalho = 'Por Fazer'")->fetch_assoc()['total'];
$servicos_em_andamento = $con->query("SELECT COUNT(*) as total FROM servico WHERE estado_do_trabalho = 'Em Andamento'")->fetch_assoc()['total'];

$dashboard_status = [
    'Concluído' => 0,
    'Cancelado' => 0,
    'Recusado' => 0,
    'Orçamento enviado' => 0,
    'Pendente' => 0,
    'Atribuído' => 0,
    'Em andamento' => 0,
    'Aprovado' => 0,
];
$status_rs = @$con->query("
    SELECT LOWER(TRIM(COALESCE(estado_do_trabalho, estado, 'pendente'))) AS st, COUNT(*) AS total
    FROM servico
    GROUP BY LOWER(TRIM(COALESCE(estado_do_trabalho, estado, 'pendente')))
");
if ($status_rs) {
    while ($st = $status_rs->fetch_assoc()) {
        $k = $st['st'];
        $v = (int)$st['total'];
        if (strpos($k, 'conclu') !== false || strpos($k, 'pronto') !== false) $dashboard_status['Concluído'] += $v;
        elseif (strpos($k, 'cancel') !== false) $dashboard_status['Cancelado'] += $v;
        elseif (strpos($k, 'recus') !== false) $dashboard_status['Recusado'] += $v;
        elseif (strpos($k, 'orc') !== false) $dashboard_status['Orçamento enviado'] += $v;
        elseif (strpos($k, 'atrib') !== false) $dashboard_status['Atribuído'] += $v;
        elseif (strpos($k, 'andamento') !== false) $dashboard_status['Em andamento'] += $v;
        elseif (strpos($k, 'aprov') !== false) $dashboard_status['Aprovado'] += $v;
        else $dashboard_status['Pendente'] += $v;
    }
}

$labels_7d = [];
$values_7d = [];
for ($i = 6; $i >= 0; $i--) {
    $labels_7d[] = date('d/m', strtotime("-$i day"));
    $values_7d[] = 0;
}
$mapIndex = [];
foreach ($labels_7d as $idx => $lbl) $mapIndex[$lbl] = $idx;
$q7d = @$con->query("
    SELECT DATE(COALESCE(data_desejada, data_registo, created_at)) AS d, COUNT(*) AS total
    FROM servico
    WHERE DATE(COALESCE(data_desejada, data_registo, created_at)) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(COALESCE(data_desejada, data_registo, created_at))
");
if ($q7d) {
    while ($r = $q7d->fetch_assoc()) {
        if (!empty($r['d'])) {
            $lbl = date('d/m', strtotime($r['d']));
            if (isset($mapIndex[$lbl])) $values_7d[$mapIndex[$lbl]] = (int)$r['total'];
        }
    }
}

$admin_tab = $_GET['tab'] ?? 'dashboard';
$admin_tabs_validas = [
    'dashboard', 'marcacoes', 'utilizadores', 'veiculos', 'catalogo',
    'criar-funcionario', 'mecanicos', 'chefes_oficina',
];
if (!in_array($admin_tab, $admin_tabs_validas, true)) {
    $admin_tab = 'dashboard';
}

if (!function_exists('admin_servico_col_label')) {
    function admin_servico_col_label(string $col): string
    {
        static $map = [
            'id' => 'ID',
            'nome' => 'Cliente',
            'email' => 'Email',
            'telefone' => 'Telefone',
            'nif' => 'NIF',
            'matricula' => 'Matrícula',
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'tipo_servico' => 'Serviço',
            'data_desejada' => 'Data desejada',
            'hora_desejada' => 'Hora',
            'data_registo' => 'Data registo',
            'estado' => 'Estado',
            'estado_do_trabalho' => 'Estado trabalho',
            'estado_marcacao' => 'Estado marcação',
            'mecanico_id' => 'Mecânico',
            'custo' => 'Custo',
            'estado_orcamento' => 'Orçamento',
            'orcamento_aprovado' => 'Orç. aprovado',
            'estado_orcamento_cliente' => 'Orç. cliente',
            'estado_pagamento' => 'Pagamento',
            'metodo_pagamento' => 'Método pag.',
            'valor_total_com_iva' => 'Total c/ IVA',
            'subtotal_sem_iva' => 'Subtotal',
            'valor_iva' => 'IVA',
            'valor_pago_total' => 'Pago',
            'valor_em_divida' => 'Em dívida',
            'data_conclusao' => 'Conclusão',
            'numero_fatura' => 'Fatura n.º',
            'notas_internas' => 'Notas internas',
            'created_at' => 'Criado em',
        ];

        return $map[$col] ?? ucwords(str_replace('_', ' ', $col));
    }
}

if (!function_exists('admin_servico_format_celula')) {
    function admin_servico_format_celula(string $col, $val, array $row): string
    {
        if ($col === 'mecanico_id') {
            $nome = trim((string) ($row['nome_mecanico'] ?? ''));
            if ($nome !== '') {
                return htmlspecialchars($nome);
            }
            return $val !== null && $val !== '' ? htmlspecialchars((string) $val) : '—';
        }

        if ($val === null || $val === '') {
            return '—';
        }

        $datas = [
            'data_desejada', 'data_registo', 'data_conclusao', 'data_pagamento', 'data_cancelamento',
            'created_at', 'data_orcamento_aceite', 'data_orcamento_recusado',
            'sla_inicio_at', 'sla_limite_at', 'codigo_levantamento_emitido_em', 'codigo_levantamento_usado_em',
        ];
        if (in_array($col, $datas, true) && strtotime((string) $val)) {
            return htmlspecialchars(date('d/m/Y H:i', strtotime((string) $val)));
        }

        if ($col === 'hora_desejada') {
            return htmlspecialchars((string) $val);
        }

        $monetarios = [
            'custo', 'orcamento_aprovado', 'subtotal_sem_iva', 'valor_iva', 'valor_total_com_iva',
            'valor_pago_total', 'valor_em_divida', 'valor_hora', 'taxa_cancelamento', 'sla_compensacao_valor',
        ];
        if (in_array($col, $monetarios, true) && is_numeric($val)) {
            return htmlspecialchars(number_format((float) $val, 2, ',', '.') . ' €');
        }

        $texto = (string) $val;
        if (strlen($texto) > 100 && (strpos($col, 'json') !== false || strpos($col, 'notas') !== false || strpos($col, 'pecas') !== false)) {
            return htmlspecialchars(substr($texto, 0, 97)) . '…';
        }

        return htmlspecialchars($texto);
    }
}

$marcacoes_colunas = [
    'id',
    'nome',
    'matricula',
    'tipo_servico',
    'data_desejada',
    'hora_desejada',
    'custo',
];

$marcacoes_result = @$con->query("
    SELECT s.*, m.nome_mecanico, u.id_utilizador AS id_utilizador_cliente
    FROM servico s
    LEFT JOIN mecanicos m ON m.id_mecanico = s.mecanico_id
    LEFT JOIN utilizador u ON LOWER(TRIM(u.email)) = LOWER(TRIM(s.email))
    ORDER BY s.id DESC
");

$veiculos_result = @$con->query("
    SELECT 
        c.id_carro,
        c.marca,
        c.modelo,
        c.ano,
        c.matricula,
        c.is_principal,
        c.notas,
        c.id_utilizador,
        u.nome_utilizador,
        u.email
    FROM carro c
    LEFT JOIN utilizador u ON u.id_utilizador = c.id_utilizador
    ORDER BY c.id_carro DESC
    LIMIT 250
");

$marcas_catalogo = am_marcas_listar($con);
$modelos_catalogo = am_modelos_listar_completo($con);
$total_marcas_catalogo = count($marcas_catalogo);
$total_modelos_catalogo = count($modelos_catalogo);

$naoLidas = chat_unread_admin_total($con);

// Buscar últimas mensagens do chat
$ultimasMensagens = chat_latest_user_messages($con, 5);
foreach ($ultimasMensagens as &$um) {
    $uid = (int) ($um['de_usuario_id'] ?? 0);
    $stmtU = $con->prepare('SELECT email FROM utilizador WHERE id_utilizador = ? LIMIT 1');
    if ($stmtU) {
        $stmtU->bind_param('i', $uid);
        $stmtU->execute();
        $rowU = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $um['usuario_email'] = $rowU['email'] ?? '';
    }
}
unset($um);

if (!empty($_SESSION['admin_flash_msg'])) {
    $mensagem = $_SESSION['admin_flash_msg'];
    unset($_SESSION['admin_flash_msg']);
}

$admin_nav = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    ['id' => 'marcacoes', 'label' => 'Marcações', 'icon' => 'bi-calendar-check'],
    ['id' => 'utilizadores', 'label' => 'Utilizadores', 'icon' => 'bi-people'],
    ['id' => 'veiculos', 'label' => 'Veículos', 'icon' => 'bi-car-front'],
    ['id' => 'catalogo', 'label' => 'Marcas / Modelos', 'icon' => 'bi-list-ul'],
    ['id' => 'criar-funcionario', 'label' => 'Novo funcionário', 'icon' => 'bi-person-plus'],
    ['id' => 'mecanicos', 'label' => 'Mecânicos', 'icon' => 'bi-tools'],
    ['id' => 'chefes_oficina', 'label' => 'Chefes', 'icon' => 'bi-person-badge'],
];

$admin_nav_groups = [
    'Operação' => array_values(array_filter($admin_nav, static function ($item) {
        return in_array($item['id'], ['dashboard', 'marcacoes', 'utilizadores', 'veiculos', 'catalogo'], true);
    })),
    'Equipa' => array_values(array_filter($admin_nav, static function ($item) {
        return in_array($item['id'], ['criar-funcionario', 'mecanicos', 'chefes_oficina'], true);
    })),
];

$admin_section_labels = [];
foreach ($admin_nav as $navItem) {
    $admin_section_labels[$navItem['id']] = $navItem['label'];
}
$admin_page_title = $admin_section_labels[$admin_tab] ?? 'Painel';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração</title>
</head>
<body class="admin-panel">
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Menu principal">
        <div class="admin-sidebar__brand">
            <div class="admin-sidebar__logo" aria-hidden="true"><i class="bi bi-shield-lock"></i></div>
            <div>
                <strong>AutoManager</strong>
                <span>Administração</span>
            </div>
        </div>

        <div class="admin-sidebar__nav">
            <?php foreach ($admin_nav_groups as $groupLabel => $groupItems): ?>
                <div class="admin-nav-group">
                    <div class="admin-nav-group__label"><?= htmlspecialchars($groupLabel, ENT_QUOTES) ?></div>
                    <nav class="admin-nav">
                        <?php foreach ($groupItems as $navItem): ?>
                            <a href="?tab=<?= htmlspecialchars($navItem['id'], ENT_QUOTES) ?>#<?= htmlspecialchars($navItem['id'], ENT_QUOTES) ?>"
                               class="admin-nav__item<?= $admin_tab === $navItem['id'] ? ' is-active' : '' ?>"
                               data-section="<?= htmlspecialchars($navItem['id'], ENT_QUOTES) ?>"
                               onclick="return scrollToSection('<?= htmlspecialchars($navItem['id'], ENT_QUOTES) ?>')">
                                <i class="bi <?= htmlspecialchars($navItem['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($navItem['label'], ENT_QUOTES) ?></span>
                                <?php if (!empty($navItem['badge'])): ?>
                                    <span class="admin-nav__badge"><?= (int) $navItem['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-sidebar__foot">
            <a class="admin-sidebar__link" href="index.php?preview=1" target="_blank" rel="noopener"><i class="bi bi-house"></i> Ver site</a>
            <a class="admin-sidebar__link admin-sidebar__link--danger" href="?logout=1"><i class="bi bi-box-arrow-right"></i> Terminar sessão</a>
        </div>
    </aside>

    <div class="admin-overlay" id="adminOverlay" hidden></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="admin-menu-toggle" id="adminMenuToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="adminSidebar">
                <i class="bi bi-list"></i>
            </button>
            <div class="admin-topbar__title">
                <h1 id="adminPageTitle"><?= htmlspecialchars($admin_page_title, ENT_QUOTES) ?></h1>
                <p>Gestão da plataforma</p>
            </div>
        </header>

        <?php if (!empty($mensagem)): ?>
        <div class="admin-alerts"><?= $mensagem ?></div>
        <?php endif; ?>

        <div class="admin-content">
    <!-- Gestão de Marcações -->
    <div id="marcacoes" class="content-section">
        <h2 class="section-title"><i class="bi bi-calendar-check"></i> Marcações <span class="section-subtitle">(vista simples — <?= (int) ($marcacoes_result ? $marcacoes_result->num_rows : 0) ?> registos)</span></h2>
        <div class="table-filters">
            <input type="text" id="filtroMarcacoes" placeholder="Buscar por cliente, matrícula ou serviço...">
            <select id="filtroEstadoMarcacao">
                <option value="">Todos os estados</option>
                <option value="por fazer">Por fazer</option>
                <option value="em andamento">Em andamento</option>
                <option value="conclu">Concluído</option>
                <option value="pronto">Pronto</option>
                <option value="cancel">Cancelado</option>
            </select>
        </div>
        <div class="table-responsive table-responsive--cards marcacoes-table-wrap marcacoes-table-wrap--full">
            <table id="tabelaMarcacoes" class="admin-data-table marcacoes-table marcacoes-table--full">
                <thead>
                    <tr>
                        <?php foreach ($marcacoes_colunas as $col): ?>
                            <th class="col-dyn col-<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(admin_servico_col_label($col), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        <?php endforeach; ?>
                        <th class="col-acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $marcacoes_total_colunas = count($marcacoes_colunas) + 1;
                    if ($marcacoes_result && $marcacoes_result->num_rows > 0):
                        while ($mk = $marcacoes_result->fetch_assoc()):
                            $estadoRaw = strtolower(trim((string) ($mk['estado_do_trabalho'] ?? 'pendente')));
                            $temAndamento = (strpos($estadoRaw, 'andamento') !== false);
                            $temPronto = (strpos($estadoRaw, 'pronto') !== false);
                            $temConclu = (strpos($estadoRaw, 'conclu') !== false);
                            $temCancel = (strpos($estadoRaw, 'cancel') !== false)
                                || strtolower((string) ($mk['estado_marcacao'] ?? '')) === 'cancelada';
                            $estadoClasse = $temCancel ? 'cancelado' : ($temAndamento ? 'andamento' : (($temPronto || $temConclu) ? 'concluido' : 'pendente'));
                    ?>
                            <tr id="marcacao-row-<?= (int) $mk['id'] ?>">
                                <?php foreach ($marcacoes_colunas as $col): ?>
                                    <?php if ($col === 'estado_do_trabalho'): ?>
                                        <td data-label="<?= htmlspecialchars(admin_servico_col_label($col), ENT_QUOTES, 'UTF-8') ?>" class="col-dyn col-<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="status-pill <?= $estadoClasse ?>"><?= htmlspecialchars((string) ($mk[$col] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                    <?php elseif ($col === 'matricula' && !empty($mk['matricula'])): ?>
                                        <td data-label="<?= htmlspecialchars(admin_servico_col_label($col), ENT_QUOTES, 'UTF-8') ?>" class="col-dyn col-<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="marcacao-matricula"><?= htmlspecialchars((string) $mk['matricula'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                    <?php elseif (in_array($col, ['custo', 'valor_total_com_iva', 'orcamento_aprovado', 'valor_pago_total'], true)): ?>
                                        <td data-label="<?= htmlspecialchars(admin_servico_col_label($col), ENT_QUOTES, 'UTF-8') ?>" class="col-dyn col-<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?> col-preco">
                                            <span class="marcacao-preco"><?= admin_servico_format_celula($col, $mk[$col] ?? null, $mk) ?></span>
                                        </td>
                                    <?php else: ?>
                                        <td data-label="<?= htmlspecialchars(admin_servico_col_label($col), ENT_QUOTES, 'UTF-8') ?>" class="col-dyn col-<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= admin_servico_format_celula($col, $mk[$col] ?? null, $mk) ?>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <td data-label="Ações" class="td-acoes col-acoes">
                                    <button type="button" class="btn-icon btn-icon--ghost abrir-nota-marcacao"
                                        title="Notas internas"
                                        aria-label="Notas internas"
                                        data-id="<?= (int) $mk['id'] ?>"
                                        data-nota="<?= htmlspecialchars($mk['notas_internas'] ?? '', ENT_QUOTES) ?>">
                                        <i class="bi bi-journal-text" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty-row"><td colspan="<?= (int) $marcacoes_total_colunas ?>" style="text-align:center;color:#aaa;">Sem registos na tabela serviço.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Veículos -->
    <div id="veiculos" class="content-section">
        <h2 class="section-title"><i class="bi bi-car-front"></i> Veículos dos clientes</h2>
        <div class="table-filters">
            <input type="text" id="filtroVeiculos" placeholder="Pesquisar por cliente, email, marca, modelo ou matrícula...">
        </div>
        <div class="table-responsive">
            <table id="tabelaVeiculos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Ano</th>
                        <th>Matrícula</th>
                        <th>Principal</th>
                        <th>Notas</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($veiculos_result && $veiculos_result->num_rows > 0): ?>
                        <?php while ($car = $veiculos_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$car['id_carro'] ?></td>
                                <td><?= htmlspecialchars($car['nome_utilizador'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($car['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($car['marca'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($car['modelo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($car['ano'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($car['matricula'] ?? '-') ?></td>
                                <td><?= !empty($car['is_principal']) ? 'Sim' : '—' ?></td>
                                <td><?= htmlspecialchars($car['notas'] ?? '-') ?></td>
                                <td>
                                    <button type="button" class="btn btn-small btn-secondary abrir-editar-veiculo"
                                        data-id="<?= (int)$car['id_carro'] ?>"
                                        data-id-utilizador="<?= (int)$car['id_utilizador'] ?>"
                                        data-marca="<?= htmlspecialchars($car['marca'] ?? '', ENT_QUOTES) ?>"
                                        data-modelo="<?= htmlspecialchars($car['modelo'] ?? '', ENT_QUOTES) ?>"
                                        data-ano="<?= htmlspecialchars($car['ano'] ?? '', ENT_QUOTES) ?>"
                                        data-matricula="<?= htmlspecialchars($car['matricula'] ?? '', ENT_QUOTES) ?>"
                                        data-notas="<?= htmlspecialchars($car['notas'] ?? '', ENT_QUOTES) ?>"
                                        data-principal="<?= !empty($car['is_principal']) ? '1' : '0' ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align:center;color:#aaa;">Sem veículos registados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Marcas / Modelos -->
    <div id="catalogo" class="content-section">
        <div class="catalog-grid">
            <article class="catalog-panel">
                <header class="catalog-panel__head">
                    <h2><i class="bi bi-tag-fill" aria-hidden="true"></i> Marcas (<?= (int) $total_marcas_catalogo ?>)</h2>
                </header>
                <form method="post" class="catalog-add-form">
                    <label class="catalog-add-form__label" for="nomeMarcaNova">Nova marca</label>
                    <div class="catalog-add-form__row">
                        <input type="text" id="nomeMarcaNova" name="nome_marca_nova" placeholder="Ex.: Toyota" maxlength="100" required>
                        <button type="submit" name="adicionar_marca_catalogo" class="btn btn-primary catalog-add-btn"><i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar</button>
                    </div>
                </form>
                <div class="catalog-table-wrap">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Nome</th>
                                <th class="col-acoes">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($marcas_catalogo !== []): ?>
                                <?php foreach ($marcas_catalogo as $marcaCat): ?>
                                    <tr>
                                        <td class="col-id"><?= (int) $marcaCat['id'] ?></td>
                                        <td><?= htmlspecialchars($marcaCat['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="col-acoes">
                                            <button type="button" class="btn-icon btn-icon--ghost abrir-editar-marca"
                                                title="Editar marca"
                                                data-id="<?= (int) $marcaCat['id'] ?>"
                                                data-nome="<?= htmlspecialchars($marcaCat['nome'], ENT_QUOTES) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </button>
                                            <a href="indexadmin.php?tab=catalogo&amp;del_marca_catalogo=<?= (int) $marcaCat['id'] ?>#catalogo"
                                               class="btn-icon btn-icon--danger"
                                               title="Eliminar marca"
                                               data-confirm="1"
                                               data-confirm-title="Eliminar marca?"
                                               data-confirm-message="Eliminar «<?= htmlspecialchars($marcaCat['nome'], ENT_QUOTES) ?>»? Só é possível se não tiver modelos associados."
                                               data-confirm-cta="Eliminar"
                                               data-confirm-btn-class="btn-danger">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="catalog-empty">Sem marcas registadas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="catalog-panel">
                <header class="catalog-panel__head">
                    <h2><i class="bi bi-list-ul" aria-hidden="true"></i> Modelos (<?= (int) $total_modelos_catalogo ?>)</h2>
                </header>
                <form method="post" class="catalog-add-form catalog-add-form--modelo">
                    <div class="catalog-add-form__grid">
                        <div>
                            <label class="catalog-add-form__label" for="idMarcaModelo">Marca</label>
                            <select id="idMarcaModelo" name="id_marca_modelo" required>
                                <option value="">Selecionar...</option>
                                <?php foreach ($marcas_catalogo as $marcaCat): ?>
                                    <option value="<?= (int) $marcaCat['id'] ?>"><?= htmlspecialchars($marcaCat['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="catalog-add-form__label" for="nomeModeloNovo">Modelo</label>
                            <input type="text" id="nomeModeloNovo" name="nome_modelo_novo" placeholder="Ex.: Corolla" maxlength="100" required>
                        </div>
                        <div class="catalog-add-form__submit">
                            <button type="submit" name="adicionar_modelo_catalogo" class="btn btn-primary catalog-add-btn"><i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar</button>
                        </div>
                    </div>
                </form>
                <div class="catalog-table-wrap">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th class="col-acoes">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($modelos_catalogo !== []): ?>
                                <?php foreach ($modelos_catalogo as $modeloCat): ?>
                                    <tr>
                                        <td class="col-id"><?= (int) $modeloCat['id'] ?></td>
                                        <td><?= htmlspecialchars($modeloCat['marca'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($modeloCat['modelo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="col-acoes">
                                            <button type="button" class="btn-icon btn-icon--ghost abrir-editar-modelo"
                                                title="Editar modelo"
                                                data-id="<?= (int) $modeloCat['id'] ?>"
                                                data-id-marca="<?= (int) $modeloCat['id_marca'] ?>"
                                                data-marca="<?= htmlspecialchars($modeloCat['marca'], ENT_QUOTES) ?>"
                                                data-modelo="<?= htmlspecialchars($modeloCat['modelo'], ENT_QUOTES) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </button>
                                            <a href="indexadmin.php?tab=catalogo&amp;del_modelo_catalogo=<?= (int) $modeloCat['id'] ?>#catalogo"
                                               class="btn-icon btn-icon--danger"
                                               title="Eliminar modelo"
                                               data-confirm="1"
                                               data-confirm-title="Eliminar modelo?"
                                               data-confirm-message="Eliminar «<?= htmlspecialchars($modeloCat['modelo'], ENT_QUOTES) ?>» da marca <?= htmlspecialchars($modeloCat['marca'], ENT_QUOTES) ?>?"
                                               data-confirm-cta="Eliminar"
                                               data-confirm-btn-class="btn-danger">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="catalog-empty">Sem modelos registados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </div>
    
    <!-- Dashboard com Estatísticas -->
    <div id="dashboard" class="content-section content-section--dashboard">
        <header class="dash-header">
            <div class="dash-header__text">
                <h2 class="dash-header__title">Visão geral</h2>
                <p class="dash-header__sub">Resumo da plataforma e estado dos serviços na oficina</p>
            </div>
            <div class="dash-header__meta">
                <span class="dash-header__date"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('d/m/Y') ?></span>
            </div>
        </header>

        <div class="stats-grid stats-grid--kpis">
            <div class="stat-card stat-card--kpi" role="button" tabindex="0" onclick="scrollToSection('utilizadores')" onkeydown="if(event.key==='Enter')scrollToSection('utilizadores')">
                <div class="stat-card__icon stat-card__icon--users"><i class="bi bi-people" aria-hidden="true"></i></div>
                <div class="stat-card__body">
                    <div class="stat-number"><?= $total_utilizadores ?></div>
                    <div class="stat-label">Utilizadores</div>
                </div>
            </div>
            <div class="stat-card stat-card--kpi" role="button" tabindex="0" onclick="scrollToSection('mecanicos')" onkeydown="if(event.key==='Enter')scrollToSection('mecanicos')">
                <div class="stat-card__icon stat-card__icon--mech"><i class="bi bi-wrench-adjustable" aria-hidden="true"></i></div>
                <div class="stat-card__body">
                    <div class="stat-number"><?= $total_mecanicos ?></div>
                    <div class="stat-label">Mecânicos</div>
                </div>
            </div>
            <div class="stat-card stat-card--kpi" role="button" tabindex="0" onclick="scrollToSection('chefes_oficina')" onkeydown="if(event.key==='Enter')scrollToSection('chefes_oficina')">
                <div class="stat-card__icon stat-card__icon--chef"><i class="bi bi-person-badge" aria-hidden="true"></i></div>
                <div class="stat-card__body">
                    <div class="stat-number"><?= $total_chefes ?></div>
                    <div class="stat-label">Chefes de oficina</div>
                </div>
            </div>
        </div>

        <section class="dash-block">
            <h3 class="dashboard-subtitle"><i class="bi bi-gear-wide-connected" aria-hidden="true"></i> Estado dos serviços</h3>
            <div class="servicos-stats servicos-stats--enhanced">
                <div class="servico-stat por-fazer" role="button" tabindex="0" onclick="scrollToSection('marcacoes')" onkeydown="if(event.key==='Enter')scrollToSection('marcacoes')">
                    <div class="servico-stat__ring" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
                    <h4><?= (int) $servicos_por_fazer ?></h4>
                    <p>Por fazer</p>
                </div>
                <div class="servico-stat em-andamento" role="button" tabindex="0" onclick="scrollToSection('marcacoes')" onkeydown="if(event.key==='Enter')scrollToSection('marcacoes')">
                    <div class="servico-stat__ring" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></div>
                    <h4><?= (int) $servicos_em_andamento ?></h4>
                    <p>Em andamento</p>
                </div>
            </div>
        </section>

        <div class="dashboard-charts dashboard-charts--wide">
            <div class="chart-card chart-card--donut">
                <div class="chart-card__head">
                    <h3><i class="bi bi-pie-chart" aria-hidden="true"></i> Distribuição por estado</h3>
                    <span class="chart-card__hint">Todos os serviços registados</span>
                </div>
                <div class="chart-wrap chart-wrap--donut">
                    <canvas id="estadoChart"></canvas>
                </div>
            </div>
            <div class="chart-card chart-card--line">
                <div class="chart-card__head">
                    <h3><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Marcações nos últimos 7 dias</h3>
                    <span class="chart-card__hint">Novas marcações por dia</span>
                </div>
                <div class="chart-wrap chart-wrap--line">
                    <canvas id="ultimos7diasChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Criar funcionário -->
    <div id="criar-funcionario" class="content-section staff-create-shell">
        <div class="staff-create-panel">
            <header class="staff-hero">
                <div class="staff-hero__badge" aria-hidden="true"><i class="bi bi-person-plus"></i></div>
                <div class="staff-hero__text">
                    <h2 class="staff-hero__title">Criar novo funcionário</h2>
                    <p class="staff-hero__sub">Registe um mecânico ou chefe de oficina. O acesso à plataforma é criado automaticamente.</p>
                </div>
                <div class="staff-create-stats" aria-label="Equipa atual">
                    <div class="staff-stat staff-stat--mec">
                        <span class="staff-stat__icon" aria-hidden="true"><i class="bi bi-tools"></i></span>
                        <span class="staff-stat__body">
                            <strong><?= (int) $total_mecanicos ?></strong>
                            <span>mecânicos</span>
                        </span>
                    </div>
                    <div class="staff-stat staff-stat--chefe">
                        <span class="staff-stat__icon" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
                        <span class="staff-stat__body">
                            <strong><?= (int) $total_chefes ?></strong>
                            <span>chefes</span>
                        </span>
                    </div>
                </div>
            </header>

            <div class="staff-steps" role="list" aria-label="Progresso do registo">
                <div class="staff-steps__track" aria-hidden="true"><span class="staff-steps__fill" id="staffStepsFill"></span></div>
                <div class="staff-steps__item is-active" id="staffStepPick" role="listitem">
                    <span class="staff-steps__num">1</span>
                    <span class="staff-steps__label">Escolher perfil</span>
                </div>
                <div class="staff-steps__item" id="staffStepForm" role="listitem">
                    <span class="staff-steps__num">2</span>
                    <span class="staff-steps__label">Dados de contratação</span>
                </div>
            </div>

            <nav class="staff-type-bar hidden" id="staffTypeBar" aria-label="Tipo de funcionário">
                <button type="button" class="staff-type-bar__btn" data-tipo="mecanico" onclick="mostrarForm('mecanico')">
                    <i class="bi bi-tools" aria-hidden="true"></i> Mecânico
                </button>
                <button type="button" class="staff-type-bar__btn" data-tipo="chefe_oficina" onclick="mostrarForm('chefe_oficina')">
                    <i class="bi bi-person-badge" aria-hidden="true"></i> Chefe de oficina
                </button>
            </nav>

            <div class="staff-picker" id="formToggle">
                <p class="staff-picker__lead">Que perfil pretende adicionar à equipa?</p>
                <div class="staff-picker__grid criar-botoes">
                    <button type="button" class="staff-picker__card staff-picker__card--mec criar-btn" data-tipo="mecanico" onclick="mostrarForm('mecanico')">
                        <span class="staff-picker__top">
                            <span class="staff-picker__icon staff-picker__icon--mec" aria-hidden="true"><i class="bi bi-tools"></i></span>
                            <span class="staff-picker__role">Operações</span>
                        </span>
                        <span class="staff-picker__body">
                            <span class="staff-picker__title">Mecânico</span>
                            <span class="staff-picker__desc btn-subtitle">Executa reparações, regista horas, peças e conclusão do trabalho na oficina.</span>
                            <span class="staff-picker__chips">
                                <span>Área de mecânico</span>
                                <span>Tracking</span>
                                <span>Faturação</span>
                            </span>
                        </span>
                        <span class="staff-picker__footer">
                            <span>Continuar</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </span>
                    </button>
                    <button type="button" class="staff-picker__card staff-picker__card--chefe criar-btn" data-tipo="chefe_oficina" onclick="mostrarForm('chefe_oficina')">
                        <span class="staff-picker__top">
                            <span class="staff-picker__icon staff-picker__icon--chefe" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
                            <span class="staff-picker__role staff-picker__role--chefe">Gestão</span>
                        </span>
                        <span class="staff-picker__body">
                            <span class="staff-picker__title">Chefe de oficina</span>
                            <span class="staff-picker__desc btn-subtitle">Atribui serviços, acompanha a equipa e envia orçamentos aos clientes.</span>
                            <span class="staff-picker__chips">
                                <span>Painel de gestão</span>
                                <span>Orçamentos</span>
                                <span>Atribuições</span>
                            </span>
                        </span>
                        <span class="staff-picker__footer">
                            <span>Continuar</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </span>
                    </button>
                </div>
                <p class="staff-picker__hint"><i class="bi bi-info-circle" aria-hidden="true"></i> Credenciais enviadas por email <code>@automanager.pt</code> após o registo.</p>
            </div>

        <!-- Formulário mecânico -->
        <div class="form-container staff-form-panel staff-form-panel--mec hidden" id="formMecanico">
            <header class="staff-form-header">
                <button type="button" class="staff-form-back" onclick="voltarSelecao()">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
                </button>
                <div class="staff-form-header__main">
                    <span class="form-title__icon form-title__icon--mec" aria-hidden="true"><i class="bi bi-tools"></i></span>
                    <div>
                        <h3>Novo mecânico</h3>
                        <p>Dados de contratação e acesso à plataforma</p>
                    </div>
                </div>
            </header>
            <form method="POST" id="formCriarMecanico" class="staff-form" lang="pt-PT">
                <div class="staff-cred-banner form-credenciais-info">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    <div><?= am_texto_credenciais_funcionario_html() ?></div>
                </div>
                <p class="staff-form-note"><span class="staff-req">*</span> Campos obrigatórios</p>
                <div class="staff-form-body form-grid">
                    <fieldset class="staff-fieldset">
                        <legend class="staff-fieldset__head">
                            <span class="staff-fieldset__num">1</span>
                            <span class="staff-fieldset__label"><i class="bi bi-person" aria-hidden="true"></i> Identificação</span>
                        </legend>
                        <div class="staff-fieldset__grid">
                    <div class="form-group staff-form-group--full">
                        <label for="nome_mecanico">Nome completo <span class="staff-req" aria-hidden="true">*</span></label>
                        <input type="text" id="nome_mecanico" name="nome_mecanico" placeholder="Ex.: João Silva" required>
                    </div>
                    <div class="form-group">
                        <label for="email_mecanico">Email corporativo <span class="staff-req" aria-hidden="true">*</span></label>
                        <input type="email" id="email_mecanico" name="email_mecanico" placeholder="nome@automanager.pt" pattern="^[^@\s]+@automanager\.pt$" title="Use um email @automanager.pt" required>
                        <small class="form-hint staff-form-hint">Domínio obrigatório <code>@automanager.pt</code></small>
                    </div>
                    <div class="form-group">
                        <label for="telefone_mecanico">Telemóvel <span class="staff-req" aria-hidden="true">*</span></label>
                        <input type="tel" class="am-telemovel-input" id="telefone_mecanico" name="telefone_mecanico" placeholder="912 345 678" maxlength="11" autocomplete="tel" required>
                    </div>
                    <div class="form-group staff-form-group--full">
                        <label for="morada_mecanico">Morada</label>
                        <input type="text" id="morada_mecanico" name="morada_mecanico" placeholder="Rua, n.º, cidade">
                        <small class="form-hint staff-form-hint">Opcional</small>
                    </div>
                        </div>
                    </fieldset>
                    <fieldset class="staff-fieldset">
                        <legend class="staff-fieldset__head">
                            <span class="staff-fieldset__num">2</span>
                            <span class="staff-fieldset__label"><i class="bi bi-briefcase" aria-hidden="true"></i> Contrato</span>
                        </legend>
                        <div class="staff-fieldset__grid">
                    <div class="form-group">
                        <label for="salario_mecanico">Salário bruto</label>
                        <div class="staff-input-affix">
                            <span class="staff-input-affix__pre" aria-hidden="true">€</span>
                            <input type="number" id="salario_mecanico" name="salario_mecanico" step="0.01" min="0" placeholder="1500.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="data_contratacao_mecanico">Data de contratação</label>
                        <input type="date" id="data_contratacao_mecanico" name="data_contratacao_mecanico" value="<?= date('Y-m-d') ?>">
                        <small class="form-hint staff-form-hint">Formato dd/mm/aaaa</small>
                    </div>
                    <div class="form-group">
                        <label for="nif_mecanico">NIF</label>
                        <input type="text" class="am-nif-input" id="nif_mecanico" name="nif_mecanico" inputmode="numeric" maxlength="9" placeholder="123456789" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="iban_mecanico">IBAN</label>
                        <input type="text" class="am-iban-input" id="iban_mecanico" name="iban_mecanico" placeholder="PT50 0000 0000 0000 0000 0000 0" autocomplete="off" spellcheck="false">
                    </div>
                    <div class="form-group full-width">
                        <label for="especialidade_mecanico">Especialidade <span class="staff-req" aria-hidden="true">*</span></label>
                        <select id="especialidade_mecanico" name="especialidade_mecanico" required>
                            <option value="">Selecione a especialidade</option>
                            <?php foreach ($am_especialidades_mecanico as $esp): ?>
                            <option value="<?= htmlspecialchars($esp) ?>"><?= htmlspecialchars($esp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                        </div>
                    </fieldset>
                    <fieldset class="staff-fieldset staff-fieldset--access">
                        <legend class="staff-fieldset__head">
                            <span class="staff-fieldset__num">3</span>
                            <span class="staff-fieldset__label"><i class="bi bi-key" aria-hidden="true"></i> Acesso à plataforma</span>
                        </legend>
                        <label class="staff-toggle">
                            <input type="checkbox" name="enviar_email_mecanico" checked>
                            <span class="staff-toggle__box" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                            <span class="staff-toggle__text">
                                <strong>Enviar email com credenciais de acesso</strong>
                                <small>Conta <code>@automanager.pt</code> com palavra-passe inicial da oficina.</small>
                            </span>
                        </label>
                    </fieldset>
                </div>
                <footer class="staff-form-footer form-actions form-actions--staff">
                    <button type="button" class="btn btn-secondary" onclick="voltarSelecao()">Cancelar</button>
                    <button type="submit" name="criar_mecanico" class="btn btn-primary btn-large">
                        <i class="bi bi-check-lg"></i> Criar mecânico
                    </button>
                </footer>
            </form>
        </div>

        <!-- Formulário chefe de oficina -->
        <div class="form-container staff-form-panel staff-form-panel--chefe hidden" id="formChefeOficina">
            <header class="staff-form-header">
                <button type="button" class="staff-form-back" onclick="voltarSelecao()">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
                </button>
                <div class="staff-form-header__main">
                    <span class="form-title__icon form-title__icon--chefe" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
                    <div>
                        <h3>Novo chefe de oficina</h3>
                        <p>Dados de contratação e acesso à plataforma</p>
                    </div>
                </div>
            </header>
            <form method="POST" id="formCriarChefeOficina" class="staff-form" lang="pt-PT">
                <div class="staff-cred-banner form-credenciais-info">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    <div><?= am_texto_credenciais_funcionario_html() ?></div>
                </div>
                <p class="staff-form-note"><span class="staff-req">*</span> Campos obrigatórios</p>
                <div class="staff-form-body form-grid">
                    <fieldset class="staff-fieldset">
                        <legend class="staff-fieldset__head">
                            <span class="staff-fieldset__num">1</span>
                            <span class="staff-fieldset__label"><i class="bi bi-person" aria-hidden="true"></i> Identificação</span>
                        </legend>
                        <div class="staff-fieldset__grid">
                    <div class="form-group staff-form-group--full">
                        <label for="nome_chefe_oficina">Nome completo <span class="staff-req" aria-hidden="true">*</span></label>
                        <input type="text" id="nome_chefe_oficina" name="nome_chefe_oficina" placeholder="Ex.: Maria Costa" required>
                    </div>
                    <div class="form-group">
                        <label for="email_chefe_oficina">Email corporativo <span class="staff-req" aria-hidden="true">*</span></label>
                        <input type="email" id="email_chefe_oficina" name="email_chefe_oficina" placeholder="nome@automanager.pt" pattern="^[^@\s]+@automanager\.pt$" title="Use um email @automanager.pt" required>
                        <small class="form-hint staff-form-hint">Domínio obrigatório <code>@automanager.pt</code></small>
                    </div>
                    <div class="form-group">
                        <label for="telefone_chefe_oficina">Telemóvel</label>
                        <input type="tel" class="am-telemovel-input" id="telefone_chefe_oficina" name="telefone_chefe_oficina" placeholder="912 345 678" maxlength="11" autocomplete="tel">
                    </div>
                    <div class="form-group staff-form-group--full">
                        <label for="morada_chefe_oficina">Morada</label>
                        <input type="text" id="morada_chefe_oficina" name="morada_chefe_oficina" placeholder="Rua, n.º, cidade">
                        <small class="form-hint staff-form-hint">Opcional</small>
                    </div>
                        </div>
                    </fieldset>
                    <fieldset class="staff-fieldset">
                        <legend class="staff-fieldset__head">
                            <span class="staff-fieldset__num">2</span>
                            <span class="staff-fieldset__label"><i class="bi bi-briefcase" aria-hidden="true"></i> Contrato</span>
                        </legend>
                        <div class="staff-fieldset__grid">
                    <div class="form-group">
                        <label for="salario_chefe_oficina">Salário bruto</label>
                        <div class="staff-input-affix">
                            <span class="staff-input-affix__pre" aria-hidden="true">€</span>
                            <input type="number" id="salario_chefe_oficina" name="salario_chefe_oficina" step="0.01" min="0" placeholder="2500.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="data_contratacao_chefe">Data de contratação</label>
                        <input type="date" id="data_contratacao_chefe" name="data_contratacao_chefe" value="<?= date('Y-m-d') ?>">
                        <small class="form-hint staff-form-hint">Formato dd/mm/aaaa</small>
                    </div>
                    <div class="form-group">
                        <label for="nif_chefe_oficina">NIF</label>
                        <input type="text" class="am-nif-input" id="nif_chefe_oficina" name="nif_chefe_oficina" inputmode="numeric" maxlength="9" placeholder="123456789" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="iban_chefe_oficina">IBAN</label>
                        <input type="text" class="am-iban-input" id="iban_chefe_oficina" name="iban_chefe_oficina" placeholder="PT50 0000 0000 0000 0000 0000 0" autocomplete="off" spellcheck="false">
                    </div>
                    <div class="form-group full-width">
                        <label for="especialidade_chefe_oficina">Especialidade <span class="staff-req" aria-hidden="true">*</span></label>
                        <select id="especialidade_chefe_oficina" name="especialidade_chefe_oficina" required>
                            <option value="">Selecione a especialidade</option>
                            <?php foreach ($am_especialidades_chefe as $esp): ?>
                            <option value="<?= htmlspecialchars($esp) ?>"><?= htmlspecialchars($esp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                        </div>
                    </fieldset>
                    <fieldset class="staff-fieldset staff-fieldset--access staff-fieldset--access-chefe">
                        <legend class="staff-fieldset__head">
                            <span class="staff-fieldset__num">3</span>
                            <span class="staff-fieldset__label"><i class="bi bi-key" aria-hidden="true"></i> Acesso à plataforma</span>
                        </legend>
                        <label class="staff-toggle">
                            <input type="checkbox" name="enviar_email_chefe" checked>
                            <span class="staff-toggle__box" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                            <span class="staff-toggle__text">
                                <strong>Enviar email com credenciais de acesso</strong>
                                <small>Conta <code>@automanager.pt</code> com palavra-passe inicial da oficina.</small>
                            </span>
                        </label>
                    </fieldset>
                </div>
                <footer class="staff-form-footer form-actions form-actions--staff">
                    <button type="button" class="btn btn-secondary" onclick="voltarSelecao()">Cancelar</button>
                    <button type="submit" name="criar_chefe_oficina" class="btn btn-chefe btn-large">
                        <i class="bi bi-check-lg"></i> Criar chefe de oficina
                    </button>
                </footer>
            </form>
        </div>
        </div><!-- .staff-create-panel -->
    </div>
    
    

    <!-- Gestão de Mecânicos -->
   <div class="content-section" id="mecanicos">
    <h2 class="section-title"><i class="bi bi-gear"></i> Gestão de Mecânicos</h2>
    
    <?php
    // Buscar mecânicos novamente para a tabela
    $mecanicos_table = $con->query("SELECT * FROM mecanicos ORDER BY nome_mecanico");
    ?>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Especialidade</th>
                    <th>Contacto</th>
                    <th>Salário Bruto</th>
                    <th>1º Login</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($mecanicos_table && $mecanicos_table->num_rows > 0): ?>
                    <?php while ($mecanico = $mecanicos_table->fetch_assoc()): 
                        $primeiro_login = isset($mecanico['primeiro_login']) ? $mecanico['primeiro_login'] : 0;
                    ?>
                        <tr>
                            <td><?= $mecanico['id_mecanico'] ?></td>
                            <td><?= htmlspecialchars($mecanico['nome_mecanico']) ?></td>
                            <td><?= htmlspecialchars($mecanico['especialidade']) ?></td>
                            <td><?= htmlspecialchars($mecanico['contacto'] ?? '-') ?></td>
                            <td>
                                <?= isset($mecanico['ordenado_bruto']) && $mecanico['ordenado_bruto'] ? 
                                    '€ ' . number_format($mecanico['ordenado_bruto'], 2, ',', ' ') : 
                                    '-' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $primeiro_login ? 'status-blocked' : 'status-active' ?>">
                                    <?= $primeiro_login ? 'Pendente' : 'Concluído' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a class="btn btn-small btn-danger" 
                                    href="?delete_mecanico=<?= $mecanico['id_mecanico'] ?>"
                                    data-confirm="1"
                                    data-confirm-title="Eliminar mecânico"
                                    data-confirm-message="Tem a certeza que deseja eliminar o mecânico <?= htmlspecialchars($mecanico['nome_mecanico'], ENT_QUOTES) ?>? Esta ação não pode ser desfeita."
                                    data-confirm-cta="Eliminar"
                                    data-confirm-btn-class="btn-danger">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #aaa;">
                            Nenhum mecânico encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="modalEditarUtilizador" class="admin-modal">
        <div class="admin-modal-card">
            <div class="admin-modal-head">
                <h3><i class="bi bi-person"></i> Editar Utilizador</h3>
                <button type="button" class="btn btn-small btn-danger fechar-modal" data-target="modalEditarUtilizador">x</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_utilizador" id="editUserId">
                <div class="admin-modal-grid">
                    <div class="full">
                        <label>Nome</label>
                        <input type="text" name="nome_utilizador" id="editUserNome" required>
                    </div>
                    <div class="full">
                        <label>Email</label>
                        <input type="email" name="email_utilizador" id="editUserEmail" required>
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input type="text" class="am-telemovel-input" name="telefone_utilizador" id="editUserTelefone" maxlength="11" autocomplete="tel" placeholder="912 345 678">
                    </div>
                    <div>
                        <label for="editUserNif">NIF</label>
                        <input type="text" class="am-nif-input" name="nif_utilizador" id="editUserNif" inputmode="numeric" maxlength="9" placeholder="123456789" autocomplete="off">
                    </div>
                </div>
                <div class="admin-modal-actions">
                    <button type="button" class="btn btn-secondary fechar-modal" data-target="modalEditarUtilizador">Cancelar</button>
                    <button type="submit" name="salvar_utilizador_admin" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalConfirmAcao" class="admin-modal admin-modal--confirm" role="dialog" aria-modal="true" aria-labelledby="confirmAcaoTitulo">
        <div class="admin-modal-card">
            <div class="admin-modal-head">
                <h3 id="confirmAcaoTitulo">Confirmar</h3>
                <button type="button" class="btn btn-small btn-danger fechar-modal" data-target="modalConfirmAcao" aria-label="Fechar">×</button>
            </div>
            <div class="am-dialog__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
            <h4 id="confirmAcaoTituloVisivel" class="am-dialog__title">Confirmar</h4>
            <p id="confirmAcaoMensagem" class="am-dialog__msg">Tem a certeza que pretende realizar esta ação?</p>
            <div class="admin-modal-actions">
                <button type="button" class="btn btn-secondary fechar-modal" data-target="modalConfirmAcao">Cancelar</button>
                <a id="confirmAcaoConfirmar" class="btn btn-warning" href="#">Confirmar</a>
            </div>
        </div>
    </div>

    <div id="modalEditarVeiculo" class="admin-modal">
        <div class="admin-modal-card">
            <div class="admin-modal-head">
                <h3><i class="bi bi-car-front"></i> Editar Veículo</h3>
                <button type="button" class="btn btn-small btn-danger fechar-modal" data-target="modalEditarVeiculo">x</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_carro" id="editCarroId">
                <div class="admin-modal-grid">
                    <div class="full">
                        <label>Cliente (dono)</label>
                        <select name="id_utilizador_carro" id="editCarroUtilizador" required>
                            <?php if ($utilizadores_para_select && $utilizadores_para_select->num_rows > 0): ?>
                                <?php while ($optU = $utilizadores_para_select->fetch_assoc()): ?>
                                    <option value="<?= (int)$optU['id_utilizador'] ?>">
                                        <?= htmlspecialchars($optU['nome_utilizador']) ?> - <?= htmlspecialchars($optU['email']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label>Marca</label>
                        <input type="text" name="marca_carro" id="editCarroMarca" required>
                    </div>
                    <div>
                        <label>Modelo</label>
                        <input type="text" name="modelo_carro" id="editCarroModelo" required>
                    </div>
                    <div>
                        <label>Ano</label>
                        <input type="number" name="ano_carro" id="editCarroAno" min="1900" max="2099">
                    </div>
                    <div>
                        <label>Matrícula</label>
                        <input type="text" name="matricula_carro" id="editCarroMatricula" maxlength="8" title="Matrícula portuguesa: AA-00-AA, 00-AA-00 ou 00-00-AA" required style="text-transform:uppercase">
                    </div>
                    <div class="full">
                        <label>Notas da oficina</label>
                        <textarea name="notas_carro" id="editCarroNotas" rows="3"></textarea>
                    </div>
                    <div class="full">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="principal_carro" id="editCarroPrincipal" style="width:auto;">
                            Veículo principal deste cliente
                        </label>
                    </div>
                </div>
                <div class="admin-modal-actions">
                    <button type="button" class="btn btn-secondary fechar-modal" data-target="modalEditarVeiculo">Cancelar</button>
                    <button type="submit" name="salvar_veiculo_admin" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalNotaMarcacao" class="admin-modal">
        <div class="admin-modal-card">
            <div class="admin-modal-head">
                <h3><i class="bi bi-journal-text"></i> Notas internas da marcação</h3>
                <button type="button" class="btn btn-small btn-danger fechar-modal" data-target="modalNotaMarcacao">x</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_servico_nota" id="notaMarcacaoId">
                <label>Nota interna</label>
                <textarea name="nota_interna" id="notaMarcacaoTexto" rows="6"></textarea>
                <div class="admin-modal-actions">
                    <button type="button" class="btn btn-secondary fechar-modal" data-target="modalNotaMarcacao">Cancelar</button>
                    <button type="submit" name="guardar_notas_marcacao_admin" class="btn btn-primary">Guardar nota</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditarMarca" class="admin-modal">
        <div class="admin-modal-card">
            <div class="admin-modal-head">
                <h3><i class="bi bi-tag"></i> Editar marca</h3>
                <button type="button" class="btn btn-small btn-danger fechar-modal" data-target="modalEditarMarca">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_marca" id="editMarcaId">
                <label for="editMarcaNome">Nome da marca</label>
                <input type="text" name="nome_marca" id="editMarcaNome" maxlength="100" required>
                <div class="admin-modal-actions">
                    <button type="button" class="btn btn-secondary fechar-modal" data-target="modalEditarMarca">Cancelar</button>
                    <button type="submit" name="editar_marca_catalogo" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditarModelo" class="admin-modal">
        <div class="admin-modal-card">
            <div class="admin-modal-head">
                <h3><i class="bi bi-list-ul"></i> Editar modelo</h3>
                <button type="button" class="btn btn-small btn-danger fechar-modal" data-target="modalEditarModelo">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_modelo" id="editModeloId">
                <div class="admin-modal-grid">
                    <div class="full">
                        <label for="editModeloMarca">Marca</label>
                        <select name="id_marca_modelo_edit" id="editModeloMarca" required>
                            <?php foreach ($marcas_catalogo as $marcaCat): ?>
                                <option value="<?= (int) $marcaCat['id'] ?>"><?= htmlspecialchars($marcaCat['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="full">
                        <label for="editModeloNome">Modelo</label>
                        <input type="text" name="nome_modelo" id="editModeloNome" maxlength="100" required>
                    </div>
                </div>
                <div class="admin-modal-actions">
                    <button type="button" class="btn btn-secondary fechar-modal" data-target="modalEditarModelo">Cancelar</button>
                    <button type="submit" name="editar_modelo_catalogo" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Gestão de Chefes de Oficina -->
<div class="content-section" id="chefes_oficina">
    <h2 class="section-title"><i class="bi bi-person-badge"></i> Gestão de Chefes de Oficina</h2>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>Salário</th>
                    <th>Contratado</th>
                    <th>1º Login</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($chefes_result && $chefes_result->num_rows > 0): ?>
                    <?php while ($chefe = $chefes_result->fetch_assoc()): 
                        $primeiro_login = isset($chefe['primeiro_login']) ? $chefe['primeiro_login'] : 0;
                    ?>
                        <tr>
                            <td><?= $chefe['id_chefe'] ?? '' ?></td>
                            <td><?= htmlspecialchars($chefe['nome'] ?? '') ?></td>
                            <td><?= htmlspecialchars($chefe['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($chefe['telefone'] ?? '-') ?></td>
                            <td>
                                <?= isset($chefe['salario_bruto']) ? '€ ' . number_format($chefe['salario_bruto'], 2, ',', ' ') : '-' ?>
                            </td>
                            <td>
                                <?= isset($chefe['data_contratacao']) ? date('d/m/Y', strtotime($chefe['data_contratacao'])) : '-' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $primeiro_login ? 'status-blocked' : 'status-active' ?>">
                                    <?= $primeiro_login ? 'Pendente' : 'Concluído' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons" style="display: flex; gap: 5px; flex-wrap: nowrap;">
                                    <a href="?confirmar_eliminar_chefe=<?= (int) ($chefe['id_chefe'] ?? 0) ?>" 
                                    class="btn btn-small btn-danger"
                                    data-confirm="1"
                                    data-confirm-title="Eliminar chefe de oficina"
                                    data-confirm-message="Tem a certeza que deseja eliminar permanentemente este chefe de oficina? Esta ação não pode ser desfeita."
                                    data-confirm-cta="Eliminar"
                                    data-confirm-btn-class="btn-danger"
                                    style="padding: 3px 8px; font-size: 12px; background-color: #dc3545; border-color: #dc3545;">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #aaa;">
                            Nenhum chefe de oficina encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
    <!-- Gestão de Utilizadores -->
    <div class="content-section" id="utilizadores">
        <h2 class="section-title"><i class="bi bi-people"></i> Gestão de Utilizadores</h2>
        <div class="table-filters table-filters--single">
            <input type="text" id="filtroUtilizadores" placeholder="Pesquisar por nome, email, telefone, NIF ou estado...">
        </div>
        <div class="table-responsive table-responsive--cards utilizadores-table-wrap">
            <table id="tabelaUtilizadores" class="admin-data-table utilizadores-table">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-utilizador">Utilizador</th>
                        <th class="col-contacto">Contacto</th>
                        <th class="col-estado">Estado</th>
                        <th class="col-bloqueio">Data bloqueio</th>
                        <th class="col-acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($utilizadores_result && $utilizadores_result->num_rows > 0): ?>
                        <?php while ($row = $utilizadores_result->fetch_assoc()): 
                            $esta_bloqueado = $row['bloqueado'] == 1;
                            $telefone = trim((string)($row['telefone'] ?? ''));
                            $nif = trim((string)($row['nif'] ?? ''));
                        ?>
                            <tr>
                                <td data-label="ID" class="col-id"><span class="utilizador-id">#<?= (int)$row['id_utilizador'] ?></span></td>
                                <td data-label="Utilizador" class="col-utilizador">
                                    <div class="utilizador-identidade">
                                        <span class="utilizador-nome"><?= htmlspecialchars($row['nome_utilizador']) ?></span>
                                        <span class="utilizador-email"><?= htmlspecialchars($row['email']) ?></span>
                                    </div>
                                </td>
                                <td data-label="Contacto" class="col-contacto">
                                    <div class="utilizador-contacto">
                                        <span class="utilizador-tel"><?= $telefone !== '' ? htmlspecialchars($telefone) : '—' ?></span>
                                        <span class="utilizador-nif"><?= $nif !== '' ? 'NIF: ' . htmlspecialchars($nif) : 'NIF: —' ?></span>
                                    </div>
                                </td>
                                <td data-label="Estado" class="col-estado">
                                    <span class="status-badge <?= $esta_bloqueado ? 'status-blocked' : 'status-active' ?>">
                                        <?= $esta_bloqueado ? 'Bloqueado' : 'Ativo' ?>
                                    </span>
                                </td>
                                <td data-label="Data bloqueio" class="col-bloqueio">
                                    <span class="utilizador-bloqueio-data">
                                        <?= $esta_bloqueado && $row['data_bloqueio'] ? date('d/m/Y H:i', strtotime($row['data_bloqueio'])) : '—' ?>
                                    </span>
                                </td>
                                <td data-label="Ações" class="td-acoes col-acoes">
                                    <div class="utilizador-acoes">
                                        <button type="button" class="btn-icon btn-icon--ghost abrir-editar-utilizador"
                                            title="Editar utilizador"
                                            aria-label="Editar utilizador"
                                            data-id="<?= (int)$row['id_utilizador'] ?>"
                                            data-nome="<?= htmlspecialchars($row['nome_utilizador'], ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>"
                                            data-telefone="<?= htmlspecialchars($row['telefone'] ?? '', ENT_QUOTES) ?>"
                                            data-nif="<?= htmlspecialchars($row['nif'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                        <a
                                            class="btn-icon <?= $esta_bloqueado ? 'btn-icon--success' : 'btn-icon--warning' ?>"
                                            href="?toggle_block=<?= $row['id_utilizador'] ?>"
                                            title="<?= $esta_bloqueado ? 'Desbloquear utilizador' : 'Bloquear utilizador' ?>"
                                            aria-label="<?= $esta_bloqueado ? 'Desbloquear utilizador' : 'Bloquear utilizador' ?>"
                                            data-confirm="1"
                                            data-confirm-title="<?= $esta_bloqueado ? 'Desbloquear utilizador' : 'Bloquear utilizador' ?>"
                                            data-confirm-message="<?= htmlspecialchars(($esta_bloqueado ? 'Desbloquear' : 'Bloquear') . ' o utilizador ' . ($row['nome_utilizador'] ?? '') . '?', ENT_QUOTES) ?>"
                                            data-confirm-cta="<?= $esta_bloqueado ? 'Desbloquear' : 'Bloquear' ?>">
                                            <i class="bi <?= $esta_bloqueado ? 'bi-unlock' : 'bi-lock' ?>" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn-icon btn-icon--danger"
                                            href="?delete=<?= $row['id_utilizador'] ?>"
                                            title="Eliminar utilizador"
                                            aria-label="Eliminar utilizador"
                                            data-confirm="1"
                                            data-confirm-title="Eliminar utilizador"
                                            data-confirm-message="<?= htmlspecialchars('Eliminar permanentemente o utilizador ' . ($row['nome_utilizador'] ?? '') . '?', ENT_QUOTES) ?>"
                                            data-confirm-cta="Eliminar">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="6" style="text-align: center; padding: 20px; color: #aaa;">
                                Nenhum utilizador encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Função para mostrar/esconder formulários
function staffSetStep(isForm) {
    const stepPick = document.getElementById('staffStepPick');
    const stepForm = document.getElementById('staffStepForm');
    const stepsWrap = document.querySelector('.staff-steps');
    const panel = document.querySelector('.staff-create-panel');
    if (stepPick) {
        stepPick.classList.toggle('is-active', !isForm);
        stepPick.classList.toggle('is-done', isForm);
    }
    if (stepForm) stepForm.classList.toggle('is-active', isForm);
    if (stepsWrap) stepsWrap.classList.toggle('is-form', isForm);
    if (panel) panel.classList.toggle('is-form-mode', isForm);
}

function staffSetTypeBar(tipo, visible) {
    const bar = document.getElementById('staffTypeBar');
    if (!bar) return;
    bar.classList.toggle('hidden', !visible);
    bar.querySelectorAll('.staff-type-bar__btn').forEach(btn => {
        btn.classList.toggle('is-active', visible && btn.dataset.tipo === tipo);
    });
}

function mostrarForm(tipo) {
    scrollToSection('criar-funcionario');
    const formMecanico = document.getElementById('formMecanico');
    const formChefeOficina = document.getElementById('formChefeOficina');
    const formToggle = document.getElementById('formToggle');

    staffSetStep(true);
    staffSetTypeBar(tipo, true);

    if (formToggle) {
        formToggle.style.opacity = '0';
        formToggle.style.transition = 'opacity 0.25s ease';
        setTimeout(() => {
            if (formToggle) {
                formToggle.classList.add('hidden');
                formToggle.style.opacity = '';
            }
        }, 250);
    }

    [formMecanico, formChefeOficina].forEach(form => {
        if (form) {
            form.classList.remove('visible');
            form.classList.add('hidden');
        }
    });

    setTimeout(() => {
        const formToShow = tipo === 'mecanico' ? formMecanico : formChefeOficina;
        if (formToShow) {
            formToShow.classList.remove('hidden');
            formToShow.classList.add('visible');
            formToShow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            const firstInput = formToShow.querySelector('input:not([type="hidden"]):not([readonly])');
            if (firstInput) firstInput.focus({ preventScroll: true });
        }
    }, 40);
}

function voltarSelecao() {
    const formMecanico = document.getElementById('formMecanico');
    const formChefeOficina = document.getElementById('formChefeOficina');
    const formToggle = document.getElementById('formToggle');

    staffSetStep(false);
    staffSetTypeBar('', false);

    if (formMecanico) {
        formMecanico.classList.remove('visible');
        formMecanico.classList.add('hidden');
    }
    if (formChefeOficina) {
        formChefeOficina.classList.remove('visible');
        formChefeOficina.classList.add('hidden');
    }

    setTimeout(() => {
        if (formToggle) {
            formToggle.classList.remove('hidden');
            formToggle.style.opacity = '0';
            formToggle.style.transition = 'opacity 0.35s ease';
            requestAnimationFrame(() => {
                formToggle.style.opacity = '1';
            });
            formToggle.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, 200);
}

// Função para scroll suave
const ADMIN_SECTION_ALIASES = { chefes: 'chefes_oficina', funcionarios: 'criar-funcionario' };
const ADMIN_SECTION_TITLES = <?= json_encode($admin_section_labels, JSON_UNESCAPED_UNICODE) ?>;

function setAdminPageTitle(sectionId) {
    const titleEl = document.getElementById('adminPageTitle');
    if (!titleEl) return;
    titleEl.textContent = ADMIN_SECTION_TITLES[sectionId] || 'Painel';
}

function closeAdminSidebar() {
    document.body.classList.remove('admin-sidebar-open');
    const toggle = document.getElementById('adminMenuToggle');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    const overlay = document.getElementById('adminOverlay');
    if (overlay) overlay.hidden = true;
}

function scrollToSection(sectionId) {
    sectionId = ADMIN_SECTION_ALIASES[sectionId] || sectionId;
    let element = document.getElementById(sectionId);
    if (!element || !element.classList.contains('content-section')) {
        element = document.getElementById('dashboard');
        sectionId = 'dashboard';
    }
    if (!element) return false;

    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    element.classList.add('active');

    document.querySelectorAll('.admin-nav__item').forEach(link => {
        link.classList.remove('is-active');
    });
    const activeLink = document.querySelector('.admin-nav__item[data-section="' + sectionId + '"]');
    if (activeLink) activeLink.classList.add('is-active');

    setAdminPageTitle(sectionId);

    const qs = new URLSearchParams(window.location.search);
    qs.set('tab', sectionId);
    const newUrl = window.location.pathname + '?' + qs.toString() + '#' + sectionId;
    window.history.replaceState({}, '', newUrl);

    closeAdminSidebar();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return false;
}

function openConfirmModal(opts) {
    const modal = document.getElementById('modalConfirmAcao');
    if (!modal) return false;

    const titleEl = document.getElementById('confirmAcaoTituloVisivel');
    const msgEl = document.getElementById('confirmAcaoMensagem');
    const okEl = document.getElementById('confirmAcaoConfirmar');

    const title = (opts && opts.title) ? String(opts.title) : 'Confirmar';
    const message = (opts && opts.message) ? String(opts.message) : 'Tem a certeza que pretende realizar esta ação?';
    const href = (opts && opts.href) ? String(opts.href) : '#';
    const cta = (opts && opts.cta) ? String(opts.cta) : 'Confirmar';
    const btnClass = (opts && opts.btnClass) ? String(opts.btnClass) : 'btn-warning';

    if (titleEl) titleEl.textContent = title;
    if (msgEl) {
        msgEl.textContent = message.replace(/&#10;/g, '\n');
    }
    if (okEl) {
        okEl.href = href;
        okEl.textContent = cta;
        okEl.className = 'btn ' + btnClass;
    }

    modal.classList.add('active');
    return true;
}

function bindConfirmLinks(root) {
    const scope = root || document;
    scope.querySelectorAll('a[data-confirm="1"], button[data-confirm="1"]').forEach((link) => {
        if (link.dataset.amConfirmBound === '1') {
            return;
        }
        link.dataset.amConfirmBound = '1';
        link.addEventListener('click', (e) => {
            e.preventDefault();
            openConfirmModal({
                title: link.getAttribute('data-confirm-title') || 'Confirmar',
                message: (link.getAttribute('data-confirm-message') || 'Tem a certeza que pretende realizar esta ação?').replace(/&#10;/g, '\n'),
                href: link.getAttribute('href') || '#',
                cta: link.getAttribute('data-confirm-cta') || 'Confirmar',
                btnClass: link.getAttribute('data-confirm-btn-class') || 'btn-warning',
            });
        });
    });
}

// Inicializar a página
document.addEventListener('DOMContentLoaded', function() {
    bindConfirmLinks(document);
    const menuToggle = document.getElementById('adminMenuToggle');
    const overlay = document.getElementById('adminOverlay');
    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            const open = document.body.classList.toggle('admin-sidebar-open');
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (overlay) overlay.hidden = !open;
        });
    }
    if (overlay) {
        overlay.addEventListener('click', closeAdminSidebar);
    }

    ['modalEditarUtilizador', 'modalEditarVeiculo', 'modalNotaMarcacao', 'modalEditarMarca', 'modalEditarModelo', 'modalConfirmAcao'].forEach((id) => {
        const el = document.getElementById(id);
        if (el && el.parentElement !== document.body) document.body.appendChild(el);
    });

    const hashSection = (window.location.hash || '').replace('#', '').trim();
    const initialSection = hashSection || '<?= htmlspecialchars($admin_tab, ENT_QUOTES) ?>';
    scrollToSection(initialSection);

    window.addEventListener('hashchange', () => {
        const id = (window.location.hash || '').replace('#', '').trim();
        if (id) scrollToSection(id);
    });

    const filtroTxt = document.getElementById('filtroMarcacoes');
    const filtroEstado = document.getElementById('filtroEstadoMarcacao');
    const tabela = document.getElementById('tabelaMarcacoes');
    if (tabela && filtroTxt && filtroEstado) {
        const filtrar = () => {
            const termo = filtroTxt.value.toLowerCase().trim();
            const estado = filtroEstado.value.toLowerCase().trim();
            tabela.querySelectorAll('tbody tr').forEach((row) => {
                const txt = row.textContent.toLowerCase();
                const estadoTxt = (row.querySelector('.status-pill')?.textContent || '').toLowerCase();
                const okTexto = !termo || txt.includes(termo);
                const okEstado = !estado || estadoTxt.includes(estado);
                row.style.display = (okTexto && okEstado) ? '' : 'none';
            });
        };
        const persistMarcacaoFiltros = () => {
            try {
                sessionStorage.setItem('admin_marcacoes_filtros', JSON.stringify({
                    txt: filtroTxt.value,
                    est: filtroEstado.value
                }));
            } catch (e) { /* ignore */ }
        };
        try {
            const rawF = sessionStorage.getItem('admin_marcacoes_filtros');
            if (rawF) {
                const o = JSON.parse(rawF);
                if (typeof o.txt === 'string') filtroTxt.value = o.txt;
                if (typeof o.est === 'string') filtroEstado.value = o.est;
            }
        } catch (e) { /* ignore */ }
        filtrar();
        filtroTxt.addEventListener('input', () => { filtrar(); persistMarcacaoFiltros(); });
        filtroEstado.addEventListener('change', () => { filtrar(); persistMarcacaoFiltros(); });

        const paramsMarcacao = new URLSearchParams(window.location.search);
        const marcacaoFocus = paramsMarcacao.get('marcacao_focus');
        if (marcacaoFocus) {
            const rowFocus = document.getElementById('marcacao-row-' + marcacaoFocus);
            if (rowFocus) {
                rowFocus.style.display = '';
                rowFocus.scrollIntoView({ behavior: 'smooth', block: 'center' });
                rowFocus.classList.add('marcacao-row-highlight');
                setTimeout(() => rowFocus.classList.remove('marcacao-row-highlight'), 2600);
            }
            paramsMarcacao.delete('marcacao_focus');
            const qs = paramsMarcacao.toString();
            const nextUrl = window.location.pathname + (qs ? '?' + qs : '') + (window.location.hash || '');
            window.history.replaceState({}, '', nextUrl);
        }
    }

    const filtroUtilizadores = document.getElementById('filtroUtilizadores');
    const tabelaUtilizadores = document.getElementById('tabelaUtilizadores');
    if (filtroUtilizadores && tabelaUtilizadores) {
        const filtrarUtilizadores = () => {
            const termo = filtroUtilizadores.value.toLowerCase().trim();
            tabelaUtilizadores.querySelectorAll('tbody tr').forEach((row) => {
                const txt = row.textContent.toLowerCase();
                row.style.display = (!termo || txt.includes(termo)) ? '' : 'none';
            });
        };
        filtroUtilizadores.addEventListener('input', filtrarUtilizadores);
    }

    const filtroVeiculos = document.getElementById('filtroVeiculos');
    const tabelaVeiculos = document.getElementById('tabelaVeiculos');
    if (filtroVeiculos && tabelaVeiculos) {
        const filtrarVeiculos = () => {
            const termo = filtroVeiculos.value.toLowerCase().trim();
            tabelaVeiculos.querySelectorAll('tbody tr').forEach((row) => {
                const txt = row.textContent.toLowerCase();
                row.style.display = (!termo || txt.includes(termo)) ? '' : 'none';
            });
        };
        filtroVeiculos.addEventListener('input', filtrarVeiculos);
    }

    document.querySelectorAll('.abrir-editar-utilizador').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('editUserId').value = btn.dataset.id || '';
            document.getElementById('editUserNome').value = btn.dataset.nome || '';
            document.getElementById('editUserEmail').value = btn.dataset.email || '';
            document.getElementById('editUserTelefone').value = (btn.dataset.telefone || '').replace(/\D+/g, '').slice(0, 9).replace(/(\d{3})(?=\d)/g, '$1 ').trim();
            document.getElementById('editUserNif').value = (btn.dataset.nif || '').replace(/\D+/g, '').slice(0, 9);
            document.getElementById('editUserNif').dataset.excluirUtilizador = btn.dataset.id || '';
            document.getElementById('modalEditarUtilizador').classList.add('active');
        });
    });

    document.querySelectorAll('.abrir-editar-veiculo').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('editCarroId').value = btn.dataset.id || '';
            document.getElementById('editCarroUtilizador').value = btn.dataset.idUtilizador || '';
            document.getElementById('editCarroMarca').value = btn.dataset.marca || '';
            document.getElementById('editCarroModelo').value = btn.dataset.modelo || '';
            document.getElementById('editCarroAno').value = btn.dataset.ano || '';
            document.getElementById('editCarroMatricula').value = btn.dataset.matricula || '';
            document.getElementById('editCarroNotas').value = btn.dataset.notas || '';
            document.getElementById('editCarroPrincipal').checked = (btn.dataset.principal || '') === '1';
            document.getElementById('modalEditarVeiculo').classList.add('active');
        });
    });

    document.querySelectorAll('.abrir-editar-marca').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('editMarcaId').value = btn.dataset.id || '';
            document.getElementById('editMarcaNome').value = btn.dataset.nome || '';
            document.getElementById('modalEditarMarca').classList.add('active');
        });
    });

    document.querySelectorAll('.abrir-editar-modelo').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('editModeloId').value = btn.dataset.id || '';
            document.getElementById('editModeloMarca').value = btn.dataset.idMarca || '';
            document.getElementById('editModeloNome').value = btn.dataset.modelo || '';
            document.getElementById('modalEditarModelo').classList.add('active');
        });
    });

    document.querySelectorAll('.abrir-nota-marcacao').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('notaMarcacaoId').value = btn.dataset.id || '';
            document.getElementById('notaMarcacaoTexto').value = btn.dataset.nota || '';
            document.getElementById('modalNotaMarcacao').classList.add('active');
        });
    });

    document.querySelectorAll('.fechar-modal').forEach((btn) => {
        btn.addEventListener('click', () => {
            const alvo = btn.dataset.target;
            const modal = document.getElementById(alvo);
            if (modal) modal.classList.remove('active');
        });
    });

    ['editUserTelefone', 'editUserNif'].forEach((id) => {
        const el = document.getElementById(id);
        if (el && id === 'editUserTelefone') {
            el.addEventListener('input', () => {
                el.value = el.value.replace(/\D+/g, '').slice(0, 9);
            });
        }
    });

    if (typeof amInitNifInputs === 'function') {
        amInitNifInputs(document);
    }

    const matInput = document.getElementById('editCarroMatricula');
    if (matInput) {
        matInput.addEventListener('input', () => {
            const limpo = matInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
            matInput.value = [limpo.slice(0, 2), limpo.slice(2, 4), limpo.slice(4, 6)].filter(Boolean).join('-');
        });
    }

    if (window.Chart) {
        const chartFont = { family: 'Inter, system-ui, sans-serif' };
        const estadoCtx = document.getElementById('estadoChart');
        if (estadoCtx) {
            new Chart(estadoCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_keys($dashboard_status), JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($dashboard_status)) ?>,
                        backgroundColor: ['#9CA3AF', '#8B5CF6', '#3B82F6', '#F59E0B', '#10B981', '#6366F1', '#EF4444', '#ea580c'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#d1d5db', boxWidth: 12, padding: 14, font: chartFont }
                        },
                        tooltip: { enabled: true }
                    }
                }
            });
        }

        const diasCtx = document.getElementById('ultimos7diasChart');
        const vals7 = <?= json_encode($values_7d) ?>;
        const max7 = Math.max(1, ...(Array.isArray(vals7) ? vals7 : [0]));
        if (diasCtx) {
            new Chart(diasCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels_7d, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        label: 'Marcações',
                        data: vals7,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.35)',
                        borderRadius: 6,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: { color: '#d1d5db', font: chartFont, maxRotation: 0, autoSkip: false },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: max7 + 1,
                            ticks: { color: '#d1d5db', precision: 0, font: chartFont },
                            grid: { color: 'rgba(255,255,255,0.08)' }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true }
                    }
                }
            });
        }
    }
});
</script>
<script src="nif.js?v=2"></script>
<script src="iban.js?v=3"></script>
<script src="telemovel.js?v=1"></script>
<script src="matricula.js?v=1"></script>
</body>
</html>
