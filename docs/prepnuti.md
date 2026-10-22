# Přepnutí na nový web

Checklist pro #23. Nový web nahradí starý **ve stejné instalaci WordPressu a databázi**: obsah se připraví na testovací kopii (tabulky `tstpk_`) a ostrý web při přepnutí přejde na její tabulky. Staré tabulky živého webu (`hwjcw_`) zůstanou beze změny a převod (#21) z nich znovu načte Rezervace a souhlasy. Kroky označené „ověřeno“ prošly zkouškou nad zálohou živé databáze (`make zkouska` a Výsledek zkoušky přepnutí níže).

## Zkouška lokálně

```sh
unzip -p ~/Downloads/…/backups-dup-lite/…_archive.zip 'dup-installer/dup-database__*.sql' > /tmp/zaloha.sql
make zkouska DUMP=/tmp/zaloha.sql   # web http://localhost:8090 (zkouska / zkouska), pošta http://localhost:8035
make zkouska-down                   # po zkoušce smazat, jsou tam osobní údaje zákazníků
```

`make zkouska` založí oddělené prostředí (vývojové na :8080 se nemění), nahraje zálohu a provede nad starou databází kroky ze skriptu `docker/cli/prepnuti.sh` (nový web nad tabulkami `hwjcw_`, hodí se na zkoušku převodu). Všechny e‑maily skončí v Mailpitu zkoušky. Staré pluginy (WP Mail SMTP …) se nenačtou, jejich soubory v kontejneru nejsou.

## Rozhodnout před přepnutím

- **Kde připravit obsah:** na testovací kopii (rozhodnuto 2026‑10‑22, obsah tam je). Ostrý web při přepnutí přejde na její tabulky `tstpk_` (změna `$table_prefix`), staré `hwjcw_` zůstanou jako archiv a cesta zpět. Rezervace, souhlasy a Rozesílky kopie se zahodí a převedou se znovu z čerstvé staré tabulky, ať na ostrém webu nezůstane nic zkušebního ani Rezervace zrušená na starém webu po vytvoření kopie.
- **E‑mail s novým odkazem na Zrušení** převedeným budoucím Rezervacím: ano (rozhodl vývojář s Provozovatelem). Na kopii převádět bez e‑mailu, naostro s ním.
- **Zálohy ostrého webu.** Všechny Rezervace a souhlasy jsou jen v databázi. Zjistit u Webglobe, jak často a jak dlouho databázi zálohují a jak se záloha obnoví. Když to nestačí (třeba jen týden zpětně), domluvit vlastní pravidelný export.
- **Cron na hostingu.** Zjistit, jestli Webglobe umí spouštět naplánovaný příkaz (cron). Plánované úlohy pluginu (anonymizace, Připomínky přezutí a Termínu, Rozesílky, recenze) jinak běží přes WP‑Cron, tedy jen když někdo otevře web a web si úspěšně zavolá sám sebe.

## Testovací kopie (hosting Webglobe)

Kopie živého webu na `test.pneukarnik.cz` (název je na nás). Obsahuje osobní údaje zákazníků, proto je od prvního souboru za heslem a nesmí sama posílat e‑maily. Postup v administraci Webglobe je podle jejich nápovědy (poradna webglobe.cz), kroky ve WordPressu prošly zkouškou.

Hosting má **jedinou databázi**, kopie proto bydlí ve stejné databázi jako živý web, jen s prefixem tabulek `tstpk_` (živý web má `hwjcw_`). Plugin bere prefix z `wp-config.php`, převod dat funguje stejně. Platí dvě pravidla:

- **Nikdy nepoužívat installer Duplicatoru** (ani zálohu/obnovu Duplicatorem na kopii): jeho volby databáze mažou všechny tabulky, tedy i živý web.
- **V `wp-config.php` kopie musí zůstat `$table_prefix = 'tstpk_';`.** S `hwjcw_` by kopie běžela nad daty živého webu (aktivace šablony by přepnula ostrý web).

