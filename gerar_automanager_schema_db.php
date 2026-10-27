<?php
/**
 * Gera automanager_schema.db — catálogo SQLite do esquema da aplicação.
 * Uso: php gerar_automanager_schema_db.php
 */
declare(strict_types=1);

$root = __DIR__;
$mdPath = $root . '/TABELAS_BD.md';
$outDb = $root . '/automanager_schema.db';
$extrasPath = $root . '/scripts/schema_extras.json';

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Extensão pdo_sqlite não disponível.\n");
    exit(1);
}

if (!is_file($mdPath)) {
    fwrite(STDERR, "Ficheiro TABELAS_BD.md não encontrado.\n");
    exit(1);
}

$md = file_get_contents($mdPath);
$parsed = am_schema_parse_markdown($md);
$extras = is_file($extrasPath) ? json_decode((string) file_get_contents($extrasPath), true) : [];
if (!is_array($extras)) {
    $extras = [];
}

$schema = am_schema_merge_extras($parsed, $extras);
am_schema_build_sqlite($outDb, $schema);

echo "OK: {$outDb}\n";
echo 'Tabelas: ' . count($schema['tabelas']) . "\n";
echo 'Colunas: ' . array_sum(array_map(static fn($t) => count($t['colunas']), $schema['tabelas'])) . "\n";
echo 'Relações: ' . count($schema['relacoes']) . "\n";

function am_schema_parse_markdown(string $md): array
{
    $tabelas = [];
    $resumo = [];

    if (preg_match('/## 1\. Resumo rápido\s+([\s\S]*?)---/u', $md, $m)) {
        if (preg_match_all('/\|\s*`([^`]+)`\s*\|\s*([^|]+)\|\s*([^|]+)\|/u', $m[1], $rows, PREG_SET_ORDER)) {
            foreach ($rows as $row) {
                if ($row[1] === '#' || $row[1] === '---' || $row[1] === 'Tabela') {
                    continue;
                }
                $resumo[trim($row[1])] = [
                    'tipo' => trim($row[2]),
                    'uso' => trim($row[3]),
                ];
            }
        }
    }

    if (preg_match('/## 13\. Diagrama de relações\s+```([\s\S]*?)```/u', $md, $mRel)) {
        $relacoes = am_schema_parse_relacoes_texto($mRel[1]);
    } else {
        $relacoes = [];
    }

    $chunks = preg_split('/\n### `/u', $md) ?: [];
    array_shift($chunks);
    foreach ($chunks as $chunk) {
        if (!preg_match('/^([^`\r\n]+)`[^\n]*\n([\s\S]*)/u', $chunk, $block)) {
            continue;
        }
        $nome = trim($block[1]);
        $corpo = $block[2];
        if (preg_match('/\n## \d+\./u', $corpo, $mSec, PREG_OFFSET_CAPTURE)) {
            $corpo = substr($corpo, 0, $mSec[0][1]);
        }
        $categoria = am_schema_categoria_tabela($md, $nome) ?: $categoriaAtual;

        $descricao = '';
        if (preg_match('/^(.*?)(?:\n\n|\n####|\n\| Coluna|\n\*\*Usada)/us', trim($corpo), $dm)) {
            $descricao = trim(preg_replace('/\s+/u', ' ', $dm[1]));
        }

        $ficheiros = '';
        if (preg_match('/\*\*Usada em:\*\*\s*`([^`]+)`/u', $corpo, $fm)) {
            $ficheiros = str_replace('`, `', ', ', $fm[1]);
        } elseif (preg_match('/\*\*Usada em:\*\*\s*(.+)$/mu', $corpo, $fm2)) {
            $ficheiros = trim($fm2[1]);
        }

        $tipo = $resumo[$nome]['tipo'] ?? 'principal';
        $usoPrincipal = $resumo[$nome]['uso'] ?? '';

        $colunas = [];
        $ordem = 0;
        foreach (preg_split('/\R/u', $corpo) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_starts_with($linha, '|')) {
                continue;
            }
            if (str_contains($linha, '---') || str_contains($linha, '| Coluna |')) {
                continue;
            }
            if (!preg_match('/^\|\s*`(.+?)`\s*\|\s*(.+)\s*\|$/u', $linha, $m)) {
                continue;
            }
            $nomesCol = preg_split('/\s*\/\s*/u', $m[1]) ?: [$m[1]];
            foreach ($nomesCol as $nomeCol) {
                $nomeCol = trim(str_replace('`', '', $nomeCol));
                if ($nomeCol === '') {
                    continue;
                }
                $ordem++;
                $descCol = trim($m[2]);
                $pk = (stripos($descCol, 'chave primária') !== false || stripos($descCol, 'pk') === 0) ? 1 : 0;
                $fk = null;
                if (preg_match('/FK\s*(?:opcional\s*)?→\s*`?([a-z0-9_]+)`?(?:\.`?([a-z0-9_]+)`?)?/iu', $descCol, $fkM)) {
                    $fk = $fkM[1] . (isset($fkM[2]) && $fkM[2] !== '' ? '.' . $fkM[2] : '');
                }
                $valores = null;
                if (preg_match_all('/`([^`]+)`/u', $descCol, $vm) && str_contains($descCol, '/')) {
                    $valores = implode(', ', $vm[1]);
                }
                $colunas[] = [
                    'nome' => $nomeCol,
                    'tipo_sql' => am_schema_infer_tipo($nomeCol, $descCol),
                    'descricao' => $descCol,
                    'chave_primaria' => $pk,
                    'chave_estrangeira' => $fk,
                    'valores_possiveis' => $valores,
                    'opcional' => $pk ? 0 : 1,
                    'ordem' => $ordem,
                ];
            }
        }

        $tabelas[] = [
            'nome' => $nome,
            'categoria' => $categoria,
            'tipo' => am_schema_normalizar_tipo($tipo),
            'descricao' => $descricao,
            'uso_principal' => $usoPrincipal,
            'ficheiros_uso' => $ficheiros,
            'colunas' => $colunas,
        ];
    }

    return ['tabelas' => $tabelas, 'relacoes' => $relacoes];
}

