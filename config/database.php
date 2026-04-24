<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        /*
        |----------------------------------------------------------------------
        | MySQL — Base interne : users, submissions, staging_corrections,
        |          reviews, audit_logs, notifications, sessions, jobs
        |----------------------------------------------------------------------
        */
        'mysql' => [
            'driver'    => 'mysql',
            'url'       => env('DATABASE_URL'),
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'data_correction'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ],

        /*
        |----------------------------------------------------------------------
        | DB2 — Base cible : destination des corrections validées uniquement.
        |       Connexion via ODBC. Aucune lecture n'est effectuée depuis DB2.
        |----------------------------------------------------------------------
        */
        'db2' => [
            'driver'        => 'odbc',
            'dsn'           => 'DRIVER={IBM DB2 ODBC DRIVER};'
                . 'DATABASE=' . env('DB2_DATABASE') . ';'
                . 'HOSTNAME=' . env('DB2_HOST') . ';'
                . 'PORT='     . env('DB2_PORT', 50000) . ';'
                . 'PROTOCOL=TCPIP;'
                . 'UID='      . env('DB2_USERNAME') . ';'
                . 'PWD='      . env('DB2_PASSWORD') . ';',
            'username'      => env('DB2_USERNAME'),
            'password'      => env('DB2_PASSWORD'),
            'schema'        => env('DB2_SCHEMA', ''),
            'prefix'        => '',
            'database'      => env('DB2_DATABASE'),
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client'  => env('REDIS_CLIENT', 'phpredis'),
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
