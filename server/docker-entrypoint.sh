#!/bin/sh
set -e

# The data dir may be a host bind mount, which resets ownership on every
# start — re-apply it here instead of relying on the build-time chown.
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data

exec "$@"
