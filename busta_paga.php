<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funzioni_calcolo.php';
require_once __DIR__ . '/includes/layout_header.php';

$collaboratoreId = 1;
$stmt = $pdo->prepare('SELECT * FROM collaboratori WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $collaboratoreId]);
$collaboratore = $stmt->fetch();

if (!$collaboratore) {
    echo '<div class="alert alert-warning">Nessuna collaboratrice trovata. Vai prima su <a href="collaboratore.php">Collaboratrice</a>.</div>';
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

$messaggio = '';
$tipoMessaggio = 'success';

// ------------------------------------------------------------
// Fase 1: periodo selezionato
// ------------------------------------------------------------
$dataInizio = $_GET['data_inizio'] ?? $_POST['data_inizio'] ?? date('Y-m-01');
$dataFine = $_GET['data_fine'] ?? $_POST['data_fine'] ?? date('Y-m-t');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInizio)) $dataInizio = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFine)) $dataFine = date('Y-m-t');

// ------------------------------------------------------------
// Fase 2: anteprima (solo se l'utente ha cliccato "Anteprima")
// ------------------------------------------------------------
$anteprima = null;
if (isset($_GET['anteprima']) && $_GET['anteprima'] === '1') {
    $anteprima = calcola_busta_paga($pdo, $collaboratoreId, $dataInizio, $dataFine);
}

