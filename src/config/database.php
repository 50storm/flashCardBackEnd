<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule();

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? 'db',
    'database'  => $_ENV['DB_DATABASE'] ?? 'flashcard_db',
    'username'  => $_ENV['DB_USERNAME'] ?? 'admin_user',
    'password'  => $_ENV['DB_PASSWORD'] ?? 'admin_pass',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();
