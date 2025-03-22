<?php
// Konfiguration laden
require_once '../config/database.php';

// JSON-Header
header('Content-Type: application/json');

// Überprüfen, ob eine Kunden-ID übergeben wurde
if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
    echo json_encode(['error' => 'Keine Kunden-ID angegeben']);
    exit;
}

$customerId = (int)$_GET['customer_id'];

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Ansprechpartner abrufen
$contactStmt = $conn->prepare("
    SELECT contact_id, first_name, last_name, position 
    FROM contacts 
    WHERE customer_id = ? 
    ORDER BY is_primary DESC, last_name, first_name
");
$contactStmt->bind_param("i", $customerId);
$contactStmt->execute();
$contactResult = $contactStmt->get_result();

$contacts = [];
while ($row = $contactResult->fetch_assoc()) {
    $contacts[] = [
        'id' => $row['contact_id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'] . 
                 (!empty($row['position']) ? ' (' . $row['position'] . ')' : '')
    ];
}

$contactStmt->close();
$conn->close();

// Ergebnis zurückgeben
echo json_encode($contacts);
?>