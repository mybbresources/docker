FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libpng-dev libjpeg62-turbo-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring mysqli \
    && rm -rf /var/lib/apt/lists/*

ARG MYBB_VERSION=1.8.41

RUN mybb_release_code="$(printf '%s' "$MYBB_VERSION" | tr -d '.')" \
    && curl -fsSL "https://resources.mybb.com/downloads/mybb_${mybb_release_code}.zip" -o /tmp/mybb.zip \
    && echo '00d4c742782e23319698c758293cd272d5fc9a6093caad739403fc99963331d8d7cfaafbd25d3acdf003c64b29b4f471568ce5c388b469947f756fee7917933e  /tmp/mybb.zip' | sha512sum -c - \
    && unzip -q /tmp/mybb.zip 'Upload/*' -d /opt/mybb \
    && rm /tmp/mybb.zip

COPY start-mybb /usr/local/bin/start-mybb
COPY mybb.conf /etc/apache2/conf-enabled/mybb.conf
CMD ["start-mybb"]
