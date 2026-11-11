<?php

declare(strict_types=1);

/**
 * Configurazione dell'applicazione.
 *
 * Per passare a MySQL basta cambiare 'driver' in 'mysql' e compilare
 * i parametri di connessione qui sotto. Le tabelle vengono create
 * automaticamente al primo avvio.
 */
return [
    'app' => [
        'name' => 'SOS Manutentore',
    ],

    'db' => [
        // 'sqlite' (nessuna configurazione) oppure 'mysql'
        'driver' => 'sqlite',

        // Usato solo con driver = sqlite
        'sqlite_path' => __DIR__ . '/../storage/manutenzioni.sqlite',

        // Usati solo con driver = mysql
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'manutenzioni',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'manutenzioni_session',
        'lifetime' => 60 * 60 * 8, // 8 ore
    ],
];
