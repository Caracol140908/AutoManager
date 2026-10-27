#!/usr/bin/env python3
"""Gera automanager_schema.db — catálogo SQLite do esquema usado pela aplicação AutoManager."""

from __future__ import annotations

import json
import sqlite3
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT_DB = ROOT / "automanager_schema.db"

SCHEMA = {
    "info_projeto": {
        "nome": "AutoManager",
        "motor_producao": "MySQL / MariaDB",
        "base_dados_producao": "aluno19041",
        "ficheiro_catalogo": "automanager_schema.db",
        "descricao": "Catálogo de referência com tabelas, colunas, relações e ficheiros PHP que as utilizam. Não substitui a BD de produção.",
        "gerado_em": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC"),
        "fonte": "TABELAS_BD.md + análise do código PHP",
    },
    "tabelas": [
        {
            "nome": "utilizador",
            "categoria": "Utilizadores e autenticação",
            "tipo": "principal",
            "descricao": "Clientes registados no site.",
            "uso_principal": "Login, perfil, marcações e pagamentos do cliente.",
            "ficheiros_uso": "login.php, configuracoes.php, chat.php, indexadmin.php, header.php",
            "colunas": [
                ("id_utilizador", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome_utilizador", "VARCHAR(255)", "Nome completo", 0, None, None, 0),
                ("email", "VARCHAR(255)", "Email de login", 0, None, None, 0),
                ("password", "VARCHAR(255)", "Password encriptada (BCrypt)", 0, None, None, 0),
                ("carro", "VARCHAR(255)", "Descrição legada do veículo (texto)", 0, None, None, 1),
                ("telefone", "VARCHAR(20)", "Telefone de contacto", 0, None, None, 1),
                ("nif", "VARCHAR(9)", "NIF português", 0, None, None, 1),
                ("bloqueado", "TINYINT(1)", "Conta bloqueada (0/1)", 0, None, "0, 1", 1),
                ("data_bloqueio", "DATETIME", "Data do bloqueio", 0, None, None, 1),
                ("termos_aceite", "TINYINT(1)", "Aceitação dos termos", 0, None, "0, 1", 1),
                ("data_aceite", "DATETIME", "Data de aceitação dos termos", 0, None, None, 1),
                ("metodo_pagamento_preferido", "VARCHAR(30)", "MB Way, cartão, etc.", 0, None, None, 1),
                ("notif_cliente_pulse_ack", "VARCHAR(32)", "Hash de notificações já vistas pelo cliente", 0, None, None, 1),
                ("reset_code", "VARCHAR(64)", "Código de recuperação de password", 0, None, None, 1),
                ("reset_expire", "DATETIME", "Expiração do código de recuperação", 0, None, None, 1),
                ("created_at", "TIMESTAMP", "Data de criação", 0, None, None, 1),
                ("updated_at", "TIMESTAMP", "Última atualização", 0, None, None, 1),
            ],
        },
        {
            "nome": "administrador",
            "categoria": "Utilizadores e autenticação",
            "tipo": "principal",
            "descricao": "Contas de administração do sistema.",
            "uso_principal": "Acesso ao painel indexadmin.php.",
            "ficheiros_uso": "login.php, indexadmin.php",
            "colunas": [
                ("id_admin", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("username", "VARCHAR(100)", "Nome de utilizador", 0, None, None, 1),
                ("email", "VARCHAR(255)", "Email de login", 0, None, None, 0),
                ("password", "VARCHAR(255)", "Password encriptada", 0, None, None, 0),
                ("nivel", "VARCHAR(50)", "Nível de acesso", 0, None, None, 1),
                ("reset_code", "VARCHAR(64)", "Código de recuperação", 0, None, None, 1),
                ("reset_expire", "DATETIME", "Expiração do código", 0, None, None, 1),
            ],
        },
        {
            "nome": "servico",
            "categoria": "Marcações e reparações",
            "tipo": "central",
            "descricao": "Tabela central: marcações, orçamentos, pagamentos, trabalho e faturação.",
            "uso_principal": "Todo o fluxo operacional da oficina.",
            "ficheiros_uso": "index.php, configuracoes.php, indexadmin.php, indexchefeoficina.php, funcionarios.php, pagamento_orcamento.php, concluir_servico.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome", "VARCHAR(255)", "Nome do cliente", 0, None, None, 0),
                ("email", "VARCHAR(255)", "Email do cliente", 0, None, None, 1),
                ("telefone", "VARCHAR(20)", "Telefone", 0, None, None, 1),
                ("nif", "VARCHAR(9)", "NIF do cliente", 0, None, None, 1),
                ("marca", "VARCHAR(60)", "Marca do veículo", 0, None, None, 1),
                ("modelo", "VARCHAR(80)", "Modelo do veículo", 0, None, None, 1),
                ("matricula", "VARCHAR(20)", "Matrícula portuguesa", 0, None, None, 1),
                ("id_utilizador", "INT", "FK opcional → utilizador.id_utilizador", 0, "utilizador.id_utilizador", None, 1),
                ("id_carro", "INT", "FK opcional → carro.id_carro", 0, "carro.id_carro", None, 1),
                ("tipo_servico", "VARCHAR(255)", "Tipo de intervenção", 0, None, None, 1),
                ("data_desejada", "DATE/DATETIME", "Data pretendida da marcação", 0, None, None, 1),
                ("hora_desejada", "VARCHAR(5)", "Hora pretendida (HH:MM)", 0, None, None, 1),
                ("custo", "DECIMAL(10,2)", "Valor estimado ou legado", 0, None, None, 1),
                ("data_registo", "DATETIME", "Data de registo da marcação", 0, None, None, 1),
                ("created_at", "TIMESTAMP", "Timestamp de criação", 0, None, None, 1),
                ("estado_marcacao", "VARCHAR(20)", "ativa / cancelada", 0, None, "ativa, cancelada", 1),
                ("data_cancelamento", "DATETIME", "Data de cancelamento", 0, None, None, 1),
                ("taxa_cancelamento", "DECIMAL(10,2)", "Taxa de cancelamento (ex. 15 €)", 0, None, None, 1),
                ("taxa_cancelamento_paga", "TINYINT(1)", "Taxa de cancelamento paga", 0, None, "0, 1", 1),
                ("metodo_pagamento_taxa_cancel", "VARCHAR(30)", "Método de pagamento da taxa", 0, None, None, 1),
                ("estado_orcamento", "VARCHAR(30)", "pendente / aprovado / recusado / em_analise", 0, None, "pendente, aprovado, recusado, em_analise", 1),
                ("orcamento_aprovado", "DECIMAL(10,2)", "Valor do orçamento aprovado (s/ IVA)", 0, None, None, 1),
                ("data_orcamento", "DATETIME", "Data do orçamento", 0, None, None, 1),
                ("observacoes_orcamento", "TEXT", "Observações do orçamento", 0, None, None, 1),
                ("estado_orcamento_cliente", "VARCHAR(20)", "pendente / aceite / recusado", 0, None, "pendente, aceite, aceito, recusado", 1),
                ("data_orcamento_aceite", "DATETIME", "Data de aceitação pelo cliente", 0, None, None, 1),
                ("data_orcamento_recusado", "DATETIME", "Data de recusa pelo cliente", 0, None, None, 1),
                ("estado_excesso_orcamento_cliente", "VARCHAR(20)", "Decisão sobre valor acima do orçamento", 0, None, "pendente, aceite, recusado", 1),
                ("data_excesso_orcamento_notificado", "DATETIME", "Quando o cliente foi notificado do excesso", 0, None, None, 1),
                ("data_excesso_orcamento_resposta", "DATETIME", "Quando o cliente respondeu ao excesso", 0, None, None, 1),
                ("aviso_chefe_orcamento_aceite", "TINYINT(1)", "Flag de notificação ao chefe", 0, None, "0, 1", 1),
                ("aviso_chefe_orcamento_recusado", "TINYINT(1)", "Flag de notificação ao chefe", 0, None, "0, 1", 1),
                ("id_orcamentista", "INT", "FK → orcamentista.id_orcamentista", 0, "orcamentista.id_orcamentista", None, 1),
                ("estado_pagamento", "VARCHAR(30)", "Estado do pagamento do trabalho", 0, None, "pendente, aguarda_trabalho, pago, parcial", 1),
                ("metodo_pagamento", "VARCHAR(30)", "MB Way, cartão, referência MB", 0, None, None, 1),
                ("data_pagamento", "DATETIME", "Data do pagamento", 0, None, None, 1),
                ("token_pagamento", "VARCHAR(255)", "Token de acesso ao pagamento", 0, None, None, 1),
                ("subtotal_sem_iva", "DECIMAL(10,2)", "Subtotal da fatura", 0, None, None, 1),
                ("valor_iva", "DECIMAL(10,2)", "Valor do IVA (23%)", 0, None, None, 1),
                ("valor_total_com_iva", "DECIMAL(10,2)", "Total com IVA", 0, None, None, 1),
                ("valor_pago_total", "DECIMAL(10,2)", "Total já pago pelo cliente", 0, None, None, 1),
                ("valor_em_divida", "DECIMAL(10,2)", "Valor em dívida", 0, None, None, 1),
                ("numero_fatura", "VARCHAR(40)", "Número da fatura", 0, None, None, 1),
                ("fatura_linhas_json", "TEXT/JSON", "Linhas da fatura e fecho do mecânico", 0, None, None, 1),
                ("pedir_contribuinte_fatura", "TINYINT(1)", "Pedir NIF na fatura", 0, None, "0, 1", 1),
                ("contribuinte_fatura", "VARCHAR(9)", "NIF para fatura", 0, None, None, 1),
                ("estado_do_trabalho", "VARCHAR(50)", "Estado do trabalho na oficina", 0, None, "Por Fazer, Em Andamento, Pronto, Concluído", 1),
                ("estado", "VARCHAR(50)", "Estado legado", 0, None, "pendente, progresso, concluido", 1),
                ("mecanico_id", "INT", "FK → mecanicos (compatibilidade)", 0, "mecanicos.id_mecanico", None, 1),
                ("id_mecanico", "INT", "FK → mecanicos (compatibilidade)", 0, "mecanicos.id_mecanico", None, 1),
                ("hora_inicio_trabalho", "DATETIME", "Início do timer de trabalho", 0, None, None, 1),
                ("hora_fim_trabalho", "DATETIME", "Fim do timer", 0, None, None, 1),
                ("tempo_trabalho_minutos", "INT", "Minutos trabalhados (legado)", 0, None, None, 1),
                ("horas_trabalhadas", "DECIMAL(6,2)", "Horas registadas na conclusão", 0, None, None, 1),
                ("valor_hora", "DECIMAL(8,2)", "Valor/hora do mecânico", 0, None, None, 1),
                ("pecas_substituidas", "TEXT/JSON", "Peças substituídas", 0, None, None, 1),
                ("data_conclusao", "DATETIME", "Data de conclusão do trabalho", 0, None, None, 1),
                ("notas_internas", "TEXT", "Notas da oficina/admin/chefe", 0, None, None, 1),
                ("notas_mecanico", "TEXT", "Notas do mecânico", 0, None, None, 1),
                ("data_atualizacao_estado", "DATETIME", "Última atualização de estado", 0, None, None, 1),
                ("plano_pronto", "VARCHAR(30)", "Plano SLA premium", 0, None, "normal, premium_4h, premium_8h, premium_24h", 1),
                ("sla_prometido_horas", "INT", "Horas prometidas no SLA", 0, None, None, 1),
                ("sla_inicio_at", "DATETIME", "Início do SLA", 0, None, None, 1),
                ("sla_limite_at", "DATETIME", "Limite do SLA", 0, None, None, 1),
                ("sla_estado", "VARCHAR(20)", "Estado do SLA", 0, None, "ativo, atrasado, cumprido", 1),
                ("sla_compensacao_tipo", "VARCHAR(30)", "Tipo de compensação SLA", 0, None, "desconto", 1),
                ("sla_compensacao_valor", "DECIMAL(10,2)", "Valor da compensação SLA", 0, None, None, 1),
                ("codigo_levantamento", "VARCHAR(6)", "Código para levantar o veículo", 0, None, None, 1),
                ("codigo_levantamento_emitido_em", "DATETIME", "Emissão do código", 0, None, None, 1),
                ("codigo_levantamento_usado_em", "DATETIME", "Validação do código na entrega", 0, None, None, 1),
            ],
        },
        {
            "nome": "carro",
            "categoria": "Veículos",
            "tipo": "principal",
            "descricao": "Veículos registados pelos clientes.",
            "uso_principal": "Garagem do cliente e perfil técnico.",
            "ficheiros_uso": "configuracoes.php, index.php, indexadmin.php, passaporte_veiculo.php",
            "colunas": [
                ("id_carro", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("id_utilizador", "INT", "FK → utilizador.id_utilizador", 0, "utilizador.id_utilizador", None, 0),
                ("marca", "VARCHAR(100)", "Marca", 0, None, None, 1),
                ("modelo", "VARCHAR(100)", "Modelo", 0, None, None, 1),
                ("ano", "INT", "Ano de fabrico", 0, None, None, 1),
                ("matricula", "VARCHAR(20)", "Matrícula", 0, None, None, 1),
                ("is_principal", "TINYINT(1)", "Veículo principal do cliente", 0, None, "0, 1", 1),
                ("notas", "TEXT", "Notas gerais", 0, None, None, 1),
                ("created_at", "TIMESTAMP", "Data de registo", 0, None, None, 1),
            ],
        },
        {
            "nome": "carro_perfil",
            "categoria": "Veículos",
            "tipo": "principal",
            "descricao": "Perfil técnico detalhado do veículo (1:1 com carro).",
            "uso_principal": "Dados técnicos para diagnóstico e fecho de serviço.",
            "ficheiros_uso": "inc_veiculo_perfil.php, configuracoes.php, mecanico_guardar_perfil_fecho.php",
            "colunas": [
                ("id_carro", "INT", "PK/FK → carro.id_carro", 1, "carro.id_carro", None, 0),
                ("propulsao", "VARCHAR(50)", "Gasolina, diesel, elétrico, etc.", 0, None, None, 1),
                ("motorizacao", "VARCHAR(80)", "Motorização", 0, None, None, 1),
                ("vin", "VARCHAR(17)", "Número de chassis", 0, None, None, 1),
                ("km_atual", "INT", "Quilometragem atual", 0, None, None, 1),
                ("consumo_l_100", "DECIMAL(5,2)", "Consumo médio L/100km", 0, None, None, 1),
                ("autonomia_media_km", "INT", "Autonomia (elétrico)", 0, None, None, 1),
                ("capacidade_deposito_litros", "DECIMAL(6,2)", "Capacidade do depósito", 0, None, None, 1),
                ("kwh_bateria", "DECIMAL(6,2)", "Capacidade da bateria kWh", 0, None, None, 1),
                ("tipo_oleo", "VARCHAR(80)", "Tipo de óleo", 0, None, None, 1),
                ("pneus_medida", "VARCHAR(40)", "Medida dos pneus", 0, None, None, 1),
                ("pneus_pressao", "VARCHAR(20)", "Pressão recomendada", 0, None, None, 1),
                ("data_ultima_inspecao", "DATE", "Última inspeção", 0, None, None, 1),
                ("data_ultimo_servico", "DATE", "Último serviço", 0, None, None, 1),
                ("seguro_seguradora", "VARCHAR(100)", "Seguradora", 0, None, None, 1),
                ("seguro_apolice", "VARCHAR(50)", "Número da apólice", 0, None, None, 1),
                ("seguro_validade", "DATE", "Validade do seguro", 0, None, None, 1),
                ("foto_relativa", "VARCHAR(255)", "Caminho da foto", 0, None, None, 1),
                ("notas_oficina", "TEXT", "Notas internas da oficina", 0, None, None, 1),
                ("atualizado_por", "VARCHAR(20)", "cliente / mecanico", 0, None, "cliente, mecanico", 1),
                ("atualizado_em", "DATETIME", "Última atualização", 0, None, None, 1),
            ],
        },
        {
            "nome": "carro_perfil_pedido",
            "categoria": "Veículos",
            "tipo": "principal",
            "descricao": "Pedidos do mecânico ao cliente para completar dados do veículo.",
            "uso_principal": "Fluxo 'Pedir dados do carro' na área do mecânico.",
            "ficheiros_uso": "inc_veiculo_perfil.php, mecanico_pedir_perfil.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("id_carro", "INT", "FK → carro.id_carro", 0, "carro.id_carro", None, 0),
                ("id_utilizador", "INT", "FK → utilizador.id_utilizador", 0, "utilizador.id_utilizador", None, 1),
                ("id_servico", "INT", "FK opcional → servico.id", 0, "servico.id", None, 1),
                ("id_mecanico", "INT", "FK → mecanicos.id_mecanico", 0, "mecanicos.id_mecanico", None, 1),
                ("estado", "VARCHAR(20)", "pendente / resolvido", 0, None, "pendente, resolvido", 1),
                ("created_at", "DATETIME", "Data do pedido", 0, None, None, 1),
                ("resolved_at", "DATETIME", "Data de resolução", 0, None, None, 1),
            ],
        },
        {
            "nome": "servico_orcamento_item",
            "categoria": "Orçamentos e pagamentos",
            "tipo": "principal",
            "descricao": "Linhas detalhadas do orçamento interativo.",
            "uso_principal": "Composição de orçamento com peças e mão de obra.",
            "ficheiros_uso": "inc_orcamento_interativo.php, configuracoes.php, indexchefeoficina.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("servico_id", "INT", "FK → servico.id", 0, "servico.id", None, 0),
                ("orden", "INT", "Ordem de apresentação", 0, None, None, 1),
                ("categoria", "VARCHAR(30)", "peca / mao_obra / outro / taxa", 0, None, "peca, mao_obra, outro, taxa", 1),
                ("descricao", "VARCHAR(255)", "Descrição da linha", 0, None, None, 1),
                ("quantidade", "DECIMAL(10,2)", "Quantidade", 0, None, None, 1),
                ("preco_unitario", "DECIMAL(10,2)", "Preço unitário", 0, None, None, 1),
                ("minutos_trabalho", "INT", "Minutos estimados", 0, None, None, 1),
                ("grupo_alternativa", "VARCHAR(50)", "Grupo de alternativas", 0, None, None, 1),
                ("decisao_cliente", "VARCHAR(20)", "aceite / recusado / pendente", 0, None, "aceite, recusado, pendente", 1),
            ],
        },
        {
            "nome": "mecanicos",
            "categoria": "Equipa e RH",
            "tipo": "principal",
            "descricao": "Mecânicos da oficina.",
            "uso_principal": "Login, área do mecânico e atribuição de trabalhos.",
            "ficheiros_uso": "funcionarios.php, login.php, indexadmin.php, bate_ponto.php",
            "colunas": [
                ("id_mecanico", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome_mecanico", "VARCHAR(255)", "Nome", 0, None, None, 0),
                ("email", "VARCHAR(255)", "Email de login", 0, None, None, 0),
                ("password", "VARCHAR(255)", "Password encriptada", 0, None, None, 0),
                ("contacto", "VARCHAR(20)", "Telefone", 0, None, None, 1),
                ("morada", "VARCHAR(200)", "Morada", 0, None, None, 1),
                ("especialidade", "VARCHAR(100)", "Especialidade técnica", 0, None, None, 1),
                ("ordenado_bruto", "DECIMAL(10,2)", "Salário bruto", 0, None, None, 1),
                ("salario_bruto", "DECIMAL(10,2)", "Alias de salário (legado)", 0, None, None, 1),
                ("data_contratacao", "DATE", "Data de contratação", 0, None, None, 1),
                ("nif", "VARCHAR(20)", "NIF", 0, None, None, 1),
                ("iban", "VARCHAR(50)", "IBAN", 0, None, None, 1),
                ("ativo", "TINYINT(1)", "Conta ativa", 0, None, "0, 1", 1),
                ("primeiro_login", "TINYINT(1)", "Forçar alteração de password", 0, None, "0, 1", 1),
                ("id_chefe", "INT", "FK → chefes_oficina.id_chefe", 0, "chefes_oficina.id_chefe", None, 1),
                ("data_despedimento", "DATE", "Data de despedimento", 0, None, None, 1),
                ("motivo_despedimento", "TEXT", "Motivo do despedimento", 0, None, None, 1),
                ("reset_code", "VARCHAR(64)", "Recuperação de password", 0, None, None, 1),
                ("reset_expire", "DATETIME", "Expiração do código", 0, None, None, 1),
                ("created_at", "TIMESTAMP", "Criação", 0, None, None, 1),
                ("updated_at", "TIMESTAMP", "Atualização", 0, None, None, 1),
            ],
        },
        {
            "nome": "chefes_oficina",
            "categoria": "Equipa e RH",
            "tipo": "principal",
            "descricao": "Chefes de oficina.",
            "uso_principal": "Painel de atribuição e orçamentos.",
            "ficheiros_uso": "indexchefeoficina.php, login.php, indexadmin.php",
            "colunas": [
                ("id_chefe", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome", "VARCHAR(255)", "Nome", 0, None, None, 0),
                ("email", "VARCHAR(255)", "Email de login", 0, None, None, 0),
                ("password", "VARCHAR(255)", "Password encriptada", 0, None, None, 0),
                ("telefone", "VARCHAR(20)", "Telefone", 0, None, None, 1),
                ("nivel_acesso", "VARCHAR(50)", "Nível de acesso", 0, None, None, 1),
                ("ativo", "TINYINT(1)", "Conta ativa", 0, None, "0, 1", 1),
                ("primeiro_login", "TINYINT(1)", "Forçar alteração de password", 0, None, "0, 1", 1),
                ("reset_code", "VARCHAR(64)", "Recuperação de password", 0, None, None, 1),
                ("reset_expire", "DATETIME", "Expiração", 0, None, None, 1),
                ("created_at", "TIMESTAMP", "Criação", 0, None, None, 1),
            ],
        },
        {
            "nome": "orcamentista",
            "categoria": "Equipa e RH",
            "tipo": "principal",
            "descricao": "Orçamentistas (perfil em descontinuação parcial).",
            "uso_principal": "Login e orçamentos legados.",
            "ficheiros_uso": "orcamentista.php, login.php, indexadmin.php",
            "colunas": [
                ("id_orcamentista", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome_orcamentista", "VARCHAR(255)", "Nome", 0, None, None, 0),
                ("email", "VARCHAR(255)", "Email", 0, None, None, 0),
                ("password", "VARCHAR(255)", "Password", 0, None, None, 0),
                ("morada", "VARCHAR(200)", "Morada", 0, None, None, 1),
                ("telefone", "VARCHAR(20)", "Telefone", 0, None, None, 1),
                ("salario_bruto", "DECIMAL(10,2)", "Salário", 0, None, None, 1),
                ("data_contratacao", "DATE", "Contratação", 0, None, None, 1),
                ("nif", "VARCHAR(20)", "NIF", 0, None, None, 1),
                ("iban", "VARCHAR(50)", "IBAN", 0, None, None, 1),
                ("especialidade", "VARCHAR(100)", "Especialidade", 0, None, None, 1),
                ("ativo", "TINYINT(1)", "Ativo", 0, None, "0, 1", 1),
                ("primeiro_login", "TINYINT(1)", "Primeiro login", 0, None, "0, 1", 1),
                ("data_despedimento", "DATE", "Despedimento", 0, None, None, 1),
                ("motivo_despedimento", "TEXT", "Motivo", 0, None, None, 1),
                ("reset_code", "VARCHAR(64)", "Reset", 0, None, None, 1),
                ("reset_expire", "DATETIME", "Expiração reset", 0, None, None, 1),
                ("created_at", "TIMESTAMP", "Criação", 0, None, None, 1),
                ("updated_at", "TIMESTAMP", "Atualização", 0, None, None, 1),
            ],
        },
        {
            "nome": "presenca",
            "categoria": "Equipa e RH",
            "tipo": "principal",
            "descricao": "Bate-ponto dos mecânicos.",
            "uso_principal": "Controlo de presenças.",
            "ficheiros_uso": "bate_ponto.php, api_bate_ponto.php",
            "colunas": [
                ("id_presenca", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("id_mecanico", "INT", "FK → mecanicos.id_mecanico", 0, "mecanicos.id_mecanico", None, 0),
                ("data", "DATE", "Data do registo", 0, None, None, 1),
                ("hora_entrada", "TIME", "Hora de entrada", 0, None, None, 1),
                ("hora_saida", "TIME", "Hora de saída", 0, None, None, 1),
                ("timestamp_entrada", "DATETIME", "Timestamp entrada", 0, None, None, 1),
                ("timestamp_saida", "DATETIME", "Timestamp saída", 0, None, None, 1),
                ("total_horas", "DECIMAL(5,2)", "Total de horas", 0, None, None, 1),
                ("falta", "TINYINT(1)", "Marcada como falta", 0, None, "0, 1", 1),
                ("justificacao", "TEXT", "Justificação", 0, None, None, 1),
                ("criado_em", "TIMESTAMP", "Criação", 0, None, None, 1),
                ("atualizado_em", "TIMESTAMP", "Atualização", 0, None, None, 1),
            ],
        },
        {
            "nome": "historico_salarios",
            "categoria": "Equipa e RH",
            "tipo": "principal",
            "descricao": "Auditoria de alterações salariais.",
            "uso_principal": "Histórico de aumentos salariais.",
            "ficheiros_uso": "api_aumentar_salario.php, gestao_funcionarios_chefe.php",
            "colunas": [
                ("id_historico", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("id_mecanico", "INT", "FK → mecanicos.id_mecanico", 0, "mecanicos.id_mecanico", None, 0),
                ("salario_anterior", "DECIMAL(10,2)", "Salário anterior", 0, None, None, 1),
                ("salario_novo", "DECIMAL(10,2)", "Novo salário", 0, None, None, 1),
                ("data_alteracao", "DATE", "Data da alteração", 0, None, None, 1),
                ("motivo_alteracao", "TEXT", "Motivo", 0, None, None, 1),
                ("alterado_por", "INT", "FK → chefes_oficina.id_chefe", 0, "chefes_oficina.id_chefe", None, 1),
                ("criado_em", "TIMESTAMP", "Criação", 0, None, None, 1),
            ],
        },
        {
            "nome": "chat_mensagens",
            "categoria": "Comunicação e suporte",
            "tipo": "principal",
            "descricao": "Chat de suporte cliente ↔ administrador.",
            "uso_principal": "Mensagens em tempo real.",
            "ficheiros_uso": "chat.php, chat_admin.php, enviar_mensagem.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("de_usuario_id", "INT", "ID remetente (NULL = admin)", 0, "utilizador.id_utilizador", None, 1),
                ("de_usuario_nome", "VARCHAR(255)", "Nome do remetente", 0, None, None, 1),
                ("para_usuario_id", "INT", "ID destinatário (0 = admin)", 0, None, None, 1),
                ("mensagem", "TEXT", "Texto da mensagem", 0, None, None, 0),
                ("data_envio", "DATETIME", "Data/hora de envio", 0, None, None, 1),
                ("lida_admin", "TINYINT(1)", "Lida pelo admin", 0, None, "0, 1", 1),
                ("lida_usuario", "TINYINT(1)", "Lida pelo cliente", 0, None, "0, 1", 1),
            ],
        },
        {
            "nome": "feedback_duvidas",
            "categoria": "Comunicação e suporte",
            "tipo": "principal",
            "descricao": "Feedbacks e dúvidas do site.",
            "uso_principal": "Formulário de contacto/feedback.",
            "ficheiros_uso": "feedback.php, processar_feedback.php, indexadmin.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome", "VARCHAR(255)", "Nome", 0, None, None, 0),
                ("email", "VARCHAR(255)", "Email", 0, None, None, 0),
                ("tipo", "ENUM/VARCHAR", "feedback / duvida", 0, None, "feedback, duvida", 1),
                ("assunto", "VARCHAR(255)", "Assunto", 0, None, None, 1),
                ("mensagem", "TEXT", "Mensagem", 0, None, None, 0),
                ("resposta", "TEXT", "Resposta do admin", 0, None, None, 1),
                ("estado", "VARCHAR(20)", "pendente / respondido / resolvido", 0, None, "pendente, respondido, resolvido", 1),
                ("data_envio", "DATETIME", "Envio", 0, None, None, 1),
                ("data_resposta", "DATETIME", "Resposta", 0, None, None, 1),
            ],
        },
        {
            "nome": "pedidos_desbloqueio",
            "categoria": "Utilizadores e autenticação",
            "tipo": "principal",
            "descricao": "Pedidos de reativação de contas bloqueadas.",
            "uso_principal": "Suporte a clientes bloqueados.",
            "ficheiros_uso": "solicitar_desbloqueio.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("id_utilizador", "INT", "FK → utilizador", 0, "utilizador.id_utilizador", None, 1),
                ("email", "VARCHAR(255)", "Email", 0, None, None, 1),
                ("nome_utilizador", "VARCHAR(255)", "Nome", 0, None, None, 1),
                ("motivo", "VARCHAR(255)", "Motivo", 0, None, None, 1),
                ("mensagem", "TEXT", "Mensagem", 0, None, None, 1),
                ("data_pedido", "DATETIME", "Data do pedido", 0, None, None, 1),
                ("estado", "VARCHAR(20)", "pendente / aprovado / rejeitado", 0, None, "pendente, aprovado, rejeitado", 1),
                ("data_resposta", "DATETIME", "Resposta", 0, None, None, 1),
                ("resposta", "TEXT", "Texto da resposta", 0, None, None, 1),
            ],
        },
        {
            "nome": "estado_site",
            "categoria": "Site e sessões",
            "tipo": "config",
            "descricao": "Modo de manutenção do site.",
            "uso_principal": "Ligar/desligar manutenção.",
            "ficheiros_uso": "login.php, indexadmin.php",
            "colunas": [
                ("id", "INT", "Chave primária (fixo: 1)", 1, None, None, 0),
                ("estado", "TINYINT(1)", "0=online, 1=manutenção", 0, None, "0, 1", 0),
                ("mensagem", "TEXT", "Mensagem aos visitantes", 0, None, None, 1),
                ("data_atualizacao", "DATETIME", "Última alteração", 0, None, None, 1),
            ],
        },
        {
            "nome": "atualizacoes_site",
            "categoria": "Site e sessões",
            "tipo": "config",
            "descricao": "Banners de novidades no site.",
            "uso_principal": "Avisos no index.php.",
            "ficheiros_uso": "index.php, marcar_mensagem_vista.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("mensagem", "TEXT", "Texto da atualização", 0, None, None, 0),
                ("data_publicacao", "DATETIME", "Publicação", 0, None, None, 1),
            ],
        },
        {
            "nome": "sessoes_temporarias",
            "categoria": "Site e sessões",
            "tipo": "principal",
            "descricao": "Visitantes não autenticados (sessão limitada).",
            "uso_principal": "Demo/trial de 2 minutos.",
            "ficheiros_uso": "check_session.php, login.php, verificar_tempo.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("ip_address", "VARCHAR(45)", "IP", 0, None, None, 1),
                ("user_agent", "TEXT", "Browser/dispositivo", 0, None, None, 1),
                ("primeiro_acesso", "DATETIME", "Primeiro acesso", 0, None, None, 1),
                ("ultimo_acesso", "DATETIME", "Último acesso", 0, None, None, 1),
                ("tempo_restante", "INT", "Segundos restantes", 0, None, None, 1),
                ("visitas", "INT", "Número de visitas", 0, None, None, 1),
                ("pagina_atual", "VARCHAR(255)", "Última página", 0, None, None, 1),
                ("expirou", "TINYINT(1)", "Sessão expirada", 0, None, "0, 1", 1),
                ("utilizador_id", "INT", "FK opcional após login", 0, "utilizador.id_utilizador", None, 1),
            ],
        },
        {
            "nome": "avaliacoes_temp",
            "categoria": "Site e sessões",
            "tipo": "principal",
            "descricao": "Avaliações de visitantes temporários.",
            "uso_principal": "Popup de estrelas no index.php.",
            "ficheiros_uso": "avaliacao_popup.php, index.php, cron_emails.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome", "VARCHAR(255)", "Nome", 0, None, None, 1),
                ("email", "VARCHAR(255)", "Email", 0, None, None, 1),
                ("avaliacao", "TINYINT", "Nota 1-5", 0, None, "1,2,3,4,5", 1),
                ("comentario", "TEXT", "Comentário", 0, None, None, 1),
                ("ip_address", "VARCHAR(45)", "IP", 0, None, None, 1),
                ("data_avaliacao", "DATETIME", "Data", 0, None, None, 1),
            ],
        },
        {
            "nome": "logs",
            "categoria": "Sistema",
            "tipo": "sistema",
            "descricao": "Registos de atividade do sistema.",
            "uso_principal": "Auditoria e debug.",
            "ficheiros_uso": "indexadmin.php, indexchefedeoficina.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("origem", "VARCHAR(100)", "Módulo de origem", 0, None, None, 1),
                ("mensagem", "TEXT", "Mensagem", 0, None, None, 1),
                ("dados", "TEXT", "Dados extra JSON/texto", 0, None, None, 1),
                ("criado_em", "DATETIME", "Timestamp", 0, None, None, 1),
            ],
        },
        {
            "nome": "logs_visitas_temporarias",
            "categoria": "Sistema",
            "tipo": "sistema",
            "descricao": "Páginas visitadas em sessões temporárias.",
            "uso_principal": "Analytics de visitantes trial.",
            "ficheiros_uso": "verificar_sessoes.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("sessao_id", "INT", "FK → sessoes_temporarias.id", 0, "sessoes_temporarias.id", None, 1),
                ("ip_address", "VARCHAR(45)", "IP", 0, None, None, 1),
                ("pagina", "VARCHAR(255)", "URL/página", 0, None, None, 1),
                ("data_acesso", "DATETIME", "Acesso", 0, None, None, 1),
            ],
        },
        {
            "nome": "emails_marketing",
            "categoria": "Marketing",
            "tipo": "principal",
            "descricao": "Lista de emails para campanhas.",
            "uso_principal": "Follow-up automático.",
            "ficheiros_uso": "cron_emails.php, optout.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("email", "VARCHAR(255)", "Email", 0, None, None, 0),
                ("nome", "VARCHAR(255)", "Nome", 0, None, None, 1),
                ("ativo", "TINYINT(1)", "Subscrição ativa", 0, None, "0, 1", 1),
                ("token_optout", "VARCHAR(64)", "Token de cancelamento", 0, None, None, 1),
                ("ultimo_envio", "DATETIME", "Último email", 0, None, None, 1),
                ("total_envios", "INT", "Contador", 0, None, None, 1),
                ("data_registo", "DATETIME", "Registo", 0, None, None, 1),
            ],
        },
        {
            "nome": "passaporte_eventos",
            "categoria": "Passaporte digital",
            "tipo": "principal",
            "descricao": "Histórico de eventos por veículo.",
            "uso_principal": "Passaporte digital do carro.",
            "ficheiros_uso": "passaporte_veiculo.php, inc_inovacao.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("email", "VARCHAR(255)", "Proprietário", 0, None, None, 1),
                ("matricula", "VARCHAR(20)", "Matrícula", 0, None, None, 1),
                ("tipo", "VARCHAR(50)", "Tipo de evento", 0, None, None, 1),
                ("descricao", "TEXT", "Descrição", 0, None, None, 1),
                ("odometro", "INT", "Km no evento", 0, None, None, 1),
                ("data_evento", "DATE", "Data do evento", 0, None, None, 1),
                ("origem", "VARCHAR(30)", "sistema / manual", 0, None, None, 1),
                ("hash_evento", "VARCHAR(64)", "Anti-duplicados", 0, None, None, 1),
                ("created_at", "DATETIME", "Criação", 0, None, None, 1),
            ],
        },
        {
            "nome": "passaporte_links_partilha",
            "categoria": "Passaporte digital",
            "tipo": "principal",
            "descricao": "Links públicos temporários do passaporte.",
            "uso_principal": "Partilha do histórico do veículo.",
            "ficheiros_uso": "passaporte_veiculo.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("email", "VARCHAR(255)", "Proprietário", 0, None, None, 1),
                ("matricula", "VARCHAR(20)", "Matrícula", 0, None, None, 1),
                ("token", "VARCHAR(64)", "Token único", 0, None, None, 0),
                ("expira_em", "DATETIME", "Expiração (7 dias)", 0, None, None, 1),
                ("revogado_em", "DATETIME", "Revogação", 0, None, None, 1),
                ("views", "INT", "Visualizações", 0, None, None, 1),
                ("created_at", "DATETIME", "Criação", 0, None, None, 1),
            ],
        },
        {
            "nome": "marcas",
            "categoria": "Catálogo",
            "tipo": "principal",
            "descricao": "Marcas de veículos para formulários.",
            "uso_principal": "Dropdown de marcas na marcação.",
            "ficheiros_uso": "inc_marcas_modelos.php, index.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome", "VARCHAR(100)", "Nome da marca (único)", 0, None, None, 0),
            ],
        },
        {
            "nome": "modelos",
            "categoria": "Catálogo",
            "tipo": "principal",
            "descricao": "Modelos por marca.",
            "uso_principal": "Dropdown de modelos.",
            "ficheiros_uso": "inc_marcas_modelos.php, index.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("id_marca", "INT", "FK → marcas.id", 0, "marcas.id", None, 0),
                ("nome", "VARCHAR(100)", "Nome do modelo", 0, None, None, 0),
            ],
        },
        {
            "nome": "telegram_config",
            "categoria": "Integrações",
            "tipo": "config",
            "descricao": "Configuração do bot Telegram.",
            "uso_principal": "Notificações Telegram.",
            "ficheiros_uso": "config_telegram.php, enviar_notificacao_telegram.php",
            "colunas": [
                ("id", "INT", "Chave primária", 1, None, None, 0),
                ("bot_token", "VARCHAR(255)", "Token do bot", 0, None, None, 1),
                ("chat_id_admin", "VARCHAR(50)", "Chat ID admin", 0, None, None, 1),
                ("ativo", "TINYINT(1)", "Ativo", 0, None, "0, 1", 1),
            ],
        },
        {
            "nome": "ai_conversas",
            "categoria": "Inteligência artificial",
            "tipo": "principal",
            "descricao": "Conversas do módulo AutoAssist AI.",
            "uso_principal": "Histórico de chat AI.",
            "ficheiros_uso": "ai-chat.php, gerar_relatorio.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("data_registro", "DATETIME", "Data da conversa", 0, None, None, 1),
            ],
        },
        {
            "nome": "ai_diagnosticos",
            "categoria": "Inteligência artificial",
            "tipo": "principal",
            "descricao": "Diagnósticos registados pela AI.",
            "uso_principal": "Relatórios de diagnóstico.",
            "ficheiros_uso": "gerar_relatorio.php, ai-estimativa.php",
            "colunas": [
                ("id", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("problema_identificado", "TEXT", "Problema detetado", 0, None, None, 1),
                ("data_analise", "DATETIME", "Data da análise", 0, None, None, 1),
            ],
        },
        {
            "nome": "reparacoes",
            "categoria": "Legado",
            "tipo": "legado",
            "descricao": "Reparações do setup inicial (substituída por servico).",
            "uso_principal": "Protótipos e painéis legados.",
            "ficheiros_uso": "setup_completo.php, indexchefedeoficina.php",
            "colunas": [
                ("id_reparacao", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("descricao", "TEXT", "Descrição", 0, None, None, 1),
                ("estado", "VARCHAR(50)", "Estado", 0, None, None, 1),
                ("data_entrada", "DATETIME", "Entrada", 0, None, None, 1),
                ("mecanico_atribuido", "VARCHAR(255)", "Mecânico", 0, None, None, 1),
                ("cliente_id", "INT", "Cliente legado", 0, "clientes.id_cliente", None, 1),
                ("prioridade", "VARCHAR(20)", "Prioridade", 0, None, None, 1),
                ("data_conclusao", "DATETIME", "Conclusão", 0, None, None, 1),
                ("observacoes", "TEXT", "Observações", 0, None, None, 1),
            ],
        },
        {
            "nome": "clientes",
            "categoria": "Legado",
            "tipo": "legado",
            "descricao": "Clientes do setup inicial (substituída por utilizador).",
            "uso_principal": "Compatibilidade com reparacoes.",
            "ficheiros_uso": "setup_completo.php",
            "colunas": [
                ("id_cliente", "INT AUTO_INCREMENT", "Chave primária", 1, None, None, 0),
                ("nome", "VARCHAR(255)", "Nome", 0, None, None, 1),
                ("email", "VARCHAR(255)", "Email", 0, None, None, 1),
                ("telefone", "VARCHAR(20)", "Telefone", 0, None, None, 1),
                ("ativo", "TINYINT(1)", "Ativo", 0, None, "0, 1", 1),
                ("data_criacao", "DATETIME", "Criação", 0, None, None, 1),
            ],
        },
        {
            "nome": "servicos",
            "categoria": "Legado",
            "tipo": "legado",
            "descricao": "Variante antiga plural de servico.",
            "uso_principal": "Scripts de diagnóstico apenas.",
            "ficheiros_uso": "colunas_bd.php, diagnostico_bd.php",
            "colunas": [
                ("id", "INT", "Chave primária (inferida)", 1, None, None, 0),
            ],
        },
        {
            "nome": "orcamentos",
            "categoria": "Legado",
            "tipo": "legado",
            "descricao": "Orçamentos antigos antes do fluxo em servico.",
            "uso_principal": "Verificação ao eliminar orçamentista.",
            "ficheiros_uso": "indexadmin.php",
            "colunas": [
                ("id", "INT", "Chave primária", 1, None, None, 0),
                ("id_orcamentista", "INT", "FK → orcamentista", 0, "orcamentista.id_orcamentista", None, 1),
            ],
        },
    ],
    "relacoes": [
        ("utilizador", "id_utilizador", "carro", "id_utilizador", "Um cliente tem vários veículos"),
        ("carro", "id_carro", "carro_perfil", "id_carro", "Perfil técnico 1:1"),
        ("carro", "id_carro", "carro_perfil_pedido", "id_carro", "Pedidos de dados do veículo"),
        ("utilizador", "id_utilizador", "servico", "id_utilizador", "Marcações do cliente"),
        ("carro", "id_carro", "servico", "id_carro", "Marcação associada ao veículo"),
        ("servico", "id", "servico_orcamento_item", "servico_id", "Linhas do orçamento"),
        ("mecanicos", "id_mecanico", "servico", "mecanico_id", "Mecânico atribuído"),
        ("chefes_oficina", "id_chefe", "mecanicos", "id_chefe", "Chefe responsável"),
        ("mecanicos", "id_mecanico", "presenca", "id_mecanico", "Presenças do mecânico"),
        ("mecanicos", "id_mecanico", "historico_salarios", "id_mecanico", "Histórico salarial"),
        ("chefes_oficina", "id_chefe", "historico_salarios", "alterado_por", "Quem alterou o salário"),
        ("orcamentista", "id_orcamentista", "servico", "id_orcamentista", "Orçamentista responsável"),
        ("utilizador", "id_utilizador", "pedidos_desbloqueio", "id_utilizador", "Pedido de desbloqueio"),
        ("sessoes_temporarias", "id", "logs_visitas_temporarias", "sessao_id", "Visitas da sessão trial"),
        ("marcas", "id", "modelos", "id_marca", "Modelos da marca"),
    ],
}


