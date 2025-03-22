/**
 * Globale Suchfunktion für das ERP/CRM-System
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('global-search-input');
    const searchResultsContainer = document.getElementById('search-results');
    const searchOverlay = document.getElementById('search-overlay');
    
    if (!searchInput) return;
    
    let searchTimeout;
    
    // Suchergebnisse anzeigen
    function showSearchResults(results) {
        if (!searchResultsContainer) return;
        
        searchResultsContainer.innerHTML = '';
        
        if (results.total === 0) {
            searchResultsContainer.innerHTML = '<div class="p-3 text-center text-muted">Keine Ergebnisse gefunden</div>';
            return;
        }
        
        // Ergebnisse nach Kategorien anzeigen
        const categories = {
            'customers': { icon: 'fas fa-users', title: 'Kunden' },
            'appointments': { icon: 'fas fa-calendar-alt', title: 'Termine' },
            'services': { icon: 'fas fa-tasks', title: 'Leistungen' }
        };
        
        for (const [category, items] of Object.entries(results.results)) {
            if (items.length === 0) continue;
            
            const categoryEl = document.createElement('div');
            categoryEl.className = 'search-category';
            categoryEl.innerHTML = `
                <h6 class="p-2 bg-light mb-0">
                    <i class="${categories[category].icon}"></i> ${categories[category].title}
                </h6>
            `;
            
            const itemsList = document.createElement('ul');
            itemsList.className = 'list-group list-group-flush';
            
            items.forEach(item => {
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item';
                
                let content = '';
                
                if (category === 'customers') {
                    content = `
                        <a href="${item.url}" class="text-decoration-none">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${item.name}</strong>
                                    <span class="badge ${item.type === 'business' ? 'bg-primary' : 'bg-info'} ms-2">
                                        ${item.type === 'business' ? 'Geschäft' : 'Privat'}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                            <div class="small text-muted">
                                ${item.email ? `<i class="fas fa-envelope me-1"></i>${item.email}` : ''}
                                ${item.phone ? `<i class="fas fa-phone ms-2 me-1"></i>${item.phone}` : ''}
                            </div>
                        </a>
                    `;
                } else if (category === 'appointments') {
                    content = `
                        <a href="${item.url}" class="text-decoration-none">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${item.title}</strong>
                                    <span class="badge ${getStatusBadgeClass(item.status)} ms-2">
                                        ${item.status}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-user me-1"></i>${item.customer}
                                <i class="fas fa-clock ms-2 me-1"></i>${item.date}
                            </div>
                        </a>
                    `;
                } else if (category === 'services') {
                    content = `
                        <a href="${item.url}" class="text-decoration-none">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${item.description}</strong>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-calendar-alt me-1"></i>${item.appointment_title}
                                <i class="fas fa-user ms-2 me-1"></i>${item.customer}
                            </div>
                        </a>
                    `;
                }
                
                listItem.innerHTML = content;
                itemsList.appendChild(listItem);
            });
            
            categoryEl.appendChild(itemsList);
            searchResultsContainer.appendChild(categoryEl);
        }
    }
    
    // Status-Badge-Klasse ermitteln
    function getStatusBadgeClass(status) {
        switch(status) {
            case 'geplant': return 'bg-primary';
            case 'durchgeführt': return 'bg-success';
            case 'abgesagt': return 'bg-danger';
            case 'verschoben': return 'bg-warning';
            default: return 'bg-secondary';
        }
    }
    
    // Suche ausführen
    function performSearch(query) {
        if (!query || query.length < 2) {
            if (searchResultsContainer) {
                searchResultsContainer.innerHTML = '';
            }
            if (searchOverlay) {
                searchOverlay.style.display = 'none';
            }
            return;
        }
        
        fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSearchResults(data);
                    if (searchOverlay) {
                        searchOverlay.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Fehler bei der Suche:', error);
            });
    }
    
    // Event-Listener für Sucheingabe
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        const query = this.value.trim();
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Event-Listener für Klick auf den Overlay (um die Suche zu schließen)
    if (searchOverlay) {
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    }
    
    // Event-Listener für Escape-Taste
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && searchOverlay) {
            searchOverlay.style.display = 'none';
        }
    });
});