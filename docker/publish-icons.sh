#!/bin/sh
# Icon files change whenever the admin uploads a new favicon, but `public/` comes from
# the image; regenerate them after the storage scaffolding exists, before nginx serves
# anything. Failure only means /favicon.ico stays missing, never a broken boot.
set -e

php /var/www/html/artisan icons:publish --no-interaction >/dev/null 2>&1 || true
