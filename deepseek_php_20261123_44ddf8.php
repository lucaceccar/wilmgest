<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout_header.php';
?>
<h1 class="h3 mb-4">Dashboard</h1>
<div class="row g-3">
    <div class="col-md-3">
        <a href="collaboratore.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <h5 class="card-title">Collaboratrice</h5>
                <p class="card-text text-muted">Anagrafica e configurazioni</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="ore.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <h5 class="card-title">Registrazione ore</h5>
                <p class="card-text text-muted">Inserisci le ore per data e tipologia</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="busta_paga.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <h5 class="card-title">Busta paga</h5>
                <p class="card-text text-muted">Calcola ed esporta in PDF</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="report_contributi.php" class="card text-decoration-none h-100">
            <div class="card-body">
                <h5 class="card-title">Contributi</h5>
                <p class="card-text text-muted">Report trimestrale</p>
            </div>
        </a>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>