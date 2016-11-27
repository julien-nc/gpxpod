#!/usr/bin/env python
# -*- coding: utf-8 -*-

from __future__ import print_function
import sys, math, os
import json
import traceback
import gpxpy, gpxpy.gpx, geojson
MP_AVAILABLE=True
try:
    from multiprocessing import Pool
except Exception as e:
    MP_AVAILABLE = False

import re
DISTANCE_BETWEEN_SHORT_POINTS=300

def format_time_seconds(time_s):
    if not time_s:
        return 'n/a'
    minutes = math.floor(time_s / 60.)
    hours = math.floor(minutes / 60.)

    return '%s:%s:%s' % (str(int(hours)).zfill(2), str(int(minutes % 60)).zfill(2), str(int(time_s % 60)).zfill(2))

def distance(p1, p2):
    """ return distance between these two gpx points in meters
    """

    lat1 = p1.latitude
    long1 = p1.longitude
    lat2 = p2.latitude
    long2 = p2.longitude

    if (lat1 == lat2 and long1 == long2):
        return 0

    # Convert latitude and longitude to
    # spherical coordinates in radians.
    degrees_to_radians = math.pi/180.0

    # phi = 90 - latitude
    phi1 = (90.0 - lat1)*degrees_to_radians
    phi2 = (90.0 - lat2)*degrees_to_radians

    # theta = longitude
    theta1 = long1*degrees_to_radians
    theta2 = long2*degrees_to_radians

    # Compute spherical distance from spherical coordinates.

    # For two locations in spherical coordinates
    # (1, theta, phi) and (1, theta, phi)
    # cosine( arc length ) =
    #    sin phi sin phi' cos(theta-theta') + cos phi cos phi'
    # distance = rho * arc length

    cos = (math.sin(phi1)*math.sin(phi2)*math.cos(theta1 - theta2) +
           math.cos(phi1)*math.cos(phi2))
    # why some cosinus are > than 1 ?
    if cos>1.0:
        cos=1.0
    arc = math.acos( cos )

    # Remember to multiply arc by the radius of the earth
    # in your favorite set of units to get length.
    return arc*6371000

def gpxTracksToGeojson(gpx_content, name):
    """ converts the gpx string input to a geojson string
    """
    gpx = gpxpy.parse(gpx_content)

    featureList = []
    for waypoint in gpx.waypoints:
        try:
            welevation = int(waypoint.elevation)
        except Exception as e:
            welevation = '???'
        wcmt = waypoint.comment or ''
        wdesc = waypoint.description or ''
        featureList.append(
            geojson.Feature(
                id=waypoint.name,
                properties={'elevation': welevation, 'comment': wcmt, 'description': wdesc},
                geometry=geojson.Point((waypoint.longitude, waypoint.latitude))
            )
        )

    for track in gpx.tracks:
        coordinates = []
        lastPoint = None
        trackname = track.name or ''
        for segment in track.segments:
            for point in segment.points:
                if not point.elevation:
                    point.elevation = 0
                nbsec = 0
                if point.time:
                    nbsec = point.time.second + (60 * point.time.minute) + (3600 * point.time.hour)
                coordinates.append((point.longitude, point.latitude, int(point.elevation), nbsec))

        featureList.append(
            geojson.Feature(
                id=trackname,
                properties=None,
                geometry=geojson.LineString(coordinates)
            )
        )
    for route in gpx.routes:
        coordinates = []
        lastPoint = None
        routename = route.name or ''
        for point in route.points:
            if not point.elevation:
                point.elevation = 0
            nbsec = 0
            if point.time:
                nbsec = point.time.second + (60 * point.time.minute) + (3600 * point.time.hour)
            coordinates.append((point.longitude, point.latitude, int(point.elevation), nbsec))

        featureList.append(
            geojson.Feature(
                id=routename,
                properties=None,
                geometry=geojson.LineString(coordinates)
            )
        )

    fc = geojson.FeatureCollection(featureList, id=name)
    return geojson.dumps(fc)