**Potřebuješ:** přístup do administrace Webglobe (hosting, DNS, e‑maily, phpMyAdmin), SFTP údaje k hostingu, čerstvou zálohu Duplicatoru z živého webu (`…_archive.zip`), Docker.

### 1. Ověřit PHP živého webu

- [ ] Živý web: Nástroje → Stav webu → Informace → Server: verze PHP (pro přepnutí).
- Nový web potřebuje **PHP ≥ 8.1** (vyvíjí se na 8.3) a **WordPress 7.1** (živý web má 7.0.6). Webglobe nastavuje PHP zvlášť pro každou subdoménu, kopie tedy živý web neovlivní. Na živém webu se verze zvedá až při přepnutí.

### 2. Subdoména a certifikát

- [ ] Webglobe Admin → Hosting → Web → Subdomény → **Přidat subdoménu**: název `test`, adresář `test` (vlastní, ne adresář živého webu, ten je `www`), PHP nejnovější 8.x (ideálně 8.3) → Uložit. Za chvíli je aktivní.
- [ ] Pokud DNS domény není u Webglobe, přidat záznam pro `test` (stejný A záznam jako `pneukarnik.cz`).
- [ ] Hosting → SSL certifikát → Nový SSL certifikát → Let's Encrypt → Vygenerovat pro `test.pneukarnik.cz`, dokud je adresář prázdný (bez hesla). Doména musí mířit na hosting Webglobe. Certifikát se sám obnovuje, `.htaccess` kopie mu ověření pustí i za heslem.

### 3. Připravit kopii lokálně

- [ ] V repu:
  ```sh
  make kopie ZALOHA=~/Downloads/…_archive.zip   # volitelně URL=https://test.pneukarnik.cz PREFIX=tstpk_
  ```
  Za pár minut vznikne `kopie/` (v gitu ignorovaný, jsou v něm osobní údaje):
  - `kopie/web/`: soubory ze zálohy bez záloh, cache W3 Total Cache, `/nova/` a statistik. Navíc:
    - `wp-config.php`: prefix `tstpk_`, bez `WP_CACHE`, s `DISABLE_WP_CRON` (kopie sama nic nepošle) a `WP_DEBUG_LOG` (chyby do `wp-content/debug.log`). Přístupy k databázi zůstávají z živého webu, databáze je stejná.
    - `.htaccess`: heslo a adresy WordPressu. Dokud se nedoplní cesta k `.htpasswd`, web nikoho nepustí.
  - `kopie/kopie.sql`: všechny tabulky živého webu jako `tstpk_`, adresy přepsané na `https://test.pneukarnik.cz` (WP‑CLI, i v serializovaných datech), vyloučení z indexace zapnuté, W3 Total Cache vypnutý. Web je jinak ve stavu živého, přepnutí se nacvičuje až na hostingu (krok 7).
- Databáze na hostingu je **Percona Server 5.7.44** (MySQL 5.7), `make kopie` proto pracuje s MySQL 5.7.44 a export jde do phpMyAdminu beze změn. Export z MariaDB hosting odmítne (`Unknown collation: 'utf8mb4_uca1400_ai_ci'`).
- Skript sám zkontroluje, že `kopie.sql` vytváří a maže jen tabulky `tstpk_` a nikde v něm nezůstalo `hwjcw_`. Varování „Skipping an uninitialized class Astra…/Elementor…“ jsou zbytky dřívějších šablon v datech, nevadí.

### 4. Heslo

- [ ] Soubor s heslem:
  ```sh
  docker run --rm httpd:2.4 htpasswd -nbB tester 'dlouhe-heslo' > .htpasswd
  ```
- [ ] `.htpasswd` nahrát přes SFTP **mimo adresáře webů** (vedle `www` a `test`, ne do nich). Plnou cestu na serveru ukáže administrace, nebo dočasný soubor `cesta.php` s `<?php echo __DIR__;` v adresáři `test` (po přečtení smazat).
- [ ] V `kopie/web/.htaccess` nahradit `/DOPLNIT/plnou/cestu/k/.htpasswd` skutečnou cestou.

