# Aplikacja GpxPod Nextcloud

Wyświetla, analizuje, porównuje i udostępnia utworzone pliki GPS.

🌍 Pomóż nam przetłumaczyć tę aplikację w [projekcie GpxPod Crowdin](https://crowdin.com/project/gpxpod).

GpxPod :

* 🗺 może wyświetlać pliki gpx/kml/tcx/igc/fit z dowolnego miejsca, pliki udostępnione oraz pliki z udostępnionych katalogów. Pliki fit zostaną przekonwertowane i wyświetlone tylko wtedy, gdy w systemie serwera zostanie znaleziony **GpsBabel**
* 📏 obsługuje systemy miar metrycznych, angielskich i morskich
* 🗠 rysuje wykres interaktywny wysokości, prędkości lub tempa
* 🗠 może kolorować linie według prędkości, wysokości lub tempa
* 🗠 pokazuje statystyki trasy
* ⛛ filtruje trasy według daty, całkowitej odległości...
* 🖻 wyświetla geotagowane zdjęcia znalezione w wybranym katalogu
* 🖧 generuje publiczne linki do wskazanej trasy/katalogu. Tego łącza można użyć, jeśli plik/katalog jest udostępniany przez link publiczny
* 🗁 pozwala przenosić wybrane pliki tras
* 🗠 może poprawić wzniesienia tras, jeśli SRTM.py (gpxelevations) zostanie znaleziony w systemie serwera
* ⚖ może dokonać globalnego porównania wielu tras
* ⚖ może dokonać porównanie wizualne rozbieżnych elementów podobnych tras
* 🀆 umożliwia użytkownikom dodawanie osobistych serwerów kafelkowych map
* ⚙ zapisuje/przywraca wartości opcji użytkownika
* 🖍 umożliwia użytkownikowi ręczne ustawienie kolorów linii trasy
* 🕑 wykrywa strefę czasową przeglądarki
* 🗬 ładuje dodatkowe symbole znaczników z GpxEdit, jeśli są zainstalowane
* 🔒 działa z zaszyfrowanym katalogiem danych (szyfrowanie po stronie serwera)
* 🍂 dumnie korzysta z dużej ilości pluginów Leaflet, aby wyświetlić mapę
* 🖴 jest kompatybilny z bazami danych SQLite, MySQL i PostgreSQL
* 🗁 dodaje możliwość przeglądania plików .gpx bezpośrednio z "Pliki" aplikacji

Ta aplikacja jest testowana na Nextcloud 15 z Firefoxem 57+ i Chromium.

Ta aplikacja jest w fazie (powolnym) rozwoju.

Link do strony internetowej aplikacji Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Instalacja

Zobacz [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc), aby uzyskać szczegóły instalacji

## Znane problemy

* złe nazewnictwo plików, w tym proste lub podwójne cudzysłowy
* *OSTRZEŻENIE*, konwersja kml NIE będzie działać z nowymi plikami kml przy użyciu zastrzeżonego znacznika rozszerzenia "gx:track".

Będą doceniane wszelkie opinie.