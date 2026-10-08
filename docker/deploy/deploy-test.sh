#!/bin/sh
# Nahraje šablonu a plugin na testovací kopii (test.pneukarnik.cz, docs/prepnuti.md) přes FTPS.
# Nic jiného neřeší: subdoména, certifikát, heslo a databáze kopie musí už existovat (kroky 1–6
# v docs/prepnuti.md). Spouští se make deploy-test (heslo se vyžádá interaktivně), přístup jde
# přepsat i přes HOST=… FTP_USER=… REMOTE=… make deploy-test.
set -eu

HOST=${HOST:-ftp.pneukarnik.cz}
FTP_USER=${FTP_USER:-vdvorak.pneukarnik.cz}
REMOTE=${REMOTE:-/public_html/test}
PORT=${PORT:-21}

command -v lftp >/dev/null 2>&1 || {
	echo "Chybí lftp (Debian/Ubuntu: apt install lftp, macOS: brew install lftp)" >&2
	exit 1
}

cd "$(git rev-parse --show-toplevel)"

echo "Nahraju wp-content/themes/pneukarnik a wp-content/plugins/pneukarnik-booking"
echo "na $FTP_USER@$HOST:$PORT$REMOTE (FTPS, smaže na serveru soubory, které už lokálně nejsou)."
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
mirror --reverse --delete --verbose wp-content/themes/pneukarnik '$REMOTE/wp-content/themes/pneukarnik';
mirror --reverse --delete --verbose wp-content/plugins/pneukarnik-booking '$REMOTE/wp-content/plugins/pneukarnik-booking';
bye
"

echo "Hotovo. Zkontroluj na $HOST: wp-content/debug.log je prázdný a nové věci fungují."
