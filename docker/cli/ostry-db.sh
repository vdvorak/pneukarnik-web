#!/bin/sh
# Databáze testovací kopie pro ostrý web (make ostry, docker/kopie/ostry.sh): adresy kopie na adresu
# ostrého webu, indexace zapnutá a bez zkušebních Rezervací, souhlasů a Rozesílek z kopie. Rezervace
# a souhlasy se při přepnutí převedou znovu ze staré tabulky (docs/prepnuti.md, Přepnutí).
# Bez pluginů a šablon: jen data, nic se nemá spouštět.
set -eu

wp() {
	command wp --skip-plugins --skip-themes "$@"
}

echo "== Adresy $KOPIE_URL na $OSTRY_URL"
# I guid: s adresou kopie mají jen příspěvky založené na kopii a ty nikdy veřejně nevyšly.
for old in "$KOPIE_URL" "$(echo "$KOPIE_URL" | sed 's/^https:/http:/')"; do
	echo "$old: $(wp search-replace "$old" "$OSTRY_URL" --all-tables-with-prefix --format=count)"
done

echo "== Indexace vyhledávači zapnutá"
wp option update blog_public 1 --quiet

echo "== Zkušební data kopie pryč (Rezervace, souhlasy, Rozesílky)"
# Přes $wpdb: klient MariaDB z obrazu WP-CLI by k MySQL 5.7 vyžadoval důvěryhodný certifikát.
wp eval '
global $wpdb;
foreach ( [ "pneukarnik_bookings", "pneukarnik_booking_services", "pneukarnik_subscriptions", "pneukarnik_mailings", "pneukarnik_mailing_recipients" ] as $table ) {
	$table = $wpdb->prefix . $table;
	echo $table . ": " . $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) . " řádků\n";
	if ( false === $wpdb->query( "TRUNCATE TABLE `$table`" ) ) {
		exit( 1 );
	}
}'

echo "siteurl: $(wp option get siteurl), home: $(wp option get home), blog_public: $(wp option get blog_public)"
