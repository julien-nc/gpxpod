#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys, math, os
import json
import gpxpy, gpxpy.gpx, geojson
import re

PROXIMITY_THRESHOLD = 80

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
    gpx = gpxpy.parse(gpx_content)
    for track in gpx.tracks:
        featureList = []
        lastPoint = None
        pointIndex = 0
        for segment in track.segments:
            for point in segment.points:
                #print 'Point at ({0},{1}) -> {2}'.format(point.latitude, point.longitude, point.elevation)
                if lastPoint != None:
                    properties={'id':'%s-%s'%(pointIndex-1, pointIndex),
                                'elevation':[lastPoint.elevation, point.elevation],
                                'timestamps':'%s ; %s'%(lastPoint.time,point.time),
                                'quickerThan':[],
                                'shorterThan':[],
                                'longerThan':[],
                                'slowerThan':[],
                                'morePositiveDenivThan':[],
                                'lessPositiveDenivThan':[],
                                'distance':None,
                                'positiveDeniv':None,
                                'time':None
                               }
                    # is the point in a divergence ?
                    for d in divList:
                        if pointIndex > d['divPoint'] and pointIndex <= d['convPoint']:
                            comparedTo = d['comparedTo']
                            properties['distance'] = d['distance']
                            properties['time'] = d['time']
                            properties['positiveDeniv'] = d['positiveDeniv']
                            if d['isDistanceBetter']:
                                properties['shorterThan'].append(comparedTo)
                            else:
                                properties['longerThan'].append(comparedTo)
                            if d['isTimeBetter']:
                                properties['quickerThan'].append(comparedTo)
                            else:
                                properties['slowerThan'].append(comparedTo)
                            if d['isPositiveDenivBetter']:
                                properties['lessPositiveDenivThan'].append(comparedTo)
                            else:
                                properties['morePositiveDenivThan'].append(comparedTo)

                    featureList.append(
                        geojson.Feature(
                            id='%s-%s'%(pointIndex-1, pointIndex),
                            properties=properties,
                            geometry=geojson.LineString([(lastPoint.longitude, lastPoint.latitude), (point.longitude, point.latitude)])
                        )
                    )
                lastPoint = point
                pointIndex += 1

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
            c1div = div[0]
            c2div = div[1]
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
    result2['isPositiveDenivBetter'] = (posden2 <= posden1)
    result2['positiveDeniv'] = posden2

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
    result2['isDistanceBetter'] = (dist1 >= dist2)
    result2['distance'] = dist2

    # time
    tdiv1 = p1[div[0]].time
    tconv1 = p1[conv[0]].time
    t1 = tconv1 - tdiv1
    
    tdiv2 = p2[div[1]].time
    tconv2 = p2[conv[1]].time
    t2 = tconv2 - tdiv2
    result1['isTimeBetter'] = (t1 < t2)
    result1['time'] = str(t1)
    result2['isTimeBetter'] = (t1 >= t2)
    result2['time'] = str(t2)

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

if __name__ == "__main__":
    paths = []
    contents = []
    names = []
    indexes = []
    taggedGeo = []
    for i in sys.argv[1:]:
        paths.append(i)
        indexes.append([])

    for p in paths:
        f=open(p,'r')
        content_raw = f.read()
        content = re.sub(r'<course>.*<\/course>', '', content_raw)
        contents.append(content)
        f.close()
        names.append(os.path.basename(p))

    # comparison of each pair of input file
    i = 0
    while i<len(paths):
        j = i+1
        while j<len(paths):
            comp = compareTwoGpx(contents[i], names[i], contents[j], names[j])
            indexes[i].extend(comp[0])
            indexes[j].extend(comp[1])
            j += 1
        i += 1

    # from all comparison information, convert GPX to GeoJson with lot of meta-info
    for i in range(len(contents)):
        taggedGeo.append(gpxTracksToGeojson(contents[i], names[i], indexes[i]))

    # write geojson files in current directory
    for i in range(len(contents)):
        f=open('%s.geojson'%names[i],'w')
        f.write(str(taggedGeo[i]))
        f.close()
