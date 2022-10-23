# Aplicación GpxPod para Nextcloud

Muestra, analiza, compara y comparte archivos de pistas GPS.

🌍 Ayúdanos a traducir esta aplicación en el [proyecto de GpxPod en Crowdin](https://crowdin.com/project/gpxpod).

GpxPod:

* 🗺 puede mostrar archivos gpx/kml/tcx/igc/fit en cualquier lugar de tus archivos, en archivos compartidos contigo o en archivos en carpetas compartidas contigo. los archivos fit se convertirán y se mostrarán solo si **GpsBabel** se encuentra en el servidor
* 📏 soporta los sistemas métrico, anglosajón y náutico
* · Traza una tabla interactiva de elevación, velocidad o ritmo
* Puede colorear las líneas de las pistas por velocidad, elevación o ritmo
* 🗠 muestra estadísticas de la pista
* ⛛ filtra pistas por fecha, distancia total...
* 🖻 muestra las imágenes georreferenciadas que se hallen en el directorio seleccionado
* 🖧 genera enlaces públicos para una pista/carpeta. Este enlace se puede usar si el archivo/carpeta es compartido mediante un enlace público
* 🗁 permite mover las pistas seleccionadas
* 🗠 puede corregir la elevación de las pistas si el servidor cuenta con SRTM.py (gpxelevations)
* ⚖ puede hacer comparaciones globales de varias pistas
* ⚖ puede hacer comparaciones visuales de partes divergentes en pistas similares
* 🀆 permite que los usuarios añadan servidores de teselas personales
* ⚙ guarda/restaura los valores de opciones de usuario
* 🖍 permite al usuario establecer manualmente los colores de las pistas
* 🕑 detecta la zona horaria del navegador
* 🗬 carga los símbolos de marcadores extra de GpxEdit si está instalada
* 🔒 funciona con carpetas de datos cifrados (cifrado del lado del servidor)
* 🍂 utiliza con orgullo Leaflet con muchos plugins para mostrar el mapa
* 🖴 es compatible con bases de datos SQLite, MySQL y PostgreSQL
* 🗁 añade la posibilidad de editar ficheros .gpx directamente desde la aplicación Archivos

Esta aplicación está probada en Nextcloud 15 con Firefox 57+ y Chromium.

Esta aplicación está en (lento) desarrollo.

Enlace al sitio web de aplicación de Nextcloud : https://apps.nextcloud.com/apps/gpxpod

## Instalación

Consulta la [Documentación de Administración](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) para los detalles de la instalación

## Incidencias conocidas

* mala gestión de nombres de archivos que incluyan comillas simples o dobles
* *ADVERTENCIA*, la conversión kml NO funcionará con archivos kml recientes que usen la etiqueta de extensión propietaria «gx:track».

Se agradece cualquier comentario.