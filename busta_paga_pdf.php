<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funzioni_calcolo.php';

richiedi_login();

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$collaboratoreId = 1;

// ------------------------------------------------------------
// Recupero busta paga
// ------------------------------------------------------------
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('ID busta paga mancante.');
}

$stmt = $pdo->prepare('SELECT * FROM buste_paga WHERE id = :id AND collaboratore_id = :cid');
$stmt->execute([':id' => $id, ':cid' => $collaboratoreId]);
$busta = $stmt->fetch();

if (!$busta) {
    http_response_code(404);
    die('Busta paga non trovata.');
}

$stmt = $pdo->prepare('SELECT * FROM collaboratori WHERE id = :id');
$stmt->execute([':id' => $collaboratoreId]);
$collaboratore = $stmt->fetch();

// Righe dettaglio
$stmt = $pdo->prepare('SELECT bpr.*, t.descrizione AS tipo_descrizione, t.codice AS tipo_codice
                       FROM busta_paga_righe bpr
                       JOIN tipologie_ore t ON t.id = bpr.tipologia_ore_id
                       WHERE bpr.busta_paga_id = :id
                       ORDER BY bpr.data, t.id');
$stmt->execute([':id' => $id]);
$righe = $stmt->fetchAll();

// Righe manuali
$stmt = $pdo->prepare('SELECT * FROM busta_paga_righe_manuali WHERE busta_paga_id = :id ORDER BY id');
$stmt->execute([':id' => $id]);
$manuali = $stmt->fetchAll();

// ------------------------------------------------------------
// Costruzione HTML
// ------------------------------------------------------------
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return number_format((float) $v, 2, ',', '.'); }
function ore($v): string { return number_format((float) $v, 2, ',', '.'); }
function dataIt($v): string { return $v ? date('d/m/Y', strtotime($v)) : '—'; }

$html = '<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Busta paga ' . e($busta['numero']) . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; }
    h1 { font-size: 18px; margin: 0 0 4px 0; }
    h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #000; padding-bottom: 2px; }
    .intestazione { margin-bottom: 14px; }
    .intestazione .label { color: #555; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #999; padding: 4px 6px; }
    th { background: #eee; text-align: left; }
    td.num, th.num { text-align: right; }
    .totali td { font-weight: bold; }
    .footer { margin-top: 24px; font-size: 9px; color: #666; text-align: center; }
</style>
</head>
<body>

<div class="intestazione">
    <h1>Busta paga n. ' . e($busta['numero']) . '</h1>
    <div><span class="label">Data emissione:</span> ' . dataIt($busta['data_emissione']) . '</div>
    <div><span class="label">Collaboratrice:</span> ' . e($collaboratore['nome'] . ' ' . $collaboratore['cognome']) . '</div>
    <div><span class="label">Codice fiscale:</span> ' . e($collaboratore['codice_fiscale']) . '</div>
    <div><span class="label">Periodo:</span> ' . dataIt($busta['data_inizio_periodo']) . ' – ' . dataIt($busta['data_fine_periodo']) . '</div>
</div>

<h2>Dettaglio ore</h2>
<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Tipologia</th>
            <th class="num">Ore</th>
            <th class="num">Importo/ora</th>
            <th class="num">Importo</th>
        </tr>
    </thead>
    <tbody>';

if (!$righe) {
    $html .= '<tr><td colspan="5">Nessuna riga di dettaglio.</td></tr>';
} else {
    foreach ($righe as $r) {
        $html .= '<tr>
            <td>' . dataIt($r['data']) . '</td>
            <td>' . e($r['tipo_descrizione']) . '</td>
            <td class="num">' . ore($r['ore']) . '</td>
            <td class="num">€ ' . money($r['importo_ora']) . '</td>
            <td class="num">€ ' . money($r['importo_totale']) . '</td>
        </tr>';
    }
}

$html .= '</tbody></table>

<h2>Riepilogo ore per tipologia</h2>
<table>
    <tbody>
        <tr><td>Ore lavorate</td><td class="num">' . ore($busta['totale_ore_lavorate']) . ' h</td><td class="num">€ ' . money($busta['importo_lavorate']) . '</td></tr>
        <tr><td>Ore ferie</td><td class="num">' . ore($busta['totale_ore_ferie']) . ' h</td><td class="num">€ ' . money($busta['importo_ferie']) . '</td></tr>
        <tr><td>Ore festività retribuite</td><td class="num">' . ore($busta['totale_ore_festivita']) . ' h</td><td class="num">€ ' . money($busta['importo_festivita']) . '</td></tr>
        <tr><td>Permesso non retribuito</td><td class="num">' . ore($busta['totale_ore_permesso']) . ' h</td><td class="num">€ 0,00</td></tr>
    </tbody>
</table>';

if ($manuali) {
    $html .= '<h2>Altre voci</h2>
    <table>
        <thead><tr><th>Descrizione</th><th class="num">Importo</th></tr></thead>
        <tbody>';
    foreach ($manuali as $m) {
        $html .= '<tr>
            <td>' . e($m['descrizione']) . '</td>
            <td class="num">€ ' . money($m['importo']) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '<h2>Totali</h2>
<table class="totali">
    <tbody>
        <tr><td>Totale lordo (A)</td><td class="num">€ ' . money($busta['totale_lordo_a']) . '</td></tr>
        <tr><td>Contributi a carico lavoratore (B)</td><td class="num">− € ' . money($busta['contributi_lavoratore_b']) . '</td></tr>
        <tr><td>Totale netto busta paga (C = A − B)</td><td class="num">€ ' . money($busta['totale_netto_c']) . '</td></tr>
        <tr><td>Contributi a carico datore (informativo)</td><td class="num">€ ' . money($busta['contributi_datore']) . '</td></tr>
    </tbody>
</table>

<div class="footer">
    Documento generato il ' . date('d/m/Y H:i') . '
</div>

</body>
</html>';

// ------------------------------------------------------------
// Generazione PDF con Dompdf
// ------------------------------------------------------------
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// inline = visualizza nel browser; per forzare il download usa 'Attachment'
$dompdf->stream('busta_paga_' . preg_replace('/[^0-9A-Za-z]/', '_', $busta['numero']) . '.pdf', ['Attachment' => false]);
exit;