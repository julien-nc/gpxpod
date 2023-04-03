# GpxPod Nextcloud-Anwendung

Anzeige, Analyse, Vergleich und Freigabe von GPS-Steckendateien.

🌍 Helfen Sie uns, diese App auf [GpxPod Crowdin Projekt](https://crowdin.com/project/gpxpod) zu übersetzen.

GpxPod:

* 🗺 kann GPX-, KML- ,TCX- ,IGC- und FIT-Dateien überall in Ihren Dateien und in Dateien und Dateien in Ordner, die mit Ihnen geteilt werden, angezeigt werden. Fit-Dateien werden nur konvertiert und angezeigt, wenn **GpsBabel** auf dem Serversystem gefunden wird.
* 📏 unterstützt metrische, englische und nautische Messsysteme
* zeichnet Höhe, Geschwindigkeit oder Tempo interaktives Diagramm
* 🗠 kann Spurlinien nach Geschwindigkeit, Höhe oder Tempo einfärben
* 🗠 Trackstatistik anzeigen
* ⛛ Filtern von Tracks nach Datum, Gesamtdistanz...
* 🖻 zeigt geotagged Bilder im ausgewählten Verzeichnis
* 🖧 generiert öffentliche Links, die auf einen Track/Ordner verweisen. Dieser Link kann verwendet werden, wenn die Datei/Ordner durch den öffentlichen Link geteilt werden
* 🗁 erlaubt das Verschieben ausgewählter Track-Dateien
* 🗠 kann die Höhe der Spuren korrigieren, wenn SRTM.py (gpxelevations) auf dem Server gefunden wird
* ⚖ kann einen globalen Vergleich mehrerer Tracks machen
* ⚖ kann einen visuellen Vergleich von zwei unterschiedlichen Teilen ähnlicher Tracks durchführen
* 🀆 Erlaubt Benutzern persönliche Karten-Server hinzuzufügen
* ⚙ Speichern/Wiederherstellen von Benutzeroptionen
* 🖍 Erlaubt dem Benutzer die manuelle Einstellung der Linienfarben
* Erkennt Browser-Zeitzone
* 🗬 lädt zusätzliche Markierungssymbole von GpxEdit sofern installiert
* 🔒 Funktioniert auch mit verschlüsseltem Datenordner (serverseitige Verschlüsselung)
* 🍂 benutzt und unterstützt Leaflet mit vielen Plugins um die Karte anzuzeigen
* Kompatibel mit SQLite-, MySQL- und PostgreSQL-Datenbanken
* 🗁 fügt die Möglichkeit hinzu, .gpx-Dateien direkt aus der "Dateien"-App anzusehen

Diese App wurde auf Nextcloud 15, Firefox 57+ und Chromium getestet.

Die App wird aktiv (aber langsam) weiterentwickelt.

Link zur Webseite der Nextcloud App : https://apps.nextcloud.com/apps/gpxpod

## Installation

Siehe [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) für Installationsdetails

## Bekannte Probleme

* schlechte Verwaltung von Dateinamen wenn diese einfache oder doppelte Anführungszeichen enthalten
* *WARNUNG*, die kml-Konvertierung funktioniert NICHT mit aktuellen kml-Dateien unter Verwendung des proprietären Tags der "gx:track"-Erweiterung.

Wir freuen uns über jede Rückmeldung.
