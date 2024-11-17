#!/bin/bash

set -ex

cd "$(dirname -- "$0")/../data/"
rsync -e ssh -arv --delete \
    --exclude php_errorlog \
    ./ fcch-web:/home/u930-v2vbn3xb6dhb/www/staging5.fortcollinscreatorhub.org/public_html/fcch/
