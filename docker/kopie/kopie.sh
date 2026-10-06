#!/bin/sh
# Testovací kopie živého webu pro hosting s jedinou databází (docs/prepnuti.md, #23).
# Ze zálohy Duplicatoru připraví v $OUT:
#   web/        soubory k nahrání na subdoménu, bez záloh, cache a /nova/, s upraveným wp-config.php
#   kopie.sql   tabulky s prefixem $PREFIX místo hwjcw_ a adresami $URL, k importu do STEJNÉ databáze
# Databázi upraví WP-CLI v dočasném Dockeru s MySQL 5.7 jako na hostingu (adresy v serializovaných
# datech), pak ho smaže.
# Spouští make kopie ZALOHA=…_archive.zip.
set -eu

OLD=hwjcw_
URL=${URL:-https://test.pneukarnik.cz}
PREFIX=${PREFIX:-tstpk_}
OUT=${OUT:-kopie}

compose() {
	WP_PORT=8091 MAILPIT_PORT=8036 docker compose -p pneukarnik-kopie -f docker-compose.yml -f docker/kopie/compose.yml "$@"
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

[ -f "${ZALOHA:-}" ] || fail "chybí záloha: make kopie ZALOHA=cesta/k/…_archive.zip (Duplicator)"
echo "$PREFIX" | grep -Eq '^[a-z0-9]+_$' || fail "PREFIX smí mít jen malá písmena a číslice a končit _"
# Stejná délka: přejmenování prefixu v hodnotách nerozbije délky v serializovaných datech.
[ "${#PREFIX}" -eq "${#OLD}" ] && [ "$PREFIX" != "$OLD" ] || fail "PREFIX musí mít ${#OLD} znaků a lišit se od $OLD"
echo "$URL" | grep -Eq '^https://[a-z0-9.-]+$' || fail "URL ve tvaru https://test.pneukarnik.cz (bez lomítka na konci)"
[ ! -e "$OUT" ] || fail "$OUT už existuje, smaž ho (jsou v něm osobní údaje zákazníků)"

echo "== Soubory do $OUT/web"
mkdir -p "$OUT/web"
unzip -q "$ZALOHA" -d "$OUT/web" -x \
	'dup-installer/*' 'installer.php' '*_installer-backup.php' 'nova/*' 'awstats/*' 'aios-bootstrap.php' '.user.ini' '.htaccess' \
	'wp-content/backups-dup-lite/*' 'wp-content/aiowps_backups/*' 'wp-content/cache/*' 'wp-content/w3tc-config/*' \
	'wp-content/upgrade/*' 'wp-content/upgrade-temp-backup/*' 'wp-content/debug.log' \
	'wp-content/advanced-cache.php' 'wp-content/object-cache.php' 'wp-content/db.php'

echo "== wp-config.php (prefix $PREFIX, bez W3 Total Cache, cron vypnutý)"
unzip -p "$ZALOHA" 'dup-installer/original_files_*/source_site_wpconfig' > "$OUT/web/wp-config.php"
grep -q "^\$table_prefix = '$OLD';" "$OUT/web/wp-config.php" || fail "wp-config ze zálohy nemá \$table_prefix = '$OLD'"
grep -q "^/\* That's all, stop editing" "$OUT/web/wp-config.php" || fail "wp-config ze zálohy nemá řádek „That's all, stop editing“"
sed -i \
	-e "s/^\$table_prefix = '$OLD';/\$table_prefix = '$PREFIX'; \/\/ Testovací kopie, živý web má $OLD ve stejné databázi./" \
	-e "/define( *'WP_CACHE'/d" \
	-e "/define( *'WP_DEBUG'/d" \
	-e "/^\/\* That's all, stop editing/i\\
// Testovací kopie (docs/prepnuti.md): sama nic nepošle, chyby do wp-content/debug.log.\\
define( 'DISABLE_WP_CRON', true );\\
define( 'WP_DEBUG', true );\\
define( 'WP_DEBUG_LOG', true );\\
define( 'WP_DEBUG_DISPLAY', false );\\
" \
	"$OUT/web/wp-config.php"

echo "== .htaccess (heslo, adresy WordPressu)"
# Dokud se nedoplní cesta k .htpasswd, vrací Apache chybu 500: kopie bez hesla nikdy neběží.
cat > "$OUT/web/.htaccess" <<'HTACCESS'
# Testovací kopie: heslo na úrovni serveru (docs/prepnuti.md). Doplň plnou cestu k .htpasswd.
AuthType Basic
AuthName "Testovaci web"
AuthUserFile /DOPLNIT/plnou/cestu/k/.htpasswd
<RequireAny>
	Require valid-user
	Require expr %{REQUEST_URI} =~ m#^/\.well-known/#
</RequireAny>

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS

echo "== Databáze v dočasném Dockeru"
compose down -v --remove-orphans >/dev/null 2>&1
trap 'compose down -v >/dev/null 2>&1' EXIT
# Kontejner WordPressu jen připraví soubory pro WP-CLI. Běžet nesmí: požadavek na web by nad starou
# databází načetl nový plugin (stejný adresář jako starý pokus) a ten by ji hned dorovnal.
compose up -d --wait wordpress
compose stop wordpress
# Záloha Duplicatoru neuvádí kódování, klient MySQL 5.7 by ji jinak četl jako latin1 a češtinu zdvojeně zakódoval.
unzip -p "$ZALOHA" 'dup-installer/dup-database__*.sql' | db mysql --default-character-set=utf8mb4 wordpress
[ -z "$(db mysql -N -e "SHOW TABLES LIKE '${PREFIX%_}\\_%'" wordpress)" ] || fail "záloha už obsahuje tabulky $PREFIX"
# Bez vstupního skriptu obrazu: ten volá „wp help sh“, načte WordPress i s pluginy a obejde --skip-plugins.
compose run --rm -T --entrypoint sh -e KOPIE_URL="$URL" cli /scripts/kopie-db.sh

echo "== Export tabulek $OLD jako $PREFIX do $OUT/kopie.sql"
TABLES=$(db mysql -N -e "SHOW TABLES LIKE '${OLD%_}\\_%'" wordpress)
# shellcheck disable=SC2086 # názvy tabulek jako samostatné argumenty
db mysqldump --skip-dump-date --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 wordpress $TABLES \
	| sed "s/$OLD/$PREFIX/g" > "$OUT/kopie.sql"

echo "== Kontrola"
COUNT=$(echo "$TABLES" | wc -l)
CREATED=$(grep -c "^CREATE TABLE \`$PREFIX" "$OUT/kopie.sql" || true)
[ "$CREATED" -eq "$COUNT" ] || fail "v kopie.sql je $CREATED tabulek $PREFIX, čekáno $COUNT"
[ "$(grep -c '^CREATE TABLE' "$OUT/kopie.sql")" -eq "$COUNT" ] || fail "kopie.sql obsahuje i jiné tabulky než $PREFIX"
! grep -q "$OLD" "$OUT/kopie.sql" || fail "kopie.sql pořád obsahuje $OLD"
! grep -Eiq '^(CREATE DATABASE|USE |DROP DATABASE)' "$OUT/kopie.sql" || fail "kopie.sql pracuje s celou databází"
[ -z "$(grep '^DROP TABLE' "$OUT/kopie.sql" | grep -v "^DROP TABLE IF EXISTS \`$PREFIX")" ] || fail "kopie.sql maže jiné tabulky než $PREFIX"
grep -q "'siteurl','$URL'" "$OUT/kopie.sql" || fail "siteurl v kopie.sql není $URL"
! grep -Eq 'uca1400|current_timestamp\(\)|json_valid' "$OUT/kopie.sql" || fail "kopie.sql obsahuje zápis MariaDB, který MySQL 5.7 na hostingu nezná"
# Zdvojeně zakódovaná čeština (č → Ä, ř → Å™ …): v kopii nesmí být víc takových řádků než v záloze.
BROKEN_SRC=$(unzip -p "$ZALOHA" 'dup-installer/dup-database__*.sql' | grep -c 'Ã\|Å\|Ä' || true)
BROKEN_OUT=$(grep -c 'Ã\|Å\|Ä' "$OUT/kopie.sql" || true)
[ "$BROKEN_OUT" -le "$BROKEN_SRC" ] || fail "kopie.sql má rozbitou češtinu ($BROKEN_OUT řádků, v záloze $BROKEN_SRC)"

cat <<EOF

Hotovo: $OUT/web ($(du -sh "$OUT/web" | cut -f1)) a $OUT/kopie.sql ($(du -h "$OUT/kopie.sql" | cut -f1)), $COUNT tabulek $PREFIX.
Další kroky: docs/prepnuti.md, Testovací kopie. Po nahrání na hosting $OUT smaž, jsou v něm osobní údaje zákazníků.
EOF
