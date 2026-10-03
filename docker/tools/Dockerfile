# Stejné PHP a jádro WordPressu jako služba `wordpress`. Jádro leží v /usr/src/wordpress
# a slouží jako ABSPATH pro WP test suite.
FROM wordpress:7.1-php8.3-apache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/* \
	&& chmod -R a+w /usr/src/wordpress/wp-content
