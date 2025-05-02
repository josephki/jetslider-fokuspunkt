<?php
/**
 * Plugin Name: JetSlider Fokuspunkt
 * Plugin URI: https://github.com/josephki/jetslider-fokuspunkt
 * Description: Fügt Bildpositions-Steuerung für JetSlider hinzu (oben, mitte, unten) und korrigiert Caching-Probleme in WordPress 6.8+
 * Version: 1.0.0
 * Author: Ihr Name
 * Author URI: https://web-werkstatt.at
 * Text Domain: jetslider-fokuspunkt
 * Domain Path: /languages
 * License: GPL2
 * GitHub Plugin URI: https://github.com/josephki/jetslider-fokuspunkt
 * Primary Branch: main
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Plugin-Update-Checker laden (falls vorhanden)
if (!class_exists('Puc_v4_Factory') && file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
}

// Plugin-Klasse definieren
class JetSlider_Fokuspunkt {
    
    // Plugin-Version
    private $version = '1.0.0';
    
    // Feld-Name für Slider-Bild
    private $field_name = 'slider_bild';
    
    // Seiten-ID, für die der Fix angewendet werden soll
    private $target_page_id = 6905; // HIER ANPASSEN: Spezifische Seiten-ID

    /**
     * Konstruktor: Hooks und Filter initialisieren
     */
    public function __construct() {
        // Admin-Bereich
        add_action('admin_head', array($this, 'add_focus_point_css'));
        add_action('admin_footer', array($this, 'add_focus_point_js'));
        add_action('wp_ajax_get_focal_point_data', array($this, 'get_focal_point_data_ajax'));
        add_action('wp_ajax_save_focal_point_position', array($this, 'save_focal_point_position_ajax'));
        add_action('save_post', array($this, 'save_focus_point_positions'), 10, 2);
        
        // Frontend
        add_action('wp_footer', array($this, 'fix_jetslider_position_script'), 999);
        add_filter('elementor/frontend/widget/before_render', array($this, 'add_jet_slider_fix_attributes'), 10, 2);
        
        // Cache-Management
        add_action('updated_post_meta', array($this, 'clear_position_cache_on_update'), 10, 4);
        add_action('save_post', array($this, 'clear_transient_cache_on_post_save'), 10, 3);
        add_filter('style_loader_src', array($this, 'add_cache_busting_parameter'), 10, 2);
        add_filter('script_loader_src', array($this, 'add_cache_busting_parameter'), 10, 2);
        
        // Optionaler Debug-Helper (auskommentiert)
        // add_action('wp_footer', array($this, 'debug_cache_info'), 999);
        
        // Plugin-Update-Checker initialisieren
        $this->setup_update_checker();
    }
    
    /**
     * Plugin-Update-Checker initialisieren
     */
    private function setup_update_checker() {
        if (class_exists('Puc_v4_Factory')) {
            $update_checker = Puc_v4_Factory::buildUpdateChecker(
                'https://github.com/josephki/jetslider-fokuspunkt/', // GitHub Repository URL
                __FILE__, // Hauptdatei des Plugins
                'jetslider-fokuspunkt' // Plugin-Slug
            );
            
            // Wenn Sie ein privates Repository verwenden, setzen Sie den Zugriffstoken
            // $update_checker->setAuthentication('your-token-here');
            
            // Den Branch setzen, der die stabile Version enthält
            $update_checker->setBranch('main');
        }
    }

    /**
     * CSS für die Fokuspunkt-UI im Backend hinzufügen
     */
    public function add_focus_point_css() {
        ?>
        <style>
        /* Wrapper für das gesamte Fokuspunkt-Interface */
        .focal-point-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: absolute;
            right: 10px;
            top: 10px;
            width: 180px;
            z-index: 10; /* Höherer z-index, um Überlappungsprobleme zu vermeiden */
        }
        
        /* Überschrift für den Picker */
        .focal-point-title {
            margin: 0 0 10px 0;
            padding: 0 0 5px 0;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            color: #23282d;
            border-bottom: 1px solid #eee;
            width: 100%;
        }
        
        /* Inhalt-Container für Picker und Buttons */
        .focal-point-content {
            display: flex;
            width: 100%;
        }
        
        /* Stil für den Picker selbst */
        .focal-point-picker {
            position: relative;
            width: 100px;
            height: 100px;
            cursor: crosshair;
            overflow: hidden;
            border: 1px solid #ddd;
            margin-right: 10px;
            flex-shrink: 0;
            border-radius: 3px;
        }
        
        .focal-point-picker img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .focal-point-picker .focal-point {
            position: absolute;
            width: 16px;
            height: 16px;
            margin-left: -8px;
            margin-top: -8px;
            border-radius: 50%;
            background: rgba(255, 0, 0, 0.5);
            border: 2px solid #fff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }
        
        /* Steuerelemente für die Buttons - FIXIERTER HÖHE */
        .focal-point-controls {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 3px;
            flex-grow: 1;
        }
        
        /* WICHTIG: Fixierte Höhe für die Buttons, um das Hüpfen zu verhindern */
        .focal-point-preset {
            padding: 6px 8px;
            background: #f7f7f7;
            border: 1px solid #ccc;
            cursor: pointer;
            text-align: center;
            margin: 0;
            border-radius: 3px;
            font-size: 12px;
            height: 32px; /* Fixierte Höhe für alle Buttons */
            line-height: 16px; /* Zentrierte Textausrichtung */
            box-sizing: border-box; /* Box-Modell fixieren */
            transition: background-color 0.2s ease, color 0.2s ease; /* NUR Farbe animieren, keine Größenänderung */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .focal-point-preset:hover {
            background: #ebebeb;
        }
        
        .focal-point-preset.active {
            background: #2271b1;
            color: white;
            border-color: #135e96;
            /* Kein unterschiedlicher padding, margin oder height für aktive Buttons */
        }
        
        /* Anpassung für das bestehende Crocoblock-Layout */
        .cx-ui-container {
            position: relative !important;
            min-height: 170px;
        }
        
        /* Fixierung für verschiedene JetEngine UI-Varianten */
        .jet-engine-meta-wrap {
            position: relative !important;
            min-height: 170px;
        }
        
        /* Info-Text unter den Buttons */
        .focal-point-info {
            font-size: 11px;
            color: #666;
            margin-top: 8px;
            text-align: center;
            font-style: italic;
        }
        
        /* Status-Anzeige für das Speichern */
        .focal-point-status {
            margin-top: 5px;
            font-size: 11px;
            color: #2e7d32; /* Grün für Erfolg */
            text-align: center;
            height: 15px; /* Fixierte Höhe, um Hüpfen zu verhindern */
            transition: opacity 0.5s ease;
            opacity: 0;
        }
        
        .focal-point-status.show {
            opacity: 1;
        }
        
        .focal-point-status.error {
            color: #c62828; /* Rot für Fehler */
        }
        </style>
        <?php
    }

    /**
     * JavaScript für die Fokuspunkt-UI im Backend hinzufügen
     */
    public function add_focus_point_js() {
        // Nur im Admin-Bereich auf Post-Bearbeitungsseiten ausführen
        global $pagenow;
        if (!is_admin() || ($pagenow !== 'post.php' && $pagenow !== 'post-new.php')) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Vermeiden, dass unsere AJAX-Anfragen die WordPress "Verbindung unterbrochen"-Meldung auslösen
            var originalHeartbeatSend = wp.heartbeat ? wp.heartbeat.send : null;
            
            // Funktion zur Initialisierung des Fokuspunkt-Pickers für ein Bild
            function initFocalPointPicker(mediaFieldName, titleText) {
                // Nach dem Media-Feld suchen
                var mediaField = $('input[name="' + mediaFieldName + '"]');
                if (!mediaField.length) {
                    return;
                }
                
                // Prüfen, ob bereits ein Picker existiert
                if (mediaField.siblings('.focal-point-wrapper').length) {
                    return;
                }
                
                // Den übergeordneten Container finden
                var mediaContainer = mediaField.closest('.jet-engine-meta-wrap, .cx-ui-container');
                if (!mediaContainer.length) {
                    return;
                }
                
                // Sicherstellen, dass der Container relative Positionierung hat
                mediaContainer.css('position', 'relative');
                
                // Bild-ID oder URL holen
                var mediaValue = mediaField.val();
                if (!mediaValue) {
                    return;
                }
                
                // Für Array-Format (JetEngine "Array with media ID and URL")
                var mediaId = '';
                try {
                    var mediaObj = JSON.parse(mediaValue);
                    if (mediaObj && mediaObj.id) {
                        mediaId = mediaObj.id;
                    }
                } catch(e) {
                    // Kein JSON, vermutlich ID oder URL direkt
                    mediaId = mediaValue;
                }
                
                // Post-ID abrufen
                var postId = $('#post_ID').val();
                
                // Direkt alle notwendigen Daten mit einer Anfrage holen (reduziert AJAX-Aufrufe)
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_focal_point_data',
                        post_id: postId,
                        field_name: mediaFieldName,
                        image_id: mediaId,
                        // Zufallszahl hinzufügen, um Cache-Probleme zu vermeiden
                        cache_buster: Math.random()
                    },
                    beforeSend: function() {
                        // Stelle sicher, dass Heartbeat läuft, um "Verbindung unterbrochen" zu vermeiden
                        if (originalHeartbeatSend) {
                            wp.heartbeat.connectNow();
                        }
                    },
                    success: function(response) {
                        if (!response.success) {
                            return;
                        }
                        
                        var imageUrl = response.data.image_url;
                        var savedPosition = response.data.position || 'middle';
                        
                        if (!imageUrl) {
                            return;
                        }
                        
                        // Position in X/Y-Koordinaten umrechnen
                        var focusX = 0.5, focusY = 0.5; // Standardwert: Mitte
                        
                        switch(savedPosition) {
                            case 'top':
                                focusX = 0.5;
                                focusY = 0;
                                break;
                            case 'middle':
                                focusX = 0.5;
                                focusY = 0.5;
                                break;
                            case 'bottom':
                                focusX = 0.5;
                                focusY = 1;
                                break;
                        }
                        
                        // Positionsfeld-Name
                        var positionFieldName = mediaFieldName + '_position';
                        
                        // HTML für den Fokuspunkt-Picker erstellen
                        var pickerHTML = 
                            '<div class="focal-point-wrapper">' +
                              '<h3 class="focal-point-title">' + titleText + '</h3>' +
                              '<div class="focal-point-content">' +
                                '<div class="focal-point-picker">' +
                                  '<img src="' + imageUrl + '" alt="Bild-Vorschau">' +
                                  '<div class="focal-point" style="left: ' + (focusX * 100) + '%; top: ' + (focusY * 100) + '%;"></div>' +
                                '</div>' +
                                '<div class="focal-point-controls">' +
                                  '<button type="button" class="focal-point-preset ' + (savedPosition === 'top' ? 'active' : '') + '" data-position="top">Oben</button>' +
                                  '<button type="button" class="focal-point-preset ' + (savedPosition === 'middle' ? 'active' : '') + '" data-position="middle">Mitte</button>' +
                                  '<button type="button" class="focal-point-preset ' + (savedPosition === 'bottom' ? 'active' : '') + '" data-position="bottom">Unten</button>' +
                                '</div>' +
                              '</div>' +
                              '<div class="focal-point-info">Klicken Sie auf das Bild oder die Buttons</div>' +
                              '<div class="focal-point-status">Gespeichert!</div>' +
                              '<input type="hidden" id="' + positionFieldName + '" name="' + positionFieldName + '" value="' + savedPosition + '">' +
                            '</div>';
                        
                        // Füge den Wrapper in den Container ein
                        mediaContainer.append(pickerHTML);
                        
                        // Lokale Zustandsverfolgung
                        var currentPosition = savedPosition;
                        var saveTimeout = null;
                        
                        // Funktion, um die Position zu speichern
                        function savePosition(position) {
                            var statusEl = mediaContainer.find('.focal-point-status');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'save_focal_point_position',
                                    post_id: postId,
                                    field_name: positionFieldName,
                                    position: position,
                                    nonce: '<?php echo wp_create_nonce('focal_point_save'); ?>',
                                    // Zufallszahl hinzufügen, um Cache-Probleme zu vermeiden
                                    cache_buster: Math.random()
                                },
                                beforeSend: function() {
                                    // Stelle sicher, dass Heartbeat läuft, um "Verbindung unterbrochen" zu vermeiden
                                    if (originalHeartbeatSend) {
                                        wp.heartbeat.connectNow();
                                    }
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Erfolg anzeigen
                                        statusEl.text('Gespeichert!').removeClass('error').addClass('show');
                                        
                                        // Status nach 2 Sekunden ausblenden
                                        setTimeout(function() {
                                            statusEl.removeClass('show');
                                        }, 2000);
                                    } else {
                                        // Fehler anzeigen
                                        statusEl.text('Fehler!').addClass('error show');
                                        
                                        // Status nach 2 Sekunden ausblenden
                                        setTimeout(function() {
                                            statusEl.removeClass('show');
                                        }, 2000);
                                    }
                                }
                            });
                        }
                        
                        // Funktion, um den Fokuspunkt zu aktualisieren
                        function updateFocalPoint(position) {
                            var picker = mediaContainer.find('.focal-point-picker');
                            var focalPoint = picker.find('.focal-point');
                            
                            // Alte Position speichern
                            var oldPosition = currentPosition;
                            
                            // Neue Position setzen
                            currentPosition = position;
                            
                            // Nur aktualisieren, wenn sich die Position geändert hat
                            if (oldPosition !== position) {
                                // Fokuspunkt visuell aktualisieren
                                switch(position) {
                                    case 'top':
                                        focalPoint.css({
                                            left: '50%',
                                            top: '0%'
                                        });
                                        break;
                                    case 'middle':
                                        focalPoint.css({
                                            left: '50%',
                                            top: '50%'
                                        });
                                        break;
                                    case 'bottom':
                                        focalPoint.css({
                                            left: '50%',
                                            top: '100%'
                                        });
                                        break;
                                }
                                
                                // Hidden Input aktualisieren
                                mediaContainer.find('input[name="' + positionFieldName + '"]').val(position);
                                
                                // Aktive Button-Klasse aktualisieren (vorher alle zurücksetzen)
                                mediaContainer.find('.focal-point-preset').removeClass('active');
                                mediaContainer.find('.focal-point-preset[data-position="' + position + '"]').addClass('active');
                                
                                // Status-Nachricht zurücksetzen
                                var statusEl = mediaContainer.find('.focal-point-status');
                                statusEl.removeClass('show').removeClass('error');
                                
                                // Verzögerte automatische Speicherung
                                if (saveTimeout) {
                                    clearTimeout(saveTimeout);
                                }
                                
                                saveTimeout = setTimeout(function() {
                                    savePosition(position);
                                }, 300); // Kleine Verzögerung, um zu schnelle wiederholte Speicherungen zu vermeiden
                            }
                        }
                        
                        // Event-Handler für Klicks auf dem Bild
                        mediaContainer.find('.focal-point-picker').on('click', function(e) {
                            var picker = $(this);
                            
                            // Position berechnen (0-1 Bereich)
                            var offsetX = e.pageX - picker.offset().left;
                            var offsetY = e.pageY - picker.offset().top;
                            
                            var x = offsetX / picker.width();
                            var y = offsetY / picker.height();
                            
                            // Wert setzen (nahe Top/Middle/Bottom)
                            var position;
                            
                            if (y < 0.25) {
                                position = 'top';
                            } else if (y > 0.75) {
                                position = 'bottom';
                            } else {
                                position = 'middle';
                            }
                            
                            // Fokuspunkt-Position aktualisieren
                            updateFocalPoint(position);
                        });
                        
                        // Event-Handler für Preset-Buttons
                        mediaContainer.find('.focal-point-preset').on('click', function() {
                            var position = $(this).data('position');
                            updateFocalPoint(position);
                        });
                    }
                });
            }
            
            // Alle Media-Felder finden und Fokuspunkt-Picker initialisieren
            function initAllFocalPointPickers() {
                // Format: [Feldname, Überschrifttext]
                var mediaFields = [
                    ['<?php echo esc_js($this->field_name); ?>', 'Bildposition']
                ];
                
                // Für jedes Feld einen Picker initialisieren
                mediaFields.forEach(function(field) {
                    initFocalPointPicker(field[0], field[1]);
                });
            }
            
            // Bei Seitenlade initialisieren
            initAllFocalPointPickers();
            
            // Bei Medienfeld-Updates den Picker neu laden
            $(document).on('click', '.cx-ui-media-button, .jet-engine-file-upload-button', function() {
                // Warten auf die Media-Modal-Schließung
                setTimeout(function() {
                    initAllFocalPointPickers();
                }, 1000);
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX-Handler für das Abrufen der Fokuspunkt-Daten
     */
    public function get_focal_point_data_ajax() {
        if (!isset($_POST['post_id']) || !isset($_POST['field_name']) || !isset($_POST['image_id'])) {
            wp_send_json_error('Fehlende Parameter');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $image_id = $_POST['image_id'];
        
        // Position abrufen
        $position_field = $field_name . '_position';
        $position = get_post_meta($post_id, $position_field, true);
        
        // Standardwert setzen, falls leer
        if (empty($position)) {
            $position = 'middle';
        }
        
        // Bild-URL abhängig vom Format ermitteln
        $image_url = '';
        
        if (is_numeric($image_id)) {
            // Media ID
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
        } elseif (filter_var($image_id, FILTER_VALIDATE_URL)) {
            // Media URL
            $image_url = $image_id;
        } else {
            // Versuch, als JSON zu parsen (Array-Format)
            $media_data = json_decode($image_id, true);
            if ($media_data && isset($media_data['id'])) {
                $image_url = wp_get_attachment_image_url($media_data['id'], 'medium');
            } elseif ($media_data && isset($media_data['url'])) {
                $image_url = $media_data['url'];
            }
        }
        
        if (!$image_url) {
            wp_send_json_error('Bild konnte nicht gefunden werden');
            return;
        }
        
        wp_send_json_success(array(
            'position' => $position,
            'image_url' => $image_url
        ));
    }

    /**
     * AJAX-Handler für das Speichern der Fokuspunkt-Position
     */
    public function save_focal_point_position_ajax() {
        // Sicherheitsüberprüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'focal_point_save')) {
            wp_send_json_error('Sicherheitsüberprüfung fehlgeschlagen');
            return;
        }
        
        if (!isset($_POST['post_id']) || !isset($_POST['field_name']) || !isset($_POST['position'])) {
            wp_send_json_error('Fehlende Parameter');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $position = sanitize_text_field($_POST['position']);
        
        // Berechtigungen überprüfen
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Keine Berechtigung zum Bearbeiten des Beitrags');
            return;
        }
        
        // Position speichern
        $result = update_post_meta($post_id, $field_name, $position);
        
        // Cache leeren für die Position (WordPress 6.8+ Kompatibilität)
        wp_cache_delete($field_name, 'post_meta_' . $post_id);
        if (function_exists('wp_cache_delete_multiple')) {
            $keys = array(
                $field_name,
                'position_' . $post_id,
                'slider_style_' . $post_id
            );
            wp_cache_delete_multiple($keys, 'jetslider');
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Position gespeichert',
                'field' => $field_name,
                'position' => $position
            ));
        } else {
            wp_send_json_error('Fehler beim Speichern der Position');
        }
    }

    /**
     * Speichern der Positionswerte beim Speichern des Beitrags
     */
    public function save_focus_point_positions($post_id, $post) {
        // Keine Autosave-Aktion verarbeiten
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Die Position für den bekannten Feldnamen speichern
        $position_field = $this->field_name . '_position';
        
        if (isset($_POST[$position_field])) {
            update_post_meta($post_id, $position_field, sanitize_text_field($_POST[$position_field]));
            
            // Cache leeren für die Position (WordPress 6.8+ Kompatibilität)
            wp_cache_delete($position_field, 'post_meta_' . $post_id);
        }
    }

    /**
     * Frontend JavaScript für die Positionsanwendung
     */
    public function fix_jetslider_position_script() {
        // Nur auf der Zielseite ausführen
        $current_post_id = get_the_ID();
        if ($current_post_id != $this->target_page_id) {
            return;
        }
        
        // Position aus der Datenbank direkt abfragen
        global $wpdb;
        $db_position = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}postmeta 
             WHERE post_id = %d AND meta_key = %s",
            $current_post_id,
            $this->field_name . '_position'
        ));
        
        // Standardwert setzen, falls leer
        $position = (!empty($db_position) && in_array($db_position, ['top', 'middle', 'bottom'])) 
                    ? $db_position : 'middle';
        
        // CSS-Position ermitteln
        $css_position = 'center center';
        if ($position === 'top') $css_position = 'center top';
        if ($position === 'bottom') $css_position = 'center bottom';
        
        // Eindeutige ID für das Script generieren, um Caching-Probleme zu vermeiden
        $unique_id = 'jetslider_fix_' . mt_rand(1000, 9999);
        
        // Debug-Info (auskommentiert im Live-Einsatz)
        echo "<!-- JETSLIDER FIX: ID=$current_post_id, DB Position=$db_position, Using=$position -->";
        
        // CSS mit höchster Priorität
        ?>
        <style id="<?php echo $unique_id; ?>_css">
        /* Ultra-spezifisches CSS für die Position */
        body.page-id-<?php echo $this->target_page_id; ?> .jet-slider .sp-image,
        body.postid-<?php echo $this->target_page_id; ?> .jet-slider .sp-image,
        html body .jet-slider .sp-image.position-<?php echo $position; ?>,
        html body .sp-slide .sp-image.position-<?php echo $position; ?>,
        #jet-slider-<?php echo $this->target_page_id; ?> .sp-image,
        .elementor-<?php echo $this->target_page_id; ?> .jet-slider .sp-image,
        [data-elementor-id="<?php echo $this->target_page_id; ?>"] .jet-slider .sp-image {
            object-position: <?php echo $css_position; ?> !important;
        }
        </style>
        
        <script id="<?php echo $unique_id; ?>_js" type="text/javascript">
        (function() {
            // Konfiguration
            var targetPosition = '<?php echo $position; ?>';
            var cssPosition = '<?php echo $css_position; ?>';
            var pageId = <?php echo $current_post_id; ?>;
            var uniqueId = '<?php echo $unique_id; ?>';
            
            // Debug-Info in der Konsole
            console.log('JETSLIDER FIX ' + uniqueId + ': Seite ' + pageId + ', Position ' + targetPosition);
            
            // Hauptfunktion zum Setzen der Position
            function forceSliderPosition() {
                // Alle Bilder in Slidern finden
                var images = document.querySelectorAll('.jet-slider .sp-image, .sp-slide .sp-image');
                
                if (images.length === 0) {
                    return false;
                }
                
                console.log('JETSLIDER FIX: ' + images.length + ' Bilder gefunden');
                
                // Position auf alle Bilder anwenden
                images.forEach(function(img) {
                    // Aktuellen Zeitstempel als Data-Attribut setzen (für Cache-Debug)
                    img.setAttribute('data-js-fix-time', Date.now());
                    
                    // Klassen entfernen und neu setzen
                    img.classList.remove('position-top', 'position-middle', 'position-bottom');
                    img.classList.add('position-image', 'position-' + targetPosition);
                    
                    // Inline-Stil mit !important setzen
                    img.style.setProperty('object-fit', 'cover', 'important');
                    img.style.setProperty('object-position', cssPosition, 'important');
                    
                    // Data-Attribute zur Nachverfolgung
                    img.setAttribute('data-forced-position', targetPosition);
                    img.setAttribute('data-fix-id', uniqueId);
                });
                
                return images.length > 0;
            }
            
            // MutationObserver zum Überwachen von DOM-Änderungen
            function setupMutationObserver() {
                if (!window.MutationObserver) {
                    return; // Nicht unterstützt
                }
                
                // Überwacht Änderungen am gesamten Body
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            // Neue Elemente wurden hinzugefügt - Position erneut anwenden
                            setTimeout(forceSliderPosition, 10);
                        }
                    });
                });
                
                // Konfigurieren und starten
                observer.observe(document.body, { 
                    childList: true, 
                    subtree: true 
                });
                
                console.log('JETSLIDER FIX: MutationObserver aktiv');
            }
            
            // Ausführungszeitplan
            function executeWithStrategies() {
                // Strategie 1: Sofortige Ausführung
                var success = forceSliderPosition();
                
                // Strategie 2: MutationObserver für DOM-Änderungen
                setupMutationObserver();
                
                // Strategie 3: Verzögerte Ausführungen
                setTimeout(forceSliderPosition, 10);
                setTimeout(forceSliderPosition, 100);
                setTimeout(forceSliderPosition, 500);
                setTimeout(forceSliderPosition, 1000);
                setTimeout(forceSliderPosition, 2000);
                
                // Strategie 4: Kontinuierliche Überwachung
                var checkInterval = setInterval(function() {
                    forceSliderPosition();
                }, 1000); // Jede Sekunde prüfen
                
                // Nach 10 Sekunden Intervall beenden
                setTimeout(function() {
                    clearInterval(checkInterval);
                    console.log('JETSLIDER FIX: Kontinuierliche Überwachung beendet');
                }, 10000);
            }
            
            // Ausführung starten
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', executeWithStrategies);
            } else {
                executeWithStrategies();
            }
            
            // Nach vollständigem Laden ausführen
            window.addEventListener('load', forceSliderPosition);
            
            // Bei Resize erneut anwenden
            window.addEventListener('resize', forceSliderPosition);
        })();
        </script>
        <?php
    }

    /**
     * Attribute zum JetSlider-Widget hinzufügen
     */
    public function add_jet_slider_fix_attributes($widget) {
        // Prüfen, ob es sich um einen JetSlider handelt
        if ($widget->get_name() === 'jet-slider') {
            // Aktuellen Post abrufen
            $post_id = get_the_ID();
            
            // Positionsdaten aus der Datenbank
            $position = get_post_meta($post_id, $this->field_name . '_position', true);
            if (empty($position)) {
                $position = 'middle';
            }
            
            // CSS-Klasse hinzufügen
            $widget->add_render_attribute('_wrapper', 'class', 'slider-position-' . $position);
            
            // Data-Attribute hinzufügen
            $widget->add_render_attribute('_wrapper', 'data-slider-position', $position);
            $widget->add_render_attribute('_wrapper', 'data-position-timestamp', time());
        }
    }

    /**
     * Cache-Leerer bei Update-Aktionen
     */
    public function clear_position_cache_on_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Nur für unsere Positionsfelder
        if (strpos($meta_key, '_position') !== false) {
            // WordPress Objekt-Cache leeren für diese Meta-Key
            wp_cache_delete($meta_key, 'post_meta_' . $post_id);
            
            // Erweiterte Cache-Löschung für WP 6.8+
            if (function_exists('wp_cache_delete_multiple')) {
                $keys = array(
                    $meta_key,
                    'position_' . $post_id,
                    'slider_style_' . $post_id
                );
                wp_cache_delete_multiple($keys, 'jetslider');
            }
            
            // Für Plugins wie WP Rocket
            if (function_exists('rocket_clean_post')) {
                rocket_clean_post($post_id);
            }
            
            // Für W3 Total Cache
            if (function_exists('w3tc_flush_post')) {
                w3tc_flush_post($post_id);
            }
        }
    }

    /**
     * Transient-Cache bei Speicherung leeren
     */
    public function clear_transient_cache_on_post_save($post_id, $post, $update) {
        // Prüfen, ob es sich um einen relevanten Post handelt
        if ($post_id == $this->target_page_id) {
            // Transients löschen
            delete_transient('slider_cache_' . $post_id);
            delete_transient('slider_html_' . $post_id);
            
            // Für WP Object Cache
            wp_cache_delete('slider_data_' . $post_id, 'jetslider');
        }
    }

    /**
     * Cache-Busting-Parameter zu CSS und JS hinzufügen
     */
    public function add_cache_busting_parameter($src, $handle) {
        // Nur für bestimmte Handles anwenden
        if (strpos($handle, 'jet-slider') !== false || 
            strpos($handle, 'elementor-') !== false || 
            strpos($src, 'jet-elements') !== false) {
            
            // Aktuelle Zeit als Version hinzufügen
            if (strpos($src, '?ver=') !== false) {
                // Existierende Version ersetzen
                $src = preg_replace('/\?ver=([^&]*)/', '?ver=' . time(), $src);
            } else {
                // Neue Version hinzufügen
                $src .= (strpos($src, '?') === false) ? '?ver=' . time() : '&ver=' . time();
            }
        }
        
        return $src;
    }

    /**
     * Debug-Informationen anzeigen
     */
    public function debug_cache_info() {
        // Nur auf der Zielseite anzeigen
        if (get_the_ID() != $this->target_page_id) {
            return;
        }
        
        echo "<!-- Cache Debug Info: " . date('Y-m-d H:i:s') . " -->";
        echo "<!-- WordPress Version: " . get_bloginfo('version') . " -->";
        
        // Installierte Plugins auflisten
        echo "<!-- Aktivierte Caching-Plugins: ";
        $active_plugins = get_option('active_plugins');
        $cache_plugins = array_filter($active_plugins, function($plugin) {
            return (
                strpos($plugin, 'cache') !== false || 
                strpos($plugin, 'rocket') !== false || 
                strpos($plugin, 'litespeed') !== false ||
                strpos($plugin, 'autoptimize') !== false ||
                strpos($plugin, 'jetpack') !== false
            );
        });
        echo implode(', ', $cache_plugins);
        echo " -->";
        
        // Position aus der Datenbank 
        global $wpdb;
        $position = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = %s",
            $this->target_page_id,
            $this->field_name . '_position'
        ));
        echo "<!-- Slider Position in DB: " . $position . " -->";
    }

    /**
     * Einstellungen-Link zum Plugin hinzufügen
     */
    public static function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=jetslider-fokuspunkt">Einstellungen</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Plugin-Aktivierungsfunktion
     */
    public static function activate() {
        // Hier können Initialisierungen bei der Plugin-Aktivierung stattfinden
        // z.B. Datenbanktabellen erstellen, Optionen setzen, etc.
    }

    /**
     * Plugin-Deaktivierungsfunktion
     */
    public static function deactivate() {
        // Hier können Aufräumarbeiten bei der Plugin-Deaktivierung stattfinden
        // z.B. temporäre Dateien löschen, etc.
    }
}

