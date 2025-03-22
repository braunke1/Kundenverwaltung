<?php
// Konfiguration laden
require_once '../config/database.php';

// JSON-Header
header('Content-Type: application/json');

// Prüfen, ob ein Suchbegriff übergeben wurde
if (!isset($_GET['term']) || empty($_GET['term'])) {
    echo json_encode(['error' => 'Kein Suchbegriff angegeben']);
    exit;
}

$searchTerm = sanitizeInput($_GET['term']);

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Suche nach Kunden (Privat und Geschäft)
$sql = "SELECT 
            customer_id, 
            customer_type,
            CASE 
                WHEN customer_type = 'business' THEN company_name 
                ELSE CONCAT(first_name, ' ', last_name) 
            END as display_name,
            first_name,
            last_name,
            company_name,
            email,
            phone
        FROM 
            customers 
        WHERE 
            first_name LIKE ? OR 
            last_name LIKE ? OR 
            company_name LIKE ? OR
            CONCAT(first_name, ' ', last_name) LIKE ?
        ORDER BY 
            CASE 
                WHEN first_name = ? OR last_name = ? OR company_name = ? THEN 1 -- Exakte Treffer
                WHEN customer_type = 'business' THEN 2  -- Geschäftskunden  
                ELSE 3
            END,
            display_name
        LIMIT 15";

$stmt = $conn->prepare($sql);
$searchPattern = '%' . $searchTerm . '%';

// Bind-Parameter
$stmt->bind_param(
    "sssssss",
    $searchPattern, // first_name LIKE
    $searchPattern, // last_name LIKE
    $searchPattern, // company_name LIKE
    $searchPattern, // CONCAT(first_name, ' ', last_name) LIKE
    $searchTerm,    // Exakter Vorname
    $searchTerm,    // Exakter Nachname
    $searchTerm     // Exakter Firmenname
);

$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    // Kundeninformationen mit ein paar nützlichen Details
    $customers[] = [
        'id' => $row['customer_id'],
        'text' => $row['display_name'],
        'type' => $row['customer_type'],
        'email' => $row['email'],
        'phone' => $row['phone']
    ];
}

$stmt->close();
$conn->close();

echo json_encode($customers);
?>