function am_schema_categoria_tabela(string $md, string $nome): ?string
{
    $partes = preg_split('/^## /m', $md) ?: [];
    foreach ($partes as $parte) {
        if (strpos($parte, '### `' . $nome . '`') !== false) {
            $linha = strtok($parte, "\n");
            return trim(preg_replace('/^\d+\.\s*/', '', (string) $linha));
        }
    }
    return null;
}

function am_schema_parse_relacoes_texto(string $texto): array
{
    $rel = [];
    $map = [
        'utilizador' => [
            'carro' => ['id_utilizador', 'id_utilizador', 'Cliente possui veículos'],
            'servico' => ['id_utilizador', 'id_utilizador', 'Marcações do cliente'],
            'pedidos_desbloqueio' => ['id_utilizador', 'id_utilizador', 'Pedidos de desbloqueio'],
        ],
        'carro' => [
            'carro_perfil' => ['id_carro', 'id_carro', 'Perfil técnico 1:1'],
            'carro_perfil_pedido' => ['id_carro', 'id_carro', 'Pedidos de perfil'],
            'servico' => ['id_carro', 'id_carro', 'Marcação no veículo'],
        ],
        'servico' => [
            'servico_orcamento_item' => ['id', 'servico_id', 'Linhas de orçamento'],
            'mecanicos' => ['mecanico_id', 'id_mecanico', 'Mecânico atribuído'],
        ],
        'chefes_oficina' => ['mecanicos' => ['id_chefe', 'id_chefe', 'Chefe da equipa']],
        'mecanicos' => [
            'presenca' => ['id_mecanico', 'id_mecanico', 'Presenças'],
            'historico_salarios' => ['id_mecanico', 'id_mecanico', 'Histórico salarial'],
        ],
        'orcamentista' => ['servico' => ['id_orcamentista', 'id_orcamentista', 'Orçamentista']],
        'sessoes_temporarias' => ['logs_visitas_temporarias' => ['id', 'sessao_id', 'Visitas trial']],
        'marcas' => ['modelos' => ['id', 'id_marca', 'Modelos por marca']],
    ];
    foreach ($map as $origem => $destinos) {
        foreach ($destinos as $destino => $dados) {
            $rel[] = [
                'tabela_origem' => $origem,
                'coluna_origem' => $dados[0],
                'tabela_destino' => $destino,
                'coluna_destino' => $dados[1],
                'descricao' => $dados[2],
            ];
        }
    }
    return $rel;
}

function am_schema_infer_tipo(string $nome, string $desc): string
{
    $n = strtolower($nome);
    if (strpos($n, 'data_') === 0 || substr($n, -3) === '_at' || $n === 'created_at' || $n === 'updated_at') {
        return 'DATETIME';
    }
    if (strpos($n, 'email') !== false) {
        return 'VARCHAR(255)';
    }
    if (strpos($desc, 'JSON') !== false) {
        return 'TEXT/JSON';
    }
    if (strpos($desc, 'DECIMAL') !== false || strpos($desc, '€') !== false || strpos($n, 'valor') !== false || strpos($n, 'custo') !== false || strpos($n, 'salario') !== false || strpos($n, 'ordenado') !== false) {
        return 'DECIMAL(10,2)';
    }
    if (strpos($n, 'id_') === 0 || $n === 'id') {
        return 'INT';
    }
    if (strpos($desc, '0/1') !== false || stripos($desc, 'flag') !== false) {
        return 'TINYINT(1)';
    }
    return 'VARCHAR/TEXT';
}

