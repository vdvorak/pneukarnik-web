#!/bin/sh
# Při přepnutí (docs/prepnuti.md, Přepnutí) nahraje na ostrý web přes FTPS jádro WordPressu z make ostry,
# šablonu a plugin a smaže drop-in W3 Total Cache. wp-config.php a .htaccess nemění, ty se upravují ručně.
# Web musí být v údržbě (.maintenance), jinak by nový plugin hned načetl stará data.
# Spouští se make deploy-ostry (heslo se vyžádá interaktivně), přístup jde přepsat i přes
# HOST=… FTP_USER=… REMOTE=… make deploy-ostry.
set -eu

HOST=${HOST:-ftp.pneukarnik.cz}
FTP_USER=${FTP_USER:-vdvorak.pneukarnik.cz}
REMOTE=${REMOTE:-/public_html/www}
PORT=${PORT:-21}
CORE=ostry/wordpress

command -v lftp >/dev/null 2>&1 || {
	echo "Chybí lftp (Debian/Ubuntu: apt install lftp, macOS: brew install lftp)" >&2
	exit 1
}

cd "$(git rev-parse --show-toplevel)"

[ -f "$CORE/wp-settings.php" ] || {
	echo "Chybí jádro WordPressu v $CORE, nejdřív make ostry (docs/prepnuti.md)." >&2
	exit 1
}

echo "Nahraju na $FTP_USER@$HOST:$PORT$REMOTE (FTPS):"
echo "  wp-admin, wp-includes a soubory v kořeni z $CORE (WordPress, bez wp-config.php)"
echo "  wp-content/themes/pneukarnik a wp-content/plugins/pneukarnik-booking"
echo "  a smažu wp-content/advanced-cache.php (W3 Total Cache)."
echo "wp-admin, wp-includes, šablona a plugin se zrcadlí: soubory, které lokálně nejsou, se na serveru smažou."
echo "Web musí být v údržbě (.maintenance v $REMOTE, krok 2)."
printf 'Pokračovat? [y/N] '
read -r odpoved
case "$odpoved" in
	y | Y) ;;
	*)
		echo "Zrušeno."
		exit 1
		;;
esac

# Heslo lftp samo vyžádá interaktivně (stdin zůstává volný, protože příkazy jdou přes -e, ne heredoc).
lftp -u "$FTP_USER" -p "$PORT" "ftp://$HOST" -e "
set ftp:ssl-force true;
set ftp:ssl-protect-data true;
# Certifikát hostingu (Webglobe) nesedí na vlastní doménu, přenos ale zůstává šifrovaný.
set ssl:verify-certificate false;
mirror --reverse --delete --verbose $CORE/wp-admin '$REMOTE/wp-admin';
mirror --reverse --delete --verbose $CORE/wp-includes '$REMOTE/wp-includes';
mirror --reverse --no-recursion --verbose $CORE '$REMOTE';
mirror --reverse --delete --verbose wp-content/themes/pneukarnik '$REMOTE/wp-content/themes/pneukarnik';
mirror --reverse --delete --verbose wp-content/plugins/pneukarnik-booking '$REMOTE/wp-content/plugins/pneukarnik-booking';
rm -f '$REMOTE/wp-content/advanced-cache.php';
bye
"

echo "Hotovo. Pokračuj krokem 7 v docs/prepnuti.md (wp-config.php, .htaccess, údržba)."
