<?php
require_once __DIR__ . '/auth.php';
richiedi_login();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verwaltung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">Gestionale Domestica</a>
        <div class="navbar-nav me-auto">
            <a class="nav-link" href="collaboratore.php">Collaboratrice</a>
            <a class="nav-link" href="ore.php">Ore</a>
            <a class="nav-link" href="busta_paga.php">Busta Paga</a>
            <a class="nav-link" href="report_contributi.php">Contributi</a>
        </div>
        <span class="navbar-text me-3"><?= htmlspecialchars(utente_username() ?? '') ?></span>
        <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
    </div>
</nav>
<main class="container">