function am_schema_normalizar_tipo(string $tipo): string
{
    $t = strtolower($tipo);
    if (strpos($t, 'legad') !== false) {
        return 'legado';
    }
    if (strpos($t, 'config') !== false) {
        return 'config';
    }
    if (strpos($t, 'central') !== false) {
        return 'central';
    }
    if (strpos($t, 'aux') !== false || strpos($t, 'sistema') !== false) {
        return 'sistema';
    }
    return 'principal';
}

function am_schema_merge_extras(array $parsed, array $extras): array
{
    $byName = [];
    foreach ($parsed['tabelas'] as $t) {
        $byName[$t['nome']] = $t;
    }

    foreach (($extras['colunas_extra'] ?? []) as $tabela => $cols) {
        if (!isset($byName[$tabela])) {
            continue;
        }
        $existentes = array_column($byName[$tabela]['colunas'], 'nome');
        $ordem = count($byName[$tabela]['colunas']);
        foreach ($cols as $col) {
            if (in_array($col['nome'], $existentes, true)) {
                continue;
            }
            $ordem++;
            $col['ordem'] = $ordem;
            $byName[$tabela]['colunas'][] = $col;
        }
    }

    foreach (($extras['tabelas_extra'] ?? []) as $t) {
        if (!isset($byName[$t['nome']])) {
            $byName[$t['nome']] = $t;
        }
    }

    $rel = $parsed['relacoes'];
    foreach (($extras['relacoes_extra'] ?? []) as $r) {
        $rel[] = $r;
    }

    ksort($byName);

    return [
        'tabelas' => array_values($byName),
        'relacoes' => $rel,
        'info_projeto' => array_merge([
            'nome' => 'AutoManager',
            'motor_producao' => 'MySQL / MariaDB',
            'base_dados_producao' => 'automanager',
            'ficheiro_catalogo' => 'automanager_schema.db',
            'descricao' => 'Catálogo de referência com tabelas, colunas, relações e ficheiros PHP. Não substitui a BD de produção.',
            'gerado_em' => gmdate('Y-m-d H:i') . ' UTC',
            'fonte' => 'TABELAS_BD.md + scripts/schema_extras.json + código PHP',
        ], $extras['info_projeto'] ?? []),
    ];
}

function am_schema_build_sqlite(string $path, array $schema): void
{
    if (is_file($path)) {
        unlink($path);
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(<<<'SQL'
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
SQL);

    $insInfo = $pdo->prepare('INSERT INTO info_projeto (chave, valor) VALUES (?, ?)');
    foreach ($schema['info_projeto'] as $k => $v) {
        $insInfo->execute([$k, (string) $v]);
    }

    $insTab = $pdo->prepare('INSERT INTO tabelas (nome, categoria, tipo, descricao, uso_principal, ficheiros_uso) VALUES (?, ?, ?, ?, ?, ?)');
    $insCol = $pdo->prepare('INSERT INTO colunas (tabela_id, ordem, nome, tipo_sql, descricao, chave_primaria, chave_estrangeira, valores_possiveis, opcional) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($schema['tabelas'] as $tabela) {
        $insTab->execute([
            $tabela['nome'],
            $tabela['categoria'] ?? '',
            $tabela['tipo'] ?? 'principal',
            $tabela['descricao'] ?? '',
            $tabela['uso_principal'] ?? '',
            $tabela['ficheiros_uso'] ?? '',
        ]);
        $tabelaId = (int) $pdo->lastInsertId();
        foreach ($tabela['colunas'] as $col) {
            $insCol->execute([
                $tabelaId,
                (int) ($col['ordem'] ?? 0),
                $col['nome'],
                $col['tipo_sql'] ?? '',
                $col['descricao'] ?? '',
                (int) ($col['chave_primaria'] ?? 0),
                $col['chave_estrangeira'] ?? null,
                $col['valores_possiveis'] ?? null,
                (int) ($col['opcional'] ?? 1),
            ]);
        }
    }

    $insRel = $pdo->prepare('INSERT INTO relacoes (tabela_origem, coluna_origem, tabela_destino, coluna_destino, descricao) VALUES (?, ?, ?, ?, ?)');
    foreach ($schema['relacoes'] as $rel) {
        if (isset($rel[0])) {
            $insRel->execute([$rel[0], $rel[1], $rel[2], $rel[3], $rel[4] ?? '']);
        } else {
            $insRel->execute([
                $rel['tabela_origem'],
                $rel['coluna_origem'],
                $rel['tabela_destino'],
                $rel['coluna_destino'],
                $rel['descricao'] ?? '',
            ]);
        }
    }

    $totalCols = (int) $pdo->query('SELECT COUNT(*) FROM colunas')->fetchColumn();
    $insInfo->execute(['total_tabelas', (string) count($schema['tabelas'])]);
    $insInfo->execute(['total_colunas', (string) $totalCols]);
    $insInfo->execute(['total_relacoes', (string) count($schema['relacoes'])]);
}
