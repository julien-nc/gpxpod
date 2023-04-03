# Aplicación GpxPod para Nextcloud

Mostra, analiza, compara e comparte ficheiros de trazas GPS.

🌍 Axúdanos a traducir esta app no [proxecto GpxPod en Crowdin](https://crowdin.com/project/gpxpod).

GpxPod:

* 🗺  can display gpx/kml/tcx/igc/fit files anywhere in your files, files shared with you, files in folders shared with you. fit files will be converted and displayed only if **GpsBabel** is found on the server system
* 📏 soporta os sistemas de medida métrico, inglés e náutico
* 🗠 debuxa gráficos interactivos de elevación, velocidade ou ritmo
* 🗠 pode utilizar cores nas liñas para velocidade, elevación ou ritmo
* 🗠 mostra estatísticas da ruta
* ⛛ filtra rutas por data, distancia total...
* 🖻 mostra imaxes xeoetiquetadas que se atopen no directorio seleccionado
* 🖧  generates public links pointing to a track/folder. This link can be used if the file/folder is shared by public link
* 🗁 permite mover os ficheiros de rutas seleccionados
* 🗠 pode correxir datos de elevación se SRTM.py (gpxelevations) está instalada no sistema do servidor
* ⚖ pode facer unha comparación global de múltiples rexistros
* ⚖ pode facer unha comparación visual de partes diverxentes en rutas semellantes
* 🀆 permite que as usuarias engadan servidores de mapas personalizados
* ⚙ garda/restablece os valores das opcións da usuaria
* 🖍 permítelle á usuaria establecer as cores das liñas da ruta
* 🕑 detecta a zona horaria do navegador
* 🗬 carga marcadores extra de símbolos desde GpxEdit se está instalada
* 🔒 funciona tamén con cartafoles de datos cifrados (cifrado do lado do servidor)
* 🍂 satisfácese de utilizar Leaflet con moitos complementos para mostrar o mapa
* 🖴 é compatible con bases de datos SQLite, MySQL e PostgreSQL
* 🗁 engade a posibilidade de ver ficheiros .gpx directamente desde a app "Ficheiros"

Esta app está probada en Nextcloud 15 con Firefox 57+ e Chromium.

Esta app está en desenvolvemento (lento).

Ligazón ao sitio web desta aplicación Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Instalación

Le o [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) para os detalles da instalación

## Problemas coñecidos

* xestión incorrecta de nomes de ficheiro que inclúen comiñas dobres ou simple
* *AVISO*, a conversión kml NON funcionará con ficheiros kml recentes que utizan a etiqueta de extensión "gx:track" propietaria.

Calquera opinión será ben recibida.
