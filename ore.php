<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout_header.php';

// ------------------------------------------------------------
// Recupero collaboratrice (una sola, id = 1)
// ------------------------------------------------------------
$collaboratoreId = 1;
$stmt = $pdo->prepare('SELECT * FROM collaboratori WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $collaboratoreId]);
$collaboratore = $stmt->fetch();

if (!$collaboratore) {
    echo '<div class="alert alert-warning">Nessuna collaboratrice trovata. Vai prima su <a href="collaboratore.php">Collaboratrice</a> per crearla.</div>';
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

// ------------------------------------------------------------
// Tipologie ore
// ------------------------------------------------------------
$tipologie = $pdo->query('SELECT * FROM tipologie_ore ORDER BY id')->fetchAll();

// ------------------------------------------------------------
// Gestione POST: inserimento / eliminazione
// ------------------------------------------------------------
$messaggio = '';
$tipoMessaggio = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifica_csrf();
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'inserisci') {
        $data = $_POST['data'] ?? '';
        $tipologiaId = (int) ($_POST['tipologia_ore_id'] ?? 0);
        $ore = (float) str_replace(',', '.', $_POST['ore'] ?? '0');
        $note = trim($_POST['note'] ?? '');

        $errori = [];
        if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $errori[] = 'Data non valida.';
        }
        if ($tipologiaId <= 0) {
            $errori[] = 'Seleziona una tipologia.';
        }
        if ($ore <= 0) {
            $errori[] = 'Le ore devono essere maggiori di zero.';
        }
        if ($ore > 24) {
            $errori[] = 'Le ore non possono superare 24 in un giorno.';
        }

        if ($errori) {
            $messaggio = implode(' ', $errori);
            $tipoMessaggio = 'danger';
        } else {
            $sql = 'INSERT INTO registrazioni_ore (collaboratore_id, data, tipologia_ore_id, ore, note)
                    VALUES (:cid, :data, :tipo, :ore, :note)
                    ON DUPLICATE KEY UPDATE ore = VALUES(ore), note = VALUES(note)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cid' => $collaboratoreId,
                ':data' => $data,
                ':tipo' => $tipologiaId,
                ':ore' => $ore,
                ':note' => $note !== '' ? $note : null,
            ]);
            $messaggio = 'Ore registrate correttamente.';
        }
    } elseif ($azione === 'elimina') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM registrazioni_ore WHERE id = :id AND collaboratore_id = :cid');
            $stmt->execute([':id' => $id, ':cid' => $collaboratoreId]);
            $messaggio = 'Registrazione eliminata.';
        }
    }
}

// ------------------------------------------------------------
// Filtro periodo
// ------------------------------------------------------------
$dataInizio = $_GET['data_inizio'] ?? date('Y-m-01');
$dataFine = $_GET['data_fine'] ?? date('Y-m-t');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInizio)) $dataInizio = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFine)) $dataFine = date('Y-m-t');

$sql = 'SELECT ro.*, t.codice AS tipo_codice, t.descrizione AS tipo_descrizione
        FROM registrazioni_ore ro
        JOIN tipologie_ore t ON t.id = ro.tipologia_ore_id
        WHERE ro.collaboratore_id = :cid
          AND ro.data BETWEEN :dinizio AND :dfine
        ORDER BY ro.data DESC, t.id';
$stmt = $pdo->prepare($sql);
$stmt->execute([':cid' => $collaboratoreId, ':dinizio' => $dataInizio, ':dfine' => $dataFine]);
$registrazioni = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Registrazione ore</h1>
    <span class="text-muted">
        <?= htmlspecialchars($collaboratore['nome'] . ' ' . $collaboratore['cognome']) ?>
    </span>
</div>

<?php if ($messaggio): ?>
    <div class="alert alert-<?= htmlspecialchars($tipoMessaggio) ?>"><?= htmlspecialchars($messaggio) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form inserimento -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Inserisci ore</h5>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="azione" value="inserisci">

                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" class="form-control" required value="<?= htmlspecialchars(date('Y-m-d')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipologia</label>
                        <select name="tipologia_ore_id" class="form-select" required>
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($tipologie as $t): ?>
                                <option value="<?= (int) $t['id'] ?>">
                                    <?= htmlspecialchars($t['descrizione']) ?>
                                    <?= (int) $t['retribuita'] === 1 ? '' : ' (non retribuita)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ore</label>
                        <input type="number" name="ore" step="0.25" min="0.25" max="24" class="form-control" required>
                        <div class="form-text">Es. 4, 4.5, 2.25</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note (opzionale)</label>
                        <input type="text" name="note" class="form-control" maxlength="255">
                    </div>
                    <button class="btn btn-primary w-100">Registra</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabella ore registrate -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Ore registrate</h5>
                    <form method="get" class="d-flex gap-2 align-items-end">
                        <div>
                            <label class="form-label small mb-0">Dal</label>
                            <input type="date" name="data_inizio" class="form-control form-control-sm" value="<?= htmlspecialchars($dataInizio) ?>">
                        </div>
                        <div>
                            <label class="form-label small mb-0">Al</label>
                            <input type="date" name="data_fine" class="form-control form-control-sm" value="<?= htmlspecialchars($dataFine) ?>">
                        </div>
                        <button class="btn btn-sm btn-outline-secondary">Filtra</button>
                    </form>
                </div>

                <?php if (!$registrazioni): ?>
                    <p class="text-muted mb-0">Nessuna registrazione nel periodo selezionato.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipologia</th>
                                    <th class="text-end">Ore</th>
                                    <th>Note</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totale = 0.0;
                                foreach ($registrazioni as $r):
                                    $totale += (float) $r['ore'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['data']))) ?></td>
                                        <td><?= htmlspecialchars($r['tipo_descrizione']) ?></td>
                                        <td class="text-end"><?= number_format((float) $r['ore'], 2, ',', '.') ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($r['note'] ?? '') ?></td>
                                        <td class="text-end">
                                            <form method="post" onsubmit="return confirm('Eliminare questa registrazione?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <input type="hidden" name="azione" value="elimina">
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">Elimina</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Totale</th>
                                    <th class="text-end"><?= number_format($totale, 2, ',', '.') ?></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>