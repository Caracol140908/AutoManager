#  GUIA DE TESTES - SISTEMA DE FUNCIONÁRIOS

## [ALVO] Como Testar Tudo

### PRÉ-REQUISITOS
- [OK] Base de dados funcionando
- [OK] Chefe de oficina criado e logado
- [OK] Pelo menos 2 mecânicos/orçamentistas ativos
- [OK] Email SMTP configurado (para testes de email)

---

## 1⃣ TESTE: BATE PONTO

###  Passo 1: Aceder ao Bate Ponto
```
1. Fazer login como MECÂNICO
2. Aceder a: http://seu-site/bate_ponto.php
3. Verificar se página carrega com relógio digital
```

### [OK] Teste 1.1: Registar Entrada
```
1. Clicar no botão "Registar Entrada"
2. Verificar:
   - [OK] Botão desaparece
   - [OK] Mensagem "Entrada registada com sucesso!" aparece
   - [OK] Página recarrega após 1,5 segundos
   - [OK] Agora mostra "Status: Presente"
   - [OK] Hora da entrada é exibida
```

**Resultado Esperado:**
- Entrada foi gravada na BD
- Data e hora de entrada registadas
- Página atualiza automaticamente

### [ERRO] Teste 1.2: Tentar Registar Entrada Novamente
```
1. Clicar novamente em "Registar Entrada"
2. Verificar:
   -  Botão desaparece
   - [OK] Mensagem "Entrada já registada hoje às [hora]"
   - [OK] Página NÃO recarrega
   - [OK] Botão fica disponível novamente
```

**Resultado Esperado:**
- Impede dupla entrada no mesmo dia [OK]

### [OK] Teste 1.3: Registar Saída
```
1. Clicar em "Registar Saída"
2. Confirmar na caixa de diálogo
3. Verificar:
   - [OK] Botão desaparece
   - [OK] Mensagem "Saída registada com sucesso!"
   - [OK] Página recarrega
   - [OK] Agora mostra "Status: Concluído"
   - [OK] Mostra entrada, saída e horas trabalhadas
```

**Resultado Esperado:**
- Saída foi gravada na BD
- Horas trabalhadas foram calculadas [OK]

### [RELATORIO] Teste 1.4: Histórico
```
1. Scroll down para ver "Histórico (Últimos 30 Dias)"
2. Verificar:
   - [OK] Hoje aparece com as horas registadas
   - [OK] Formato: "Data → Hora Entrada - Hora Saída → Total Horas"
   - [OK] Status mostra "[OK] Presente"
```

**Resultado Esperado:**
- Histórico carrega corretamente [OK]

---

## 2⃣ TESTE: DESPEDIR FUNCIONÁRIO

###  Passo 1: Aceder à Gestão
```
1. Fazer logout do mecânico
2. Fazer login como CHEFE
3. Aceder a: http://seu-site/gestao_funcionarios_chefe.php
4. Verificar se página carrega com todas as tabelas
```

### [OK] Teste 2.1: Abrir Modal de Despedimento
```
1. Na tabela de Mecânicos, procurar um mecânico
2. Clicar no botão vermelho "Despedir"
3. Verificar:
   - [OK] Modal aparece com fundo escuro
   - [OK] Nome do mecânico está correto
   - [OK] Dropdown com motivos realistas
   - [OK] Campo de detalhes adicionais
   - [OK] Aviso vermelho "[AVISO] Atenção: Esta ação é irreversível"
```

**Resultado Esperado:**
- Modal carrega com todos os elementos [OK]

### [OK] Teste 2.2: Despedir com Motivo Predefinido
```
1. Selecionar motivo: "Desempenho insuficiente"
2. (Opcional) Adicionar detalhes: "Atrasos constantes nas reparações"
3. Clicar "Confirmar Despedimento"
4. Verificar:
   - [OK] Modal fecha
   - [OK] Mensagem de sucesso aparece no topo
   - [OK] Página recarrega automaticamente
   - [OK] Mecânico DESAPARECE da tabela (foi desativado)
```

**Resultado Esperado:**
- Mecânico foi desativado (ativo = 0) [OK]
- Data de despedimento foi registada [OK]
- Motivo foi guardado [OK]

