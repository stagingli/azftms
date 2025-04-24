<?php
/**
 * fahrer_modal.php
 * Optimiertes, modulares Modal zur Bearbeitung von Fahrtdetails.
 * Wird sowohl vom Dashboard als auch von der Fahrtenübersicht verwendet.
 */
?>
<div class="modal fade" id="fahrerDetailsModal" tabindex="-1" aria-labelledby="fahrerDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <!-- Modal Header -->
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="fahrerDetailsModalLabel">
          <i class="fas fa-car-alt me-2"></i> Fahrtdetails
          <span id="fahrt_id_display" class="badge bg-light text-primary ms-2">ID: </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      
      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Formular mit korrektem action-Attribut für den POST-Request -->
        <form id="fahrtDatenForm" method="post" action="/fahrer/update_fahrt.php">
          <input type="hidden" id="fahrt_id" name="fahrt_id">
          <div class="container-fluid">
            <!-- Statusanzeige -->
            <div id="fahrt_status_container" class="alert alert-info d-flex align-items-center mb-3">
              <i class="fas fa-info-circle fs-4 me-2"></i>
              <div>
                <strong id="fahrt_status">Anstehend</strong>
                <span id="fahrt_zeit_info" class="ms-2"></span>
              </div>
              <div class="ms-auto">
                <span id="fahrt_countdown" class="badge bg-primary d-none"></span>
              </div>
            </div>
            
            <!-- Cards: Kunde, Route, Termin, Fahrzeug & Personen, Flug-Informationen -->
            <div class="row g-3 mb-4">
              <!-- Kunde Card -->
              <div class="col-md-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i> Kunde</h6>
                    <!-- Wird nur angezeigt, wenn Firmenkunde -->
                    <span id="kunde_typ" class="badge bg-info d-none">Firma</span>
                  </div>
                  <div class="card-body">
                    <div class="mb-2"><strong id="kunde">–</strong></div>
                    <!-- Ansprechpartner: Nur sichtbar bei Firmenkunden -->
                    <div id="ansprechpartner_container" class="mb-2 d-none">
                      <small class="text-muted">Ansprechpartner:</small>
                      <span id="ansprechpartner">–</span>
                    </div>
                    <div class="customer-contact mt-3">
                      <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-phone-alt me-2 text-primary"></i>
                        <a id="kunde_telefon" href="tel:">–</a>
                      </div>
                      <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-mobile-alt me-2 text-primary"></i>
                        <a id="kunde_mobil" href="tel:">–</a>
                      </div>
                      <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-envelope me-2 text-primary"></i>
                        <span id="kunde_email">–</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Route Card -->
              <div class="col-md-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i> Route</h6>
                  </div>
                  <div class="card-body">
                    <div class="route-details">
                      <div class="d-flex align-items-start mb-3">
                        <div class="route-icon bg-danger text-white rounded-circle p-2 me-2">
                          <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                          <div class="text-muted small mb-1">Abholort:</div>
                          <div id="abholort" class="fw-bold">–</div>
                        </div>
                      </div>
                      <div class="d-flex align-items-start mb-3">
                        <div class="route-icon bg-success text-white rounded-circle p-2 me-2">
                          <i class="fas fa-map-pin"></i>
                        </div>
                        <div>
                          <div class="text-muted small mb-1">Zielort:</div>
                          <div id="zielort" class="fw-bold">–</div>
                        </div>
                      </div>
                      <a href="#" id="navigation_link" class="btn btn-success btn-sm mt-2 w-100" target="_blank">
                        <i class="fas fa-directions me-1"></i> Navigation starten
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Termin Card -->
              <div class="col-md-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Zeitplan</h6>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Datum:</div>
                          <div id="abholdatum" class="fw-bold">–</div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Abholzeit:</div>
                          <div id="abfahrtszeit" class="fw-bold">–</div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Hinweis auf Rückfahrt, falls vorhanden -->
                    <div id="hinfahrt_container" class="alert alert-info py-2 small mt-2 mb-0 d-none">
                      <i class="fas fa-exchange-alt me-1"></i>
                      <strong>Rückfahrt:</strong>
                      <span id="hinfahrt_info"></span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Fahrzeug & Personen Card -->
              <div class="col-md-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-car-side me-2"></i> Fahrzeug & Personen</h6>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Fahrzeug:</div>
                          <div id="fahrzeug" class="fw-bold">–</div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Personen:</div>
                          <div id="personenanzahl" class="fw-bold">–</div>
                        </div>
                      </div>
                    </div>
                    <div class="mb-3">
                      <div class="text-muted small mb-1">Zusatzequipment:</div>
                      <div id="equipment_badges" class="d-flex flex-wrap mt-1">
                        <span class="text-muted">Keine Zusatzausstattung</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Flug-Informationen Card -->
              <div class="col-md-6">
                <div class="card shadow-sm h-100" id="flugCard">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-plane me-2"></i> Flug-Informationen</h6>
                  </div>
                  <div class="card-body">
                    <div class="mb-3">
                      <div class="text-muted small mb-1">Flugnummer:</div>
                      <div id="flugnummer" class="fw-bold">–</div>
                    </div>
                    <div class="mb-3">
                      <div class="text-muted small mb-1">Status:</div>
                      <div id="flugstatus" class="fw-bold">–</div>
                    </div>
                    <div id="erweiterter_flugstatus" class="d-none mt-3 pt-2 border-top">
                      <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Geplant:</small>
                        <span id="flightScheduledTime">–</span>
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                        <small class="text-muted">Aktuell:</small>
                        <span id="flightActualTime">–</span>
                      </div>
                      <div class="d-flex justify-content-between">
                        <small class="text-muted">Verspätung:</small>
                        <span id="flightDelay" class="badge bg-warning text-dark">–</span>
                      </div>
                    </div>
                    <div id="kein_flug_container" class="alert alert-light small py-2 mt-2 mb-0 d-none">
                      <i class="fas fa-info-circle me-1"></i>
                      <span id="kein_flug_message">Keine Fluginformationen verfügbar</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Fahrtdaten & Aktionsbuttons Card -->
              <div class="col-md-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i> Fahrtdaten</h6>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Beginn:</div>
                          <div id="fahrzeit_von_display" class="fw-bold">–</div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Ende:</div>
                          <div id="fahrzeit_bis_display" class="fw-bold">–</div>
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Wartezeit:</div>
                          <div id="wartezeit_display" class="fw-bold">–</div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <div class="text-muted small mb-1">Ausgaben:</div>
                          <div id="ausgaben_display" class="fw-bold">–</div>
                        </div>
                      </div>
                    </div>
                    <div id="fahrtaktionen_container" class="d-flex gap-2 mt-2">
                      <button type="button" id="modalStartFahrtBtn" class="btn btn-success d-none w-100">
                        <i class="fas fa-play me-1"></i> Fahrt starten
                      </button>
                      <button type="button" id="modalEndFahrtBtn" class="btn btn-danger d-none w-100">
                        <i class="fas fa-flag-checkered me-1"></i> Fahrt beenden
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Notizen / Bemerkungen Card -->
              <div class="col-md-12">
                <div class="card shadow-sm">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-comments me-2"></i> Bemerkungen</h6>
                  </div>
                  <div class="card-body">
                    <div class="accordion" id="accordionNotes">
                      <!-- Disposition Bemerkung -->
                      <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDispo">
                          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDispo" aria-expanded="false" aria-controls="collapseDispo">
                            <i class="fas fa-clipboard-list me-2"></i> Disposition
                          </button>
                        </h2>
                        <div id="collapseDispo" class="accordion-collapse collapse" aria-labelledby="headingDispo" data-bs-parent="#accordionNotes">
                          <div class="accordion-body">
                            <p id="dispo_bemerkung" class="mb-0">Keine Anweisungen von der Disposition.</p>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Kunden Bemerkung -->
                      <div class="accordion-item">
                        <h2 class="accordion-header" id="headingKunde">
                          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseKunde" aria-expanded="false" aria-controls="collapseKunde">
                            <i class="fas fa-user-tag me-2"></i> Kunde
                          </button>
                        </h2>
                        <div id="collapseKunde" class="accordion-collapse collapse" aria-labelledby="headingKunde" data-bs-parent="#accordionNotes">
                          <div class="accordion-body">
                            <p id="kunde_bemerkung" class="mb-0">Keine besonderen Hinweise zum Kunden.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div><!-- /row -->
            
            <!-- Bearbeitungsbereich -->
            <h5 class="mt-4 mb-3">Fahrtdetails bearbeiten</h5>
            <div class="row g-3 mb-3">
              <div class="col-md-6 col-lg-3">
                <label for="fahrzeit_von" class="form-label">Fahrzeit von</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-clock"></i></span>
                  <input type="time" class="form-control" name="fahrzeit_von" id="fahrzeit_von">
                </div>
              </div>
              <div class="col-md-6 col-lg-3">
                <label for="fahrzeit_bis" class="form-label">Fahrzeit bis</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-clock"></i></span>
                  <input type="time" class="form-control" name="fahrzeit_bis" id="fahrzeit_bis">
                </div>
              </div>
              <div class="col-md-6 col-lg-3">
                <label for="ausgaben" class="form-label">Ausgaben (€)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-euro-sign"></i></span>
                  <input type="number" step="0.01" class="form-control" name="ausgaben" id="ausgaben" placeholder="0.00">
                  <span class="input-group-text">€</span>
                </div>
              </div>
              <div class="col-md-6 col-lg-3">
                <label for="wartezeit" class="form-label">Wartezeit (Min)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-hourglass-half"></i></span>
                  <input type="number" class="form-control" name="wartezeit" id="wartezeit" placeholder="0">
                  <span class="input-group-text">Min</span>
                </div>
              </div>
              <div class="col-12">
                <label for="fahrer_bemerkung_input" class="form-label">Ihre Bemerkungen</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-pen"></i></span>
                  <textarea class="form-control" name="fahrer_bemerkung" id="fahrer_bemerkung_input" rows="2" placeholder="Geben Sie hier Ihre Notizen zur Fahrt ein..."></textarea>
                </div>
              </div>
            </div>
            
            <!-- Lohnvorschau -->
            <div class="card shadow-sm mt-4">
              <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-calculator me-2"></i> Lohnberechnung</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 mb-3 mb-md-0">
                    <div class="text-muted mb-2">Grundlage:</div>
                    <div class="mb-1">
                      <i class="fas fa-euro-sign me-1"></i> Stundenlohn: 
                      <strong><span id="stundenlohn_display">12,82</span> €</strong>
                    </div>
                    <div>
                      <i class="fas fa-info-circle me-1"></i> Berechnung: 
                      <span id="calc_explanation" class="text-muted">Wird nach Eingabe berechnet</span>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="text-muted mb-2">Zusammenfassung:</div>
                    <div class="alert alert-light py-2">
                      <div class="d-flex justify-content-between">
                        <div>Fahrtlohn:</div>
                        <strong><span id="fahrtlohn_preview">0,00</span> €</strong>
                      </div>
                      <div class="d-flex justify-content-between">
                        <div>+ Ausgaben:</div>
                        <strong><span id="ausgaben_preview">0,00</span> €</strong>
                      </div>
                      <hr class="my-1">
                      <div class="d-flex justify-content-between fw-bold">
                        <div>= Auszahlung:</div>
                        <strong><span id="gesamtbetrag_preview">0,00</span> €</strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
          </div><!-- /container -->
        </form>
      </div><!-- /modal-body -->
      
      <!-- Modal Footer -->
      <div class="modal-footer d-flex flex-wrap">
        <div class="me-auto">
          <a href="#" id="navigation_button" class="btn btn-success me-2" target="_blank">
            <i class="fas fa-directions me-1"></i> Navigation
          </a>
          <button type="button" id="startFahrtBtn" class="btn btn-outline-primary me-2 d-none">
            <i class="fas fa-play me-1"></i> Starten
          </button>
          <button type="button" id="endFahrtBtn" class="btn btn-outline-danger me-2 d-none">
            <i class="fas fa-flag-checkered me-1"></i> Beenden
          </button>
        </div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times-circle me-1"></i> Schließen
        </button>
        <button type="submit" form="fahrtDatenForm" class="btn btn-primary ms-2">
          <i class="fas fa-save me-1"></i> Speichern
        </button>
      </div>
    </div><!-- /modal-content -->
  </div><!-- /modal-dialog -->
