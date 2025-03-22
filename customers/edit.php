<?php
require_once '../includes/header.php';

// Überprüfen, ob eine ID übergeben wurde
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectTo("/customers/", "Keine gültige Kunden-ID angegeben.");
}

$customerId = (int)$_GET['id'];

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Kundendaten aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

// Prüfen, ob der Kunde existiert
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirectTo("/customers/", "Kunde nicht gefunden.");
}

$customer = $result->fetch_assoc();
$stmt->close();

// Kontakte abrufen, wenn es ein Geschäftskunde ist
$contacts = [];
if ($customer['customer_type'] === 'business') {
    $contactStmt = $conn->prepare("SELECT * FROM contacts WHERE customer_id = ? ORDER BY is_primary DESC, last_name, first_name");
    $contactStmt->bind_param("i", $customerId);
    $contactStmt->execute();
    $contactResult = $contactStmt->get_result();
    while ($contact = $contactResult->fetch_assoc()) {
        $contacts[] = $contact;
    }
    $contactStmt->close();
}

// Initialisieren von Fehlermeldungen und Erfolgsbenachrichtigungen
$errors = [];
$success = '';

// Erfolgs- oder Fehlermeldung aus der Session holen und anzeigen
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Kontakte bearbeiten, wenn es ein Geschäftskunde ist
if ($customer['customer_type'] === 'business' && isset($_POST['contact_action'])) {
    $contactAction = $_POST['contact_action'];
    
    // Neuen Kontakt hinzufügen
    if ($contactAction === 'add' && isset($_POST['new_contact_first_name'], $_POST['new_contact_last_name'])) {
        $contactFirstName = sanitizeInput($_POST['new_contact_first_name']);
        $contactLastName = sanitizeInput($_POST['new_contact_last_name']);
        $contactPosition = sanitizeInput($_POST['new_contact_position']);
        $contactEmail = sanitizeInput($_POST['new_contact_email']);
        $contactPhone = sanitizeInput($_POST['new_contact_phone']);
        $contactIsPrimary = isset($_POST['new_contact_is_primary']) ? 1 : 0;
        $contactNotes = sanitizeInput($_POST['new_contact_notes']);
        
        if (!empty($contactFirstName) && !empty($contactLastName)) {
            // Wenn dieser Kontakt primär ist, alle anderen auf nicht-primär setzen
            if ($contactIsPrimary) {
                $updateStmt = $conn->prepare("UPDATE contacts SET is_primary = 0 WHERE customer_id = ?");
                $updateStmt->bind_param("i", $customerId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            $insertStmt = $conn->prepare("INSERT INTO contacts 
                (customer_id, first_name, last_name, position, email, phone, is_primary, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
            $insertStmt->bind_param(
                "isssssis", 
                $customerId, $contactFirstName, $contactLastName, $contactPosition, 
                $contactEmail, $contactPhone, $contactIsPrimary, $contactNotes
            );
            
            if ($insertStmt->execute()) {
                $success = "Kontakt erfolgreich hinzugefügt!";
                
                // Kontakte neu laden
                $contactStmt = $conn->prepare("SELECT * FROM contacts WHERE customer_id = ? ORDER BY is_primary DESC, last_name, first_name");
                $contactStmt->bind_param("i", $customerId);
                $contactStmt->execute();
                $contactResult = $contactStmt->get_result();
                $contacts = [];
                while ($contact = $contactResult->fetch_assoc()) {
                    $contacts[] = $contact;
                }
                $contactStmt->close();
            } else {
                $errors[] = "Fehler beim Hinzufügen des Kontakts: " . $conn->error;
            }
            
            $insertStmt->close();
        } else {
            $errors[] = "Vor- und Nachname sind für Kontakte erforderlich.";
        }
    }
    
    // Kontakt bearbeiten
    else if ($contactAction === 'edit' && isset($_POST['edit_contact_id'], $_POST['edit_contact_first_name'], $_POST['edit_contact_last_name'])) {
        $contactId = (int)$_POST['edit_contact_id'];
        $contactFirstName = sanitizeInput($_POST['edit_contact_first_name']);
        $contactLastName = sanitizeInput($_POST['edit_contact_last_name']);
        $contactPosition = sanitizeInput($_POST['edit_contact_position']);
        $contactEmail = sanitizeInput($_POST['edit_contact_email']);
        $contactPhone = sanitizeInput($_POST['edit_contact_phone']);
        $contactIsPrimary = isset($_POST['edit_contact_is_primary']) ? 1 : 0;
        $contactNotes = sanitizeInput($_POST['edit_contact_notes']);
        
        if (!empty($contactFirstName) && !empty($contactLastName)) {
            // Wenn dieser Kontakt primär ist, alle anderen auf nicht-primär setzen
            if ($contactIsPrimary) {
                $updateStmt = $conn->prepare("UPDATE contacts SET is_primary = 0 WHERE customer_id = ?");
                $updateStmt->bind_param("i", $customerId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            $updateStmt = $conn->prepare("UPDATE contacts SET 
                first_name = ?, last_name = ?, position = ?, email = ?, 
                phone = ?, is_primary = ?, notes = ? 
                WHERE contact_id = ? AND customer_id = ?");
                
            $updateStmt->bind_param(
                "ssssssiii", 
                $contactFirstName, $contactLastName, $contactPosition, 
                $contactEmail, $contactPhone, $contactIsPrimary, $contactNotes,
                $contactId, $customerId
            );
            
            if ($updateStmt->execute()) {
                $success = "Kontakt erfolgreich aktualisiert!";
                
                // Kontakte neu laden
                $contactStmt = $conn->prepare("SELECT * FROM contacts WHERE customer_id = ? ORDER BY is_primary DESC, last_name, first_name");
                $contactStmt->bind_param("i", $customerId);
                $contactStmt->execute();
                $contactResult = $contactStmt->get_result();
                $contacts = [];
                while ($contact = $contactResult->fetch_assoc()) {
                    $contacts[] = $contact;
                }
                $contactStmt->close();
            } else {
                $errors[] = "Fehler beim Aktualisieren des Kontakts: " . $conn->error;
            }
            
            $updateStmt->close();
        } else {
            $errors[] = "Vor- und Nachname sind für Kontakte erforderlich.";
        }
    }
    
    // Kontakt löschen
    else if ($contactAction === 'delete' && isset($_POST['delete_contact_id'])) {
        $contactId = (int)$_POST['delete_contact_id'];
        
        $deleteStmt = $conn->prepare("DELETE FROM contacts WHERE contact_id = ? AND customer_id = ?");
        $deleteStmt->bind_param("ii", $contactId, $customerId);
        
        if ($deleteStmt->execute()) {
            $success = "Kontakt erfolgreich gelöscht!";
            
            // Kontakte neu laden
            $contactStmt = $conn->prepare("SELECT * FROM contacts WHERE customer_id = ? ORDER BY is_primary DESC, last_name, first_name");
            $contactStmt->bind_param("i", $customerId);
            $contactStmt->execute();
            $contactResult = $contactStmt->get_result();
            $contacts = [];
            while ($contact = $contactResult->fetch_assoc()) {
                $contacts[] = $contact;
            }
            $contactStmt->close();
        } else {
            $errors[] = "Fehler beim Löschen des Kontakts: " . $conn->error;
        }
        
        $deleteStmt->close();
    }
}

// Verarbeiten des Formulars beim Absenden der Kundendaten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['contact_action'])) {
    // Kundendaten sammeln
    $customerType = sanitizeInput($_POST['customer_type']);
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $street = sanitizeInput($_POST['street']);
    $postalCode = sanitizeInput($_POST['postal_code']);
    $city = sanitizeInput($_POST['city']);
    $country = sanitizeInput($_POST['country']);
    $companyName = ($customerType === 'business') ? sanitizeInput($_POST['company_name']) : null;
    $taxId = ($customerType === 'business') ? sanitizeInput($_POST['tax_id']) : null;
    $website = ($customerType === 'business') ? sanitizeInput($_POST['website']) : null;
    $industry = ($customerType === 'business') ? sanitizeInput($_POST['industry']) : null;
    $notes = sanitizeInput($_POST['notes']);
    
    // Validierung
    if ($customerType === 'private') {
        if (empty($firstName)) {
            $errors[] = "Vorname ist erforderlich für Privatkunden.";
        }
        if (empty($lastName)) {
            $errors[] = "Nachname ist erforderlich für Privatkunden.";
        }
    } else {
        if (empty($companyName)) {
            $errors[] = "Firmenname ist erforderlich für Geschäftskunden.";
        }
    }
    
    // Wenn keine Fehler, in die Datenbank aktualisieren
    if (empty($errors)) {
        $sql = "UPDATE customers SET 
                    customer_type = ?, 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    street = ?, 
                    postal_code = ?, 
                    city = ?, 
                    country = ?, 
                    company_name = ?, 
                    tax_id = ?, 
                    website = ?, 
                    industry = ?, 
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE customer_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssssi", 
            $customerType, $firstName, $lastName, $email, $phone, 
            $street, $postalCode, $city, $country, 
            $companyName, $taxId, $website, $industry, $notes, $customerId
        );
        
        if ($stmt->execute()) {
            // Daten neu laden
            $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();
            
            // Wenn der Kundentyp geändert wurde und jetzt kein Geschäftskunde mehr ist, 
            // Kontakte löschen (optional)
            if ($customerType !== 'business' && !empty($contacts)) {
                $deleteContactsStmt = $conn->prepare("DELETE FROM contacts WHERE customer_id = ?");
                $deleteContactsStmt->bind_param("i", $customerId);
                $deleteContactsStmt->execute();
                $deleteContactsStmt->close();
                $contacts = []; // Kontakte zurücksetzen
            }
            
            // Erfolgsmeldung und Weiterleitung
            if (isset($_POST['save_and_return'])) {
                redirectTo("/customers/", "Kunde erfolgreich aktualisiert.");
            } else {
                // Auf der Bearbeitungsseite bleiben mit Erfolgsmeldung
                redirectTo("/customers/edit.php?id=" . $customerId, "Kunde erfolgreich aktualisiert.");
            }
        } else {
            $errors[] = "Fehler beim Aktualisieren des Kunden: " . $conn->error;
        }
        
        $stmt->close();
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user-edit"></i> Kunde bearbeiten</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/customers/" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Zurück zur Liste
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <!-- Kundentyp auswählen -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Kundentyp</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="customer_type" id="type_private" value="private" <?php echo ($customer['customer_type'] === 'private') ? 'checked' : ''; ?> onchange="toggleCustomerType()">
                                    <label class="form-check-label" for="type_private">
                                        Privatkunde
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="customer_type" id="type_business" value="business" <?php echo ($customer['customer_type'] === 'business') ? 'checked' : ''; ?> onchange="toggleCustomerType()">
                                    <label class="form-check-label" for="type_business">
                                        Geschäftskunde
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Geschäftskunden-spezifische Felder -->
                        <div id="business_fields" style="display: <?php echo ($customer['customer_type'] === 'business') ? 'block' : 'none'; ?>;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="company_name" class="form-label">Firmenname *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($customer['company_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="tax_id" class="form-label">USt-ID</label>
                                    <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?php echo htmlspecialchars($customer['tax_id']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="website" class="form-label">Webseite</label>
                                    <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($customer['website']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="industry" class="form-label">Branche</label>
                                    <input type="text" class="form-control" id="industry" name="industry" value="<?php echo htmlspecialchars($customer['industry']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Allgemeine Informationen -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">Vorname <span id="private_required" <?php echo ($customer['customer_type'] === 'business') ? 'style="display:none;"' : ''; ?>>*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Nachname <span id="private_required2" <?php echo ($customer['customer_type'] === 'business') ? 'style="display:none;"' : ''; ?>>*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-Mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>">
                            </div>
                        </div>
                        
                        <!-- Adressinformationen -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="street" class="form-label">Straße</label>
                                <input type="text" class="form-control" id="street" name="street" value="<?php echo htmlspecialchars($customer['street']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="postal_code" class="form-label">PLZ</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($customer['postal_code']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label">Stadt</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($customer['city']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="country" class="form-label">Land</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($customer['country']); ?>">
                            </div>
                        </div>
                        
                        <!-- Notizen -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notizen</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Submit-Buttons -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Speichern
                                        </button>
                                        <button type="submit" name="save_and_return" value="1" class="btn btn-success">
                                            <i class="fas fa-save"></i> Speichern & zurück
                                        </button>
                                    </div>
                                    <a href="/customers/" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Kontakte verwalten (nur für Geschäftskunden) -->
            <?php if ($customer['customer_type'] === 'business'): ?>
            <div class="card mt-4" id="contacts_section">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-address-card"></i> Ansprechpartner verwalten</h5>
                </div>
                <div class="card-body">
                    <!-- Vorhandene Kontakte anzeigen -->
                    <?php if (!empty($contacts)): ?>
                        <div class="table-responsive mb-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Kontakt</th>
                                        <th>Primär</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['position']); ?></td>
                                            <td>
                                                <?php if (!empty($contact['email'])): ?>
                                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($contact['phone'])): ?>
                                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact['phone']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($contact['is_primary']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check"></i> Primär</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-contact-btn" 
                                                            data-contact-id="<?php echo $contact['contact_id']; ?>"
                                                            data-first-name="<?php echo htmlspecialchars($contact['first_name']); ?>"
                                                            data-last-name="<?php echo htmlspecialchars($contact['last_name']); ?>"
                                                            data-position="<?php echo htmlspecialchars($contact['position']); ?>"
                                                            data-email="<?php echo htmlspecialchars($contact['email']); ?>"
                                                            data-phone="<?php echo htmlspecialchars($contact['phone']); ?>"
                                                            data-is-primary="<?php echo $contact['is_primary']; ?>"
                                                            data-notes="<?php echo htmlspecialchars($contact['notes']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-contact-btn"
                                                            data-contact-id="<?php echo $contact['contact_id']; ?>"
                                                            data-contact-name="<?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Keine Ansprechpartner vorhanden.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Neuen Kontakt hinzufügen -->
                    <button type="button" class="btn btn-success mb-3" id="add-contact-btn">
                        <i class="fas fa-plus"></i> Neuen Ansprechpartner hinzufügen
                    </button>
                    
                    <!-- Formular für neuen Kontakt (anfangs versteckt) -->
                    <div id="add-contact-form" style="display: none;">
                        <h5 class="mb-3">Neuen Ansprechpartner hinzufügen</h5>
                        <form action="" method="POST">
                            <input type="hidden" name="contact_action" value="add">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new_contact_first_name" class="form-label">Vorname *</label>
                                    <input type="text" class="form-control" id="new_contact_first_name" name="new_contact_first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="new_contact_last_name" class="form-label">Nachname *</label>
                                    <input type="text" class="form-control" id="new_contact_last_name" name="new_contact_last_name" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new_contact_position" class="form-label">Position</label>
                                    <input type="text" class="form-control" id="new_contact_position" name="new_contact_position">
                                </div>
                                <div class="col-md-6">
                                    <label for="new_contact_email" class="form-label">E-Mail</label>
                                    <input type="email" class="form-control" id="new_contact_email" name="new_contact_email">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new_contact_phone" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="new_contact_phone" name="new_contact_phone">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="new_contact_is_primary" name="new_contact_is_primary">
                                        <label class="form-check-label" for="new_contact_is_primary">
                                            Primärer Ansprechpartner
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="new_contact_notes" class="form-label">Notizen</label>
                                    <textarea class="form-control" id="new_contact_notes" name="new_contact_notes" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Speichern
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancel-add-contact">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Formular für Kontakt bearbeiten (wird per JS gefüllt) -->
                    <div id="edit-contact-form" style="display: none;">
                        <h5 class="mb-3">Ansprechpartner bearbeiten</h5>
                        <form action="" method="POST">
                            <input type="hidden" name="contact_action" value="edit">
                            <input type="hidden" name="edit_contact_id" id="edit_contact_id">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit_contact_first_name" class="form-label">Vorname *</label>
                                    <input type="text" class="form-control" id="edit_contact_first_name" name="edit_contact_first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_contact_last_name" class="form-label">Nachname *</label>
                                    <input type="text" class="form-control" id="edit_contact_last_name" name="edit_contact_last_name" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit_contact_position" class="form-label">Position</label>
                                    <input type="text" class="form-control" id="edit_contact_position" name="edit_contact_position">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_contact_email" class="form-label">E-Mail</label>
                                    <input type="email" class="form-control" id="edit_contact_email" name="edit_contact_email">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit_contact_phone" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="edit_contact_phone" name="edit_contact_phone">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="edit_contact_is_primary" name="edit_contact_is_primary">
                                        <label class="form-check-label" for="edit_contact_is_primary">
                                            Primärer Ansprechpartner
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="edit_contact_notes" class="form-label">Notizen</label>
                                    <textarea class="form-control" id="edit_contact_notes" name="edit_contact_notes" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Speichern
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancel-edit-contact">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Formular für Kontakt löschen -->
                    <form id="delete-contact-form" action="" method="POST" style="display: none;">
                        <input type="hidden" name="contact_action" value="delete">
                        <input type="hidden" name="delete_contact_id" id="delete_contact_id">
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCustomerType() {
    const isBusinessCustomer = document.getElementById('type_business').checked;
    const businessFields = document.getElementById('business_fields');
    const contactsSection = document.getElementById('contacts_section');
    const privateRequired = document.querySelectorAll('#private_required, #private_required2');
    
    if (isBusinessCustomer) {
        businessFields.style.display = 'block';
        if (contactsSection) contactsSection.style.display = 'block';
        privateRequired.forEach(elem => elem.style.display = 'none');
    } else {
        businessFields.style.display = 'none';
        if (contactsSection) contactsSection.style.display = 'none';
        privateRequired.forEach(elem => elem.style.display = 'inline');
    }
}

// Kontaktformulare verwalten
document.addEventListener('DOMContentLoaded', function() {
    // Neuen Kontakt hinzufügen
    const addContactBtn = document.getElementById('add-contact-btn');
    const addContactForm = document.getElementById('add-contact-form');
    const cancelAddContactBtn = document.getElementById('cancel-add-contact');
    
    if (addContactBtn) {
        addContactBtn.addEventListener('click', function() {
            addContactForm.style.display = 'block';
            this.style.display = 'none';
            document.getElementById('edit-contact-form').style.display = 'none';
        });
    }
    
    if (cancelAddContactBtn) {
        cancelAddContactBtn.addEventListener('click', function() {
            addContactForm.style.display = 'none';
            addContactBtn.style.display = 'block';
        });
    }
    
    // Kontakt bearbeiten
    const editContactBtns = document.querySelectorAll('.edit-contact-btn');
    const editContactForm = document.getElementById('edit-contact-form');
    const cancelEditContactBtn = document.getElementById('cancel-edit-contact');
    
    editContactBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const contactId = this.getAttribute('data-contact-id');
            const firstName = this.getAttribute('data-first-name');
            const lastName = this.getAttribute('data-last-name');
            const position = this.getAttribute('data-position');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const isPrimary = this.getAttribute('data-is-primary') === '1';
            const notes = this.getAttribute('data-notes');
            
            document.getElementById('edit_contact_id').value = contactId;
            document.getElementById('edit_contact_first_name').value = firstName;
            document.getElementById('edit_contact_last_name').value = lastName;
            document.getElementById('edit_contact_position').value = position;
            document.getElementById('edit_contact_email').value = email;
            document.getElementById('edit_contact_phone').value = phone;
            document.getElementById('edit_contact_is_primary').checked = isPrimary;
            document.getElementById('edit_contact_notes').value = notes;
            
            editContactForm.style.display = 'block';
            addContactForm.style.display = 'none';
            addContactBtn.style.display = 'block';
        });
    });
    
    if (cancelEditContactBtn) {
        cancelEditContactBtn.addEventListener('click', function() {
            editContactForm.style.display = 'none';
        });
    }
    
    // Kontakt löschen
    const deleteContactBtns = document.querySelectorAll('.delete-contact-btn');
    const deleteContactForm = document.getElementById('delete-contact-form');
    
    deleteContactBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const contactId = this.getAttribute('data-contact-id');
            const contactName = this.getAttribute('data-contact-name');
            
            if (confirm(`Möchten Sie den Ansprechpartner "${contactName}" wirklich löschen?`)) {
                document.getElementById('delete_contact_id').value = contactId;
                deleteContactForm.submit();
            }
        });
    });
});
</script>

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>