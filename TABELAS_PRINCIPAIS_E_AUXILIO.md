# AutoManager — Tabelas principais e tabelas de auxílio

Documento de apoio ao modelo ER e relatório académico.

---

## Tabelas principais

São o **núcleo do negócio**: registo de clientes, veículos, marcações, orçamentos e execução na oficina.

### `utilizador`
Representa o **cliente registado** na plataforma. Guarda dados de identificação (nome, email, telefone, NIF), credenciais de acesso e estado da conta (ativo ou bloqueado). É o ponto de partida de quase todo o fluxo: sem utilizador não há veículos nem marcações associadas a uma conta.

### `carro`
Armazena os **veículos** de cada cliente. Um utilizador pode ter vários carros (relação 1:N). Inclui marca, modelo, matrícula, ano e indicação do veículo principal. Os dados do carro são usados nas marcações e no histórico do veículo.

### `servico`
É a **entidade central** do sistema. Cada registo corresponde a uma marcação ou reparação: tipo de serviço, data pretendida, estados (trabalho, orçamento, pagamento), custo e matrícula do veículo. Liga o cliente ao trabalho na oficina e concentra a maior parte das interações do sistema.

### `servico_orcamento_item`
Detalha o **orçamento** de cada serviço em linhas (peças, mão de obra, etc.). Um serviço tem várias linhas (1:N). Cada linha tem descrição, quantidade, preço unitário e decisão do cliente (aceite ou recusa).

### `mecanicos`
Funcionários da **oficina** que executam os serviços. Um mecânico pode ter vários serviços atribuídos (1:N). Inclui dados profissionais (nome, email, especialidade, estado ativo/inativo).

### `orcamentista`
Funcionário que **elabora orçamentos**. Liga-se aos serviços através do campo `id_orcamentista` em `servico`. Trabalha na fase anterior à atribuição ao mecânico.

### `chefes_oficina`
Responsável pela **gestão operacional** da oficina: atribuição de trabalhos, acompanhamento da equipa e alterações salariais. Relaciona-se com `mecanicos` (gestão) e com `historico_salarios` (auditoria).

---

## Tabelas de auxílio

Apoiam funcionalidades secundárias, configuração, comunicação, RH, marketing e auditoria. Não são o centro do fluxo marcação → orçamento → reparação, mas são necessárias ao funcionamento completo da plataforma.

### Comunicação e suporte

| Tabela | Função |
|--------|--------|
| `chat_mensagens` | Chat de suporte entre cliente e administração |
| `feedback_duvidas` | Feedbacks e dúvidas enviados pelo formulário público |
| `pedidos_desbloqueio` | Pedidos de reativação de contas bloqueadas |

### Veículos (complemento)

| Tabela | Função |
|--------|--------|
| `carro_perfil` | Ficha técnica alargada do veículo (relação 1:1 com `carro`) |
| `carro_perfil_pedido` | Pedidos para completar ou atualizar o perfil do veículo |

### Recursos humanos

| Tabela | Função |
|--------|--------|
| `presenca` | Registo de entradas e saídas (bate-ponto) dos mecânicos |
| `historico_salarios` | Histórico de alterações salariais |

### Sistema e configuração

| Tabela | Função |
|--------|--------|
| `administrador` | Conta de administração da plataforma (independente dos clientes) |
| `estado_site` | Modo de manutenção do site (ligado/desligado) |
| `logs` | Registo de eventos e auditoria do sistema |
| `telegram_config` | Configuração de notificações via Telegram |
| `sessoes_temporarias` | Sessões de visitantes anónimos antes do registo |

### Site e marketing

| Tabela | Função |
|--------|--------|
| `atualizacoes_site` | Mensagens de novidades mostradas no site |
| `visualizacoes_atualizacoes` | Registo de quais utilizadores já viram cada mensagem |
| `avaliacoes_temp` | Avaliações rápidas (popup) dos visitantes |
| `emails_marketing` | Lista de emails para campanhas automáticas |

### Passaporte do veículo

| Tabela | Função |
|--------|--------|
| `passaporte_eventos` | Histórico cronológico de intervenções por veículo |
| `passaporte_links_partilha` | Links temporários para partilhar o passaporte |

### Inteligência artificial

| Tabela | Função |
|--------|--------|
| `ai_conversas` | Conversas do assistente AI (relatórios) |
| `ai_diagnosticos` | Diagnósticos gerados pelo módulo AI |

---

## Resumo comparativo

| | Tabelas principais | Tabelas de auxílio |
|---|-------------------|-------------------|
| **Quantidade** | 7 | ~18 |
| **Papel** | Fluxo de negócio da oficina | Suporte, configuração, comunicação |
| **Dependência** | O sistema não funciona sem elas | O núcleo pode existir sem muitas delas |
| **Exemplo** | Cliente marca reparação → mecânico executa | Chat, logs, marketing, bate-ponto |

---

## Relações entre tabelas principais

```
utilizador  1 ──── N  carro
utilizador  1 ──── N  servico
mecanicos   1 ──── N  servico
servico     1 ──── N  servico_orcamento_item
chefes_oficina  1 ──── N  mecanicos
orcamentista    1 ──── N  servico
```

Ligação lógica adicional: `servico.matricula` ≈ `carro.matricula` (sem chave estrangeira directa na base de dados).

---

## Texto para relatório (parágrafo único)

O modelo de dados do AutoManager divide-se em **tabelas principais** e **tabelas de auxílio**. As principais — `utilizador`, `carro`, `servico`, `servico_orcamento_item`, `mecanicos`, `orcamentista` e `chefes_oficina` — modelam o fluxo central: o cliente regista-se, associa veículos, solicita serviços, recebe orçamentos detalhados e a oficina atribui a execução aos mecânicos sob gestão dos chefes de oficina. A entidade `servico` funciona como hub, ligando cliente, veículo, orçamento e equipa técnica. As tabelas de auxílio complementam o sistema com chat de suporte, feedbacks, pedidos de desbloqueio, bate-ponto, histórico salarial, configuração do site, notificações, marketing, passaporte do veículo e módulo de inteligência artificial, sem participarem directamente no ciclo principal de marcação e reparação.

---

## Ficheiros relacionados no projeto

| Ficheiro | Conteúdo |
|----------|----------|
| `MODELO_ER_TABELAS.md` | Inventário completo de todas as tabelas |
| `dbdiagram_automanager.dbml` | ER académico (5 tabelas) para [dbdiagram.io](https://dbdiagram.io/d) |
| `automanager.dbml` | ER completo (28 tabelas) para dbdiagram.io |
