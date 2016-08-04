#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys, math, os
import gpxpy, gpxpy.gpx, geojson
import re
import math as mod_math

PROXIMITY_THRESHOLD = 70

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
    arc = math.acos( cos )

    # Remember to multiply arc by the radius of the earth
    # in your favorite set of units to get length.
    return arc*6371000

def gpxTracksToGeojson(gpx_content, name, divList):
    """ converts the gpx string input to a geojson string
    """
    currentlyInDivergence = False
    currentSectionPointList = []
    currentProperties={'id':'',
                'elevation':[],
                'timestamps':'',
                'quickerThan':[],
                'shorterThan':[],
                'longerThan':[],
                'distanceOthers':{},
                'timeOthers':{},
                'positiveDenivOthers':{},
                'slowerThan':[],
                'morePositiveDenivThan':[],
                'lessPositiveDenivThan':[],
                'distance':None,
                'positiveDeniv':None,
                'time':None
    }

    sections = []
    properties = []

    gpx = gpxpy.parse(gpx_content)
    for track in gpx.tracks:
        featureList = []
        lastPoint = None
        pointIndex = 0
        for segment in track.segments:
            for point in segment.points:
                #print 'Point at ({0},{1}) -> {2}'.format(point.latitude, point.longitude, point.elevation)
                if lastPoint != None:
                    # is the point in a divergence ?
                    isDiv = False
                    for d in divList:
                        if pointIndex > d['divPoint'] and pointIndex <= d['convPoint']:
                            # we are in a divergence
                            isDiv = True
                            # is it the first point in div ?
                            if not currentlyInDivergence:
                                # it is the first div point, we add previous section
                                currentSectionPointList.append(lastPoint)
                                sections.append(currentSectionPointList)
                                # we update properties with lastPoint infos (the last in previous section)
                                currentProperties['id'] += '%s'%(pointIndex-1)
                                currentProperties['elevation'].append(lastPoint.elevation)
                                currentProperties['timestamps'] += '%s'%lastPoint.time
                                # we add previous properties and reset tmp vars
                                properties.append(currentProperties)
                                currentSectionPointList = []
                                # we add the last point that is the junction
                                # between the two sections
                                currentSectionPointList.append(lastPoint)
                                currentProperties = {}

                                currentProperties={'id':'%s-'%(pointIndex-1),
                                            'elevation':[lastPoint.elevation],
                                            'timestamps':'%s ; '%(lastPoint.time),
                                            'quickerThan':[],
                                            'shorterThan':[],
                                            'longerThan':[],
                                            'distanceOthers':{},
                                            'timeOthers':{},
                                            'positiveDenivOthers':{},
                                            'slowerThan':[],
                                            'morePositiveDenivThan':[],
                                            'lessPositiveDenivThan':[],
                                            'distance':None,
                                            'positiveDeniv':None,
                                            'time':None
                                }
                                currentlyInDivergence = True

                                comparedTo = d['comparedTo']
                                currentProperties['distance'] = d['distance']
                                currentProperties['time'] = d['time']
                                currentProperties['positiveDeniv'] = d['positiveDeniv']
                                if d['isDistanceBetter']:
                                    currentProperties['shorterThan'].append(comparedTo)
                                else:
                                    currentProperties['longerThan'].append(comparedTo)
                                currentProperties['distanceOthers'][comparedTo] = d['distance_other']
                                if d['isTimeBetter']:
                                    currentProperties['quickerThan'].append(comparedTo)
                                else:
                                    currentProperties['slowerThan'].append(comparedTo)
                                currentProperties['timeOthers'][comparedTo] = d['time_other']
                                if d['isPositiveDenivBetter']:
                                    currentProperties['lessPositiveDenivThan'].append(comparedTo)
                                else:
                                    currentProperties['morePositiveDenivThan'].append(comparedTo)
                                currentProperties['positiveDenivOthers'][comparedTo] = d['positiveDeniv_other']

                    # if we were in a divergence and now are NOT in a divergence
                    if currentlyInDivergence and not isDiv:
                        # it is the first NON div point, we add previous section
                        currentSectionPointList.append(lastPoint)
                        currentSectionPointList.append(point)
                        sections.append(currentSectionPointList)
                        # we update properties with lastPoint infos (the last in previous section)
                        currentProperties['id'] += '%s'%(pointIndex)
                        currentProperties['elevation'].append(point.elevation)
                        currentProperties['timestamps'] += '%s'%point.time
                        # we add previous properties and reset tmp vars
                        properties.append(currentProperties)
                        currentSectionPointList = []
                        currentProperties = {}

                        currentProperties={'id':'%s-'%(pointIndex),
                                    'elevation':[point.elevation],
                                    'timestamps':'%s ; '%(point.time),
                                    'quickerThan':[],
                                    'shorterThan':[],
                                    'longerThan':[],
                                    'distanceOthers':{},
                                    'timeOthers':{},
                                    'positiveDenivOthers':{},
                                    'slowerThan':[],
                                    'morePositiveDenivThan':[],
                                    'lessPositiveDenivThan':[],
                                    'distance':None,
                                    'positiveDeniv':None,
                                    'time':None
                        }
                        currentlyInDivergence = False

                    currentSectionPointList.append(point)
                else:
                    # this is the first point
                    currentProperties['id'] = 'begin-'
                    currentProperties['timestamps'] = '%s ; '%point.time
                    currentProperties['elevation'].append('%s'%point.elevation)

                lastPoint = point
                pointIndex += 1

        if len(currentSectionPointList) > 0:
            sections.append(currentSectionPointList)
            currentProperties['id'] += 'end'
            currentProperties['timestamps'] += '%s'%lastPoint.time
            currentProperties['elevation'].append('%s'%lastPoint.elevation)
            properties.append(currentProperties)

        # for each section, we add a Feature
        for i in range(len(sections)):
            coords = []
            for p in sections[i]:
                coords.append((p.longitude, p.latitude))
            featureList.append(
                geojson.Feature(
                    id='%s'%(i),
                    properties=properties[i],
                    geometry=geojson.LineString(coords)
                )
            )

        fc = geojson.FeatureCollection(featureList, id=name)
        return fc

