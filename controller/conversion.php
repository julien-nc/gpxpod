<?php


function utcdate() {
    return gmdate("Y-m-d\Th:i:s\Z");
}

// get decimal coordinate from exif data
function getDecimalCoords($exifCoord, $hemi) {
    $degrees = count($exifCoord) > 0 ? exifCoordToNumber($exifCoord[0]) : 0;
    $minutes = count($exifCoord) > 1 ? exifCoordToNumber($exifCoord[1]) : 0;
    $seconds = count($exifCoord) > 2 ? exifCoordToNumber($exifCoord[2]) : 0;

    $flip = ($hemi === 'W' or $hemi === 'S') ? -1 : 1;

    return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

// parse the coordinate string to calculate the float value
function exifCoordToNumber($coordPart) {
    $parts = explode('/', $coordPart);

    if (count($parts) <= 0)
        return 0;

    if (count($parts) === 1)
        return $parts[0];

    return floatval($parts[0]) / floatval($parts[1]);
}

function createDomGpxWithHeaders() {
    $dom_gpx = new DOMDocument('1.0', 'UTF-8');
    $dom_gpx->formatOutput = true;

    //root node
    $gpx = $dom_gpx->createElement('gpx');
    $gpx = $dom_gpx->appendChild($gpx);

    $gpx_version = $dom_gpx->createAttribute('version');
    $gpx->appendChild($gpx_version);
    $gpx_version_text = $dom_gpx->createTextNode('1.0');
    $gpx_version->appendChild($gpx_version_text);

    $gpx_creator = $dom_gpx->createAttribute('creator');
    $gpx->appendChild($gpx_creator);
    $gpx_creator_text = $dom_gpx->createTextNode('GpxPod conversion tool');
    $gpx_creator->appendChild($gpx_creator_text);

    $gpx_xmlns_xsi = $dom_gpx->createAttribute('xmlns:xsi');
    $gpx->appendChild($gpx_xmlns_xsi);
    $gpx_xmlns_xsi_text = $dom_gpx->createTextNode('http://www.w3.org/2001/XMLSchema-instance');
    $gpx_xmlns_xsi->appendChild($gpx_xmlns_xsi_text);

    $gpx_xmlns = $dom_gpx->createAttribute('xmlns');
    $gpx->appendChild($gpx_xmlns);
    $gpx_xmlns_text = $dom_gpx->createTextNode('http://www.topografix.com/GPX/1/0');
    $gpx_xmlns->appendChild($gpx_xmlns_text);

    $gpx_xsi_schemaLocation = $dom_gpx->createAttribute('xsi:schemaLocation');
    $gpx->appendChild($gpx_xsi_schemaLocation);
    $gpx_xsi_schemaLocation_text = $dom_gpx->createTextNode('http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd');
    $gpx_xsi_schemaLocation->appendChild($gpx_xsi_schemaLocation_text);

    $gpx_time = $dom_gpx->createElement('time');
    $gpx_time = $gpx->appendChild($gpx_time);
    $gpx_time_text = $dom_gpx->createTextNode(utcdate());
    $gpx_time->appendChild($gpx_time_text);

    return $dom_gpx;
}

function jpgToGpx($jpgFilePath, $fileName) {
    $result = '';
    $exif = \exif_read_data($jpgFilePath, 0, true);
    if (    isset($exif['GPS'])
        and isset($exif['GPS']['GPSLongitude'])
        and isset($exif['GPS']['GPSLatitude'])
        and isset($exif['GPS']['GPSLatitudeRef'])
        and isset($exif['GPS']['GPSLongitudeRef'])
    ) {
        $lon = getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
        $lat = getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);

        $dom_gpx = createDomGpxWithHeaders();
		$gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

        $gpx_wpt = $dom_gpx->createElement('wpt');
        $gpx_wpt = $gpx->appendChild($gpx_wpt);

        $gpx_wpt_lat = $dom_gpx->createAttribute('lat');
        $gpx_wpt->appendChild($gpx_wpt_lat);
        $gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
        $gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

        $gpx_wpt_lon = $dom_gpx->createAttribute('lon');
        $gpx_wpt->appendChild($gpx_wpt_lon);
        $gpx_wpt_lon_text = $dom_gpx->createTextNode($lon);
        $gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

        $gpx_name = $dom_gpx->createElement('name');
        $gpx_name = $gpx_wpt->appendChild($gpx_name);
        $gpx_name_text = $dom_gpx->createTextNode($fileName);
        $gpx_name->appendChild($gpx_name_text);

        $gpx_symbol = $dom_gpx->createElement('sym');
        $gpx_symbol = $gpx_wpt->appendChild($gpx_symbol);
        $gpx_symbol_text = $dom_gpx->createTextNode('Flag, Blue');
        $gpx_symbol->appendChild($gpx_symbol_text);

        $result = $dom_gpx->saveXML();
    }
    return $result;
}