</div><!-- /modal -->

<!-- Stil-Anpassungen -->
<style>
  /* Optimierungen für das Modal */
  #fahrerDetailsModal .route-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  #fahrerDetailsModal .badge {
    font-weight: normal;
  }
  #fahrerDetailsModal .card {
    overflow: hidden;
  }
  #fahrerDetailsModal .card-header {
    font-weight: 600;
  }
  
  /* Mobile Optimierungen */
  @media (max-width: 767.98px) {
    #fahrerDetailsModal .modal-body { padding: 1rem; }
    #fahrerDetailsModal .card { margin-bottom: 0.5rem; }
    #fahrerDetailsModal .modal-footer { flex-direction: column; }
    #fahrerDetailsModal .modal-footer > div,
    #fahrerDetailsModal .modal-footer button {
      width: 100%;
      margin: 0.25rem 0 !important;
    }
    #fahrerDetailsModal .d-flex { flex-wrap: wrap; }
  }
  
  @media (max-width: 575.98px) {
    #fahrerDetailsModal .modal-header h5,
    #fahrerDetailsModal h5,
    #fahrerDetailsModal h6 { font-size: 0.9rem; }
    #fahrerDetailsModal .card-body { padding: 0.75rem; }
    #fahrerDetailsModal .modal-footer button { font-size: 0.9rem; }
  }
