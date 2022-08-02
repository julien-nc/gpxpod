# Aplicación GpxPod para Nextcloud

Mostra, analiza, compara e comparte ficheiros de trazas GPS.

🌍 Axúdanos a traducir esta app no [proxecto GpxPod en Crowdin](https://crowdin.com/project/gpxpod).

GpxPod:

* 🗺 pode mostrar calquera dos teus ficheiros gpx/kml/tcx/igc/fit, ficheiros compartidos contigo, ficheiros en cartafoles compartidos contigo. fit files will be converted and displayed only if **GpsBabel** is found on the server system
* 📏 soporta os sistemas de medida métrico, inglés e náutico
* 🗠  debuxa gráficos interactivos de elevación, velocidade ou ritmo
* 🗠  pode utilizar cores nas liñas para velocidade, elevación ou ritmo
* 🗠  mostra estatísticas da ruta
* ⛛  filtra rutas por data, distancia total...
* 🖻  mostra imaxes xeoetiquetadas que se atopen no directorio seleccionado
* 🖧  crea ligazóns públicas a rutas/cartafoles. Esta ligazón pode utilizarse se o cartafol/ficheiro está compartido de xeito público
* 🗁  permite mover os ficheiros de rutas seleccionados
* 🗠  pode correxir datos de elevación se SRTM.py (gpxelevations) está instalada no sistema do servidor
* ⚖  pode facer unha comparación global de múltiples rexistros
* ⚖  pode facer unha comparación visual de partes diverxentes en rutas semellantes
* 🀆  permite que as usuarias engadan servidores de mapas personalizados
* ⚙  garda/restablece os valores das opcións da usuaria
* 🖍 permítelle á usuaria establecer as cores das liñas da ruta
* 🕑 detecta a zona horaria do navegador
* 🗬  carga marcadores extra de símbolos desde GpxEdit se está instalada
* 🔒 funciona tamén con cartafoles de datos cifrados (cifrado do lado do servidor)
* 🍂 satisfácese de utilizar Leaflet con moitos complementos para mostrar o mapa
* 🖴  é compatible con bases de datos SQLite, MySQL e PostgreSQL
* 🗁  engade a posibilidade de ver ficheiros .gpx directamente desde a app "Ficheiros"

Esta app está probada en Nextcloud 15 con Firefox 57+ e Chromium.

Esta app está en desenvolvemento (lento).

Ligazón ao sitio web desta aplicación Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Instalación

Le o [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) para os detalles da instalación

## Problemas coñecidos

* xestión incorrecta de nomes de ficheiro que inclúen comiñas dobres ou simple
* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary "gx:track" extension tag.

Calquera opinión será ben recibida.