function igcToGpx($igcFilePath,$trackOptions){
    $dom_gpx = createDomGpxWithHeaders();
    $gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);
    
    $hasBaro = false;
    $fh = fopen($igcFilePath,'r');
    $date = new DateTime();
    $date->setTimestamp(0);
    //Parse header and detect baro altitude
    while($line =  fgets($fh)){
        if(substr($line,0,5)==='HFDTE'){
            $date->setTimestamp(strtotime(
                    substr($line,5,2).'.'
                    .substr($line,7,2).'.'
                    .(intval(substr($line,9,2))<70?'20':'19').substr($line,9,2)
                ));
        }else if(substr($line,0,10)==='HFPLTPILOT'){
            $author = trim(explode(':', $line,2)[1]);
            $gpx_author = $dom_gpx->createElement('author');
            $gpx->insertBefore($gpx_author,$dom_gpx->getElementsByTagName('time')->item(0));
            $gpx_author_text = $dom_gpx->createTextNode($author);
            $gpx_author->appendChild($gpx_author_text);
        }else if($line{0}==='B'){
            $hasBaro = intval(substr($line, 25,5))!==0;
            if($hasBaro){
                rewind($fh);
                break;
            }
        }
    }
    $includeGnss = !$hasBaro || $trackOptions!=='pres';
    $includeBaro = $hasBaro && $trackOptions!=='gnss';
    
    if($includeGnss){
        $gpx_trk = $dom_gpx->createElement('trk');
        $gpx_trk_name = $dom_gpx->createElement('name');
        $gpx_trk_name->nodeValue = 'GNSSALTTRK';
        $gpx_trk->appendChild($gpx_trk_name);
        $gpx_trkseg = $dom_gpx->createElement('trkseg');
        $gpx_trk->appendChild($gpx_trkseg);
        $gpx->appendChild($gpx_trk);
    }
    
    if($includeBaro){
        $gpx_trk_baro = $dom_gpx->createElement('trk');
        $gpx_trk_baro_name = $dom_gpx->createElement('name');
        $gpx_trk_baro_name->nodeValue = 'PRESALTTRK';
        $gpx_trk_baro->appendChild($gpx_trk_baro_name);
        $gpx->appendChild($gpx_trk_baro);
        $gpx_trkseg_baro = $dom_gpx->createElement('trkseg');
        $gpx_trk_baro->appendChild($gpx_trkseg_baro); 
    }
    
    //Parse tracklog
    while($line =  fgets($fh)){
        $type = $line{0};
        if($type==='B'){
            $minutesLat = round((floatval('0.'.substr($line, 9,5))/60)*100,5);
            $lat = floatval(intval(substr($line, 7,2))+$minutesLat)*($line{14}==='N'?1:-1);
            $minutesLon = round((floatval('0.'.substr($line, 18,5))/60)*100,5);
            $lon = floatval(intval(substr($line, 15,3))+$minutesLon)*($line{23}==='E'?1:-1);
            
            $gpx_trkpt = $dom_gpx->createElement('trkpt');
            
            if($includeGnss){
                $gpx_trkseg->appendChild($gpx_trkpt);
            }
            
            $gpx_wpt_lat = $dom_gpx->createAttribute('lat');
            $gpx_trkpt->appendChild($gpx_wpt_lat);
            $gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
            $gpx_wpt_lat->appendChild($gpx_wpt_lat_text);
            
            $gpx_wpt_lon = $dom_gpx->createAttribute('lon');
            $gpx_trkpt->appendChild($gpx_wpt_lon);
            $gpx_wpt_lon_text = $dom_gpx->createTextNode($lon);
            $gpx_wpt_lon->appendChild($gpx_wpt_lon_text);
            
            $gpx_ele = $dom_gpx->createElement('ele');
            $gpx_trkpt->appendChild($gpx_ele);
            $gpx_ele_text = $dom_gpx->createTextNode(intval(substr($line, 30,5)));
            $gpx_ele->appendChild($gpx_ele_text);
            
            $gpx_time = $dom_gpx->createElement('time');
            $gpx_trkpt->appendChild($gpx_time);
            $gpx_time_text = $dom_gpx->createTextNode(
                    $date->format('Y-m-d').
                    'T'.substr($line,1,2).':'.substr($line,3,2).':'.substr($line,5,2)
                );
            $gpx_time->appendChild($gpx_time_text);
            
            if($includeBaro){
                $gpx_trkpt_baro = $gpx_trkpt->cloneNode(true);
                $ele = $gpx_trkpt_baro->getElementsByTagName('ele')->item(0);
                $ele->nodeValue = intval(substr($line, 25,5));
                $gpx_trkseg_baro->appendChild($gpx_trkpt_baro);
            }
        }
    }
    
    return $dom_gpx->saveXML();
}

