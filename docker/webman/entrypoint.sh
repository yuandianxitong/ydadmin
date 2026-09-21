#!/bin/sh
set -eu
cd /var/www/html
sh /opt/ydadmin/ensure-hosts.sh /var/www/html/.env /var/www/html/.env.example
exec php start.php start
