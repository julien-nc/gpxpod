#!/bin/bash

cd ..
rm -rf /tmp/gpxpodjs
mv js /tmp/gpxpodjs
translationtool.phar create-pot-files
mv /tmp/gpxpodjs js
cd -
