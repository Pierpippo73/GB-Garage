<?php
// get_corsa.php

// Rimuove eventuali spazi o output inviati per errore da altri file inclusi prima del tempo
ob_start(); 

header('Content-Type: application/json');
require('/var/www/gbgarage/partial_php/connect-db.php');

if (!isset($_GET['id_corsa']) || empty($_GET['id_corsa'])) {
    ob_clean();
    echo json_encode(['error' => 'ID corsa mancante']);
    exit;
}

$id_corsa = intval($_GET['id_corsa']);

// 1. Recupera i dati generali della corsa formattando gli orari in HH:MM per l'input HTML
$query_corsa = "SELECT linea, partenza, TIME_FORMAT(orario_corsa, '%H:%i') AS orario_corsa, arrivo, TIME_FORMAT(fine_corsa, '%H:%i') AS fine_corsa, note FROM gbgar_corse WHERE id_corsa = ?";
$stmt = $Conn->prepare($query_corsa);
$stmt->bind_param("i", $id_corsa);
$stmt->execute();
$ris_corsa = $stmt->get_result();

if ($ris_corsa->num_rows === 0) {
    ob_clean();
    echo json_encode(['error' => 'Corsa non trovata']);
    exit;
}

$corsa = $ris_corsa->fetch_assoc();

// 2. Recupera i periodi e la frequenza associati
$stmt_p = $Conn->prepare("SELECT id_periodo, frequenza FROM gbgar_cadenza_corse WHERE id_corsa = ?");
$stmt_p->bind_param("i", $id_corsa);
$stmt_p->execute();
$ris_periodi = $stmt_p->get_result();

$periodi = [];
$frequenza = "";

while ($row = $ris_periodi->fetch_assoc()) {
    // Convertiamo l'ID in intero per farlo digerire correttamente al .includes() di JS
    $periodi[] = intval($row['id_periodo']); 
    $frequenza = $row['frequenza']; 
}

// 3. Costruisci la risposta gestendo i valori nulli
$risposta = [
    'linea'        => (string)$corsa['linea'],
    'partenza'     => (string)$corsa['partenza'],
    'orario_corsa' => (string)$corsa['orario_corsa'],
    'arrivo'       => (string)$corsa['arrivo'],
    'fine_corsa'   => (string)$corsa['fine_corsa'],
    'note'         => ($corsa['note'] !== null) ? (string)$corsa['note'] : "",
    'periodi'      => $periodi,
    'frequenza'    => (string)$frequenza
];

// Pulisce il buffer da eventuali warning di PHP prima di stampare il JSON puro
ob_clean(); 
echo json_encode($risposta);
exit;
