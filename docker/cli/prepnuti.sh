#!/bin/sh
# Kroky přepnutí nad databází starého webu (docs/prepnuti.md). Spouští make zkouska v kontejneru `cli`.
# Na hostingu se dělají stejně, jen bez přepisu adres a zkušebního účtu (viz checklist).
set -eu

echo "== Databáze WordPressu"
wp core update-db

echo "== Adresy živého webu na adresu zkoušky"
wp search-replace 'https://pneukarnik.cz' "$WP_URL" --skip-columns=guid --format=count 2>/dev/null

echo "== Nová šablona a plugin, staré pluginy vypnout"
wp option update active_plugins '[]' --format=json --quiet
wp theme activate pneukarnik --quiet
wp plugin activate pneukarnik-booking --quiet
wp rewrite flush --quiet

echo "== Zkušební účet správce (zkouska / zkouska)"
wp user get zkouska --field=ID >/dev/null 2>&1 || wp user create zkouska zkouska@example.test --role=administrator --user_pass=zkouska --quiet

echo "== Stav"
echo "verze DB pluginu: $(wp option get pneukarnik_db_version)"
echo "struktura odkazů: $(wp option get permalink_structure)"
echo "Hotovo: $WP_URL/wp-admin/ (zkouska / zkouska), převod dat: Rezervace → Převod ze starého webu"
