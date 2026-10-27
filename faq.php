<?php
session_start();
require 'ligar.php';

$utilizador_logado = isset($_SESSION['user']['id_utilizador']) && !empty($_SESSION['user']['id_utilizador']);

$faq_sections = [
    [
        'id' => 'conta',
        'icon' => 'fa-user',
        'title' => 'Conta e acesso',
        'items' => [
            [
                'q' => 'Preciso de conta para marcar uma reparação?',
                'a' => '<p>Sim. O formulário <strong>Marcar reparação</strong> na página inicial só funciona com sessão iniciada. Podes criar conta em <a href="login.php?show=register">Criar conta</a> ou iniciar sessão em <a href="login.php">login</a>.</p>',
            ],
            [
                'q' => 'Onde altero os meus dados pessoais?',
                'a' => '<p>Em <a href="configuracoes.php?seccao=dados">Configurações → Dados pessoais</a> vês nome, email, telefone e NIF. Carrega em <strong>Editar</strong>, guarda as alterações e voltam a aparecer na lista.</p>
                <p class="faq-note">Estes dados pré-preenchem o passo 1 quando fazes uma nova marcação.</p>',
            ],
            [
                'q' => 'Como altero a password ou elimino a conta?',
                'a' => '<p>Na mesma área de <a href="configuracoes.php?seccao=dados">Dados pessoais</a> existem formulários para <strong>alterar password</strong> (password atual + nova) e para <strong>eliminar conta</strong>, que remove o utilizador da base de dados e termina a sessão.</p>',
            ],
            [
                'q' => 'Para que serve o ícone de utilizador no topo?',
                'a' => '<p>Abre <a href="configuracoes.php">Configurações</a>. Se tiveres notificações por ler (orçamento novo, atualização de estado, etc.), aparece um número laranja no ícone — o contador reflete o que está pendente na tua conta.</p>',
            ],
        ],
    ],
    [
        'id' => 'marcacoes',
        'icon' => 'fa-calendar-check',
        'title' => 'Marcações e agendamentos',
        'items' => [
            [
                'q' => 'Como marco uma reparação no site?',
                'a' => '<p>Com sessão iniciada, em 4 passos:</p>
                <ol class="faq-steps">
                    <li><strong>Passo 1 — Identificação</strong> — Na <a href="index.php#book-form">página inicial → Marcar reparação</a>: nome, telefone, NIF e email (muitas vezes já preenchidos).</li>
                    <li><strong>Passo 2 — Veículo</strong> — Marca, modelo e matrícula (ou escolha de um carro registado).</li>
                    <li><strong>Passo 3 — Serviço</strong> — Tipo de intervenção e <strong>plano de entrega</strong> (padrão ou premium 4h/8h/24h com taxa extra).</li>
                    <li><strong>Passo 4 — Agendamento</strong> — Data e hora desejadas. Confirmas com <strong>Enviar marcação</strong>.</li>
                    <li>A reserva aparece de imediato em <a href="configuracoes.php?seccao=marcacoes">Minhas marcações</a> e recebes email de confirmação.</li>
                </ol>
                <p>Alternativa: em Minhas Marcações, <strong>Nova marcação</strong> leva ao mesmo formulário. Também podes marcar por telefone <a href="tel:+351912964022">912 964 022</a>.</p>',
            ],
            [
                'q' => 'Posso cancelar ou remarcar uma marcação?',
                'a' => '<p>Em <a href="configuracoes.php?seccao=marcacoes">Minhas marcações</a>, cada reserva ativa tem <strong>Cancelar</strong> — o cancelamento é <strong>gratuito</strong> e a marcação passa para <strong>Marcações canceladas</strong>.</p>
                <p class="faq-note">O site pede confirmação numa janela própria (modal), não no alerta do browser. Para remarcar, cria uma nova marcação ou contacta-nos.</p>',
            ],
            [
                'q' => 'O que é o plano de entrega premium?',
                'a' => '<p>No passo 3 do formulário podes escolher <strong>Padrão</strong> (sem compromisso de horas) ou planos <strong>Premium 4h / 8h / 24h</strong> com valores extra indicados na lista. Isto regista prioridade na oficina; o valor final do serviço continua a depender do orçamento aprovado.</p>',
            ],
        ],
    ],
    [
        'id' => 'orcamentos',
        'icon' => 'fa-file-invoice-dollar',
        'title' => 'Orçamentos e pagamentos',
        'items' => [
            [
                'q' => 'Como funciona o orçamento no AutoManager?',
                'a' => '<ol class="faq-steps">
                    <li><strong>Marcação</strong> — ficas com a reserva em Minhas Marcações e email de confirmação.</li>
                    <li><strong>Orçamento enviado</strong> — na mesma página vês o valor (e linhas de peças/mão de obra se o orçamento for detalhado). Recebes notificação/email. Ainda não pagas.</li>
                    <li><strong>Decisão</strong> — <strong>Aceitar orçamento</strong> ou, se houver opções por linha, <strong>Aceitar esta composição</strong> / recusar itens ou o orçamento inteiro.</li>
                    <li><strong>Pagamento</strong> — só depois de aceitar: <strong>Ir para pagamento</strong> (MB WAY, cartão ou referência Multibanco, com IVA). Depois podes <strong>Ver fatura</strong>.</li>
                    <li><strong>Reparação</strong> — <strong>Tracking</strong> no cartão da marcação mostra a linha temporal: pedido → orçamento → aprovado → mecânico → em andamento → concluído.</li>
                </ol>
                <p class="faq-note">O pagamento do serviço é feito <strong>antes</strong> da reparação avançar, não no fim — tal como no ecrã de pagamento do site.</p>',
            ],
            [
                'q' => 'Que métodos de pagamento existem no site?',
                'a' => '<p>No ecrã de <strong>pagamento do trabalho realizado</strong> podes escolher:</p>
                <ul class="faq-steps">
                    <li><strong>MB WAY</strong> — com confirmação do número de telemóvel (9 dígitos).</li>
                    <li><strong>Cartão</strong> — dados do cartão no formulário.</li>
                    <li><strong>Referência Multibanco</strong> — pagamento por referência.</li>
                </ul>
                <p>Na oficina podem existir outras formas (numerário, etc.); o que está descrito aqui é o que o <strong>site</strong> processa online.</p>',
            ],
            [
                'q' => 'Posso escolher só algumas peças do orçamento?',
                'a' => '<p>Quando a oficina envia um orçamento <strong>por linhas</strong>, em Minhas Marcações podes aceitar ou recusar itens e depois confirmar a <strong>composição</strong> antes de pagar. O total a pagar corresponde ao que aceitaste.</p>',
            ],
        ],
    ],
    [
        'id' => 'veiculos',
        'icon' => 'fa-car',
        'title' => 'Veículos',
        'items' => [
            [
                'q' => 'Como registo ou edito um veículo?',
                'a' => '<p>Em <a href="configuracoes.php?seccao=veiculos">Configurações → Veículos</a> podes adicionar carros (marca, modelo, matrícula, ano, foto, etc.) e marcar um como <strong>principal</strong>. O veículo principal pré-preenche o passo 2 das novas marcações.</p>
                <p>Se tiveres vários carros, no formulário de marcação aparece uma lista para escolher a matrícula registada.</p>',
            ],
            [
                'q' => 'Como elimino um veículo da minha conta?',
                'a' => '<p>Em <a href="configuracoes.php?seccao=veiculos">Veículos</a>, cada cartão tem <strong>Eliminar</strong>:</p>
                <ol class="faq-steps">
                    <li>Se <strong>não houver marcações ativas</strong> para essa matrícula, o carro é removido logo (lista, perfil e foto).</li>
                    <li>Se houver <strong>marcações em curso</strong>, o site avisa e cancela-as automaticamente ao eliminar o veículo — sem qualquer taxa.</li>
                </ol>',
            ],
        ],
    ],
    [
        'id' => 'reparacao',
        'icon' => 'fa-route',
        'title' => 'Acompanhamento da reparação',
        'items' => [
            [
                'q' => 'Como acompanho o estado da minha reparação?',
                'a' => '<p>Em <a href="configuracoes.php?seccao=marcacoes">Minhas marcações</a>, no cartão do serviço, usa <strong>Tracking</strong>. Abre a mesma linha temporal do site (pedido recebido, orçamento, aprovado, atribuído ao mecânico, em andamento, concluído).</p>
                <p>Também recebes emails e o contador de notificações no ícone de utilizador quando há novidades.</p>',
            ],
            [
                'q' => 'Quanto tempo demora uma revisão geral?',
                'a' => '<p>Em média, <strong>2 a 4 horas</strong>, conforme o veículo. Serviços mais complexos podem exigir o carro um dia inteiro na oficina. O prazo concreto vem no orçamento e nas atualizações de tracking.</p>',
            ],
            [
                'q' => 'Têm garantia nos serviços?',
                'a' => '<p>Sim, de forma geral:</p>
                <ul class="faq-steps">
                    <li>Reparações — 12 meses</li>
                    <li>Peças novas — 24 meses</li>
                    <li>Revisões — 6 meses ou 10 000 km</li>
                </ul>
                <p>A garantia cobre mão de obra e peças fornecidas por nós. Detalhes específicos constam na documentação da intervenção.</p>',
            ],
        ],
    ],
];