### 5. Nahrát soubory

- [ ] Obsah `kopie/web/` (včetně skrytého `.htaccess`) nahrát přes SFTP do adresáře subdomény `test`.
- [ ] Ověřit: `https://test.pneukarnik.cz/` bez hesla vrací 401, s heslem chybu připojení k databázi nebo instalaci WordPressu (tabulky `tstpk_` ještě nejsou). Instalaci **nedokončovat**.

### 6. Databáze

- [ ] **Záloha živé databáze:** phpMyAdmin → databáze → Exportovat (rychlý, SQL), soubor uložit. Duplicator na živém webu jde taky, jen ho neobnovovat do kopie.
- [ ] Zapsat si počet tabulek `hwjcw_` (phpMyAdmin, filtr tabulek `hwjcw_`).
- [ ] phpMyAdmin → stejná databáze → Import → `kopie/kopie.sql` (18 MB; při limitu nahrávání ho zabalit `gzip kopie/kopie.sql` a importovat `.sql.gz`).
- [ ] Kontrola: přibylo 58 tabulek `tstpk_`, počet `hwjcw_` se nezměnil, živý web běží.
- [ ] `https://test.pneukarnik.cz/` (s heslem) ukáže starý web s adresami `test.pneukarnik.cz`. Přihlášení `…/wp-admin/` je stejné jako na živém webu.
- [ ] Nastavení → Obecné: obě adresy `https://test.pneukarnik.cz`. Nastavení → Čtení: „Požádat vyhledávače, aby neindexovaly tento web“ je zaškrtnuté.
- Hlášení „Action Scheduler: … past-due actions found“ v administraci kopie nevadí. Úlohy údržby WP Mail SMTP ze zálohy čekají na WP‑Cron, který má kopie vypnutý. Hlášku jde zavřít, WP Mail SMTP kvůli ní nevypínat (kopie má zůstat jako živý web).

### 7. Kroky přepnutí na kopii

Nový web nad tabulkami `tstpk_` (zálohou kopie je `kopie/`), v tomto pořadí:

- [ ] Pluginy: hromadně **deaktivovat všechny** staré pluginy. Kvůli WordPressu 7.1 to musí být před aktualizací.
- [ ] Nástěnka → Aktualizace: WordPress na 7.1, pak „Aktualizovat databázi“, pokud ji WordPress nabídne. Ověřeno: 58975 → 61833.
- [ ] Nahrát šablonu a plugin z repa. Zipy připravíš v kořeni repa:
  ```sh
  (cd wp-content/themes && zip -rq ../../pneukarnik.zip pneukarnik)
  (cd wp-content/plugins && zip -rq ../../pneukarnik-booking.zip pneukarnik-booking)
  ```
  Vzhled → Motivy → Přidat → Nahrát `pneukarnik.zip`. Pluginy → Přidat → Nahrát `pneukarnik-booking.zip`. Plugin ze starého pokusu tam už je, zvolit **„Nahradit stávající“**. Jiná cesta je SFTP: starý adresář `wp-content/plugins/pneukarnik-booking` nejdřív smazat, ať nezůstanou staré soubory. Pro opakované nahrávání jen šablony a pluginu (po dalších commitech) jde použít `make deploy-test` (`docker/deploy/deploy-test.sh`, přístup napevno pro `test.pneukarnik.cz`): přes FTPS zrcadlí oba adresáře i s mazáním starých souborů, heslo zadáš interaktivně. Jiný přístup jde přepsat přes `HOST=… FTP_USER=… REMOTE=… make deploy-test`.
