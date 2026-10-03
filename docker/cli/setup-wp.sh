#!/bin/sh
# Nainstaluje a nastaví lokální WordPress. Spouští se v kontejneru `cli` (make up), opakovaně bezpečně.
set -eu

# Kontejner `wordpress` při prvním startu kopíruje jádro a vytváří wp-config.php.
tries=0
until [ -f /var/www/html/wp-config.php ] && [ -f /var/www/html/wp-includes/version.php ]; do
	tries=$((tries + 1))
	if [ "$tries" -gt 60 ]; then
		echo "WordPress se nepřipravil do 60 s." >&2
		exit 1
	fi
	sleep 1
done

if ! wp core is-installed 2>/dev/null; then
	wp core install \
		--url="$WP_URL" \
		--title="Pneuservis Kárník" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@example.test \
		--skip-email
fi

wp option update home "$WP_URL" --quiet
wp option update siteurl "$WP_URL" --quiet
wp option update timezone_string Europe/Prague --quiet
wp option update date_format 'j. n. Y' --quiet
wp option update time_format 'H:i' --quiet
wp rewrite structure '/%postname%/' --quiet

if ! wp language core is-installed cs_CZ 2>/dev/null; then
	wp language core install cs_CZ --activate --quiet || echo "Čeština se nestáhla (offline?), pokračuji bez ní." >&2
fi

wp theme activate pneukarnik --quiet
wp plugin activate pneukarnik-booking --quiet

# Kontakty Provozovatele pro lokální web (dají se přepsat v Nastavení pluginu).
wp option add pneukarnik_phone '+420 775 565 326' --quiet 2>/dev/null || true
wp option add pneukarnik_email 'servis@example.test' --quiet 2>/dev/null || true
wp option add pneukarnik_address 'Dobšická 10, 669 02 Znojmo' --quiet 2>/dev/null || true

wp rewrite flush --quiet

echo "Hotovo: $WP_URL (admin / admin)"