def gpxTracksToColoredGeojson(gpx_content, name):
    """ converts the gpx string input to a geojson string with one
    feature per segment. Each feature has slope, speed, elevation properties
    """
    # TODO add route processing
    speedMin = None
    slopeMin = None
    elevationMax = None
    elevationMin = None
    speedMax = None
    slopeMax = None

    gpx = gpxpy.parse(gpx_content)
    featureList = []

    for waypoint in gpx.waypoints:
        try:
            welevation = int(waypoint.elevation)
        except Exception as e:
            welevation = '???'
        wcmt = waypoint.comment or ''
        wdesc = waypoint.description or ''
        featureList.append(
            geojson.Feature(
                id=waypoint.name,
                properties={'elevation': welevation, 'comment': wcmt, 'description': wdesc},
                geometry=geojson.Point((waypoint.longitude, waypoint.latitude))
            )
        )

    for track in gpx.tracks:
        lastPoint = None
        speedMin = None
        slopeMin = None
        elevationMax = None
        elevationMin = None
        speedMax = None
        slopeMax = None
        pointIndex = 0
        for segment in track.segments:
            for point in segment.points:
                #print 'Point at ({0},{1}) -> {2}'.format(point.latitude, point.longitude, point.elevation)
                if lastPoint != None:
                    dist = distance(lastPoint, point)
                    if point.time != None and lastPoint.time != None:
                        try:
                            time = (point.time - lastPoint.time).total_seconds()
                        except AttributeError:
                            #print('Warning : Timedelta total_seconds() method missing, switching back to days and seconds')
                            d = point.time - lastPoint.time
                            time = (d.days*3600*24)+d.seconds
                        if time != 0:
                            speed = dist / time
                        else:
                            speed = 0
                    else:
                        time = 0
                        speed = 0
                    elevation = point.elevation
                    if point.elevation != None and lastPoint.elevation != None:
                        deniv = point.elevation - lastPoint.elevation
                    else:
                        elevation = 0
                        deniv = 0
                    if dist > 0 and pointIndex > 30:
                        slope = deniv / dist
                    else:
                        slope = 0

                    if slopeMin == None and slopeMax == None and speedMax == None and speedMin == None and elevationMin == None and elevationMax == None:
                        speedMin = speed
                        slopeMin = slope
                        speedMax = speed
                        slopeMax = slope
                        elevationMin = elevation
                        elevationMax = elevation
                    else:
                        if elevation > elevationMax:
                            elevationMax = elevation
                        elif elevation < elevationMin:
                            elevationMin = elevation
                        if speed > speedMax:
                            speedMax = speed
                        elif speed < speedMin:
                            speedMin = speed
                        if slope > slopeMax:
                            slopeMax = slope
                        elif slope < slopeMin:
                            slopeMin = slope


                    properties={'id':'%s-%s'%(pointIndex-1, pointIndex),
                                'elevation':float('%.2f'%elevation),
                                'speed':float('%.2f'%(speed*3.6)),
                                'slope':float('%.2f'%slope)
                               }

                    featureList.append(
                        geojson.Feature(
                            id='%s-%s'%(pointIndex-1, pointIndex),
                            properties=properties,
                            geometry=geojson.LineString([(lastPoint.longitude, lastPoint.latitude), (point.longitude, point.latitude)])
                        )
                    )
                lastPoint = point
                pointIndex += 1

    if speedMin == None:
        speedMin = 0
    if speedMax == None:
        speedMax = 1
    if slopeMin == None:
        slopeMin = 0
    if slopeMax == None:
        slopeMax = 1
    if elevationMin == None:
        elevationMin = 0
    if elevationMax == None:
        elevationMax = 1

    fc = geojson.FeatureCollection(featureList, id=name,
            properties={'elevationMin':float('%.2f'%elevationMin),'elevationMax':float('%.2f'%elevationMax),
                        'speedMin':float('%.2f'%(speedMin*3.6)),'speedMax':float('%.2f'%(speedMax*3.6)),
                        'slopeMin':float('%.2f'%slopeMin),'slopeMax':float('%.2f'%slopeMax),})
    return geojson.dumps(fc)