def compareTwoGpx(gpxc1, id1, gpxc2, id2):
    """ build an index of divergence comparison
    """
    gpx1 = gpxpy.parse(gpxc1)
    gpx2 = gpxpy.parse(gpxc2)
    if (gpx1.tracks and gpx2.tracks):
        t1 = gpx1.tracks[0]
        t2 = gpx2.tracks[0]
        if (t1.segments and t2.segments):
            p1 = t1.segments[0].points
            p2 = t2.segments[0].points
        else:
            raise Exception('At least one segment is needed per track')
    else:
        raise Exception('At least one track per GPX is needed')

    # index that will be returned
    index1 = []
    index2 = []
    # current points
    c1 = 0
    c2 = 0
    # find first convergence point
    conv = findFirstConvergence(p1, c1, p2, c2)

    # loop on 
    while (conv != None):
        # find first divergence point
        c1 = conv[0]
        c2 = conv[1]
        div = findFirstDivergence2(p1, c1, p2, c2)

        # if there isn't any divergence after
        if (div == None):
            conv = None
            continue
        else:
            # if there is a divergence
            c1 = div[0]
            c2 = div[1]
            # find first convergence point again
            conv = findFirstConvergence(p1, c1, p2, c2)
            if conv != None:
                if (div[0]-2 > 0 and div[1]-2 > 0):
                    div = (div[0]-2, div[1]-2)
                indexes = compareBetweenDivAndConv(div, conv, p1, p2, id1, id2)
                index1.append(indexes[0])
                index2.append(indexes[1])
    return (index1, index2)

