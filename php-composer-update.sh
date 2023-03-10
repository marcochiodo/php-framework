#!/bin/bash

docker run -it --rm \
    -v "$PWD":/srv \
    -v "$COMPOSER_PHAR":/srv/composer.phar \
    -w /srv \
    -u developer \
    sigblue/apache-php:82-full \
    php -c . composer.phar update