def getMarkerFromGpx(gpx_content, name):
    """ return marker string that will be used in the web interface
        each marker is : [x,y,filename,distance,duration,datebegin,dateend,poselevation,negelevation]
    """
    lat = '0'
    lon = '0'
    total_distance = 0
    total_duration = 'null'
    date_begin = 'null'
    date_end = 'null'
    pos_elevation = 0
    neg_elevation = 0
    min_elevation = 'null'
    max_elevation = 'null'
    max_speed = 0
    avg_speed = "null"
    moving_time = 0
    moving_avg_speed = 0
    stopped_time = 0
    north = None
    south = None
    east = None
    west = None
    shortPointList = []
    lastShortPoint = None

    isGoingUp = False
    lastDeniv = None
    upBegin = None
    downBegin = None

    gpx = gpxpy.parse(gpx_content)

    for track in gpx.tracks:
        for segment in track.segments:
            lastPoint = None
            pointIndex = 0
            lastDeniv = None
            for point in segment.points:
                lastTime = point.time
                if pointIndex == 0:
                    if lat == '0' and lon == '0':
                        lat = point.latitude
                        lon = point.longitude
                    date_begin = point.time
                    downBegin = point.elevation
                    min_elevation = point.elevation
                    max_elevation = point.elevation
                    if north is None:
                        north = point.latitude
                        south = point.latitude
                        east = point.longitude
                        west = point.longitude
                    shortPointList.append([point.latitude, point.longitude])
                    lastShortPoint = point

                if lastShortPoint != None:
                    # if the point is more than 500m far from the last in shortPointList
                    # we add it
                    if distance(lastShortPoint, point) > DISTANCE_BETWEEN_SHORT_POINTS:
                        shortPointList.append([point.latitude, point.longitude])
                        lastShortPoint = point
                if point.latitude > north:
                    north = point.latitude
                if point.latitude < south:
                    south = point.latitude
                if point.longitude > east:
                    east = point.longitude
                if point.longitude < west:
                    west = point.longitude
                if point.elevation < min_elevation:
                    min_elevation = point.elevation
                if point.elevation > max_elevation:
                    max_elevation = point.elevation
                if lastPoint != None and point.time and lastPoint.time:
                    t = (point.time - lastPoint.time).seconds
                    if t != 0:
                        speed = distance(lastPoint, point) / t
                        speed = speed / 1000
                        speed = speed * 3600
                        if speed > max_speed:
                            max_speed = speed
                if lastPoint != None:
                    total_distance += distance(lastPoint, point)
                if lastPoint != None and point.elevation and lastPoint.elevation:
                    deniv = point.elevation - lastPoint.elevation
                if lastDeniv != None and point.elevation and lastPoint and lastPoint.elevation:
                    # we start to go up
                    if (isGoingUp == False) and deniv > 0:
                        upBegin = lastPoint.elevation
                        isGoingUp = True
                        neg_elevation += (downBegin - lastPoint.elevation)
                    if (isGoingUp == True) and deniv < 0:
                        # we add the up portion
                        pos_elevation += (lastPoint.elevation - upBegin)
                        isGoingUp = False
                        downBegin = lastPoint.elevation
                # update vars
                if lastPoint != None and point.elevation and lastPoint.elevation:
                    lastDeniv = deniv

                lastPoint = point
                pointIndex += 1

        if not max_elevation:
            max_elevation = "null"
        else:
            max_elevation = '%.2f'%max_elevation
        if not min_elevation:
            min_elevation = "null"
        else:
            min_elevation = '%.2f'%min_elevation
        date_end = lastTime
        if date_end and date_begin:
            try:
                totsec = (date_end - date_begin).total_seconds()
            except AttributeError:
                print('Warning : Timedelta total_seconds() method missing, \
switching back to days and seconds', file=sys.stderr)
                d = date_end - date_begin
                totsec = (d.days*3600*24)+d.seconds
            #total_duration =str(date_end - date_begin)
            total_duration = '%.2i:%.2i:%.2i'%(totsec // 3600, totsec % 3600 // 60, totsec % 60)
            if totsec == 0:
                avg_speed = 0
            else:
                avg_speed = (total_distance) / totsec
                avg_speed = avg_speed / 1000
                avg_speed = avg_speed * 3600
                avg_speed = '%.2f'%avg_speed
        else:
            total_duration = "???"

        # auto analye from gpxpy
        # we consider every segment under 0.9 km/h as a stop time
        moving_time, stopped_time, moving_distance, stopped_distance, moving_max_speed = gpx.get_moving_data(0.9)

        # determination of real moving average speed from moving time
        moving_avg_speed = 0
        if moving_time != 0:
            moving_avg_speed = (total_distance) / moving_time
            moving_avg_speed = moving_avg_speed / 1000
            moving_avg_speed = moving_avg_speed * 3600
            moving_avg_speed = '%.2f'%moving_avg_speed

    if len(gpx.tracks) == 0:
        for route in gpx.routes:
            lastPoint = None
            pointIndex = 0
            for point in route.points:
                lastTime = point.time
                if pointIndex == 0:
                    lat = point.latitude
                    lon = point.longitude
                    date_begin = point.time
                    downBegin = point.elevation
                    min_elevation = point.elevation
                    max_elevation = point.elevation
                    if north is None:
                        north = point.latitude
                        south = point.latitude
                        east = point.longitude
                        west = point.longitude
                    shortPointList.append([point.latitude, point.longitude])
                    lastShortPoint = point

                if lastShortPoint != None:
                    # if the point is more than 500m far from the last in shortPointList
                    # we add it
                    if distance(lastShortPoint, point) > DISTANCE_BETWEEN_SHORT_POINTS:
                        shortPointList.append([point.latitude, point.longitude])
                        lastShortPoint = point
                if point.latitude > north:
                    north = point.latitude
                if point.latitude < south:
                    south = point.latitude
                if point.longitude > east:
                    east = point.longitude
                if point.longitude < west:
                    west = point.longitude
                if point.elevation < min_elevation:
                    min_elevation = point.elevation
                if point.elevation > max_elevation:
                    max_elevation = point.elevation
                if lastPoint != None and point.time and lastPoint.time:
                    t = (point.time - lastPoint.time).seconds
                    if t != 0:
                        speed = distance(lastPoint, point) / t
                        speed = speed / 1000
                        speed = speed * 3600
                        if speed > max_speed:
                            max_speed = speed
                if lastPoint != None and point.elevation and lastPoint.elevation:
                    deniv = point.elevation - lastPoint.elevation
                    total_distance += distance(lastPoint, point)
                if lastDeniv != None and point.elevation and lastPoint.elevation:
                    # we start to go up
                    if (isGoingUp == False) and deniv > 0:
                        upBegin = lastPoint.elevation
                        isGoingUp = True
                        neg_elevation += (downBegin - lastPoint.elevation)
                    if (isGoingUp == True) and deniv < 0:
                        # we add the up portion
                        pos_elevation += (lastPoint.elevation - upBegin)
                        isGoingUp = False
                        downBegin = lastPoint.elevation
                # update vars
                if lastPoint != None and point.elevation and lastPoint.elevation:
                    lastDeniv = deniv

                lastPoint = point
                pointIndex += 1

            if not max_elevation:
                max_elevation = "null"
            else:
                max_elevation = '%.2f'%max_elevation
            if not min_elevation:
                min_elevation = "null"
            else:
                min_elevation = '%.2f'%min_elevation
            date_end = lastTime
            if date_end and date_begin:
                try:
                    totsec = (date_end - date_begin).total_seconds()
                except AttributeError:
                    print('Warning : Timedelta total_seconds() method missing, \
switching back to days and seconds', file=sys.stderr)
                    d = date_end - date_begin
                    totsec = (d.days*3600*24)+d.seconds
                #total_duration =str(date_end - date_begin)
                total_duration = '%.2i:%.2i:%.2i'%(totsec // 3600, totsec % 3600 // 60, totsec % 60)
                if totsec == 0:
                    avg_speed = 0
                else:
                    avg_speed = (total_distance) / totsec
                    avg_speed = avg_speed / 1000
                    avg_speed = avg_speed * 3600
                    avg_speed = '%.2f'%avg_speed
            else:
                total_duration = "???"

            # auto analye from gpxpy
            # we consider every segment under 0.9 km/h as a stop time
            moving_time, stopped_time, moving_distance, stopped_distance, moving_max_speed = gpx.get_moving_data(0.9)

            # determination of real moving average speed from moving time
            moving_avg_speed = 0
            if moving_time != 0:
                moving_avg_speed = (total_distance) / moving_time
                moving_avg_speed = moving_avg_speed / 1000
                moving_avg_speed = moving_avg_speed * 3600
                moving_avg_speed = '%.2f'%moving_avg_speed

    if len(gpx.waypoints) > 0:
        # if no nsew bounds are set, we init them
        if north is None:
            north = gpx.waypoints[0].latitude
            south = gpx.waypoints[0].latitude
            east =  gpx.waypoints[0].longitude
            west =  gpx.waypoints[0].longitude
        # if no marker position is set, we take the first waypoint
        if lat == '0' and lon == '0':
            lat = gpx.waypoints[0].latitude
            lon = gpx.waypoints[0].longitude

    for waypoint in gpx.waypoints:
        shortPointList.append([waypoint.latitude, waypoint.longitude])

        if waypoint.latitude > north:
            north = waypoint.latitude
        if waypoint.latitude < south:
            south = waypoint.latitude
        if waypoint.longitude > east:
            east = waypoint.longitude
        if waypoint.longitude < west:
            west = waypoint.longitude

    result = '[%s, %s, "%s", %s, "%s", "%s", "%s", %s, %s, %s, %s, %s, %s, "%s", "%s", %s, %s, %s, %s, %s, %s]'%(
            lat,
            lon,
            name,
            '%.2f'%total_distance,
            total_duration,
            date_begin, 
            date_end,
            '%.2f'%pos_elevation,
            '%.2f'%neg_elevation,
            min_elevation,
            max_elevation,
            '%.2f'%max_speed,
            avg_speed,
            format_time_seconds(moving_time),
            format_time_seconds(stopped_time),
            moving_avg_speed,
            north,
            south,
            east,
            west,
            shortPointList
            )
    return result

def processFile(p):
    try:
        f = p['f']
        i = p['i']
        scantype = p['scantype']

        fd = open(f,'r')
        content_raw = fd.read()
        # gpxpy wants <course> to be int and with gpslogger tracks,
        # it's not, so we remove <course> tags
        content = re.sub(r'<course>.*<\/course>', '', content_raw)
        fd.close()

        done = False

        # write GEOJSON
        if (not os.path.exists('%s.geojson'%f)) or scantype == 'all':
            geoj = gpxTracksToGeojson('%s'%content, os.path.basename(f))
            if geoj:
                gf = open('%s.geojson'%f, 'w')
                gf.write(geoj)
                gf.close()
                done = True
                if (not os.path.exists('%s.geojson.colored'%f)) or scantype == 'all':
                    gf = open('%s.geojson.colored'%f, 'w')
                    geojcol = gpxTracksToColoredGeojson(content, os.path.basename(f))
                    if geojcol:
                        gf.write(geojcol)
                    gf.close()

        # build and write marker file
        if (not os.path.exists('%s.marker'%f)) or scantype == 'all':
            marktxt = getMarkerFromGpx(content,os.path.basename(f))
            mf = open('%s.marker'%f, 'w')
            mf.write(marktxt)
            mf.close()
            done = True

        if done:
            print('Processing %s [%s/%s] ... Done'%(os.path.basename(f),(i+1),len(files)))

    except KeyboardInterrupt:
        print('KeyboardInterrupt in pool process')
    except Exception as e:
        print('Processing %s [%s/%s] ... Problem'%(os.path.basename(f),(i+1),len(files)))
        print('Problem in file : %s \n %s'%(f, e), file=sys.stderr)
        traceback.print_exc()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.stderr.write('At least an argument is required')
        sys.exit(1)
    else:
        path = sys.argv[1]
        if not os.path.exists(path):
            sys.stderr.write('%s does not exist'%path)
            sys.exit(1)

        scantype = 'newonly'
        if len(sys.argv) > 2:
            scantype = sys.argv[2].replace('--','')

    files = [ os.path.join(path,f) for f in os.listdir(path)
            if (os.path.isfile(os.path.join(path,f)) and (f.endswith('.gpx') or f.endswith('.GPX')) ) ]

    paramset = []
    for i,f in enumerate(files):
        paramset.append({'i':i, 'f':f, 'scantype':scantype})

    try:
        p = Pool(4)
    except Exception as e:
        MP_AVAILABLE = False

    if MP_AVAILABLE:
        try:
            p.map(processFile, paramset)
            p.close()
            p.join()
        except KeyboardInterrupt:
            print('KeyboardInterrupt, terminating pool processes')
            p.terminate()
            p.join()
    else:
        map(processFile, paramset)
