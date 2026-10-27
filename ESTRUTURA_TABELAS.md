# [LISTA] Estrutura das Tabelas - REPARACOES e SERVICOS

## [RELATORIO] Tabela: `reparacoes`

**Localização:** `criar_reparacoes.php`

### Campos:
| Campo | Tipo | Null | Chave | Padrão | Observações |
|-------|------|------|-------|--------|------------|
| id_reparacao | INT | NÃO | PRI | auto_increment | Identificador único |
| descricao | VARCHAR(255) | NÃO | | | Descrição da reparação |
| estado | VARCHAR(50) | SIM | | 'pendente' | pendente, ativa, concluída |
| data_entrada | DATETIME | SIM | | CURRENT_TIMESTAMP | Data de registo |
| mecanico_atribuido | VARCHAR(100) | SIM | | NULL | Nome do mecânico |
| cliente_id | INT | SIM | | NULL | Referência ao cliente |
| prioridade | VARCHAR(20) | SIM | | 'normal' | normal, alta, baixa |
| data_conclusao | DATETIME | SIM | | NULL | Data de conclusão |
| observacoes | TEXT | SIM | | NULL | Observações adicionais |

---

## [RELATORIO] Tabela: `servicos` (Tabela Expandida)

Baseada na imagem fornecida, a tabela `servicos` contém os seguintes campos:

### Campos Principais:
| Campo | Tipo | Null | Chave | Padrão | Observações |
|-------|------|------|-------|--------|------------|
| id | INT | NÃO | PRI | auto_increment | Identificador único |
| nome | VARCHAR(100) | SIM | | NULL | Nome do cliente/serviço |
| telefone | VARCHAR(20) | SIM | | NULL | Contacto telefónico |
| email | VARCHAR(100) | SIM | | NULL | Email do cliente |
| matricula | VARCHAR(20) | SIM | | NULL | Matrícula do veículo |
| tipo_servico | VARCHAR(100) | SIM | | NULL | Tipo de serviço |
| data_desejada | DATE | SIM | | NULL | Data pretendida |
| custo | DECIMAL(10,2) | SIM | | NULL | Custo do serviço |
| data_registo | TIMESTAMP | SIM | | CURRENT_TIMESTAMP | Data de registo |
| estado | ENUM | SIM | | 'pendente' | pendente, progresso, concluído |
| metodo_pagamento | VARCHAR(50) | SIM | | NULL | Forma de pagamento |
| token_pagamento | VARCHAR(32) | SIM | | NULL | Token de transação |
| estado_pagamento | ENUM | SIM | | 'Pendente' | Pendente, Pago |
| data_pagamento | DATETIME | SIM | | NULL | Data do pagamento |
| numero_fatura | VARCHAR(20) | SIM | | NULL | Número da fatura |
| mecanico_id | INT | SIM | | NULL | Referência ao mecânico |
| id_mecanico | INT | SIM | | NULL | ID do mecânico (duplicado?) |
| estado_do_trabalho | VARCHAR(20) | SIM | | 'Por Fazer' | Por Fazer, Em Andamento, Concluído |
| data_conclusao | DATETIME | SIM | | NULL | Data de conclusão |
| observacoes | TEXT | SIM | | NULL | Observações |
| custo_estimado | INT | NÃO | | 0 | Custo estimado |
| custo_final | INT | NÃO | | 0 | Custo final |
| data_final | DATETIME | SIM | | NULL | Data final |
| id_orcamentista | INT | SIM | MUL | NULL | Referência ao orçamentista |
| orcamento_aprovado | DECIMAL(10,2) | SIM | | NULL | Orçamento aprovado |
| estado_orcamento | ENUM | SIM | | 'pendente' | pendente, analise, aprovado, recusado |
| observacoes_orcamento | TEXT | SIM | | NULL | Observações do orçamento |
| data_orcamento | TIMESTAMP | SIM | | | Data do orçamento |
| tempo_trabalho_minutos | INT | SIM | | 0 | Tempo de trabalho em minutos |
| hora_inicio_trabalho | DATETIME | SIM | | NULL | Hora de início |
| hora_fim_trabalho | DATETIME | SIM | | NULL | Hora de fim |
| relatorio_mecanico | TEXT | SIM | | NULL | Relatório do mecânico |
| data_finalizacao | DATETIME | SIM | | NULL | Data de finalização |
| pecas_substituidas | TEXT | SIM | | NULL | Peças substituídas |

---

## [LINK] Relação entre Tabelas

### `reparacoes` → `servicos`

```
reparacoes.mecanico_atribuido → servicos.id_mecanico
reparacoes.cliente_id → servicos.id
```

---

## [OK] Verificação de Estrutura

Para verificar a estrutura atual das tabelas, execute:

```sql
-- Verificar tabela reparacoes
DESCRIBE reparacoes;

-- Verificar tabela servicos
DESCRIBE servicos;
```

---

## [NOTAS] Notas Importantes

1. **Inconsistências observadas:**
   - Campos duplicados: `mecanico_id` e `id_mecanico` na tabela `servicos`
   - Estados com nomenclaturas diferentes: `'ativa'` vs `'Em Andamento'`
   - Valores por defeito inconsistentes

2. **Recomendações:**
   - Unificar nomenclatura de estados
   - Remover campos duplicados
   - Normalizar tipos de dados (ENUM vs VARCHAR)

---

**Data de Criação:** Janeiro 2026
**Sistema:** AUTOMANAGER - Sistema de Gestão de Oficina
