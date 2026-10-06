#!/bin/sh
# Databáze testovací kopie (make kopie, docker/kopie/kopie.sh): adresy živého webu na adresu kopie
# a vyloučení z indexace. Web zůstává ve stavu živého, přepnutí se nacvičuje až na hostingu.
# Bez pluginů a šablon: v kontejneru je pod stejným názvem nový plugin a ten by nad starou
# databází hned dorovnal svou databázi.
set -eu

wp() {
	command wp --skip-plugins --skip-themes "$@"
}

echo "== Adresy živého webu na $KOPIE_URL"
for old in https://www.pneukarnik.cz http://www.pneukarnik.cz https://pneukarnik.cz http://pneukarnik.cz; do
	echo "$old: $(wp search-replace "$old" "$KOPIE_URL" --all-tables-with-prefix --skip-columns=guid --format=count)"
done

echo "== Vyloučit z indexace, vypnout W3 Total Cache (kopie ho nemá nastavený)"
wp option update blog_public 0 --quiet
wp eval 'update_option( "active_plugins", array_values( array_filter( (array) get_option( "active_plugins" ), static fn( $p ) => ! str_starts_with( $p, "w3-total-cache/" ) ) ) );'

echo "siteurl: $(wp option get siteurl), home: $(wp option get home)"
