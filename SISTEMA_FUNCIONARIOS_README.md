# Sistema de Gestão de Funcionários - AUTOMANAGER

## [ALVO] Funcionalidades Implementadas

### 1. **BATE PONTO (Check-in/Check-out)** 
**Arquivo:** `bate_ponto.php`

Permite aos mecânicos registar entrada e saída do trabalho com relógio em tempo real.

#### Como usar:
1. Aceder a `bate_ponto.php` (adicionar link no painel do mecânico)
2. Clicar em "Registar Entrada" no início do turno
3. Clicar em "Registar Saída" no fim do turno
4. Sistema calcula automaticamente as horas trabalhadas
5. Histórico dos últimos 30 dias é exibido

#### Dados registados:
- Data de entrada/saída
- Hora de entrada/saída com timestamps
- Total de horas trabalhadas (cálculo automático)
- Histórico completo de presenças
- Status: Presente, Ausente, Completo

#### Base de Dados:
Tabela `presenca`:
```sql
- id_presenca
- id_mecanico
- data
- hora_entrada / hora_saida
- timestamp_entrada / timestamp_saida
- total_horas (calculado automaticamente)
- falta (para marcação manual)
- justificacao
```

---

### 2. **DESPEDIMENTO DE FUNCIONÁRIOS**
**Arquivo:** `gestao_funcionarios_chefe.php` + `api_despedir_funcionario.php`

Permite ao chefe despedir mecânicos e orçamentistas com registro de motivo realista.

#### Como usar:
1. Chefe acede a `gestao_funcionarios_chefe.php`
2. Procura o funcionário na tabela
3. Clica em "Despedir"
4. Modal abre com 10 motivos realistas predefinidos:
   - Desempenho insuficiente
   - Violação de políticas internas
   - Absentismo reiterado
   - Comportamento inadequado
   - Insubordinação
   - Redução de quadro
   - Justa causa (roubo/fraude)
   - Restruturação interna
   - Inadequação profissional
   - Outro motivo customizado
5. Campo opcional para detalhes adicionais
6. Confirmar despedimento

#### O que acontece:
- [OK] Conta do funcionário é desativada (ativo = 0)
- [OK] Data de despedimento é registada
- [OK] Motivo é guardado no historial
- [OK] Email profissional é enviado ao funcionário com:
  - Notificação formal do despedimento
  - Motivo específico
  - Data de validade
  - Informações sobre processamento salarial

#### Base de Dados:
Campos adicionados às tabelas `mecanicos` e `orcamentista`:
```sql
- data_despedimento (DATE)
- motivo_despedimento (VARCHAR 255)
```

---

### 3. **AUMENTO DE SALÁRIO**
**Arquivo:** `gestao_funcionarios_chefe.php` + `api_aumentar_salario.php`

Permite ao chefe aumentar o salário dos funcionários com histórico completo.

#### Como usar:
1. Chefe acede a `gestao_funcionarios_chefe.php`
2. Procura o funcionário na tabela
3. Clica em "Aumentar" (botão verde)
4. Modal abre com:
   - Salário atual (apenas leitura)
   - Campo para inserir novo salário
   - Cálculo automático de:
     - Valor do aumento (€)
     - Percentagem de aumento (%)
   - Dropdown com 8 motivos realistas:
     - Desempenho excepcional
     - Promoção interna
     - Aumento de responsabilidades
     - Reconhecimento técnico
     - Ajuste por inflação
     - Novo papel/função
     - Retenção de talento
     - Outro motivo
5. Confirmar aumento

#### O que acontece:
- [OK] Novo salário é guardado na BD
- [OK] Histórico completo do aumento é registado
- [OK] Guarda: Salário anterior, novo, data, motivo, quem alterou
- [OK] Validações: novo salário > salário anterior
- [OK] Histórico consultável para auditoria

#### Base de Dados:
Tabela `historico_salarios`:
```sql
- id_historico
- id_mecanico / id_orcamentista
- salario_anterior (DECIMAL 10,2)
- salario_novo (DECIMAL 10,2)
- data_alteracao (DATE)
- motivo_alteracao (VARCHAR 255)
- alterado_por (ID do chefe)
- criado_em (TIMESTAMP)
```

---

## [LISTA] Estrutura de Ficheiros