// Plugin-Instanz erstellen
$jetslider_fokuspunkt = new JetSlider_Fokuspunkt();

// Plugin-Aktivierungs-/Deaktivierungshooks
register_activation_hook(__FILE__, array('JetSlider_Fokuspunkt', 'activate'));
register_deactivation_hook(__FILE__, array('JetSlider_Fokuspunkt', 'deactivate'));

// Einstellungen-Link im Plugin-Menü hinzufügen
add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('JetSlider_Fokuspunkt', 'add_settings_link'));

/**
 * Automatischen Download des Plugin-Update-Checkers durchführen, wenn er nicht existiert
 */
add_action('admin_init', 'jetslider_fokuspunkt_check_puc');

function jetslider_fokuspunkt_check_puc() {
    // Prüfen, ob der Plugin-Update-Checker bereits existiert
    if (!file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
        // URL zum Plugin-Update-Checker
        $puc_url = 'https://github.com/YahnisElsts/plugin-update-checker/archive/refs/heads/master.zip';
        
        // Temporärer Pfad für den Download
        $temp_file = download_url($puc_url);
        
        if (!is_wp_error($temp_file)) {
            // Extrahieren des ZIP-Archivs
            $wp_filesystem = jetslider_fokuspunkt_initialize_filesystem();
            $extract_to = __DIR__;
            $unzipped = unzip_file($temp_file, $extract_to);
            
            if (!is_wp_error($unzipped)) {
                // Verschieben der Dateien in den korrekten Ordner
                $extracted_folder = $extract_to . '/plugin-update-checker-master';
                $target_folder = $extract_to . '/plugin-update-checker';
                
                // Ordner umbenennen
                if (file_exists($extracted_folder) && !file_exists($target_folder)) {
                    rename($extracted_folder, $target_folder);
                }
                
                // Nach dem Verschieben Plugin-Update-Checker neu laden
                if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
                    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
                    
                    // Plugin-Update-Checker initialisieren
                    $update_checker = Puc_v4_Factory::buildUpdateChecker(
                        'https://github.com/ihr-josephki/jetslider-fokuspunkt/',
                        __FILE__,
                        'jetslider-fokuspunkt'
                    );
                    $update_checker->setBranch('main');
                }
            }
            
            // Temporäre Datei löschen
            @unlink($temp_file);
        }
    }
}

/**
 * WordPress-Dateisystem initialisieren
 * 
 * @return WP_Filesystem_Base Das initialisierte Dateisystem-Objekt
 */
function jetslider_fokuspunkt_initialize_filesystem() {
    global $wp_filesystem;
    
    if (empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }
    
    return $wp_filesystem;
}