#
# PHP 8.2-FPM image for natassg (adapted from SSG, without private SSH keys)
#
# Arguments:
#     PUID                 User id. Default "1000"
#     INSTALL_GIT          Install git (false|true). Default "true"
#     PHP_INSTALL_XDEBUG   Enable xdebug (false|true). Default "false"
#     PHP_XDEBUG_PORT      Xdebug client port. Default "9000"
#

FROM php:8.2-fpm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get -qy update && apt-get upgrade -qy && apt-get -qy install --no-install-recommends \
        wget curl \
        sudo \
        zip unzip \
        lsb-release \
        gnupg openssh-client \
        libmemcached-dev \
        libz-dev \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libwebp-dev \
        libxpm-dev \
        libfreetype6-dev \
        libssl-dev \
        libmcrypt-dev \
        libzip-dev libicu-dev \
        libldap2-dev \
        librabbitmq-dev \
        libgmp-dev \
        libxml2-dev \
        libxslt-dev \
        libkrb5-dev \
    && apt-get autoremove -qy && apt-get clean -qy && rm -rf /tmp/* /var/tmp/* /var/lib/apt/lists/*

RUN pecl update-channels && \
    docker-php-ext-configure gd \
            --prefix=/usr \
            --with-jpeg \
            --with-webp \
            --with-xpm \
            --with-freetype && \
    docker-php-ext-install gd && \
    docker-php-ext-install zip && \
    docker-php-ext-configure ldap --with-libdir=lib/$(uname -m)-linux-gnu/ && \
    docker-php-ext-install ldap && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl && \
    docker-php-ext-install bcmath && \
    docker-php-ext-install exif && \
    docker-php-ext-install gmp && \
    docker-php-ext-install opcache && \
    docker-php-ext-install pcntl && \
    docker-php-ext-install pdo_pgsql && \
    docker-php-ext-install pgsql && \
    docker-php-ext-install sockets && \
    docker-php-ext-install xsl && \
    docker-php-ext-install soap && \
    docker-php-ext-enable soap && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-enable pdo_mysql && \
    docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli && \
    pecl install -o -f amqp && docker-php-ext-enable amqp && \
    pecl install -o -f memcached && docker-php-ext-enable memcached && \
    pecl install -o -f redis && docker-php-ext-enable redis && \
    pecl install -o -f xdebug && \
    rm -rf /tmp/* /var/tmp/* /var/lib/apt/lists/*

ADD ./50-custom.ini /usr/local/etc/php/conf.d/

RUN EXPECTED_SIGNATURE=$(wget -q -O - https://composer.github.io/installer.sig) && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', 'composer-setup.php');") && \
    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then \
      >&2 echo 'ERROR: Invalid installer signature'; \
      exit 1; \
    else \
      php composer-setup.php --install-dir=/usr/local/bin --filename=composer.phar; \
    fi && \
    rm composer-setup.php

RUN wget --no-check-certificate -q -O /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/1.17/gosu-amd64" \
    && chmod +x /usr/local/bin/gosu

RUN cp -r /etc/skel /home/www-data \
    && mkdir -p /home/www-data/.composer \
    && chown -R www-data:www-data /home/www-data \
    && usermod -d /home/www-data www-data

ARG PUID=1000
ENV PUID=${PUID}
RUN if [ -n "${PUID}" ] && [ "${PUID%:*}" != 0 ]; then \
  usermod -u ${PUID} www-data >/dev/null 2>&1; \
fi

ARG INSTALL_GIT=true
RUN if [ ${INSTALL_GIT} = true ]; then \
    apt-get -qy update && apt-get -qy install --no-install-recommends git && \
    apt-get autoremove -qy && apt-get clean -qy && rm -rf /tmp/* /var/tmp/* /var/lib/apt/lists/*; \
fi

ADD ./composer.sh /usr/local/sbin/composer
RUN sed -i 's/\r//' /usr/local/sbin/composer && chmod +x /usr/local/sbin/composer

ARG PHP_INSTALL_XDEBUG=false
RUN if [ ${PHP_INSTALL_XDEBUG} = true ]; then \
    docker-php-ext-enable xdebug; \
fi

ARG PHP_XDEBUG_PORT=false
RUN if [ ${PHP_XDEBUG_PORT} != false ]; then \
    echo "xdebug.client_port=${PHP_XDEBUG_PORT}" >> /usr/local/etc/php/conf.d/50-custom.ini; \
fi

RUN apt-get clean -y && rm -rf /tmp/* /var/tmp/* /var/lib/apt/lists/*

ENV PATH=/var/www:$PATH
WORKDIR /var/www

CMD ["php-fpm", "--allow-to-run-as-root", "--nodaemonize"]