- [ ] Aktivovat šablonu Pneukarník a plugin Pneukarnik Booking. Databáze pluginu se dorovná z verze 1.2 (starý pokus).
- [ ] Nastavení → Trvalé odkazy → Uložit. Struktura `/%year%/%monthnum%/%day%/%postname%/` může zůstat, nové stránky na ní fungují. WordPress doplní svůj blok do `.htaccess`, blok s heslem musí zůstat nahoře.
- [ ] Rezervace → Převod ze starého webu **bez e‑mailu Zákazníkům** a projít report.
- [ ] Smazat staré pluginy (Pods, Timber, Complianz, W3 Total Cache, Contact Form 7, Email Log). WP Mail SMTP nechat kvůli kroku 8 níže. (`/nova/` v kopii není.)
- [ ] `wp-content/debug.log` je prázdný.

### 8. E‑maily: SMTP, SPF, DKIM

Stav k 2026‑10‑22 (veřejné DNS a nastavení WP Mail SMTP v kopii): DNS domény je u Onebitu (Webglobe, `ns.onebit.cz`). Živý web posílá přes `mail.webglobe.cz:465` z `info@pneukarnik.cz`. SPF `v=spf1 mx include:_spf.onebit.cz ~all` ten server povoluje, DKIM `default._domainkey` existuje, DMARC chybí. Zbývá tedy hlavně DMARC a zkouška doručení.

- [ ] Administrace Webglobe: e‑mailová schránka na doméně pro odesílání (dnes `info@pneukarnik.cz`, jiná jen když bude důvod). Poznamenat si údaje SMTP (server, port, šifrování).
- [ ] DNS `pneukarnik.cz` (u Webglobe nebo jinde):
  - **SPF:** TXT záznam `v=spf1 … -all` obsahuje poštovní servery Webglobe. Přesné znění (`include:` …) dá administrace nebo podpora Webglobe. Smí být jen jeden SPF záznam.
  - **DKIM:** zapnout pro doménu v administraci pošty Webglobe a ověřit, že se v DNS objevil záznam `…._domainkey`.
  - **DMARC** (doporučeno): TXT `_dmarc.pneukarnik.cz` = `v=DMARC1; p=none; rua=mailto:rezervace@pneukarnik.cz`.
- [ ] Na kopii aktivovat **WP Mail SMTP**: zvolit Ostatní SMTP, údaje schránky, zapnout „Vynutit e‑mail odesílatele“ s adresou schránky. Bez toho plugin posílá z `noreply@test.pneukarnik.cz`, a to SPF ani DKIM neprojde.
- [ ] Zkušební rezervace na vlastní adresu v Gmailu a na Seznamu:
  - nesmí skončit ve spamu
  - Gmail → Zobrazit původní zprávu: `SPF: PASS`, `DKIM: PASS`, `DMARC: PASS`
  - Seznam: zobrazit zdroj zprávy a v hlavičce `Authentication-Results` ověřit SPF a DKIM `pass`
- Volitelně: rezervace na adresu z mail-tester.com a skóre aspoň 9/10.

### 9. Obsah a ověření

Služby, Průvodci, Nastavení a oba účty jsou na kopii doplněné podle odpovědí Provozovatele (2026‑10‑22, import tabulek `options`, `posts`, `postmeta`, `users` a `usermeta` vyrobený lokálně přes WP‑CLI). Provozovatel je zkontroluje, otevřené zůstává: cena Dekarbonizace (web „od 1 690 Kč“, starý web 2 290 Kč nad 2 000 cm³), finální text Ochrany osobních údajů.

- [ ] Účty (Uživatelé → Přidat), administrátorem zůstává vývojář:
  - Provozovatel: role **Provozovatel** (Služby, Akce, Průvodci, stránky, Rezervace, Nastavení, Výjimky, E‑maily Zákazníkům; ne pluginy, uživatelé a Převod).
  - Technici: společný účet s rolí **Rezervace – prohlížení** (kalendář, seznam a PDF přehled dne).
  - Oběma heslo k testovací kopii.
