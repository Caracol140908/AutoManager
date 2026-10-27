# [ALVO] GUIA RÁPIDO - SISTEMA DE FUNCIONÁRIOS AUTOMANAGER

## [INICIAR] ACESSO RÁPIDO

### Para MECÂNICOS (Bate Ponto):
```
URL: http://seu-site.com/bate_ponto.php
Funcionalidade: Registar entrada e saída do trabalho
```

### Para CHEFES (Gestão):
```
URL: http://seu-site.com/gestao_funcionarios_chefe.php
Funcionalidade: Despedir, aumentar salários, ver histórico
```

---

##  FLUXO DE OPERAÇÕES

### 1⃣ BATE PONTO (Mecânico)
```
Entrar na página → Clicar "Registar Entrada" → 
Trabalhar durante o dia → 
Clicar "Registar Saída" → Sistema calcula horas
```

**Resultado:** Histórico de presenças com horas trabalhadas

---

### 2⃣ DESPEDIR FUNCIONÁRIO (Chefe)
```
Acessar gestão_funcionarios_chefe.php →
Procurar o funcionário →
Clicar botão vermelho "Despedir" →
Escolher motivo realista (dropdown) →
Adicionar detalhes (opcional) →
Confirmar
```

**Resultado:** 
- [ERRO] Conta desativada
- [EMAIL] Email enviado ao funcionário
- [NOTAS] Motivo guardado no histórico

**Motivos Disponíveis:**
- Desempenho insuficiente
- Violação de políticas internas
- Absentismo reiterado
- Comportamento inadequado
- Insubordinação
- Redução de quadro
- Justa causa (roubo/fraude)
- Restruturação interna
- Inadequação profissional
- Customizado

---

### 3⃣ AUMENTAR SALÁRIO (Chefe)
```
Acessar gestao_funcionarios_chefe.php →
Procurar o funcionário →
Clicar botão verde "Aumentar" →
Ver salário atual →
Inserir novo salário →
Sistema mostra:
  • Aumento em € 
  • Percentagem de aumento
Escolher motivo realista →
Confirmar
```

**Resultado:**
-  Novo salário guardado
- [RELATORIO] Histórico completo registado
- [GRAFICO] Auditoria de quem fez a alteração

**Motivos Disponíveis:**
- Desempenho excepcional
- Promoção interna
- Aumento de responsabilidades
- Reconhecimento técnico
- Ajuste por inflação
- Novo papel/função
- Retenção de talento
- Customizado

---

## [RELATORIO] DADOS REGISTADOS

### Bate Ponto:
[OK] Data completa
[OK] Hora de entrada e saída
[OK] Duração em horas
[OK] Timestamps precisos
[OK] Histórico 30 dias
[OK] Marcação de faltas

### Despedimento:
[OK] Data de despedimento
[OK] Motivo específico
[OK] Detalhes adicionais
[OK] Email de notificação
[OK] Conta desativada

### Aumento Salarial:
[OK] Salário anterior e novo
[OK] Data da alteração
[OK] Motivo da alteração
[OK] Quem fez a alteração
[OK] Histórico completo

---

##  INTERFACE

### Bate Ponto:
- ⏰ Relógio digital em tempo real
- [DISPOSITIVO] Responsivo (mobile/desktop)
- [ALVO] Botões grandes e claros
- [GRAFICO] Histórico visual
- [OK] Status em tempo real

### Gestão de Funcionários:
- [LISTA] Tabelas organizadas (Mecânicos/Orçamentistas)
- [ALVO] Botões de ação por linha
-  Vermelho para despedir
-  Verde para aumentar
- [RELATORIO] Salários em destaque

---

## [SEGURANCA] SEGURANÇA

[OK] Apenas funcionários ativos podem fazer bate ponto
[OK] Despedimento é irreversível
[OK] Novos salários devem ser > salário atual
[OK] Todas as ações são auditadas (quem, quando)
[OK] Emails profissionais e seguros
[OK] Validação de dados de entrada

---

## 🆘 TROUBLESHOOTING

### Problema: "Não autorizado"
**Solução:** Verificar se está logado como mecânico/chefe

### Problema: "Já tem entrada registada"
**Solução:** Pode registar saída ou tentar amanhã

### Problema: Email de despedimento não enviou
**Solução:** Verificar configuração SMTP em email.php

### Problema: "Novo salário deve ser superior"
**Solução:** Inserir valor maior que o atual

---

## [DISPOSITIVO] LINKS ÚTEIS

- **Bate Ponto:** `bate_ponto.php`
- **Gestão:** `gestao_funcionarios_chefe.php`
- **API Bate Ponto:** `api_bate_ponto.php`
- **API Despedir:** `api_despedir_funcionario.php`
- **API Salário:** `api_aumentar_salario.php`

---

## [TELEFONE] SUPORTE

Para dúvidas contactar:
- [EMAIL] Email: rh@AUTOMANAGER.pt
-  Departamento: Recursos Humanos
- ‍ Chefe de Oficina

---

**Sistema Operacional:** [OK] 100% Funcional
**Última Atualização:** Janeiro 2026
**Versão:** 1.0