### [EMAIL] Teste 2.3: Email de Despedimento
```
1. Verificar email do mecânico despedido
2. Procurar email com assunto: "Comunicado de Rescisão de Contrato"
3. Verificar conteúdo:
   - [OK] Cumprimento personalizado
   - [OK] Data de rescisão
   - [OK] Motivo: "Desempenho insuficiente: Atrasos constantes..."
   - [OK] Informações sobre processamento salarial
   - [OK] Template profissional
```

**Resultado Esperado:**
- Email recebido com conteúdo correto [OK]

### [ERRO] Teste 2.4: Tentar Acessar com Conta Despedida
```
1. Fazer logout do chefe
2. Tentar fazer login com credentials do mecânico despedido
3. Se login passar:
   - [OK] Tentar aceder a bate_ponto.php
   - Verificar: mensagem "Conta desativada"
```

**Resultado Esperado:**
- Funcionário não consegue aceder [OK]

---

## 3⃣ TESTE: AUMENTAR SALÁRIO

###  Passo 1: Aceder à Gestão
```
1. Estar logado como CHEFE
2. Aceder a: http://seu-site/gestao_funcionarios_chefe.php
3. Procurar um mecânico/orçamentista ativo
```

### [OK] Teste 3.1: Abrir Modal de Aumento
```
1. Clicar no botão verde "Aumentar"
2. Verificar:
   - [OK] Modal aparece
   - [OK] Nome do funcionário está correto
   - [OK] Salário Atual: € [valor] (apenas leitura)
   - [OK] Campo "Novo Salário" está vazio
   - [OK] Dropdown com motivos realistas
```

**Resultado Esperado:**
- Modal carrega com campos corretos [OK]

### [OK] Teste 3.2: Cálculo Automático
```
1. Salário atual é € 1000.00
2. Inserir novo salário: € 1200.00
3. Verificar:
   - [OK] Aparece: "Aumento: € 200.00 (20.0%)"
   - [OK] Cálculo está correto
   - [OK] Atualiza em tempo real conforme digita
```

**Resultado Esperado:**
- Cálculos automáticos funcionam [OK]

### [OK] Teste 3.3: Aumentar Salário
```
1. Novo salário: € 1200.00
2. Motivo: "Desempenho excepcional"
3. Clicar "Confirmar Aumento"
4. Verificar:
   - [OK] Modal fecha
   - [OK] Mensagem: "Salário aumentado com sucesso! (20.0% +€200.00)"
   - [OK] Página recarrega
   - [OK] Novo salário aparece na tabela: € 1,200.00
```

**Resultado Esperado:**
- Salário foi atualizado [OK]
- Histórico foi criado [OK]

### [ERRO] Teste 3.4: Validação - Novo Salário Inválido
```
1. Tentar inserir: € 900.00 (menor que atual 1000)
2. Verificar:
   -  Mensagem: "O novo salário deve ser superior ao atual"
   -  Aumento NÃO foi processado
```

**Resultado Esperado:**
- Validação funciona [OK]
- Impede salários menores [OK]

### [OK] Teste 3.5: Verificar Histórico
```
1. Em linha de comando SQL:
   SELECT * FROM historico_salarios WHERE id_mecanico = [id]
2. Verificar:
   - [OK] Salário anterior: 1000.00
   - [OK] Salário novo: 1200.00
   - [OK] Data de alteração: [data hoje]
   - [OK] Motivo: "Desempenho excepcional"
   - [OK] Alterado por: [id do chefe]
```

**Resultado Esperado:**
- Histórico está registado corretamente [OK]

---

## 4⃣ TESTES DE SEGURANÇA

### [SEGURANCA] Teste 4.1: Sem Sessão
```
1. Limpar cookies/sessão
2. Tentar aceder a:
   - bate_ponto.php
   - gestao_funcionarios_chefe.php
   - api_bate_ponto.php (POST)
3. Verificar:
   - [OK] Redireciona para login
   - [OK] API retorna: "Não autorizado"
```

**Resultado Esperado:**
- Acesso negado sem sessão [OK]

