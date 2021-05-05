#!/bin/sh

if ! command -v openssl > /dev/null 2>&1; then
    echo "openssl must be installed"
    exit 1
fi

if test ${#1} -lt 3; then
    echo "Please provide organisation name at least 3 charachters long"
    exit 1
fi

NAME_PROD=${1}-prod
NAME_WRU=${1}-wru

echo "Generating key for $NAME_PROD"
openssl genrsa -des3 -out $NAME_PROD.key 4096
echo "Create signing request for $NAME_PROD"
openssl req -new -key $NAME_PROD.key -out $NAME_PROD.csr -subj "/emailAddress=DL-cao-cwa@t-systems.com/C=DE/ST=Hessen/L=Frankfurt am Main/O=T-Systems International GmbH/OU=schnelltestportal.de/OU=1key/CN="$NAME_PROD".schnelltestportal.de"

echo "Generating key for $NAME_WRU"
openssl genrsa -des3 -out $NAME_WRU.key 4096
echo "Create signing request for $NAME_WRU"
openssl req -new -key $NAME_WRU.key -out $NAME_WRU.csr -subj "/emailAddress=DL-cao-cwa@t-systems.com/C=DE/ST=Hessen/L=Frankfurt am Main/O=T-Systems International GmbH/OU=schnelltestportal.de/OU=1key/CN="$NAME_WRU".schnelltestportal.de"

echo -n "Please send following files to cwa-schnelltest-onboarding@t-systems.com: "
ls *.csr

