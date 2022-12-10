# Nextcloud aplikace GpxPod

Zobrazování, analýza, porovnání a sdílení souborů s GPS trasami.

🌍 Pomozte nám s překládáním textů v rozhraní této aplikace v rámci [projektu GpxPod na službě Crowdin](https://crowdin.com/project/gpxpod).

GpxPod:

* 🗺  umí zobrazit obsah gpx/kml/tcx/igc/fit souborů kdekoli ve vašich souborech. Stejně tak v těch, které vám někdo nasdílel, či souborech nacházejících se ve vám sdílených složkách. fit soubory budou převedeny a zobrazeny pouze v případě, že se na serveru nachází nástroj **GpsBabel**
* 📏 podporuje metrické, anglické a námořní systémy měrných jednotek
* 🗠   vykresluje interaktivní graf výšky, rychlosti nebo tempa
* 🗠  umí obarvovat čáry podle rychlosti, nadmořské výšky nebo tempa
* 🗠  zobrazuje statistiky trasy
* ⛛  filtruje trasy podle data, celkové vzdálenosti…
* 🖻  zobrazuje obrázky, které mají v metadatech vyplněnou polohu, které nalezne ve vybrané složce
* 🖧  vytváří veřejné odkazy vedoucí na trasu/složku. Tento odkaz je možné použít pokud je soubor/složka sdílena veřejným odkazem
* 🗁  umožňuje přesouvat označené soubory s trasami
* 🗠  umí opravovat nadmořské výšky tras – tedy pokud je v systému na serveru nalezen nástroj SRTM.py (gpxelevations)
* ⚖  umí provádět globální porovnávání několika tras
* ⚖  umí provádět vizuální porovnávání odlišných částí podobných tras
* 🀆  umožňuje uživatelům přidávat jimi určené servery s mapovými podklady
* ⚙  ukládá/obnovuje hodnoty předvoleb uživatele
* 🖍 umožňuje uživateli ručně nastavit barvy čáry trasy
* 🕑 zjišťuje jakou časovou zónu má nastavenou webový prohlížeč
* 🗬  pokud je nainstalovaná také aplikace GpxEdit, načítá další označovací symboly
* 🔒 funguje i s šifrovanou datovou složkou (šifrování na straně serveru)
* 🍂 pro zobrazení mapy hrdě používá Leaflet s mnoha zásuvnými moduly
* 🖴  je kompatibilní s databázemi SQLite, MySQL a PostgreSQL
* 🗁  přidává možnost zobrazit si obsah .gpx souborů přímo z aplikace „Soubory“

Tato aplikace je testovaná na Nextcloud 15 a prohlížečích Firefox 57 a novějším a Chromium.

Na této aplikaci probíhá (nepříliš rychlý) vývoj.

Odkaz na stránku aplikace v katalogu Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Instalace

Podrobnosti ohledně instalace naleznete v [dokumentaci pro správce](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc)

## Známé problémy

* nesprávné zacházení se soubory, jejichž názvy obsahují jednoduché nebo dvojité uvozovky
* *VAROVÁNÍ*, převod z kml formátu nebude fungovat v případě nových kml souborů, které používají proprietární rozšiřující značku „gx:track“.

Jakákoliv zpětná vazba bude vítána.