### [SEGURANCA] Teste 4.2: Conta Desativada
```
1. Despedir um mecânico
2. Fazer login com essa conta
3. Se conseguir login:
   - Tentar registar bate ponto
   - Verificar: "Conta desativada"
```

**Resultado Esperado:**
- Conta desativada não consegue usar [OK]

### [SEGURANCA] Teste 4.3: Apenas Chefe Pode Despedir
```
1. Fazer login como mecânico
2. Tentar aceder a: gestao_funcionarios_chefe.php
3. Verificar:
   - [OK] Redireciona para painel mecânico
   -  NÃO mostra página de gestão
```

**Resultado Esperado:**
- Apenas chefe tem acesso [OK]

---

## 5⃣ TESTES DE DADOS

### [RELATORIO] Teste 5.1: Base de Dados - Tabela Presença
```
SQL: SELECT * FROM presenca;

Verificar campos:
- [OK] id_presenca
- [OK] id_mecanico
- [OK] data
- [OK] hora_entrada
- [OK] hora_saida
- [OK] total_horas
- [OK] timestamp_entrada
- [OK] timestamp_saida
```

### [RELATORIO] Teste 5.2: Base de Dados - Histórico Salários
```
SQL: SELECT * FROM historico_salarios;

Verificar campos:
- [OK] id_historico
- [OK] id_mecanico
- [OK] salario_anterior
- [OK] salario_novo
- [OK] data_alteracao
- [OK] motivo_alteracao
- [OK] alterado_por
```

### [RELATORIO] Teste 5.3: Base de Dados - Campos de Despedimento
```
SQL: SELECT id_mecanico, ativo, data_despedimento, 
      motivo_despedimento FROM mecanicos;

Verificar:
- [OK] Mecânico ativo = 1
- [OK] Mecânico despedido = ativo 0, data_despedimento, motivo preenchidos
```

---

## [OK] RESUMO DE TESTES

```
[ ] Bate Ponto - Entrada                [OK]
[ ] Bate Ponto - Saída                  [OK]
[ ] Bate Ponto - Histórico              [OK]
[ ] Bate Ponto - Dupla Entrada          [OK]
[ ] Despedimento - Modal                [OK]
[ ] Despedimento - Processar            [OK]
[ ] Despedimento - Email                [OK]
[ ] Despedimento - Desativação          [OK]
[ ] Aumento - Modal                     [OK]
[ ] Aumento - Cálculo                   [OK]
[ ] Aumento - Processar                 [OK]
[ ] Aumento - Histórico                 [OK]
[ ] Aumento - Validação                 [OK]
[ ] Segurança - Sem Sessão              [OK]
[ ] Segurança - Conta Desativada        [OK]
[ ] Segurança - Permissões              [OK]
[ ] BD - Tabela Presença                [OK]
[ ] BD - Tabela Histórico               [OK]
[ ] BD - Campos Despedimento            [OK]
```

---

##  Troubleshooting Testes

### Erro: "Tabela presenca não existe"
- [OK] Solução: Aceder a indexadmin.php para criar tabelas

### Erro: "Email não foi enviado"
- [OK] Solução: Verificar configuração SMTP em email.php

### Erro: "Salário antigo ainda aparece"
- [OK] Solução: Fazer refresh na página (Ctrl+F5)

### Erro: "Não consegue despedir"
- [OK] Solução: Verificar se está logado como CHEFE

### Erro: "Não consegue registar bate ponto"
- [OK] Solução: Verificar se é MECÂNICO e está ativo

---

## [DISPOSITIVO] Testes Responsivos

### Desktop (1920x1080)
- [ ] Tabelas completas
- [ ] Botões visíveis
- [ ] Modals centrados

### Tablet (768px)
- [ ] Tabelas com scroll horizontal
- [ ] Botões continuam clicáveis
- [ ] Modals redimensionam

### Mobile (375px)
- [ ] Relógio está visível
- [ ] Botões stack verticalmente
- [ ] Histórico em scroll
- [ ] Tabelas colapsam (se necessário)

---

**Tempo Estimado de Testes:** 30-45 minutos  
**Status:** Pronto para teste completo [OK]