- [ ] Provozovatel doplní Služby (viz Výsledek zkoušky převodu), Nastavení (kontakty, Pracovní doba, Sezóny, Lhůta zrušení), stránky O nás, Kontakt, Ochrana osobních údajů (perex do výňatku stránky) a Průvodce.
  Nastavení podle `docs/podklady-od-klienta/odpovedi-3.md`:
  - Pracovní doba Po–Pá 8:00–12:00 a 13:00–17:00, So–Ne zavřeno.
  - Sezóny: jarní 15. 3. až 30. 4. s leasing od 1. 4., podzimní 1. 10. až 15. 11. s leasing do 31. 10. („hotové do 1. 11.“, ověřit telefonem, viz `docs/otazky-pro-klienta.md`).
  - Předstih pro dnešek 1440 min (objednat se jde nejpozději den předem), Lhůta zrušení 24 hodin, Horizont zůstává 60 dní (Provozovatel neví).
  - E‑mail o každé nové online Rezervaci zapnutý.

  Podle `docs/podklady-od-klienta/odpovedi-1.md` a `odpovedi-2.md`:
  - E‑mail `j.v.karnik@volny.cz`.
  - Pohotovost zapnutá, telefon 775565326, popis „Defekt na cestě? Přijedeme za vámi. Znojmo a okolí, Po–Pá 8–17 h.“
  - Rok založení 1991. Proč k nám:
    ```
    Zkušenosti od roku 1991 | Servisu se věnujeme od roku 1991.
    Pneu i autoservis | Vše vyřídíte na jednom místě.
    Osobní přístup | Zákazníci se k nám dlouhodobě vracejí.
    Rychlé objednání | Často se dostanete na řadu ještě tentýž den.
    Férové ceny | Ceny odpovídají odvedené práci.
    ```
  - Google recenze zapnuté.
  - Nejžádanější Služby: Přezutí pneu, Geometrie, Výměna oleje. Akce žádná.
  - Sociální sítě: Facebook `https://www.facebook.com/jvkpneu`, Instagram `https://www.instagram.com/pneuservisjan/`, Google Firma `https://share.google/v4htT35a3nmeGdn5g`.
- [ ] Zkušební rezervace, Zrušení odkazem z e‑mailu, PDF přehled a iCal.
- [ ] Cron je vypnutý. Anonymizaci, Připomínky přezutí a Termínu, Rozesílky a recenze jde spustit ručně, když je potřeba vyzkoušet: plugin WP Crontrol, nebo dočasně `DISABLE_WP_CRON` na `false`.
- [ ] Návrat zpět (nacvičení): znovu nahrát soubory a `kopie.sql` podle kroků 5 a 6 a v phpMyAdminu smazat tabulky `tstpk_`, které přidal nový plugin (`tstpk_pneukarnik_booking_services`, `…_day_exceptions`, `…_subscriptions`). Starý web na kopii musí naběhnout, pak krok 7 zopakovat. Proto `kopie/` lokálně smaž až po této zkoušce.

**Tabulky `tstpk_` nemazat:** při přepnutí se z nich stane ostrý web. Adresář subdomény a lokální `kopie/` se mažou až v kroku 10 přepnutí, všude jsou osobní údaje zákazníků.

## Přepnutí (mimo sezónu, cíl leden–únor 2027)

Ostrý web přejde na tabulky testovací kopie: v jeho `wp-config.php` se změní `$table_prefix` z `hwjcw_` na `tstpk_`. Obsah, Nastavení a účty z kopie tak platí rovnou. Staré tabulky `hwjcw_` přepnutí nemění (ověřeno součty), zůstanou jako archiv a cesta zpět. Rezervace a souhlasy se převedou znovu z čerstvé staré tabulky.

**Potřebuješ:** totéž co pro kopii (administrace Webglobe, phpMyAdmin, SFTP, FTPS přes `lftp` s přístupem do `/public_html/www`, Docker) a zhruba dvě hodiny večer. Kopie musí být hotová: co se na ní uloží po exportu v kroku 3, na ostrý web nepřejde. Na kopii se nezadávají skutečné Rezervace, Rezervace, souhlasy a Rozesílky kopie se v kroku 3 zahodí.

