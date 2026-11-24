<?php
// config/database.php — NON committare questo file

$host     = '127.0.0.1';
$port     = 3306;
$database = 'wilmgest_db';
$username = 'root';
$password = '';

$dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('Errore di connessione al database: ' . htmlspecialchars($e->getMessage()));
}
