<?php
session_start();
require_once('check_session.php');
require_once('ligar.php');
require_once __DIR__ . '/inc_codigo_levantamento.php';
require_once __DIR__ . '/inc_notificar_cliente_email.php';

/**
 * Prefixos MB WAY: os dois primeiros dígitos do telemóvel (ex.: 91, 92, 93, 96).
 * Lista vazia = não validar prefixo (só 9 dígitos nacionais 9XXXXXXXX).
 * Para desactivar o filtro: em ligar.php (antes deste ficheiro) define('AM_MBWAY_PREFIXOS_2D', []);
 * Para alargar: define('AM_MBWAY_PREFIXOS_2D', ['91','92','93','96','97']);
 */
if (!defined('AM_MBWAY_PREFIXOS_2D')) {
    define('AM_MBWAY_PREFIXOS_2D', ['91', '92', '93', '96']);
}

if (empty($_SESSION['user']) || empty($_SESSION['user']['id_utilizador']) || empty($_SESSION['user']['email'])) {
    header('Location: login.php');
    exit;
}

$id_utilizador = (int)$_SESSION['user']['id_utilizador'];
$email_utilizador = (string)$_SESSION['user']['email'];
$nome_utilizador = (string)($_SESSION['user']['nome_utilizador'] ?? 'Cliente');
$nif_utilizador = preg_replace('/\D+/', '', (string)($_SESSION['user']['nif'] ?? ''));
$servico_id = (int)($_GET['servico_id'] ?? $_POST['servico_id'] ?? 0);

if ($servico_id <= 0) {
    die('Pagamento inválido.');
}

@$con->query("ALTER TABLE servico ADD COLUMN estado_pagamento VARCHAR(20) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN metodo_pagamento VARCHAR(30) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN data_pagamento DATETIME NULL");
@$con->query("ALTER TABLE servico ADD COLUMN subtotal_sem_iva DECIMAL(10,2) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN valor_iva DECIMAL(10,2) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN valor_total_com_iva DECIMAL(10,2) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN valor_pago_total DECIMAL(10,2) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN valor_em_divida DECIMAL(10,2) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN pedir_contribuinte_fatura TINYINT(1) NOT NULL DEFAULT 0");
@$con->query("ALTER TABLE servico ADD COLUMN contribuinte_fatura VARCHAR(9) NULL");
@$con->query("ALTER TABLE servico ADD COLUMN numero_fatura VARCHAR(40) NULL");
am_garantir_colunas_codigo_levantamento($con);

function validarNifPtPg(string $nif): bool {
    $nif = preg_replace('/\D+/', '', $nif);
    if (strlen($nif) !== 9) return false;
    if (preg_match('/^(\d)\1{8}$/', $nif)) return false;
    if (!preg_match('/^[1235689]/', $nif)) return false;
    $soma = 0;
    for ($i = 0; $i < 8; $i++) $soma += ((int)$nif[$i]) * (9 - $i);
    $dig = 11 - ($soma % 11);
    if ($dig >= 10) $dig = 0;
    return (int)$nif[8] === $dig;
}

/** Telemóvel nacional 9 dígitos (9 + 8). */
function telefoneMovelPt9(string $tel): bool {
    return (bool)preg_match('/^9\d{8}$/', $tel);
}

/**
 * Opcional: os dois primeiros dígitos (9X) têm de estar na lista, ex. 91, 92, 93, 96.
 * @param list<string> $prefixos2d
 */
function telefoneMbwayPrefixoAceite(string $tel, array $prefixos2d): bool {
    if ($prefixos2d === []) {
        return true;
    }
    if (strlen($tel) < 2) {
        return false;
    }
    $dois = substr($tel, 0, 2);
    return in_array($dois, $prefixos2d, true);
}

