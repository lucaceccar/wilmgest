<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifica_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errore = 'Inserisci username e password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM utenti WHERE username = :u');
        $stmt->execute([':u' => $username]);
        $utente = $stmt->fetch();

        if ($utente && password_verify($password, $utente['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['utente_id'] = (int) $utente['id'];
            $_SESSION['utente_username'] = $utente['username'];
            header('Location: index.php');
            exit;
        }
        $errore = 'Credenziali non valide.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login – Gestionale Domestica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width: 420px; margin-top: 10vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3">Accesso</h4>
            <?php if ($errore): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errore) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Accedi</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>