function kmlToGpx($kmlFilePath) {
    $kmlcontent = file_get_contents($kmlFilePath);
    $dom_kml = new DOMDocument();
    $dom_kml->loadXML($kmlcontent);

    $dom_gpx = createDomGpxWithHeaders();
    $gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

    // placemarks
    $names = array();
    foreach ($dom_kml->getElementsByTagName('Placemark') as $placemark) {
        //name
        foreach ($placemark->getElementsByTagName('name') as $name) {
            $name  = $name->nodeValue;
            //check if the key exists
            if (array_key_exists($name, $names)) {
                //increment the value
                ++$names[$name];
                $name = $name." ({$names[$name]})";
            } else {
                $names[$name] = 0;
            }
        }
        //description
        foreach ($placemark->getElementsByTagName('description') as $description) {
            $description  = $description->nodeValue;
        }
        foreach ($placemark->getElementsByTagName('Point') as $point) {
            foreach ($point->getElementsByTagName('coordinates') as $coordinates) {
                //add the marker
                $coordinate = $coordinates->nodeValue;
                $coordinate = str_replace(" ", "", $coordinate);//trim white space
                $latlng = explode(",", $coordinate);

                if (($lat = $latlng[1]) && ($lng = $latlng[0])) {
                    $gpx_wpt = $dom_gpx->createElement('wpt');
                    $gpx_wpt = $gpx->appendChild($gpx_wpt);

                    $gpx_wpt_lat = $dom_gpx->createAttribute('lat');
                    $gpx_wpt->appendChild($gpx_wpt_lat);
                    $gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
                    $gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

                    $gpx_wpt_lon = $dom_gpx->createAttribute('lon');
                    $gpx_wpt->appendChild($gpx_wpt_lon);
                    $gpx_wpt_lon_text = $dom_gpx->createTextNode($lng);
                    $gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

                    $gpx_time = $dom_gpx->createElement('time');
                    $gpx_time = $gpx_wpt->appendChild($gpx_time);
                    $gpx_time_text = $dom_gpx->createTextNode(utcdate());
                    $gpx_time->appendChild($gpx_time_text);

                    $gpx_name = $dom_gpx->createElement('name');
                    $gpx_name = $gpx_wpt->appendChild($gpx_name);
                    $gpx_name_text = $dom_gpx->createTextNode($name);
                    $gpx_name->appendChild($gpx_name_text);

                    $gpx_desc = $dom_gpx->createElement('desc');
                    $gpx_desc = $gpx_wpt->appendChild($gpx_desc);
                    $gpx_desc_text = $dom_gpx->createTextNode($description);
                    $gpx_desc->appendChild($gpx_desc_text);

                    $gpx_sym = $dom_gpx->createElement('sym');
                    $gpx_sym = $gpx_wpt->appendChild($gpx_sym);
                    $gpx_sym_text = $dom_gpx->createTextNode('Waypoint');
                    $gpx_sym->appendChild($gpx_sym_text);

                    if (count($latlng) > 2) {
                        $gpx_ele = $dom_gpx->createElement('ele');
                        $gpx_ele = $gpx_wpt->appendChild($gpx_ele);
                        $gpx_ele_text = $dom_gpx->createTextNode($latlng[2]);
                        $gpx_ele->appendChild($gpx_ele_text);
                    }
                }
            }
        }
        foreach ($placemark->getElementsByTagName('Polygon') as $lineString) {
            $outbounds = $lineString->getElementsByTagName('outerBoundaryIs');
            foreach ($outbounds as $outbound) {
                foreach ($outbound->getElementsByTagName('coordinates') as $coordinates) {
                    //add the new track
                    $gpx_trk = $dom_gpx->createElement('trk');
                    $gpx_trk = $gpx->appendChild($gpx_trk);

                    $gpx_name = $dom_gpx->createElement('name');
                    $gpx_name = $gpx_trk->appendChild($gpx_name);
                    $gpx_name_text = $dom_gpx->createTextNode($name);
                    $gpx_name->appendChild($gpx_name_text);

                    $gpx_trkseg = $dom_gpx->createElement('trkseg');
                    $gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

                    $coordinates = trim($coordinates->nodeValue);
                    $coordinates = preg_split("/[\s\r\n]+/", $coordinates); //split the coords by new line
                    foreach ($coordinates as $coordinate) {
                        $latlng = explode(",", $coordinate);

                        if (($lat = $latlng[1]) && ($lng = $latlng[0])) {
                            $gpx_trkpt = $dom_gpx->createElement('trkpt');
                            $gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

                            $gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
                            $gpx_trkpt->appendChild($gpx_trkpt_lat);
                            $gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat);
                            $gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);

                            $gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
                            $gpx_trkpt->appendChild($gpx_trkpt_lon);
                            $gpx_trkpt_lon_text = $dom_gpx->createTextNode($lng);
                            $gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);

                            $gpx_time = $dom_gpx->createElement('time');
                            $gpx_time = $gpx_trkpt->appendChild($gpx_time);
                            $gpx_time_text = $dom_gpx->createTextNode(utcdate());
                            $gpx_time->appendChild($gpx_time_text);

                            if (count($latlng) > 2) {
                                $gpx_ele = $dom_gpx->createElement('ele');
                                $gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
                                $gpx_ele_text = $dom_gpx->createTextNode($latlng[2]);
                                $gpx_ele->appendChild($gpx_ele_text);
                            }
                        }
                    }
                }
            }
        }
        foreach ($placemark->getElementsByTagName('LineString') as $lineString) {
            foreach ($lineString->getElementsByTagName('coordinates') as $coordinates) {
                //add the new track
                $gpx_trk = $dom_gpx->createElement('trk');
                $gpx_trk = $gpx->appendChild($gpx_trk);

                $gpx_name = $dom_gpx->createElement('name');
                $gpx_name = $gpx_trk->appendChild($gpx_name);
                $gpx_name_text = $dom_gpx->createTextNode($name);
                $gpx_name->appendChild($gpx_name_text);

                $gpx_trkseg = $dom_gpx->createElement('trkseg');
                $gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

                $coordinates = trim($coordinates->nodeValue);
                $coordinates = preg_split("/[\s\r\n]+/", $coordinates); //split the coords by new line
                foreach ($coordinates as $coordinate) {
                    $latlng = explode(",", $coordinate);

                    if (($lat = $latlng[1]) && ($lng = $latlng[0])) {
                        $gpx_trkpt = $dom_gpx->createElement('trkpt');
                        $gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

                        $gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
                        $gpx_trkpt->appendChild($gpx_trkpt_lat);
                        $gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat);
                        $gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);

                        $gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
                        $gpx_trkpt->appendChild($gpx_trkpt_lon);
                        $gpx_trkpt_lon_text = $dom_gpx->createTextNode($lng);
                        $gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);

                        $gpx_time = $dom_gpx->createElement('time');
                        $gpx_time = $gpx_trkpt->appendChild($gpx_time);
                        $gpx_time_text = $dom_gpx->createTextNode(utcdate());
                        $gpx_time->appendChild($gpx_time_text);

                        if (count($latlng) > 2) {
                            $gpx_ele = $dom_gpx->createElement('ele');
                            $gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
                            $gpx_ele_text = $dom_gpx->createTextNode($latlng[2]);
                            $gpx_ele->appendChild($gpx_ele_text);
                        }
                    }
                }
            }
        }
    }

    return $dom_gpx->saveXML();
}

