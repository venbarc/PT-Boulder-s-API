<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "Connected OK (no password)\n";
    $pdo->exec('CREATE DATABASE IF NOT EXISTS pt_boulder');
    echo "Database pt_boulder created/verified\n";
} catch (Exception $e) {
    echo 'Failed with no password: '.$e->getMessage()."\n";

    // Try common default passwords
    foreach (['root', 'password', 'mysql'] as $pass) {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', $pass);
            echo "Connected OK with password: {$pass}\n";
            $pdo->exec('CREATE DATABASE IF NOT EXISTS pt_boulder');
            echo "Database pt_boulder created/verified\n";
            break;
        } catch (Exception $e2) {
            echo "Failed with '{$pass}': ".$e2->getMessage()."\n";
        }
    }
}
