<?php
require_once __DIR__ . '/config/database.php';

$username = 'admin';
$password = 'cambiami123'; // Cambia prima di eseguire!

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT INTO utenti (username, password_hash) VALUES (:u, :p)
                       ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
$stmt->execute([':u' => $username, ':p' => $hash]);

echo "Utente '$username' creato/aggiornato. Password: $password<br>";
echo "Ora cancella questo file (setup_admin.php).";