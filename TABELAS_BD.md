# AutoManager — Tabelas da Base de Dados

Documentação das tabelas MySQL/MariaDB utilizadas pelo projeto **AutoManager**, identificadas através do código-fonte PHP.

**Base de dados:** `automanager`  
**Motor:** MySQL / MariaDB (ligação via `ligar.php` — mysqli)

---

## Índice

1. [Resumo rápido](#1-resumo-rápido)
2. [Utilizadores e autenticação](#2-utilizadores-e-autenticação)
3. [Marcações e reparações](#3-marcações-e-reparações)
4. [Veículos](#4-veículos)
5. [Orçamentos e pagamentos](#5-orçamentos-e-pagamentos)
6. [Comunicação e suporte](#6-comunicação-e-suporte)
7. [Equipa e recursos humanos](#7-equipa-e-recursos-humanos)
8. [Site, sessões e marketing](#8-site-sessões-e-marketing)
9. [Passaporte digital](#9-passaporte-digital)
10. [Inteligência artificial](#10-inteligência-artificial)
11. [Integrações](#11-integrações)
12. [Tabelas legadas / paralelas](#12-tabelas-legadas--paralelas)
13. [Diagrama de relações](#13-diagrama-de-relações)

---

## 1. Resumo rápido

| # | Tabela | Tipo | Uso principal |
|---|--------|------|---------------|
| 1 | `utilizador` | Principal | Clientes registados |
| 2 | `administrador` | Principal | Contas de administração |
| 3 | `mecanicos` | Principal | Mecânicos da oficina |
| 4 | `orcamentista` | Principal | Orçamentistas |
| 5 | `chefes_oficina` | Principal | Chefes de oficina |
| 6 | `servico` | **Central** | Marcações, orçamentos, pagamentos, trabalhos |
| 7 | `carro` | Principal | Veículos dos clientes |
| 8 | `carro_perfil` | Principal | Perfil técnico do veículo |
| 9 | `carro_perfil_pedido` | Principal | Pedidos de dados ao cliente |
| 10 | `servico_orcamento_item` | Principal | Linhas de orçamento interativo |
| 11 | `chat_mensagens` | Principal | Chat cliente ↔ admin |
| 12 | `feedback_duvidas` | Principal | Feedbacks e dúvidas |
| 13 | `sessoes_temporarias` | Principal | Visitantes temporários (2 min) |
| 14 | `avaliacoes_temp` | Principal | Avaliações pós-visita |
| 15 | `estado_site` | Config | Modo de manutenção |
| 16 | `atualizacoes_site` | Config | Banners de atualização |
| 17 | `presenca` | RH | Bate-ponto dos mecânicos |
| 18 | `historico_salarios` | RH | Histórico de alterações salariais |
| 19 | `pedidos_desbloqueio` | Suporte | Pedidos de desbloqueio de conta |
| 20 | `passaporte_eventos` | Inovação | Eventos do passaporte digital |
| 21 | `passaporte_links_partilha` | Inovação | Links públicos de partilha |
| 22 | `logs` | Sistema | Registos de atividade |
| 23 | `telegram_config` | Integração | Configuração do bot Telegram |
| 24 | `emails_marketing` | Marketing | Campanhas de email |
| 25 | `ai_conversas` | AI | Conversas do AutoAssist |
| 26 | `ai_diagnosticos` | AI | Diagnósticos registados |
| 27 | `logs_visitas_temporarias` | Sistema | Histórico de páginas visitadas (sessões temp.) |
| 28 | `orcamentos` | Legado | Orçamentos antigos (referência) |
| 29 | `reparacoes` | Legado | Reparações antigas (setup) |
| 30 | `clientes` | Legado | Clientes antigos (setup) |
| 31 | `servicos` | Legado | Variante antiga de `servico` |

> **Nota:** A tabela **`servico`** é o núcleo operacional do sistema. Muitas colunas são adicionadas automaticamente em runtime (`ALTER TABLE`) conforme as funcionalidades evoluem.

---

## 2. Utilizadores e autenticação

### `utilizador`
Clientes registados no site.

| Coluna | Descrição |
|--------|-----------|
| `id_utilizador` | Chave primária |
| `nome_utilizador` | Nome completo |
| `email` | Email (login) |
| `password` | Password encriptada (BCrypt) |
| `carro` | Descrição legada do veículo (texto) |
| `telefone` | Telefone de contacto |
| `nif` | NIF português |
| `bloqueado` | Conta bloqueada (0/1) |
| `data_bloqueio` | Data do bloqueio |
| `termos_aceite` | Aceitação dos termos |
| `data_aceite` | Data de aceitação |
| `metodo_pagamento_preferido` | MB Way, cartão, etc. |
| `notif_cliente_pulse_ack` | Controlo de notificações lidas |
| `reset_code` / `reset_expire` | Recuperação de password |
| `created_at` / `updated_at` | Timestamps |

**Usada em:** `login.php`, `configuracoes.php`, `chat.php`, `indexadmin.php`

---

### `administrador`
Contas de administração do sistema.

| Coluna | Descrição |
|--------|-----------|
| `id_admin` | Chave primária |
| `username` | Nome de utilizador |
| `email` | Email (login) |
| `password` | Password encriptada |
| `nivel` | Nível de acesso |
| `reset_code` / `reset_expire` | Recuperação de password |

**Usada em:** `login.php`, `indexadmin.php`

---

### `pedidos_desbloqueio`
Pedidos de clientes bloqueados para reativar a conta.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `id_utilizador` | FK → `utilizador` |
| `email` | Email do cliente |
| `nome_utilizador` | Nome |
| `motivo` | Motivo do pedido |
| `mensagem` | Mensagem detalhada |
| `data_pedido` | Data de submissão |
| `estado` | `pendente` / `aprovado` / `rejeitado` |
| `data_resposta` | Data da resposta do admin |
| `resposta` | Resposta do administrador |

**Usada em:** `solicitar_desbloqueio.php`

---

## 3. Marcações e reparações

### `servico` ⭐ (tabela central)
Armazena marcações, estados de orçamento, pagamentos, atribuição de mecânicos e progresso do trabalho.

#### Dados do cliente e veículo
| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `nome` | Nome do cliente |
| `email` | Email |
| `telefone` | Telefone |
| `nif` | NIF |
| `marca` | Marca do veículo |
| `modelo` | Modelo do veículo |
| `matricula` | Matrícula |
| `id_utilizador` | FK opcional → `utilizador` |
| `id_carro` | FK opcional → `carro` |

#### Marcação
| Coluna | Descrição |
|--------|-----------|
| `tipo_servico` | Tipo de reparação/serviço |
| `data_desejada` | Data pretendida |
| `hora_desejada` | Hora pretendida |
| `custo` | Valor estimado/final |
| `data_registo` / `created_at` | Data de criação |
| `estado_marcacao` | `ativa` / `cancelada` |
| `data_cancelamento` | Data de cancelamento |
| `taxa_cancelamento` | Valor da taxa (15 €) |
| `taxa_cancelamento_paga` | Taxa paga (0/1) |
| `metodo_pagamento_taxa_cancel` | Método de pagamento da taxa |

#### Orçamento
| Coluna | Descrição |
|--------|-----------|
| `estado_orcamento` | `pendente` / `aprovado` / `recusado` / `em_analise` |
| `orcamento_aprovado` | Valor do orçamento |
| `data_orcamento` | Data do orçamento |
| `observacoes_orcamento` | Observações |
| `estado_orcamento_cliente` | `pendente` / `aceite` / `recusado` |
| `data_orcamento_aceite` | Data de aceitação |
| `data_orcamento_recusado` | Data de recusa |
| `aviso_chefe_orcamento_aceite` | Flag de notificação ao chefe |
| `aviso_chefe_orcamento_recusado` | Flag de notificação ao chefe |
| `id_orcamentista` | FK → `orcamentista` |

#### Pagamento e fatura
| Coluna | Descrição |
|--------|-----------|
| `estado_pagamento` | `Pendente` / `Pago` / etc. |
| `metodo_pagamento` | MB Way, cartão, referência MB |
| `data_pagamento` | Data do pagamento |
| `token_pagamento` | Token de acesso ao pagamento |
| `subtotal_sem_iva` | Subtotal |
| `valor_iva` | Valor do IVA (23 %) |
| `valor_total_com_iva` | Total com IVA |
| `numero_fatura` | Número da fatura |
| `fatura_linhas_json` | Linhas da fatura (JSON) |
| `pedir_contribuinte_fatura` | Pedir NIF na fatura |
| `contribuinte_fatura` | NIF para fatura |

#### Trabalho e mecânico
| Coluna | Descrição |
|--------|-----------|
| `estado_do_trabalho` | `Por Fazer` / `Em Andamento` / `Pronto` / `concluido` |
| `estado` | Estado legado |
| `mecanico_id` / `id_mecanico` | FK → `mecanicos` (compatibilidade) |
| `hora_inicio_trabalho` | Início do timer |
| `hora_fim_trabalho` | Fim do timer |
| `tempo_trabalho_minutos` | Minutos trabalhados |
| `data_conclusao` | Data de conclusão |
| `notas_internas` | Notas da oficina (admin/chefe) |
| `notas_mecanico` | Notas do mecânico |
| `data_atualizacao_estado` | Última atualização de estado |

#### SLA (planos premium)
| Coluna | Descrição |
|--------|-----------|
| `plano_pronto` | `normal` / `premium_4h` / `premium_8h` / `premium_24h` |
| `sla_prometido_horas` | Horas prometidas |
| `sla_inicio_at` | Início do SLA |
| `sla_limite_at` | Limite do SLA |
| `sla_estado` | Estado do SLA (`atrasado`, etc.) |
| `sla_compensacao_tipo` | Tipo de compensação |
| `sla_compensacao_valor` | Valor da compensação |

**Usada em:** `index.php`, `configuracoes.php`, `indexadmin.php`, `indexchefeoficina.php`, `funcionarios.php`, `orcamentista.php`, `pagamento_orcamento.php`, `rastreamento_reparacao.php`, `timer_servico.php`, `concluir_servico.php`, `assumir_servico.php`

---

## 4. Veículos

### `carro`
Veículos registados pelos clientes.

| Coluna | Descrição |
|--------|-----------|
| `id_carro` | Chave primária |
| `id_utilizador` | FK → `utilizador` |
| `marca` | Marca |
| `modelo` | Modelo |
| `ano` | Ano de fabrico |
| `matricula` | Matrícula (formato PT) |
| `is_principal` | Veículo principal (0/1) |
| `notas` | Notas gerais |
| `created_at` | Data de registo |

**Usada em:** `configuracoes.php`, `index.php`, `login.php`, `indexadmin.php`, `passaporte_veiculo.php`

---

### `carro_perfil`
Perfil técnico detalhado (1:1 com `carro`).

| Coluna | Descrição |
|--------|-----------|
| `id_carro` | PK / FK → `carro` |
| `propulsao` | Gasolina, diesel, elétrico, etc. |
| `motorizacao` | Motorização |
| `vin` | Número de chassis |
| `km_atual` | Quilometragem |
| `consumo_l_100` | Consumo médio |
| `autonomia_media_km` | Autonomia (elétrico) |
| `capacidade_deposito_litros` | Depósito |
| `kwh_bateria` | Capacidade da bateria |
| `tipo_oleo` | Tipo de óleo |
| `pneus_medida` | Medida dos pneus |
| `pneus_pressao` | Pressão recomendada |
| `data_ultima_inspecao` | Última inspeção |
| `data_ultimo_servico` | Último serviço |
| `seguro_seguradora` | Seguradora |
| `seguro_apolice` | Apólice |
| `seguro_validade` | Validade do seguro |
| `foto_relativa` | Caminho da foto |
| `notas_oficina` | Notas internas |
| `atualizado_por` | Quem atualizou (`cliente` / `mecanico`) |
| `atualizado_em` | Data de atualização |

**Usada em:** `configuracoes.php`, `inc_veiculo_perfil.php`, `mecanico_guardar_perfil_fecho.php`

---

### `carro_perfil_pedido`
Pedidos do mecânico ao cliente para completar dados do veículo.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `id_carro` | FK → `carro` |
| `id_utilizador` | FK → `utilizador` |
| `id_servico` | FK opcional → `servico` |
| `id_mecanico` | FK → `mecanicos` |
| `estado` | `pendente` / `resolvido` |
| `created_at` | Data do pedido |
| `resolved_at` | Data de resolução |

**Usada em:** `inc_veiculo_perfil.php`, `mecanico_pedir_perfil.php`

---

## 5. Orçamentos e pagamentos

### `servico_orcamento_item`
Linhas detalhadas do orçamento interativo (peças, mão de obra, taxas).

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `servico_id` | FK → `servico` |
| `orden` | Ordem de apresentação |
| `categoria` | `peca` / `mao_obra` / `outro` / `taxa` |
| `descricao` | Descrição da linha |
| `quantidade` | Quantidade |
| `preco_unitario` | Preço unitário |
| `minutos_trabalho` | Minutos estimados |
| `grupo_alternativa` | Grupo de alternativas |
| `decisao_cliente` | `aceite` / `recusado` / `pendente` |

**Usada em:** `inc_orcamento_interativo.php`, `configuracoes.php`, `indexchefeoficina.php`

---

### `orcamentos` (legado)
Tabela antiga referenciada ao eliminar orçamentistas. Pode coexistir com o fluxo atual baseado em `servico`.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `id_orcamentista` | FK → `orcamentista` |

**Usada em:** `indexadmin.php` (verificação antes de eliminar orçamentista)

---

## 6. Comunicação e suporte

### `chat_mensagens`
Mensagens do chat de suporte cliente ↔ administrador.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `de_usuario_id` | ID do remetente (`NULL` = admin) |
| `de_usuario_nome` | Nome do remetente |
| `para_usuario_id` | ID do destinatário (`0` = admin) |
| `mensagem` | Texto da mensagem |
| `data_envio` | Data/hora de envio |
| `lida_admin` | Lida pelo admin (0/1) |
| `lida_usuario` | Lida pelo cliente (0/1) |

**Usada em:** `chat.php`, `chat_admin.php`, `enviar_mensagem.php`, `obter_novas_mensagens.php`

---

### `feedback_duvidas`
Feedbacks e dúvidas submetidos no site.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `nome` | Nome do utilizador |
| `email` | Email |
| `tipo` | `feedback` / `duvida` |
| `assunto` | Assunto |
| `mensagem` | Mensagem |
| `resposta` | Resposta do admin |
| `estado` | `pendente` / `respondido` / `resolvido` |
| `data_envio` | Data de envio |
| `data_resposta` | Data da resposta |

**Usada em:** `feedback.php`, `processar_feedback.php`, `indexadmin.php`

---

## 7. Equipa e recursos humanos

### `mecanicos`
Mecânicos da oficina.

| Coluna | Descrição |
|--------|-----------|
| `id_mecanico` | Chave primária |
| `nome_mecanico` | Nome |
| `email` | Email (login) |
| `password` | Password encriptada |
| `contacto` | Telefone |
| `morada` | Morada |
| `especialidade` | Especialidade técnica |
| `ordenado_bruto` / `salario_bruto` | Salário |
| `data_contratacao` | Data de contratação |
| `nif` | NIF |
| `iban` | IBAN |
| `ativo` | Conta ativa (0/1) |
| `primeiro_login` | Forçar alteração de password |
| `id_chefe` | FK → `chefes_oficina` |
| `data_despedimento` | Data de despedimento |
| `motivo_despedimento` | Motivo |
| `reset_code` / `reset_expire` | Recuperação de password |
| `created_at` / `updated_at` | Timestamps |

**Usada em:** `funcionarios.php`, `login.php`, `indexadmin.php`, `bate_ponto.php`, `indexchefeoficina.php`

---

### `orcamentista`
Orçamentistas.

| Coluna | Descrição |
|--------|-----------|
| `id_orcamentista` | Chave primária |
| `nome_orcamentista` | Nome |
| `email` | Email (login) |
| `password` | Password encriptada |
| `morada` | Morada |
| `telefone` | Telefone |
| `salario_bruto` | Salário |
| `data_contratacao` | Data de contratação |
| `nif` | NIF |
| `iban` | IBAN |
| `especialidade` | Especialidade |
| `ativo` | Conta ativa (0/1) |
| `primeiro_login` | Forçar alteração de password |
| `data_despedimento` | Data de despedimento |
| `motivo_despedimento` | Motivo |
| `reset_code` / `reset_expire` | Recuperação de password |
| `created_at` / `updated_at` | Timestamps |

**Usada em:** `orcamentista.php`, `login.php`, `indexadmin.php`

---

### `chefes_oficina`
Chefes de oficina.

| Coluna | Descrição |
|--------|-----------|
| `id_chefe` | Chave primária |
| `nome` | Nome |
| `email` | Email (login) |
| `password` | Password encriptada |
| `telefone` | Telefone |
| `nivel_acesso` | Nível de acesso |
| `ativo` | Conta ativa (0/1) |
| `primeiro_login` | Forçar alteração de password |
| `reset_code` / `reset_expire` | Recuperação de password |
| `created_at` | Data de criação |

**Usada em:** `indexchefeoficina.php`, `login.php`, `indexadmin.php`, `gestao_funcionarios_chefe.php`

---

### `presenca`
Registo de bate-ponto dos mecânicos.

| Coluna | Descrição |
|--------|-----------|
| `id_presenca` | Chave primária |
| `id_mecanico` | FK → `mecanicos` |
| `data` | Data do registo |
| `hora_entrada` | Hora de entrada |
| `hora_saida` | Hora de saída |
| `timestamp_entrada` | Timestamp de entrada |
| `timestamp_saida` | Timestamp de saída |
| `total_horas` | Total de horas |
| `falta` | Marcada como falta (0/1) |
| `justificacao` | Justificação |
| `criado_em` / `atualizado_em` | Timestamps |

**Usada em:** `bate_ponto.php`, `api_bate_ponto.php`

---

### `historico_salarios`
Auditoria de alterações salariais.

| Coluna | Descrição |
|--------|-----------|
| `id_historico` | Chave primária |
| `id_mecanico` | FK → `mecanicos` |
| `salario_anterior` | Salário anterior |
| `salario_novo` | Novo salário |
| `data_alteracao` | Data da alteração |
| `motivo_alteracao` | Motivo |
| `alterado_por` | FK → `chefes_oficina` |
| `criado_em` | Timestamp |

**Usada em:** `api_aumentar_salario.php`, `gestao_funcionarios_chefe.php`

---

## 8. Site, sessões e marketing

### `estado_site`
Modo de manutenção do site.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária (fixo: 1) |
| `estado` | `0` = online / `1` = manutenção |
| `mensagem` | Mensagem exibida aos visitantes |
| `data_atualizacao` | Última alteração |

**Usada em:** `login.php`, `indexadmin.php`

---

### `atualizacoes_site`
Banners de novidades no site público.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `mensagem` | Texto da atualização |
| `data_publicacao` | Data de publicação |

**Usada em:** `index.php`, `marcar_mensagem_vista.php`

---

### `sessoes_temporarias`
Sessões de visitantes não autenticados (limite de 2 minutos).

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `ip_address` | Endereço IP |
| `user_agent` | Browser/dispositivo |
| `primeiro_acesso` | Primeiro acesso |
| `ultimo_acesso` | Último acesso |
| `tempo_restante` | Segundos restantes |
| `visitas` | Número de visitas |
| `pagina_atual` | Última página visitada |
| `expirou` | Sessão expirada (0/1) |
| `utilizador_id` | FK opcional → `utilizador` (após login) |

**Usada em:** `check_session.php`, `login.php`, `verificar_tempo.php`

---

### `avaliacoes_temp`
Avaliações de visitantes temporários após expiração da sessão.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `nome` | Nome |
| `email` | Email |
| `avaliacao` | Nota (1–5) |
| `comentario` | Comentário |
| `ip_address` | IP do visitante |
| `data_avaliacao` | Data da avaliação |

**Usada em:** `avaliacao_popup.php`, `index.php`, `cron_emails.php`

---

### `emails_marketing`
Lista de emails para campanhas de follow-up.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `email` | Email |
| `nome` | Nome |
| `ativo` | Subscrição ativa (0/1) |
| `token_optout` | Token para cancelar subscrição |
| `ultimo_envio` | Data do último email |
| `total_envios` | Contador de envios |
| `data_registo` | Data de registo |

**Usada em:** `cron_emails.php`, `optout.php`

---

### `logs`
Registos de atividade do sistema.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `origem` | Origem do log (módulo) |
| `mensagem` | Mensagem |
| `dados` | Dados adicionais (JSON/texto) |
| `criado_em` | Timestamp |

**Usada em:** `indexadmin.php`, `indexchefedeoficina.php`

---

### `logs_visitas_temporarias`
Histórico de páginas visitadas por sessões temporárias.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `sessao_id` | FK → `sessoes_temporarias` |
| `ip_address` | Endereço IP |
| `pagina` | URL/página visitada |
| `data_acesso` | Data/hora do acesso |

**Usada em:** `verificar_sessoes.php`

---

## 9. Passaporte digital

### `passaporte_eventos`
Histórico de eventos por veículo (inspeções, reparações, etc.).

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `email` | Email do proprietário |
| `matricula` | Matrícula |
| `tipo` | Tipo de evento |
| `descricao` | Descrição |
| `odometro` | Quilometragem no evento |
| `data_evento` | Data do evento |
| `origem` | Origem (`sistema`, `manual`, etc.) |
| `hash_evento` | Hash para evitar duplicados |
| `created_at` | Data de criação |

**Usada em:** `passaporte_veiculo.php`, `inc_inovacao.php`

---

### `passaporte_links_partilha`
Links públicos temporários para partilhar o passaporte.

| Coluna | Descrição |
|--------|-----------|
| `id` | Chave primária |
| `email` | Email do proprietário |
| `matricula` | Matrícula |
| `token` | Token único de acesso |
| `expira_em` | Data de expiração (7 dias) |
| `revogado_em` | Data de revogação |
| `views` | Número de visualizações |
| `created_at` | Data de criação |

**Usada em:** `passaporte_veiculo.php`

---

## 10. Inteligência artificial

### `ai_conversas`
Registo de conversas do módulo AutoAssist AI.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `data_registro` | Data da conversa |
| *(outras colunas)* | Metadados da sessão AI |

**Usada em:** `gerar_relatorio.php`, `ai-chat.php`

---

### `ai_diagnosticos`
Diagnósticos identificados pelo módulo AI.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `problema_identificado` | Problema detetado |
| `data_analise` | Data da análise |
| *(outras colunas)* | Detalhes do diagnóstico |

**Usada em:** `gerar_relatorio.php`

---

## 11. Integrações

### `telegram_config`
Configuração do bot Telegram para notificações.

| Coluna (inferida) | Descrição |
|-------------------|-----------|
| `id` | Chave primária |
| `bot_token` | Token do bot |
| `chat_id_admin` | Chat ID do administrador |
| `ativo` | Configuração ativa (0/1) |

**Usada em:** `config_telegram.php`, `enviar_notificacao_telegram.php`, `obter_chat_id.php`

> Alternativa: ficheiro `telegram_config.json` no servidor (sem BD).

---

## 12. Tabelas legadas / paralelas

Estas tabelas existem no projeto mas são **menos utilizadas** no fluxo principal atual (substituídas por `servico` + `utilizador`).

### `servicos`
Variante antiga da tabela de serviços. Referenciada em scripts de diagnóstico (`colunas_bd.php`, `diagnostico_bd.php`). O código ativo usa **`servico`** (singular).

### `reparacoes`
Tabela de reparações do setup inicial. Usada em protótipos e painéis legados (`indexchefedeoficina.php`, `setup_completo.php`).

| Coluna | Descrição |
|--------|-----------|
| `id_reparacao` | Chave primária |
| `descricao` | Descrição |
| `estado` | Estado |
| `data_entrada` | Data de entrada |
| `mecanico_atribuido` | Nome do mecânico |
| `cliente_id` | ID do cliente |
| `prioridade` | Prioridade |
| `data_conclusao` | Data de conclusão |
| `observacoes` | Observações |

### `clientes`
Tabela de clientes do setup inicial. Substituída por **`utilizador`**.

| Coluna | Descrição |
|--------|-----------|
| `id_cliente` | Chave primária |
| `nome` | Nome |
| `email` | Email |
| `telefone` | Telefone |
| `ativo` | Ativo (0/1) |
| `data_criacao` | Data de criação |

---

## 13. Diagrama de relações

```
utilizador ─────┬──── carro ───── carro_perfil
                │         └──── carro_perfil_pedido
                │
                ├──── servico ───── servico_orcamento_item
                │         │
                │         └──── mecanicos (mecanico_id)
                │
                ├──── chat_mensagens
                ├──── pedidos_desbloqueio
                └──── passaporte_eventos / passaporte_links_partilha

administrador
orcamentista ──── servico (id_orcamentista)
chefes_oficina ──┬── mecanicos (id_chefe)
                 └── historico_salarios (alterado_por)

mecanicos ─────── presenca
                 historico_salarios

sessoes_temporarias ──┬── utilizador (utilizador_id, opcional)
                      └── logs_visitas_temporarias
avaliacoes_temp
emails_marketing
feedback_duvidas
estado_site
atualizacoes_site
telegram_config
logs
ai_conversas
ai_diagnosticos
```

---

## Notas para o relatório

1. **Migrações automáticas:** O projeto não usa um sistema formal de migrations. As tabelas e colunas são criadas/alteradas em runtime pelos ficheiros PHP (`ALTER TABLE`, `CREATE TABLE IF NOT EXISTS`).

2. **Tabela central:** Quase todo o fluxo de negócio passa por **`servico`** — marcação → orçamento → pagamento → atribuição → execução → fatura.

3. **Compatibilidade:** Existem colunas duplicadas por evolução do projeto (`mecanico_id` vs `id_mecanico`, `servico` vs `servicos`).

4. **Configuração externa:** O Telegram pode usar BD (`telegram_config`) ou ficheiro JSON (`telegram_config.json`).

5. **Scripts de diagnóstico:** `setup_completo.php`, `diagnostico_bd.php`, `colunas_bd.php` e `verificar_estrutura_db.php` listam/criam tabelas para instalação e testes.

---

*Documento gerado para o relatório do projeto AutoManager — baseado na análise do código-fonte.*