</style>

<!-- JavaScript für das Modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ========== Modal Elemente und Event Listener ==========
    
    // Modal-Elemente
    const modalElements = {
        modal: document.getElementById('fahrerDetailsModal'),
        form: document.getElementById('fahrtDatenForm'),
        fahrtId: document.getElementById('fahrt_id'),
        fahrzeitVon: document.getElementById('fahrzeit_von'),
        fahrzeitBis: document.getElementById('fahrzeit_bis'),
        ausgaben: document.getElementById('ausgaben'),
        wartezeit: document.getElementById('wartezeit'),
        fahrerBemerkung: document.getElementById('fahrer_bemerkung_input'),
        startBtn: document.getElementById('modalStartFahrtBtn'),
        endBtn: document.getElementById('modalEndFahrtBtn'),
        navigationBtn: document.getElementById('navigation_button'),
        // Status-Anzeigen
        fahrtlohnPreview: document.getElementById('fahrtlohn_preview'),
        ausgabenPreview: document.getElementById('ausgaben_preview'),
        gesamtbetragPreview: document.getElementById('gesamtbetrag_preview'),
        calcExplanation: document.getElementById('calc_explanation'),
        stundenlohnDisplay: document.getElementById('stundenlohn_display')
    };
    
    // Stundenlohn setzen (wird in PHP definiert)
    if (modalElements.stundenlohnDisplay) {
      modalElements.stundenlohnDisplay.textContent = formatCurrency(DRIVER_WAGE || 12.82, false);
        }
    
    // Event-Listener für Modal-Öffnen-Buttons
    document.querySelectorAll('.open-fahrt-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            openFahrerModal(this.dataset);
            
            // Bootstrap Modal öffnen
            const modalEl = document.getElementById('fahrerDetailsModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    });
    
    // Fahrt Starten/Beenden Buttons im Modal
    if (modalElements.startBtn) {
        modalElements.startBtn.addEventListener('click', function() {
            startFahrt(modalElements.fahrtId.value);
        });
    }
    
    if (modalElements.endBtn) {
        modalElements.endBtn.addEventListener('click', function() {
            endFahrt(modalElements.fahrtId.value);
        });
    }
    
    // Berechnung der Lohnvorschau bei Änderungen an den relevanten Formularfeldern
    ['fahrzeit_von', 'fahrzeit_bis', 'wartezeit', 'ausgaben'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', computeFahrtLohn);
            el.addEventListener('change', computeFahrtLohn);
        }
    });
    
    // Modal-Events
    if (modalElements.modal) {
        modalElements.modal.addEventListener('shown.bs.modal', function() {
            setTimeout(computeFahrtLohn, 200);
        });
    }
    
    // ========== Funktionen ==========
    
    /**
     * Öffnet das Fahrer-Modal und weist die übergebenen Daten den entsprechenden Elementen zu.
     * @param {object} data - Objekt mit allen benötigten Daten.
     */
    function openFahrerModal(data) {
        console.log("Modal öffnen mit Daten:", data);
        
        // Fahrt-ID setzen
        assignElement('fahrt_id_display', 'ID: ' + (data.id || ''));
        modalElements.fahrtId.value = data.id || '';
        
        // Kunde
        if (data.isFirma === '1' && data.ansprechpartner) {
            document.getElementById('kunde').innerHTML = '<span class="badge bg-info me-1">Firma</span> ' + 
                data.kunde + ' <small class="text-muted">(Ansprechpartner: ' + data.ansprechpartner + ')</small>';
            document.getElementById('ansprechpartner_container').classList.remove('d-none');
        } else {
            assignElement('kunde', data.kunde);
            document.getElementById('ansprechpartner_container').classList.add('d-none');
        }
        
        // Kontaktdaten
        assignElement('kunde_telefon', data.telefon);
        var telEl = document.getElementById('kunde_telefon');
        if (telEl && data.telefon) {
            telEl.href = 'tel:' + data.telefon;
        }
        
        assignElement('kunde_mobil', data.mobil);
        var mobilEl = document.getElementById('kunde_mobil');
        if (mobilEl && data.mobil) {
            mobilEl.href = 'tel:' + data.mobil;
        }
        
        assignElement('kunde_email', data.email);
        
        // Route
        assignElement('abholort', data.abholort);
        assignElement('zielort', data.zielort);
        document.querySelectorAll('#navigation_link, #navigation_button').forEach(function(link) {
            link.href = 'https://www.google.com/maps/dir/?api=1&origin=' + 
                encodeURIComponent(data.abholort || '') + '&destination=' + encodeURIComponent(data.zielort || '');
        });
        
        // Termin
        assignElement('abholdatum', formatDate(data.abholdatum));
        
        // Format abfahrtszeit in HH:MM (ohne "Uhr")
        if (data.abfahrtszeit) {
            var abfahrtsTime = data.abfahrtszeit.substring(0,5);
            assignElement('abfahrtszeit', abfahrtsTime);
        } else {
            assignElement('abfahrtszeit', data.abfahrtszeit);
        }
        
        // Fahrzeug & Personen
        assignElement('fahrzeug', data.fahrzeug);
        assignElement('personenanzahl', data.personenanzahl);
        
        // Zusatzausstattung
        createEquipmentBadges(data.zusatzequipment);
        
        // Flug-Informationen
        assignElement('flugnummer', data.flugnummer);
        assignElement('flugstatus', 'Wird abgefragt...');
        
        // Fahrtdaten
        assignElement('fahrzeit_von', data.fahrzeitVon);
        assignElement('fahrzeit_bis', data.fahrzeitBis);
        assignElement('fahrzeit_von_display', data.fahrzeitVon ? data.fahrzeitVon.substring(0,5) : '–');
        assignElement('fahrzeit_bis_display', data.fahrzeitBis ? data.fahrzeitBis.substring(0,5) : '–');
        assignElement('wartezeit', data.wartezeit);
        assignElement('wartezeit_display', data.wartezeit ? (data.wartezeit + ' Min') : '–');
        assignElement('ausgaben', data.ausgaben);
        assignElement('ausgaben_display', data.ausgaben ? formatCurrency(data.ausgaben) : '–');
        
        // Status und Aktionen
        updateFahrtStatus(data);
        
        // Notizen / Bemerkungen
        assignElement('dispo_bemerkung', data.dispoBemerkung || 'Keine Anweisungen von der Disposition.');
        assignElement('kunde_bemerkung', data.kundeBemerkung || 'Keine besonderen Hinweise zum Kunden.');
        assignElement('fahrer_bemerkung_input', data.fahrerBemerkung || '');
        
        // Flugstatus abfragen, falls Flugnummer vorhanden
        if (data.flugnummer && data.flugnummer.trim() !== '' && data.flugnummer.trim() !== '–') {
            fetchFlightStatus(data.flugnummer, data.abholdatum, data.abholort, data.zielort);
        } else {
            assignElement('flugstatus', 'Keine Flugnummer angegeben');
        }
        
        // Lohn neu berechnen
        computeFahrtLohn();
    }
    
    /**
     * Aktualisiert die Anzeige des Fahrtstatus im Modal basierend auf den Fahrtdaten.
     * @param {object} data Die Fahrtdaten
     */
    function updateFahrtStatus(data) {
        const statusContainer = document.getElementById('fahrt_status_container');
        const statusEl = document.getElementById('fahrt_status');
        const zeitInfoEl = document.getElementById('fahrt_zeit_info');
        const countdownEl = document.getElementById('fahrt_countdown');
        
        if (!statusContainer || !statusEl) return;
        
        // Status-Text und Icon
        let statusText = data.status || 'Anstehend';
        let statusColor = data.statusColor || 'info';
        let zeitInfo = '';
        
        // Status-Container-Klasse setzen
        statusContainer.className = 'alert alert-' + statusColor + ' d-flex align-items-center mb-3';
        
        // Start/Ende-Buttons anzeigen/verstecken basierend auf Status
        if (modalElements.startBtn && modalElements.endBtn) {
            if (data.istHeute === '1' && !data.fahrzeitVon) {
                modalElements.startBtn.classList.remove('d-none');
                modalElements.endBtn.classList.add('d-none');
            } else if (data.fahrzeitVon && !data.fahrzeitBis) {
                modalElements.startBtn.classList.add('d-none');
                modalElements.endBtn.classList.remove('d-none');
            } else {
                modalElements.startBtn.classList.add('d-none');
                modalElements.endBtn.classList.add('d-none');
            }
        }
        
        // Status-Text setzen
        statusEl.textContent = statusText;
        
        // Zeit-Info basierend auf Status
        const heute = new Date().toISOString().split('T')[0];
        const fahrtDatum = data.abholdatum;
        
        if (fahrtDatum < heute) {
            zeitInfo = 'Vergangene Fahrt';
            countdownEl.classList.add('d-none');
        } else if (fahrtDatum > heute) {
            // Datum formatieren
            const date = new Date(fahrtDatum);
            const options = { weekday: 'long', day: 'numeric', month: 'long' };
            zeitInfo = 'Geplant für ' + date.toLocaleDateString('de-DE', options);
            countdownEl.classList.add('d-none');
        } else {
            // Heute
            if (data.fahrzeitBis) {
                zeitInfo = 'Abgeschlossen um ' + data.fahrzeitBis.substring(0,5) + ' Uhr';
                countdownEl.classList.add('d-none');
            } else if (data.fahrzeitVon) {
                zeitInfo = 'Gestartet um ' + data.fahrzeitVon.substring(0,5) + ' Uhr';
                
                // Laufzeit anzeigen
                const startTime = new Date('1970-01-01T' + data.fahrzeitVon);
                const now = new Date();
                const diffMs = now - startTime;
                const diffHrs = Math.floor(diffMs / 3600000);
                const diffMins = Math.floor((diffMs % 3600000) / 60000);
                
                countdownEl.textContent = 'Laufzeit: ' + 
                    (diffHrs > 0 ? diffHrs + ' Std ' : '') + 
                    diffMins + ' Min';
                countdownEl.classList.remove('d-none');
            } else {
                // Heute, noch nicht gestartet
                const now = new Date();
                const departTime = new Date('1970-01-01T' + data.abfahrtszeit);
                
                if (departTime < now) {
                    zeitInfo = 'Überfällig seit ' + data.abfahrtszeit.substring(0,5) + ' Uhr';
                    countdownEl.classList.add('d-none');
                } else {
                    // Countdown berechnen
                    const diffMs = departTime - now;
                    const diffHrs = Math.floor(diffMs / 3600000);
                    const diffMins = Math.floor((diffMs % 3600000) / 60000);
                    
                    zeitInfo = 'Abfahrt heute um ' + data.abfahrtszeit.substring(0,5) + ' Uhr';
                    countdownEl.textContent = 'In ' + 
                        (diffHrs > 0 ? diffHrs + ' Std ' : '') + 
                        diffMins + ' Min';
                    countdownEl.classList.remove('d-none');
                    
                    // Countdown aktualisieren
                    if (window.countdownInterval) clearInterval(window.countdownInterval);
                    window.countdownInterval = setInterval(function() {
                        const now = new Date();
                        const diffMs = departTime - now;
                        
                        if (diffMs <= 0) {
                            countdownEl.textContent = 'Jetzt!';
                            clearInterval(window.countdownInterval);
                            return;
                        }
                        
                        const diffHrs = Math.floor(diffMs / 3600000);
                        const diffMins = Math.floor((diffMs % 3600000) / 60000);
                        
                        countdownEl.textContent = 'In ' + 
                            (diffHrs > 0 ? diffHrs + ' Std ' : '') + 
                            diffMins + ' Min';
                    }, 60000); // Jede Minute aktualisieren
                }
            }
        }
        
        zeitInfoEl.textContent = zeitInfo;
    }
    
    /**
     * Berechnet den Lohn für die aktuelle Fahrt basierend auf den eingegebenen Zeiten.
     */
    function computeFahrtLohn() {
        if (!modalElements.fahrzeitVon || 
            !modalElements.fahrzeitBis || 
            !modalElements.ausgaben ||
            !modalElements.fahrtlohnPreview ||
            !modalElements.ausgabenPreview ||
            !modalElements.gesamtbetragPreview) {
            return;
        }
        
        const fahrzeitVon = modalElements.fahrzeitVon.value;
        const fahrzeitBis = modalElements.fahrzeitBis.value;
        const ausgaben = parseFloat(modalElements.ausgaben.value) || 0;
        const wartezeit = parseInt(modalElements.wartezeit.value) || 0;
        
        let fahrtlohn = 0;
        let explanation = '';
        
        if (fahrzeitVon && fahrzeitBis) {
            // Zeiten parsen
            const start = new Date('1970-01-01T' + fahrzeitVon);
            let end = new Date('1970-01-01T' + fahrzeitBis);
            
            // Falls die Endzeit vor der Startzeit liegt (über Mitternacht)
            if (end < start) {
                end = new Date('1970-01-02T' + fahrzeitBis);
            }
            
            // Differenz in Minuten berechnen
            const diffMinutes = (end - start) / 60000;
            
            // Wartezeit hinzuaddieren (falls vorhanden)
            const totalMinutes = diffMinutes + wartezeit;
            
            // Umrechnung in Stunden
            const hours = totalMinutes / 60;
            
            // Stundenlohn verwenden (aus Window-Variable oder Standard)
            const stundenlohn = DRIVER_WAGE || 12.82;
                        
            // Lohn berechnen
            fahrtlohn = hours * stundenlohn;
            
            // Erklärung generieren
            explanation = `${hours.toFixed(2)} Std × ${formatCurrency(stundenlohn, false)} €/h`;
            
            if (wartezeit > 0) {
                explanation += ` (inkl. ${wartezeit} Min Wartezeit)`;
            }
        } else {
            explanation = 'Wird nach Eingabe berechnet';
        }
        
        // Werte in die Vorschau eintragen
        modalElements.fahrtlohnPreview.textContent = formatCurrency(fahrtlohn, false);
        modalElements.ausgabenPreview.textContent = formatCurrency(ausgaben, false);
        modalElements.gesamtbetragPreview.textContent = formatCurrency(fahrtlohn + ausgaben, false);
        modalElements.calcExplanation.textContent = explanation;
    }
    
    /**
     * Startet eine Fahrt durch Erfassung der Startzeit.
     * @param {string} fahrtId ID der Fahrt
     */
    function startFahrt(fahrtId) {
        if (!fahrtId) return;
        
        const jetzt = new Date();
        const zeit = jetzt.getHours().toString().padStart(2, '0') + ':' + 
                     jetzt.getMinutes().toString().padStart(2, '0');
        
        const formData = new FormData();
        formData.append('fahrt_id', fahrtId);
        formData.append('fahrzeit_von', zeit);
        formData.append('action', 'start_fahrt');
        
        fetch('/fahrer/update_fahrt.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            alert('Fehler: ' + error.message);
        });
    }
    
    /**
     * Beendet eine Fahrt durch Erfassung der Endzeit.
     * @param {string} fahrtId ID der Fahrt
     */
    function endFahrt(fahrtId) {
        if (!fahrtId) return;
        
        const jetzt = new Date();
        const zeit = jetzt.getHours().toString().padStart(2, '0') + ':' + 
                     jetzt.getMinutes().toString().padStart(2, '0');
        
        const formData = new FormData();
        formData.append('fahrt_id', fahrtId);
        formData.append('fahrzeit_bis', zeit);
        formData.append('action', 'end_fahrt');
        
        fetch('/fahrer/update_fahrt.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            alert('Fehler: ' + error.message);
        });
    }
    
    /**
     * Ruft den Flugstatus für eine Flugnummer ab.
     * @param {string} flightNumber Die Flugnummer
     * @param {string} date Das Datum im Format YYYY-MM-DD
     * @param {string} abholort Der Abholort
     * @param {string} zielort Der Zielort
     */
    function fetchFlightStatus(flightNumber, date, abholort, zielort) {
        if (!flightNumber || flightNumber === '–') {
            assignElement('flugstatus', 'Keine Flugnummer');
            return;
        }
        
        // Abfragetyp bestimmen (Ankunft/Abflug)
        const checkAirport = (str) => {
            str = (str || '').toLowerCase();
            return str.includes('flughafen') || str.includes('airport');
        };
        
        let flightType = '';
        if (checkAirport(abholort)) {
            flightType = 'arrivals';
        } else if (checkAirport(zielort)) {
            flightType = 'departures';
        }
        
        if (!flightType) {
            assignElement('flugstatus', 'Kein Flughafen erkannt');
            return;
        }
        
        assignElement('flugstatus', 'Daten werden abgefragt...');
        
        // API-Aufruf
        fetch(`/fahrer/flugstatus_proxy.php?type=${flightType}&from=${date}&to=${date}&flightNumber=${encodeURIComponent(flightNumber)}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    assignElement('flugstatus', 'Keine Daten (' + data.message + ')');
                    return;
                }
                
                let flight = null;
                
                if (Array.isArray(data.flights) && data.flights.length > 0) {
                    flight = data.flights[0];
                } else if (Array.isArray(data.data) && data.data.length > 0) {
                    flight = data.data[0];
                } else {
                    const numericKeys = Object.keys(data).filter(k => !isNaN(Number(k)));
                    if (numericKeys.length > 0) {
                        flight = data[numericKeys[0]];
                    }
                }
                
                if (!flight) {
                    assignElement('flugstatus', 'Keine Flugdaten gefunden');
                    return;
                }
                
                const planned = flight.plannedDepartureTime || flight.plannedArrivalTime || '';
                const expected = flight.expectedDepartureTime || flight.expectedArrivalTime || '';
                const plannedDate = parseFlightDateTime(planned);
                const expectedDate = parseFlightDateTime(expected);
                
                let diffMins = 0;
                if (plannedDate && expectedDate) {
                    diffMins = Math.round((expectedDate - plannedDate) / 60000);
                }
                
                let flightStatusText = (plannedDate && expectedDate && diffMins > 10) ? 'Verspätet' : 'Pünktlich';
                
                if (flight.flightStatusDeparture) {
                    flightStatusText = interpretFlightStatus(flight.flightStatusDeparture);
                } else if (flight.flightStatusArrival) {
                    flightStatusText = interpretFlightStatus(flight.flightStatusArrival);
                }
                
                assignElement('flugstatus', flightStatusText);
                
                // Erweiterten Flugstatus anzeigen
                const erweiterterStatus = document.getElementById('erweiterter_flugstatus');
                if (erweiterterStatus) {
                    erweiterterStatus.classList.remove('d-none');
                    
                    const scheduled = document.getElementById('flightScheduledTime');
                    if (scheduled && plannedDate) {
                        scheduled.textContent = formatFlightDateTime(plannedDate);
                    }
                    
                    const actual = document.getElementById('flightActualTime');
                    if (actual && expectedDate) {
                        actual.textContent = formatFlightDateTime(expectedDate);
                    }
                    
                    const delay = document.getElementById('flightDelay');
                    if (delay) {
                        if (diffMins > 0) {
                            delay.textContent = '+' + diffMins + ' Min';
                            delay.className = 'badge bg-warning text-dark';
                        } else if (diffMins < 0) {
                            delay.textContent = diffMins + ' Min';
                            delay.className = 'badge bg-success';
                        } else {
                            delay.textContent = 'Pünktlich';
                            delay.className = 'badge bg-success';
                        }
                    }
                }
            })
            .catch(err => {
                assignElement('flugstatus', 'Fehler: ' + err.message);
            });
    }
    
    // ========== Hilfsfunktionen ==========
    
    /**
     * Setzt den Text oder den Value eines Elements anhand seiner ID.
     * @param {string} id - Die Element-ID.
     * @param {string} val - Der zu setzende Wert.
     */
    function assignElement(id, val) {
        const el = document.getElementById(id);
        if (el) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)) {
                el.value = val || '';
            } else {
                el.textContent = val || '–';
            }
        }
    }
    
    /**
     * Formatiert ein Datum im deutschen Format.
     * @param {string} dateString - Das zu formatierende Datum.
     * @return {string} Das formatierte Datum.
     */
    function formatDate(dateString) {
        if (!dateString) return '–';
        
        const date = new Date(dateString);
        if (isNaN(date)) return dateString;
        
        const day = ('0' + date.getDate()).slice(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const year = date.getFullYear();
        
        return day + '.' + month + '.' + year;
    }
    
    /**
     * Formatiert einen Betrag als Währung.
     * @param {number} value - Der zu formatierende Betrag.
     * @param {boolean} withSymbol - Gibt an, ob das Währungssymbol angezeigt werden soll.
     * @return {string} Der formatierte Betrag.
     */
    function formatCurrency(value, withSymbol = true) {
        const num = parseFloat(value) || 0;
        const formatted = num.toFixed(2).replace('.', ',');
        return withSymbol ? formatted + ' €' : formatted;
    }
    
    /**
     * Wandelt einen Dezimalwert mit Komma oder Punkt in eine Zahl um.
     * @param {string} str - Die zu konvertierende Zeichenkette.
     * @return {number} Der konvertierte Wert.
     */
    function parseDecimalNumber(str) {
        if (!str) return 0;
        return parseFloat(String(str).replace(/\./g, '').replace(',', '.')) || 0;
    }
    
    /**
     * Formatiert eine Dauer in Minuten als Text.
     * @param {number} minutes - Die Dauer in Minuten.
     * @return {string} Die formatierte Dauer.
     */
    function formatDuration(minutes) {
        minutes = parseInt(minutes) || 0;
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return hours > 0 ? hours + ' Std ' + mins + ' Min' : mins + ' Min';
    }
    
    /**
     * Erstellt Badges für das Zusatzequipment.
     * @param {string} equipmentString - JSON-String oder kommaseparierte Liste des Equipments.
     */
    function createEquipmentBadges(equipmentString) {
        const container = document.getElementById('equipment_badges');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (!equipmentString || equipmentString === '–') {
            container.innerHTML = '<span class="text-muted">Keine Zusatzausstattung</span>';
            return;
        }
        
        let items = [];
        try {
            const parsed = JSON.parse(equipmentString);
            items = Array.isArray(parsed) ? parsed : [equipmentString];
        } catch (e) {
            items = equipmentString.split(',').map(item => item.trim());
        }
        
        items.forEach(item => {
            if (!item) return;
            const span = document.createElement('span');
            span.className = 'badge bg-secondary me-1 mb-1';
            span.textContent = item;
            container.appendChild(span);
        });
        
        if (container.innerHTML === '') {
            container.innerHTML = '<span class="text-muted">Keine Zusatzausstattung</span>';
        }
    }
    
    /**
     * Verarbeitet ein Flugdatum aus der API.
     * @param {string} dtString - Das zu verarbeitende Datum.
     * @return {Date|null} Das verarbeitete Datum oder null.
     */
    function parseFlightDateTime(dtString) {
        if (!dtString) return null;
        const cleaned = dtString.replace(/\[.*$/, '');
        const dateObj = new Date(cleaned);
        return isNaN(dateObj) ? null : dateObj;
    }
    
    /**
     * Formatiert ein Flugdatum für die Anzeige.
     * @param {Date} dateObj - Das zu formatierende Datum.
     * @return {string} Das formatierte Datum.
     */
    function formatFlightDateTime(dateObj) {
        if (!(dateObj instanceof Date)) return '–';
        
        const dd = String(dateObj.getDate()).padStart(2, '0');
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const yyyy = dateObj.getFullYear();
        const hh = String(dateObj.getHours()).padStart(2, '0');
        const min = String(dateObj.getMinutes()).padStart(2, '0');
        
        return `${dd}.${mm}.${yyyy} - ${hh}:${min}`;
    }
    
    /**
     * Interpretiert den Statuscode des Fluges.
     * @param {string} statusCode - Der zu interpretierende Statuscode.
     * @return {string} Der interpretierte Status.
     */
    function interpretFlightStatus(statusCode) {
        if (!statusCode) return 'Keine Status-Info';
        
        const code = String(statusCode).toUpperCase();
        
        switch (code) {
            case 'DEP': return 'Abgeflogen';
            case 'DEL': return 'Verspätet';
            case 'BRD': return 'Boarding';
            case 'ARR': return 'Gelandet';
            case 'DIV': return 'Umgeleitet';
            case 'CNL': return 'Gestrichen';
            default: return statusCode;
        }
    }
});
</script>