def compareBetweenDivAndConv(div, conv, p1, p2, id1, id2):
    """ determine who's best in time and distance during this divergence
    """
    result1 = {'divPoint':div[0],
            'convPoint':conv[0],
            'comparedTo':id2,
            'isTimeBetter': None,
            'isDistanceBetter': None,
            'isPositiveDenivBetter': None,
            'positiveDeniv':None,
            'time': None,
            'distance':None
            }
    result2 = {'divPoint':div[1],
            'convPoint':conv[1],
            'comparedTo':id1,
            'isTimeBetter': None,
            'isDistanceBetter': None,
            'isPositiveDenivBetter': None,
            'positiveDeniv':None,
            'time': None,
            'distance':None
            }
    # positive deniv
    posden1 = 0
    posden2 = 0
    lastp = None
    upBegin = None
    isGoingUp = False
    lastDeniv = None
    for p in p1[div[0]:conv[0]+1]:
        if lastp != None and p.elevation and lastp.elevation:
            deniv = p.elevation - lastp.elevation
        if lastDeniv != None:
            # we start to go up
            if (isGoingUp == False) and deniv > 0:
                upBegin = lastp.elevation
                isGoingUp = True
            if (isGoingUp == True) and deniv < 0:
                # we add the up portion
                posden1 += lastp.elevation - upBegin
                isGoingUp = False
        # update variables
        if lastp != None and p.elevation and lastp.elevation:
            lastDeniv = deniv
        lastp = p

    lastp = None
    upBegin = None
    isGoingUp = False
    lastDeniv = None
    for p in p2[div[1]:conv[1]+1]:
        if lastp != None and p.elevation and lastp.elevation:
            deniv = p.elevation - lastp.elevation
        if lastDeniv != None:
            # we start a way up
            if (isGoingUp == False) and deniv > 0:
                upBegin = lastp.elevation
                isGoingUp = True
            if (isGoingUp == True) and deniv < 0:
                # we add the up portion
                posden2 += lastp.elevation - upBegin
                isGoingUp = False
        # update variables
        if lastp != None and p.elevation and lastp.elevation:
            lastDeniv = deniv
        lastp = p

    result1['isPositiveDenivBetter'] = (posden1 < posden2)
    result1['positiveDeniv'] = posden1
    result1['positiveDeniv_other'] = posden2
    result2['isPositiveDenivBetter'] = (posden2 <= posden1)
    result2['positiveDeniv'] = posden2
    result2['positiveDeniv_other'] = posden1

    # distance
    dist1 = 0
    dist2 = 0
    lastp = None
    for p in p1[div[0]:conv[0]+1]:
        if lastp != None:
            dist1 += distance(lastp, p)
        lastp = p
    lastp = None
    for p in p2[div[1]:conv[1]+1]:
        if lastp != None:
            dist2 += distance(lastp, p)
        lastp = p

    result1['isDistanceBetter'] = (dist1 < dist2)
    result1['distance'] = dist1
    result1['distance_other'] = dist2
    result2['isDistanceBetter'] = (dist1 >= dist2)
    result2['distance'] = dist2
    result2['distance_other'] = dist1

    # time
    tdiv1 = p1[div[0]].time
    tconv1 = p1[conv[0]].time
    t1 = tconv1 - tdiv1
    
    tdiv2 = p2[div[1]].time
    tconv2 = p2[conv[1]].time
    t2 = tconv2 - tdiv2
    result1['isTimeBetter'] = (t1 < t2)
    result1['time'] = str(t1)
    result1['time_other'] = str(t2)
    result2['isTimeBetter'] = (t1 >= t2)
    result2['time'] = str(t2)
    result2['time_other'] = str(t1)

    return (result1, result2)

def findFirstDivergence2(p1, c1, p2, c2):
    """ find the first divergence by using findFirstConvergence
    """
    # we are in a convergence state so we need to advance
    ct1 = c1+1
    ct2 = c2+1
    conv = findFirstConvergence(p1, ct1, p2, ct2)
    while (conv != None):
        # if it's still convergent, go on
        if (conv[0] == ct1 and conv[1] == ct2):
            ct1 += 1
            ct2 += 1
        # if the convergence made only ct2 advance
        elif (conv[0] == ct1):
            ct1 += 1
            ct2 = conv[1]+1
        # if the convergence made only ct1 advance
        elif (conv[1] == ct2):
            ct2 += 1
            ct1 = conv[0]+1
        # the two tracks advanced to find next convergence, it's a divergence !
        else:
            return (ct1+1, ct2+1)

        conv = findFirstConvergence(p1, ct1, p2, ct2)

    return None

