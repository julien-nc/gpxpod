# Applicazione GpxPod Nextcloud

Visualizza, analizza, confronta e condividi i file GPS.

〓 Aiutaci a tradurre questa app su [GpxPod Crowdin project](https://crowdin.com/project/gpxpod).

GpxPod :

* 🗺 può visualizzare i file gpx/kml/tcx/igc/fit ovunque nei file, i file condivisi con voi, i file nelle cartelle condivise con voi. i file se corretti verranno convertiti e visualizzati solo se **GpsBabel** si trova sul sistema server
* 📏 supporta sistemi di misurazione metrica, inglese e nautica
* <unk> disegna l'elevazione, la velocità o il ritmo con grafico interattivo
* <unk> può colorare le linee della traccia per velocità, elevazione o ritmo
* <unk> mostra le statistiche della traccia
* <unk> filtra le tracce per data, distanza totale...
* <unk> visualizza immagini geotaggate trovate nella directory selezionata
* <unk> genera collegamenti pubblici che puntano a una traccia/cartella. Questo link può essere usato se il file/cartella è condiviso dal link pubblico
* <unk> consente di spostare i file di traccia selezionati
* <unk> può correggere gli aumenti delle tracce se SRTM.py (gpxelevations) è trovato nel sistema del server
* ⚖ può fare un confronto globale di più tracce
* ⚖ può fare un confronto visivo tra le coppie di parti divergenti di tracce simili
* <unk> consente agli utenti di aggiungere i server delle mappe personali
* ⚙ salva / ripristina i valori delle opzioni utente
* 🖍 consente all'utente di impostare manualmente i colori della linea
* 🕑 rileva il fuso orario del browser
* <unk> carica simboli di marker extra da GpxEdit se installato
* 🔒 funziona con la cartella dati cifrata (crittografia lato server)
* 🍂 utilizza con orgoglio il flet con un sacco di plugin per visualizzare la mappa
* <unk> è compatibile con i database SQLite, MySQL e PostgreSQL
* <unk> aggiunge la possibilità di visualizzare i file .gpx direttamente dall'app "File"

Questa app è testata su Nextcloud 15 con Firefox 57+ e Chromium.

Questa applicazione è sotto (lento) sviluppo.

Link al sito web dell'applicazione Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Installazione

Vedi [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) per i dettagli di installazione

## Problemi noti

* cattiva gestione dei nomi dei file, comprese le virgolette semplici o doppie
* *ATTENZIONE*, la conversione in kml NON funzionerà con i file kml recenti utilizzando il tag proprietario di estensione "gx:track".

Qualsiasi feedback sarà apprezzato.
