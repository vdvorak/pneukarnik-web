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

## Testovací kopie (hosting)

- [ ] Kopie živého webu na subdoméně za heslem, `noindex` (Nastavení → Čtení) a heslo na úrovni serveru.
- [ ] Na kopii stejné kroky jako při přepnutí níže.
- [ ] SMTP z adresy na doméně, SPF a DKIM. Testovací e‑mail z rezervace do Gmailu a Seznamu nespadne do spamu.
- [ ] Provozovatel doplní Služby (viz Výsledek zkoušky), Nastavení (kontakty, Pracovní doba, Sezóny, Lhůta zrušení), stránky O nás, Kontakt, Ochrana osobních údajů a Průvodce.
- [ ] Zkušební rezervace, Zrušení odkazem, PDF přehled a iCal.

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
