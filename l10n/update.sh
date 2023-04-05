#!/bin/bash

rm -rf /tmp/gpxpod
git clone https://github.com/julien-nc/gpxpod /tmp/gpxpod -b l10n_main --single-branch
cp -r /tmp/gpxpod/l10n/descriptions/[a-z][a-z]_[A-Z][A-Z] ./descriptions/
cp -r /tmp/gpxpod/translationfiles/[a-z][a-z]_[A-Z][A-Z] ../translationfiles/
rm -rf /tmp/gpxpod

echo "files copied"
