# 2.2 Ficheiros PHP

O projeto **AutoManager** foi desenvolvido em **PHP**, responsável pela lógica da aplicação web e pela comunicação com a base de dados **MySQL**. A implementação segue uma arquitectura baseada em páginas PHP autónomas, complementada por ficheiros de inclusão (`inc_*.php`) que centralizam funcionalidades partilhadas. Para a publicação e gestão dos ficheiros no servidor de alojamento, pode ser utilizada uma ferramenta de transferência FTP, como o **FileZilla**.

A **ligação à base de dados** é estabelecida no ficheiro `ligar.php`, que inicializa a conexão MySQLi e define o charset UTF-8. Este ficheiro é incluído na maior parte das páginas da aplicação, funcionando como ponto único de acesso à base de dados.

A **página pública** do sistema é carregada através do `index.php`, onde se encontram a apresentação da oficina, o formulário de marcação de serviços e a secção de suporte. O **sistema de autenticação** é suportado por `login.php`, que trata o registo e o início de sessão de clientes, administradores, mecânicos, orçamentistas e chefes de oficina. A terminação de sessão é assegurada por `logout.php`.

A **área do cliente autenticado** é gerida principalmente por `configuracoes.php`, onde o utilizador pode consultar e editar dados pessoais, gerir veículos, acompanhar marcações, aceitar orçamentos e efectuar pagamentos. O acompanhamento do estado das reparações é disponibilizado em `rastreamento_reparacao.php`, e o **chat de suporte** é implementado em `chat.php`, com API de mensagens em `chat_api.php` e biblioteca partilhada em `chat_lib.php`.

O **painel de administração** é carregado através do `indexadmin.php`, sendo o ficheiro central onde estão implementadas as principais funcionalidades de gestão: utilizadores, veículos, marcações, catálogo, equipa (mecânicos, orçamentistas, chefes de oficina), feedbacks, chat e logs. O chat administrativo é tratado em `chat_admin.php`.

A **área do chefe de oficina** é suportada por `indexchefeoficina.php`, responsável pela atribuição de trabalhos, gestão de orçamentos e acompanhamento operacional da oficina. A **área do mecânico** é implementada em `funcionarios.php`, onde o técnico consulta e actualiza o estado dos serviços atribuídos. O **painel do orçamentista** encontra-se em `orcamentista.php`, dedicado à elaboração e gestão de orçamentos.

Para **funcionalidades complementares**, existem ainda ficheiros de apoio como `inc_orcamento_interativo.php` (linhas de orçamento e faturação), `inc_veiculo_perfil.php` (perfil técnico do veículo), `inc_utilizador_notificacoes.php` (notificações ao cliente), `inc_cancelamento_marcacao.php` (cancelamento de marcações), `pagamento_orcamento.php` e `metodopagamento.php` (fluxo de pagamento), `passaporte_veiculo.php` (histórico do veículo) e `mailer.php`, que encapsula o envio de emails através da biblioteca **PHPMailer**.

A **uniformização visual** das páginas é garantida por `inc_am_head.php`, que carrega automaticamente folhas de estilo, fontes e ícones consoante a página em execução, e por `header.php` e `footer.php` na área pública. O tema global (cores e tokens CSS) está definido em `theme.css`, referenciado pelo sistema de cabeçalho.

Para **pedidos assíncronos** (AJAX) e integrações internas, existem endpoints como `enviar_mensagem.php`, `obter_novas_mensagens.php`, `ajax_servicos.php`, `assumir_servico.php`, `concluir_servico.php`, `atualizar_estado_reparacao.php` e `api_bate_ponto.php`, que permitem actualizar o estado da aplicação sem recarregar a página completa.

---

## Os principais ficheiros de código são:

### Ligação e infraestrutura
- **`ligar.php`** — ligação à base de dados MySQL.
- **`check_session.php`** — verificação e gestão de sessões de visitantes.
- **`inc_am_head.php`** — cabeçalho HTML unificado (CSS, fontes, ícones por página).
- **`mailer.php`** — envio de emails com PHPMailer.
- **`config_telegram.php`** — configuração de notificações via Telegram.

