<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
richiedi_login();

// ------------------------------------------------------------
// Gestione azioni POST
// ------------------------------------------------------------
$messaggio = '';
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifica_csrf();
    $azione = $_POST['azione'] ?? '';

    try {
        switch ($azione) {

            // ---------- ANAGRAFICA ----------
            case 'salva_anagrafica':
                $id = (int) ($_POST['id'] ?? 0);
                $nome = trim($_POST['nome'] ?? '');
                $cognome = trim($_POST['cognome'] ?? '');
                $cf = strtoupper(trim($_POST['codice_fiscale'] ?? ''));
                $dataAssunzione = $_POST['data_assunzione'] ?? '';
                $dataNascita = $_POST['data_nascita'] ?: null;
                $luogoNascita = trim($_POST['luogo_nascita'] ?? '');
                $indirizzo = trim($_POST['indirizzo'] ?? '');

                if ($nome === '' || $cognome === '' || $cf === '' || $dataAssunzione === '') {
                    throw new Exception('Nome, cognome, codice fiscale e data assunzione sono obbligatori.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE collaboratori SET nome=:n, cognome=:c, codice_fiscale=:cf,
                        data_assunzione=:da, data_nascita=:dn, luogo_nascita=:ln, indirizzo=:ind
                        WHERE id=:id');
                    $stmt->execute([':n'=>$nome, ':c'=>$cognome, ':cf'=>$cf, ':da'=>$dataAssunzione,
                        ':dn'=>$dataNascita, ':ln'=>$luogoNascita, ':ind'=>$indirizzo, ':id'=>$id]);
                    $messaggio = 'Anagrafica aggiornata.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO collaboratori (nome, cognome, codice_fiscale,
                        data_assunzione, data_nascita, luogo_nascita, indirizzo)
                        VALUES (:n, :c, :cf, :da, :dn, :ln, :ind)');
                    $stmt->execute([':n'=>$nome, ':c'=>$cognome, ':cf'=>$cf, ':da'=>$dataAssunzione,
                        ':dn'=>$dataNascita, ':ln'=>$luogoNascita, ':ind'=>$indirizzo]);
                    $messaggio = 'Collaboratrice creata.';
                }
                break;

            // ---------- RETRIBUZIONE ORDINARIA ----------
            case 'salva_retribuzione_ordinaria':
                $cid = (int) ($_POST['collaboratore_id'] ?? 0);
                $importo = (float) ($_POST['importo_ora'] ?? 0);
                $inizio = $_POST['data_inizio_validita'] ?? '';
                $fine = $_POST['data_fine_validita'] ?: null;
                if ($cid <= 0 || $importo <= 0 || $inizio === '') {
                    throw new Exception('Compila tutti i campi obbligatori.');
                }
                $stmt = $pdo->prepare('INSERT INTO retribuzioni_ordinarie
                    (collaboratore_id, importo_ora, data_inizio_validita, data_fine_validita)
                    VALUES (:cid, :imp, :ini, :fin)');
                $stmt->execute([':cid'=>$cid, ':imp'=>$importo, ':ini'=>$inizio, ':fin'=>$fine]);
                $messaggio = 'Retribuzione ordinaria salvata.';
                break;

            case 'elimina_retribuzione_ordinaria':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM retribuzioni_ordinarie WHERE id=:id')->execute([':id'=>$id]);
                $messaggio = 'Retribuzione ordinaria eliminata.';
                break;

            // ---------- RETRIBUZIONE FESTIVITÀ ----------
            case 'salva_retribuzione_festivita':
                $cid = (int) ($_POST['collaboratore_id'] ?? 0);
                $importo = (float) ($_POST['importo_ora'] ?? 0);
                $inizio = $_POST['data_inizio_validita'] ?? '';
                $fine = $_POST['data_fine_validita'] ?: null;
                if ($cid <= 0 || $importo <= 0 || $inizio === '') {
                    throw new Exception('Compila tutti i campi obbligatori.');
                }
                $stmt = $pdo->prepare('INSERT INTO retribuzioni_festivita
                    (collaboratore_id, importo_ora, data_inizio_validita, data_fine_validita)
                    VALUES (:cid, :imp, :ini, :fin)');
                $stmt->execute([':cid'=>$cid, ':imp'=>$importo, ':ini'=>$inizio, ':fin'=>$fine]);
                $messaggio = 'Retribuzione festività salvata.';
                break;

            case 'elimina_retribuzione_festivita':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM retribuzioni_festivita WHERE id=:id')->execute([':id'=>$id]);
                $messaggio = 'Retribuzione festività eliminata.';
                break;

            // ---------- CONTRIBUTI ----------
            case 'salva_contributo':
                $cid = (int) ($_POST['collaboratore_id'] ?? 0);
                $tipo = $_POST['tipo'] ?? '';
                $importo = (float) ($_POST['importo_ora'] ?? 0);
                $inizio = $_POST['data_inizio_validita'] ?? '';
                $fine = $_POST['data_fine_validita'] ?: null;
                if ($cid <= 0 || !in_array($tipo, ['DATORE','LAVORATORE']) || $importo <= 0 || $inizio === '') {
                    throw new Exception('Compila tutti i campi obbligatori.');
                }
                $stmt = $pdo->prepare('INSERT INTO contributi
                    (collaboratore_id, tipo, importo_ora, data_inizio_validita, data_fine_validita)
                    VALUES (:cid, :tipo, :imp, :ini, :fin)');
                $stmt->execute([':cid'=>$cid, ':tipo'=>$tipo, ':imp'=>$importo, ':ini'=>$inizio, ':fin'=>$fine]);
                $messaggio = 'Contributo salvato.';
                break;

            case 'elimina_contributo':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM contributi WHERE id=:id')->execute([':id'=>$id]);
                $messaggio = 'Contributo eliminato.';
                break;

            // ---------- MONTE FERIE ----------
            case 'salva_monte_ferie':
                $cid = (int) ($_POST['collaboratore_id'] ?? 0);
                $disponibili = (float) ($_POST['ore_disponibili'] ?? 0);
                $riporto = (float) ($_POST['ore_riporto'] ?? 0);
                $inizio = $_POST['data_inizio_validita'] ?? '';
                $fine = $_POST['data_fine_validita'] ?? '';
                if ($cid <= 0 || $inizio === '' || $fine === '') {
                    throw new Exception('Compila tutti i campi obbligatori.');
                }
                $residue = $disponibili + $riporto;
                $stmt = $pdo->prepare('INSERT INTO monte_ferie
                    (collaboratore_id, ore_disponibili, ore_riporto, ore_residue,
                     data_inizio_validita, data_fine_validita)
                    VALUES (:cid, :disp, :rip, :res, :ini, :fin)');
                $stmt->execute([':cid'=>$cid, ':disp'=>$disponibili, ':rip'=>$riporto,
                    ':res'=>$residue, ':ini'=>$inizio, ':fin'=>$fine]);
                $messaggio = 'Monte ferie salvato.';
                break;

            case 'elimina_monte_ferie':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM monte_ferie WHERE id=:id')->execute([':id'=>$id]);
                $messaggio = 'Monte ferie eliminato.';
                break;

            // ---------- PERIODI CONTRIBUTIVI ----------
            case 'salva_periodo':
                $cid = (int) ($_POST['collaboratore_id'] ?? 0);
                $trimestre = (int) ($_POST['trimestre'] ?? 0);
                $anno = (int) ($_POST['anno'] ?? 0);
                $inizio = $_POST['data_inizio'] ?? '';
                $fine = $_POST['data_fine'] ?? '';
                if ($cid <= 0 || $trimestre < 1 || $trimestre > 4 || $anno < 2000 || $inizio === '' || $fine === '') {
                    throw new Exception('Compila tutti i campi obbligatori.');
                }
                $stmt = $pdo->prepare('INSERT INTO periodi_contributivi
                    (collaboratore_id, trimestre, anno, data_inizio, data_fine)
                    VALUES (:cid, :tri, :anno, :ini, :fin)
                    ON DUPLICATE KEY UPDATE data_inizio=VALUES(data_inizio), data_fine=VALUES(data_fine)');
                $stmt->execute([':cid'=>$cid, ':tri'=>$trimestre, ':anno'=>$anno, ':ini'=>$inizio, ':fin'=>$fine]);
                $messaggio = 'Periodo contributivo salvato.';
                break;

            case 'elimina_periodo':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM periodi_contributivi WHERE id=:id')->execute([':id'=>$id]);
                $messaggio = 'Periodo eliminato.';
                break;
        }
    } catch (Exception $e) {
        $errore = $e->getMessage();
    }
}

// ------------------------------------------------------------
// Caricamento dati
// ------------------------------------------------------------
$collaboratori = $pdo->query('SELECT * FROM collaboratori ORDER BY cognome, nome')->fetchAll();
$collaboratoreId = (int) ($_GET['id'] ?? ($collaboratori[0]['id'] ?? 0));
$collaboratore = null;
foreach ($collaboratori as $c) {
    if ((int)$c['id'] === $collaboratoreId) { $collaboratore = $c; break; }
}

$retribuzioniOrd = [];
$retribuzioniFest = [];
$contributi = [];
$monteFerie = [];
$periodi = [];

if ($collaboratore) {
    $stmt = $pdo->prepare('SELECT * FROM retribuzioni_ordinarie WHERE collaboratore_id=:cid ORDER BY data_inizio_validita DESC');
    $stmt->execute([':cid'=>$collaboratoreId]);
    $retribuzioniOrd = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM retribuzioni_festivita WHERE collaboratore_id=:cid ORDER BY data_inizio_validita DESC');
    $stmt->execute([':cid'=>$collaboratoreId]);
    $retribuzioniFest = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM contributi WHERE collaboratore_id=:cid ORDER BY tipo, data_inizio_validita DESC');
    $stmt->execute([':cid'=>$collaboratoreId]);
    $contributi = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM monte_ferie WHERE collaboratore_id=:cid ORDER BY data_inizio_validita DESC');
    $stmt->execute([':cid'=>$collaboratoreId]);
    $monteFerie = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM periodi_contributivi WHERE collaboratore_id=:cid ORDER BY anno DESC, trimestre DESC');
    $stmt->execute([':cid'=>$collaboratoreId]);
    $periodi = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/layout_header.php';
?>

<h1 class="h3 mb-4">Collaboratrice</h1>

<?php if ($messaggio): ?>
    <div class="alert alert-success"><?= htmlspecialchars($messaggio) ?></div>
<?php endif; ?>
<?php if ($errore): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<?php if (count($collaboratori) > 1): ?>
    <form method="get" class="mb-4">
        <label class="form-label">Seleziona collaboratrice</label>
        <select name="id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($collaboratori as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $collaboratoreId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['cognome'] . ' ' . $c['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
<?php endif; ?>

<!-- ============================================================ -->
<!-- SEZIONE ANAGRAFICA                                            -->
<!-- ============================================================ -->
<div class="card mb-4">
    <div class="card-header"><strong>Anagrafica</strong></div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="salva_anagrafica">
            <input type="hidden" name="id" value="<?= $collaboratore['id'] ?? 0 ?>">

            <div class="col-md-4">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required
                       value="<?= htmlspecialchars($collaboratore['nome'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cognome *</label>
                <input type="text" name="cognome" class="form-control" required
                       value="<?= htmlspecialchars($collaboratore['cognome'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Codice fiscale *</label>
                <input type="text" name="codice_fiscale" class="form-control" maxlength="16" required
                       value="<?= htmlspecialchars($collaboratore['codice_fiscale'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Data assunzione *</label>
                <input type="date" name="data_assunzione" class="form-control" required
                       value="<?= htmlspecialchars($collaboratore['data_assunzione'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Data nascita</label>
                <input type="date" name="data_nascita" class="form-control"
                       value="<?= htmlspecialchars($collaboratore['data_nascita'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Luogo nascita</label>
                <input type="text" name="luogo_nascita" class="form-control"
                       value="<?= htmlspecialchars($collaboratore['luogo_nascita'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Indirizzo</label>
                <input type="text" name="indirizzo" class="form-control"
                       value="<?= htmlspecialchars($collaboratore['indirizzo'] ?? '') ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Salva anagrafica</button>
            </div>
        </form>
    </div>
</div>

<?php if ($collaboratore): ?>

<!-- ============================================================ -->
<!-- SEZIONE RETRIBUZIONE ORDINARIA                                -->
<!-- ============================================================ -->
<div class="card mb-4">
    <div class="card-header"><strong>Retribuzione oraria ordinaria</strong></div>
    <div class="card-body">
        <form method="post" class="row g-3 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="salva_retribuzione_ordinaria">
            <input type="hidden" name="collaboratore_id" value="<?= $collaboratoreId ?>">
            <div class="col-md-3">
                <label class="form-label">Importo €/ora *</label>
                <input type="number" step="0.01" name="importo_ora" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Inizio validità *</label>
                <input type="date" name="data_inizio_validita" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fine validità</label>
                <input type="date" name="data_fine_validita" class="form-control">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary">Aggiungi</button>
            </div>
        </form>
        <?php if ($retribuzioniOrd): ?>
            <table class="table table-sm">
                <thead><tr><th>Importo</th><th>Inizio</th><th>Fine</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($retribuzioniOrd as $r): ?>
                    <tr>
                        <td>€ <?= number_format($r['importo_ora'], 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($r['data_inizio_validita']) ?></td>
                        <td><?= $r['data_fine_validita'] ? htmlspecialchars($r['data_fine_validita']) : '—' ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Eliminare?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="azione" value="elimina_retribuzione_ordinaria">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted mb-0">Nessuna retribuzione ordinaria inserita.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SEZIONE RETRIBUZIONE FESTIVITÀ                                -->
<!-- ============================================================ -->
<div class="card mb-4">
    <div class="card-header"><strong>Retribuzione oraria festività retribuita</strong></div>
    <div class="card-body">
        <form method="post" class="row g-3 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="salva_retribuzione_festivita">
            <input type="hidden" name="collaboratore_id" value="<?= $collaboratoreId ?>">
            <div class="col-md-3">
                <label class="form-label">Importo €/ora *</label>
                <input type="number" step="0.01" name="importo_ora" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Inizio validità *</label>
                <input type="date" name="data_inizio_validita" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fine validità</label>
                <input type="date" name="data_fine_validita" class="form-control">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary">Aggiungi</button>
            </div>
        </form>
        <?php if ($retribuzioniFest): ?>
            <table class="table table-sm">
                <thead><tr><th>Importo</th><th>Inizio</th><th>Fine</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($retribuzioniFest as $r): ?>
                    <tr>
                        <td>€ <?= number_format($r['importo_ora'], 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($r['data_inizio_validita']) ?></td>
                        <td><?= $r['data_fine_validita'] ? htmlspecialchars($r['data_fine_validita']) : '—' ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Eliminare?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="azione" value="elimina_retribuzione_festivita">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted mb-0">Nessuna retribuzione festività inserita.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SEZIONE CONTRIBUTI                                            -->
<!-- ============================================================ -->
<div class="card mb-4">
    <div class="card-header"><strong>Contributi</strong></div>
    <div class="card-body">
        <form method="post" class="row g-3 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="salva_contributo">
            <input type="hidden" name="collaboratore_id" value="<?= $collaboratoreId ?>">
            <div class="col-md-2">
                <label class="form-label">Tipo *</label>
                <select name="tipo" class="form-select" required>
                    <option value="DATORE">Datore</option>
                    <option value="LAVORATORE">Lavoratore</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Importo €/ora *</label>
                <input type="number" step="0.01" name="importo_ora" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Inizio validità *</label>
                <input type="date" name="data_inizio_validita" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fine validità</label>
                <input type="date" name="data_fine_validita" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary">Aggiungi</button>
            </div>
        </form>
        <?php if ($contributi): ?>
            <table class="table table-sm">
                <thead><tr><th>Tipo</th><th>Importo</th><th>Inizio</th><th>Fine</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($contributi as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['tipo']) ?></td>
                        <td>€ <?= number_format($r['importo_ora'], 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($r['data_inizio_validita']) ?></td>
                        <td><?= $r['data_fine_validita'] ? htmlspecialchars($r['data_fine_validita']) : '—' ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Eliminare?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="azione" value="elimina_contributo">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted mb-0">Nessun contributo inserito.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SEZIONE MONTE FERIE                                           -->
<!-- ============================================================ -->
<div class="card mb-4">
    <div class="card-header"><strong>Monte ferie</strong></div>
    <div class="card-body">
        <form method="post" class="row g-3 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="salva_monte_ferie">
            <input type="hidden" name="collaboratore_id" value="<?= $collaboratoreId ?>">
            <div class="col-md-2">
                <label class="form-label">Ore disponibili *</label>
                <input type="number" step="0.01" name="ore_disponibili" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Ore riporto</label>
                <input type="number" step="0.01" name="ore_riporto" class="form-control" value="0">
                <small class="text-muted">Ferie avanzate dal periodo precedente (input manuale)</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Inizio validità *</label>
                <input type="date" name="data_inizio_validita" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fine validità *</label>
                <input type="date" name="data_fine_validita" class="form-control" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary">Aggiungi</button>
            </div>
        </form>
        <?php if ($monteFerie): ?>
            <table class="table table-sm">
                <thead><tr><th>Disponibili</th><th>Riporto</th><th>Residue</th><th>Inizio</th><th>Fine</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($monteFerie as $r): ?>
                    <tr>
                        <td><?= number_format($r['ore_disponibili'], 2, ',', '.') ?> h</td>
                        <td><?= number_format($r['ore_riporto'], 2, ',', '.') ?> h</td>
                        <td><strong><?= number_format($r['ore_residue'], 2, ',', '.') ?> h</strong></td>
                        <td><?= htmlspecialchars($r['data_inizio_validita']) ?></td>
                        <td><?= htmlspecialchars($r['data_fine_validita']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Eliminare?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="azione" value="elimina_monte_ferie">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted mb-0">Nessun monte ferie inserito.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SEZIONE PERIODI CONTRIBUTIVI                                  -->
<!-- ============================================================ -->
<div class="card mb-4">
    <div class="card-header"><strong>Periodi contributivi (trimestri)</strong></div>
    <div class="card-body">
        <form method="post" class="row g-3 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="salva_periodo">
            <input type="hidden" name="collaboratore_id" value="<?= $collaboratoreId ?>">
            <div class="col-md-1">
                <label class="form-label">Trim. *</label>
                <select name="trimestre" class="form-select" required>
                    <option value="1">1°</option>
                    <option value="2">2°</option>
                    <option value="3">3°</option>
                    <option value="4">4°</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Anno *</label>
                <input type="number" name="anno" class="form-control" min="2000" max="2100"
                       value="<?= date('Y') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Data inizio *</label>
                <input type="date" name="data_inizio" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Data fine *</label>
                <input type="date" name="data_fine" class="form-control" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary">Aggiungi / aggiorna</button>
            </div>
        </form>
        <?php if ($periodi): ?>
            <table class="table table-sm">
                <thead><tr><th>Trimestre</th><th>Anno</th><th>Inizio</th><th>Fine</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($periodi as $r): ?>
                    <tr>
                        <td><?= (int)$r['trimestre'] ?>°</td>
                        <td><?= (int)$r['anno'] ?></td>
                        <td><?= htmlspecialchars($r['data_inizio']) ?></td>
                        <td><?= htmlspecialchars($r['data_fine']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Eliminare?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="azione" value="elimina_periodo">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted mb-0">Nessun periodo contributivo inserito.</p>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>