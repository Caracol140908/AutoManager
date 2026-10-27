# [LOCALIZACAO] Guia de Rastreamento de Reparações - AUTOMANAGER

## [ALVO] Funcionalidades Adicionadas

Foi implementado um sistema completo de rastreamento de reparações que permite:

### Para o **Cliente/Utilizador**:
- [OK] Visualizar o estado atual da sua reparação
- [OK] Acompanhar o progresso através de uma timeline visual
- [OK] Contactar o mecânico responsável
- [OK] Ver informações detalhadas do serviço

### Para o **Mecânico**:
- [OK] Atualizar o estado da reparação com múltiplas opções
- [OK] Adicionar notas sobre o progresso
- [OK] Registar problemas encontrados
- [OK] Gerir peças utilizadas

---

##  Ficheiros Adicionados/Modificados

### Novos Ficheiros:
1. **rastreamento_reparacao.php**
   - Página de visualização do estado da reparação para o cliente
   - Timeline visual com estados
   - Informações do mecânico responsável
   
2. **atualizar_estado_reparacao.php**
   - Página para mecânicos atualizarem o estado
   - Interface com opções de estados
   - Campo para notas

3. **criar_tabela_estados.php**
   - Script para adicionar campos necessários à base de dados

### Ficheiros Modificados:
1. **configuracoes.php**
   - Adicionado botão "[LOCALIZACAO] Rastrear Reparação" em cada serviço
   - Botão permite acesso à página de rastreamento

2. **funcionarios.php**
   - Adicionado botão "Atualizar" para mecânicos
   - Permite fácil acesso à página de atualização de estado

3. **styleconfiguracoes.css**
   - Adicionados estilos para os novos botões
   - `.servico-actions` e `.btn-rastrear`

---

## [ATUAL] Estados de Reparação

O sistema suporta 5 estados de reparação:

| Estado | Ícone | Descrição |
|--------|-------|-----------|
| **Por Fazer** | [LISTA] | Reparação agendada e à espera de início |
| **Aguardar Preparação** | ⏳ | Preparação de materiais e ferramentas |
| **Em Andamento** | [FERRAMENTAS] | Reparação em progresso |
| **Pronto** | [OK] | Reparação concluída e pronto para levantar |
| **Concluído** | [SUCESSO] | Reparação retirada |

---

##  Como Utilizar - Cliente

### 1. Aceder ao Rastreamento
1. Ir para **Configurações**
2. Clicar em **Meus Serviços**
3. Para cada serviço, clicar em **"[LOCALIZACAO] Rastrear Reparação"**

### 2. Visualizar Informações
- **Informações do Serviço**: Tipo, matrícula, data, custo
- **Estado Atual**: Indica o estado presente com ícone e descrição
- **Barra de Progresso**: Mostra visualmente o avanço
- **Timeline**: Lista todos os estados e qual está ativo
- **Mecânico Responsável**: Nome, email e telefone

### 3. Contactar Mecânico
Se tiver dúvidas, pode contactar directamente o mecânico responsável através do email ou telefone exibido.

---

## [MECANICO] Como Utilizar - Mecânico

### 1. Aceder à Página de Atualização
1. Ir para **Área do Funcionário**
2. Na tabela de "Serviços Disponíveis"
3. Clicar em **"Atualizar"** na coluna de Ações

### 2. Atualizar o Estado
1. Selecionar o novo estado da reparação
2. *(Opcional)* Adicionar notas sobre o progresso
3. Clicar em **"[OK] Atualizar Estado"**

### 3. Adicionar Notas
- Descrever o progresso do trabalho
- Indicar problemas encontrados
- Listar peças utilizadas
- Registar tempo de trabalho

### Estados Recomendados:

**Por Fazer → Aguardar Preparação**
- Quando o serviço é assumido e inicia a preparação

**Aguardar Preparação → Em Andamento**
- Quando começa o trabalho de reparação

**Em Andamento → Pronto**
- Quando a reparação está concluída e pronta para levantar
- *Nota: Cliente recebe notificação por email automaticamente*

**Pronto → Concluído**
- Quando o cliente levanta o carro

---

##  Configuração Técnica

### Campos Adicionados à Base de Dados

Se ainda não existirem, execute o script **criar_tabela_estados.php**:

```sql
ALTER TABLE servico ADD COLUMN notas_mecanico LONGTEXT NULL DEFAULT NULL;
ALTER TABLE servico ADD COLUMN data_atualizacao_estado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

### Permissões Requeridas

- **Cliente**: Pode apenas visualizar suas próprias reparações
- **Mecânico**: Pode atualizar apenas suas reparações
- **Admin**: Pode ver e atualizar todas as reparações

---

## [EMAIL] Notificações Automáticas

Quando o estado muda para **"Pronto"**, o cliente recebe automaticamente um email notificando que:
- A reparação está concluída
- O carro está pronto para levantar
- Deve aceder à sua conta para mais detalhes

---

##  Interface Visual

### Cliente
- **Cores**: Tema escuro (preto/laranja)
- **Timeline**: Visual com bolinhas representando cada estado
- **Progresso**: Barra visual mostrando avanço

### Mecânico
- **Estados**: Seleção com rádio buttons
- **Notas**: Campo de texto expandível
- **Confirmação**: Mensagens de sucesso/erro

---

## [LISTA] Checklist de Implementação

- [OK] Criar página de rastreamento para cliente
- [OK] Criar página de atualização para mecânico
- [OK] Adicionar links em configurações
- [OK] Adicionar links em funcionários
- [OK] Adicionar estilos CSS
- [OK] Implementar 5 estados de reparação
- [OK] Adicionar campo de notas
- [OK] Enviar notificação ao cliente quando pronto

---

## [FERRAMENTAS] Manutenção

### Se Precisar de Adicionar Novo Estado:

1. **Editar rastreamento_reparacao.php**:
   ```php
   $estados_ordem = [
       'Por Fazer' => 1,
       'Novo Estado' => 2,
       // ... outros
   ];
   ```

2. **Editar atualizar_estado_reparacao.php**:
   - Adicionar novo radio button

3. **Atualizar validação**:
   ```php
   $estados_validos = ['Por Fazer', 'Novo Estado', ...];
   ```

---

## [OBS] Dicas de Utilização

### Para o Cliente:
- Visite a página de rastreamento regularmente para ver atualizações
- Guarde o email do mecânico para comunicações diretas
- A barra de progresso mostra onde se encontra a reparação

### Para o Mecânico:
- Atualize o estado regularmente para manter cliente informado
- Use as notas para registar todos os detalhes
- Lembre-se de mudar para "Pronto" quando terminar (avisa o cliente)

---

## [TELEFONE] Suporte

Para dúvidas ou problemas:
1. Verificar se todos os ficheiros foram criados
2. Verificar se a base de dados foi atualizada (criar_tabela_estados.php)
3. Verificar permissões de ficheiros
4. Verificar logs de erro do servidor

---

**Sistema implementado com sucesso! [SUCESSO]**
