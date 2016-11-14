#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys, os
try:
    from PIL import Image
    from PIL.ExifTags import TAGS, GPSTAGS
except Exception as e:
    sys.exit(1)

def get_exif_data(image):
    """Returns a dictionary from the exif data of an PIL Image item. Also converts the GPS Tags"""
    exif_data = {}
    #info = image._getexif()
    info = getattr(image, '_getexif', lambda: None)()
    if info:
        for tag, value in info.items():
            decoded = TAGS.get(tag, tag)
            if decoded == "GPSInfo":
                gps_data = {}
                for t in value:
                    sub_decoded = GPSTAGS.get(t, t)
                    gps_data[sub_decoded] = value[t]

                exif_data[decoded] = gps_data
            else:
                exif_data[decoded] = value

    return exif_data

def _get_if_exist(data, key):
    if key in data:
        return data[key]

    return None

def _convert_to_degress(value):
    """Helper function to convert the GPS coordinates stored in the EXIF to degress in float format"""
    d0 = value[0][0]
    d1 = value[0][1]
    d = float(d0) / float(d1)

    m0 = value[1][0]
    m1 = value[1][1]
    m = float(m0) / float(m1)

    s0 = value[2][0]
    s1 = value[2][1]
    s = float(s0) / float(s1)

    return d + (m / 60.0) + (s / 3600.0)

def get_lat_lon(exif_data):
    """Returns the latitude and longitude, if available, from the provided exif_data (obtained through get_exif_data above)"""
    lat = None
    lon = None

    if "GPSInfo" in exif_data:
        gps_info = exif_data["GPSInfo"]

        gps_latitude = _get_if_exist(gps_info, "GPSLatitude")
        gps_latitude_ref = _get_if_exist(gps_info, 'GPSLatitudeRef')
        gps_longitude = _get_if_exist(gps_info, 'GPSLongitude')
        gps_longitude_ref = _get_if_exist(gps_info, 'GPSLongitudeRef')

        if gps_latitude and gps_latitude_ref and gps_longitude and gps_longitude_ref:
            lat = _convert_to_degress(gps_latitude)
            if gps_latitude_ref != "N":
                lat = 0 - lat

            lon = _convert_to_degress(gps_longitude)
            if gps_longitude_ref != "E":
                lon = 0 - lon

    return lat, lon


################
# Example ######
################
if __name__ == "__main__":
    path = sys.argv[1]
    if not os.path.exists(path):
        sys.stderr.write('%s does not exist'%path)
        sys.exit(1)
    files = [ os.path.join(path,f) for f in os.listdir(path)
            if (os.path.isfile(os.path.join(path,f)) and (f.endswith('.jpg') or f.endswith('.JPG')) ) ]
    of = open('%s/pictures.txt'%path, 'w')
    for onefile in files:
        try:
            image = Image.open(onefile) # load an image through PIL's Image object
            exif_data = get_exif_data(image)
            latlng = get_lat_lon(exif_data)
            if latlng != (None, None):
                #print('"%s" : [%s, %s]' % (os.path.basename(onefile), latlng[0], latlng[1]))
                of.write('"%s" : [%s, %s],\n' % (os.path.basename(onefile), latlng[0], latlng[1]))
        except Exception as e:
            print(e)

    of.close()