### Área pública
- **`index.php`** — página inicial pública; marcação de serviços e apresentação da oficina.
- **`faq.php`** — perguntas frequentes.
- **`login.php`** — registo e autenticação de todos os tipos de utilizador.
- **`logout.php`** — terminação de sessão.
- **`feedback.php`** / **`processar_feedback.php`** — formulário e tratamento de feedbacks.
- **`termos.php`** — termos e condições de utilização.
- **`header.php`** / **`footer.php`** — cabeçalho e rodapé partilhados.

### Área do cliente
- **`configuracoes.php`** — área autenticada do cliente (dados, veículos, marcações, pagamentos).
- **`chat.php`** — chat de suporte com a administração.
- **`rastreamento_reparacao.php`** — acompanhamento do estado da reparação.
- **`passaporte_veiculo.php`** — histórico e passaporte digital do veículo.
- **`ver_orcamento.php`** — visualização e decisão sobre orçamentos.
- **`pagamento_orcamento.php`** / **`metodopagamento.php`** — processamento de pagamentos.
- **`download_fatura.php`** — emissão e download de fatura.
- **`solicitar_desbloqueio.php`** — pedido de desbloqueio de conta.

### Painel de administração
- **`indexadmin.php`** — painel principal de administração (gestão global da plataforma).
- **`chat_admin.php`** — chat de suporte (lado administrador).
- **`adicionar_funcionario.php`** — criação de funcionários (mecânicos, orçamentistas, chefes).

### Área do chefe de oficina
- **`indexchefeoficina.php`** — painel operacional do chefe de oficina.
- **`gestao_funcionarios_chefe.php`** — gestão da equipa pela perspectiva do chefe.
- **`login_chefe.php`** — autenticação específica do chefe de oficina.

### Área do mecânico
- **`funcionarios.php`** — painel do mecânico (serviços atribuídos e estados).
- **`bate_ponto.php`** / **`api_bate_ponto.php`** — registo de presenças.
- **`timer_servico.php`** — cronómetro de trabalho por serviço.
- **`assumir_servico.php`** / **`concluir_servico.php`** — assumir e concluir reparações.

### Área do orçamentista
- **`orcamentista.php`** — painel de elaboração e gestão de orçamentos.

### APIs e comunicação (chat)
- **`chat_api.php`** — API JSON do chat (enviar, listar, conversas).
- **`chat_lib.php`** — funções partilhadas do módulo de chat.
- **`enviar_mensagem.php`** / **`enviar_mensagem_admin.php`** — envio de mensagens.
- **`obter_novas_mensagens.php`** / **`obter_contador_nao_lidas.php`** — polling de mensagens.

### Bibliotecas de apoio (`inc_*.php`)
- **`inc_orcamento_interativo.php`** — orçamentos por linhas e faturação.
- **`inc_veiculo_perfil.php`** — perfil técnico e pedidos de actualização do veículo.
- **`inc_utilizador_notificacoes.php`** — notificações ao cliente sobre marcações.
- **`inc_cancelamento_marcacao.php`** — lógica de cancelamento e taxas.
- **`inc_eliminar_carro.php`** — eliminação de veículos.
- **`inc_notificar_cliente_email.php`** — notificações por email ao cliente.
- **`inc_chat_schema.php`** — criação e migração da tabela de chat.

### Inteligência artificial (módulo opcional)
- **`ai-chat.php`** — assistente conversacional.
- **`ai-estimativa.php`** / **`ai-agendamento.php`** — estimativas e agendamento assistido por AI.

---

## Estrutura simplificada do projeto

```
parabox/
├── ligar.php              → ligação MySQL
├── index.php              → site público
├── login.php              → autenticação
├── configuracoes.php      → área cliente
├── indexadmin.php         → painel admin
├── indexchefeoficina.php  → painel chefe
├── funcionarios.php       → painel mecânico
├── orcamentista.php       → painel orçamentista
├── chat.php / chat_admin.php
├── inc_*.php              → bibliotecas partilhadas
├── mailer.php             → emails (PHPMailer)
└── *.css / *.js           → interface
```

---

*Documento de apoio ao relatório — AutoManager.*
