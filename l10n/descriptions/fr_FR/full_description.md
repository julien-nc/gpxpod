# Application Nextcloud GpxPod

Afficher, analyser, comparer et partager des fichiers de traces GPS.

🌍 Aidez-nous à traduire cette application sur [le project Crowdin Nextcloud Gpxpod](https://crowdin.com/project/gpxpod).

GpxPod :

* 🗺 peut afficher des fichiers gpx/kml/tcx/igc/fit placés n'importe où dans vos fichiers, fichiers partagés avec vous, fichiers dans des dossiers partagés avec vous. Les fichiers fit seront convertis et affichés uniquement si **GpsBabel** est trouvé sur le système du serveur
* 📏 supporte les systèmes de mesure métriques, anglais et nautique
* 🗠 dessine un graphique interactif d'altitude, de vitesse ou de rythme
* 🗠 peut colorier les lignes des traces en fonction de la vitesse, de l'altitude ou du rythme
* affiche les statistiques des traces
* ⛛ filtre les traces par date, distance totale...
* 🖻 affiche les images géotaggées trouvées dans le répertoire sélectionné
* 🖧 génère des liens publics vers un fichier/dossier. Ce lien peut être utilisé si le fichier/dossier est partagé par un lien public
* 🗁 vous permet de déplacer les fichiers de piste sélectionnés
* 🗠 peut corriger les altitudes des traces si SRTM.py (gpxelevations) est trouvé sur le système du serveur
* ⚖ peut faire une comparaison globale de plusieurs traces
* ⚖ peut faire une comparaison visuelle de parties divergentes de paires de traces similaires
* 🀆 permet aux utilisateurs d'ajouter des serveurs personnels de tuiles de carte
* ⚙ sauvegarde/restaure les valeurs d'options de l'utilisateur
* 🖍 permet à l'utilisateur de définir manuellement la couleur des traces
* 🕑 détecte le fuseau horaire du navigateur
* 🗬 charge les symboles de marqueur supplémentaires de GpxEdit si installé
* 🔒 fonctionne avec un dossier de données chiffré (chiffrement côté serveur)
* 🍂 utilise fièrement Leaflet avec plein de plugins pour afficher la carte
* 🖴 est compatible avec les bases de données SQLite, MySQL et PostgreSQL
* 🗁 ajoute la possibilité de voir les fichiers .gpx directement à partir de l'application "Fichiers"

Cette application est testée sur Nextcloud 16 avec Firefox et Chromium.

Cette appli est en développement (lent).

## Installation

Voir l' [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) pour les détails sur l'installation

## Dons

Plus de détails dans le [wiki du project](https://gitlab.com/eneiluj/gpxpod-oc/wikis/home#donation).

## Problèmes connus

* [RESOLU] mauvaise gestion des noms de fichiers incluant des guillemets simples ou doubles
* *ATTENTION*, la conversion kml ne fonctionnera PAS avec les fichiers kml récents utilisant le tag propriétaire "gx:track".

Tout retour sera apprécié.