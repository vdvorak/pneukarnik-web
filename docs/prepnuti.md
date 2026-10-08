# Přepnutí na nový web

Checklist pro #23. Nový web nahradí starý **ve stejné instalaci WordPressu a databázi** (prefix `hwjcw_`): odstraní se stará šablona a pluginy, data zůstanou a převod (#21) je načte. Kroky označené „ověřeno“ prošly zkouškou nad zálohou živé databáze (`make zkouska`, viz níže). Ostatní potřebují hosting a čekají na #23.

## Zkouška lokálně

```sh
unzip -p ~/Downloads/…/backups-dup-lite/…_archive.zip 'dup-installer/dup-database__*.sql' > /tmp/zaloha.sql
make zkouska DUMP=/tmp/zaloha.sql   # web http://localhost:8090 (zkouska / zkouska), pošta http://localhost:8035
make zkouska-down                   # po zkoušce smazat, jsou tam osobní údaje zákazníků
```

`make zkouska` založí oddělené prostředí (vývojové na :8080 se nemění), nahraje zálohu a provede kroky přepnutí ze skriptu `docker/cli/prepnuti.sh`. Všechny e‑maily skončí v Mailpitu zkoušky. Staré pluginy (WP Mail SMTP …) se nenačtou, jejich soubory v kontejneru nejsou.

## Rozhodnout před přepnutím

- **Kde připravit obsah.** Co Provozovatel nastaví na testovací kopii (perexy Služeb, Nastavení, Průvodci, stránky), se samo na ostrý web nepřenese. Doporučení: připravit vše na testovací kopii a při přepnutí její databázi nasadit na ostrý web, do ní nahrát čerstvou tabulku `hwjcw_reservations` ze živého webu a převod spustit znovu. Převod je opakovatelný, takže přidá jen Rezervace a souhlasy, které mezitím na starém webu přibyly.
- **E‑mail s novým odkazem na Zrušení** převedeným budoucím Rezervacím: ano/ne (Provozovatel).
- **Zálohy ostrého webu.** Všechny Rezervace a souhlasy jsou jen v databázi. Zjistit u Webglobe, jak často a jak dlouho databázi zálohují a jak se záloha obnoví. Když to nestačí (třeba jen týden zpětně), domluvit vlastní pravidelný export.
- **Cron na hostingu.** Zjistit, jestli Webglobe umí spouštět naplánovaný příkaz (cron). Plánované úlohy pluginu (anonymizace, Připomínky přezutí, recenze) jinak běží přes WP‑Cron, tedy jen když někdo otevře web a web si úspěšně zavolá sám sebe.

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

### 7. Kroky přepnutí na kopii

Stejné jako v sekci Přepnutí níže (bez kroku 1, zálohou kopie je `kopie/`), v tomto pořadí:

- [ ] Pluginy: hromadně **deaktivovat všechny** staré pluginy (krok 4). Kvůli WordPressu 7.1 to musí být před aktualizací.
- [ ] Nástěnka → Aktualizace: WordPress na 7.1, pak „Aktualizovat databázi“, pokud ji WordPress nabídne (krok 3).
- [ ] Nahrát šablonu a plugin z repa (krok 2). Zipy připravíš v kořeni repa:
  ```sh
  (cd wp-content/themes && zip -rq ../../pneukarnik.zip pneukarnik)
  (cd wp-content/plugins && zip -rq ../../pneukarnik-booking.zip pneukarnik-booking)
  ```
  Vzhled → Motivy → Přidat → Nahrát `pneukarnik.zip`. Pluginy → Přidat → Nahrát `pneukarnik-booking.zip`. Plugin ze starého pokusu tam už je, zvolit **„Nahradit stávající“**. Jiná cesta je SFTP: starý adresář `wp-content/plugins/pneukarnik-booking` nejdřív smazat, ať nezůstanou staré soubory. Pro opakované nahrávání jen šablony a pluginu (po dalších commitech) jde použít `make deploy-test` (`docker/deploy/deploy-test.sh`, přístup napevno pro `test.pneukarnik.cz`): přes FTPS zrcadlí oba adresáře i s mazáním starých souborů, heslo zadáš interaktivně. Jiný přístup jde přepsat přes `HOST=… FTP_USER=… REMOTE=… make deploy-test`.
- [ ] Aktivovat šablonu Pneukarník a plugin Pneukarnik Booking (krok 4).
- [ ] Nastavení → Trvalé odkazy → Uložit (krok 5). WordPress doplní svůj blok do `.htaccess`, blok s heslem musí zůstat nahoře.
- [ ] Rezervace → Převod ze starého webu **bez e‑mailu Zákazníkům** (krok 6) a projít report (krok 7).
- [ ] Smazat staré pluginy podle kroku 8. WP Mail SMTP nechat kvůli kroku 8 níže. (`/nova/` v kopii není.)
- [ ] `wp-content/debug.log` je prázdný.

