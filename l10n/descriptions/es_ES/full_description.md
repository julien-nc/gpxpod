# Aplicaci贸n GpxPod para Nextcloud

Muestra, analiza, compara y comparte archivos de pistas GPS.

馃實 Ay煤danos a traducir esta aplicaci贸n en el [proyecto de GpxPod en Crowdin](https://crowdin.com/project/gpxpod).

GpxPod:

* 馃椇 puede mostrar archivos gpx/kml/tcx/igc/fit en cualquier lugar de tus archivos, en archivos compartidos contigo o en archivos en carpetas compartidas contigo. los archivos fit se convertir谩n y se mostrar谩n solo si **GpsBabel** se encuentra en el servidor
* 馃搹 soporta los sistemas m茅trico, anglosaj贸n y n谩utico
* 路 Traza una tabla interactiva de elevaci贸n, velocidad o ritmo
* Puede colorear las l铆neas de las pistas por velocidad, elevaci贸n o ritmo
* 馃棤 muestra estad铆sticas de la pista
* 鉀? filtra pistas por fecha, distancia total...
* 馃柣 muestra las im谩genes georreferenciadas que se hallen en el directorio seleccionado
* 馃枾 genera enlaces p煤blicos para una pista/carpeta. Este enlace se puede usar si el archivo/carpeta es compartido mediante un enlace p煤blico
* 馃梺 permite mover las pistas seleccionadas
* 馃棤 puede corregir la elevaci贸n de las pistas si el servidor cuenta con SRTM.py (gpxelevations)
* 鈿? puede hacer comparaciones globales de varias pistas
* 鈿? puede hacer comparaciones visuales de partes divergentes en pistas similares
* 馃?? permite que los usuarios a帽adan servidores de teselas personales
* 鈿? guarda/restaura los valores de opciones de usuario
* 馃枍 permite al usuario establecer manualmente los colores de las pistas
* 馃晳 detecta la zona horaria del navegador
* 馃棳 carga los s铆mbolos de marcadores extra de GpxEdit si est谩 instalada
* 馃敀 funciona con carpetas de datos cifrados (cifrado del lado del servidor)
* 馃崅 utiliza con orgullo Leaflet con muchos plugins para mostrar el mapa
* 馃柎 es compatible con bases de datos SQLite, MySQL y PostgreSQL
* 馃梺 a帽ade la posibilidad de editar ficheros .gpx directamente desde la aplicaci贸n Archivos

Esta aplicaci贸n est谩 probada en Nextcloud 15 con Firefox 57+ y Chromium.

Esta aplicaci贸n est谩 en (lento) desarrollo.

Enlace al sitio web de aplicaci贸n de Nextcloud : https://apps.nextcloud.com/apps/gpxpod

## Instalaci贸n

Consulta la [Documentaci贸n de Administraci贸n](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) para los detalles de la instalaci贸n

## Incidencias conocidas

* mala gesti贸n de nombres de archivos que incluyan comillas simples o dobles
* *ADVERTENCIA*, la conversi贸n kml NO funcionar谩 con archivos kml recientes que usen la etiqueta de extensi贸n propietaria 芦gx:track禄.

Se agradece cualquier comentario.