function unicsvToGpx($csvFilePath) {
    $result = '';
    $dom_gpx = createDomGpxWithHeaders();
    $gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

    $csv = array_map('str_getcsv', file($csvFilePath, FILE_SKIP_EMPTY_LINES));
    $keys = array_shift($csv);

    foreach ($csv as $i=>$row) {
        $csv[$i] = array_combine($keys, $row);
    }

    foreach ($csv as $line) {
        $lat = $line['Latitude'];
        $lon = $line['Longitude'];

        $gpx_wpt = $dom_gpx->createElement('wpt');
        $gpx_wpt = $gpx->appendChild($gpx_wpt);

        $gpx_wpt_lat = $dom_gpx->createAttribute('lat');
        $gpx_wpt->appendChild($gpx_wpt_lat);
        $gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
        $gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

        $gpx_wpt_lon = $dom_gpx->createAttribute('lon');
        $gpx_wpt->appendChild($gpx_wpt_lon);
        $gpx_wpt_lon_text = $dom_gpx->createTextNode($lon);
        $gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

        if (array_key_exists('Symbol', $line)) {
            $gpx_symbol = $dom_gpx->createElement('sym');
            $gpx_symbol = $gpx_wpt->appendChild($gpx_symbol);
            $gpx_symbol_text = $dom_gpx->createTextNode($line['Symbol']);
            $gpx_symbol->appendChild($gpx_symbol_text);
        }
        if (array_key_exists('Name', $line)) {
            $gpx_name = $dom_gpx->createElement('name');
            $gpx_name = $gpx_wpt->appendChild($gpx_name);
            $gpx_name_text = $dom_gpx->createTextNode($line['Name']);
            $gpx_name->appendChild($gpx_name_text);
        }

    }
    $result = $dom_gpx->saveXML();

    return $result;
}

