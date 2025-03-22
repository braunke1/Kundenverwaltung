<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Initialisieren von Fehlermeldungen und Erfolgsbenachrichtigungen
$errors = [];
$success = '';

// Erfolgs- oder Fehlermeldung aus der Session holen und anzeigen
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Nachricht aus der Session entfernen
}

// Verarbeiten des Formulars beim Absenden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Wenn keine Fehler, in die Datenbank einfügen
    if (empty($errors)) {
        $sql = "INSERT INTO customers (
                    customer_type, first_name, last_name, email, phone, 
                    street, postal_code, city, country, 
                    company_name, tax_id, website, industry, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssss", 
            $customerType, $firstName, $lastName, $email, $phone, 
            $street, $postalCode, $city, $country, 
            $companyName, $taxId, $website, $industry, $notes
        );
        
        if ($stmt->execute()) {
            $customerId = $conn->insert_id;
            
            if (isset($_POST['save_and_new'])) {
                // Speichern und neuen Kunden anlegen
                redirectTo("/customers/add.php", "Kunde erfolgreich erstellt. Sie können nun einen weiteren Kunden anlegen.");
            } else {
                // Speichern und zur Kundenliste zurückkehren
                redirectTo("/customers/", "Kunde erfolgreich erstellt.");
            }
        } else {
            $errors[] = "Fehler beim Erstellen des Kunden: " . $conn->error;
        }
        
        $stmt->close();
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user-plus"></i> Neuen Kunden anlegen</h1>
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
                                    <input class="form-check-input" type="radio" name="customer_type" id="type_private" value="private" checked onchange="toggleCustomerType()">
                                    <label class="form-check-label" for="type_private">
                                        Privatkunde
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="customer_type" id="type_business" value="business" onchange="toggleCustomerType()">
                                    <label class="form-check-label" for="type_business">
                                        Geschäftskunde
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Geschäftskunden-spezifische Felder -->
                        <div id="business_fields" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="company_name" class="form-label">Firmenname *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name">
                                </div>
                                <div class="col-md-6">
                                    <label for="tax_id" class="form-label">USt-ID</label>
                                    <input type="text" class="form-control" id="tax_id" name="tax_id">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="website" class="form-label">Webseite</label>
                                    <input type="url" class="form-control" id="website" name="website">
                                </div>
                                <div class="col-md-6">
                                    <label for="industry" class="form-label">Branche</label>
                                    <input type="text" class="form-control" id="industry" name="industry">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Allgemeine Informationen -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">Vorname <span id="private_required">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Nachname <span id="private_required2">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-Mail</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        
                        <!-- Adressinformationen -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="street" class="form-label">Straße</label>
                                <input type="text" class="form-control" id="street" name="street">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="postal_code" class="form-label">PLZ</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code">
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label">Stadt</label>
                                <input type="text" class="form-control" id="city" name="city">
                            </div>
                            <div class="col-md-4">
                                <label for="country" class="form-label">Land</label>
                                <input type="text" class="form-control" id="country" name="country" value="Deutschland">
                            </div>
                        </div>
                        
                        <!-- Notizen -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notizen</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <!-- Submit-Buttons -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="submit" class="btn btn-primary" name="save" value="1">
                                            <i class="fas fa-save"></i> Speichern
                                        </button>
                                        <button type="submit" class="btn btn-success" name="save_and_new" value="1">
                                            <i class="fas fa-save"></i> Speichern & neuer Kunde
                                        </button>
                                    </div>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Zurücksetzen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomerType() {
    const isBusinessCustomer = document.getElementById('type_business').checked;
    const businessFields = document.getElementById('business_fields');
    const privateRequired = document.querySelectorAll('#private_required, #private_required2');
    
    if (isBusinessCustomer) {
        businessFields.style.display = 'block';
        privateRequired.forEach(elem => elem.style.display = 'none');
    } else {
        businessFields.style.display = 'none';
        privateRequired.forEach(elem => elem.style.display = 'inline');
    }
}
</script>

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>