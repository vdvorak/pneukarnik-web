#!/bin/sh
# Ostrý web z testovací kopie (docs/prepnuti.md, Přepnutí). Ostrý web přejde na tabulky kopie
# ($PREFIX), staré tabulky hwjcw_ zůstanou beze změny jako archiv a cesta zpět.
# Z exportu tabulek $PREFIX z phpMyAdminu připraví v $OUT:
#   ostry.sql   tabulky $PREFIX s adresami $URL místo $KOPIE_URL, bez zkušebních Rezervací, souhlasů
#               a Rozesílek, k importu do STEJNÉ databáze (nahradí tabulky $PREFIX)
#   wordpress/  jádro WordPressu (bez wp-content a wp-config.php) ve verzi, pro kterou je databáze
# Databázi upraví WP-CLI v dočasném Dockeru s MySQL 5.7 jako na hostingu, pak ho smaže.
# Spouští make ostry DUMP=…sql.
set -eu

OLD=hwjcw_
PREFIX=tstpk_
URL=${URL:-https://pneukarnik.cz}
KOPIE_URL=${KOPIE_URL:-https://test.pneukarnik.cz}
OUT=${OUT:-ostry}

compose() {
	TABLE_PREFIX=$PREFIX WP_PORT=8093 MAILPIT_PORT=8038 docker compose -p pneukarnik-ostry -f docker-compose.yml -f docker/kopie/compose.yml "$@"
}
db() { # db mysql|mysqldump [volby a argumenty]
	cmd=$1
	shift
	compose exec -T db "$cmd" -uwordpress -pwordpress "$@"
}
fail() {
	echo "Chyba: $*" >&2
	exit 1
}

[ -f "${DUMP:-}" ] || fail "chybí export: make ostry DUMP=cesta/k/exportu.sql (phpMyAdmin, jen tabulky $PREFIX)"
echo "$URL" | grep -Eq '^https://[a-z0-9.-]+$' || fail "URL ve tvaru https://pneukarnik.cz (bez lomítka na konci)"
echo "$KOPIE_URL" | grep -Eq '^https://[a-z0-9.-]+$' || fail "KOPIE_URL ve tvaru https://test.pneukarnik.cz"
[ ! -e "$OUT" ] || fail "$OUT už existuje, smaž ho (jsou v něm osobní údaje zákazníků)"

COUNT=$(grep -c '^CREATE TABLE' "$DUMP" || true)
[ "$COUNT" -gt 0 ] || fail "$DUMP neobsahuje žádnou tabulku"
[ "$(grep -c "^CREATE TABLE \`$PREFIX" "$DUMP" || true)" -eq "$COUNT" ] || fail "$DUMP obsahuje i jiné tabulky než $PREFIX"
! grep -q "$OLD" "$DUMP" || fail "$DUMP obsahuje $OLD (tabulky živého webu se nepřenášejí)"
grep -q "'siteurl', *'$KOPIE_URL'" "$DUMP" || fail "siteurl v $DUMP není $KOPIE_URL (export z testovací kopie?)"

echo "== Databáze v dočasném Dockeru ($COUNT tabulek $PREFIX)"
compose down -v --remove-orphans >/dev/null 2>&1
trap 'compose down -v >/dev/null 2>&1' EXIT
# Kontejner WordPressu jen připraví soubory pro WP-CLI a jádro, běžet nesmí (nic se nemá spustit).
compose up -d --wait wordpress
compose stop wordpress
db mysql --default-character-set=utf8mb4 wordpress < "$DUMP"
# Bez vstupního skriptu obrazu: ten volá „wp help sh“, načte WordPress i s pluginy a obejde --skip-plugins.
compose run --rm -T --entrypoint sh -e KOPIE_URL="$KOPIE_URL" -e OSTRY_URL="$URL" cli /scripts/ostry-db.sh

echo "== Jádro WordPressu do $OUT/wordpress"
mkdir -p "$OUT/wordpress"
CORE_DB=$(compose run --rm -T --entrypoint sh cli -c "php -r 'include \"wp-includes/version.php\"; echo \$wp_db_version;'")
SITE_DB=$(db mysql -N -e "SELECT option_value FROM ${PREFIX}options WHERE option_name = 'db_version'" wordpress)
WP_VERSION=$(compose run --rm -T --entrypoint sh cli -c "php -r 'include \"wp-includes/version.php\"; echo \$wp_version;'")
[ "$CORE_DB" -ge "$SITE_DB" ] || fail "databáze kopie ($SITE_DB) je novější než WordPress $WP_VERSION z Dockeru ($CORE_DB), aktualizuj obraz v docker-compose.yml"
compose run --rm -T --entrypoint tar cli -C /var/www/html -c --exclude=./wp-content --exclude='./wp-config*.php' --exclude=./.htaccess . | tar -x -C "$OUT/wordpress"
[ -f "$OUT/wordpress/wp-settings.php" ] && [ -d "$OUT/wordpress/wp-admin" ] && [ -d "$OUT/wordpress/wp-includes" ] || fail "jádro WordPressu v $OUT/wordpress není celé"

echo "== Export tabulek $PREFIX do $OUT/ostry.sql"
TABLES=$(db mysql -N -e "SHOW TABLES LIKE '${PREFIX%_}\\_%'" wordpress)
# shellcheck disable=SC2086 # názvy tabulek jako samostatné argumenty
db mysqldump --skip-dump-date --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 wordpress $TABLES > "$OUT/ostry.sql"

echo "== Kontrola"
[ "$(echo "$TABLES" | wc -l)" -eq "$COUNT" ] || fail "v databázi je $(echo "$TABLES" | wc -l) tabulek $PREFIX, v exportu $COUNT"
[ "$(grep -c "^CREATE TABLE \`$PREFIX" "$OUT/ostry.sql" || true)" -eq "$COUNT" ] || fail "v ostry.sql chybí tabulky $PREFIX"
[ "$(grep -c '^CREATE TABLE' "$OUT/ostry.sql")" -eq "$COUNT" ] || fail "ostry.sql obsahuje i jiné tabulky než $PREFIX"
! grep -q "$OLD" "$OUT/ostry.sql" || fail "ostry.sql obsahuje $OLD"
! grep -Eiq '^(CREATE DATABASE|USE |DROP DATABASE)' "$OUT/ostry.sql" || fail "ostry.sql pracuje s celou databází"
[ -z "$(grep '^DROP TABLE' "$OUT/ostry.sql" | grep -v "^DROP TABLE IF EXISTS \`$PREFIX")" ] || fail "ostry.sql maže jiné tabulky než $PREFIX"
! grep -q "${KOPIE_URL#https://}" "$OUT/ostry.sql" || fail "ostry.sql pořád obsahuje ${KOPIE_URL#https://}"
grep -q "'siteurl','$URL'" "$OUT/ostry.sql" || fail "siteurl v ostry.sql není $URL"
grep -q "'blog_public','1'" "$OUT/ostry.sql" || fail "ostry.sql nemá zapnutou indexaci"
! grep -Eq 'uca1400|current_timestamp\(\)|json_valid' "$OUT/ostry.sql" || fail "ostry.sql obsahuje zápis MariaDB, který MySQL 5.7 na hostingu nezná"
# Zdvojeně zakódovaná čeština (č → Ä, ř → Å™ …): nesmí jí přibýt.
BROKEN_SRC=$(grep -c 'Ã\|Å\|Ä' "$DUMP" || true)
BROKEN_OUT=$(grep -c 'Ã\|Å\|Ä' "$OUT/ostry.sql" || true)
[ "$BROKEN_OUT" -le "$BROKEN_SRC" ] || fail "ostry.sql má rozbitou češtinu ($BROKEN_OUT řádků, v exportu $BROKEN_SRC)"

cat <<EOF

Hotovo: $OUT/ostry.sql ($(du -h "$OUT/ostry.sql" | cut -f1), $COUNT tabulek $PREFIX pro $URL) a $OUT/wordpress (WordPress $WP_VERSION).
Další kroky: docs/prepnuti.md, Přepnutí. Po přepnutí $OUT smaž, jsou v něm osobní údaje zákazníků.
EOF