1. [ ] **Zálohy:** živý web Duplicatorem (soubory i databáze), archiv stáhnout. phpMyAdmin → Exportovat celou databázi.
2. [ ] **Údržba:** do `www` (SFTP) nahrát soubor `.maintenance` s obsahem `<?php $upgrading = time();`. Web i administrace ukazují „Probíhá plánovaná údržba“: starý web nepřijme Rezervaci a nový plugin nic nenačte, dokud se v kroku 6 nepřepne prefix (starý plugin `pneukarnik-booking` je na živém webu aktivní, nové soubory by jinak hned dorovnaly tabulky `hwjcw_`).
3. [ ] **Export kopie:** phpMyAdmin → Struktura → filtr `tstpk_` → Zaškrtnout vše → Se zaškrtnutými: Exportovat (SQL). V repu:
   ```sh
   make ostry DUMP=~/Downloads/…_tstpk.sql
   ```
   Vznikne `ostry/` (v gitu ignorovaný, jsou v něm osobní údaje): `ostry.sql` s adresami `https://pneukarnik.cz`, zapnutou indexací a bez Rezervací, souhlasů a Rozesílek kopie, a `ostry/wordpress/` s jádrem WordPressu ve verzi, pro kterou je databáze. Skript sám zkontroluje, že `ostry.sql` vytváří a maže jen tabulky `tstpk_`, nikde v něm není `hwjcw_` ani adresa kopie.
4. [ ] **Import:** phpMyAdmin → Import → `ostry/ostry.sql` (přes 18 MB: `gzip` a importovat `.sql.gz`). Nahradí tabulky `tstpk_`, `hwjcw_` nemění. Testovací kopie od teď přesměrovává na pneukarnik.cz.
5. [ ] **Staré Rezervace:** phpMyAdmin → SQL:
   ```sql
   SET SESSION sql_mode = '';
   DROP TABLE IF EXISTS tstpk_reservations;
   CREATE TABLE tstpk_reservations LIKE hwjcw_reservations;
   INSERT INTO tstpk_reservations SELECT * FROM hwjcw_reservations;
   SELECT (SELECT COUNT(*) FROM hwjcw_reservations) AS stara, (SELECT COUNT(*) FROM tstpk_reservations) AS kopie;
   ```
   Obě čísla musí být stejná. Bez `sql_mode` MySQL odmítne staré řádky s datem `0000-00-00`, tabulka zůstane prázdná a převod pak nic nepřevede (ověřeno).
6. [ ] **Soubory v `www`:**
   - Webglobe → Hosting → Web → `www`: PHP 8.3 (nejméně 8.1).
   - `make deploy-ostry` (`docker/deploy/deploy-ostry.sh`): jádro z `ostry/wordpress` (`wp-admin` a `wp-includes` se zrcadlí i s mazáním, soubory v kořeni se přepíšou), šablona a plugin, smaže `wp-content/advanced-cache.php` (W3 Total Cache). Ostatní soubory (`wp-content/uploads`, staré pluginy a šablona, `wp-config.php`) nechá.
   - `wp-config.php` (SFTP): `$table_prefix = 'tstpk_';` a smazat řádek `define('WP_CACHE', true); // Added by W3 Total Cache`. Nic dalšího, přístupy k databázi platí.
   - `.htaccess`: smazat bloky `# BEGIN W3TC …` až `# END W3TC …`, blok WordPressu a ostatní nechat. `aios-bootstrap.php` v kořeni nemazat, může ho načítat `.user.ini`.
