#!/bin/sh
cd /var/www || exit 1
gosu www-data composer.phar "$@"