def build_db(path: Path) -> None:
    if path.exists():
        path.unlink()

    con = sqlite3.connect(path)
    cur = con.cursor()

    cur.executescript(
        """
        PRAGMA foreign_keys = ON;

        CREATE TABLE info_projeto (
            chave TEXT PRIMARY KEY,
            valor TEXT NOT NULL
        );

        CREATE TABLE tabelas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL UNIQUE,
            categoria TEXT,
            tipo TEXT,
            descricao TEXT,
            uso_principal TEXT,
            ficheiros_uso TEXT
        );

        CREATE TABLE colunas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tabela_id INTEGER NOT NULL,
            ordem INTEGER NOT NULL DEFAULT 0,
            nome TEXT NOT NULL,
            tipo_sql TEXT,
            descricao TEXT,
            chave_primaria INTEGER NOT NULL DEFAULT 0,
            chave_estrangeira TEXT,
            valores_possiveis TEXT,
            opcional INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (tabela_id) REFERENCES tabelas(id) ON DELETE CASCADE
        );

        CREATE TABLE relacoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tabela_origem TEXT NOT NULL,
            coluna_origem TEXT NOT NULL,
            tabela_destino TEXT NOT NULL,
            coluna_destino TEXT NOT NULL,
            descricao TEXT
        );

        CREATE VIEW v_schema_completo AS
        SELECT
            t.nome AS tabela,
            t.categoria,
            t.tipo AS tipo_tabela,
            t.descricao AS descricao_tabela,
            t.uso_principal,
            t.ficheiros_uso,
            c.ordem,
            c.nome AS coluna,
            c.tipo_sql,
            c.descricao AS descricao_coluna,
            c.chave_primaria,
            c.chave_estrangeira,
            c.valores_possiveis,
            c.opcional
        FROM tabelas t
        JOIN colunas c ON c.tabela_id = t.id
        ORDER BY t.nome, c.ordem, c.nome;

        CREATE VIEW v_resumo_tabelas AS
        SELECT
            t.nome AS tabela,
            t.categoria,
            t.tipo,
            t.descricao,
            COUNT(c.id) AS total_colunas,
            SUM(CASE WHEN c.chave_primaria = 1 THEN 1 ELSE 0 END) AS colunas_pk
        FROM tabelas t
        LEFT JOIN colunas c ON c.tabela_id = t.id
        GROUP BY t.id
        ORDER BY t.categoria, t.nome;
        """
    )

    for chave, valor in SCHEMA["info_projeto"].items():
        cur.execute("INSERT INTO info_projeto (chave, valor) VALUES (?, ?)", (chave, str(valor)))

    for tabela in SCHEMA["tabelas"]:
        cur.execute(
            """
            INSERT INTO tabelas (nome, categoria, tipo, descricao, uso_principal, ficheiros_uso)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            (
                tabela["nome"],
                tabela["categoria"],
                tabela["tipo"],
                tabela["descricao"],
                tabela["uso_principal"],
                tabela["ficheiros_uso"],
            ),
        )
        tabela_id = cur.lastrowid
        for ordem, col in enumerate(tabela["colunas"], start=1):
            nome, tipo_sql, descricao, pk, fk, valores, opcional = col
            cur.execute(
                """
                INSERT INTO colunas (
                    tabela_id, ordem, nome, tipo_sql, descricao,
                    chave_primaria, chave_estrangeira, valores_possiveis, opcional
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (tabela_id, ordem, nome, tipo_sql, descricao, pk, fk, valores, opcional),
            )

    for rel in SCHEMA["relacoes"]:
        cur.execute(
            """
            INSERT INTO relacoes (tabela_origem, coluna_origem, tabela_destino, coluna_destino, descricao)
            VALUES (?, ?, ?, ?, ?)
            """,
            rel,
        )

    cur.execute(
        "INSERT INTO info_projeto (chave, valor) VALUES (?, ?)",
        ("total_tabelas", str(len(SCHEMA["tabelas"]))),
    )
    cur.execute(
        "SELECT COUNT(*) FROM colunas",
    )
    total_cols = cur.fetchone()[0]
    cur.execute(
        "INSERT INTO info_projeto (chave, valor) VALUES (?, ?)",
        ("total_colunas", str(total_cols)),
    )

    con.commit()
    con.close()


def main() -> None:
    build_db(OUT_DB)
    print(f"OK: {OUT_DB}")
    print(f"Tabelas: {len(SCHEMA['tabelas'])}")
    print(f"Relações: {len(SCHEMA['relacoes'])}")


if __name__ == "__main__":
    main()
