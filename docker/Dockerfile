# syntax=docker/dockerfile:1.3-labs
ARG PHP_VERSION=8.1

FROM php:${PHP_VERSION}-cli

# make PHP extension installation easier
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

MAINTAINER Oliver G. Mueller <oliver@teqneers.de>

SHELL ["/bin/bash", "-c"]
RUN <<EOC
# ERROR HANDLING
set -o pipefail # trace ERR through pipes
set -o errtrace # trace ERR through 'time command' and other functions
set -o nounset  ## set -u : exit the script if you try to use an uninitialised variable
set -o errexit  # stop on non zero return code
trap "exit" SIGHUP SIGINT SIGQUIT SIGABRT SIGTERM

apt-get update
apt-get install --no-install-recommends -y \
    git \
    libzip-dev \
    unzip

install-php-extensions \
  @composer \
  bcmath \
  xdebug \
  zip \

apt-get autoremove -y
apt-get clean
rm -rf /var/lib/apt/lists/*

mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

cat <<EOF >>/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
xdebug.mode = develop,debug
xdebug.cli_color = 1
xdebug.idekey=\"PHPSTORM\"
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
;xdebug.remote_handler = dbgp
xdebug.max_nesting_level = 128
xdebug.var_display_max_depth = 8
xdebug.var_display_max_children = 128
xdebug.var_display_max_data = 256
xdebug.output_dir=/tmp/xdebug
EOF

EOC

# install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /web

#ENTRYPOINT ["docker-entrypoint.sh"]
#CMD ["-c", "/etc/supervisor/supervisord.conf"]

# vim: syntax=Dockerfile ts=4 sw=4 et sr softtabstop=4 autoindent