function tcxToGpx($tcxFilePath) {
    $tcxcontent = file_get_contents($tcxFilePath);
    $dom_tcx = new DOMDocument();
    $dom_tcx->loadXML($tcxcontent);

    $dom_gpx = createDomGpxWithHeaders();
    $gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

    foreach ($dom_tcx->getElementsByTagName('Course') as $course) {
        $name = '';
        foreach ($course->getElementsByTagName('Name') as $name) {
            $name  = $name->nodeValue;
        }
        //add the new track
        $gpx_trk = $dom_gpx->createElement('trk');
        $gpx_trk = $gpx->appendChild($gpx_trk);

        $gpx_name = $dom_gpx->createElement('name');
        $gpx_name = $gpx_trk->appendChild($gpx_name);
        $gpx_name_text = $dom_gpx->createTextNode($name);
        $gpx_name->appendChild($gpx_name_text);

        foreach ($course->getElementsByTagName('Track') as $track) {

            $gpx_trkseg = $dom_gpx->createElement('trkseg');
            $gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

            foreach ($track->getElementsByTagName('Trackpoint') as $trackpoint) {

                $gpx_trkpt = $dom_gpx->createElement('trkpt');
                $gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

                foreach ($trackpoint->getElementsByTagName('Time') as $time) {
                    $gpx_time = $dom_gpx->createElement('time');
                    $gpx_time = $gpx_trkpt->appendChild($gpx_time);
                    $gpx_time_text = $dom_gpx->createTextNode($time->nodeValue);
                    $gpx_time->appendChild($gpx_time_text);
                }
                foreach ($trackpoint->getElementsByTagName('Position') as $position) {
                    foreach ($trackpoint->getElementsByTagName('LatitudeDegrees') as $lat) {
                        $gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
                        $gpx_trkpt->appendChild($gpx_trkpt_lat);
                        $gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat->nodeValue);
                        $gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);
                    }
                    foreach ($trackpoint->getElementsByTagName('LongitudeDegrees') as $lon) {
                        $gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
                        $gpx_trkpt->appendChild($gpx_trkpt_lon);
                        $gpx_trkpt_lon_text = $dom_gpx->createTextNode($lon->nodeValue);
                        $gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);
                    }
                }
                foreach ($trackpoint->getElementsByTagName('AltitudeMeters') as $ele) {
                    $gpx_ele = $dom_gpx->createElement('ele');
                    $gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
                    $gpx_ele_text = $dom_gpx->createTextNode($ele->nodeValue);
                    $gpx_ele->appendChild($gpx_ele_text);
                }
            }
        }
    }

    return $dom_gpx->saveXML();
}

?>