### 8. E‑maily: SMTP, SPF, DKIM

- [ ] Administrace Webglobe: e‑mailová schránka na doméně pro odesílání, např. `rezervace@pneukarnik.cz`. Poznamenat si údaje SMTP (server, port, šifrování).
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

- [ ] Provozovatel dostane vlastní účet (Uživatelé → Přidat, role Administrátor nebo Editor) a heslo k serveru.
- [ ] Provozovatel doplní Služby (viz Výsledek zkoušky), Nastavení (kontakty, Pracovní doba, Sezóny, Lhůta zrušení), stránky O nás, Kontakt, Ochrana osobních údajů (perex do výňatku stránky) a Průvodce.
- [ ] Zkušební rezervace, Zrušení odkazem z e‑mailu, PDF přehled a iCal.
- [ ] Cron je vypnutý. Anonymizaci, Připomínky přezutí a recenze jde spustit ručně, když je potřeba vyzkoušet: plugin WP Crontrol, nebo dočasně `DISABLE_WP_CRON` na `false`.
- [ ] Návrat zpět (nacvičení): znovu nahrát soubory a `kopie.sql` podle kroků 5 a 6 a v phpMyAdminu smazat tabulky `tstpk_`, které přidal nový plugin (`tstpk_pneukarnik_booking_services`, `…_day_exceptions`, `…_subscriptions`). Starý web na kopii musí naběhnout, pak krok 7 zopakovat. Proto `kopie/` lokálně smaž až po této zkoušce.

**Po zkoušce** (nebo až kopie nebude potřeba): smazat adresář subdomény a v phpMyAdminu **jen tabulky `tstpk_`** (filtr tabulek `tstpk_`, Zaškrtnout vše, Odstranit). Lokálně smazat `kopie/`. Všude jsou osobní údaje zákazníků.

## Přepnutí (mimo sezónu, cíl leden–únor 2027)

1. [ ] Záloha celého webu (Duplicator) a databáze.
2. [ ] Nahrát šablonu `pneukarnik` a plugin `pneukarnik-booking`.
3. [ ] `wp core update-db` (nebo Nástěnka → Aktualizovat databázi). Ověřeno: 58975 → 61833.
4. [ ] Vypnout všechny staré pluginy, aktivovat šablonu Pneukarník a plugin Pneukarnik Booking. Ověřeno: databáze pluginu se dorovná z verze 1.2 (starý pokus) na 1.12.
5. [ ] Nastavení → Trvalé odkazy → Uložit (přegeneruje adresy). Struktura `/%year%/%monthnum%/%day%/%postname%/` může zůstat, nové stránky na ní fungují (ověřeno).
6. [ ] Rezervace → Převod ze starého webu (s e‑mailem podle rozhodnutí). Ověřeno, viz Výsledek zkoušky.
7. [ ] Projít report převodu: chybné Rezervace a budoucí překryvy vyřešit se Zákazníky telefonicky.
8. [ ] Smazat `/nova/`, staré pluginy (Pods, Timber, Complianz, W3 Total Cache, Contact Form 7, Email Log, WP Mail SMTP podle nového SMTP, Duplicator až po přepnutí) a starou šablonu `tmechanics-theme`.
9. [ ] Kontrola přesměrování: všechny adresy z `docs/stare-url.md` vrátí 301 na stránku s 200. Ověřeno (45 z 45).
10. [ ] Sitemapa (`/wp-sitemap.xml`) do Search Console. Adresy z přehledu Stránky doplnit do `docs/stare-url.md`.
11. [ ] Archivovat `hwjcw_reservations` (export SQL mimo web) a zapsat datum ručního smazání tabulky za rok (zadání).
12. [ ] Plánované úlohy běží: Nástroje → Stav webu bez chyby „smyčkový požadavek“ (loopback) a v pluginu WP Crontrol žádná úloha `pneukarnik_…` po termínu. Když hosting cron umí: do `wp-config.php` `define( 'DISABLE_WP_CRON', true );` a na hostingu každých 15 minut spouštět `wp-cron.php` (např. `wget -q -O - https://pneukarnik.cz/wp-cron.php?doing_wp_cron`). Pak znovu ověřit ve WP Crontrol.
13. [ ] Zálohy podle rozhodnutí výše (Webglobe, případně vlastní export) běží a jedna obnova je vyzkoušená.

## Návrat zpět

- [ ] Obnovit zálohu z kroku 1 (Duplicator: soubory i databáze). Nový web do starých tabulek nezapisuje (jen čte `hwjcw_reservations` a staré Služby), takže starý web po obnovení šablony a pluginů běží s daty z okamžiku přepnutí. Rezervace vzniklé mezitím na novém webu je třeba přepsat ručně.
- [ ] Vyzkoušet na testovací kopii.

## Výsledek zkoušky (záloha z 2026‑07‑20, převod 2026‑10‑03 a 04)

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
