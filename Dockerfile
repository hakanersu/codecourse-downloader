From php:7.1-alpine3.4

LABEL Maintainer="Zaher Ghaibeh <z@zah.me>" \
      Description="Lightweight container with PHP 7.1 based on Alpine Linux." \
      Date="25-11-2017"
COPY docker-entrypoint.sh /docker-entrypoint.sh

WORKDIR /app

ADD . /app

RUN apk update && apk upgrade && apk --no-cache add git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-progress --no-suggest --prefer-dist --no-dev --optimize-autoloader \
    && wget -O /usr/local/bin/dumb-init https://github.com/Yelp/dumb-init/releases/download/v1.2.0/dumb-init_1.2.0_amd64 \
    && chmod +x /usr/local/bin/dumb-init \
    && chmod +x codecourse \
    && cp .env.example .env

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["php","codecourse","download"]
