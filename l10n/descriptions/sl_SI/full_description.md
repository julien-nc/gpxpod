# Aplikacija GpxPod za Nextcloud

Prikaz, analiza, primerjava in deljenje GPS sledi.

🌍 Pomagajte pri prevajanju aplikacije na strani [GpxPod Crowdin projekta](https://crowdin.com/project/gpxpod).

GpxPod :

* 🗺  can display gpx/kml/tcx/igc/fit files anywhere in your files, files shared with you, files in folders shared with you. fit files will be converted and displayed only if **GpsBabel** is found on the server system
* 📏 podpora metričnemu, anglosaškemu in navtičnemu merskemu sistemu
* 🗠 izris vzpona, hitrosti ali tempa na interaktivnem grafu
* 🗠 lahko obarva črte sledi po hitrosti, vzponi ali tempu
* 🗠 prikaz statistike sledi
* ⛛ prikaz sledi po datumu, skupni razdalji...
* 🖻 prikaz fotografij v izbrani mapi z geolokacijsko oznako
* 🖧  generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* 🗁 omogoča premikanje datotek izbranih sledi
* 🗠 omogoča popravljanje vzpona sledi, če je na strežniku nameščen SRTM.py (gpxelevations)
* ⚖ omogoča primerjavo večih sledi
* ⚖ omogoča vizualno primerjavo razhajanja delov podobnih sledi
* 🀆 omogoča uporabnikom, da dodajo osebne strežnike s ploščicami
* ⚙ shrani/obnovi parametre uporabnikovih nastavitev
* 🖍 omogoča, da uporabnik ročno nastavi barvo črte sledi
* 🕑 zazna časovni pas brskalnika
* 🗬 naloži dodatne simbole programa GpxEdit, če je naložen
* 🔒 deluje s šifriranimi mapami (šifriranje s strani strežnika)
* 🍂 ponosno uporablja Leaflet z vtičniki za prikaz zemljevidov
* 🖴 je združljiv s podatkovnimi bazami SQLite, MySQL in PostgreSQL
* 🗁 doda možnost pogleda .gpx datotek v aplikaciji "Datoteke"

Aplikacija je preizkušena v Nextcloud različice 15 z brskalnikoma Firefox 57+ in Chromium.

Aplikacija je v (počasnem) razvoju.

Povezava do Nextcloud aplikacije : https://apps.nextcloud.com/apps/gpxpod

## Namestitev

Preberite [dokumentacijo](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) za podrobnosti namestitve

## Znane težave

* slabo upravljanje z imeni datotek, ki vsebujejo enojne ali dvojne narekovaje
* *OPOZORILO*, pretvorba kml NE bo delovala pri kml datotekah, ki vsebujejo razširjeno značko "gx:track".

Vsaka povratna informacija je zaželena.