7. [ ] **Spuštění:** smazat `.maintenance` a přihlásit se (účty z kopie). Když WordPress nabídne „Aktualizovat databázi“, potvrdit, pak Nástěnka → Aktualizace → Překlady. Nastavení → Trvalé odkazy → Uložit. Nástroje → Stav webu bez kritických problémů.
8. [ ] **E‑mail funguje:** WP Mail SMTP → Nástroje → Testovací e‑mail na vlastní adresu dojde. Musí to být před převodem: e‑mail s novým odkazem na Zrušení převod pošle jen jednou, Rezervacím převedeným při nefunkční poště ho znovu nepošle.
9. [ ] **Převod:** Rezervace → Převod ze starého webu **s e‑mailem** Zákazníkům. V reportu musí být Služby přeskočené (vazba z kopie), počet odeslaných e‑mailů rovný budoucím Rezervacím. Chybné Rezervace a budoucí překryvy vyřešit se Zákazníky telefonicky.
10. [ ] **Kopie pryč:** smazat subdoménu `test` a její adresář (běží nad stejnými tabulkami jako ostrý web). Lokálně smazat `kopie/` a `ostry/`.
11. [ ] Zkušební Rezervace na vlastní adresu v Gmailu a na Seznamu (SPF, DKIM, DMARC jako v kroku 8 kopie), Zrušení odkazem z e‑mailu, PDF přehled, iCal. Rezervaci pak zrušit.
12. [ ] Kontrola přesměrování: všechny adresy z `docs/stare-url.md` vrátí 301 na stránku s 200. Staré Služby `/service/…` vedou na detail své Služby, ne na Úvod jako v tabulce (ta platí pro vývojový web bez těch Služeb). Ověřeno zkouškou (57 adres).
13. [ ] Sitemapa (`/wp-sitemap.xml`) do Search Console (doména už má ověření v DNS, zjistit, čí účet). Adresy z přehledu Stránky doplnit do `docs/stare-url.md`.
14. [ ] Plánované úlohy běží: Nástroje → Stav webu bez chyby „smyčkový požadavek“ (loopback) a v pluginu WP Crontrol žádná úloha `pneukarnik_…` po termínu. Když hosting cron umí: do `wp-config.php` `define( 'DISABLE_WP_CRON', true );` a na hostingu každých 15 minut spouštět `wp-cron.php` (např. `wget -q -O - https://pneukarnik.cz/wp-cron.php?doing_wp_cron`). Pak znovu ověřit ve WP Crontrol.
15. [ ] Zálohy podle rozhodnutí výše (Webglobe, případně vlastní export) běží a jedna obnova je vyzkoušená.
16. [ ] **Úklid** (po pár dnech provozu, až nebude potřeba cesta zpět): v `www` smazat `/nova/`, staré pluginy (Pods, Timber, Complianz, W3 Total Cache, Contact Form 7, Email Log, Duplicator), starou šablonu `tmechanics-theme`, `wp-content/cache` a `wp-content/w3tc-config`. WP Mail SMTP zůstává (je aktivní).
17. [ ] **Archiv:** `hwjcw_reservations` exportovat (SQL mimo web) a zapsat datum ručního smazání tabulky za rok (zadání). Ostatní tabulky `hwjcw_` smazat (filtr `hwjcw_`) až po úklidu, dřív ne.

## Návrat zpět

Tabulky `hwjcw_` přepnutí nemění, návrat je hlavně o souborech:

- [ ] `.maintenance` do `www` (krok 2).
- [ ] `wp-config.php`: `$table_prefix = 'hwjcw_';` a zpět `define('WP_CACHE', true); // Added by W3 Total Cache`.
- [ ] Z archivu Duplicatoru (krok 1) nahrát zpět `wp-admin`, `wp-includes`, soubory v kořeni (kromě `wp-config.php`), `wp-content/plugins/pneukarnik-booking`, `wp-content/advanced-cache.php` a `.htaccess`. Ostatní staré pluginy a šablona v `www` zůstaly, dokud neproběhl úklid (krok 16), potom nahrát i je.
- [ ] Smazat `.maintenance`. Starý web běží s daty z okamžiku přepnutí. Rezervace vzniklé mezitím na novém webu (Rezervace → seznam od data přepnutí) přepsat do starého ručně.
- Tabulky `tstpk_` nechat, přepnutí jde zopakovat od kroku 3.
- Vyzkoušeno lokálně jen pro databázi (součty `hwjcw_` před a po přepnutí stejné). Starý web s obnovenými soubory se nezkoušel, obnova souborů z Duplicatoru je standardní.

