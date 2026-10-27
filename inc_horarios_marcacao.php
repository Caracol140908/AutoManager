<?php
/**
 * Horários de marcação — evita duas marcações no mesmo dia e hora.
 */

if (!function_exists('am_horarios_oficina_inicio')) {
    function am_horarios_oficina_inicio(): string
    {
        return '08:00';
    }
}

if (!function_exists('am_horarios_oficina_fim')) {
    function am_horarios_oficina_fim(): string
    {
        return '18:00';
    }
}

if (!function_exists('am_horarios_intervalo_minutos')) {
    function am_horarios_intervalo_minutos(): int
    {
        return 30;
    }
}

if (!function_exists('am_horarios_normalizar_hora')) {
    function am_horarios_normalizar_hora(?string $hora): string
    {
        $hora = trim((string) $hora);
        if ($hora === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }
}

if (!function_exists('am_horarios_lista_base')) {
    /** @return list<string> */
    function am_horarios_lista_base(): array
    {
        $slots = [];
        $inicio = strtotime('today ' . am_horarios_oficina_inicio());
        $fim = strtotime('today ' . am_horarios_oficina_fim());
        $passo = am_horarios_intervalo_minutos() * 60;
        if ($inicio === false || $fim === false || $passo <= 0) {
            return $slots;
        }
        for ($t = $inicio; $t < $fim; $t += $passo) {
            $slots[] = date('H:i', $t);
        }

        return $slots;
    }
}

if (!function_exists('am_horarios_data_minima_marcacao')) {
    function am_horarios_data_minima_marcacao(): string
    {
        return date('Y-m-d', strtotime('+1 day'));
    }
}

if (!function_exists('am_horarios_validar_data_marcacao')) {
    /**
     * @return array{0:bool,1:string}
     */
    function am_horarios_validar_data_marcacao(string $data): array
    {
        $data = trim($data);
        if ($data === '') {
            return [false, 'Selecione uma data.'];
        }
        $ts = strtotime($data);
        if (!$ts) {
            return [false, 'Data inválida.'];
        }
        $hoje = date('Y-m-d');
        if ($data <= $hoje) {
            return [false, 'Não é possível marcar para o dia de hoje. Escolha a partir de amanhã (' . date('d/m/Y', strtotime('+1 day')) . ').'];
        }
        $min = am_horarios_data_minima_marcacao();
        $max = date('Y-m-d', strtotime('+3 months'));
        if ($data < $min) {
            return [false, 'A data deve ser pelo menos amanhã.'];
        }
        if ($data > $max) {
            return [false, 'A data não pode ser superior a 3 meses.'];
        }
        if (in_array((int) date('w', $ts), [0, 6], true)) {
            return [false, 'Não trabalhamos aos fins de semana. Selecione um dia útil.'];
        }

        return [true, ''];
    }
}

if (!function_exists('am_horarios_ocupados')) {
    /**
     * Horas já reservadas num dia (marcações ativas).
     *
     * @return list<string>
     */
    function am_horarios_ocupados(mysqli $con, string $data, int $excluir_servico_id = 0): array
    {
        [$dataOk] = am_horarios_validar_data_marcacao($data);
        if (!$dataOk) {
            return [];
        }

        $sql = "
            SELECT hora_desejada
            FROM servico
            WHERE data_desejada IS NOT NULL
              AND DATE(data_desejada) = ?
              AND hora_desejada IS NOT NULL
              AND TRIM(hora_desejada) <> ''
              AND LOWER(COALESCE(estado_marcacao, 'ativa')) <> 'cancelada'
        ";
        if ($excluir_servico_id > 0) {
            $sql .= ' AND id <> ?';
        }

        $st = $con->prepare($sql);
        if (!$st) {
            return [];
        }
        if ($excluir_servico_id > 0) {
            $st->bind_param('si', $data, $excluir_servico_id);
        } else {
            $st->bind_param('s', $data);
        }
        $ocupados = [];
        if ($st->execute()) {
            $res = $st->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $h = am_horarios_normalizar_hora($row['hora_desejada'] ?? '');
                if ($h !== '') {
                    $ocupados[$h] = true;
                }
            }
        }
        $st->close();

        return array_keys($ocupados);
    }
}

if (!function_exists('am_horarios_disponiveis')) {
    /**
     * @return list<string>
     */
    function am_horarios_disponiveis(mysqli $con, string $data, int $excluir_servico_id = 0): array
    {
        $ocupados = array_fill_keys(am_horarios_ocupados($con, $data, $excluir_servico_id), true);
        $livres = [];
        foreach (am_horarios_lista_base() as $slot) {
            if (!isset($ocupados[$slot])) {
                $livres[] = $slot;
            }
        }

        return $livres;
    }
}

if (!function_exists('am_horarios_resumo_dia')) {
    /**
     * @return array{
     *   data:string,
     *   slots:list<array{hora:string,disponivel:bool}>,
     *   disponiveis:list<string>,
     *   ocupados:list<string>,
     *   total_disponiveis:int,
     *   total_ocupados:int
     * }
     */
    function am_horarios_resumo_dia(mysqli $con, string $data, int $excluir_servico_id = 0): array
    {
        $data = trim($data);
        $ocupados = am_horarios_ocupados($con, $data, $excluir_servico_id);
        $mapOcup = array_fill_keys($ocupados, true);
        $slots = [];
        $disponiveis = [];
        foreach (am_horarios_lista_base() as $hora) {
            $livre = !isset($mapOcup[$hora]);
            $slots[] = ['hora' => $hora, 'disponivel' => $livre];
            if ($livre) {
                $disponiveis[] = $hora;
            }
        }

        return [
            'data' => $data,
            'slots' => $slots,
            'disponiveis' => $disponiveis,
            'ocupados' => $ocupados,
            'total_disponiveis' => count($disponiveis),
            'total_ocupados' => count($ocupados),
        ];
    }
}

if (!function_exists('am_horario_slot_disponivel')) {
    /**
     * @return array{0:bool,1:string}
     */
    function am_horario_slot_disponivel(mysqli $con, string $data, string $hora, int $excluir_servico_id = 0): array
    {
        [$dataOk, $msgData] = am_horarios_validar_data_marcacao($data);
        if (!$dataOk) {
            return [false, $msgData];
        }

        $hora = am_horarios_normalizar_hora($hora);
        if ($hora === '') {
            return [false, 'Selecione um horário válido.'];
        }

        $base = am_horarios_lista_base();
        if (!in_array($hora, $base, true)) {
            return [false, 'Horário fora do expediente da oficina (' . am_horarios_oficina_inicio() . ' – ' . am_horarios_oficina_fim() . ').'];
        }

        $ocupados = am_horarios_ocupados($con, $data, $excluir_servico_id);
        if (in_array($hora, $ocupados, true)) {
            $livres = am_horarios_disponiveis($con, $data, $excluir_servico_id);
            $sugestao = $livres !== [] ? ' Horários livres: ' . implode(', ', array_slice($livres, 0, 6)) . ($livres !== [] && count($livres) > 6 ? '…' : '') : ' Não há mais horários livres neste dia.';

            return [false, 'Este horário já está ocupado.' . $sugestao];
        }

        return [true, ''];
    }
}
