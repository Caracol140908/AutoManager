<?php

declare(strict_types=1);

/**
 * Marcas e modelos de veículos (tabelas marcas / modelos).
 */

if (!function_exists('am_ensure_marcas_modelos_tables')) {
    function am_ensure_marcas_modelos_tables(mysqli $con): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        @$con->query("
            CREATE TABLE IF NOT EXISTS marcas (
                id INT NOT NULL AUTO_INCREMENT,
                nome VARCHAR(100) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_marcas_nome (nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ");

        @$con->query("
            CREATE TABLE IF NOT EXISTS modelos (
                id INT NOT NULL AUTO_INCREMENT,
                id_marca INT NOT NULL,
                nome VARCHAR(100) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_modelos_marca (id_marca)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ");

        $cntRes = @$con->query('SELECT COUNT(*) AS total FROM marcas');
        $total = 0;
        if ($cntRes) {
            $row = $cntRes->fetch_assoc();
            $total = (int) ($row['total'] ?? 0);
        }

        if ($total > 0) {
            return;
        }

        $marcas = [
            [26, 'Alfa Romeo'], [11, 'Audi'], [5, 'BMW'], [31, 'BYD'], [9, 'Citroën'],
            [3, 'Dacia'], [12, 'Fiat'], [13, 'Ford'], [14, 'Honda'], [15, 'Hyundai'],
            [29, 'Jeep'], [16, 'Kia'], [27, 'Land Rover'], [28, 'Lexus'], [17, 'Mazda'],
            [2, 'Mercedes-Benz'], [30, 'MG'], [18, 'Mini'], [19, 'Mitsubishi'], [20, 'Nissan'],
            [21, 'Opel'], [1, 'Peugeot'], [4, 'Renault'], [10, 'Seat'], [22, 'Skoda'],
            [23, 'Smart'], [24, 'Suzuki'], [8, 'Tesla'], [7, 'Toyota'], [6, 'Volkswagen'],
            [25, 'Volvo'],
        ];

        $modelos = [
            [2, 1, '2008'], [1, 1, '208'], [4, 1, '3008'], [3, 1, '308'], [6, 1, '5008'],
            [5, 1, '508'], [7, 1, 'Partner / Rifter'], [16, 2, 'CLA'], [8, 2, 'Classe A'],
            [9, 2, 'Classe C'], [10, 2, 'Classe E'], [13, 2, 'EQA'], [14, 2, 'EQB'],
            [15, 2, 'EQC'], [11, 2, 'GLA'], [12, 2, 'GLC'], [18, 3, 'Duster'],
            [20, 3, 'Jogger'], [21, 3, 'Logan'], [17, 3, 'Sandero'], [19, 3, 'Spring'],
            [26, 4, 'Austral'], [23, 4, 'Captur'], [22, 4, 'Clio'], [28, 4, 'Kangoo'],
            [24, 4, 'Megane'], [25, 4, 'Twingo'], [27, 4, 'Zoe'], [35, 5, 'i4'],
            [38, 5, 'iX'], [36, 5, 'iX1'], [37, 5, 'iX3'], [29, 5, 'Série 1'],
            [30, 5, 'Série 3'], [31, 5, 'Série 5'], [32, 5, 'X1'], [33, 5, 'X3'],
            [34, 5, 'X5'], [39, 6, 'Golf'], [44, 6, 'ID.3'], [45, 6, 'ID.4'],
            [46, 6, 'ID.5'], [43, 6, 'Passat'], [40, 6, 'Polo'], [41, 6, 'T-Roc'],
            [47, 6, 'Taigo'], [42, 6, 'Tiguan'], [52, 7, 'Aygo'], [50, 7, 'C-HR'],
            [49, 7, 'Corolla'], [54, 7, 'Prius'], [51, 7, 'RAV4'], [48, 7, 'Yaris'],
            [53, 7, 'Yaris Cross'], [55, 8, 'Model 3'], [57, 8, 'Model S'],
            [58, 8, 'Model X'], [56, 8, 'Model Y'], [65, 9, 'Berlingo'], [59, 9, 'C3'],
            [60, 9, 'C3 Aircross'], [61, 9, 'C4'], [62, 9, 'C4 X'], [63, 9, 'C5 Aircross'],
            [64, 9, 'C5 X'], [68, 10, 'Arona'], [69, 10, 'Ateca'], [66, 10, 'Ibiza'],
            [67, 10, 'Leon'], [70, 10, 'Tarraco'], [71, 11, 'A3'], [72, 11, 'A4'],
            [73, 11, 'A6'], [77, 11, 'e-tron / Q8 e-tron'], [74, 11, 'Q3'],
            [75, 11, 'Q5'], [76, 11, 'Q8'], [78, 12, '500'], [82, 12, '500e'],
            [81, 12, '500X'], [79, 12, 'Panda'], [80, 12, 'Tipo'], [83, 13, 'Fiesta'],
            [84, 13, 'Focus'], [86, 13, 'Kuga'], [87, 13, 'Mustang Mach-E'],
            [85, 13, 'Puma'], [88, 13, 'Tourneo Custom'], [89, 14, 'Civic'],
            [90, 14, 'CR-V'], [93, 14, 'e'], [91, 14, 'HR-V'], [92, 14, 'Jazz'],
            [101, 15, 'Bayon'], [94, 15, 'i10'], [95, 15, 'i20'], [96, 15, 'i30'],
            [99, 15, 'IONIQ 5'], [100, 15, 'IONIQ 6'], [97, 15, 'Kona'],
            [98, 15, 'Tucson'], [104, 16, 'Ceed'], [107, 16, 'EV6'], [108, 16, 'Niro'],
            [102, 16, 'Picanto'], [103, 16, 'Rio'], [106, 16, 'Sportage'],
            [105, 16, 'Stonic'], [111, 17, 'CX-30'], [112, 17, 'CX-5'],
            [109, 17, 'Mazda2'], [110, 17, 'Mazda3'], [113, 17, 'MX-30'],
            [114, 18, '3 Portas'], [115, 18, '5 Portas'], [117, 18, 'Clubman'],
            [118, 18, 'Cooper SE'], [116, 18, 'Countryman'], [119, 19, 'ASX'],
            [121, 19, 'Eclipse Cross'], [123, 19, 'L200'], [122, 19, 'Outlander PHEV'],
            [120, 19, 'Space Star'], [125, 20, 'Juke'], [128, 20, 'Leaf'],
            [124, 20, 'Micra'], [126, 20, 'Qashqai'], [127, 20, 'X-Trail'],
            [130, 21, 'Astra'], [129, 21, 'Corsa'], [132, 21, 'Crossland'],
            [133, 21, 'Grandland'], [131, 21, 'Mokka'], [134, 21, 'Zafira Life'],
            [141, 22, 'Enyaq'], [135, 22, 'Fabia'], [138, 22, 'Kamiq'],
            [139, 22, 'Karoq'], [140, 22, 'Kodiaq'], [136, 22, 'Octavia'],
            [137, 22, 'Superb'], [143, 23, 'EQ forfour'], [142, 23, 'EQ fortwo'],
            [144, 23, 'Smart #1'], [148, 24, 'Ignis'], [149, 24, 'Jimny'],
            [147, 24, 'S-Cross'], [145, 24, 'Swift'], [146, 24, 'Vitara'],
            [154, 25, 'C40'], [150, 25, 'EX30'], [155, 25, 'S60'], [153, 25, 'V60'],
            [151, 25, 'XC40'], [152, 25, 'XC60'], [156, 26, 'Giulia'],
            [157, 26, 'Stelvio'], [158, 26, 'Tonale'], [162, 27, 'Defender'],
            [159, 27, 'Discovery Sport'], [160, 27, 'Range Rover Evoque'],
            [161, 27, 'Range Rover Velar'], [166, 28, 'ES'], [167, 28, 'LBX'],
            [164, 28, 'NX'], [165, 28, 'RX'], [163, 28, 'UX'], [170, 29, 'Avenger'],
            [169, 29, 'Compass'], [168, 29, 'Renegade'], [171, 29, 'Wrangler'],
            [175, 30, 'HS'], [173, 30, 'MG4 Electric'], [174, 30, 'MG5 Electric'],
            [172, 30, 'ZS EV'], [176, 31, 'Atto 3'], [177, 31, 'Dolphin'],
            [178, 31, 'Seal'],
        ];

        $stmtMarca = $con->prepare('INSERT IGNORE INTO marcas (id, nome) VALUES (?, ?)');
        if ($stmtMarca) {
            foreach ($marcas as [$id, $nome]) {
                $stmtMarca->bind_param('is', $id, $nome);
                $stmtMarca->execute();
            }
            $stmtMarca->close();
        }

        $stmtModelo = $con->prepare('INSERT IGNORE INTO modelos (id, id_marca, nome) VALUES (?, ?, ?)');
        if ($stmtModelo) {
            foreach ($modelos as [$id, $idMarca, $nome]) {
                $stmtModelo->bind_param('iis', $id, $idMarca, $nome);
                $stmtModelo->execute();
            }
            $stmtModelo->close();
        }

        @$con->query('ALTER TABLE marcas AUTO_INCREMENT = 32');
        @$con->query('ALTER TABLE modelos AUTO_INCREMENT = 179');
    }
}

if (!function_exists('am_marcas_listar')) {
    /** @return list<array{id:int,nome:string}> */
    function am_marcas_listar(mysqli $con): array
    {
        am_ensure_marcas_modelos_tables($con);

        $out = [];
        $res = $con->query('SELECT id, nome FROM marcas ORDER BY nome ASC');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'nome' => (string) $row['nome'],
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('am_modelos_por_marca_map')) {
    /** @return array<string, list<string>> */
    function am_modelos_por_marca_map(mysqli $con): array
    {
        am_ensure_marcas_modelos_tables($con);

        $map = [];
        $sql = '
            SELECT m.nome AS marca, mo.nome AS modelo
            FROM modelos mo
            INNER JOIN marcas m ON m.id = mo.id_marca
            ORDER BY m.nome ASC, mo.nome ASC
        ';
        $res = $con->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $marca = (string) $row['marca'];
                if (!isset($map[$marca])) {
                    $map[$marca] = [];
                }
                $map[$marca][] = (string) $row['modelo'];
            }
        }

        return $map;
    }
}

if (!function_exists('am_modelos_listar_completo')) {
    /** @return list<array{id:int,id_marca:int,marca:string,modelo:string}> */
    function am_modelos_listar_completo(mysqli $con): array
    {
        am_ensure_marcas_modelos_tables($con);

        $out = [];
        $sql = '
            SELECT mo.id, mo.id_marca, mo.nome AS modelo, m.nome AS marca
            FROM modelos mo
            INNER JOIN marcas m ON m.id = mo.id_marca
            ORDER BY m.nome ASC, mo.nome ASC
        ';
        $res = $con->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'id_marca' => (int) $row['id_marca'],
                    'marca' => (string) $row['marca'],
                    'modelo' => (string) $row['modelo'],
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('am_marca_contar_modelos')) {
    function am_marca_contar_modelos(mysqli $con, int $idMarca): int
    {
        if ($idMarca <= 0) {
            return 0;
        }
        $st = $con->prepare('SELECT COUNT(*) AS total FROM modelos WHERE id_marca = ?');
        if (!$st) {
            return 0;
        }
        $st->bind_param('i', $idMarca);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('am_render_marca_options')) {
    function am_render_marca_options(array $marcas, string $selected = '', string $emptyLabel = 'Selecione...'): void
    {
        if ($emptyLabel !== '') {
            echo '<option value="">' . htmlspecialchars($emptyLabel) . '</option>';
        }
        foreach ($marcas as $marca) {
            $nome = (string) ($marca['nome'] ?? '');
            if ($nome === '') {
                continue;
            }
            $sel = ($selected !== '' && $selected === $nome) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($nome) . '"' . $sel . '>' . htmlspecialchars($nome) . '</option>';
        }
    }
}