## Výsledek zkoušky přepnutí (2026‑10‑22)

Lokálně na MySQL 5.7: tabulky `hwjcw_` ze zálohy živého webu z 2026‑10‑06 a `tstpk_` z kopie s doplněným obsahem. Na staré tabulce nasimulovaný provoz po vytvoření kopie: nová Rezervace a zrušení jedné převedené. Pak kroky 3–5, web nad `tstpk_` a převod (krok 9).

- `make ostry`: 6 656 adres kopie přepsaných, z kopie pryč 1 032 Rezervací (z toho 8 zkušebních), 94 souhlasů, WordPress 7.1.2.
- Krok 5 bez `sql_mode` spadl na datu `0000-00-00` a nechal prázdnou tabulku, proto `SET SESSION sql_mode = ''`.
- Převod: Služby 17 přeskočené, Rezervace 930 (nová převedená, zrušená ne), chybné 2 (`0000-00-00`), souhlasy 95. E‑maily se ve zkoušce neodeslaly (WP‑CLI bez pošty), odesílání pokrývají testy pluginu.
- Úvod, Služby, Rezervace, Průvodci, stránky a sitemapa 200, staré adresy 301, `debug.log` bez chyb.
- Součty (`CHECKSUM TABLE`) všech 58 tabulek `hwjcw_` před a po přepnutí stejné.

## Výsledek zkoušky převodu (záloha z 2026‑07‑20, převod 2026‑10‑03 a 04)

- Převod 3,7–4,4 s. Služby 17 (16 převzatých ze starého pokusu, 1 nový koncept), Rezervace 838–840 (podle data převodu), přeskočeno 3 097, chybná 1 (datum `0000-00-00`), souhlasy 93. Opakovaný převod nic nepřidá.
- Překryvy existují jen v minulosti (chyba starého webu: tříhodinová Geometrie a v ní další Rezervace), report je proto nehlásí.
- Převedené Rezervace zabírají dílnu s Délkou ze starého webu. Služby ze starého pokusu mají ale jiné Délky, nové Rezervace se budou řídit jimi. **Provozovatel je musí zkontrolovat:**

  | Služba | Délka v novém webu | Na starém webu |
  |---|---|---|
  | Geometrie | 60 min | 180 min |
  | Sezónní uskladnění pneu | 20 min | 60 min |
  | Oprava čelních skel | 30 min | 60 min |
  | Výměna oleje | 30 min | 60 min |
  | Výměna tlumičů | 90 min | 60 min |
  | Výměna žárovek | 20 min | 60 min |
  | Autodiagnostika | 30 min | 60 min |
  | Nástřik podvozku / antikorozní ochrana | 120 min | 60 min |
  | Duše a pláště na elektrokoloběžky | 20 min | 60 min |

- 16 Služeb ze starého pokusu je **zveřejněných bez perexu** (a bez dalších částí detailu). Doplnit před přepnutím, jinak je web ukáže neúplné. „Servisní prohlídka před dovolenou“ je nový koncept.
- Stránky Úvod, Kategorie, detaily Služeb, Rezervace, O nás, Kontakt, Ochrana osobních údajů a sitemapa vrací 200, administrace (kalendář, seznam, Nastavení, Výjimky, převod) i PDF přehled fungují, `debug.log` je prázdný.
- Anonymizace je naplánovaná a převedené Rezervace nemaže (všechny jsou mladší než rok od Termínu). Připomínka přezutí nemá příjemce (převedené souhlasy jsou jen „informace o slevách“).