def findFirstConvergence(p1, c1, p2, c2):
    """ returns indexes of the first convergence point found 
        from c1 and c2 in the point tables
    """
    ct1 = c1
    while (ct1 < len(p1)):
        ct2 = c2
        while (ct2 < len(p2) and distance(p1[ct1], p2[ct2]) > PROXIMITY_THRESHOLD):
            ct2 += 1
        if ct2 < len(p2):
            # we found a convergence point
            return (ct1, ct2)
        ct1 += 1
    return None

def format_time(time_s):
    if not time_s:
        return 'n/a'
    minutes = mod_math.floor(time_s / 60.)
    hours = mod_math.floor(minutes / 60.)

    return '%s:%s:%s' % (str(int(hours)).zfill(2), str(int(minutes % 60)).zfill(2), str(int(time_s % 60)).zfill(2))

if __name__ == "__main__":
    paths = []
    contents = {}
    indexes = {}
    taggedGeo = {}
    for i in sys.argv[1:]:
        paths.append(i)

    for p in paths:
        f=open(p,'r')
        content_raw = f.read()
        content = re.sub(r'<course>.*<\/course>', '', content_raw)
        name = os.path.basename(p)
        contents[name] = content
        indexes[name] = {}
        taggedGeo[name] = {}
        f.close()

    # comparison of each pair of input file
    names = contents.keys()
    i = 0
    while i<len(names):
        ni = names[i]
        j = i+1
        while j<len(names):
            nj = names[j]
            comp = compareTwoGpx(contents[ni], ni, contents[nj], nj)
            indexes[ni][nj] = comp[0]
            indexes[nj][ni] = comp[1]
            j += 1
        i += 1

    # from all comparison information, convert GPX to GeoJson with lots of meta-info
    for ni in names:
        for nj in names:
            if nj != ni:
                taggedGeo[ni][nj] = gpxTracksToGeojson(contents[ni], ni, indexes[ni][nj])

    # write geojson files in current directory
    for i in taggedGeo.keys():
        for j in taggedGeo[i].keys():
            f=open('%s%s.geojson'%(i,j),'w')
            f.write(str(taggedGeo[i][j]))
            f.close()

    # write global stats for each track
    for p in paths:
        gpxo = gpxpy.parse(open(p))
        stats_txt = '{'+'\n\t"length_2d": {:.3f},\n'.format(gpxo.length_2d() / 1000.)
        stats_txt += '\t"length_3d": {:.3f},\n'.format(gpxo.length_3d() / 1000.)

        moving_time, stopped_time, moving_distance, stopped_distance, max_speed = gpxo.get_moving_data()
        stats_txt += '\t"moving_time": "%s",\n' % (format_time(moving_time))
        stats_txt += '\t"stopped_time": "%s",\n' % (format_time(stopped_time))
        stats_txt += '\t"max_speed": {:.2f},\n'.format(max_speed * 60. ** 2 / 1000. if max_speed else 0)

        uphill, downhill = gpxo.get_uphill_downhill()
        stats_txt += '\t"total_uphill": {:.2f},\n'.format(uphill)
        stats_txt += '\t"total_downhill": {:.2f},\n'.format(downhill)

        start_time, end_time = gpxo.get_time_bounds()
        stats_txt += '\t"started": "%s",\n' % (start_time)
        stats_txt += '\t"ended": "%s",\n' % (end_time)

        points_no = len(list(gpxo.walk(only_points=True)))
        stats_txt += '\t"nbpoints": %s\n}' % (points_no)
        of = open('%s.stats' % p, 'w')
        of.write(stats_txt)
        of.close()

