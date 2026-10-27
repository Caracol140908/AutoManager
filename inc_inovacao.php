<?php

function am_ensure_passaporte_tables(mysqli $con): void
{
    @$con->query("
        CREATE TABLE IF NOT EXISTS passaporte_eventos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            matricula VARCHAR(30) NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            descricao TEXT NOT NULL,
            odometro INT NULL,
            data_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            origem VARCHAR(40) NOT NULL DEFAULT 'sistema',
            hash_evento VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_passaporte (email, matricula, data_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    @$con->query("
        CREATE TABLE IF NOT EXISTS passaporte_links_partilha (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            matricula VARCHAR(30) NOT NULL,
            token VARCHAR(80) NOT NULL,
            expira_em DATETIME NOT NULL,
            revogado_em DATETIME NULL,
            views INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_token (token),
            KEY idx_partilha (email, matricula, expira_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function am_hash_evento_passaporte(string $email, string $matricula, string $tipo, string $descricao, string $data): string
{
    return hash('sha256', implode('|', [$email, $matricula, $tipo, $descricao, $data]));
}

