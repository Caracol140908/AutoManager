# AutoManager — Plataforma de Gestão de Oficina Automóvel

Sistema web desenvolvido em PHP para gestão integral de uma oficina automóvel: marcações online, orçamentos, pagamentos, acompanhamento de reparações, comunicação com clientes e painéis de gestão para diferentes perfis de utilizador.

**URL local:** `http://localhost/automanager/`  
**Contacto comercial (site):** 912 964 022 · Alenquer

---

## Índice

1. [Visão geral](#1-visão-geral)
2. [Objetivos do sistema](#2-objetivos-do-sistema)
3. [Perfis de utilizador](#3-perfis-de-utilizador)
4. [Funcionalidades por módulo](#4-funcionalidades-por-módulo)
5. [Fluxos de negócio principais](#5-fluxos-de-negócio-principais)
6. [Base de dados](#6-base-de-dados)
7. [Segurança e validações](#7-segurança-e-validações)
8. [Tecnologias utilizadas](#8-tecnologias-utilizadas)
9. [Estrutura do projeto](#9-estrutura-do-projeto)
10. [Instalação e requisitos](#10-instalação-e-requisitos)

---

## 1. Visão geral

O **AutoManager** é uma aplicação web monolítica em PHP que combina:

- **Site público** — apresentação da oficina, serviços e formulário de marcação
- **Área de cliente** — gestão de dados pessoais, veículos, marcações, pagamentos e suporte
- **Backoffice** — painéis para administrador, chefe de oficina, mecânico e orçamentista

A interface segue um tema visual **preto e laranja**, com layout responsivo e ecrã de carregamento (loading) com identidade da marca.

---

## 2. Objetivos do sistema

| Objetivo | Descrição |
|----------|-----------|
| Digitalizar marcações | Permitir ao cliente marcar reparações online com validação de dados |
| Automatizar orçamentos | Fluxo de aprovação/recusa entre orçamentista, chefe e cliente |
| Acompanhar reparações | Estados de trabalho visíveis para cliente, mecânico e gestão |
| Centralizar pagamentos | Simulação de MB Way, cartão e referência Multibanco com fatura |
| Melhorar comunicação | Chat em tempo real cliente ↔ admin e notificações por email/Telegram |
| Apoiar diagnóstico | Assistente AI (AutoAssist) com estimativas e sugestão de agendamento |
| Gerir equipa | Atribuição de trabalhos, bate-ponto, salários e despedimentos |

---

## 3. Perfis de utilizador

### 3.1 Cliente (`utilizador`)

- Registo e login com email e password
- Área privada em `configuracoes.php`
- Gestão de veículos, marcações, pagamentos e chat de suporte
- Pode ser bloqueado pelo administrador (pedido de desbloqueio disponível)

### 3.2 Administrador (`administrador`)

- Acesso total ao painel `indexadmin.php`
- Gestão de utilizadores, veículos, marcações, equipa, feedbacks, logs e chat
- Ativação do modo de manutenção do site
- Único perfil com acesso durante manutenção

### 3.3 Chefe de oficina (`chefes_oficina`)

- Painel `indexchefeoficina.php`
- Criação e envio de orçamentos detalhados
- Atribuição e reatribuição de trabalhos a mecânicos
- Métricas da oficina e gestão de funcionários (despedir, aumentar salário)

### 3.4 Mecânico (`mecanicos`)

- Painel `funcionarios.php`
- Visualização de trabalhos ativos e histórico
- Timer de serviço, assumir e concluir reparações
- Bate-ponto (`bate_ponto.php`)
- Pedido de dados técnicos ao cliente e atualização do perfil do veículo

### 3.5 Orçamentista (`orcamentista`)

- Painel `orcamentista.php`
- Análise de pedidos de orçamento: aprovar, recusar ou colocar em análise
- Envio automático de emails ao cliente

### 3.6 Visitante temporário

- Navegação pública limitada a **2 minutos** por IP (`check_session.php`)
- Sem acesso a chat, marcações ou áreas privadas
- Ao expirar: avaliação do site ou redirecionamento para login

---

## 4. Funcionalidades por módulo

### 4.1 Site público — `index.php`

- Landing page com secções: Início, Serviços, Sobre, Contactos
- Mensagens de atualização do site (`atualizacoes_site`)
- **Wizard de marcação em 4 passos:**
  1. Dados pessoais (nome, telefone, NIF, email)
  2. Veículo (marca, modelo, matrícula portuguesa validada)
  3. Tipo de serviço e plano SLA (normal / premium 4h / 8h / 24h)
  4. Data e hora (dias úteis, entre amanhã e 3 meses)
- Pré-preenchimento automático para clientes com sessão iniciada
- Limite de marcações: 1 a cada 5 minutos por utilizador/email
- Email de confirmação e notificação Telegram (se configurado)
- Modal **AutoAssist AI** — assistente de diagnóstico pré-visita
- Logo da marca no header com barra de navegação transparente
- Ecrã de loading inicial com identidade visual

### 4.2 Autenticação — `login.php`

- Login unificado com deteção automática de perfil (admin → mecânico → orçamentista → chefe → cliente)
- Registo de novos clientes com aceitação de termos
- Recuperação de password em 3 passos (email → código de 6 dígitos → nova password)
- Redirecionamento automático conforme perfil autenticado
- Respeito ao modo de manutenção (apenas admin entra)

### 4.3 Área do cliente — `configuracoes.php`

**Dados pessoais**
- Editar nome, telefone e NIF (validação portuguesa)
- Alterar password
- Eliminar conta

**Veículos**
- Adicionar, editar e eliminar carros (marca, modelo, ano, matrícula)
- Definir veículo principal
- Perfil técnico: VIN, quilómetros, consumo, pneus, óleo, seguro, inspeção
- Taxa de eliminação de veículo com marcações ativas (15 €)
- Passaporte digital do veículo

**Minhas marcações**
- Listagem com estados de orçamento, pagamento e trabalho
- Aceitar ou recusar orçamento (incluindo orçamento interativo por linhas)
- Pagamento de orçamento aceite
- Cancelamento com taxa se faltar menos de 24 horas (15 €)
- Rastreamento da reparação
- Download de fatura
- Secção de marcações canceladas

### 4.4 Chat de suporte

| Ficheiro | Função |
|----------|--------|
| `chat.php` | Interface do cliente |
| `chat_admin.php` | Interface do administrador (lista de conversas, pesquisa) |
| `enviar_mensagem.php` / `enviar_mensagem_admin.php` | Envio de mensagens |
| `obter_novas_mensagens.php` / `obter_mensagens_admin.php` | Atualização automática (polling) |
| `obter_contador_nao_lidas.php` / `marcar_lida.php` | Contador e estado de leitura |

- Mensagens em tempo quase real com recarregamento automático a cada 3 segundos
- Notificação Telegram ao administrador quando o cliente envia mensagem
- Tema visual alinhado com o resto da aplicação

### 4.5 Pagamentos e faturação

| Página | Descrição |
|--------|-----------|
| `pagamento_orcamento.php` | Pagamento de orçamento aceite (MB Way, cartão, referência MB) |
| `pagamento_taxa_cancelamento.php` | Taxa de cancelamento tardio |
| `pagamento_taxa_eliminar_carro.php` | Taxa de eliminação de veículo |
| `download_fatura.php` | Fatura HTML com token de acesso |
| `metodopagamento.php` | Pagamento alternativo por link/token |

- Cálculo de IVA (23 %)
- NIF para fatura
- Geração de número de fatura e token de pagamento
- *Nota: pagamentos simulados — sem gateway bancário real*

### 4.6 Orçamentos

- **Orçamentista:** aprovar com valor e data, recusar ou marcar em análise
- **Chefe de oficina:** orçamento detalhado por linhas (peças, mão de obra, tempo)
- **Cliente:** aceitar/recusar globalmente ou por linha (`inc_orcamento_interativo.php`)
- **Visualização:** `ver_orcamento.php`
- Emails automáticos em cada transição de estado

### 4.7 Rastreamento de reparação — `rastreamento_reparacao.php`

Estados da timeline:

1. Por fazer  
2. Aguardar preparação  
3. Em andamento  
4. Pronto  
5. Concluído  

Acesso pelo cliente (serviços próprios) ou pelo administrador.

### 4.8 Passaporte digital do veículo — `passaporte_veiculo.php`

- Histórico de eventos por matrícula
- Sincronização automática a partir de serviços realizados
- Link de partilha pública com token (validade de 7 dias)

### 4.9 Painel de administração — `indexadmin.php`

**Operação**
- Dashboard com KPIs e gráficos dos últimos 7 dias
- Gestão de marcações (editar, atribuir mecânico, notas internas)
- Gestão de utilizadores (bloquear/desbloquear)
- Catálogo de veículos e marcas/modelos
- Modo de manutenção on/off

**Equipa**
- Criar mecânicos, orçamentistas e chefes de oficina
- Listar, editar e eliminar membros da equipa

**Suporte**
- Responder a feedbacks e dúvidas
- Chat integrado com clientes
- Download de logs de sistema
- Configuração e teste de notificações Telegram

### 4.10 Painel do chefe de oficina — `indexchefeoficina.php`

- Dashboard com KPIs (aprovados, atribuídos, em andamento, concluídos, receita, atrasados)
- Tab de orçamentos com formulário simplificado (total + observações)
- Trabalhos por atribuir (pagos e com orçamento aceite)
- Atribuição e reatribuição de mecânicos
- Gestão de funcionários: despedir e aumentar salário
- Tema visual preto/laranja, layout limpo e responsivo

### 4.11 Painel do mecânico — `funcionarios.php`

- Trabalhos ativos, histórico e estatísticas
- Timer de serviço (iniciar/parar, minutos registados)
- Assumir e concluir serviços com email ao cliente
- Pedir dados do veículo ao cliente
- Guardar perfil técnico ao fechar trabalho
- Bate-ponto com registo de presença
- Alteração obrigatória de password no primeiro login

### 4.12 Painel do orçamentista — `orcamentista.php`

- Fila de serviços pendentes de orçamento
- Aprovar (valor, data, observações), recusar ou colocar em análise
- Estatísticas de orçamentos tratados
- Emails HTML automáticos ao cliente

### 4.13 Módulo AI — AutoAssist

| Ficheiro | Função |
|----------|--------|
| `ai-chat.php` | Conversa com API Groq (modelo Llama) |
| `ai-database.php` | Base de conhecimento local (sintomas → diagnósticos) |
| `ai-estimativa.php` | Tabela de custos estimados |
| `ai-agendamento.php` | Sugestão de horários por urgência |
| `ai-processar-imagem.php` | Análise de imagens do veículo |
| `ai-dashboard.php` | Estatísticas de utilização |
| `gerar_relatorio.php` | Relatório HTML de conversas e diagnósticos |

### 4.14 Notificações e integrações

**Telegram** (`config_telegram.php`)
- Bot configurável por JSON ou base de dados
- Alertas: chat, feedback, nova marcação, alertas de sistema

**Email** (`mailer.php`, PHPMailer)
- Confirmações de marcação, orçamentos, conclusão de trabalhos
- Campanhas de follow-up (`cron_emails.php`)
- Opt-out de marketing (`optout.php`)

### 4.15 Conteúdo e apoio

| Página | Conteúdo |
|--------|----------|
| `faq.php` | Perguntas frequentes |
| `feedback.php` | Formulário de feedback e dúvidas |
| `termos.php` | Termos de utilização e privacidade |
| `solicitar_desbloqueio.php` | Pedido de desbloqueio de conta |
| `avaliacao_popup.php` | Avaliação após visita temporária |

---

## 5. Fluxos de negócio principais

### Fluxo A — Marcação de reparação

```
Cliente acede ao site → Inicia sessão → Preenche wizard (4 passos)
→ Validações (NIF, matrícula, data, rate limit) → Serviço criado na BD
→ Email de confirmação + Telegram → Marcação visível em Configurações
```

### Fluxo B — Orçamento até conclusão

```
Marcação criada
  → Orçamentista ou Chefe analisa e envia orçamento
  → Cliente aceita/recusa (por linhas se interativo)
  → Pagamento do orçamento (simulado)
  → Chefe atribui mecânico
  → Mecânico executa (timer) e conclui
  → Cliente acompanha no rastreamento
  → Fatura disponível para download
```

### Fluxo C — Suporte ao cliente

```
Cliente abre chat.php → Mensagem guardada na BD
→ Admin responde em chat_admin.php → Cliente recebe atualização automática
→ Notificação Telegram ao admin (opcional)
```

### Fluxo D — Gestão de equipa

```
Chefe acede ao painel → Atribui trabalhos a mecânicos
→ Mecânico regista presença (bate-ponto)
→ Chefe pode despedir ou aumentar salário (histórico auditado)
```

### Fluxo E — Passaporte do veículo

```
Cliente regista veículo e perfil técnico
→ Serviços concluídos alimentam o passaporte
→ Link de partilha gerado (7 dias) para consulta externa
```

---

## 6. Base de dados

Sistema **MySQL/MariaDB** com ligação via `ligar.php` (mysqli).

### Tabelas principais

| Tabela | Descrição |
|--------|-----------|
| `utilizador` | Clientes registados |
| `administrador` | Contas de administração |
| `mecanicos` | Mecânicos da oficina |
| `orcamentista` | Orçamentistas |
| `chefes_oficina` | Chefes de oficina |
| `servico` | Marcações e reparações |
| `carro` | Veículos dos clientes |
| `carro_perfil` | Dados técnicos do veículo |
| `servico_orcamento_item` | Linhas de orçamento interativo |
| `chat_mensagens` | Mensagens de suporte |
| `presenca` | Registos de bate-ponto |
| `feedback_duvidas` | Feedbacks e dúvidas |
| `sessoes_temporarias` | Visitantes temporários |
| `passaporte_eventos` | Histórico do passaporte digital |
| `estado_site` | Modo de manutenção |
| `logs` | Logs de sistema |
| `telegram_config` | Configuração do bot Telegram |
| `ai_conversas` / `ai_diagnosticos` | Métricas do módulo AI |

As migrações de esquema são aplicadas automaticamente nos entrypoints (`ALTER TABLE` / `CREATE TABLE IF NOT EXISTS`).

---

## 7. Segurança e validações

| Mecanismo | Implementação |
|-----------|---------------|
| Sessões PHP | `session_start()`; regeneração de ID no login |
| Passwords | `password_hash` / `password_verify` (BCrypt) |
| Autorização | Verificação de perfil em cada página privada |
| Modo manutenção | Bloqueio de logins exceto administrador |
| Rate limiting | 1 marcação / 5 minutos por utilizador |
| Validação NIF | Algoritmo de dígito de controlo português |
| Validação matrícula | Formatos AA-00-AA, 00-AA-00 e 00-00-AA |
| Validação MB Way | Prefixos 91, 92, 93, 96 |
| SQL injection | Prepared statements na maior parte do código |
| XSS | `htmlspecialchars` na saída HTML |
| Tokens | Pagamentos, faturas e partilha de passaporte |

---

## 8. Tecnologias utilizadas

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP 7+/8+ |
| Base de dados | MySQL / MariaDB (mysqli) |
| Frontend | HTML5, CSS3, JavaScript (vanilla) |
| Email | PHPMailer (SMTP) |
| APIs externas | Groq API (chat AI), Telegram Bot API |
| Ícones | Font Awesome 6, Bootstrap Icons |
| Tipografia | Google Fonts (Poppins, Inter) |
| Tema | `theme.css` — design system preto/laranja |

### Ficheiros CSS principais

- `style.css` — site público
- `indexadmin.css` — painel admin
- `indexchefeoficina.css` — painel chefe
- `styleconfiguracoes.css` — área do cliente
- `chat.css` — módulo de chat
- `loading.css` — ecrã de carregamento

### Includes PHP reutilizáveis (`inc_*.php`)

- `inc_am_head.php` — cabeçalho unificado de assets
- `inc_matricula.php` — validação de matrículas
- `inc_orcamento_interativo.php` — orçamentos por linhas
- `inc_veiculo_perfil.php` — perfil técnico do veículo
- `inc_rate_limit_marcacao.php` — limite de marcações
- `inc_utilizador_notificacoes.php` — notificações do cliente
- `inc_chat_schema.php` — esquema da tabela de chat

---

## 9. Estrutura do projeto

```
parabox/
├── index.php                 # Site público e marcações
├── login.php                 # Autenticação e registo
├── configuracoes.php         # Área do cliente
├── indexadmin.php            # Painel administrador
├── indexchefeoficina.php     # Painel chefe de oficina
├── funcionarios.php          # Painel mecânico
├── orcamentista.php          # Painel orçamentista
├── chat.php / chat_admin.php # Chat de suporte
├── pagamento_orcamento.php   # Pagamentos
├── passaporte_veiculo.php    # Passaporte digital
├── rastreamento_reparacao.php
├── ai-chat.php               # Módulo AI
├── ligar.php                 # Ligação à base de dados
├── inc_*.php                 # Módulos partilhados
├── theme.css                 # Tema global
├── img/                      # Imagens (logo, etc.)
└── README.md                 # Este documento
```

---

## 10. Instalação e requisitos

### Requisitos do servidor

- PHP 7.4 ou superior (recomendado 8.x)
- Extensões: mysqli, session, json, mbstring, curl
- MySQL 5.7+ ou MariaDB 10.3+
- Servidor web Apache ou Nginx
- SMTP configurado (opcional, para emails)
- Token Groq API (opcional, para módulo AI)
- Token Telegram Bot (opcional, para notificações)

### Passos de instalação (localhost / XAMPP)

1. Colocar o projeto em `C:\xampp\htdocs\automanager` (ou criar um junction para esta pasta)
2. Garantir Apache + MySQL ativos no XAMPP
3. Credenciais em `config/database.php` (por defeito: `root` sem password, BD `automanager`)
4. Abrir `http://localhost/automanager/setup_completo.php` para criar tabelas e dados de teste
5. Configurar SMTP em `mailer.php` (se necessário)
6. Configurar Telegram em `configurar_telegram.php` (opcional)
7. Configurar API Groq para o módulo AI (opcional)

### Scripts de manutenção

| Script | Função |
|--------|--------|
| `setup_completo.php` | Instalação inicial da base de dados |
| `diagnostico_bd.php` | Diagnóstico da estrutura da BD |
| `cron_emails.php` | Envio de emails de marketing (cron) |
| `limpar_chats_antigos.php` | Limpeza de mensagens antigas (cron) |

---

## Notas finais

- Os **pagamentos são simulados** — não existe integração com gateway bancário real (Stripe, PayPal, etc.).
- O projeto inclui ficheiros de **setup e diagnóstico** que devem ser removidos ou protegidos em ambiente de produção.
- As credenciais da base de dados devem ser **externalizadas** (variáveis de ambiente) em produção.
- Existem alguns ficheiros **legados** (`indexchefedeoficina.php`, `dashboard.php`) mantidos por compatibilidade.

---

*Documento gerado para suporte a relatório técnico do projeto AutoManager.*
