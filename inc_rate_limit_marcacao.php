<?php
/**
 * Limita criação de marcações/reparações: uma de 5 em 5 minutos por sessão e por email na BD.
 */
if (!function_exists('am_segundos_entre_marcacoes')) {
    function am_segundos_entre_marcacoes(): int
    {
        return 300;
    }
}

if (!function_exists('am_servico_coluna_data_insercao')) {
    /**
     * @return 'created_at'|'data_registo'|null
     */
    function am_servico_coluna_data_insercao(mysqli $con): ?string
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = null;
        foreach (['created_at', 'data_registo'] as $nome) {
            $chk = @$con->query("SHOW COLUMNS FROM servico LIKE '" . $con->real_escape_string($nome) . "'");
            if ($chk && $chk->num_rows > 0) {
                $cache = $nome;
                break;
            }
        }

        return $cache;
    }
}

if (!function_exists('am_rate_limit_marcacao_check')) {
    /**
     * @return array{0:bool,1:string} [ok, mensagem_erro]
     */
    function am_rate_limit_marcacao_check(mysqli $con, int $id_utilizador, string $email): array
    {
        $intervalo = am_segundos_entre_marcacoes();
        $agora = time();
        $email = trim($email);

        if (!empty($_SESSION['ultima_marcacao_ok_ts']) && ($agora - (int) $_SESSION['ultima_marcacao_ok_ts']) < $intervalo) {
            $faltam = $intervalo - ($agora - (int) $_SESSION['ultima_marcacao_ok_ts']);

            return [false, 'Só é permitido registar uma marcação de 5 em 5 minutos. Aguarda mais ' . max(1, (int) ceil($faltam / 60)) . ' minuto(s).'];
        }

        $colData = am_servico_coluna_data_insercao($con);
        $temIdUtil = false;
        $chkUid = @$con->query("SHOW COLUMNS FROM servico LIKE 'id_utilizador'");
        if ($chkUid && $chkUid->num_rows > 0) {
            $temIdUtil = true;
        }
        if (!$temIdUtil) {
            $id_utilizador = 0;
        }

        if ($colData !== null && ($email !== '' || $id_utilizador > 0)) {
            $sql = null;
            $types = '';
            if ($id_utilizador > 0 && $email !== '') {
                $sql = 'SELECT TIMESTAMPDIFF(SECOND, `' . $colData . '`, NOW()) AS sec_ago FROM servico WHERE (id_utilizador = ? OR email = ?) ORDER BY id DESC LIMIT 1';
                $types = 'is';
            } elseif ($id_utilizador > 0) {
                $sql = 'SELECT TIMESTAMPDIFF(SECOND, `' . $colData . '`, NOW()) AS sec_ago FROM servico WHERE id_utilizador = ? ORDER BY id DESC LIMIT 1';
                $types = 'i';
            } elseif ($email !== '') {
                $sql = 'SELECT TIMESTAMPDIFF(SECOND, `' . $colData . '`, NOW()) AS sec_ago FROM servico WHERE email = ? ORDER BY id DESC LIMIT 1';
                $types = 's';
            }
            if ($sql !== null) {
                $st = $con->prepare($sql);
                if ($st) {
                    if ($types === 'is') {
                        $st->bind_param('is', $id_utilizador, $email);
                    } elseif ($types === 'i') {
                        $st->bind_param('i', $id_utilizador);
                    } else {
                        $st->bind_param('s', $email);
                    }
                    $st->execute();
                    $res = $st->get_result();
                    if ($res && ($row = $res->fetch_assoc()) && $row['sec_ago'] !== null) {
                        $sec = (int) $row['sec_ago'];
                        if ($sec >= 0 && $sec < $intervalo) {
                            $st->close();

                            return [false, 'Já existe uma marcação recente. Só podes criar outra de 5 em 5 minutos. Tenta daqui a ' . max(1, (int) ceil(($intervalo - $sec) / 60)) . ' minuto(s).'];
                        }
                    }
                    $st->close();
                }
            }
        }

        return [true, ''];
    }
}

if (!function_exists('am_rate_limit_marcacao_registar')) {
    function am_rate_limit_marcacao_registar(): void
    {
        $_SESSION['ultima_marcacao_ok_ts'] = time();
    }
}
