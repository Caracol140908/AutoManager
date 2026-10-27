<?php
session_start();
require_once('check_session.php');
require_once('ligar.php');

// Se expirou, redirecionar
if (isset($_SESSION['temp_expired']) && $_SESSION['temp_expired'] === true) {
    if (!headers_sent()) {
        header('Location: login.php?expired=1');
        exit;
    }
}


if (empty($_SESSION['user']['id_utilizador'])) {
    header('Location: login.php');
    exit;
}
$id_utilizador = (int) $_SESSION['user']['id_utilizador'];
$is_cliente_logado = true;

require_once __DIR__ . '/inc_veiculo_perfil.php';
require_once __DIR__ . '/inc_vin_ajuda.php';
require_once __DIR__ . '/inc_orcamento_interativo.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';
require_once __DIR__ . '/inc_fatura_documento.php';
require_once __DIR__ . '/inc_cancelamento_marcacao.php';
require_once __DIR__ . '/inc_eliminar_carro.php';
require_once __DIR__ . '/inc_eliminar_conta.php';
require_once __DIR__ . '/inc_marcas_modelos.php';
require_once __DIR__ . '/inc_nif.php';
require_once __DIR__ . '/inc_telemovel.php';
require_once __DIR__ . '/inc_excesso_orcamento.php';
am_servico_sincronizar_cancelados_excesso_recusado($con);
require_once __DIR__ . '/inc_utilizador_notificacoes.php';
require_once __DIR__ . '/inc_codigo_levantamento.php';

am_garantir_nif_unico_schema($con);

$am_marcas = am_marcas_listar($con);
$am_modelos_por_marca = am_modelos_por_marca_map($con);

function garantirColunasDadosPessoais(mysqli $con): array {
    $campos = [];
    $result = $con->query("SHOW COLUMNS FROM utilizador");
    if ($result) {
        while ($col = $result->fetch_assoc()) {
            $campos[] = $col['Field'];
        }
    }

    if (!in_array('telefone', $campos, true)) {
        @$con->query("ALTER TABLE utilizador ADD COLUMN telefone VARCHAR(20) NULL AFTER email");
    }

    if (!in_array('nif', $campos, true)) {
        @$con->query("ALTER TABLE utilizador ADD COLUMN nif VARCHAR(9) NULL AFTER telefone");
    }

    $camposAtualizados = [];
    $result2 = $con->query("SHOW COLUMNS FROM utilizador");
    if ($result2) {
        while ($col = $result2->fetch_assoc()) {
            $camposAtualizados[] = $col['Field'];
        }
    }
    return $camposAtualizados;
}

// Garantir que os campos existem para guardar sempre na BD
$campos_utilizador = garantirColunasDadosPessoais($con);
$tem_coluna_telefone = in_array('telefone', $campos_utilizador, true);
$tem_coluna_nif = in_array('nif', $campos_utilizador, true);

function validarNifPortuguesCfg(string $nif): bool {
    [$ok] = am_validar_nif_portugues($nif);
    return $ok;
}

function garantirColunaCarroPrincipal(mysqli $con): void {
    $res = $con->query("SHOW COLUMNS FROM carro LIKE 'is_principal'");
    if ($res && $res->num_rows > 0) return;
    // 0/1 para indicar veículo principal
    @$con->query("ALTER TABLE carro ADD COLUMN is_principal TINYINT(1) NOT NULL DEFAULT 0");
}

function definirCarroPrincipalUsuario(mysqli $con, int $id_utilizador, int $id_carro): bool {
    if ($id_utilizador <= 0 || $id_carro <= 0) return false;
    @$con->query("UPDATE carro SET is_principal = 0 WHERE id_utilizador = " . (int)$id_utilizador);
    @$con->query("UPDATE carro SET is_principal = 1 WHERE id_utilizador = " . (int)$id_utilizador . " AND id_carro = " . (int)$id_carro);
    return $con->affected_rows >= 0;
}