$faq_total = 0;
foreach ($faq_sections as $faq_sec) {
    $faq_total += count($faq_sec['items']);
}

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<?php require __DIR__ . '/inc_am_head.php'; ?>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FAQ — AUTOMANAGER</title>
    <meta name="description" content="Perguntas frequentes sobre marcações, orçamentos, pagamentos e veículos no AutoManager." />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="faq.css?v=9">
</head>
<body class="faq-page">
<?php include 'header.php'; ?>

<main class="faq-layout" id="faq-top">
    <aside class="faq-sidebar">
        <div class="faq-sidebar-head">
            <h1>Perguntas frequentes</h1>
            <p class="faq-sidebar-lead">Tudo o que podes fazer no site AutoManager, passo a passo.</p>
            <span class="faq-sidebar-meta"><?= count($faq_sections) ?> temas · <?= (int) $faq_total ?> respostas</span>
        </div>
        <div class="faq-search" role="search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="faqSearch" placeholder="Pesquisar…" autocomplete="off" aria-controls="faqContent" aria-label="Pesquisar nas perguntas frequentes">
            <button type="button" class="faq-search-clear" id="faqSearchClear" hidden aria-label="Limpar pesquisa"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <p class="faq-search-count" id="faqSearchCount" aria-live="polite"></p>
        <nav class="faq-nav" id="faqQuickNav" aria-label="Temas">
            <?php foreach ($faq_sections as $i => $section): ?>
                <a href="#faq-<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>" data-faq-nav="<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $i === 0 ? ' class="is-active"' : '' ?>>
                    <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="faq-sidebar-actions">
            <?php if ($utilizador_logado): ?>
                <a href="index.php#book-form" class="faq-btn faq-btn--solid">Marcar reparação</a>
                <a href="configuracoes.php?seccao=marcacoes" class="faq-btn faq-btn--line">Minhas marcações</a>
            <?php else: ?>
                <a href="login.php" class="faq-btn faq-btn--solid">Iniciar sessão</a>
                <a href="login.php?show=register" class="faq-btn faq-btn--line">Criar conta</a>
            <?php endif; ?>
            <a href="index.php" class="faq-btn faq-btn--ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao site</a>
        </div>
    </aside>

    <div class="faq-main">
        <div class="faq-toolbar">
            <button type="button" id="faqExpandAll">Expandir tudo</button>
            <button type="button" id="faqCollapseAll">Fechar tudo</button>
        </div>

        <div id="faqContent" class="faq-content">
        <?php
        $faq_item_idx = 0;
        foreach ($faq_sections as $section):
        ?>
            <section class="faq-section" id="faq-<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>" data-faq-section>
                <div class="faq-section-head">
                    <span class="faq-section-icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($section['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                    <h2><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <div class="faq-list">
                    <?php foreach ($section['items'] as $item): ?>
                        <?php
                        $faq_item_idx++;
                        $searchBlob = mb_strtolower(
                            ($section['title'] ?? '') . ' ' . ($item['q'] ?? '') . ' ' . strip_tags($item['a'] ?? ''),
                            'UTF-8'
                        );
                        ?>
                        <article class="faq-item" data-faq-item data-faq-id="<?= (int) $faq_item_idx ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                            <h3 class="faq-item-title">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span><?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="faq-chevron" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
                                </button>
                            </h3>
                            <div class="faq-answer">
                                <div class="faq-answer-inner">
                                    <div class="faq-answer-body">
                                        <?= $item['a'] ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        </div>

        <p class="faq-empty" id="faqEmpty" role="status">Nenhuma pergunta corresponde à pesquisa. Tenta outras palavras ou <a href="#faq-top">limpa o campo</a>.</p>

        <aside class="faq-cta">
            <div class="faq-cta-text">
                <h3>Ainda com dúvidas?</h3>
                <p>A nossa equipa responde por telefone ou email durante o horário da oficina.</p>
            </div>
            <div class="faq-cta-actions">
                <a href="tel:+351912964022" class="faq-btn faq-btn--solid">912 964 022</a>
                <a href="mailto:suporte@AUTOMANAGER.pt" class="faq-btn faq-btn--line">Email</a>
                <a href="index.php#contact" class="faq-btn faq-btn--line">Contactos</a>
            </div>
        </aside>
    </div>
</main>

<script src="faq.js?v=2" defer></script>

<?php define('AM_HIDE_FLOATING_CLOCK', true); include 'footer.php'; ?>
</body>
</html>
