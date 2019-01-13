# CentOS 7, PHP7, MariaDB 10
FROM c7/lamp:latest
# Set a unique cache serial
ENV REFRESHED_AT="2019-01-12"

COPY grab.php /grab.php

ENTRYPOINT ["/usr/bin/php", "/grab.php"]