function normalizarCarroPrincipalUsuario(mysqli $con, int $id_utilizador): void {
    if ($id_utilizador <= 0) return;
    $q = $con->prepare("
        SELECT id_carro, is_principal
        FROM carro
        WHERE id_utilizador = ?
        ORDER BY is_principal DESC, created_at DESC, id_carro DESC
    ");
    if (!$q) return;
    $q->bind_param("i", $id_utilizador);
    $q->execute();
    $r = $q->get_result();
    $ids = [];
    $principalCount = 0;
    while ($row = $r->fetch_assoc()) {
        $ids[] = (int)$row['id_carro'];
        if (!empty($row['is_principal'])) $principalCount++;
    }
    $q->close();
    if (!$ids) return;
    if ($principalCount === 1) return;
    definirCarroPrincipalUsuario($con, $id_utilizador, $ids[0]);
}

function garantirColunasServicoMarcacao(mysqli $con): void {
    $campos = [];
    $res = $con->query("SHOW COLUMNS FROM servico");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $campos[] = $row['Field'];
        }
    }

    if (!in_array('nif', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN nif VARCHAR(9) NULL AFTER telefone");
    }
    if (!in_array('marca', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN marca VARCHAR(60) NULL AFTER nif");
    }
    if (!in_array('modelo', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN modelo VARCHAR(80) NULL AFTER marca");
    }
    if (!in_array('hora_desejada', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN hora_desejada VARCHAR(5) NULL AFTER data_desejada");
    }
    if (!in_array('estado_orcamento', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN estado_orcamento VARCHAR(30) NULL");
    }
    if (!in_array('orcamento_aprovado', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN orcamento_aprovado DECIMAL(10,2) NULL");
    }
    if (!in_array('estado_orcamento_cliente', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN estado_orcamento_cliente VARCHAR(20) NULL");
    }
    if (!in_array('data_orcamento_aceite', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN data_orcamento_aceite DATETIME NULL");
    }
    if (!in_array('aviso_chefe_orcamento_aceite', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN aviso_chefe_orcamento_aceite TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('data_orcamento_recusado', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN data_orcamento_recusado DATETIME NULL");
    }
    if (!in_array('aviso_chefe_orcamento_recusado', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN aviso_chefe_orcamento_recusado TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('estado_pagamento', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN estado_pagamento VARCHAR(20) NULL");
    }
    if (!in_array('metodo_pagamento', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN metodo_pagamento VARCHAR(30) NULL");
    }
    if (!in_array('data_pagamento', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN data_pagamento DATETIME NULL");
    }
    if (!in_array('subtotal_sem_iva', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN subtotal_sem_iva DECIMAL(10,2) NULL");
    }
    if (!in_array('valor_iva', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN valor_iva DECIMAL(10,2) NULL");
    }
    if (!in_array('valor_total_com_iva', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN valor_total_com_iva DECIMAL(10,2) NULL");
    }
    if (!in_array('valor_pago_total', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN valor_pago_total DECIMAL(10,2) NULL");
    }
    if (!in_array('valor_em_divida', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN valor_em_divida DECIMAL(10,2) NULL");
    }
    if (!in_array('pedir_contribuinte_fatura', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN pedir_contribuinte_fatura TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('contribuinte_fatura', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN contribuinte_fatura VARCHAR(9) NULL");
    }
    if (!in_array('plano_pronto', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN plano_pronto VARCHAR(30) NULL");
    }
    if (!in_array('sla_prometido_horas', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN sla_prometido_horas INT NULL");
    }
    if (!in_array('sla_inicio_at', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN sla_inicio_at DATETIME NULL");
    }
    if (!in_array('sla_limite_at', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN sla_limite_at DATETIME NULL");
    }
    if (!in_array('sla_estado', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN sla_estado VARCHAR(20) NULL");
    }
    if (!in_array('sla_compensacao_tipo', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN sla_compensacao_tipo VARCHAR(30) NULL");
    }
    if (!in_array('sla_compensacao_valor', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN sla_compensacao_valor DECIMAL(10,2) NULL");
    }
    if (!in_array('estado_excesso_orcamento_cliente', $campos, true)) {
        @$con->query("ALTER TABLE servico ADD COLUMN estado_excesso_orcamento_cliente VARCHAR(20) NULL");
    }
    am_ensure_orcamento_interativo($con);
    am_ensure_estado_pagamento_varchar($con);
    require_once __DIR__ . '/inc_cancelamento_marcacao.php';
    am_ensure_cancelamento_marcacao_columns($con);
}

// Buscar dados do utilizador
$select_telefone = ", telefone";
$select_nif = ", nif";

$stmt = $con->prepare("
    SELECT nome_utilizador, carro, email, password, created_at, updated_at, 
           verificado, termos_aceite, data_aceite, metodo_pagamento_preferido
           $select_telefone
           $select_nif
    FROM utilizador
    WHERE id_utilizador = ?
");
$stmt->bind_param("i", $id_utilizador);
if (!$stmt->execute()) {
    die("Erro ao obter utilizador: " . $stmt->error);
}
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Inicializa mensagem
$mensagem = '';
$mensagem_tipo = ''; // 'sucesso' | 'erro'
$mostrar_edicao_dados = false;

// Flash message (para não ficar "preso" em POST)
$mensagem_modal_car_id = 0;
$mensagem_modal_secao = '';
$mensagem_modal_form = [];
if (isset($_SESSION['flash_mensagem'])) {
    $mensagem = (string)($_SESSION['flash_mensagem'] ?? '');
    $mensagem_tipo = (string)($_SESSION['flash_mensagem_tipo'] ?? '');
    $mensagem_modal_car_id = (int)($_SESSION['flash_modal_car_id'] ?? 0);
    $mensagem_modal_secao = (string)($_SESSION['flash_modal_secao'] ?? '');
    if (isset($_SESSION['flash_modal_form']) && is_array($_SESSION['flash_modal_form'])) {
        $mensagem_modal_form = $_SESSION['flash_modal_form'];
    }
    unset(
        $_SESSION['flash_mensagem'],
        $_SESSION['flash_mensagem_tipo'],
        $_SESSION['flash_modal_car_id'],
        $_SESSION['flash_modal_secao'],
        $_SESSION['flash_modal_form']
    );
}
if ((isset($_GET['cancelado']) && (string) $_GET['cancelado'] === '1') || (isset($_GET['ver']) && (string) $_GET['ver'] === 'canceladas')) {
    if ($mensagem === '' && isset($_GET['cancelado'])) {
        $mensagem = 'Marcação cancelada com sucesso.';
        $mensagem_tipo = 'sucesso';
    }
}
if ($mensagem === '' && isset($_GET['eliminado']) && (string) $_GET['eliminado'] === '1') {
    $mensagem = 'Veículo eliminado com sucesso. Deixou de aparecer em todo o site.';
    $mensagem_tipo = 'sucesso';
}

function flashRedirect(string $mensagem, string $tipo, string $seccao, string $extra_query = '', int $modal_car_id = 0, string $modal_secao = '', ?array $modal_form = null): void {
    $_SESSION['flash_mensagem'] = $mensagem;
    $_SESSION['flash_mensagem_tipo'] = $tipo;
    if ($modal_car_id > 0) {
        $_SESSION['flash_modal_car_id'] = $modal_car_id;
        $_SESSION['flash_modal_secao'] = $modal_secao !== '' ? $modal_secao : 'dados';
        if (is_array($modal_form) && $modal_form !== []) {
            $_SESSION['flash_modal_form'] = $modal_form;
        }
    }
    $url = 'configuracoes.php?seccao=' . urlencode($seccao);
    if ($extra_query !== '') {
        $url .= '&' . ltrim($extra_query, '&');
    }
    header('Location: ' . $url);
    exit;
}

// ELIMINAR CARRO (processar antes de carregar listagens)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['eliminar_carro'])) {
    $id_carro_del = (int) ($_POST['id_carro'] ?? 0);
    $resumoDel = am_carro_resumo_eliminacao($con, $id_utilizador, $id_carro_del, (string) ($user['email'] ?? ''));

    if (!$resumoDel['ok']) {
        flashRedirect($resumoDel['error'], 'erro', 'veiculos');
    }

    $execDel = am_carro_executar_eliminacao($con, $id_utilizador, $id_carro_del, (string) ($user['email'] ?? ''), 0.0, false);
    if ($execDel['ok']) {
        flashRedirect('Veículo eliminado com sucesso.', 'sucesso', 'veiculos');
    }
    flashRedirect($execDel['error'], 'erro', 'veiculos');
}

// Nova marcação (Minhas Marcações)
if (isset($_POST['nova_marcacao'])) {
    try {
        garantirColunasServicoMarcacao($con);

        $nome = trim($_POST['nome'] ?? $user['nome_utilizador'] ?? '');
        $email = trim($_POST['email'] ?? $user['email'] ?? '');
        $telefone = am_normalizar_telemovel((string) ($_POST['telefone'] ?? ($user['telefone'] ?? '')));
        $nif = preg_replace('/\D+/', '', $_POST['nif'] ?? ($user['nif'] ?? ''));

        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        [$matriculaOk, $matriculaErro, $matricula] = am_validar_matricula($_POST['matricula'] ?? '');
        $tipo_servico = trim($_POST['service'] ?? '');
        $data_desejada = $_POST['date'] ?? '';
        $hora_desejada = $_POST['time'] ?? '';
        [$telOkMarc, $msgTelMarc] = am_validar_telemovel_portugues($telefone, true);

        if ($nome === '' || $email === '' || $telefone === '' || $nif === '' || $marca === '' || $modelo === '' || $matricula === '' || $tipo_servico === '' || $data_desejada === '' || $hora_desejada === '') {
            $mensagem = "Preencha todos os campos obrigatórios da marcação.";
            $mensagem_tipo = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = "Email inválido.";
            $mensagem_tipo = 'erro';
        } elseif (!$telOkMarc) {
            $mensagem = $msgTelMarc;
            $mensagem_tipo = 'erro';
        } elseif (!validarNifPortuguesCfg($nif)) {
            $mensagem = "NIF inválido.";
            $mensagem_tipo = 'erro';
        } else {
            [$nifUnicoMarc, $msgNifUnicoMarc] = am_validar_nif_unico($con, $nif, ['utilizador' => $id_utilizador]);
            if (!$nifUnicoMarc) {
                $mensagem = $msgNifUnicoMarc;
                $mensagem_tipo = 'erro';
            }
        }

        if ($mensagem === '') {
            if (!$matriculaOk) {
                $mensagem = $matriculaErro;
                $mensagem_tipo = 'erro';
            } else {
                require_once __DIR__ . '/inc_horarios_marcacao.php';
                [$dataOkMarc, $msgDataMarc] = am_horarios_validar_data_marcacao((string) $data_desejada);
                if (!$dataOkMarc) {
                    $mensagem = $msgDataMarc;
                    $mensagem_tipo = 'erro';
                } elseif (!preg_match('/^\d{2}:\d{2}$/', $hora_desejada)) {
                    $mensagem = "Hora inválida.";
                    $mensagem_tipo = 'erro';
                }
            }
        }

        if ($mensagem === '') {
            require_once __DIR__ . '/inc_horarios_marcacao.php';
            [$slotOk, $slotMsg] = am_horario_slot_disponivel($con, (string) $data_desejada, (string) $hora_desejada);
            if (!$slotOk) {
                $mensagem = $slotMsg;
                $mensagem_tipo = 'erro';
            }
        }

        if ($mensagem === '') {
            require_once __DIR__ . '/inc_rate_limit_marcacao.php';
            [$rlOk, $rlMsg] = am_rate_limit_marcacao_check($con, $id_utilizador, $email);
            if (!$rlOk) {
                $mensagem = $rlMsg;
                $mensagem_tipo = 'erro';
            } else {

            $custos = [
                'Revisão geral' => rand(120, 250),
                'Diagnóstico eletrónico' => rand(40, 80),
                'Pneus & alinhamento' => rand(60, 180),
                'Travões & suspensão' => rand(100, 300),
                'Bateria & elétrica' => rand(70, 200),
                'Ar condicionado & climatização' => rand(60, 150),
                'Óleo & filtros' => rand(50, 120),
                'Limpeza & detalhamento' => rand(30, 100)
            ];
            $custo = $custos[$tipo_servico] ?? rand(50, 150);

            $ins = $con->prepare("INSERT INTO servico (nome, telefone, nif, marca, modelo, email, matricula, tipo_servico, data_desejada, hora_desejada, custo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$ins) {
                throw new Exception("Erro na preparação da query: " . $con->error);
            }
            $ins->bind_param("ssssssssssi", $nome, $telefone, $nif, $marca, $modelo, $email, $matricula, $tipo_servico, $data_desejada, $hora_desejada, $custo);
            if ($ins->execute()) {
                am_rate_limit_marcacao_registar();
                $mensagem = "Marcação registada com sucesso!";
                $mensagem_tipo = 'sucesso';
            } else {
                $mensagem = "Erro ao registar marcação: " . $ins->error;
                $mensagem_tipo = 'erro';
            }
            $ins->close();
            }
        }
    } catch (Exception $e) {
        $mensagem = "Erro ao registar marcação: " . $e->getMessage();
        $mensagem_tipo = 'erro';
    }

    // PRG: mostrar a mensagem 1 vez e limpar o POST
    if ($mensagem !== '') {
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'marcacoes');
    }
}

// Cliente aceita orçamento
if (isset($_POST['aceitar_orcamento_cliente'])) {
    garantirColunasServicoMarcacao($con);
    $servico_id_aceitar = (int) ($_POST['servico_id'] ?? 0);
    if ($servico_id_aceitar <= 0) {
        flashRedirect('Serviço inválido para aceitar orçamento.', 'erro', 'marcacoes');
    }

    $nLinhas = am_servico_orcamento_itens_count($con, $servico_id_aceitar);
    if ($nLinhas > 0) {
        $decPorId = [];
        $orcLine = $_POST['orc_line'] ?? [];
        if (is_array($orcLine)) {
            foreach ($orcLine as $idStr => $v) {
                $idL = (int) $idStr;
                if ($idL <= 0) {
                    continue;
                }
                if ((string) $v === '1' || $v === 1 || $v === true) {
                    $decPorId[$idL] = 'aceite';
                }
            }
        }
        $radioGrupo = [];
        $orcGrupo = $_POST['orc_grupo'] ?? [];
        if (is_array($orcGrupo)) {
            foreach ($orcGrupo as $slug => $chosenId) {
                $slug = trim((string) $slug);
                if ($slug === '') {
                    continue;
                }
                $radioGrupo[$slug] = (int) $chosenId;
            }
        }
        $resDec = am_servico_orcamento_cliente_aplicar_decisoes($con, $servico_id_aceitar, (string) $user['email'], $decPorId, $radioGrupo, $id_utilizador);
        if (!$resDec['ok']) {
            flashRedirect($resDec['error'] ?? 'Não foi possível validar a tua seleção.', 'erro', 'marcacoes');
        }
    }

    $emailAceitar = am_cliente_email_canonico($con, $id_utilizador, (string) ($user['email'] ?? ''));
    $resAceitar = am_servico_cliente_aceitar_orcamento($con, $servico_id_aceitar, $id_utilizador, $emailAceitar);
    if ($resAceitar['ok']) {
        $srvA = $resAceitar['servico'] ?? null;
        if (is_array($srvA)) {
            require_once __DIR__ . '/inc_notificar_cliente_email.php';
            $nomeMail = trim((string) ($srvA['nome'] ?? ''));
            if ($nomeMail === '') {
                $nomeMail = trim((string) ($user['nome_utilizador'] ?? ''));
            }
            $tipoA = htmlspecialchars(trim((string) ($srvA['tipo_servico'] ?? '')), ENT_QUOTES, 'UTF-8');
            $matA = htmlspecialchars(trim((string) ($srvA['matricula'] ?? '')), ENT_QUOTES, 'UTF-8');
            $valor = (float) ($srvA['orcamento_aprovado'] ?? 0);
            $valorFmt = number_format($valor, 2, ',', ' ');
            $listaA = '<ul style="padding-left:20px">';
            if ($tipoA !== '') {
                $listaA .= '<li><strong>Serviço:</strong> ' . $tipoA . '</li>';
            }
            if ($matA !== '') {
                $listaA .= '<li><strong>Matrícula:</strong> ' . $matA . '</li>';
            }
            $listaA .= '<li><strong>Valor do orçamento aceite:</strong> €' . $valorFmt . ' (sem IVA)</li>';
            $listaA .= '</ul>';
            $corpoA = '<p>Confirmámos a <strong>aceitação do orçamento</strong> para o serviço indicado abaixo.</p>' . $listaA
                . '<p>A oficina irá atribuir um mecânico em breve. Pode seguir o estado na sua área de cliente.</p>';
            am_enviar_email_cliente_automanager(
                $emailAceitar,
                $nomeMail,
                'AUTOMANAGER — Orçamento aceite',
                $corpoA
            );
        }
        flashRedirect("Orçamento aceite com sucesso. A oficina irá atribuir um mecânico em breve.", "sucesso", "marcacoes");
    }
    flashRedirect($resAceitar['error'] ?? 'Não foi possível aceitar este orçamento.', 'erro', 'marcacoes');
}

// Cliente paga orçamento aceite (pagamento fictício realista)
if (isset($_POST['pagar_orcamento_cliente'])) {
    garantirColunasServicoMarcacao($con);
    $servico_id_pagar = (int)($_POST['servico_id'] ?? 0);
    $metodo_pagamento_srv = trim((string)($_POST['metodo_pagamento_srv'] ?? ''));
    $pedir_nif_fatura = isset($_POST['pedir_contribuinte_fatura']) ? 1 : 0;
    $nif_fatura = preg_replace('/\D+/', '', (string)($_POST['contribuinte_fatura'] ?? ''));

    $metodos_validos = ['mbway', 'cartao', 'referencia_mb'];
    if ($servico_id_pagar <= 0 || !in_array($metodo_pagamento_srv, $metodos_validos, true)) {
        flashRedirect("Dados de pagamento inválidos.", "erro", "marcacoes");
    }
    if ($pedir_nif_fatura && !validarNifPortuguesCfg($nif_fatura)) {
        flashRedirect("NIF de fatura inválido.", "erro", "marcacoes");
    }

    $qValor = $con->prepare("
        SELECT orcamento_aprovado, estado_pagamento, valor_em_divida, valor_pago_total,
               subtotal_sem_iva, valor_iva, valor_total_com_iva,
               estado_do_trabalho, estado, horas_trabalhadas, valor_hora, pecas_substituidas, fatura_linhas_json
        FROM servico
        WHERE id = ? AND email = ?
          AND LOWER(COALESCE(estado_orcamento, '')) = 'aprovado'
          AND LOWER(COALESCE(estado_orcamento_cliente, '')) = 'aceite'
        LIMIT 1
    ");
    if (!$qValor) {
        flashRedirect("Erro ao preparar pagamento.", "erro", "marcacoes");
    }
    $qValor->bind_param("is", $servico_id_pagar, $user['email']);
    $qValor->execute();
    $rValor = $qValor->get_result();
    $srv = $rValor ? $rValor->fetch_assoc() : null;
    $qValor->close();
    if (!$srv) {
        flashRedirect("Serviço não encontrado para pagamento.", "erro", "marcacoes");
    }

    $valor_pago_total_srv = am_servico_valor_ja_pago($srv);
    if (!am_servico_esta_concluido($srv)) {
        flashRedirect("O pagamento só é feito após o mecânico concluir o trabalho.", "erro", "marcacoes");
    }
    $total_com_iva = am_servico_valor_trabalho_mecanico_a_pagar($srv);
    if ($total_com_iva <= 0) {
        flashRedirect("Este serviço não tem valores pendentes de pagamento.", "erro", "marcacoes");
    }
    $valoresTrabalho = am_servico_valores_fiscais_fatura($srv);
    $subtotal_registo = $valoresTrabalho['subtotal_sem_iva'];
    $valor_iva_registo = $valoresTrabalho['valor_iva'];
    $total_com_iva_registo = $valoresTrabalho['valor_total_com_iva'];
    if ($subtotal_registo <= 0 || $total_com_iva_registo <= 0) {
        flashRedirect("Valor do trabalho inválido para pagamento. O mecânico deve registar horas e peças na conclusão.", "erro", "marcacoes");
    }

    $upPagamento = $con->prepare("
        UPDATE servico
        SET estado_pagamento = 'pago',
            metodo_pagamento = ?,
            data_pagamento = NOW(),
            subtotal_sem_iva = ?,
            valor_iva = ?,
            valor_total_com_iva = ?,
            valor_pago_total = ?,
            valor_em_divida = 0,
            pedir_contribuinte_fatura = ?,
            contribuinte_fatura = ?
        WHERE id = ? AND email = ?
    ");
    if (!$upPagamento) {
        flashRedirect("Erro ao guardar pagamento.", "erro", "marcacoes");
    }
    $nif_guardar = $pedir_nif_fatura ? $nif_fatura : null;
    $novo_valor_pago_total = round($valor_pago_total_srv + $total_com_iva, 2);
    $upPagamento->bind_param('sddddisis', $metodo_pagamento_srv, $subtotal_registo, $valor_iva_registo, $total_com_iva_registo, $novo_valor_pago_total, $pedir_nif_fatura, $nif_guardar, $servico_id_pagar, $user['email']);
    if ($upPagamento->execute() && $upPagamento->affected_rows > 0) {
        $upPagamento->close();
        $codigoLevantamento = am_codigo_levantamento_emitir($con, $servico_id_pagar, (string) $user['email']);
        if ($codigoLevantamento !== null) {
            require_once __DIR__ . '/inc_notificar_cliente_email.php';
            $nomeMailPg = trim((string) ($user['nome_utilizador'] ?? $user['nome'] ?? 'Cliente'));
            $corpoPg = '<p>Recebemos o pagamento do trabalho realizado.</p>';
            $corpoPg .= '<p>Para levantar o veículo, apresente este código de 6 dígitos ao mecânico:</p>';
            $corpoPg .= '<p style="font-size:28px;font-weight:800;letter-spacing:4px;color:#ea580c;margin:14px 0;">' . htmlspecialchars(am_codigo_levantamento_formatado($codigoLevantamento), ENT_QUOTES, 'UTF-8') . '</p>';
            $corpoPg .= '<p>Guarde este código até à entrega do veículo.</p>';
            @am_enviar_email_cliente_automanager((string) $user['email'], $nomeMailPg, 'AUTOMANAGER — Código de levantamento', $corpoPg);
        }
        $msgPagamento = "Pagamento do trabalho realizado concluído. Valor liquidado: €" . number_format($total_com_iva, 2, ',', ' ') . ".";
        if ($codigoLevantamento !== null) {
            $msgPagamento .= " Código de levantamento: " . am_codigo_levantamento_formatado($codigoLevantamento) . ".";
        }
        flashRedirect($msgPagamento, "sucesso", "marcacoes", 'open_servico=' . $servico_id_pagar);
    }
    $upPagamento->close();
    flashRedirect("Não foi possível processar o pagamento.", "erro", "marcacoes");
}

// Cancelar marcação (gratuito)
if (isset($_POST['cancelar_marcacao_cliente'])) {
    require_once __DIR__ . '/inc_cancelamento_marcacao.php';
    garantirColunasServicoMarcacao($con);
    $sidCancel = (int) ($_POST['servico_id'] ?? 0);
    if ($sidCancel <= 0) {
        flashRedirect('Marcação inválida para cancelamento.', 'erro', 'marcacoes');
    }
    $srvCancel = am_marcacao_carregar_cliente($con, $sidCancel, (string) $user['email']);
    if (!$srvCancel) {
        flashRedirect('Marcação não encontrada.', 'erro', 'marcacoes');
    }
    $chkCancel = am_marcacao_pode_cancelar($srvCancel);
    if (!$chkCancel['ok']) {
        flashRedirect($chkCancel['error'], 'erro', 'marcacoes');
    }
    if (am_marcacao_executar_cancelamento($con, $sidCancel, (string) $user['email'], 0.0, false)) {
        flashRedirect('Marcação cancelada com sucesso.', 'sucesso', 'marcacoes', 'ver=canceladas');
    }
    flashRedirect('Não foi possível cancelar esta marcação.', 'erro', 'marcacoes');
}

// Cliente recusa orçamento
if (isset($_POST['recusar_orcamento_cliente'])) {
    garantirColunasServicoMarcacao($con);
    $servico_id_recusar = (int)($_POST['servico_id'] ?? 0);
    if ($servico_id_recusar <= 0) {
        flashRedirect("Serviço inválido para recusar orçamento.", "erro", "marcacoes");
    }

    $upRecusar = $con->prepare("
        UPDATE servico
        SET estado_orcamento_cliente = 'recusado',
            data_orcamento_recusado = NOW(),
            aviso_chefe_orcamento_recusado = 0
        WHERE id = ? AND email = ? AND LOWER(COALESCE(estado_orcamento, '')) = 'aprovado'
    ");
    if (!$upRecusar) {
        flashRedirect("Erro ao preparar recusa do orçamento.", "erro", "marcacoes");
    }
    $upRecusar->bind_param("is", $servico_id_recusar, $user['email']);
    if ($upRecusar->execute() && $upRecusar->affected_rows > 0) {
        $upRecusar->close();
        flashRedirect("Orçamento recusado com sucesso.", "sucesso", "marcacoes");
    }
    $upRecusar->close();
    flashRedirect("Não foi possível recusar este orçamento.", "erro", "marcacoes");
}

// Cliente confirma ou recusa total acima do orçamento
if (isset($_POST['aceitar_excesso_orcamento_cliente']) || isset($_POST['recusar_excesso_orcamento_cliente'])) {
    garantirColunasServicoMarcacao($con);
    am_ensure_excesso_orcamento_cols($con);
    $sidExcesso = (int) ($_POST['servico_id'] ?? 0);
    if ($sidExcesso <= 0) {
        flashRedirect('Serviço inválido.', 'erro', 'marcacoes');
    }
    $respostaExcesso = isset($_POST['aceitar_excesso_orcamento_cliente']) ? 'aceite' : 'recusado';
    $emailExcesso = am_cliente_email_canonico($con, $id_utilizador, (string) ($user['email'] ?? ''));
    $resExcesso = am_servico_cliente_responder_excesso_orcamento($con, $sidExcesso, $id_utilizador, $emailExcesso, $respostaExcesso);
    if ($resExcesso['ok']) {
        if ($respostaExcesso === 'aceite') {
            flashRedirect('Confirmou que deseja continuar com o valor final. Pode proceder ao pagamento quando estiver pronto.', 'sucesso', 'marcacoes');
        }
        flashRedirect('O trabalho foi cancelado porque recusou o valor final. A oficina entrará em contacto consigo.', 'sucesso', 'marcacoes', 'ver=canceladas');
    }
    flashRedirect($resExcesso['error'] ?? 'Não foi possível registar a resposta.', 'erro', 'marcacoes');
}

// Atualizar dados pessoais
if (isset($_POST['atualizar_dados_pessoais'])) {
    $mostrar_edicao_dados = true;
    // Nome e email ficam bloqueados nas configurações.
    $novo_nome = (string)($user['nome_utilizador'] ?? '');
    $novo_email = (string)($user['email'] ?? '');
    $novo_telefone = am_normalizar_telemovel((string) ($_POST['telefone'] ?? ''));
    $novo_nif = preg_replace('/\D+/', '', $_POST['nif'] ?? '');

    if ($tem_coluna_telefone && $novo_telefone !== '') {
        [$telOkPerfil, $msgTelPerfil] = am_validar_telemovel_portugues($novo_telefone);
        if (!$telOkPerfil) {
            $mensagem = $msgTelPerfil;
            $mensagem_tipo = 'erro';
        }
    } elseif ($tem_coluna_nif && $novo_nif !== '' && !validarNifPortuguesCfg($novo_nif)) {
        $mensagem = "NIF inválido.";
        $mensagem_tipo = 'erro';
    } elseif ($tem_coluna_nif && $novo_nif !== '') {
        [$nifUnicoPerfil, $msgNifUnicoPerfil] = am_validar_nif_unico($con, $novo_nif, ['utilizador' => $id_utilizador]);
        if (!$nifUnicoPerfil) {
            $mensagem = $msgNifUnicoPerfil;
            $mensagem_tipo = 'erro';
        }
    }

    if ($mensagem === '') {
        $sql = "UPDATE utilizador SET updated_at = NOW()";
        $tipos = "";
        $params = [];

        if ($tem_coluna_telefone) {
            $sql .= ", telefone = ?";
            $tipos .= "s";
            $params[] = $novo_telefone;
        }
        if ($tem_coluna_nif) {
            $sql .= ", nif = ?";
            $tipos .= "s";
            $params[] = $novo_nif;
        }

        $sql .= " WHERE id_utilizador = ?";
        $tipos .= "i";
        $params[] = $id_utilizador;

        $upd_user = $con->prepare($sql);
        if ($upd_user) {
            $upd_user->bind_param($tipos, ...$params);
            if ($upd_user->execute()) {
                $mensagem = "Dados pessoais atualizados com sucesso!";
                $mensagem_tipo = 'sucesso';
                if ($tem_coluna_telefone) $user['telefone'] = $novo_telefone;
                if ($tem_coluna_nif) $user['nif'] = $novo_nif;
            } else {
                $mensagem = "Erro ao atualizar dados pessoais: " . $upd_user->error;
                $mensagem_tipo = 'erro';
            }
            $upd_user->close();
        } else {
            $mensagem = "Erro ao preparar atualização de dados pessoais.";
            $mensagem_tipo = 'erro';
        }
    }

    if ($mensagem !== '') {
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'dados');
    }
}

// Buscar carros do utilizador
garantirColunaCarroPrincipal($con);
normalizarCarroPrincipalUsuario($con, $id_utilizador);
am_ensure_veiculo_perfil_tables($con);
$carros_stmt = $con->prepare("
    SELECT c.id_carro, c.marca, c.modelo, c.ano, c.matricula, c.is_principal, c.created_at,
           p.propulsao, p.motorizacao, p.vin, p.km_atual, p.consumo_l_100, p.autonomia_media_km,
           p.capacidade_deposito_litros, p.kwh_bateria, p.tipo_oleo, p.pneus_medida, p.pneus_pressao,
           p.data_ultima_inspecao, p.data_ultimo_servico, p.seguro_seguradora, p.seguro_apolice, p.seguro_validade,
           p.foto_relativa, p.notas_oficina, p.atualizado_por, p.atualizado_em
    FROM carro c
    LEFT JOIN carro_perfil p ON p.id_carro = c.id_carro
    WHERE c.id_utilizador = ? 
    ORDER BY c.is_principal DESC, c.created_at DESC
");
$carros_stmt->bind_param("i", $id_utilizador);
$carros_stmt->execute();
$carros_result = $carros_stmt->get_result();

// Garantir colunas extra de marcações (para não falhar o SELECT)
garantirColunasServicoMarcacao($con);
am_garantir_colunas_codigo_levantamento($con);

// Buscar todos os serviços do utilizador
$servicos_stmt = $con->prepare("
    SELECT s.id, s.tipo_servico, s.matricula, s.custo, s.data_registo,
           s.marca, s.modelo, s.data_desejada, s.hora_desejada,
           m.nome_mecanico, s.estado_do_trabalho, s.data_conclusao, s.numero_fatura,
           s.estado_orcamento, s.orcamento_aprovado, s.estado_orcamento_cliente, s.estado_excesso_orcamento_cliente,
           s.estado_pagamento, s.metodo_pagamento, s.subtotal_sem_iva, s.valor_iva, s.valor_total_com_iva, s.valor_pago_total, s.valor_em_divida, s.pedir_contribuinte_fatura, s.contribuinte_fatura,
           s.horas_trabalhadas, s.valor_hora, s.pecas_substituidas, s.fatura_linhas_json,
           s.plano_pronto, s.sla_prometido_horas, s.sla_inicio_at, s.sla_limite_at, s.sla_estado, s.sla_compensacao_tipo, s.sla_compensacao_valor,
           s.codigo_levantamento, s.codigo_levantamento_emitido_em, s.codigo_levantamento_usado_em,
           s.estado_marcacao, s.data_cancelamento, s.taxa_cancelamento, s.taxa_cancelamento_paga
    FROM servico s 
    LEFT JOIN mecanicos m ON s.mecanico_id = m.id_mecanico 
    WHERE s.email = ? 
    ORDER BY s.data_registo DESC
");
$servicos_stmt_fallback = false;
if (!$servicos_stmt) {
    // Fallback caso a BD ainda não tenha as colunas extra
    $servicos_stmt = $con->prepare("
        SELECT s.id, s.tipo_servico, s.matricula, s.custo, s.data_registo,
               '' AS marca, '' AS modelo, NULL AS data_desejada, '' AS hora_desejada,
               m.nome_mecanico, s.estado_do_trabalho, s.data_conclusao, s.numero_fatura,
               NULL AS estado_orcamento, NULL AS orcamento_aprovado, NULL AS estado_orcamento_cliente,
               NULL AS estado_pagamento, NULL AS metodo_pagamento, NULL AS subtotal_sem_iva, NULL AS valor_iva, NULL AS valor_total_com_iva, NULL AS valor_pago_total, NULL AS valor_em_divida, NULL AS pedir_contribuinte_fatura, NULL AS contribuinte_fatura,
               NULL AS plano_pronto, NULL AS sla_prometido_horas, NULL AS sla_inicio_at, NULL AS sla_limite_at, NULL AS sla_estado, NULL AS sla_compensacao_tipo, NULL AS sla_compensacao_valor,
               NULL AS codigo_levantamento, NULL AS codigo_levantamento_emitido_em, NULL AS codigo_levantamento_usado_em,
               NULL AS estado_marcacao, NULL AS data_cancelamento, NULL AS taxa_cancelamento, NULL AS taxa_cancelamento_paga
        FROM servico s 
        LEFT JOIN mecanicos m ON s.mecanico_id = m.id_mecanico 
        WHERE s.email = ? 
        ORDER BY s.data_registo DESC
    ");
    $servicos_stmt_fallback = true;
}
$servicos_stmt->bind_param("s", $user['email']);
$servicos_stmt->execute();
$servicos_result = $servicos_stmt->get_result();

// Calcular total gasto
$total_stmt = $con->prepare("SELECT SUM(custo) as total_gasto FROM servico WHERE email = ? AND (estado_do_trabalho = 'concluido' OR estado = 'concluido')");
$total_stmt->bind_param("s", $user['email']);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_gasto = $total_row['total_gasto'] ?? 0;

// Contar serviços por estado
$pendentes_stmt = $con->prepare("SELECT COUNT(*) as total FROM servico WHERE email = ? AND (estado_do_trabalho = 'Por Fazer' OR estado = 'pendente') AND LOWER(COALESCE(estado_marcacao, 'ativa')) <> 'cancelada' AND LOWER(COALESCE(estado_do_trabalho, '')) NOT LIKE '%cancel%'");
$pendentes_stmt->bind_param("s", $user['email']);
$pendentes_stmt->execute();
$pendentes_result = $pendentes_stmt->get_result();
$pendentes_row = $pendentes_result->fetch_assoc();
$total_pendentes = $pendentes_row['total'] ?? 0;

$concluidos_stmt = $con->prepare("SELECT COUNT(*) as total FROM servico WHERE email = ? AND (estado_do_trabalho = 'concluido' OR estado = 'concluido')");
$concluidos_stmt->bind_param("s", $user['email']);
$concluidos_stmt->execute();
$concluidos_result = $concluidos_stmt->get_result();
$concluidos_row = $concluidos_result->fetch_assoc();
$total_concluidos = $concluidos_row['total'] ?? 0;

$andamento_stmt = $con->prepare("SELECT COUNT(*) as total FROM servico WHERE email = ? AND (estado_do_trabalho = 'Em Andamento' OR estado = 'progresso')");
$andamento_stmt->bind_param("s", $user['email']);
$andamento_stmt->execute();
$andamento_result = $andamento_stmt->get_result();
$andamento_row = $andamento_result->fetch_assoc();
$total_andamento = $andamento_row['total'] ?? 0;

// VER FATURA
if (isset($_GET['ver_fatura'])) {
    $servico_id = intval($_GET['ver_fatura']);
    
    $fatura_stmt = $con->prepare("
        SELECT s.*, u.nome_utilizador, u.email, u.carro, m.nome_mecanico, m.especialidade
        FROM servico s 
        JOIN utilizador u ON s.email = u.email 
        LEFT JOIN mecanicos m ON s.mecanico_id = m.id_mecanico 
        WHERE s.id = ? AND s.email = ?
    ");
    $fatura_stmt->bind_param("is", $servico_id, $user['email']);
    $fatura_stmt->execute();
    $fatura_result = $fatura_stmt->get_result();
    $servico_fatura = $fatura_result->fetch_assoc();
    $fatura_stmt->close();
    
    if ($servico_fatura) {
        $syncFat = am_servico_sincronizar_divida_pagamento($con, $servico_id);
        if (is_array($syncFat)) {
            $servico_fatura = array_merge($servico_fatura, $syncFat);
        }
        $reparadoFat = am_servico_reparar_valores_fiscais_se_inconsistentes($con, $servico_id, $servico_fatura);
        if (is_array($reparadoFat)) {
            $servico_fatura = $reparadoFat;
        }
        $doc = am_fatura_documento_montar($con, $servico_fatura);
        $pagDoc = $doc['pagamento'] ?? [];
        $acoesFatura = [];
        if (!empty($pagDoc['precisa_pagar']) && (float) ($pagDoc['valor_divida'] ?? 0) > 0) {
            $acoesFatura[] = [
                'href' => 'pagamento_orcamento.php?servico_id=' . (int) $servico_id,
                'label' => 'Pagar trabalho (€' . number_format((float) $pagDoc['valor_divida'], 2, ',', ' ') . ')',
                'class' => 'fat-btn--primary',
            ];
        }
        $acoesFatura[] = ['onclick' => 'window.print()', 'label' => 'Imprimir fatura'];
        $acoesFatura[] = ['href' => 'configuracoes.php?seccao=marcacoes', 'label' => '← Voltar às marcações'];
        am_fatura_documento_emitir($doc, ['acoes' => $acoesFatura]);
        exit;
    }
}

// ATUALIZAR MÉTODO DE PAGAMENTO
if (isset($_POST['atualizar_metodo_pagamento'])) {
    $metodo_pagamento = $_POST['metodo_pagamento'];
    
    $update_stmt = $con->prepare("UPDATE utilizador SET metodo_pagamento_preferido = ? WHERE id_utilizador = ?");
    $update_stmt->bind_param("si", $metodo_pagamento, $id_utilizador);
    
    if ($update_stmt->execute()) {
        $mensagem = "Método de pagamento atualizado com sucesso!";
        $mensagem_tipo = 'sucesso';
        $user['metodo_pagamento_preferido'] = $metodo_pagamento;
    } else {
        $mensagem = "Erro ao atualizar método de pagamento.";
        $mensagem_tipo = 'erro';
    }
    $update_stmt->close();

    if (!empty($mensagem)) {
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'dados');
    }
}

// ADICIONAR CARRO
if (isset($_POST['adicionar_carro'])) {
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $ano = intval($_POST['ano']);
    [$matriculaOk, $matriculaErro, $matricula] = am_validar_matricula($_POST['matricula'] ?? '');

    if (empty($marca) || empty($modelo) || empty($ano) || empty($matricula)) {
        $mensagem = "Preencha todos os campos do carro.";
        $mensagem_tipo = 'erro';
    } elseif (!$matriculaOk) {
        $mensagem = $matriculaErro;
        $mensagem_tipo = 'erro';
    } elseif ($ano < 1900 || $ano > date('Y')) {
        $mensagem = "Ano do carro inválido.";
        $mensagem_tipo = 'erro';
    } else {
        [$matriculaUnica, $msgMatriculaUnica] = am_validar_matricula_unica($con, $matricula);
        if (!$matriculaUnica) {
            $mensagem = $msgMatriculaUnica;
            $mensagem_tipo = 'erro';
        } else {
            // Se for o primeiro carro do utilizador, fica principal automaticamente
            $count_stmt = $con->prepare("SELECT COUNT(*) as total FROM carro WHERE id_utilizador = ?");
            $count_stmt->bind_param("i", $id_utilizador);
            $count_stmt->execute();
            $count_res = $count_stmt->get_result();
            $count_row = $count_res->fetch_assoc();
            $total_carros = (int)($count_row['total'] ?? 0);
            $count_stmt->close();

            $is_principal = ($total_carros === 0) ? 1 : 0;

            $insert_stmt = $con->prepare("INSERT INTO carro (id_utilizador, marca, modelo, ano, matricula, is_principal, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("issisi", $id_utilizador, $marca, $modelo, $ano, $matricula, $is_principal);
            
            if ($insert_stmt->execute()) {
                $mensagem = "Carro adicionado com sucesso!";
                $mensagem_tipo = 'sucesso';

                $novo_id = $con->insert_id;
                if ($is_principal === 1) definirCarroPrincipalUsuario($con, $id_utilizador, (int)$novo_id);

                // Atualizar a lista de carros
                $carros_stmt->execute();
                $carros_result = $carros_stmt->get_result();
            } else {
                $mensagem = "Erro ao adicionar carro: " . $insert_stmt->error;
                $mensagem_tipo = 'erro';
            }
            $insert_stmt->close();
        }
    }

    if (!empty($mensagem)) {
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'veiculos');
    }
}

// DEFINIR CARRO PRINCIPAL
if (isset($_POST['definir_principal_carro']) || isset($_POST['definir_principal'])) {
    $id_carro = intval($_POST['id_carro'] ?? 0);
    if ($id_carro <= 0) {
        $mensagem = "Carro inválido.";
        $mensagem_tipo = 'erro';
    } else {
        $check_stmt = $con->prepare("SELECT id_carro FROM carro WHERE id_carro = ? AND id_utilizador = ?");
        $check_stmt->bind_param("ii", $id_carro, $id_utilizador);
        $check_stmt->execute();
        $res = $check_stmt->get_result();
        if ($res->num_rows === 0) {
            $mensagem = "Carro não encontrado ou não pertence ao utilizador.";
            $mensagem_tipo = 'erro';
        } else {
            definirCarroPrincipalUsuario($con, $id_utilizador, $id_carro);
            $mensagem = "Veículo principal atualizado com sucesso!";
            $mensagem_tipo = 'sucesso';
        }
        $check_stmt->close();
    }

    if (!empty($mensagem)) {
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'veiculos');
    }
}

// EDITAR CARRO
if (isset($_POST['editar_carro'])) {
    $id_carro = intval($_POST['id_carro'] ?? 0);
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ano = intval($_POST['ano'] ?? 0);
    [$matriculaOk, $matriculaErro, $matricula] = am_validar_matricula($_POST['matricula'] ?? '');

    if ($id_carro <= 0 || empty($marca) || empty($modelo) || empty($ano) || empty($matricula)) {
        $mensagem = "Preencha todos os campos para editar o carro.";
        $mensagem_tipo = 'erro';
    } elseif (!$matriculaOk) {
        $mensagem = $matriculaErro;
        $mensagem_tipo = 'erro';
    } elseif ($ano < 1900 || $ano > date('Y')) {
        $mensagem = "Ano do carro inválido.";
        $mensagem_tipo = 'erro';
    } else {
        $check_owner_stmt = $con->prepare("SELECT id_carro FROM carro WHERE id_carro = ? AND id_utilizador = ?");
        $check_owner_stmt->bind_param("ii", $id_carro, $id_utilizador);
        $check_owner_stmt->execute();
        $owner_result = $check_owner_stmt->get_result();

        if ($owner_result->num_rows === 0) {
            $mensagem = "Carro não encontrado ou não pertence ao utilizador.";
            $mensagem_tipo = 'erro';
        } else {
            [$matriculaUnica, $msgMatriculaUnica] = am_validar_matricula_unica($con, $matricula, $id_carro);
            if (!$matriculaUnica) {
                $mensagem = $msgMatriculaUnica;
                $mensagem_tipo = 'erro';
            } else {
                $update_stmt = $con->prepare("UPDATE carro SET marca = ?, modelo = ?, ano = ?, matricula = ? WHERE id_carro = ? AND id_utilizador = ?");
                $update_stmt->bind_param("ssisii", $marca, $modelo, $ano, $matricula, $id_carro, $id_utilizador);

                if ($update_stmt->execute()) {
                    $mensagem = "Carro atualizado com sucesso!";
                    $mensagem_tipo = 'sucesso';

                    $carros_stmt->execute();
                    $carros_result = $carros_stmt->get_result();
                } else {
                    $mensagem = "Erro ao atualizar carro: " . $update_stmt->error;
                    $mensagem_tipo = 'erro';
                }
                $update_stmt->close();
            }
        }
        $check_owner_stmt->close();
    }

    if (!empty($mensagem)) {
        $modalForm = null;
        if ($mensagem_tipo === 'erro') {
            $modalForm = [
                'marca' => $marca,
                'modelo' => $modelo,
                'ano' => $ano,
                'matricula' => $matricula,
            ];
        }
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'veiculos', 'open_car=' . (int) $id_carro, (int) $id_carro, 'dados', $modalForm);
    }
}

// GUARDAR PERFIL TÉCNICO DO VEÍCULO
if (isset($_POST['salvar_perfil_veiculo'])) {
    $id_carro_pf = (int) ($_POST['id_carro_perfil'] ?? 0);
    if ($id_carro_pf <= 0) {
        $mensagem = 'Veículo inválido.';
        $mensagem_tipo = 'erro';
    } else {
        $own = $con->prepare('SELECT id_carro FROM carro WHERE id_carro = ? AND id_utilizador = ? LIMIT 1');
        $own->bind_param('ii', $id_carro_pf, $id_utilizador);
        $own->execute();
        $okOwn = $own->get_result()->num_rows > 0;
        $own->close();
        if (!$okOwn) {
            $mensagem = 'Veículo não encontrado.';
            $mensagem_tipo = 'erro';
        } else {
            $hojeStr = date('Y-m-d');
            $insP = trim((string) ($_POST['data_ultima_inspecao'] ?? ''));
            $srvP = trim((string) ($_POST['data_ultimo_servico'] ?? ''));
            if (($insP !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $insP) && $insP > $hojeStr)
                || ($srvP !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $srvP) && $srvP > $hojeStr)) {
                $mensagem = 'A data da última inspeção e a do último serviço não podem ser posteriores ao dia de hoje.';
                $mensagem_tipo = 'erro';
            } else {
                $d = [
                    'propulsao' => $_POST['propulsao'] ?? '',
                    'motorizacao' => $_POST['motorizacao'] ?? '',
                    'vin' => $_POST['vin'] ?? '',
                    'km_atual' => $_POST['km_atual'] ?? '',
                    'data_ultima_inspecao' => $_POST['data_ultima_inspecao'] ?? '',
                    'data_ultimo_servico' => $_POST['data_ultimo_servico'] ?? '',
                ];
                if (!empty($_FILES['foto_veiculo']['tmp_name']) && is_uploaded_file($_FILES['foto_veiculo']['tmp_name'])) {
                    $mime = @mime_content_type($_FILES['foto_veiculo']['tmp_name']) ?: '';
                    $ext = (strpos($mime, 'png') !== false) ? 'png' : ((strpos($mime, 'webp') !== false) ? 'webp' : 'jpg');
                    if (!in_array($ext, ['jpg', 'png', 'webp'], true)) {
                        $ext = 'jpg';
                    }
                    $dir = __DIR__ . '/uploads/veiculos';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $nome = 'v_' . $id_utilizador . '_' . $id_carro_pf . '_' . time() . '.' . $ext;
                    $dest = $dir . '/' . $nome;
                    if (@move_uploaded_file($_FILES['foto_veiculo']['tmp_name'], $dest)) {
                        $d['foto_relativa'] = 'uploads/veiculos/' . $nome;
                    }
                }
                if (am_veiculo_perfil_upsert($con, $id_carro_pf, $d, 'cliente')) {
                    $mensagem = 'Perfil do veículo guardado.';
                    $mensagem_tipo = 'sucesso';
                    $carros_stmt->execute();
                    $carros_result = $carros_stmt->get_result();
                } else {
                    $det = am_veiculo_perfil_erro_store();
                    $mensagem = $det !== '' ? $det : 'Erro ao guardar o perfil do veículo.';
                    $mensagem_tipo = 'erro';
                }
            }
        }
    }
    if ($mensagem !== '') {
        flashRedirect($mensagem, $mensagem_tipo ?: 'erro', 'veiculos', 'open_car=' . (int) $id_carro_pf, (int) $id_carro_pf, 'perfil');
    }
}

// Determinar secção ativa (novo layout com 3 tabs)
$seccao = $_GET['seccao'] ?? 'dados';
if ($seccao === 'servicos' || $seccao === 'seguranca' || $seccao === 'pagamento' || $seccao === 'faturas' || $seccao === 'despesas') {
    $seccao = 'marcacoes';
}
if ($seccao === 'carro') {
    $seccao = 'veiculos';
}
$open_car_id = isset($_GET['open_car']) ? (int) $_GET['open_car'] : 0;
$mostrar_mensagem_global = $mensagem !== ''
    && !($mensagem_modal_car_id > 0 && $mensagem_modal_car_id === $open_car_id && $seccao === 'veiculos');
$notif_cfg = am_utilizador_notificacoes_metrics($con, $id_utilizador, false);
$notif_cfg_visivel = (int) ($notif_cfg['count'] ?? 0);
$notif_cfg_total = $notif_cfg_visivel;
$notif_cfg_tipos = is_array($notif_cfg['tipos'] ?? null) ? $notif_cfg['tipos'] : [];
$notif_cfg_sections = is_array($notif_cfg['sections'] ?? null)
    ? $notif_cfg['sections']
    : ['dados' => 0, 'veiculos' => 0, 'marcacoes' => 0];
$notif_cfg_resumo = [];
foreach ($notif_cfg_tipos as $tipoKey => $tipoN) {
    if ((int) $tipoN > 0) {
        $lbl = am_servico_notificacao_cliente_label((string) $tipoKey);
        if ($lbl !== '') {
            $notif_cfg_resumo[] = (int) $tipoN . '× ' . $lbl;
        }
    }
}
if ((int) ($notif_cfg_sections['veiculos'] ?? 0) > 0) {
    $notif_cfg_resumo[] = (int) $notif_cfg_sections['veiculos'] . '× Pedido de veículo pendente';
}
$notif_cfg_cards = [];
$notif_cfg_card_map = [
    'orcamento_pendente' => ['icon' => 'fas fa-file-invoice-dollar', 'section' => 'marcacoes', 'accent' => 'warn'],
    'excesso_orcamento' => ['icon' => 'fas fa-triangle-exclamation', 'section' => 'marcacoes', 'accent' => 'warn'],
    'pagamento_pendente' => ['icon' => 'fas fa-credit-card', 'section' => 'marcacoes', 'accent' => 'danger'],
    'pagamento_complemento' => ['icon' => 'fas fa-credit-card', 'section' => 'marcacoes', 'accent' => 'danger'],
    'carro_pronto' => ['icon' => 'fas fa-car-side', 'section' => 'marcacoes', 'accent' => 'success'],
];
foreach ($notif_cfg_tipos as $tipoKey => $tipoN) {
    $countTipo = (int) $tipoN;
    if ($countTipo <= 0) {
        continue;
    }
    $labelTipo = am_servico_notificacao_cliente_label((string) $tipoKey);
    if ($labelTipo === '') {
        continue;
    }
    $metaTipo = $notif_cfg_card_map[(string) $tipoKey] ?? ['icon' => 'fas fa-bell', 'section' => 'marcacoes', 'accent' => 'warn'];
    $notif_cfg_cards[] = [
        'count' => $countTipo,
        'label' => $labelTipo,
        'section' => $metaTipo['section'],
        'icon' => $metaTipo['icon'],
        'accent' => $metaTipo['accent'],
    ];
}
if ((int) ($notif_cfg_sections['veiculos'] ?? 0) > 0) {
    $notif_cfg_cards[] = [
        'count' => (int) $notif_cfg_sections['veiculos'],
        'label' => 'Pedido de veículo pendente',
        'section' => 'veiculos',
        'icon' => 'fas fa-car',
        'accent' => 'info',
    ];
}
$notif_cfg_section_pulses = is_array($notif_cfg['section_pulses'] ?? null)
    ? $notif_cfg['section_pulses']
    : ['dados' => '', 'veiculos' => '', 'marcacoes' => ''];

// ---------------- ALTERAR PASSWORD ----------------
if (isset($_POST['atual_password'], $_POST['nova_password'], $_POST['confirmar_password'])) {

    $atual_password = trim($_POST['atual_password']);
    $nova_password = trim($_POST['nova_password']);
    $confirmar_password = trim($_POST['confirmar_password']);

    if ($atual_password === '' || $nova_password === '' || $confirmar_password === '') {
        $mensagem = "Preenche todos os campos.";
        $mensagem_tipo = 'erro';
    }
    elseif (!password_verify($atual_password, $user['password'])) {
        $mensagem = "A password atual está incorreta.";
        $mensagem_tipo = 'erro';
    }
    elseif (strlen($nova_password) < 4) {
        $mensagem = "A nova password deve ter pelo menos 4 caracteres.";
        $mensagem_tipo = 'erro';
    }
    elseif ($nova_password !== $confirmar_password) {
        $mensagem = "As novas passwords não coincidem.";
        $mensagem_tipo = 'erro';
    }
    else {
        $hashed_password = password_hash($nova_password, PASSWORD_DEFAULT);
        $upd = $con->prepare("UPDATE utilizador SET password = ?, updated_at = NOW() WHERE id_utilizador = ?");
        $upd->bind_param("si", $hashed_password, $id_utilizador);

        if ($upd->execute()) {
            $mensagem = "Password alterada com sucesso!";
            $mensagem_tipo = 'sucesso';
            $user['password'] = $hashed_password;
        } else {
            $mensagem = "Erro ao alterar a password: " . $upd->error;
            $mensagem_tipo = 'erro';
        }
        $upd->close();
    }
}

// ---------------- EXCLUIR CONTA ----------------
if (isset($_POST['excluir_conta'])) {
    $password_eliminar = (string) ($_POST['password_eliminar_conta'] ?? '');

    if ($password_eliminar === '') {
        $mensagem = 'Introduza a password atual para eliminar a conta.';
        $mensagem_tipo = 'erro';
    } elseif (!password_verify($password_eliminar, $user['password'])) {
        $mensagem = 'Password incorreta. A conta não foi eliminada.';
        $mensagem_tipo = 'erro';
    } else {
        $resultadoEliminar = am_utilizador_eliminar_conta_completa(
            $con,
            $id_utilizador,
            (string) ($user['email'] ?? '')
        );
        if ($resultadoEliminar['ok']) {
            $_SESSION = [];
            if (session_id() !== '') {
                session_destroy();
            }
            header('Location: index.php?conta_eliminada=1');
            exit;
        }
        $mensagem = $resultadoEliminar['error'];
        $mensagem_tipo = 'erro';
    }
}

$servicos_stmt->close();
$total_stmt->close();
$pendentes_stmt->close();
$concluidos_stmt->close();
$andamento_stmt->close();
$carros_stmt->close();
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações - AUTOMANAGER</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="styleconfiguracoes.css">
<link rel="stylesheet" href="notificacoes.css?v=4">
</head>
<body>

<?php if (isset($_SESSION['temp_allowed']) && $_SESSION['temp_allowed'] === true && !isset($_SESSION['user'])): ?>
<!-- Timer de sessão temporária -->
<div id="temp-timer" style="position: fixed; top: 20px; right: 20px; background: rgba(255,69,0,0.9); color: white; padding: 12px 20px; border-radius: 50px; font-weight: 600; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 10px 25px -5px rgba(255,69,0,0.5); z-index: 9999; display: flex; align-items: center; gap: 10px;">
    <i class="fas fa-clock"></i>
    <span id="timer-display">5:00</span>
    <span style="font-size: 0.9em; opacity: 0.9;">restantes</span>
</div>

<script>
    // Timer regressivo
    window.tempoRestante = <?= isset($_SESSION['tempo_restante']) ? (int) $_SESSION['tempo_restante'] : (isset($_SESSION['tempo_limite']) ? (int) $_SESSION['tempo_limite'] : 120) ?>;
    const timerDisplay = document.getElementById('timer-display');
    const timerDiv = document.getElementById('temp-timer');

    function atualizarTimer() {
        if (window.tempoRestante <= 0) {
            window.location.href = 'login.php?expired=1';
            return;
        }
        
        let minutos = Math.floor(window.tempoRestante / 60);
        let segundos = window.tempoRestante % 60;
        
        timerDisplay.textContent = minutos + ':' + (segundos < 10 ? '0' : '') + segundos;
        
        // Mudar cor quando estiver a acabar
        if (window.tempoRestante <= 60) {
            timerDiv.style.background = 'rgba(255,165,0,0.9)';
        }
        if (window.tempoRestante <= 30) {
            timerDiv.style.background = 'rgba(255,0,0,0.9)';
            timerDiv.style.animation = 'pulse 1s infinite';
        }
        
        window.tempoRestante--;
    }

    // Atualizar a cada segundo
    setInterval(atualizarTimer, 1000);
    atualizarTimer(); // Chamada inicial

    // Adicionar animação pulse
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    `;
    document.head.appendChild(style);
</script>
<?php endif; ?>

<header class="cfg-topbar">
    <div class="cfg-topbar-inner">
        <a href="index.php" class="cfg-brand">AutoManager<span>.</span></a>
        <nav class="cfg-nav">
            <a href="index.php#home">Início</a>
            <a href="index.php#services">Serviços</a>
            <a href="index.php#book-form">Marcar</a>
            <a href="index.php#about">Sobre</a>
            <a href="index.php#contact">Contactos</a>
        </nav>
        <a href="configuracoes.php" class="cfg-profile-btn<?= $notif_cfg_visivel > 0 ? ' has-notif' : '' ?>" aria-label="<?= $notif_cfg_visivel > 0 ? 'Notificações e perfil' : 'Perfil' ?>" title="<?= $notif_cfg_visivel > 0 ? 'Notificações e perfil' : 'Perfil' ?>">
            <i class="fas <?= $notif_cfg_visivel > 0 ? 'fa-bell' : 'fa-user' ?>"></i>
            <span class="cfg-notif-pill<?= $notif_cfg_visivel > 0 ? ' is-on' : '' ?>" id="cfgProfileNotifPill"><?= $notif_cfg_visivel > 99 ? '99+' : (int) $notif_cfg_visivel ?></span>
        </a>
    </div>
</header>

<main class="cfg-wrap">
    <?php if ($mostrar_mensagem_global): ?>
        <div class="mensagem <?= $mensagem_tipo === 'erro' ? 'mensagem-erro' : 'mensagem-sucesso' ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <div class="cfg-tabs">
        <a href="?seccao=dados" class="cfg-tab <?= $seccao === 'dados' ? 'active' : '' ?><?= $notif_cfg_visivel > 0 && ((int) ($notif_cfg_sections['dados'] ?? 0)) > 0 ? ' cfg-tab--has-notif' : '' ?>">
            <i class="fas fa-user"></i> Dados Pessoais
            <span class="cfg-tab-badge<?= $notif_cfg_visivel > 0 && ((int) ($notif_cfg_sections['dados'] ?? 0)) > 0 ? '' : ' is-hidden' ?>" id="cfgTabBadgeDados"><?= ((int) ($notif_cfg_sections['dados'] ?? 0)) > 99 ? '99+' : (int) ($notif_cfg_sections['dados'] ?? 0) ?></span>
        </a>
        <a href="?seccao=veiculos" class="cfg-tab <?= $seccao === 'veiculos' ? 'active' : '' ?><?= $notif_cfg_visivel > 0 && ((int) ($notif_cfg_sections['veiculos'] ?? 0)) > 0 ? ' cfg-tab--has-notif' : '' ?>">
            <i class="fas fa-car"></i> Veículos
            <span class="cfg-tab-badge<?= $notif_cfg_visivel > 0 && ((int) ($notif_cfg_sections['veiculos'] ?? 0)) > 0 ? '' : ' is-hidden' ?>" id="cfgTabBadgeVeiculos"><?= ((int) ($notif_cfg_sections['veiculos'] ?? 0)) > 99 ? '99+' : (int) ($notif_cfg_sections['veiculos'] ?? 0) ?></span>
        </a>
        <a href="?seccao=marcacoes" class="cfg-tab <?= $seccao === 'marcacoes' ? 'active' : '' ?><?= $notif_cfg_visivel > 0 && ((int) ($notif_cfg_sections['marcacoes'] ?? 0)) > 0 ? ' cfg-tab--has-notif' : '' ?>">
            <i class="fas fa-calendar-check"></i> Minhas Marcações
            <span class="cfg-tab-badge<?= $notif_cfg_visivel > 0 && ((int) ($notif_cfg_sections['marcacoes'] ?? 0)) > 0 && $seccao !== 'marcacoes' ? '' : ' is-hidden' ?>" id="cfgTabBadgeMarcacoes"><?= ((int) ($notif_cfg_sections['marcacoes'] ?? 0)) > 99 ? '99+' : (int) ($notif_cfg_sections['marcacoes'] ?? 0) ?></span>
        </a>
    </div>

    <?php if ($notif_cfg_visivel > 0): ?>
    <div class="cfg-notif-banner is-visible" id="cfgNotifBanner" role="status">
        <div class="cfg-notif-banner__head">
            <span class="cfg-notif-banner__icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
            <div class="cfg-notif-banner__text">
                <strong><?= (int) $notif_cfg_visivel ?></strong>
                <?= $notif_cfg_visivel === 1 ? 'ação pendente' : 'ações pendentes' ?>
                <?php if ($notif_cfg_resumo !== []): ?>
                    <span class="cfg-notif-banner__summary"><?= htmlspecialchars(implode(' · ', array_slice($notif_cfg_resumo, 0, 4))) ?></span>
                <?php endif; ?>
            </div>
            <button type="button" class="cfg-notif-dismiss" id="cfgNotifDismiss" title="Marcar como lidas" aria-label="Marcar notificações como lidas">
                <i class="fas fa-check" aria-hidden="true"></i>
                <span>Marcar como lidas</span>
            </button>
        </div>
        <?php if ($notif_cfg_cards !== []): ?>
        <div class="cfg-notif-cards">
            <?php foreach ($notif_cfg_cards as $notifCard): ?>
                <?php
                    $cardSection = (string) ($notifCard['section'] ?? 'marcacoes');
                    $cardHref = '?seccao=' . rawurlencode($cardSection);
                    if ($cardSection === 'marcacoes') {
                        $cardHref .= '#cfgMarcacoesLista';
                    } elseif ($cardSection === 'veiculos') {
                        $cardHref .= '#cfgVeiculosLista';
                    }
                ?>
                <a class="cfg-notif-card cfg-notif-card--<?= htmlspecialchars((string) ($notifCard['accent'] ?? 'warn')) ?> cfg-notif-card--go" href="<?= htmlspecialchars($cardHref, ENT_QUOTES) ?>" data-notif-go="1">
                    <span class="cfg-notif-card__icon" aria-hidden="true"><i class="<?= htmlspecialchars((string) ($notifCard['icon'] ?? 'fas fa-bell')) ?>"></i></span>
                    <span class="cfg-notif-card__body">
                        <strong><?= (int) ($notifCard['count'] ?? 0) ?>x</strong>
                        <span><?= htmlspecialchars((string) ($notifCard['label'] ?? 'Notificação')) ?></span>
                    </span>
                    <span class="cfg-notif-card__go" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="cfg-notif-banner__actions">
            <?php if ((int) ($notif_cfg_sections['marcacoes'] ?? 0) > 0): ?>
            <a class="cfg-notif-banner__link cfg-notif-banner__link--go" href="?seccao=marcacoes#cfgMarcacoesLista" data-notif-go="1"><i class="fas fa-calendar-check"></i> Ver marcações</a>
            <?php endif; ?>
            <?php if ((int) ($notif_cfg_sections['veiculos'] ?? 0) > 0): ?>
            <a class="cfg-notif-banner__link cfg-notif-banner__link--ghost cfg-notif-banner__link--go" href="?seccao=veiculos#cfgVeiculosLista" data-notif-go="1"><i class="fas fa-car"></i> Ver veículos</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccao === 'dados'): ?>
        <section class="cfg-grid-2">
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <h3><i class="fas fa-user"></i> Dados Pessoais</h3>
                    <button type="button" id="btnEditarDados" class="btn btn-primary cfg-edit-btn">Editar</button>
                </div>
                <div class="cfg-data-list">
                    <div><span>Nome</span><strong><?= htmlspecialchars($user['nome_utilizador']) ?></strong></div>
                    <div><span>Email</span><strong><?= htmlspecialchars($user['email']) ?></strong></div>
                    <div><span>Telefone</span><strong><?= !empty($user['telefone']) ? htmlspecialchars($user['telefone']) : '--' ?></strong></div>
                    <div><span>NIF</span><strong><?= !empty($user['nif']) ? htmlspecialchars($user['nif']) : '--' ?></strong></div>
                </div>
                <form method="post" id="formEditarDados" class="car-form <?= $mostrar_edicao_dados ? '' : 'is-hidden' ?>" style="margin-top:12px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome</label>
                            <input type="text" name="nome_utilizador" value="<?= htmlspecialchars($user['nome_utilizador']) ?>" readonly disabled>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly disabled>
                        </div>
                    </div>
                    <p style="margin-top:-2px;color:#737373;font-size:12px;">Nome e email não podem ser alterados nesta área.</p>
                    <?php if ($tem_coluna_telefone || $tem_coluna_nif): ?>
                        <div class="form-row">
                            <?php if ($tem_coluna_telefone): ?>
                                <div class="form-group">
                                    <label>Telefone</label>
                                    <input type="text" class="am-telemovel-input" name="telefone" inputmode="numeric" maxlength="11" autocomplete="tel" placeholder="912 345 678" value="<?= htmlspecialchars(am_formatar_telemovel((string) ($user['telefone'] ?? ''))) ?>">
                                </div>
                            <?php endif; ?>
                            <?php if ($tem_coluna_nif): ?>
                                <div class="form-group">
                                    <label>NIF</label>
                                    <input type="text" class="am-nif-input" name="nif" inputmode="numeric" maxlength="9" placeholder="123456789" autocomplete="off" value="<?= htmlspecialchars($user['nif'] ?? '') ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="cfg-actions-row">
                        <button type="submit" name="atualizar_dados_pessoais" class="btn btn-primary">Guardar Dados Pessoais</button>
                        <button type="button" id="btnCancelarEdicao" class="btn">Cancelar</button>
                    </div>
                </form>
                <div class="cfg-actions-row">
                    <a href="logout.php" class="btn btn-danger">Terminar Sessão</a>
                </div>
            </div>

            <div class="cfg-card">
                <h3><i class="fas fa-lock"></i> Alterar Password</h3>
                <form method="post" class="password-form">
                    <div class="form-group">
                        <label>Password Atual</label>
                        <input type="password" name="atual_password" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nova Password</label>
                            <input type="password" name="nova_password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirmar Nova</label>
                            <input type="password" name="confirmar_password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Atualizar Password</button>
                </form>
            </div>
        </section>

        <section class="cfg-card cfg-card--danger cfg-delete-account">
            <h3><i class="fas fa-user-slash"></i> Eliminar conta</h3>
            <p class="cfg-delete-account__warn">
                Esta ação é <strong>permanente</strong> e remove <strong>todos os seus dados</strong> na plataforma:
                marcações e serviços (incluindo histórico), veículos, perfis, chat, passaporte digital, avaliações e a conta de acesso.
            </p>
            <form method="post" id="excluirContaForm" class="cfg-confirm-form cfg-delete-account__form" action="configuracoes.php?seccao=dados" data-confirm-title="Eliminar conta?" data-confirm-msg="Tem a certeza? Serão apagados todos os serviços, veículos, chat e dados da conta. Esta ação não pode ser revertida.">
                <input type="hidden" name="excluir_conta" value="1">
                <div class="form-group">
                    <label for="password_eliminar_conta">Password atual</label>
                    <input type="password" id="password_eliminar_conta" name="password_eliminar_conta" required autocomplete="current-password" placeholder="Confirme com a sua password">
                </div>
                <button type="submit" class="btn btn-danger btn-full">Eliminar a minha conta</button>
            </form>
        </section>
    <?php elseif ($seccao === 'veiculos'): ?>
        <section class="cfg-card" id="cfgVeiculosLista">
            <div class="cfg-card-head">
                <h3><i class="fas fa-car"></i> Meus Veículos</h3>
            </div>
            <form method="post" class="car-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Marca *</label>
                        <select name="marca" class="cfg-marca-select" required>
                            <?php am_render_marca_options($am_marcas); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modelo *</label>
                        <select name="modelo" class="cfg-modelo-select" required disabled>
                            <option value="">Selecione marca...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ano *</label>
                        <input type="number" name="ano" min="1900" max="<?= date('Y') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Matrícula *</label>
                        <input type="text" name="matricula" placeholder="AA-00-AA" maxlength="8" title="Matrícula portuguesa: AA-00-AA, 00-AA-00 ou 00-00-AA. Cada matrícula só pode existir uma vez no sistema." required style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="cfg-actions-row">
                    <button type="submit" name="adicionar_carro" class="btn btn-primary">Guardar Veículo</button>
                </div>
            </form>

            <?php if ($carros_result->num_rows > 0): ?>
                <div class="carros-grid">
                    <?php while ($carro = $carros_result->fetch_assoc()):
                        $resumoDelUi = am_carro_resumo_eliminacao($con, $id_utilizador, (int) $carro['id_carro'], (string) $user['email']);
                        $msgConfirmDel = (int) $resumoDelUi['trabalhos_ativos'] > 0
                            ? 'Este veículo tem ' . (int) $resumoDelUi['trabalhos_ativos'] . ' marcação(ões) ativa(s) que serão canceladas. Tem a certeza que quer eliminar este carro?'
                            : 'Tem a certeza que quer eliminar este carro? Será removido da base de dados e deixará de aparecer em todo o site.';
                        ?>
                        <div class="carro-item" data-carro-id="<?= (int) $carro['id_carro'] ?>">
                            <div class="carro-header">
                                <h4><?= htmlspecialchars($carro['marca']) ?> <?= htmlspecialchars($carro['modelo']) ?></h4>
                                <div class="cfg-vehicle-actions">
                                    <button type="button" class="btn cfg-btn-edit-vehicle" data-target="panelCarro<?= (int)$carro['id_carro'] ?>">
                                        <i class="fas fa-pen"></i> Editar
                                    </button>
                                    <form method="post" action="configuracoes.php?seccao=veiculos" class="cfg-confirm-form eliminar-carro-form" data-confirm-title="Eliminar veículo?" data-confirm-msg="<?= htmlspecialchars($msgConfirmDel) ?>">
                                        <input type="hidden" name="eliminar_carro" value="1">
                                        <input type="hidden" name="id_carro" value="<?= (int)$carro['id_carro'] ?>">
                                        <button type="submit" class="btn btn-eliminar" aria-label="Eliminar veículo">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php if ((int) $resumoDelUi['trabalhos_ativos'] > 0): ?>
                                <p class="cfg-carro-taxa-hint"><i class="fas fa-info-circle"></i> <?= (int) $resumoDelUi['trabalhos_ativos'] ?> marcação(ões) ativa(s) serão canceladas ao eliminar.</p>
                            <?php endif; ?>
                            <div class="carro-info">
                                <p><strong>Ano:</strong> <?= $carro['ano'] ?></p>
                                <p><strong>Matrícula:</strong> <span class="matricula"><?= htmlspecialchars($carro['matricula']) ?></span></p>
                            </div>

                            <div class="cfg-actions-row" style="margin-top:8px;">
                                <form method="post">
                                    <input type="hidden" name="id_carro" value="<?= (int)$carro['id_carro'] ?>">
                                    <?php if (!empty($carro['is_principal'])): ?>
                                        <button type="button" class="btn" style="opacity:.85; cursor:default;">Principal</button>
                                    <?php else: ?>
                                        <button type="submit" name="definir_principal" class="btn">Definir principal</button>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <?php
                                $cid = (int) $carro['id_carro'];
                                $pf_prop = (string) ($carro['propulsao'] ?? '');
                                $pf_vin = (string) ($carro['vin'] ?? '');
                                $hojePerfilUi = date('Y-m-d');
                                $valInsp = !empty($carro['data_ultima_inspecao']) ? substr((string) $carro['data_ultima_inspecao'], 0, 10) : '';
                                if ($valInsp !== '' && strcmp($valInsp, $hojePerfilUi) > 0) {
                                    $valInsp = $hojePerfilUi;
                                }
                                $valSrv = !empty($carro['data_ultimo_servico']) ? substr((string) $carro['data_ultimo_servico'], 0, 10) : '';
                                if ($valSrv !== '' && strcmp($valSrv, $hojePerfilUi) > 0) {
                                    $valSrv = $hojePerfilUi;
                                }
                            ?>

                            <?php
                            $modalAberto = ($open_car_id > 0 && $open_car_id === (int) $carro['id_carro']);
                            $modalTemMsg = $modalAberto
                                && $mensagem_modal_car_id === (int) $carro['id_carro']
                                && $mensagem !== '';
                            $modalMsgDados = $modalTemMsg && $mensagem_modal_secao === 'dados';
                            $modalMsgPerfil = $modalTemMsg && $mensagem_modal_secao === 'perfil';
                            $editMarca = ($modalMsgDados && isset($mensagem_modal_form['marca'])) ? (string) $mensagem_modal_form['marca'] : (string) ($carro['marca'] ?? '');
                            $editModelo = ($modalMsgDados && isset($mensagem_modal_form['modelo'])) ? (string) $mensagem_modal_form['modelo'] : (string) ($carro['modelo'] ?? '');
                            $editAno = ($modalMsgDados && isset($mensagem_modal_form['ano'])) ? (int) $mensagem_modal_form['ano'] : (int) ($carro['ano'] ?? 0);
                            $editMatricula = ($modalMsgDados && isset($mensagem_modal_form['matricula'])) ? (string) $mensagem_modal_form['matricula'] : (string) ($carro['matricula'] ?? '');
                            ?>
                            <div id="panelCarro<?= (int)$carro['id_carro'] ?>" class="cfg-vehicle-modal<?= $modalAberto ? '' : ' is-hidden' ?>" aria-hidden="<?= $modalAberto ? 'false' : 'true' ?>">
                                <button type="button" class="cfg-vehicle-modal__backdrop" data-cfg-modal-close aria-label="Fechar"></button>
                                <div class="cfg-vehicle-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cfg-veh-modal-title-<?= (int)$carro['id_carro'] ?>" tabindex="-1">
                                    <header class="cfg-vehicle-modal__header">
                                        <span class="cfg-vehicle-modal__grab" aria-hidden="true"></span>
                                        <div class="cfg-vehicle-modal__header-main">
                                            <div class="cfg-vehicle-modal__head-text">
                                                <h3 class="cfg-vehicle-modal__title" id="cfg-veh-modal-title-<?= (int)$carro['id_carro'] ?>">Editar veículo</h3>
                                                <p class="cfg-vehicle-modal__meta"><?= htmlspecialchars(trim((string)$carro['marca'] . ' ' . (string)$carro['modelo'])) ?> · <span class="cfg-vehicle-modal__plate"><?= htmlspecialchars((string)$carro['matricula']) ?></span></p>
                                            </div>
                                            <button type="button" class="cfg-vehicle-modal__close" data-cfg-modal-close aria-label="Fechar"><i class="fas fa-times" aria-hidden="true"></i></button>
                                        </div>
                                        <p class="cfg-vehicle-modal__hint">Cada botão <strong>Guardar</strong> grava apenas a secção onde está.</p>
                                    </header>
                                    <div class="cfg-vehicle-modal__body">
                            <form method="post" id="editCarro<?= (int)$carro['id_carro'] ?>" class="cfg-vehicle-edit cfg-vehicle-edit--in-modal">
                                <input type="hidden" name="id_carro" value="<?= (int)$carro['id_carro'] ?>">
                                <p class="cfg-vehicle-modal__section-label"><i class="fas fa-car" aria-hidden="true"></i> Dados do veículo</p>
                                <?php if ($modalMsgDados): ?>
                                    <div class="cfg-vehicle-modal__alert cfg-vehicle-modal__alert--<?= $mensagem_tipo === 'erro' ? 'erro' : 'ok' ?>" role="alert"><?= htmlspecialchars($mensagem) ?></div>
                                <?php endif; ?>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Marca</label>
                                        <select name="marca" class="cfg-marca-select" required>
                                            <?php am_render_marca_options($am_marcas, $editMarca); ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Modelo</label>
                                        <select name="modelo" class="cfg-modelo-select" data-current-model="<?= htmlspecialchars($editModelo) ?>" required>
                                            <option value="">Selecione marca...</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Ano</label>
                                        <input type="number" name="ano" min="1900" max="<?= date('Y') ?>" value="<?= (int) $editAno ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Matrícula</label>
                                        <input type="text" name="matricula" value="<?= htmlspecialchars($editMatricula) ?>" maxlength="8" title="Matrícula portuguesa: AA-00-AA, 00-AA-00 ou 00-00-AA" style="text-transform: uppercase;" required<?= $modalMsgDados && $mensagem_tipo === 'erro' ? ' aria-invalid="true"' : '' ?>>
                                    </div>
                                </div>
                                <div class="cfg-actions-row cfg-vehicle-modal__actions">
                                    <button type="submit" name="editar_carro" class="btn btn-primary">Guardar dados do veículo</button>
                                    <button type="button" class="btn cfg-btn-cancel-vehicle" data-target="panelCarro<?= (int)$carro['id_carro'] ?>">Cancelar</button>
                                </div>
                            </form>

                            <form method="post" enctype="multipart/form-data" class="cfg-perfil-veiculo cfg-perfil-veiculo--in-modal">
                                <input type="hidden" name="id_carro_perfil" value="<?= $cid ?>">
                                <p class="cfg-vehicle-modal__section-label"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Perfil técnico</p>
                                <?php if ($modalMsgPerfil): ?>
                                    <div class="cfg-vehicle-modal__alert cfg-vehicle-modal__alert--<?= $mensagem_tipo === 'erro' ? 'erro' : 'ok' ?>" role="alert"><?= htmlspecialchars($mensagem) ?></div>
                                <?php endif; ?>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Combustível</label>
                                        <select name="propulsao" id="cfgPropulsao<?= (int)$cid ?>" class="cfg-propulsao-native">
                                            <option value="">—</option>
                                            <?php foreach (['gasolina' => 'Gasolina', 'gasoleo' => 'Gasóleo', 'gpl' => 'GPL', 'hibrido' => 'Híbrido', 'eletrico' => 'Elétrico', 'hibrido_plugin' => 'Híbrido plug-in', 'outro' => 'Outro'] as $pv => $pl): ?>
                                                <option value="<?= htmlspecialchars($pv) ?>" <?= ($pf_prop === $pv) ? 'selected' : '' ?>><?= htmlspecialchars($pl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="cfg-fuel-picker" data-select-id="cfgPropulsao<?= (int)$cid ?>">
                                            <button type="button" class="cfg-fuel-picker__trigger" data-fuel-trigger>
                                                <span data-fuel-trigger-text>Selecionar</span>
                                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                            </button>
                                            <div class="cfg-fuel-picker__panel is-hidden" data-fuel-panel>
                                                <div class="cfg-fuel-picker__header">
                                                    <strong>Combustível</strong>
                                                    <button type="button" class="cfg-fuel-picker__clear" data-fuel-clear>Limpar</button>
                                                </div>
                                                <div class="cfg-fuel-picker__list" role="listbox" aria-label="Escolha o combustível">
                                                    <?php foreach (['gasolina' => 'Gasolina', 'gasoleo' => 'Gasóleo', 'gpl' => 'GPL', 'hibrido' => 'Híbrido', 'eletrico' => 'Elétrico', 'hibrido_plugin' => 'Híbrido Plug-In', 'outro' => 'Outro'] as $pv => $pl): ?>
                                                        <button type="button" class="cfg-fuel-picker__item" data-fuel-value="<?= htmlspecialchars($pv) ?>">
                                                            <span><?= htmlspecialchars($pl) ?></span>
                                                            <span class="cfg-fuel-picker__box" aria-hidden="true"></span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="button" class="cfg-fuel-picker__apply" data-fuel-apply>Aplicar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Motorização</label>
                                        <input type="text" name="motorizacao" value="<?= htmlspecialchars((string)($carro['motorizacao'] ?? '')) ?>" placeholder="ex.: 1.5 dCi 110 cv">
                                    </div>
                                    <div class="form-group">
                                        <label>VIN / chassis</label>
                                        <input type="text" name="vin" value="<?= htmlspecialchars($pf_vin) ?>" maxlength="32" style="text-transform:uppercase;">
                                        <?php am_render_bloco_ajuda_vin('c' . (string) $cid); ?>
                                    </div>
                                    <div class="form-group">
                                        <label>Quilómetros atuais</label>
                                        <input type="number" name="km_atual" min="0" step="1" value="<?= isset($carro['km_atual']) && $carro['km_atual'] !== null ? (int)$carro['km_atual'] : '' ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Última inspeção periódica</label>
                                        <input type="date" name="data_ultima_inspecao" value="<?= $valInsp !== '' ? htmlspecialchars($valInsp) : '' ?>" max="<?= htmlspecialchars($hojePerfilUi) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Último serviço (geral)</label>
                                        <input type="date" name="data_ultimo_servico" value="<?= $valSrv !== '' ? htmlspecialchars($valSrv) : '' ?>" max="<?= htmlspecialchars($hojePerfilUi) ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Foto do carro</label>
                                        <input type="file" name="foto_veiculo" accept="image/jpeg,image/png,image/webp">
                                        <?php if (!empty($carro['foto_relativa']) && is_file(__DIR__ . '/' . $carro['foto_relativa'])): ?>
                                            <div style="margin-top:8px;"><img src="<?= htmlspecialchars($carro['foto_relativa']) ?>" alt="" style="max-width:100%;max-height:140px;border-radius:8px;border:1px solid rgba(255,255,255,.12);"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($carro['atualizado_em'])): ?>
                                    <p style="font-size:12px;color:#a1a1aa;margin:0 0 10px;">Última atualização do perfil: <?= date('d/m/Y H:i', strtotime((string)$carro['atualizado_em'])) ?><?= !empty($carro['atualizado_por']) ? ' · ' . (($carro['atualizado_por'] === 'oficina') ? 'Oficina' : 'Cliente') : '' ?></p>
                                <?php endif; ?>
                                <div class="cfg-actions-row cfg-vehicle-modal__actions">
                                    <button type="submit" name="salvar_perfil_veiculo" class="btn btn-primary">Guardar perfil técnico</button>
                                </div>
                            </form>
                            <form method="post" action="configuracoes.php?seccao=veiculos" class="cfg-confirm-form cfg-vehicle-modal__delete eliminar-carro-form" data-confirm-title="Eliminar veículo?" data-confirm-msg="<?= htmlspecialchars($msgConfirmDel) ?>">
                                <input type="hidden" name="eliminar_carro" value="1">
                                <input type="hidden" name="id_carro" value="<?= (int)$carro['id_carro'] ?>">
                                <button type="submit" class="btn btn-danger btn-full">
                                    <i class="fas fa-trash"></i> Eliminar veículo
                                </button>
                            </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3><i class="fas fa-car-side"></i> Nenhum veículo registado</h3>
                    <p>Adicione o seu primeiro carro!</p>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="cfg-card" id="cfgMarcacoesLista">
            <div class="cfg-marcacoes-head">
                <div>
                    <h3><i class="fas fa-calendar-check"></i> Minhas Marcações</h3>
                    <p class="cfg-subtitle">Consulte e acompanhe o estado das suas marcações</p>
                    <p class="cfg-cancel-policy">
                        <i class="fas fa-info-circle"></i>
                        Pode cancelar marcações pendentes gratuitamente a qualquer momento.
                    </p>
                </div>
                <a class="btn btn-primary" href="index.php#book-form"><i class="fas fa-plus"></i> Nova marcação</a>
            </div>

            <div class="cfg-kpi-row">
                <div class="cfg-kpi">
                    <div class="cfg-kpi-icon"><i class="fas fa-list"></i></div>
                    <div class="cfg-kpi-meta">
                        <div class="cfg-kpi-value"><?= $total_pendentes + $total_andamento + $total_concluidos ?></div>
                        <div class="cfg-kpi-label">Total</div>
                    </div>
                </div>
                <div class="cfg-kpi">
                    <div class="cfg-kpi-icon"><i class="fas fa-clock"></i></div>
                    <div class="cfg-kpi-meta">
                        <div class="cfg-kpi-value"><?= $total_pendentes ?></div>
                        <div class="cfg-kpi-label">Pendentes</div>
                    </div>
                </div>
                <div class="cfg-kpi">
                    <div class="cfg-kpi-icon"><i class="fas fa-wrench"></i></div>
                    <div class="cfg-kpi-meta">
                        <div class="cfg-kpi-value"><?= $total_andamento ?></div>
                        <div class="cfg-kpi-label">Em andamento</div>
                    </div>
                </div>
                <div class="cfg-kpi">
                    <div class="cfg-kpi-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="cfg-kpi-meta">
                        <div class="cfg-kpi-value"><?= $total_concluidos ?></div>
                        <div class="cfg-kpi-label">Concluídas</div>
                    </div>
                </div>
            </div>

            <div class="cfg-filters">
                <div class="cfg-filter">
                    <label for="fStatus"><i class="fas fa-filter"></i> Status</label>
                    <select id="fStatus">
                        <option value="todos">Todos</option>
                        <option value="pendente">Pendentes</option>
                        <option value="andamento">Em andamento</option>
                        <option value="concluido">Concluídas</option>
                        <option value="cancelada">Canceladas</option>
                    </select>
                </div>
                <div class="cfg-filter">
                    <label for="fOrdenar"><i class="fas fa-sort"></i> Ordenar</label>
                    <select id="fOrdenar">
                        <option value="recentes">Mais recentes</option>
                        <option value="antigas">Mais antigas</option>
                    </select>
                </div>
            </div>

            <?php
            $servicos_rows = [];
            while ($rowSrv = $servicos_result->fetch_assoc()) {
                if (am_servico_tem_fecho_mecanico($rowSrv) || am_servico_esta_concluido($rowSrv)) {
                    $reparadoSrv = am_servico_reparar_valores_fiscais_se_inconsistentes($con, (int) $rowSrv['id'], $rowSrv);
                    if (is_array($reparadoSrv)) {
                        $rowSrv = array_merge($rowSrv, $reparadoSrv);
                    }
                }
                $servicos_rows[] = $rowSrv;
            }
            $total_canceladas_lista = 0;
            foreach ($servicos_rows as $rowSrv) {
                if (am_marcacao_esta_cancelada($rowSrv)) {
                    $total_canceladas_lista++;
                }
            }
            $total_ativas_lista = count($servicos_rows) - $total_canceladas_lista;
            $ver_canceladas_inicial = (($_GET['ver'] ?? '') === 'canceladas');
            ?>
            <?php if (count($servicos_rows) > 0): ?>
                <div id="cfgMarcacoesAtivasWrap" class="cfg-marcacoes-ativas-wrap"<?= $ver_canceladas_inicial ? ' style="display:none;"' : '' ?>>
                <?php if ($total_ativas_lista === 0): ?>
                    <div class="cfg-empty cfg-empty-inline">
                        <p>Não tem marcações ativas. Use o filtro «Canceladas» para ver o histórico.</p>
                    </div>
                <?php endif; ?>
                <div class="servicos-lista" id="listaMarcacoes">
                    <?php foreach ($servicos_rows as $servico): ?>
                        <?php if (am_marcacao_esta_cancelada($servico)) { continue; } ?>
                        <?php
                        $estado_raw = $servico['estado_do_trabalho'] ?? ($servico['estado'] ?? 'pendente');
                        $estado_norm = strtolower(trim($estado_raw));
                        $estado_key = 'pendente';
                        if (strpos($estado_norm, 'and') !== false || strpos($estado_norm, 'progres') !== false) $estado_key = 'andamento';
                        if (strpos($estado_norm, 'concl') !== false) $estado_key = 'concluido';
                        $chk_cancel_ui = am_marcacao_pode_cancelar($servico);
                        $pode_cancelar_marcacao = $chk_cancel_ui['ok'];
                        $orcamento_estado = strtolower(trim((string)($servico['estado_orcamento'] ?? '')));
                        $orcamento_cliente = strtolower(trim((string)($servico['estado_orcamento_cliente'] ?? '')));
                        $estado_pagamento_srv = strtolower(trim((string)($servico['estado_pagamento'] ?? 'pendente')));
                        $pode_ver_fatura = ($estado_pagamento_srv === 'pago' || $estado_key === 'concluido' || !empty($servico['numero_fatura']));
                        $mostrar_decisao_orcamento = ($orcamento_estado === 'aprovado' && !am_orcamento_cliente_aceite($orcamento_cliente) && $orcamento_cliente !== 'recusado');
                        $data_ts = strtotime($servico['data_registo']);
                        $sla_horas_srv = (int)($servico['sla_prometido_horas'] ?? 0);
                        $sla_estado_srv = strtolower(trim((string)($servico['sla_estado'] ?? 'nao_aplicavel')));
                        $sla_limite_srv = (string)($servico['sla_limite_at'] ?? '');
                        $sla_comp_valor = (float)($servico['sla_compensacao_valor'] ?? 0);
                        $tem_sla_premium = $sla_horas_srv > 0;
                        if ($tem_sla_premium && $sla_estado_srv === 'ativo' && $sla_limite_srv !== '' && strtotime($sla_limite_srv) < time()) {
                            $baseComp = (float)($servico['orcamento_aprovado'] ?? $servico['custo'] ?? 0);
                            $sla_comp_valor = round(min(30, $baseComp * 0.10), 2);
                            $upSla = $con->prepare("UPDATE servico SET sla_estado = 'atrasado', sla_compensacao_tipo = 'desconto', sla_compensacao_valor = ? WHERE id = ? AND email = ?");
                            if ($upSla) {
                                $upSla->bind_param("dis", $sla_comp_valor, $servico['id'], $user['email']);
                                $upSla->execute();
                                $upSla->close();
                            }
                            $sla_estado_srv = 'atrasado';
                        }
                        $servicoConcluidoUi = ($estado_key === 'concluido');
                        if ($servicoConcluidoUi && am_servico_tem_fecho_json_mecanico($servico)) {
                            $syncPag = am_servico_sincronizar_divida_pagamento($con, (int) $servico['id']);
                            if (is_array($syncPag)) {
                                $servico = array_merge($servico, $syncPag);
                            }
                        }
                        $valorPagoTotalUi = am_servico_valor_ja_pago($servico);
                        $precisaPagarTrabalhoUi = am_servico_precisa_pagar_trabalho_mecanico($servico);
                        $precisaPagarUi = $servicoConcluidoUi && $precisaPagarTrabalhoUi;
                        $aguardaFaturaFinalUi = am_servico_aguarda_fatura_final_mecanico($servico);
                        $totalTrabalhoUi = am_servico_total_fatura_final($servico);
                        $orcamentoEstimadoUi = am_servico_valor_orcamento_aprovado($servico);
                        $valorEmDividaUi = am_servico_valor_trabalho_mecanico_a_pagar($servico);
                        $excessoPendenteUi = am_servico_excesso_orcamento_pendente($servico);
                        $excessoRecusadoUi = am_servico_excesso_orcamento_recusado($servico);
                        $excessoAceiteUi = am_servico_excesso_orcamento_aceite($servico);
                        $cmpExcessoUi = $excessoPendenteUi ? am_servico_excesso_orcamento_comparacao($servico) : null;
                        if ($excessoPendenteUi) {
                            $precisaPagarUi = false;
                        }
                        $codigoLevantamentoUi = trim((string) ($servico['codigo_levantamento'] ?? ''));
                        $codigoLevantamentoFmtUi = am_codigo_levantamento_formatado($codigoLevantamentoUi);
                        $codigoLevantamentoUsadoUi = !empty($servico['codigo_levantamento_usado_em']);
                        $precoCardUi = am_servico_valor_exibicao_site($servico);
                        ?>
                        <div class="cfg-servico" data-status="<?= $estado_key ?>" data-date="<?= (int)$data_ts ?>">
                            <div class="cfg-servico-top">
                                <div class="cfg-servico-head">
                                    <div class="cfg-servico-title">
                                        <strong><?= htmlspecialchars($servico['tipo_servico']) ?></strong>
                                        <span class="cfg-pill cfg-pill-<?= $estado_key ?>"><?= htmlspecialchars($estado_raw) ?></span>
                                        <?php
                                        $notifTipoSrv = am_servico_notificacao_cliente_tipo($servico);
                                        if (in_array($notifTipoSrv, ['pagamento_pendente', 'pagamento_complemento'], true) && !$servicoConcluidoUi) {
                                            $notifTipoSrv = null;
                                        }
                                        if ($notifTipoSrv !== null):
                                        ?>
                                        <span class="cfg-servico-alert"><i class="fas fa-bell" aria-hidden="true"></i><?= htmlspecialchars(am_servico_notificacao_cliente_label($notifTipoSrv)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cfg-servico-meta">
                                        <span><?= htmlspecialchars($servico['matricula']) ?></span>
                                        <?php if (!empty($servico['marca']) || !empty($servico['modelo'])): ?>
                                            <span><?= htmlspecialchars(trim(($servico['marca'] ?? '') . ' ' . ($servico['modelo'] ?? ''))) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($servico['data_desejada'])): ?>
                                            <span><?= date('d/m/Y', strtotime($servico['data_desejada'])) ?><?= !empty($servico['hora_desejada']) ? ' · ' . htmlspecialchars($servico['hora_desejada']) : '' ?></span>
                                        <?php else: ?>
                                            <span><?= date('d/m/Y H:i', $data_ts) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="cfg-servico-price" title="<?= $servicoConcluidoUi && $totalTrabalhoUi > 0.009 ? 'Total do trabalho (c/ IVA)' : ($orcamentoEstimadoUi > 0.009 ? 'Orçamento aprovado (s/ IVA)' : 'Valor') ?>">€<?= number_format($precoCardUi, 2, ',', ' ') ?></div>
                            </div>
                            <?php if ($tem_sla_premium): ?>
                                <p class="cfg-servico-note cfg-servico-note--warn">
                                    Plano premium <?= (int)$sla_horas_srv ?>h<?php if (!empty($sla_limite_srv)): ?> · até <?= date('d/m/Y H:i', strtotime($sla_limite_srv)) ?><?php endif; ?>
                                    <?php if ($sla_estado_srv === 'atrasado'): ?>
                                        · Compensação €<?= number_format($sla_comp_valor, 2, ',', ' ') ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($mostrar_decisao_orcamento): ?>
                                <?php
                                $orcItensUi = am_servico_orcamento_itens_list($con, (int) $servico['id']);
                                $orcCatLabel = static function (string $c): string {
                                    $c = strtolower($c);
                                    if ($c === 'peca') {
                                        return 'Peça';
                                    }
                                    if ($c === 'mao_obra') {
                                        return 'Mão de obra';
                                    }
                                    if ($c === 'taxa') {
                                        return 'Taxa';
                                    }

                                    return 'Outro';
                                };
                                ?>
                                <div class="cfg-servico-panel">
                                    <?php if ($orcItensUi !== []): ?>
                                        <form method="post" class="cfg-orc-carrinho" style="margin:0;">
                                            <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                            <p style="font-size:13px;color:#d4d4d8;margin:0 0 10px;">
                                                <strong>Orçamento detalhado</strong> — desmarca o que não queres ou escolhe uma opção em cada grupo de alternativas. O total atualiza com a tua seleção ao aceitar.
                                            </p>
                                            <div style="display:flex;flex-direction:column;gap:10px;">
                                                <?php
                                                $grMap = [];
                                                $solos = [];
                                                foreach ($orcItensUi as $oi) {
                                                    $g = trim((string) ($oi['grupo_alternativa'] ?? ''));
                                                    if ($g !== '') {
                                                        $grMap[$g][] = $oi;
                                                    } else {
                                                        $solos[] = $oi;
                                                    }
                                                }
                                                foreach ($grMap as $g => $members) {
                                                    if (count($members) < 2) {
                                                        foreach ($members as $m) {
                                                            $solos[] = $m;
                                                        }
                                                        unset($grMap[$g]);
                                                    }
                                                }
                                                foreach ($grMap as $gSlug => $members):
                                                    $chosenId = null;
                                                    foreach ($members as $oix) {
                                                        if (strtolower((string) ($oix['decisao_cliente'] ?? '')) === 'aceite') {
                                                            $chosenId = (int) $oix['id'];
                                                            break;
                                                        }
                                                    }
                                                    if ($chosenId === null && $members !== []) {
                                                        $chosenId = (int) $members[0]['id'];
                                                    }
                                                    ?>
                                                    <fieldset style="margin:0;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,122,26,.35);background:rgba(255,122,26,.06);">
                                                        <legend style="font-size:12px;color:#fdba74;padding:0 6px;">Alternativas (escolhe uma)</legend>
                                                        <?php foreach ($members as $oi):
                                                            $lid = (int) $oi['id'];
                                                            $q = (float) $oi['quantidade'];
                                                            $pu = (float) $oi['preco_unitario'];
                                                            $linTot = round($q * $pu, 2);
                                                            ?>
                                                            <label style="display:flex;align-items:flex-start;gap:10px;padding:6px 0;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.06);">
                                                                <input type="radio" name="orc_grupo[<?= htmlspecialchars($gSlug, ENT_QUOTES, 'UTF-8') ?>]" value="<?= $lid ?>" <?= $lid === $chosenId ? 'checked' : '' ?> style="margin-top:4px;">
                                                                <span style="flex:1;font-size:13px;">
                                                                    <strong><?= htmlspecialchars((string) $oi['descricao'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                                    <span style="color:#a1a1aa;"> · <?= htmlspecialchars($orcCatLabel((string) $oi['categoria']), ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php
                                                                    $tempoOiUi = am_formatar_tempo_linha_fatura([
                                                                        'minutos_trabalho' => $oi['minutos_trabalho'] ?? null,
                                                                        'quantidade' => $oi['quantidade'] ?? 0,
                                                                        'categoria' => $oi['categoria'] ?? '',
                                                                    ]);
                                                                    if ($tempoOiUi !== '—'): ?>
                                                                        <span style="color:#a1a1aa;"> · <?= htmlspecialchars($tempoOiUi, ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php endif; ?>
                                                                    <br>
                                                                    <span style="color:#fdba74;">€<?= number_format($linTot, 2, ',', ' ') ?> s/IVA</span>
                                                                    <span style="color:#71717a;font-size:12px;"> (<?= rtrim(rtrim(number_format($q, 3, ',', ' '), '0'), ',') ?> × €<?= number_format($pu, 2, ',', ' ') ?>)</span>
                                                                </span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </fieldset>
                                                <?php endforeach; ?>
                                                <?php foreach ($solos as $oi):
                                                    $lid = (int) $oi['id'];
                                                    $q = (float) $oi['quantidade'];
                                                    $pu = (float) $oi['preco_unitario'];
                                                    $linTot = round($q * $pu, 2);
                                                    $dec = strtolower((string) ($oi['decisao_cliente'] ?? 'aceite'));
                                                    ?>
                                                    <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.1);background:rgba(0,0,0,.2);cursor:pointer;">
                                                        <input type="checkbox" name="orc_line[<?= $lid ?>]" value="1" <?= $dec === 'aceite' ? 'checked' : '' ?> style="margin-top:4px;">
                                                        <span style="flex:1;font-size:13px;">
                                                            <strong><?= htmlspecialchars((string) $oi['descricao'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <span style="color:#a1a1aa;"> · <?= htmlspecialchars($orcCatLabel((string) $oi['categoria']), ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php
                                                            $tempoOiUi = am_formatar_tempo_linha_fatura([
                                                                'minutos_trabalho' => $oi['minutos_trabalho'] ?? null,
                                                                'quantidade' => $oi['quantidade'] ?? 0,
                                                                'categoria' => $oi['categoria'] ?? '',
                                                            ]);
                                                            if ($tempoOiUi !== '—'): ?>
                                                                <span style="color:#a1a1aa;"> · <?= htmlspecialchars($tempoOiUi, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endif; ?>
                                                            <br>
                                                            <span style="color:#fdba74;">€<?= number_format($linTot, 2, ',', ' ') ?> s/IVA</span>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;">
                                                <div style="font-size:13px;color:#f7c592;">
                                                    Total com a seleção atual: <strong>€<?= number_format((float) ($servico['orcamento_aprovado'] ?? 0), 2, ',', ' ') ?></strong> s/IVA
                                                    <span style="color:#a1a1aa;font-size:12px;">(recalculado ao aceitar)</span>
                                                </div>
                                                <div style="display:flex;gap:8px;">
                                                    <button type="submit" name="aceitar_orcamento_cliente" class="btn btn-primary">Aceitar esta composição</button>
                                                </div>
                                            </div>
                                        </form>
                                        <form method="post" style="margin:8px 0 0;">
                                            <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                            <button type="submit" name="recusar_orcamento_cliente" class="btn btn-danger">Recusar orçamento inteiro</button>
                                        </form>
                                    <?php else: ?>
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                            <div style="font-size:13px;color:#f7c592;">
                                                Orçamento aprovado: <strong>€<?= number_format((float) ($servico['orcamento_aprovado'] ?? 0), 2, ',', ' ') ?></strong> s/IVA
                                            </div>
                                            <div style="display:flex;gap:8px;align-items:center;">
                                                <form method="post" style="margin:0;">
                                                    <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                                    <button type="submit" name="aceitar_orcamento_cliente" class="btn btn-primary">Aceitar orçamento</button>
                                                </form>
                                                <form method="post" style="margin:0;">
                                                    <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                                    <button type="submit" name="recusar_orcamento_cliente" class="btn btn-danger">Recusar</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($orcamento_estado === 'aprovado' && $orcamento_cliente === 'aceite'): ?>
                                <?php if ($aguardaFaturaFinalUi): ?>
                                    <p class="cfg-servico-note cfg-servico-note--warn">Serviço concluído · A aguardar o valor final da fatura pela oficina</p>
                                <?php elseif (!$precisaPagarUi && am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null) === 'pago'): ?>
                                    <?php if ($estado_key === 'concluido'): ?>
                                        <p class="cfg-servico-note cfg-servico-note--ok">Pago · Serviço concluído</p>
                                    <?php else: ?>
                                        <p class="cfg-servico-note cfg-servico-note--ok">Pago · Aguarda atribuição de mecânico</p>
                                    <?php endif; ?>
                                <?php elseif ($estado_key !== 'concluido' && $orcamentoEstimadoUi > 0): ?>
                                    <p class="cfg-servico-note cfg-servico-note--ok">
                                        Orçamento aceite · <strong>€<?= number_format($orcamentoEstimadoUi, 2, ',', ' ') ?></strong> s/IVA
                                    </p>
                                    <p class="cfg-servico-note" style="margin-top:6px;font-size:12px;">
                                        O pagamento só é feito após o mecânico concluir o trabalho. O orçamento serve apenas para ter uma ideia do valor.
                                    </p>
                                <?php elseif ($estado_key !== 'concluido'): ?>
                                    <p class="cfg-servico-note cfg-servico-note--ok">Orçamento aceite · Aguarda atribuição de mecânico</p>
                                <?php endif; ?>
                            <?php elseif ($orcamento_estado === 'aprovado' && $orcamento_cliente === 'recusado'): ?>
                                <p class="cfg-servico-note cfg-servico-note--err">Orçamento recusado</p>
                            <?php endif; ?>
                            <?php if ($excessoPendenteUi && is_array($cmpExcessoUi)): ?>
                                <div class="cfg-servico-panel" style="border-color:rgba(251,191,36,.45);background:rgba(251,191,36,.08);">
                                    <p class="cfg-servico-note cfg-servico-note--warn" style="margin-bottom:10px;">
                                        <strong>Total acima do orçamento</strong> — o valor final ficou significativamente acima do orçamento que aceitou.
                                    </p>
                                    <ul style="margin:0 0 12px;padding-left:18px;font-size:13px;color:#d4d4d8;line-height:1.5;">
                                        <li>Orçamento aceite (c/ IVA): <strong>€<?= number_format((float) ($cmpExcessoUi['orcamento_com_iva'] ?? 0), 2, ',', ' ') ?></strong></li>
                                        <li>Total final (c/ IVA): <strong>€<?= number_format((float) ($cmpExcessoUi['total_com_iva'] ?? 0), 2, ',', ' ') ?></strong></li>
                                        <li>Diferença: <strong style="color:#fbbf24;">+€<?= number_format((float) ($cmpExcessoUi['diferenca'] ?? 0), 2, ',', ' ') ?></strong></li>
                                    </ul>
                                    <p class="cfg-servico-note" style="margin-bottom:12px;font-size:12px;">
                                        Confirme se deseja continuar com este valor. Só depois poderá pagar o trabalho realizado.
                                    </p>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;">
                                        <form method="post" class="cfg-confirm-form" style="margin:0;" data-confirm-title="Cancelar trabalho?" data-confirm-msg="Ao recusar o valor final, o trabalho será cancelado e o veículo deixa de estar disponível para levantamento. A oficina entrará em contacto consigo. Deseja continuar?">
                                            <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                            <button type="submit" name="recusar_excesso_orcamento_cliente" class="btn btn-danger">Não continuar</button>
                                        </form>
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                            <button type="submit" name="aceitar_excesso_orcamento_cliente" class="btn btn-primary">Continuar com este valor</button>
                                        </form>
                                    </div>
                                </div>
                            <?php elseif ($excessoRecusadoUi): ?>
                                <p class="cfg-servico-note cfg-servico-note--err">Recusou o valor final. O trabalho foi <strong>cancelado</strong> e a oficina entrará em contacto consigo.</p>
                            <?php elseif ($excessoAceiteUi && $servicoConcluidoUi && $totalTrabalhoUi > 0.009): ?>
                                <p class="cfg-servico-note cfg-servico-note--ok">Confirmou que deseja continuar com o valor final registado pela oficina.</p>
                            <?php endif; ?>
                            <?php if ($precisaPagarUi): ?>
                                <div class="cfg-servico-panel">
                                    <?php if ($valorPagoTotalUi > 0.009): ?>
                                    <p class="cfg-servico-note cfg-servico-note--pay">
                                        Trabalho realizado pela oficina: <strong>€<?= number_format($totalTrabalhoUi, 2, ',', ' ') ?></strong> (IVA incluído)
                                    </p>
                                    <p class="cfg-servico-note" style="margin-top:6px;">
                                        Já pagou: <strong>€<?= number_format($valorPagoTotalUi, 2, ',', ' ') ?></strong>
                                    </p>
                                    <?php endif; ?>
                                    <p class="cfg-servico-note cfg-servico-note--pay" style="margin-top:<?= $valorPagoTotalUi > 0.009 ? '8' : '0' ?>px;">
                                        Valor a pagar: <strong>€<?= number_format($valorEmDividaUi, 2, ',', ' ') ?></strong> (horas + peças + IVA)
                                    </p>
                                    <p class="cfg-servico-note" style="margin-top:8px;font-size:12px;">
                                        O mecânico concluiu o serviço. Este é o valor registado na fatura final.
                                    </p>
                                    <div style="margin-top:8px;display:flex;justify-content:flex-end;">
                                        <a class="btn btn-primary" href="pagamento_orcamento.php?servico_id=<?= (int)$servico['id'] ?>">Pagar €<?= number_format($valorEmDividaUi, 2, ',', ' ') ?></a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!$precisaPagarUi && $estado_pagamento_srv === 'pago' && $codigoLevantamentoFmtUi !== '' && !$codigoLevantamentoUsadoUi): ?>
                                <div class="cfg-servico-panel" style="margin-top:10px;border-color:rgba(249,115,22,.35);background:rgba(249,115,22,.08);">
                                    <p class="cfg-servico-note cfg-servico-note--ok" style="margin-bottom:8px;">
                                        Código de levantamento
                                    </p>
                                    <div style="font-size:28px;font-weight:800;letter-spacing:4px;color:#fdba74;line-height:1;"><?= htmlspecialchars($codigoLevantamentoFmtUi) ?></div>
                                    <p class="cfg-servico-note" style="margin-top:10px;font-size:12px;">
                                        Mostre este código de 6 dígitos ao mecânico quando for levantar o veículo.
                                    </p>
                                </div>
                            <?php elseif ($codigoLevantamentoUsadoUi): ?>
                                <p class="cfg-servico-note cfg-servico-note--ok">Veículo entregue com sucesso.</p>
                            <?php endif; ?>
                            <div class="cfg-servico-foot">
                            <?php if ($pode_cancelar_marcacao): ?>
                                <div class="cfg-cancel-block">
                                    <form method="post" class="cfg-cancel-form cfg-confirm-form" data-confirm-title="Cancelar marcação?" data-confirm-msg="Tem a certeza que deseja cancelar esta marcação? Esta ação não pode ser desfeita.">
                                        <input type="hidden" name="cancelar_marcacao_cliente" value="1">
                                        <input type="hidden" name="servico_id" value="<?= (int) $servico['id'] ?>">
                                        <button type="submit" class="cfg-link-btn cfg-link-btn--danger">Cancelar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                                <div class="cfg-servico-actions">
                                    <a class="cfg-link-btn" href="rastreamento_reparacao.php?id=<?= (int)$servico['id'] ?>&return=<?= urlencode('configuracoes.php?seccao=marcacoes') ?>">Tracking</a>
                                    <?php if ($pode_ver_fatura): ?>
                                        <a class="cfg-link-btn" href="configuracoes.php?seccao=marcacoes&ver_fatura=<?= (int)$servico['id'] ?>">Fatura</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>

                <?php if ($total_canceladas_lista > 0): ?>
                <section class="cfg-marcacoes-canceladas" id="secMarcacoesCanceladas"<?= $ver_canceladas_inicial ? '' : ' style="display:none;"' ?>>
                    <div class="cfg-marcacoes-canceladas-head">
                        <h4><i class="fas fa-ban"></i> Marcações canceladas</h4>
                        <span class="cfg-pill cfg-pill-cancelada"><?= (int) $total_canceladas_lista ?></span>
                    </div>
                    <p class="cfg-subtitle" style="margin:0 0 12px;">Histórico das marcações que cancelou — já não aparecem na lista principal.</p>
                    <div class="servicos-lista servicos-lista--canceladas" id="listaMarcacoesCanceladas">
                        <?php foreach ($servicos_rows as $servico): ?>
                            <?php if (!am_marcacao_esta_cancelada($servico)) { continue; } ?>
                            <?php $data_ts = strtotime($servico['data_registo']); ?>
                            <div class="cfg-servico cfg-servico--cancelada" data-status="cancelada" data-date="<?= (int) $data_ts ?>">
                                <div class="cfg-servico-top">
                                    <div class="cfg-servico-head">
                                        <div class="cfg-servico-title">
                                            <strong><?= htmlspecialchars($servico['tipo_servico']) ?></strong>
                                            <span class="cfg-pill cfg-pill-cancelada">Cancelada</span>
                                        </div>
                                        <div class="cfg-servico-meta">
                                            <span><?= htmlspecialchars($servico['matricula']) ?></span>
                                            <?php if (!empty($servico['data_desejada'])): ?>
                                                <span><?= date('d/m/Y', strtotime($servico['data_desejada'])) ?><?= !empty($servico['hora_desejada']) ? ' · ' . htmlspecialchars($servico['hora_desejada']) : '' ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($servico['data_cancelamento'])): ?>
                                                <span>Cancelada <?= date('d/m/Y', strtotime((string) $servico['data_cancelamento'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="cfg-servico-price">€<?= number_format(am_servico_valor_exibicao_site($servico), 2, ',', ' ') ?></div>
                                </div>
                                <?php if (am_servico_excesso_orcamento_recusado($servico)): ?>
                                    <p class="cfg-servico-note cfg-servico-note--err" style="margin-top:10px;">
                                        Recusou o valor final do trabalho. O serviço foi cancelado e a oficina entrará em contacto consigo.
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            <?php else: ?>
                <div class="cfg-empty">
                    <div class="cfg-empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                    <h3>Ainda não tem marcações</h3>
                    <p>Quando marcar uma revisão ou reparação, aparecerá aqui para acompanhar.</p>
                    <a href="index.php#book-form" class="btn btn-primary">Nova marcação</a>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<script src="nif.js?v=2"></script>
<script src="telemovel.js?v=1"></script>
<script src="matricula.js?v=1"></script>
<script>

document.addEventListener('DOMContentLoaded', function () {
    const btnEditarDados = document.getElementById('btnEditarDados');
    const btnCancelarEdicao = document.getElementById('btnCancelarEdicao');
    const formEditarDados = document.getElementById('formEditarDados');

    if (btnEditarDados && formEditarDados) {
        btnEditarDados.addEventListener('click', function () {
            formEditarDados.classList.remove('is-hidden');
            const firstInput = formEditarDados.querySelector('input[name="nome_utilizador"]');
            if (firstInput) firstInput.focus();
        });
    }

    if (btnCancelarEdicao && formEditarDados) {
        btnCancelarEdicao.addEventListener('click', function () {
            formEditarDados.classList.add('is-hidden');
        });
    }
});

// NIF (matrícula → matricula.js; telemóvel → telemovel.js)
document.addEventListener('DOMContentLoaded', function () {
    if (typeof amInitNifInputs === 'function') {
        amInitNifInputs(document);
    }
    if (typeof amInitTelemovelInputs === 'function') {
        amInitTelemovelInputs(document);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const lista = document.getElementById('listaMarcacoes');
    const fStatus = document.getElementById('fStatus');
    const fOrdenar = document.getElementById('fOrdenar');
    const wrapAtivas = document.getElementById('cfgMarcacoesAtivasWrap');
    const secCanceladas = document.getElementById('secMarcacoesCanceladas');
    const listaCanceladas = document.getElementById('listaMarcacoesCanceladas');
    if (!lista || !fStatus || !fOrdenar) return;

    function ordenarLista(container, ordenar) {
        if (!container) return;
        const items = Array.from(container.querySelectorAll('.cfg-servico'));
        items.sort((a, b) => {
            const da = Number(a.getAttribute('data-date') || 0);
            const db = Number(b.getAttribute('data-date') || 0);
            return ordenar === 'antigas' ? da - db : db - da;
        });
        items.forEach(el => container.appendChild(el));
    }

    function aplicarFiltros() {
        const status = fStatus.value;
        const ordenar = fOrdenar.value;

        if (status === 'cancelada') {
            if (wrapAtivas) wrapAtivas.style.display = 'none';
            if (secCanceladas) secCanceladas.style.display = '';
            ordenarLista(listaCanceladas, ordenar);
            return;
        }

        if (wrapAtivas) wrapAtivas.style.display = '';
        if (secCanceladas) secCanceladas.style.display = 'none';

        const items = Array.from(lista.querySelectorAll('.cfg-servico'));
        items.forEach(el => {
            const st = el.getAttribute('data-status') || 'pendente';
            const visible = status === 'todos' ? true : st === status;
            el.style.display = visible ? '' : 'none';
        });

        const visiveis = items.filter(el => el.style.display !== 'none');
        visiveis.sort((a, b) => {
            const da = Number(a.getAttribute('data-date') || 0);
            const db = Number(b.getAttribute('data-date') || 0);
            return ordenar === 'antigas' ? da - db : db - da;
        });
        visiveis.forEach(el => lista.appendChild(el));
    }

    fStatus.addEventListener('change', aplicarFiltros);
    fOrdenar.addEventListener('change', aplicarFiltros);
    aplicarFiltros();

    if (new URLSearchParams(window.location.search).get('ver') === 'canceladas' && fStatus) {
        fStatus.value = 'cancelada';
        aplicarFiltros();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var modelosPorMarca = <?= json_encode($am_modelos_por_marca, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    /* Reparentar modais para <body>: dentro de .carro-item o position:fixed fica preso ao cartão
       (transform no :hover + overflow:hidden) — aparecia pequeno no canto. */
    document.querySelectorAll('.cfg-vehicle-modal').forEach(function (el) {
        document.body.appendChild(el);
    });

    function cfgFecharModalVeiculo(modal) {
        if (!modal || !modal.classList.contains('cfg-vehicle-modal')) return;
        modal.classList.add('is-hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function cfgFecharTodosModaisVeiculo() {
        document.querySelectorAll('.cfg-vehicle-modal').forEach(function (m) {
            cfgFecharModalVeiculo(m);
        });
    }

    function cfgAbrirModalVeiculo(modal) {
        if (!modal) return;
        cfgFecharTodosModaisVeiculo();
        modal.classList.remove('is-hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    document.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-cfg-modal-close]');
        if (!closeBtn) return;
        var modal = closeBtn.closest('.cfg-vehicle-modal');
        if (modal) cfgFecharModalVeiculo(modal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = document.querySelector('.cfg-vehicle-modal:not(.is-hidden)');
        if (open) cfgFecharModalVeiculo(open);
    });

    function fecharTodosFuelPanels(except) {
        document.querySelectorAll('[data-fuel-panel]').forEach(function (panel) {
            if (except && panel === except) return;
            panel.classList.add('is-hidden');
        });
    }

    function initFuelPickers(context) {
        (context || document).querySelectorAll('.cfg-fuel-picker').forEach(function (picker) {
            if (picker.dataset.boundFuelPicker === '1') return;
            picker.dataset.boundFuelPicker = '1';

            var selectId = picker.getAttribute('data-select-id') || '';
            var select = selectId ? document.getElementById(selectId) : null;
            var trigger = picker.querySelector('[data-fuel-trigger]');
            var triggerText = picker.querySelector('[data-fuel-trigger-text]');
            var panel = picker.querySelector('[data-fuel-panel]');
            var clearBtn = picker.querySelector('[data-fuel-clear]');
            var applyBtn = picker.querySelector('[data-fuel-apply]');
            var items = picker.querySelectorAll('[data-fuel-value]');

            if (!select || !trigger || !triggerText || !panel || !items.length) return;

            function syncVisual() {
                var current = select.value || '';
                var currentLabel = 'Selecionar';
                items.forEach(function (item) {
                    var val = item.getAttribute('data-fuel-value') || '';
                    var selected = (val === current);
                    item.classList.toggle('is-selected', selected);
                    if (selected) {
                        currentLabel = (item.querySelector('span') ? item.querySelector('span').textContent : 'Selecionar') || 'Selecionar';
                    }
                });
                triggerText.textContent = currentLabel;
            }

            trigger.addEventListener('click', function () {
                var willOpen = panel.classList.contains('is-hidden');
                fecharTodosFuelPanels();
                if (willOpen) panel.classList.remove('is-hidden');
            });

            items.forEach(function (item) {
                item.addEventListener('click', function () {
                    select.value = item.getAttribute('data-fuel-value') || '';
                    syncVisual();
                });
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    select.value = '';
                    syncVisual();
                });
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    panel.classList.add('is-hidden');
                });
            }

            syncVisual();
        });
    }

    function preencherModelos(marcaSelect, modeloSelect, modeloAtual) {
        if (!marcaSelect || !modeloSelect) return;
        var marca = marcaSelect.value || '';
        modeloSelect.innerHTML = '';

        if (!marca || !modelosPorMarca[marca]) {
            modeloSelect.disabled = true;
            var op0 = document.createElement('option');
            op0.value = '';
            op0.textContent = 'Selecione marca...';
            modeloSelect.appendChild(op0);
            return;
        }

        modeloSelect.disabled = false;
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = 'Escolha o modelo';
        modeloSelect.appendChild(ph);

        modelosPorMarca[marca].forEach(function (modelo) {
            var op = document.createElement('option');
            op.value = modelo;
            op.textContent = modelo;
            if (modeloAtual && modeloAtual === modelo) {
                op.selected = true;
            }
            modeloSelect.appendChild(op);
        });
    }

    function ligarMarcaModelo(context) {
        (context || document).querySelectorAll('.cfg-marca-select').forEach(function (marcaSelect) {
            var form = marcaSelect.closest('form');
            if (!form) return;
            var modeloSelect = form.querySelector('.cfg-modelo-select');
            if (!modeloSelect) return;

            var modeloAtual = modeloSelect.getAttribute('data-current-model') || '';
            preencherModelos(marcaSelect, modeloSelect, modeloAtual);

            if (!marcaSelect.dataset.modeloBound) {
                marcaSelect.addEventListener('change', function () {
                    preencherModelos(marcaSelect, modeloSelect, '');
                });
                marcaSelect.dataset.modeloBound = '1';
            }
        });
    }

    ligarMarcaModelo(document);
    initFuelPickers(document);

    var modalJaAberto = document.querySelector('.cfg-vehicle-modal:not(.is-hidden)');
    if (modalJaAberto) {
        cfgAbrirModalVeiculo(modalJaAberto);
        ligarMarcaModelo(modalJaAberto);
        initFuelPickers(modalJaAberto);
    }

    document.querySelectorAll('.cfg-btn-edit-vehicle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-target');
            if (!id) return;
            var modal = document.getElementById(id);
            if (!modal) return;
            cfgAbrirModalVeiculo(modal);
            ligarMarcaModelo(modal);
            initFuelPickers(modal);
            var first = modal.querySelector('.cfg-marca-select, input[name="marca"]');
            if (first) {
                try { first.focus(); } catch (err) {}
            }
        });
    });

    document.querySelectorAll('.cfg-btn-cancel-vehicle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-target');
            if (!id) return;
            var modal = document.getElementById(id);
            cfgFecharModalVeiculo(modal);
        });
    });

    try {
        var u = new URL(window.location.href);
        var oc = u.searchParams.get('open_car');
        var nOpen = oc ? parseInt(oc, 10) : 0;
        if (nOpen > 0) {
            var pnl = document.getElementById('panelCarro' + nOpen);
            if (pnl) {
                cfgAbrirModalVeiculo(pnl);
                ligarMarcaModelo(pnl);
                initFuelPickers(pnl);
            }
        }
    } catch (err) {}

    document.addEventListener('click', function (e) {
        var inside = e.target.closest('.cfg-fuel-picker');
        if (!inside) fecharTodosFuelPanels();
    });

    (function () {
        var notifUserId = <?= (int) $id_utilizador ?>;
        var profileBtn = document.querySelector('.cfg-profile-btn');
        var profileIcon = profileBtn ? profileBtn.querySelector('i.fas') : null;
        var profilePill = document.getElementById('cfgProfileNotifPill');
        var badgeDados = document.getElementById('cfgTabBadgeDados');
        var badgeVeiculos = document.getElementById('cfgTabBadgeVeiculos');
        var badgeMarcacoes = document.getElementById('cfgTabBadgeMarcacoes');
        var notifBanner = document.getElementById('cfgNotifBanner');
        var dismissBtn = document.getElementById('cfgNotifDismiss');

        function setBadge(el, count, pillClass) {
            if (!el) return;
            var n = parseInt(count, 10) || 0;
            el.textContent = String(n > 99 ? '99+' : n);
            if (pillClass) {
                el.classList.toggle('is-on', n > 0);
            } else {
                el.classList.toggle('is-hidden', n <= 0);
            }
        }

        function updateProfileChrome(total) {
            var n = parseInt(total, 10) || 0;
            if (profileBtn) {
                profileBtn.classList.toggle('has-notif', n > 0);
                profileBtn.setAttribute('aria-label', n > 0 ? 'Notificações e perfil' : 'Perfil');
                profileBtn.setAttribute('title', n > 0 ? 'Notificações e perfil' : 'Perfil');
            }
            if (profileIcon) {
                profileIcon.classList.toggle('fa-bell', n > 0);
                profileIcon.classList.toggle('fa-user', n <= 0);
            }
        }

        function hideNotifBanner() {
            if (!notifBanner) return;
            notifBanner.classList.add('is-hiding');
            window.setTimeout(function () {
                notifBanner.classList.remove('is-visible', 'is-hiding');
            }, 280);
        }

        function applyNotifState(payload) {
            if (!payload) return;
            var serverCount = parseInt(payload.count, 10) || 0;
            var sections = payload.sections || {};
            var visibleDados = 0;
            var visibleVeiculos = 0;
            var visibleMarcacoes = 0;

            if (serverCount > 0) {
                visibleDados = parseInt(sections.dados || 0, 10) || 0;
                visibleVeiculos = parseInt(sections.veiculos || 0, 10) || 0;
                visibleMarcacoes = parseInt(sections.marcacoes || 0, 10) || 0;
            }

            setBadge(profilePill, serverCount, true);
            setBadge(badgeDados, visibleDados, false);
            setBadge(badgeVeiculos, visibleVeiculos, false);
            setBadge(badgeMarcacoes, visibleMarcacoes, false);
            updateProfileChrome(serverCount);

            document.querySelectorAll('.cfg-tab').forEach(function (tab) {
                var href = tab.getAttribute('href') || '';
                var hasNotif = false;
                if (href.indexOf('seccao=dados') !== -1) hasNotif = visibleDados > 0;
                if (href.indexOf('seccao=veiculos') !== -1) hasNotif = visibleVeiculos > 0;
                if (href.indexOf('seccao=marcacoes') !== -1) hasNotif = visibleMarcacoes > 0;
                tab.classList.toggle('cfg-tab--has-notif', hasNotif);
            });

            if (serverCount <= 0) {
                hideNotifBanner();
            }
        }

        function marcarNotificacoesLidas(done) {
            return fetch('utilizador_notif_ler.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) return j;
                    applyNotifState(j);
                    if (typeof done === 'function') done(j);
                    return j;
                })
                .catch(function () {});
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                dismissBtn.disabled = true;
                marcarNotificacoesLidas(function () {
                    dismissBtn.disabled = false;
                });
            });
        }

        document.querySelectorAll('[data-notif-go]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var href = el.getAttribute('href') || 'configuracoes.php';
                marcarNotificacoesLidas(function () {
                    window.location.href = href;
                });
            });
        });

        applyNotifState({
            count: <?= (int) $notif_cfg_visivel ?>,
            sections: <?= json_encode($notif_cfg_sections, JSON_UNESCAPED_UNICODE) ?>
        });

        function pollNotifTabs() {
            fetch('utilizador_pulse.php', { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) return;
                    applyNotifState(j);
                })
                .catch(function () {});
        }

        window.setTimeout(pollNotifTabs, 4000);
        window.setInterval(pollNotifTabs, 30000);
    })();
});

</script>

</body>
</html> 
