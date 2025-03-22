</main>
        </div>
    </div>
    
    <!-- Bootstrap JavaScript Bundle mit Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Flatpickr für Datums- und Zeitauswahl -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>
    
    <!-- Datepicker-Initialisierung -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datepicker für alle Datumsfelder
        flatpickr("input[name='date']", {
            dateFormat: "d.m.Y",
            locale: "de",
            allowInput: true
        });
        
        // Timepicker für alle Zeitfelder
        flatpickr("input[name='start_time'], input[name='end_time']", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            locale: "de",
            allowInput: true
        });
        
        // Datepicker für alle Date-Range-Felder
        flatpickr("input[name='date_from'], input[name='date_to']", {
            dateFormat: "d.m.Y",
            locale: "de",
            allowInput: true
        });
    });
    </script>
    
    <!-- Globale Suchfunktion -->
    <script src="/js/search.js"></script>
    
    <!-- Hauptskript -->
    <script src="/js/main.js"></script>
</body>
</html>