<?php
// Gestione sessione e controllo accesso.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica che l'utente sia loggato. Se non lo è, redirige al login.
 */
function richiedi_login(): void
{
    if (empty($_SESSION['utente_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Restituisce l'ID dell'utente loggato (o null).
 */
function utente_id(): ?int
{
    return isset($_SESSION['utente_id']) ? (int) $_SESSION['utente_id'] : null;
}

/**
 * Restituisce lo username dell'utente loggato (o null).
 */
function utente_username(): ?string
{
    return $_SESSION['utente_username'] ?? null;
}

/**
 * Genera un token CSRF e lo salva in sessione.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica il token CSRF inviato in POST.
 */
function verifica_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF non valido.');
    }
}