FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libpng-dev libjpeg62-turbo-dev libonig-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring mysqli zip \
    && rm -rf /var/lib/apt/lists/*

ARG MYBB_VERSION=1.8.41

RUN mybb_release_code="$(printf '%s' "$MYBB_VERSION" | tr -d '.')" \
    && curl -fsSL "https://resources.mybb.com/downloads/mybb_${mybb_release_code}.zip" -o /tmp/mybb.zip \
    && echo '00d4c742782e23319698c758293cd272d5fc9a6093caad739403fc99963331d8d7cfaafbd25d3acdf003c64b29b4f471568ce5c388b469947f756fee7917933e  /tmp/mybb.zip' | sha512sum -c - \
    && unzip -q /tmp/mybb.zip 'Upload/*' -d /opt/mybb \
    && rm /tmp/mybb.zip

# Default MyBB Resources packages, pinned by commit and archive SHA-256.
COPY plugin-files/ /usr/local/share/mybb/plugin-files/
COPY stage-package.php /usr/local/lib/mybb/stage-package.php
COPY bundle-defaults /usr/local/bin/bundle-defaults
RUN sh /usr/local/bin/bundle-defaults \
    mybbforums-plugin a566ab70218a93b3f16a65cc97163fb4d4a3bcb6 0cc40e46f2f091fafc3b994c0d8c52747f8486c8579013dc1064580d1b46a0d8 \
    mybb-plugin-repository 7676c4c1ddb26b536674aca090c8cdbc44bba2c8 cfb6dd4c13b59114c829c32d809ba3f14793b3eaea9df9a1f0b8ed880e800051 \
    mybb-cli 597b9b4ddef1d5dcc075cc0bada15d7c3c4aa56f 4d37ef27ba860920cbc9341166779bceec24d35337e3a38a90158c4587fcbfd3 \
    mybb-tooling-core b533fdbb5a0ac35be8de7a51daf54a4c99e0e7b0 5c3e6261317e14c78c2baee31b4e5f7519f8f0993a26d5732835079d3127e77b \
    html5-doctype-support e6367a59e7f6064104cd42f27711b014edddb61e 873a27bd8055ec27217ba7ff3b636d38f9444de566467ce5247ffcf34793967f

COPY install-defaults.php /usr/local/lib/mybb/install-defaults.php
COPY start-mybb /usr/local/bin/start-mybb
COPY mybb.conf /etc/apache2/conf-enabled/mybb.conf
CMD ["start-mybb"]
