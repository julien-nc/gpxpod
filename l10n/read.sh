#!/bin/bash

cd ..
rm -rf /tmp/gpxpodjs
mv js /tmp/gpxpodjs
mv node_modules /tmp/node_modules_gpxpod
translationtool.phar create-pot-files
mv /tmp/gpxpodjs js
mv /tmp/node_modules_gpxpod node_modules
cd -
