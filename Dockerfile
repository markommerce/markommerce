# Self-contained PHP 8.5 image for running the markommerce test suites.
# Mirrors the extension set of the workspace dev image so behaviour matches,
# but stays lean: no redis/node/mysql (no test needs them).
FROM php:8.5-cli-alpine

RUN apk add --no-cache bash git unzip libpq icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev icu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl pcntl \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git needs this to operate on bind-mounted repos owned by another uid
RUN git config --system --add safe.directory '*'

WORKDIR /workspace/markommerce
