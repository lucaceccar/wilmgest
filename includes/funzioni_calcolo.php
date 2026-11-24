<?php
// Funzioni di calcolo per busta paga, contributi e monte ferie.

/**
 * Restituisce la tariffa valida a una certa data da una tabella storica.
 * $tabella: 'retribuzioni_ordinarie' | 'retribuzioni_festivita' | 'contributi'
 * Per 'contributi' va passato $tipo = 'DATORE' | 'LAVORATORE'.
 */
function get_tariffa_valida(
    PDO $pdo,
    string $tabella,
    int $collaboratoreId,
    string $data,
    ?string $tipo = null
): float {
    $sql = "SELECT importo_ora FROM $tabella
            WHERE collaboratore_id = :cid
              AND data_inizio_validita <= :data
              AND (data_fine_validita IS NULL OR data_fine_validita >= :data)";
    $params = [':cid' => $collaboratoreId, ':data' => $data];

    if ($tabella === 'contributi' && $tipo !== null) {
        $sql .= " AND tipo = :tipo";
        $params[':tipo'] = $tipo;
    }

    $sql .= " ORDER BY data_inizio_validita DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? (float) $row['importo_ora'] : 0.0;
}

/**
 * Calcola l'anteprima di una busta paga per un collaboratore e un periodo.
 */
function calcola_busta_paga(
    PDO $pdo,
    int $collaboratoreId,
    string $dataInizio,
    string $dataFine
): array {
    $sql = "SELECT ro.id, ro.data, ro.ore, ro.tipologia_ore_id,
                   t.codice AS tipo_codice, t.descrizione AS tipo_descrizione,
                   t.retribuita, t.scala_ferie
            FROM registrazioni_ore ro
            JOIN tipologie_ore t ON t.id = ro.tipologia_ore_id
            WHERE ro.collaboratore_id = :cid
              AND ro.data BETWEEN :dinizio AND :dfine
            ORDER BY ro.data, t.codice";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid' => $collaboratoreId, ':dinizio' => $dataInizio, ':dfine' => $dataFine]);
    $righe = $stmt->fetchAll();

    $totali = [
        'ore_lavorate' => 0.0, 'ore_ferie' => 0.0,
        'ore_festivita' => 0.0, 'ore_permesso' => 0.0,
        'importo_lavorate' => 0.0, 'importo_ferie' => 0.0,
        'importo_festivita' => 0.0,
        'contributi_lavoratore' => 0.0, 'contributi_datore' => 0.0,
    ];

    $dettaglio = [];

    foreach ($righe as $r) {
        $data = $r['data'];
        $ore = (float) $r['ore'];
        $retribuita = (int) $r['retribuita'] === 1;

        $importoOra = 0.0;
        if ($retribuita) {
            if ($r['tipo_codice'] === 'FESTIVITA') {
                $importoOra = get_tariffa_valida($pdo, 'retribuzioni_festivita', $collaboratoreId, $data);
            } else {
                $importoOra = get_tariffa_valida($pdo, 'retribuzioni_ordinarie', $collaboratoreId, $data);
            }
        }

        $importoTot = $retribuita ? $ore * $importoOra : 0.0;

        $contribLav = 0.0;
        $contribDat = 0.0;
        $contribLavOra = 0.0;
        $contribDatOra = 0.0;
        if ($retribuita) {
            $contribLavOra = get_tariffa_valida($pdo, 'contributi', $collaboratoreId, $data, 'LAVORATORE');
            $contribDatOra = get_tariffa_valida($pdo, 'contributi', $collaboratoreId, $data, 'DATORE');
            $contribLav = $ore * $contribLavOra;
            $contribDat = $ore * $contribDatOra;
        }

        switch ($r['tipo_codice']) {
            case 'LAVORATE':    $totali['ore_lavorate'] += $ore; $totali['importo_lavorate'] += $importoTot; break;
            case 'FERIE':       $totali['ore_ferie'] += $ore; $totali['importo_ferie'] += $importoTot; break;
            case 'FESTIVITA':   $totali['ore_festivita'] += $ore; $totali['importo_festivita'] += $importoTot; break;
            case 'PERMESSO_NR': $totali['ore_permesso'] += $ore; break;
        }
        $totali['contributi_lavoratore'] += $contribLav;
        $totali['contributi_datore'] += $contribDat;

        $dettaglio[] = [
            'registrazione_ore_id' => (int) $r['id'],
            'data' => $data,
            'tipologia_ore_id' => (int) $r['tipologia_ore_id'],
            'tipo_codice' => $r['tipo_codice'],
            'tipo_descrizione' => $r['tipo_descrizione'],
            'ore' => $ore,
            'importo_ora' => $importoOra,
            'importo_totale' => $importoTot,
            'contributo_lavoratore_ora' => $contribLavOra,
            'contributo_datore_ora' => $contribDatOra,
        ];
    }

    $aOre = $totali['importo_lavorate'] + $totali['importo_ferie'] + $totali['importo_festivita'];
    $b = $totali['contributi_lavoratore'];
    $c = $aOre - $b;

    return [
        'dettaglio' => $dettaglio,
        'totali_ore' => [
            'lavorate' => $totali['ore_lavorate'],
            'ferie' => $totali['ore_ferie'],
            'festivita' => $totali['ore_festivita'],
            'permesso' => $totali['ore_permesso'],
        ],
        'importi' => [
            'lavorate' => $totali['importo_lavorate'],
            'ferie' => $totali['importo_ferie'],
            'festivita' => $totali['importo_festivita'],
            'a_ore' => $aOre,
            'b' => $b,
            'c' => $c,
            'contributi_datore' => $totali['contributi_datore'],
        ],
    ];
}

/**
 * Calcola l'anteprima di un report contributi per un periodo.
 */
function calcola_report_contributi(
    PDO $pdo,
    int $collaboratoreId,
    string $dataInizio,
    string $dataFine
): array {
    $sql = "SELECT ro.data, ro.ore, t.retribuita
            FROM registrazioni_ore ro
            JOIN tipologie_ore t ON t.id = ro.tipologia_ore_id
            WHERE ro.collaboratore_id = :cid
              AND ro.data BETWEEN :dinizio AND :dfine
              AND t.retribuita = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid' => $collaboratoreId, ':dinizio' => $dataInizio, ':dfine' => $dataFine]);
    $righe = $stmt->fetchAll();

    $totOre = 0.0;
    $contribLav = 0.0;
    $contribDat = 0.0;

    foreach ($righe as $r) {
        $ore = (float) $r['ore'];
        $totOre += $ore;
        $contribLav += $ore * get_tariffa_valida($pdo, 'contributi', $collaboratoreId, $r['data'], 'LAVORATORE');
        $contribDat += $ore * get_tariffa_valida($pdo, 'contributi', $collaboratoreId, $r['data'], 'DATORE');
    }

    return [
        'totale_ore' => $totOre,
        'contributi_lavoratore' => $contribLav,
        'contributi_datore' => $contribDat,
    ];
}

/**
 * Restituisce il monte ferie valido a una certa data.
 */
function get_monte_ferie_valido(PDO $pdo, int $collaboratoreId, string $data): ?array
{
    $sql = "SELECT * FROM monte_ferie
            WHERE collaboratore_id = :cid
              AND data_inizio_validita <= :data
              AND data_fine_validita >= :data
            ORDER BY data_inizio_validita DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid' => $collaboratoreId, ':data' => $data]);
    $row = $stmt->fetch();
    return $row ?: null;
}