// ------------------------------------------------------------
// Fase 3: generazione busta paga
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'genera') {
    verifica_csrf();

    $righeSelezionate = $_POST['righe'] ?? [];
    if (!is_array($righeSelezionate) || count($righeSelezionate) === 0) {
        $messaggio = 'Seleziona almeno una riga di ore da includere nella busta paga.';
        $tipoMessaggio = 'danger';
    } else {
        // Righe manuali (descrizione + importo)
        $manualiDescrizione = $_POST['manuale_descrizione'] ?? [];
        $manualiImporto = $_POST['manuale_importo'] ?? [];
        $manuali = [];
        if (is_array($manualiDescrizione) && is_array($manualiImporto)) {
            foreach ($manualiDescrizione as $i => $desc) {
                $desc = trim($desc);
                $imp = (float) str_replace(',', '.', $manualiImporto[$i] ?? '0');
                if ($desc !== '' && $imp != 0.0) {
                    $manuali[] = ['descrizione' => $desc, 'importo' => $imp];
                }
            }
        }

        // Ricalcola con tutte le righe del periodo
        $calcolo = calcola_busta_paga($pdo, $collaboratoreId, $dataInizio, $dataFine);

        // Filtra solo le righe selezionate
        $dettaglioSelezionato = [];
        $idSelezionati = array_map('intval', $righeSelezionate);
        foreach ($calcolo['dettaglio'] as $riga) {
            if (in_array($riga['registrazione_ore_id'], $idSelezionati, true)) {
                $dettaglioSelezionato[] = $riga;
            }
        }

        // Ricalcola i totali solo sulle righe selezionate
        $totOre = ['LAVORATE' => 0.0, 'FERIE' => 0.0, 'FESTIVITA' => 0.0, 'PERMESSO_NR' => 0.0];
        $totImporti = ['LAVORATE' => 0.0, 'FERIE' => 0.0, 'FESTIVITA' => 0.0];
        $totContribLav = 0.0;
        $totContribDat = 0.0;

        foreach ($dettaglioSelezionato as $r) {
            $cod = $r['tipo_codice'];
            $totOre[$cod] = ($totOre[$cod] ?? 0) + $r['ore'];
            if ($cod !== 'PERMESSO_NR') {
                $totImporti[$cod] += $r['importo_totale'];
                $totContribLav += $r['ore'] * $r['contributo_lavoratore_ora'];
                $totContribDat += $r['ore'] * $r['contributo_datore_ora'];
            }
        }

        $aOre = $totImporti['LAVORATE'] + $totImporti['FERIE'] + $totImporti['FESTIVITA'];
        $aManuali = array_sum(array_column($manuali, 'importo'));
        $a = $aOre + $aManuali;
        $b = $totContribLav;
        $c = $a - $b;

        // Numero busta paga progressivo per anno
        $anno = date('Y', strtotime($dataFine));
        $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM buste_paga WHERE YEAR(data_emissione) = :anno");
        $stmt->execute([':anno' => $anno]);
        $progressivo = ((int) $stmt->fetch()['n']) + 1;
        $numero = $anno . '/' . str_pad((string) $progressivo, 3, '0', STR_PAD_LEFT);

        // Transazione
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO buste_paga
                (collaboratore_id, numero, data_emissione, data_inizio_periodo, data_fine_periodo,
                 totale_ore_lavorate, totale_ore_ferie, totale_ore_festivita, totale_ore_permesso,
                 importo_lavorate, importo_ferie, importo_festivita, importo_righe_manuali,
                 totale_lordo_a, contributi_lavoratore_b, contributi_datore, totale_netto_c)
                VALUES (:cid, :num, :emissione, :dinizio, :dfine,
                        :ore_lav, :ore_fer, :ore_fest, :ore_perm,
                        :imp_lav, :imp_fer, :imp_fest, :imp_man,
                        :a, :b, :dat, :c)');
            $stmt->execute([
                ':cid' => $collaboratoreId,
                ':num' => $numero,
                ':emissione' => date('Y-m-d'),
                ':dinizio' => $dataInizio,
                ':dfine' => $dataFine,
                ':ore_lav' => $totOre['LAVORATE'],
                ':ore_fer' => $totOre['FERIE'],
                ':ore_fest' => $totOre['FESTIVITA'],
                ':ore_perm' => $totOre['PERMESSO_NR'],
                ':imp_lav' => $totImporti['LAVORATE'],
                ':imp_fer' => $totImporti['FERIE'],
                ':imp_fest' => $totImporti['FESTIVITA'],
                ':imp_man' => $aManuali,
                ':a' => $a,
                ':b' => $b,
                ':dat' => $totContribDat,
                ':c' => $c,
            ]);
            $bustaPagaId = (int) $pdo->lastInsertId();

            // Righe dettaglio
            $stmtRiga = $pdo->prepare('INSERT INTO busta_paga_righe
                (busta_paga_id, registrazione_ore_id, data, tipologia_ore_id, ore,
                 importo_ora, importo_totale, contributo_lavoratore_ora, contributo_datore_ora)
                VALUES (:bp, :reg, :data, :tipo, :ore, :imp_ora, :imp_tot, :cl, :cd)');
            foreach ($dettaglioSelezionato as $r) {
                $stmtRiga->execute([
                    ':bp' => $bustaPagaId,
                    ':reg' => $r['registrazione_ore_id'],
                    ':data' => $r['data'],
                    ':tipo' => $r['tipologia_ore_id'],
                    ':ore' => $r['ore'],
                    ':imp_ora' => $r['importo_ora'],
                    ':imp_tot' => $r['importo_totale'],
                    ':cl' => $r['contributo_lavoratore_ora'],
                    ':cd' => $r['contributo_datore_ora'],
                ]);
            }

            // Righe manuali
            if ($manuali) {
                $stmtMan = $pdo->prepare('INSERT INTO busta_paga_righe_manuali
                    (busta_paga_id, descrizione, importo) VALUES (:bp, :desc, :imp)');
                foreach ($manuali as $m) {
                    $stmtMan->execute([':bp' => $bustaPagaId, ':desc' => $m['descrizione'], ':imp' => $m['importo']]);
                }
            }

            // Aggiorna monte ferie se ci sono ore di ferie
            $oreFerie = $totOre['FERIE'];
            if ($oreFerie > 0) {
                $monte = get_monte_ferie_valido($pdo, $collaboratoreId, $dataFine);
                if ($monte) {
                    $nuovoResiduo = max(0, (float) $monte['ore_residue'] - $oreFerie);
                    $stmt = $pdo->prepare('UPDATE monte_ferie SET ore_residue = :r WHERE id = :id');
                    $stmt->execute([':r' => $nuovoResiduo, ':id' => $monte['id']]);
                }
            }

            $pdo->commit();
            header('Location: busta_paga.php?generata=' . $bustaPagaId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $messaggio = 'Errore durante il salvataggio: ' . $e->getMessage();
            $tipoMessaggio = 'danger';
        }
    }
}

// ------------------------------------------------------------
// Busta paga appena generata
// ------------------------------------------------------------
$bustaGenerata = null;
if (isset($_GET['generata'])) {
    $id = (int) $_GET['generata'];
    $stmt = $pdo->prepare('SELECT * FROM buste_paga WHERE id = :id AND collaboratore_id = :cid');
    $stmt->execute([':id' => $id, ':cid' => $collaboratoreId]);
    $bustaGenerata = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Busta paga</h1>
    <span class="text-muted"><?= htmlspecialchars($collaboratore['nome'] . ' ' . $collaboratore['cognome']) ?></span>
</div>

<?php if ($messaggio): ?>
    <div class="alert alert-<?= htmlspecialchars($tipoMessaggio) ?>"><?= htmlspecialchars($messaggio) ?></div>
<?php endif; ?>

<?php if ($bustaGenerata): ?>
    <div class="alert alert-success">
        Busta paga <strong>n. <?= htmlspecialchars($bustaGenerata['numero']) ?></strong> generata correttamente.
        <a href="busta_paga_pdf.php?id=<?= (int) $bustaGenerata['id'] ?>" class="alert-link" target="_blank">Scarica PDF</a>
        (la generazione PDF sarà implementata nel prossimo passo).
    </div>
<?php endif; ?>

<!-- Fase 1: selezione periodo -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">Seleziona periodo</h5>
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="anteprima" value="1">
            <div class="col-md-4">
                <label class="form-label">Data inizio</label>
                <input type="date" name="data_inizio" class="form-control" value="<?= htmlspecialchars($dataInizio) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Data fine</label>
                <input type="date" name="data_fine" class="form-control" value="<?= htmlspecialchars($dataFine) ?>" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100">Anteprima</button>
            </div>
        </form>
    </div>
</div>

<?php if ($anteprima): ?>
    <!-- Fase 2: anteprima con selezione righe -->
    <?php if (!$anteprima['dettaglio']): ?>
        <div class="alert alert-warning">Nessuna ora registrata nel periodo selezionato.</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="genera">
            <input type="hidden" name="data_inizio" value="<?= htmlspecialchars($dataInizio) ?>">
            <input type="hidden" name="data_fine" value="<?= htmlspecialchars($dataFine) ?>">

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Righe ore nel periodo</h5>
                    <p class="text-muted small">Seleziona le righe da includere nella busta paga.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="seleziona-tutte" checked></th>
                                    <th>Data</th>
                                    <th>Tipologia</th>
                                    <th class="text-end">Ore</th>
                                    <th class="text-end">Importo/ora</th>
                                    <th class="text-end">Importo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anteprima['dettaglio'] as $r): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="righe[]" value="<?= (int) $r['registrazione_ore_id'] ?>" class="riga-check" checked>
                                        </td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['data']))) ?></td>
                                        <td><?= htmlspecialchars($r['tipo_descrizione']) ?></td>
                                        <td class="text-end"><?= number_format($r['ore'], 2, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($r['importo_ora'], 2, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($r['importo_totale'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Righe aggiuntive manuali</h5>
                    <p class="text-muted small">Aggiungi voci come bonus, rimborsi o trattenute (importo negativo per le trattenute).</p>
                    <div id="righe-manuali">
                        <div class="row g-2 mb-2 riga-manuale">
                            <div class="col-md-7">
                                <input type="text" name="manuale_descrizione[]" class="form-control" placeholder="Descrizione">
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="manuale_importo[]" class="form-control" step="0.01" placeholder="Importo">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger w-100 rimuovi-riga">Rimuovi</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="aggiungi-riga">+ Aggiungi riga</button>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Riepilogo anteprima</h5>
                    <table class="table table-sm">
                        <tr><td>Ore lavorate</td><td class="text-end"><?= number_format($anteprima['totali_ore']['lavorate'], 2, ',', '.') ?> h</td></tr>
                        <tr><td>Ore ferie</td><td class="text-end"><?= number_format($anteprima['totali_ore']['ferie'], 2, ',', '.') ?> h</td></tr>
                        <tr><td>Ore festività</td><td class="text-end"><?= number_format($anteprima['totali_ore']['festivita'], 2, ',', '.') ?> h</td></tr>
                        <tr><td>Permesso non retribuito</td><td class="text-end"><?= number_format($anteprima['totali_ore']['permesso'], 2, ',', '.') ?> h</td></tr>
                        <tr class="border-top"><td><strong>Totale lordo (A)</strong></td><td class="text-end"><strong>€ <?= number_format($anteprima['importi']['a_ore'], 2, ',', '.') ?></strong></td></tr>
                        <tr><td>Contributi lavoratore (B)</td><td class="text-end">€ <?= number_format($anteprima['importi']['b'], 2, ',', '.') ?></td></tr>
                        <tr class="border-top"><td><strong>Totale netto (C = A − B)</strong></td><td class="text-end"><strong>€ <?= number_format($anteprima['importi']['c'], 2, ',', '.') ?></strong></td></tr>
                        <tr><td class="text-muted small">Contributi datore (informativo)</td><td class="text-end text-muted small">€ <?= number_format($anteprima['importi']['contributi_datore'], 2, ',', '.') ?></td></tr>
                    </table>
                    <p class="text-muted small mb-0">I totali definitivi saranno ricalcolati sulle righe selezionate al momento della generazione.</p>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
                <button class="btn btn-success btn-lg">Genera busta paga</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>

<script>
document.getElementById('seleziona-tutte')?.addEventListener('change', function () {
    document.querySelectorAll('.riga-check').forEach(c => c.checked = this.checked);
});

document.getElementById('aggiungi-riga')?.addEventListener('click', function () {
    const container = document.getElementById('righe-manuali');
    const riga = document.createElement('div');
    riga.className = 'row g-2 mb-2 riga-manuale';
    riga.innerHTML = `
        <div class="col-md-7"><input type="text" name="manuale_descrizione[]" class="form-control" placeholder="Descrizione"></div>
        <div class="col-md-3"><input type="number" name="manuale_importo[]" class="form-control" step="0.01" placeholder="Importo"></div>
        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 rimuovi-riga">Rimuovi</button></div>
    `;
    container.appendChild(riga);
});

document.getElementById('righe-manuali')?.addEventListener('click', function (e) {
    if (e.target.classList.contains('rimuovi-riga')) {
        e.target.closest('.riga-manuale').remove();
    }
});
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>