```
Novos Ficheiros:
├── bate_ponto.php                    # Interface de bate ponto para mecânicos
├── api_bate_ponto.php                # API de processamento de check-in/check-out
├── gestao_funcionarios_chefe.php     # Interface de gestão completa
├── api_despedir_funcionario.php      # API de despedimento
├── api_aumentar_salario.php          # API de aumento de salário
└── SISTEMA_FUNCIONARIOS_README.md    # Este ficheiro

Ficheiros Modificados:
├── indexadmin.php                    # Adicionadas tabelas de presença e histórico
├── email.php                         # Usado para envio de emails de despedimento
```

---

## [LINK] Links de Acesso Rápido

### Para Mecânicos:
```
Link: /bate_ponto.php
- Registar entrada/saída
- Ver histórico de presenças
```

### Para Chefes:
```
Link: /gestao_funcionarios_chefe.php
- Ver todos os funcionários ativos
- Despedir funcionários (com motivo)
- Aumentar salários (com histórico)
```

---

## [CODIGO] Exemplos de API

### Registar Entrada (Bate Ponto)
```http
POST /api_bate_ponto.php
Content-Type: application/x-www-form-urlencoded

acao=entrada

Response:
{
  "sucesso": true,
  "mensagem": "Entrada registada com sucesso!",
  "hora": "09:15:30",
  "tipo": "entrada"
}
```

### Registar Saída (Bate Ponto)
```http
POST /api_bate_ponto.php
Content-Type: application/x-www-form-urlencoded

acao=saida

Response:
{
  "sucesso": true,
  "mensagem": "Saída registada com sucesso!",
  "hora": "18:45:00",
  "horas_trabalhadas": 9.5,
  "tipo": "saida"
}
```

### Despedir Funcionário
```http
POST /api_despedir_funcionario.php
Content-Type: application/x-www-form-urlencoded

funcionario_id=5
tipo=mecanico
motivo=Desempenho insuficiente: Não cumpre prazos

Response:
{
  "sucesso": true,
  "mensagem": "Mecânico despedido com sucesso"
}
```

### Aumentar Salário
```http
POST /api_aumentar_salario.php
Content-Type: application/x-www-form-urlencoded

funcionario_id=5
tipo=mecanico
novo_salario=1500.00
motivo=Desempenho excepcional

Response:
{
  "sucesso": true,
  "mensagem": "Salário aumentado com sucesso",
  "aumento": 200.00,
  "percentagem": 15.4
}
```

---

## [SEGURANCA] Segurança

### Validações Implementadas:
- [OK] Verificação de sessão (apenas chefes/mecânicos)
- [OK] Verificação de conta ativa antes de operações
- [OK] Validação de dados de entrada
- [OK] Proteção contra despedimento duplo
- [OK] Proteção contra salários inválidos
- [OK] Timestamps de auditoria
- [OK] Registos de quem fez cada alteração

### Email Seguro:
- [OK] Template profissional e realista
- [OK] Motivo específico do despedimento
- [OK] Informações de contacto HR
- [OK] Data e hora do envio

---

## [RELATORIO] Relatórios Possíveis

Com os dados registados, é possível gerar:
1. **Relatório de Presenças:** Por mecânico, período, horas totais
2. **Relatório de Turnover:** Despedimentos, motivos, datas
3. **Relatório Salarial:** Histórico completo, aumentos, custos
4. **Auditoria:** Quem fez quê e quando

---

## [AVISO] Notas Importantes

1. **Bate Ponto**: Realista com cálculo de horas, timestamps e histórico
2. **Despedimento**: Irreversível - uma vez despedido, o funcionário não pode mais aceder
3. **Salário**: Validação garante que novos salários são sempre maiores que os anteriores
4. **Emails**: Motivos realistas para parecer profissional
5. **Auditoria**: Todos os eventos são registados com data, hora e responsável

---

## [TEC] Tecnologias Usadas

- **PHP 7+** - Backend
- **MySQLi** - Base de dados
- **JavaScript Vanilla** - Frontend interativo
- **HTML5 + CSS3** - Interface responsiva
- **Bootstrap Icons** - Ícones
- **SMTP** - Envio de emails

---

## [OK] Checklist de Implementação

- [x] Tabelas de BD criadas
- [x] API de bate ponto implementada
- [x] Interface de bate ponto realista
- [x] API de despedimento com email
- [x] Modal de despedimento com motivos realistas
- [x] API de aumento de salário com histórico
- [x] Modal de aumento com cálculos automáticos
- [x] Gestão completa de funcionários
- [x] Validações de segurança
- [x] Documentação completa

---

**Versão:** 1.0  
**Data:** Janeiro 2026  
**Status:** [OK] Completo e Funcional 100%
