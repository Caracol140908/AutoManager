  # AutoManager — Tabelas da base de dados e modelo ER

  Documento gerado a partir da análise do código PHP em produção (`*.php`).  
  Objetivo: apoiar a construção do **modelo entidade-relacionamento (ER)**.

  > **Nota:** Muitas colunas são adicionadas em runtime com `ALTER TABLE`. A lista abaixo reflecte o que o programa **usa de facto**, não necessariamente um dump SQL único.

  ---

  ## Resumo

  | Categoria | N.º tabelas |
  |-----------|------------|
  | **Produção (usadas activamente)** | **28** |
  | Legado / só scripts de setup | 6 |
  | **Não são tabelas** (UI ou lógica) | 2 conceitos |

  **Tabela central:** `servico` — marcações, orçamentos, pagamentos, estados de reparação e notificações ao cliente.

  ---

  ## Diagrama ER (visão geral)

  ```mermaid
  erDiagram
      utilizador ||--o{ carro : possui
      utilizador ||--o{ servico : marca
      utilizador ||--o{ chat_mensagens : envia
      utilizador ||--o{ pedidos_desbloqueio : pede
      utilizador ||--o{ visualizacoes_atualizacoes : viu

      carro ||--o| carro_perfil : detalhe_1_1
      carro ||--o{ carro_perfil_pedido : pedido_perfil

      servico ||--o{ servico_orcamento_item : linhas_orcamento
      servico }o--o| mecanicos : atribuido
      servico }o--o| orcamentista : orcamenta
      carro_perfil_pedido }o--o| servico : origem_servico
      carro_perfil_pedido }o--o| mecanicos : mecanico_pedido

      mecanicos ||--o{ presenca : bate_ponto
      mecanicos ||--o{ historico_salarios : salario
    chefes_oficina ||--o{ historico_salarios : alterou

      atualizacoes_site ||--o{ visualizacoes_atualizacoes : mensagem

      emails_marketing }o--o| avaliacoes_temp : email

      passaporte_eventos }o--|| utilizador : email_matricula_logica
      passaporte_links_partilha }o--|| utilizador : email_matricula_logica

      administrador
      estado_site
      feedback_duvidas
      logs
      sessoes_temporarias
      telegram_config
      ai_conversas
      ai_diagnosticos
  ```

  ---

  ## 1. Utilizadores e autenticação

  ### `utilizador`
  **Papel:** Clientes registados no site.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_utilizador` | PK |
  | `nome_utilizador`, `email`, `password` | Identidade e login |
  | `carro` | Campo legado (texto); veículos reais estão em `carro` |
  | `telefone`, `nif` | Contacto e fiscal |
  | `bloqueado`, `data_bloqueio` | Conta suspensa |
  | `termos_aceite`, `data_aceite` | RGPD / termos |
  | `reset_code`, `reset_expire` | Recuperação de password |
  | `notif_cliente_pulse_ack` | Controlo de notificações vistas |
  | `created_at` | Registo |

  **Ficheiros:** `login.php`, `index.php`, `configuracoes.php`, `indexadmin.php`, `chat_*.php`, `solicitar_desbloqueio.php`, …

  **Relações:**
  - 1 → N `carro` (`carro.id_utilizador`)
  - 1 → N `servico` (por `servico.email` ou `servico.id_utilizador`)
  - 1 → N `chat_mensagens` (`de_usuario_id` / `para_usuario_id`)
  - 1 → N `pedidos_desbloqueio` (FK explícita)
  - 1 → N `visualizacoes_atualizacoes`

  ---

  ### `administrador`
  **Papel:** Conta de administração da plataforma.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_admin` | PK |
  | `username`, `email`, `password`, `nivel` | Login |
  | `reset_code`, `reset_expire` | Recuperação |

  **Relações:** Nenhuma FK declarada no código. Entidade independente.

  **Ficheiros:** `login.php`, `indexadmin.php`

  ---

  ### `mecanicos`
  **Papel:** Funcionários da oficina (área técnica).

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_mecanico` | PK |
  | `nome_mecanico`, `email`, `password` | Login |
  | `contacto`, `morada`, `nif`, `iban` | Dados pessoais |
  | `ordenado_bruto`, `data_contratacao` | RH |
  | `especialidade`, `ativo`, `primeiro_login` | Operação |
  | `id_chefe` | Chefe responsável (lógica, sem FK no CREATE) |
  | `data_despedimento`, `motivo_despedimento` | Despedimento |
  | `reset_code`, `reset_expire` | Recuperação |

  **Relações:**
  - 1 → N `servico` (`servico.mecanico_id`)
  - 1 → N `presenca` (FK)
  - 1 → N `historico_salarios` (FK)
  - N → 1 `chefes_oficina` (`id_chefe`, lógica)

  **Ficheiros:** `funcionarios.php`, `indexadmin.php`, `indexchefeoficina.php`, `bate_ponto.php`, `api_bate_ponto.php`, …

  ---

  ### `orcamentista`
  **Papel:** Funcionários que elaboram orçamentos.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_orcamentista` | PK |
  | `nome_orcamentista`, `email`, `password` | Login |
  | `morada`, `telefone`, `salario_bruto`, … | RH (análogo a mecânicos) |
  | `ativo`, `primeiro_login`, `reset_*` | Estado da conta |

  **Relações:**
  - 1 → N `servico` (`servico.id_orcamentista`)
  - Referência legada em `orcamentos` (tabela antiga, ver secção legado)

  **Ficheiros:** `orcamentista.php`, `indexadmin.php`, `ver_orcamento.php`, `login.php`

  ---

  ### `chefes_oficina`
  **Papel:** Chefes de oficina (painel operacional).

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_chefe` | PK |
  | `nome`, `email`, `password` | Login |
  | `telefone`, `nivel_acesso`, `ativo` | Perfil |
  | `primeiro_login`, `reset_*` | Conta |

  **Relações:**
  - 1 → N `historico_salarios` (`alterado_por`, FK)
  - 1 → N `mecanicos` (`mecanicos.id_chefe`, lógica)

  **Ficheiros:** `indexchefeoficina.php`, `indexadmin.php`, `login.php`

  ---

  ### `sessoes_temporarias`
  **Papel:** Visitantes anónimos / sessão temporária antes de registo.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id` | PK |
  | `ip_address`, `user_agent` | Identificação visitante |
  | `primeiro_acesso`, `ultimo_acesso`, `tempo_restante` | Tempo de sessão |
  | `utilizador_id` | Ligação após login (`utilizador`) |

  **Relações:** N → 0..1 `utilizador` (lógica, sem FK no código)

  **Ficheiros:** `login.php`, `app/Core/session.php`, `check_session.php`

  ---

  ## 2. Veículos

  ### `carro`
  **Papel:** Veículos registados por cada cliente.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_carro` | PK |
  | `id_utilizador` | FK lógica → `utilizador` |
  | `marca`, `modelo`, `ano`, `matricula` | Identificação |
  | `is_principal` | Veículo predefinido na marcação |
  | `notas` | Observações |
  | `created_at` | Registo |

  **Relações:**
  - N → 1 `utilizador`
  - 1 → 0..1 `carro_perfil`
  - 1 → N `carro_perfil_pedido`
  - Dados agregados no “catálogo” admin (`GROUP BY marca, modelo`)

  **Ficheiros:** `login.php`, `configuracoes.php`, `indexadmin.php`, `index.php`

  ---

  ### `carro_perfil`
  **Papel:** Ficha técnica alargada do veículo (1:1 com `carro`).

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_carro` | PK e FK lógica → `carro` |
  | `propulsao`, `motorizacao`, `vin`, `km_atual` | Mecânica |
  | `consumo_l_100`, `tipo_oleo`, `pneus_*` | Manutenção |
  | `seguro_*`, `foto_relativa`, `notas_oficina` | Seguro / oficina |
  | `atualizado_por`, `atualizado_em` | Auditoria |

  **Ficheiros:** `inc_veiculo_perfil.php`, `configuracoes.php`

  ---

  ### `carro_perfil_pedido`
  **Papel:** Pedidos do cliente ou mecânico para completar/atualizar perfil do veículo.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id` | PK |
  | `id_carro`, `id_utilizador` | Quem e qual veículo |
  | `id_servico` | Serviço que originou o pedido (opcional) |
  | `id_mecanico` | Mecânico que pediu dados (opcional) |
  | `estado` | `pendente`, etc. |
  | `created_at`, `resolved_at` | Fluxo |

  **Relações:** N → 1 `carro`, `utilizador`; opcionalmente `servico`, `mecanicos`

  **Ficheiros:** `inc_veiculo_perfil.php`, `mecanico_pedir_perfil.php`

  ---

  ## 3. Marcações, orçamentos e pagamentos

  ### `servico` ⭐
  **Papel:** Entidade central — cada linha é uma marcação/reparação.

  **Colunas base** (setup + evolução):

  | Grupo | Campos (exemplos) |
  |-------|-------------------|
  | Cliente | `nome`, `email`, `telefone`, `nif`, `id_utilizador` |
  | Veículo | `marca`, `modelo`, `matricula` |
  | Serviço | `tipo_servico`, `data_desejada`, `hora_desejada`, `custo` |
  | Estado oficina | `estado`, `estado_do_trabalho`, `notas_internas`, `notas_mecanico` |
  | Equipa | `mecanico_id`, `id_orcamentista` |
  | Orçamento | `estado_orcamento`, `orcamento_aprovado`, `estado_orcamento_cliente`, `data_orcamento_aceite`, `observacoes_orcamento`, … |
  | Pagamento | `token_pagamento`, `estado_pagamento`, `metodo_pagamento`, `data_pagamento`, `numero_fatura`, `subtotal_sem_iva`, `valor_iva`, `valor_total_com_iva`, `fatura_linhas_json` |
  | SLA | `plano_pronto`, `sla_prometido_horas`, `sla_inicio_at`, `sla_limite_at`, `sla_estado`, `sla_compensacao_*` |
  | Cancelamento | `estado_marcacao`, `data_cancelamento`, `taxa_cancelamento`, `taxa_cancelamento_paga` |
  | Datas | `created_at`, `data_conclusao`, `data_atualizacao_estado` |

  **Relações:**
  - N → 0..1 `utilizador` (email ou `id_utilizador`)
  - N → 0..1 `mecanicos` (`mecanico_id`)
  - N → 0..1 `orcamentista` (`id_orcamentista`)
  - 1 → N `servico_orcamento_item`
  - Origem de eventos em `passaporte_eventos` (por email + matrícula)
  - **Notificações ao cliente** são calculadas a partir desta tabela (não existe tabela `notificacoes`)

  **JOINs típicos:**
  ```sql
  servico s LEFT JOIN mecanicos m ON m.id_mecanico = s.mecanico_id
  servico s LEFT JOIN utilizador u ON LOWER(TRIM(u.email)) = LOWER(TRIM(s.email))
  servico s LEFT JOIN orcamentista o ON o.id_orcamentista = s.id_orcamentista
  ```

  **Ficheiros:** `index.php`, `configuracoes.php`, `indexadmin.php`, `indexchefeoficina.php`, `funcionarios.php`, `pagamento_orcamento.php`, `rastreamento_reparacao.php`, …

  ---

  ### `servico_orcamento_item`
  **Papel:** Linhas detalhadas do orçamento (peças, mão de obra, alternativas).

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id` | PK |
  | `servico_id` | FK lógica → `servico.id` |
  | `orden`, `categoria`, `descricao` | Linha |
  | `quantidade`, `preco_unitario`, `minutos_trabalho` | Valores |
  | `grupo_alternativa` | Opções A/B para o cliente |
  | `decisao_cliente` | `aceite`, `recusado`, etc. |

  **Relações:** N → 1 `servico`

  **Ficheiros:** `inc_orcamento_interativo.php`, `indexchefeoficina.php`, `configuracoes.php`, `pagamento_orcamento.php`

  ---

  ## 4. Comunicação e suporte

  ### `chat_mensagens`
  **Papel:** Chat de suporte cliente ↔ administração.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id` | PK |
  | `de_usuario_id` | Remetente (cliente; NULL se admin) |
  | `de_usuario_nome` | Nome apresentado (`Administrador` para suporte) |
  | `para_usuario_id` | Destinatário (0 = admin; ID cliente nas respostas) |
  | `mensagem`, `data_envio` | Conteúdo |
  | `lida_admin`, `lida_usuario` | Estado de leitura |

  **Relações:** N → 0..1 `utilizador` (sem FK — removida de propósito)

  **Ficheiros:** `chat.php`, `chat_admin.php`, `chat_lib.php`, `chat_api.php`, `indexadmin.php`

  ---

  ### `feedback_duvidas`
  **Papel:** Feedbacks e dúvidas enviados pelo formulário público.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id` | PK |
  | `nome`, `email`, `tipo` | `feedback` ou `duvida` |
  | `assunto`, `mensagem`, `resposta` | Conteúdo |
  | `estado` | `pendente`, `respondido`, `resolvido` |
  | `data_envio`, `data_resposta` | Datas |

  **Relações:** Nenhuma FK (dados por email/nome, não por `id_utilizador`)

  **Ficheiros:** `indexadmin.php`, `processar_feedback.php`, `feedback.php`

  ---

  ### `pedidos_desbloqueio`
  **Papel:** Pedidos de reativação de conta bloqueada.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id` | PK |
  | `id_utilizador` | FK → `utilizador` ON DELETE CASCADE |
  | `email`, `nome_utilizador`, `motivo`, `mensagem` | Pedido |
  | `estado` | `pendente`, `aprovado`, `rejeitado` |
  | `data_pedido`, `data_resposta`, `resposta` | Fluxo |

  **Ficheiros:** `solicitar_desbloqueio.php`

  ---

  ## 5. RH e presenças

  ### `presenca`
  **Papel:** Registo de entradas/saídas (bate-ponto) dos mecânicos.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_presenca` | PK |
  | `id_mecanico` | FK → `mecanicos` |
  | `data` | Dia (único por mecânico/dia) |
  | `hora_entrada`, `hora_saida`, `total_horas` | Horário |
  | `falta`, `justificacao` | Ausências |

  **Ficheiros:** `indexadmin.php`, `bate_ponto.php`, `api_bate_ponto.php`

  ---

  ### `historico_salarios`
  **Papel:** Auditoria de alterações salariais de mecânicos.

  | Campo principal | Descrição |
  |-----------------|-----------|
  | `id_historico` | PK |
  | `id_mecanico` | FK → `mecanicos` |
  | `salario_anterior`, `salario_novo` | Valores |
  | `alterado_por` | FK → `chefes_oficina` |
  | `data_alteracao`, `motivo_alteracao` | Contexto |

  **Ficheiros:** `indexadmin.php`, `api_aumentar_salario.php`

  ---

  ## 6. Site, marketing e sistema

  ### `estado_site`
  **Papel:** Modo manutenção do site (singleton, `id = 1`).

  | Campo | Descrição |
  |-------|-----------|
  | `estado` | `0` normal / `1` manutenção |
  | `mensagem` | Texto mostrado aos visitantes |

  **Ficheiros:** `login.php`, `indexadmin.php`

  ---

  ### `atualizacoes_site`
  **Papel:** Banner/mensagem de novidades no site.

  | Campo | Descrição |
  |-------|-----------|
  | `id`, `mensagem`, `data_publicacao` | Conteúdo |

  **Relações:** 1 → N `visualizacoes_atualizacoes`

  **Ficheiros:** `index.php`, `marcar_mensagem_vista.php`

  ---

  ### `visualizacoes_atualizacoes`
  **Papel:** Registo de que utilizador já viu qual atualização.

  | Campo | Descrição |
  |-------|-----------|
  | `id_utilizador`, `id_atualizacao` | Par utilizador + mensagem |

  **Ficheiros:** `marcar_mensagem_vista.php`

  ---

  ### `avaliacoes_temp`
  **Papel:** Avaliações rápidas (popup) de visitantes/utilizadores.

  | Campo | Descrição |
  |-------|-----------|
  | `id`, `nome`, `email`, `avaliacao` (1–5) | Avaliação |
  | `comentario`, `ip_address`, `data_avaliacao` | Meta |

  **Ficheiros:** `index.php`, `avaliacao_popup.php`, `cron_emails.php`

  ---

  ### `emails_marketing`
  **Papel:** Lista de emails para campanhas automáticas.

  **Relações:** JOIN com `avaliacoes_temp` por `email` (evitar reenvio a quem já avaliou)

  **Ficheiros:** `cron_emails.php`, `optout.php`

  ---

  ### `logs`
  **Papel:** Registo de eventos do sistema (download no admin).

  | Campo | Descrição |
  |-------|-----------|
  | `origem`, `mensagem`, `dados`, `criado_em` | Entrada de log |

  **Ficheiros:** `indexadmin.php`, `indexchefedeoficina.php` (leitura)

  ---

  ### `telegram_config`
  **Papel:** Configuração do bot Telegram (notificações).

  | Campo | Descrição |
  |-------|-----------|
  | `bot_token`, `chat_id`, `ativo` | Config |

  **Ficheiros:** `config_telegram.php`, `obter_chat_id.php`

  ---

  ## 7. Passaporte do veículo

  ### `passaporte_eventos`
  **Papel:** Histórico cronológico de intervenções/eventos por veículo.

  | Campo | Descrição |
  |-------|-----------|
  | `email`, `matricula` | Identificação do “dono” (lógica, sem FK) |
  | `tipo`, `descricao`, `odometro`, `data_evento` | Evento |
  | `origem`, `hash_evento` | Sincronização a partir de `servico` |

  **Ficheiros:** `inc_inovacao.php`, `passaporte_veiculo.php`

  ---

  ### `passaporte_links_partilha`
  **Papel:** Links temporários para partilhar o passaporte.

  | Campo | Descrição |
  |-------|-----------|
  | `email`, `matricula`, `token` | Acesso |
  | `expira_em`, `revogado_em`, `views` | Controlo |

  **Ficheiros:** `inc_inovacao.php`, `passaporte_veiculo.php`

  ---

  ## 8. Inteligência artificial (relatórios)

  ### `ai_conversas` / `ai_diagnosticos`
  **Papel:** Dados do assistente AI (apenas leitura/contagem em relatórios).

  **Ficheiros:** `gerar_relatorio.php`  
  **CREATE TABLE:** não definido em PHP de produção (tabelas criadas externamente).

  ---

  ## Tabelas referenciadas mas LEGADO / não usar no ER principal

  | Tabela | Situação |
  |--------|----------|
  | `orcamentos` | Nome antigo; só `COUNT` ao apagar orçamentista. Orçamentos actuais = `servico` + `servico_orcamento_item` |
  | `orcamentistas` (plural) | Provável erro em `get_detalhes_funcionario.php`; usar `orcamentista` |
  | `servicos` (plural) | Fallback legado em `assumir_servico.php`, `ajax_servicos.php` |
  | `service` | Fallback em `ver_orcamento.php` |
  | `clientes` | Setup antigo (`setup_completo.php`); produção usa `utilizador` |
  | `reparacoes` | Setup antigo; não integrado no fluxo principal |
  | `logs_visitas_temporarias` | Só diagnóstico (`verificar_sessoes.php`) |

  ---

  ## O que NÃO é tabela MySQL

  | Nome no UI/código | Realidade |
  |-------------------|-----------|
  | **Catálogo** | Secção no admin que faz `SELECT marca, modelo FROM carro GROUP BY …` |
  | **Notificações** | Contadores derivados de `servico` (`inc_utilizador_notificacoes.php`), opcionalmente Telegram |

  ---

  ## Mapa de cardinalidades (para desenho ER)

  | Entidade A | Relação | Entidade B | Cardinalidade | Tipo ligação |
  |------------|---------|------------|---------------|--------------|
  | utilizador | possui | carro | 1 : N | FK lógica `carro.id_utilizador` |
  | utilizador | faz marcação | servico | 1 : N | `email` / `id_utilizador` |
  | utilizador | conversa | chat_mensagens | 1 : N | `de_usuario_id`, `para_usuario_id` |
  | utilizador | pede desbloqueio | pedidos_desbloqueio | 1 : N | FK |
  | carro | tem perfil | carro_perfil | 1 : 0..1 | PK partilhada `id_carro` |
  | carro | pedidos perfil | carro_perfil_pedido | 1 : N | `id_carro` |
  | servico | tem linhas | servico_orcamento_item | 1 : N | `servico_id` |
  | servico | atribuído a | mecanicos | N : 0..1 | `mecanico_id` |
  | servico | orçamentado por | orcamentista | N : 0..1 | `id_orcamentista` |
  | mecanicos | regista presença | presenca | 1 : N | FK |
  | mecanicos | histórico salário | historico_salarios | 1 : N | FK |
  | chefes_oficina | alterou salário | historico_salarios | 1 : N | FK `alterado_por` |
  | chefes_oficina | gere | mecanicos | 1 : N | `id_chefe` (lógica) |
  | atualizacoes_site | vista por | visualizacoes_atualizacoes | 1 : N | `id_atualizacao` |
  | emails_marketing | relacionado | avaliacoes_temp | N : 0..1 | JOIN `email` |

  ---

  ## Módulos do programa → tabelas

  | Módulo / página | Tabelas principais |
  |-----------------|-------------------|
  | Login / registo | `utilizador`, `administrador`, `mecanicos`, `orcamentista`, `chefes_oficina`, `estado_site`, `sessoes_temporarias`, `carro` |
  | Marcação (`index.php`) | `servico`, `carro`, `utilizador`, `avaliacoes_temp` |
  | Configurações cliente | `utilizador`, `carro`, `carro_perfil`, `servico`, `servico_orcamento_item` |
  | Painel admin | Quase todas |
  | Chefe de oficina | `servico`, `mecanicos`, `orcamentista`, `servico_orcamento_item`, `logs` |
  | Área mecânico | `mecanicos`, `servico`, `presenca`, `carro_perfil_pedido` |
  | Orçamentista | `orcamentista`, `servico`, `utilizador` |
  | Chat suporte | `chat_mensagens`, `utilizador` |
  | Pagamentos / faturas | `servico`, `servico_orcamento_item` |
  | Passaporte veículo | `passaporte_eventos`, `passaporte_links_partilha`, `servico` (origem) |
  | Marketing | `emails_marketing`, `avaliacoes_temp` |
  | Telegram | `telegram_config` |

  ---

  ## Sugestão para o diagrama ER final (ferramentas)

  1. **Entidades fortes:** `utilizador`, `servico`, `mecanicos`, `orcamentista`, `chefes_oficina`, `administrador`, `carro`
  2. **Entidades dependentes:** `servico_orcamento_item`, `carro_perfil`, `presenca`, `chat_mensagens`, `pedidos_desbloqueio`
  3. **Entidades de configuração:** `estado_site`, `telegram_config`, `atualizacoes_site`
  4. **Desenhar `servico` no centro** com ligações a cliente, mecânico, orçamentista e itens de orçamento
  5. **Não desenhar FK** em `chat_mensagens` → `utilizador` (relação lógica apenas)
  6. **Unificar** `email` vs `id_utilizador` em `servico` como duas formas de ligar ao mesmo `utilizador` (decisão de modelação: normalizar para só `id_utilizador`)

  ---

  *Última actualização: análise do repositório AutoManager (PHP).*