$servico = null;
$stServico = $con->prepare("
    SELECT id, nome, email, telefone, nif, matricula, tipo_servico, data_desejada, hora_desejada, orcamento_aprovado,
           estado_orcamento, estado_orcamento_cliente, estado_pagamento, estado_do_trabalho, estado,
           numero_fatura, valor_pago_total, valor_em_divida, metodo_pagamento, data_pagamento,
           subtotal_sem_iva, valor_iva, valor_total_com_iva, horas_trabalhadas, valor_hora, pecas_substituidas, fatura_linhas_json
    FROM servico
    WHERE id = ?
      AND (
        LOWER(TRIM(COALESCE(email, ''))) = LOWER(TRIM(?))
        OR id_utilizador = ?
      )
    LIMIT 1
");
if ($stServico) {
    $stServico->bind_param('isi', $servico_id, $email_utilizador, $id_utilizador);
    if ($stServico->execute()) {
        $servico = $stServico->get_result()->fetch_assoc();
    }
    $stServico->close();
}
if (!$servico) {
    $stServico = $con->prepare("
        SELECT id, nome, email, telefone, nif, matricula, tipo_servico, data_desejada, hora_desejada, orcamento_aprovado,
               estado_orcamento, estado_orcamento_cliente, estado_pagamento, estado_do_trabalho, estado,
               numero_fatura, valor_pago_total, valor_em_divida, metodo_pagamento, data_pagamento,
               subtotal_sem_iva, valor_iva, valor_total_com_iva, horas_trabalhadas, valor_hora, pecas_substituidas, fatura_linhas_json
        FROM servico
        WHERE id = ? AND LOWER(TRIM(COALESCE(email, ''))) = LOWER(TRIM(?))
        LIMIT 1
    ");
    if ($stServico) {
        $stServico->bind_param('is', $servico_id, $email_utilizador);
        if ($stServico->execute()) {
            $servico = $stServico->get_result()->fetch_assoc();
        }
        $stServico->close();
    }
}

if (!$servico) {
    die('Serviço não encontrado.');
}

if ($nif_utilizador === '') {
    $stNifUser = $con->prepare('SELECT nif FROM utilizador WHERE id_utilizador = ? LIMIT 1');
    if ($stNifUser) {
        $stNifUser->bind_param('i', $id_utilizador);
        if ($stNifUser->execute()) {
            $rowNifUser = $stNifUser->get_result()->fetch_assoc();
            $nif_utilizador = preg_replace('/\D+/', '', (string) ($rowNifUser['nif'] ?? ''));
        }
        $stNifUser->close();
    }
}

require_once __DIR__ . '/inc_utilizador_notificacoes.php';
am_utilizador_notificacoes_metrics($con, $id_utilizador, true);
require_once __DIR__ . '/inc_orcamento_interativo.php';
require_once __DIR__ . '/inc_validacao_cartao.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';
require_once __DIR__ . '/inc_excesso_orcamento.php';

$syncPagamento = am_servico_sincronizar_divida_pagamento($con, $servico_id);
if (is_array($syncPagamento)) {
    $servico = array_merge($servico, $syncPagamento);
    $stReload = $con->prepare("
        SELECT id, nome, email, telefone, nif, matricula, tipo_servico, data_desejada, hora_desejada, orcamento_aprovado,
               estado_orcamento, estado_orcamento_cliente, estado_pagamento, estado_do_trabalho, estado,
               numero_fatura, valor_pago_total, valor_em_divida, metodo_pagamento, data_pagamento,
               subtotal_sem_iva, valor_iva, valor_total_com_iva, horas_trabalhadas, valor_hora, pecas_substituidas, fatura_linhas_json
        FROM servico WHERE id = ? LIMIT 1
    ");
    if ($stReload) {
        $stReload->bind_param('i', $servico_id);
        $stReload->execute();
        $rowReload = $stReload->get_result()->fetch_assoc();
        $stReload->close();
        if ($rowReload) {
            $servico = $rowReload;
        }
    }
}

$reparadoPag = am_servico_reparar_valores_fiscais_se_inconsistentes($con, $servico_id, $servico);
if (is_array($reparadoPag)) {
    $servico = array_merge($servico, $reparadoPag);
}

if (!am_servico_permite_pagina_pagamento($servico)) {
    die('Este serviço ainda não está pronto para pagamento.');
}

if (am_servico_excesso_orcamento_pendente($servico)) {
    die('O valor final está acima do orçamento aceite. Confirme em Configurações → Minhas Marcações se deseja continuar antes de pagar.');
}
if (am_servico_excesso_orcamento_recusado($servico)) {
    die('Recusou o valor final do trabalho. O serviço foi cancelado e a oficina entrará em contacto consigo.');
}

$estado_orc = strtolower(trim((string)($servico['estado_orcamento'] ?? '')));
$estado_orc_cliente = strtolower(trim((string)($servico['estado_orcamento_cliente'] ?? '')));
$estado_pag = am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null);

$servico_concluido = am_servico_esta_concluido($servico);
$tem_fecho_mecanico = am_servico_tem_fecho_mecanico($servico);
$total_trabalho_mecanico = am_servico_total_fatura_final($servico);
$precisa_pagar_trabalho = am_servico_precisa_pagar_trabalho_mecanico($servico);
$precisa_pagar = $precisa_pagar_trabalho;
$intervencao_pagamento_html = $tem_fecho_mecanico
    ? am_fatura_intervencao_html($servico, 'dark')
    : '';

if (!$servico_concluido) {
    die('O pagamento só é feito após o mecânico concluir o trabalho. O orçamento é apenas uma estimativa do valor.');
}

if (!$precisa_pagar) {
    if (am_servico_aguarda_fatura_final_mecanico($servico)) {
        die('O serviço foi concluído, mas a oficina ainda não registou o valor final da fatura. Tente novamente em breve.');
    }
    if (am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null) === 'pago') {
        // deixa a página mostrar confirmação abaixo
    } else {
        die('Este serviço não tem pagamentos pendentes.');
    }
}

$valor_pago_total = am_servico_valor_ja_pago($servico);
$valor_em_divida = round((float) ($servico['valor_em_divida'] ?? 0), 2);
$total = am_servico_valor_trabalho_mecanico_a_pagar($servico);
$subtotal_registo = (float) ($servico['subtotal_sem_iva'] ?? 0);
$iva_registo = (float) ($servico['valor_iva'] ?? 0);
$total_registo = (float) ($servico['valor_total_com_iva'] ?? 0);
$erro = '';
$sucesso = '';
$codigo_levantamento_sucesso = '';

if (!empty($_SESSION['pay_flash_sucesso'])) {
    $sucesso = (string) $_SESSION['pay_flash_sucesso'];
    unset($_SESSION['pay_flash_sucesso']);
}
if (!empty($_SESSION['pay_flash_codigo'])) {
    $codigo_levantamento_sucesso = (string) $_SESSION['pay_flash_codigo'];
    unset($_SESSION['pay_flash_codigo']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $precisa_pagar) {
    $metodo = trim((string)($_POST['metodo_pagamento_srv'] ?? ''));
    $telefone_pag = preg_replace('/\D+/', '', (string)($_POST['telefone_pagamento'] ?? ''));
    $pedir_nif = isset($_POST['pedir_contribuinte_fatura']) ? 1 : 0;
    $nif_fatura = preg_replace('/\D+/', '', (string)($_POST['contribuinte_fatura'] ?? ''));
    $card_num = preg_replace('/\D+/', '', (string)($_POST['numero_cartao'] ?? ''));
    $card_nome = trim((string)($_POST['nome_titular'] ?? ''));
    $card_validade = trim((string)($_POST['validade'] ?? ''));
    $card_cvv = preg_replace('/\D+/', '', (string)($_POST['cvv'] ?? ''));

    $prefixosMbway = AM_MBWAY_PREFIXOS_2D;
    if (!is_array($prefixosMbway)) {
        $prefixosMbway = [];
    }

    $metodos_validos = ['mbway', 'cartao', 'referencia_mb'];
    if (!in_array($metodo, $metodos_validos, true)) {
        $erro = 'Selecione um método de pagamento.';
    } elseif (!telefoneMovelPt9($telefone_pag)) {
        $erro = 'Introduza um número de telemóvel válido (9 dígitos, começados por 9).';
    } elseif ($metodo === 'mbway' && !telefoneMbwayPrefixoAceite($telefone_pag, $prefixosMbway)) {
        $erro = 'O prefixo deste número não está entre os aceites para MB WAY. Verifica o número ou contacta o apoio.';
    } elseif ($pedir_nif && !validarNifPtPg($nif_fatura)) {
        $erro = 'NIF para fatura inválido.';
    } elseif ($metodo === 'cartao') {
        $erroCartao = am_cartao_validar_dados([
            'numero_cartao' => $card_num,
            'validade' => $card_validade,
            'cvv' => $card_cvv,
            'nome_titular' => $card_nome,
        ]);
        if ($erroCartao !== null) {
            $erro = $erroCartao;
        }
    }

    if ($erro === '') {
        $numero_fatura = !empty($servico['numero_fatura']) ? $servico['numero_fatura'] : ('FAT-' . date('Ymd') . '-' . str_pad((string)$servico_id, 6, '0', STR_PAD_LEFT));
        $nif_guardar = $pedir_nif ? $nif_fatura : null;
        $novo_valor_pago_total = round($valor_pago_total + $total, 2);
        $valoresTrabalho = am_servico_valores_fiscais_fatura($servico);

        $up = $con->prepare("
            UPDATE servico
            SET estado_pagamento = 'pago',
                metodo_pagamento = ?,
                data_pagamento = NOW(),
                telefone = ?,
                subtotal_sem_iva = ?,
                valor_iva = ?,
                valor_total_com_iva = ?,
                valor_pago_total = ?,
                valor_em_divida = 0,
                pedir_contribuinte_fatura = ?,
                contribuinte_fatura = ?,
                numero_fatura = COALESCE(NULLIF(numero_fatura, ''), ?)
            WHERE id = ? AND email = ?
        ");
        if ($up) {
            $up->bind_param(
                'ssddddissis',
                $metodo,
                $telefone_pag,
                $valoresTrabalho['subtotal_sem_iva'],
                $valoresTrabalho['valor_iva'],
                $valoresTrabalho['valor_total_com_iva'],
                $novo_valor_pago_total,
                $pedir_nif,
                $nif_guardar,
                $numero_fatura,
                $servico_id,
                $email_utilizador
            );
        }

        if ($up) {
            if ($up->execute()) {
                $estado_pag = 'pago';
                $precisa_pagar = false;
                $servico['numero_fatura'] = $numero_fatura;
                $codigo_levantamento_sucesso = (string) (am_codigo_levantamento_emitir($con, $servico_id, $email_utilizador) ?? '');
                if ($codigo_levantamento_sucesso !== '') {
                    $servico['codigo_levantamento'] = $codigo_levantamento_sucesso;
                    $corpoPg = '<p>Recebemos o pagamento do trabalho realizado.</p>';
                    $corpoPg .= '<p>Para levantar o veículo, apresente este código de 6 dígitos ao mecânico:</p>';
                    $corpoPg .= '<p style="font-size:28px;font-weight:800;letter-spacing:4px;color:#ea580c;margin:14px 0;">' . htmlspecialchars(am_codigo_levantamento_formatado($codigo_levantamento_sucesso), ENT_QUOTES, 'UTF-8') . '</p>';
                    $corpoPg .= '<p>Guarde este código até à entrega do veículo.</p>';
                    @am_enviar_email_cliente_automanager($email_utilizador, $nome_utilizador, 'AUTOMANAGER — Código de levantamento', $corpoPg);
                }
                $_SESSION['pay_flash_sucesso'] = 'Pagamento do trabalho realizado concluído com sucesso.';
                if ($codigo_levantamento_sucesso !== '') {
                    $_SESSION['pay_flash_codigo'] = $codigo_levantamento_sucesso;
                }
                header('Location: pagamento_orcamento.php?servico_id=' . $servico_id);
                exit;
            } else {
                $erro = 'Não foi possível concluir o pagamento.';
            }
            $up->close();
        } else {
            $erro = 'Erro interno ao preparar pagamento.';
        }
    }
}
$jsonMbwayPrefixos = json_encode(AM_MBWAY_PREFIXOS_2D, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
if (!is_string($jsonMbwayPrefixos)) {
    $jsonMbwayPrefixos = '[]';
}
$metodo_sel = ($_SERVER['REQUEST_METHOD'] === 'POST') ? trim((string)($_POST['metodo_pagamento_srv'] ?? '')) : '';
$tel_pag_val = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (string)($_POST['telefone_pagamento'] ?? '') : (string)($servico['telefone'] ?? '');
$pedir_nif_post = ($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['pedir_contribuinte_fatura']);
$nif_fatura_val = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (string)($_POST['contribuinte_fatura'] ?? '') : '';
$nif_servico = preg_replace('/\D+/', '', (string)($servico['nif'] ?? ''));
$nif_cliente_prefill = $nif_fatura_val !== ''
    ? preg_replace('/\D+/', '', $nif_fatura_val)
    : ($nif_utilizador !== '' ? $nif_utilizador : $nif_servico);
$mbwayPrefixosLista = is_array(AM_MBWAY_PREFIXOS_2D) ? AM_MBWAY_PREFIXOS_2D : [];
$eh_complemento = $valor_pago_total > 0.009 && $total > 0.009;
$pagamento_liquidado = !$precisa_pagar && am_servico_estado_pagamento_norm($servico['estado_pagamento'] ?? null) === 'pago';
$rotulo_ja_pago = $eh_complemento ? 'Já pago (adiantamento)' : 'Já pago';
$comparacao_pag = am_servico_comparacao_orcamento_total($servico, $total_trabalho_mecanico);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento — AUTOMANAGER</title>
    <?php require __DIR__ . '/inc_pagamento_head.php'; ?>
</head>
<body>
<main class="pay-wrap">
    <header class="pay-head">
        <a href="configuracoes.php?seccao=marcacoes" class="pay-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar</a>
        <div class="pay-head__title">
            <h1>Pagamento</h1>
            <p class="pay-head__sub">Liquidação do trabalho realizado na oficina</p>
        </div>
    </header>

    <section class="pay-card pay-card--summary">
        <?php if ($eh_complemento): ?>
            <div class="pay-kicker"><i class="fas fa-receipt" aria-hidden="true"></i> Complemento ao orçamento inicial</div>
        <?php endif; ?>
        <h2>Pagamento do trabalho realizado</h2>
        <div class="pay-service-meta">
            <div class="pay-service-meta__item"><span>Cliente</span><strong><?= htmlspecialchars($nome_utilizador) ?></strong></div>
            <div class="pay-service-meta__item"><span>Serviço</span><strong><?= htmlspecialchars((string)$servico['tipo_servico']) ?></strong></div>
            <div class="pay-service-meta__item"><span>Matrícula</span><strong><?= htmlspecialchars((string)$servico['matricula']) ?></strong></div>
            <div class="pay-service-meta__item"><span>Data</span><strong><?= !empty($servico['data_desejada']) ? date('d/m/Y', strtotime((string)$servico['data_desejada'])) : '-' ?></strong></div>
        </div>
        <?php if ($intervencao_pagamento_html !== ''): ?>
        <div class="pay-intervention"><?= $intervencao_pagamento_html ?></div>
        <?php endif; ?>
        <div class="pay-balance">
            <div class="pay-balance__rows">
                <div class="pay-balance__row">
                    <span>Valor final do trabalho</span>
                    <strong>€<?= number_format($total_trabalho_mecanico, 2, ',', ' ') ?></strong>
                </div>
                <?php if ($valor_pago_total > 0.009): ?>
                <div class="pay-balance__row">
                    <span><?= htmlspecialchars($rotulo_ja_pago) ?></span>
                    <strong>− €<?= number_format($valor_pago_total, 2, ',', ' ') ?></strong>
                </div>
                <?php endif; ?>
                <?php if (!empty($comparacao_pag['tem_orcamento']) && $comparacao_pag['status'] === 'acima'): ?>
                <div class="pay-balance__row">
                    <span>Diferença face ao orçamento</span>
                    <strong>+ €<?= number_format((float)$comparacao_pag['diferenca'], 2, ',', ' ') ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($pagamento_liquidado): ?>
            <div class="pay-balance__due pay-balance__due--settled">
                <span>Pagamento</span>
                <strong>Liquidado · €<?= number_format($valor_pago_total > 0 ? $valor_pago_total : $total_trabalho_mecanico, 2, ',', ' ') ?></strong>
            </div>
            <?php else: ?>
            <div class="pay-balance__due<?= $total <= 0.009 ? ' pay-balance__due--settled' : '' ?>">
                <span>Valor a pagar agora</span>
                <strong>€<?= number_format($total, 2, ',', ' ') ?></strong>
            </div>
            <?php endif; ?>
        </div>
        <p class="pay-hint pay-hint--below">O valor a pagar corresponde ao total registado pelo mecânico (horas + peças + IVA). O orçamento inicial foi apenas uma estimativa.</p>
    </section>

    <?php if ($erro !== ''): ?><div class="msg err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <?php if ($sucesso !== ''): ?><div class="msg ok"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

    <?php if (!$precisa_pagar): ?>
        <section class="pay-card done">
            <h2><i class="fas fa-circle-check"></i> Pagamento confirmado</h2>
            <p>O pagamento foi registado e a sua fatura já está disponível.</p>
            <?php if ($codigo_levantamento_sucesso !== ''): ?>
            <div class="msg ok" style="margin-top:14px;">
                Código de levantamento:
                <strong style="font-size:1.25rem;letter-spacing:3px;"><?= htmlspecialchars(am_codigo_levantamento_formatado($codigo_levantamento_sucesso), ENT_QUOTES, 'UTF-8') ?></strong><br>
                <span style="font-size:0.92rem;opacity:.92;">Apresente este código ao mecânico quando for levantar o veículo.</span>
            </div>
            <?php endif; ?>
            <div class="pay-actions">
                <a class="btn primary" href="configuracoes.php?seccao=marcacoes&ver_fatura=<?= (int)$servico_id ?>">Ver fatura</a>
            </div>
        </section>
    <?php else: ?>
        <form method="post" class="pay-card pay-card--form" id="payForm">
            <input type="hidden" name="servico_id" value="<?= (int)$servico_id ?>">

            <div class="pay-form-intro">
                <h2>Como queres pagar?</h2>
                <p class="pay-form-intro__meta">
                    Total a liquidar agora:
                    <strong>€<?= number_format($total, 2, ',', ' ') ?></strong>
                </p>
            </div>

            <section class="pay-form-step" aria-labelledby="payStepMetodo">
                <div class="pay-form-step__head">
                    <span class="pay-form-step__num" aria-hidden="true">1</span>
                    <div>
                        <h3 id="payStepMetodo">Método de pagamento</h3>
                        <p class="pay-form-step__hint" id="payMethodHint">Escolhe como queres concluir o pagamento.</p>
                    </div>
                </div>
                <div class="pay-methods" role="radiogroup" aria-label="Método de pagamento">
                    <label class="pay-method">
                        <input type="radio" name="metodo_pagamento_srv" value="mbway" <?= $metodo_sel === 'mbway' || $metodo_sel === '' ? 'checked' : '' ?> required>
                        <span class="pay-method__card">
                            <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
                            <span class="pay-method__title">MB WAY</span>
                            <span class="pay-method__desc">Pedido na app</span>
                        </span>
                    </label>
                    <label class="pay-method">
                        <input type="radio" name="metodo_pagamento_srv" value="cartao" <?= $metodo_sel === 'cartao' ? 'checked' : '' ?>>
                        <span class="pay-method__card">
                            <i class="fas fa-credit-card" aria-hidden="true"></i>
                            <span class="pay-method__title">Cartão</span>
                            <span class="pay-method__desc">Débito ou crédito</span>
                        </span>
                    </label>
                    <label class="pay-method">
                        <input type="radio" name="metodo_pagamento_srv" value="referencia_mb" <?= $metodo_sel === 'referencia_mb' ? 'checked' : '' ?>>
                        <span class="pay-method__card">
                            <i class="fas fa-barcode" aria-hidden="true"></i>
                            <span class="pay-method__title">Multibanco</span>
                            <span class="pay-method__desc">Entidade e referência</span>
                        </span>
                    </label>
                </div>
            </section>

            <section class="pay-form-step" aria-labelledby="payStepContacto">
                <div class="pay-form-step__head">
                    <span class="pay-form-step__num" aria-hidden="true">2</span>
                    <div>
                        <h3 id="payStepContacto">Dados para pagamento</h3>
                        <p class="pay-form-step__hint" id="payContactHint">Indica o telemóvel associado ao pagamento.</p>
                    </div>
                </div>

                <div class="pay-contact-panel">
                    <div class="field pay-field--phone">
                        <label for="telPagamento" id="telPagamentoLabel">Telemóvel</label>
                        <div class="pay-input-wrap">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <input id="telPagamento" type="tel" name="telefone_pagamento" maxlength="9" inputmode="numeric" autocomplete="tel-national" placeholder="912 345 678" required value="<?= htmlspecialchars($tel_pag_val, ENT_QUOTES, 'UTF-8') ?>" aria-describedby="mbwayPrefixHint payFieldNote">
                        </div>
                        <p class="pay-field-note" id="payFieldNote">9 dígitos, sem espaços.</p>
                    </div>

                    <div id="mbwayTelBlock" class="pay-mbway-extra" hidden>
                        <?php if ($mbwayPrefixosLista !== []): ?>
                        <p class="pay-hint" id="mbwayPrefixHint">O pedido MB WAY será enviado para este número. Prefixos aceites: <strong><?= htmlspecialchars(implode(', ', $mbwayPrefixosLista), ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                        <?php else: ?>
                        <p class="pay-hint" id="mbwayPrefixHint">O pedido MB WAY será enviado para este número (9 dígitos, começado por 9).</p>
                        <?php endif; ?>
                    </div>

                    <div id="cartaoFields" class="cartao-grid pay-card-panel">
                        <p class="pay-card-panel__title"><i class="fas fa-shield-halved" aria-hidden="true"></i> Dados do cartão</p>
                        <div class="field">
                            <label for="numeroCartao">Número do cartão</label>
                            <input id="numeroCartao" type="text" name="numero_cartao" maxlength="19" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000">
                        </div>
                        <div class="field">
                            <label for="nomeTitular">Nome do titular</label>
                            <input id="nomeTitular" type="text" name="nome_titular" autocomplete="cc-name" placeholder="Nome completo">
                        </div>
                        <div class="field">
                            <label for="validadeCartao">Validade</label>
                            <input id="validadeCartao" type="text" name="validade" maxlength="5" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA">
                        </div>
                        <div class="field">
                            <label for="cvvCartao">CVV</label>
                            <input id="cvvCartao" type="text" name="cvv" maxlength="4" inputmode="numeric" autocomplete="cc-csc" placeholder="123">
                        </div>
                    </div>

                    <div id="multibancoInfo" class="pay-ref-info" hidden>
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <p>Após confirmar, receberás a entidade e referência Multibanco para pagar no homebanking ou ATM.</p>
                    </div>
                </div>
            </section>

            <section class="pay-form-step pay-form-step--last" aria-labelledby="payStepFatura">
                <div class="pay-form-step__head">
                    <span class="pay-form-step__num" aria-hidden="true">3</span>
                    <div>
                        <h3 id="payStepFatura">Faturação</h3>
                        <p class="pay-form-step__hint">Opcional — só se precisares de NIF na fatura.</p>
                    </div>
                </div>

                <label class="pay-toggle" for="pedirNifChk">
                    <input type="checkbox" name="pedir_contribuinte_fatura" id="pedirNifChk" value="1" <?= $pedir_nif_post ? 'checked' : '' ?>>
                    <span class="pay-toggle__box">
                        <span class="pay-toggle__title">Incluir NIF na fatura</span>
                        <span class="pay-toggle__desc">Para empresa ou dedução em IRS</span>
                    </span>
                </label>
                <div id="nifBlock" class="pay-nif-block"<?= $pedir_nif_post ? '' : ' hidden' ?>>
                    <div class="field" style="margin-bottom:0;">
                        <label for="nifFatura">NIF do contribuinte</label>
                        <input id="nifFatura" type="text" name="contribuinte_fatura" maxlength="9" inputmode="numeric" placeholder="123456789" value="<?= htmlspecialchars($nif_fatura_val, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </section>

            <div class="pay-form-footer">
                <p class="pay-secure-note"><i class="fas fa-lock" aria-hidden="true"></i> Ligação segura · os teus dados não são guardados no cartão</p>
                <button class="btn primary pay-submit" type="submit">
                    <span class="pay-submit__label">Confirmar pagamento</span>
                    <span class="pay-submit__amount">€<?= number_format($total, 2, ',', ' ') ?></span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>

<script src="pagamento_cartao.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const metodoRadios = document.querySelectorAll('input[name="metodo_pagamento_srv"]');
  const cartaoBox = document.getElementById('cartaoFields');
  const mbwayBlock = document.getElementById('mbwayTelBlock');
  const multibancoInfo = document.getElementById('multibancoInfo');
  const methodHint = document.getElementById('payMethodHint');
  const contactHint = document.getElementById('payContactHint');
  const telPag = document.getElementById('telPagamento');
  const telPagLabel = document.getElementById('telPagamentoLabel');
  const payFieldNote = document.getElementById('payFieldNote');
  const form = document.getElementById('payForm');
  const nifChk = document.getElementById('pedirNifChk');
  const nifBlock = document.getElementById('nifBlock');
  const nifInput = document.getElementById('nifFatura');
  const nifClientePrefill = <?= json_encode($nif_cliente_prefill, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const AM_MBWAY_PREFIXOS = <?= $jsonMbwayPrefixos ?>;

  function metodoAtual() {
    const checked = document.querySelector('input[name="metodo_pagamento_srv"]:checked');
    return checked ? checked.value : '';
  }

  if (window.AMValidacaoCartao) {
    AMValidacaoCartao.bindCartaoFields(form || document);
  }

  function toggleNifBlock() {
    if (!nifBlock || !nifChk) return;
    const show = nifChk.checked;
    nifBlock.hidden = !show;
    if (nifInput) {
      if (show) {
        nifInput.setAttribute('required', 'required');
        if (!nifInput.value.trim() && nifClientePrefill) {
          nifInput.value = onlyDigits(nifClientePrefill);
        }
      } else {
        nifInput.removeAttribute('required');
        nifInput.value = '';
      }
    }
  }
  if (nifChk) {
    nifChk.addEventListener('change', toggleNifBlock);
    toggleNifBlock();
  }

  function onlyDigits(v) {
    return String(v || '').replace(/\D/g, '').slice(0, 9);
  }
  function validMovel9(t) {
    return /^9\d{8}$/.test(t);
  }
  function prefixoMbwayOk(t) {
    if (!Array.isArray(AM_MBWAY_PREFIXOS) || AM_MBWAY_PREFIXOS.length === 0) return true;
    const dois = t.slice(0, 2);
    return AM_MBWAY_PREFIXOS.indexOf(dois) !== -1;
  }
  function applyMetodo() {
    const val = metodoAtual();
    const isCard = val === 'cartao';
    const isMbway = val === 'mbway';
    const isRef = val === 'referencia_mb';
    if (methodHint) {
      if (isMbway) methodHint.textContent = 'Receberás um pedido de pagamento na app MB WAY.';
      else if (isCard) methodHint.textContent = 'Introduz os dados do cartão para concluir o pagamento.';
      else if (isRef) methodHint.textContent = 'Geramos entidade e referência para pagares no Multibanco.';
      else methodHint.textContent = 'Escolhe como queres concluir o pagamento.';
    }
    if (contactHint) {
      if (isMbway) contactHint.textContent = 'Indica o número onde queres receber o pedido MB WAY.';
      else if (isCard) contactHint.textContent = 'Telemóvel de contacto associado ao pagamento.';
      else if (isRef) contactHint.textContent = 'Telemóvel para envio da referência Multibanco.';
      else contactHint.textContent = 'Indica o telemóvel associado ao pagamento.';
    }
    if (telPagLabel) {
      telPagLabel.textContent = isMbway ? 'Telemóvel MB WAY' : 'Telemóvel';
    }
    if (payFieldNote) {
      payFieldNote.hidden = isMbway;
    }
    if (cartaoBox) {
      cartaoBox.style.display = isCard ? 'grid' : 'none';
      cartaoBox.querySelectorAll('input').forEach(function(i) {
        if (isCard) i.setAttribute('required', 'required');
        else i.removeAttribute('required');
      });
    }
    if (mbwayBlock) {
      mbwayBlock.hidden = !isMbway;
    }
    if (multibancoInfo) {
      multibancoInfo.hidden = !isRef;
    }
  }
  metodoRadios.forEach(function(r) {
    r.addEventListener('change', applyMetodo);
  });
  applyMetodo();

  if (form && telPag) {
    form.addEventListener('submit', function(e) {
      const notify = function(msg) {
        e.preventDefault();
        if (typeof amAlert === 'function') {
          amAlert(msg, { variant: 'warn', title: 'Pagamento' });
        } else {
          alert(msg);
        }
      };
      const metodo = metodoAtual();
      if (!metodo) {
        notify('Seleciona um método de pagamento.');
        return;
      }
      if (metodo === 'mbway') {
        const t = onlyDigits(telPag.value);
        if (!validMovel9(t)) {
          notify('Indica um telemóvel válido com 9 dígitos (começado por 9).');
          return;
        }
        if (!prefixoMbwayOk(t)) {
          notify('O prefixo deste número não está na lista aceite para MB WAY.');
          return;
        }
        return;
      }
      if (metodo === 'cartao' && window.AMValidacaoCartao) {
        const err = AMValidacaoCartao.validateCartao({
          numero_cartao: (form.querySelector('input[name="numero_cartao"]') || {}).value,
          validade: (form.querySelector('input[name="validade"]') || {}).value,
          cvv: (form.querySelector('input[name="cvv"]') || {}).value,
          nome_titular: (form.querySelector('input[name="nome_titular"]') || {}).value
        });
        if (err) {
          notify(err);
        }
      }
    });
  }
});
</script>
</body>
</html>

