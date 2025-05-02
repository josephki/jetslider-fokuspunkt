# JetSlider Fokuspunkt

Ein WordPress-Plugin zur Steuerung der Bildpositionierung in JetSlider mit zusätzlichen Anpassungen für WordPress 6.8+ Cache-Kompatibilität.

![JetSlider Fokuspunkt Screenshot](screenshot.jpg)

## Beschreibung

JetSlider Fokuspunkt ist ein spezielles Plugin für WordPress, das eine intuitive Steuerung der Bildpositionierung (oben, mitte, unten) für den JetSlider von JetElements/Crocoblock ermöglicht. Es löst auch Caching-Probleme, die in WordPress 6.8 und höher auftreten können.

### Hauptfunktionen

- **Einfacher Fokuspunkt-Picker**: Wählen Sie mit wenigen Klicks zwischen "oben", "mitte" und "unten" Positionen für Ihre Slider-Bilder
- **Cache-Kompatibilität**: Speziell optimiert für die neuen Caching-Mechanismen in WordPress 6.8+
- **Mehrschichtige Anwendung**: Verwendet verschiedene Methoden, um sicherzustellen, dass die Bildpositionen konsistent angezeigt werden
- **Performance-Optimierung**: Minimale Performance-Auswirkungen durch gezielte Anwendung nur auf bestimmten Seiten

### Vorteile

- Verbesserte Benutzerfreundlichkeit durch konsistente Bildpositionierung
- Löst Probleme mit dem Caching, bei denen die Bildposition zwischen angemeldeten und nicht angemeldeten Benutzern unterschiedlich sein kann
- Keine Konflikte mit anderen Plugins oder Themes
- Einfach zu installieren und zu konfigurieren

## Installation

1. Laden Sie das Plugin als ZIP-Datei herunter
2. Gehen Sie in Ihrem WordPress-Admin-Bereich zu "Plugins > Installieren"
3. Klicken Sie auf "Plugin hochladen" und wählen Sie die heruntergeladene ZIP-Datei aus
4. Aktivieren Sie das Plugin

Alternativ können Sie das Plugin auch manuell installieren:

1. Entpacken Sie die ZIP-Datei
2. Laden Sie den `jetslider-fokuspunkt`-Ordner in das `/wp-content/plugins/`-Verzeichnis Ihrer WordPress-Installation hoch
3. Aktivieren Sie das Plugin über den Menüpunkt "Plugins" in WordPress

## Konfiguration

Nach der Aktivierung funktioniert das Plugin sofort mit den Standardeinstellungen:

- Es wird automatisch für Bilder im Feld `slider_bild` angewendet
- Der Fix wird nur auf der Seite mit der ID 6905 angewendet

Um diese Einstellungen anzupassen, öffnen Sie die Datei `jetslider-fokuspunkt.php` und ändern Sie diese Werte am Anfang der Klasse:

```php
// Feld-Name für Slider-Bild
private $field_name = 'slider_bild';

// Seiten-ID, für die der Fix angewendet werden soll
private $target_page_id = 6905;
```

## Verwendung

### Im Backend

1. Bearbeiten Sie eine Seite oder einen Beitrag, der einen JetSlider enthält
2. Sobald Sie ein Bild für den Slider auswählen, erscheint der Fokuspunkt-Picker rechts neben dem Bildauswahlfeld
3. Klicken Sie auf einen der Positionsbuttons (Oben, Mitte, Unten) oder direkt auf das Bild im Picker
4. Die Position wird automatisch gespeichert und angewendet

### Im Frontend

Die ausgewählte Bildposition wird automatisch auf den JetSlider im Frontend angewendet. Es sind keine weiteren Schritte erforderlich.

## Technische Details

Das Plugin verwendet verschiedene Techniken, um sicherzustellen, dass die Bildposition konsistent angezeigt wird:

1. **Backend-Editor**: Ein visueller Fokuspunkt-Picker zum einfachen Auswählen der Position
2. **Direkte Datenbankabfragen**: Vermeidet Cache-Probleme durch direkte SQL-Abfragen
3. **Spezifisches CSS**: Ultra-spezifische CSS-Selektoren mit höchster Priorität
4. **JavaScript-Anwendung**: Dynamisches Anwenden der Position via JavaScript
5. **MutationObserver**: Überwacht DOM-Änderungen und wendet die Position bei Bedarf erneut an
6. **Cache-Invalidierung**: Löscht relevante Cache-Einträge bei Änderungen

## Fehlerbehebung

### Die Bildposition wird nicht korrekt angezeigt

Versuchen Sie diese Schritte:

1. Leeren Sie den WordPress-Cache (falls ein Cache-Plugin verwendet wird)
2. Leeren Sie den Browser-Cache (STRG+F5 oder SHIFT+F5)
3. Prüfen Sie, ob die richtige Seiten-ID im Plugin konfiguriert ist
4. Überprüfen Sie, ob der richtige Feldname konfiguriert ist

### Andere Probleme

Wenn Sie auf andere Probleme stoßen:

1. Aktivieren Sie den Debug-Helper, indem Sie folgende Zeile in der Plugin-Datei auskommentieren:
   ```php
   add_action('wp_footer', array($this, 'debug_cache_info'), 999);
   ```
2. Prüfen Sie die Browser-Konsole auf JavaScript-Fehler oder Warnungen
3. Analysieren Sie die Debug-Informationen, die im HTML-Quelltext angezeigt werden

## Updates

Dieses Plugin unterstützt automatische Updates von GitHub. Wenn eine neue Version veröffentlicht wird, erscheint eine Update-Benachrichtigung im WordPress-Admin-Bereich.

## Anforderungen

- WordPress 5.6 oder höher
- JetSlider/JetElements oder kompatibles Slider-Plugin
- PHP 7.2 oder höher

## Unterstützte Plugins

- JetElements (Crocoblock)
- Elementor
- WP Rocket (optional)
- W3 Total Cache (optional)

## Mitwirkende

- [Ihr Name] - Entwicklung und Wartung

## Lizenz

Dieses Plugin ist unter der GPL v2 oder höher lizenziert.

## Änderungsprotokoll

### 1.0.0 (2025-05-02)
- Erste öffentliche Version
- Unterstützung für JetSlider/JetElements
- WordPress 6.8+ Cache-Kompatibilität
- Fokuspunkt-Picker für einfache Positionsauswahl

## Support

Bei Fragen oder Problemen erstellen Sie bitte ein Issue auf GitHub oder kontaktieren Sie uns unter [Ihre E-Mail-Adresse].