# Nextcloud aplikace GpxPod

Zobrazování, analýza, porovnání a sdílení souborů s GPS trasami.

🌍 Pomozte nám s překládáním textů v rozhraní této aplikace v rámci [projektu GpxPod na službě Crowdin](https://crowdin.com/project/gpxpod).

GpxPod:

* 🗺  can display gpx/kml/tcx/igc/fit files anywhere in your files, files shared with you, files in folders shared with you
* 📏 podporuje metrické, anglické a námořní systémy měrných jednotek
* 🗠 vykresluje interaktivní graf výšky, rychlosti nebo tempa
* 🗠 umí obarvovat čáry podle rychlosti, nadmořské výšky nebo tempa
* 🗠 zobrazuje statistiky trasy
* 🖻  displays geotagged pictures
* 🖧  generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* 🗁 umožňuje přesouvat označené soubory s trasami
* 🗠 umí opravovat nadmořské výšky tras – tedy pokud je v systému na serveru nalezen nástroj SRTM.py (gpxelevations)
* ⚖ umí provádět globální porovnávání několika tras
* ⚖ umí provádět vizuální porovnávání odlišných částí podobných tras
* 🀆 umožňuje uživatelům přidávat jimi určené servery s mapovými podklady
* ⚙ ukládá/obnovuje hodnoty předvoleb uživatele
* 🖍 umožňuje uživateli ručně nastavit barvy čáry trasy
* 🕑 zjišťuje jakou časovou zónu má nastavenou webový prohlížeč
* 🗬 pokud je nainstalovaná také aplikace GpxEdit, načítá další označovací symboly
* 🔒 funguje i s šifrovanou datovou složkou (šifrování na straně serveru)
* 🍂 pro zobrazení mapy hrdě používá Leaflet s mnoha zásuvnými moduly
* 🖴 je kompatibilní s databázemi SQLite, MySQL a PostgreSQL
* 🗁 přidává možnost zobrazit si obsah .gpx souborů přímo z aplikace „Soubory“

Tato aplikace je testovaná na Nextcloud 15 a prohlížečích Firefox 57 a novějším a Chromium.

Na této aplikaci probíhá (nepříliš rychlý) vývoj.

Odkaz na stránku aplikace v katalogu Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Instalace

Podrobnosti ohledně instalace naleznete v [dokumentaci pro správce](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc)

## Známé problémy

* *VAROVÁNÍ*, převod z kml formátu nebude fungovat v případě nových kml souborů, které používají proprietární rozšiřující značku „gx:track“.

Jakákoliv zpětná vazba bude vítána.
