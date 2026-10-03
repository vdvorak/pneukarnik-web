# Revize převzatého pluginu `pneukarnik-booking`

Revize pluginu ze starého pokusu (`~/dev/AI/pneukarnik/wordpress/plugins/pneukarnik-booking`, stav z 2026-05-30) před jeho převzetím jako základu rezervací (ticket #2, zadání #1).
Pojmy viz `CONTEXT.md`.

## Co se převzalo

| Část | Stav po převzetí | Kde se přepracuje |
|---|---|---|
| Tabulky Rezervací a uzavřených dnů, migrace verzí (`Pneukarnik_DB`) | beze změny schématu | #4, #5 |
| Vytvoření Rezervace v transakci se zámkem (`Pneukarnik_Booking`) | beze změny logiky | #4 |
| Výpočet Termínů (`Pneukarnik_Slot_Engine`), Pracovní doba, Sezóna, uzavřené dny | beze změny logiky | #4, #6, #7 |
| Zrušení tokenem (v DB jen SHA‑256 hash) a Zrušení Provozovatelem | beze změny logiky | #8, #11 |
| E‑maily (potvrzení, Zrušení, upozornění Provozovateli) a jejich šablony | beze změny | #8 |
| GDPR: export a výmaz osobních údajů pro nástroje WordPressu, denní anonymizace | beze změny logiky | #20 |
| REST: `/services`, `/slots`, `/bookings`, `/cancel`, `/bookings/{id}/cancel`, `/calendar` (iCal) | beze změny chování | #3, #4, #8, #12 |
| Administrace: seznam Rezervací s filtry, zadání Rezervace, Nastavení, uzavřené dny, pole Služby, PDF (FPDF) | beze změny chování | #3, #6, #11, #12 |

## Co se odstranilo

| Část | Proč |
|---|---|
| CPT `pneukarnik_gallery`, `/gallery`, `/gallery-import` | Galerie patří šabloně (stránka O nás), ne rezervacím. |
| `/setup` | Inicializační endpoint deploy skriptu headless pokusu. |
| `/sitemap-slugs` | Pozůstatek headless webu. Sitemapu řeší WordPress a ticket #18. |
| `/settings` + sekce „Obsah webu“ (hero, O nás) v Nastavení | Pozůstatek headless webu. Šablona vykresluje obsah serverově (ADR 0001). |
| `/contact` + šablony e‑mailů kontaktního formuláře | Kontaktní formulář není v zadání. |
| Připomínka den před Termínem (cron + šablona) | Není v zadání. Zadání má jen Připomínku přezutí (#19). Pokud ji Provozovatel bude chtít, vrátí se ze starého repa. |
| Staré testy | Volají interní třídy. Nové testy jdou přes REST (spec #1, Testing Decisions). Ve starém repu zůstávají jako reference: souběh, cancel token, zachytávání e‑mailů přes `pre_wp_mail`. |

## Co se změnilo při převzetí

- **Jediný zdroj času `Pneukarnik_Clock`**, vždy v Europe/Prague nezávisle na nastavení WordPressu.
  - Nahradil `wp_timezone()`, `current_time()`, `date()`, `strtotime()`/`time()` při plánování cronu a všechna `new DateTimeImmutable('now'|'today')`.
  - `created_at` se nově nastavuje z něj. Dřív ho dávala DB (`CURRENT_TIMESTAMP`, tedy čas DB serveru, v Dockeru UTC).
  - Testy nastavují „teď“ přes `Pneukarnik_Clock::freeze()`.
- **FPDF** se přesunulo z `vendor/fpdf` do `lib/fpdf`. Starý repozitář měl `vendor/` v `.gitignore`, takže knihovna v gitu chyběla.
- **Úpravy kvůli lintu** (WordPress Coding Standards + PHPStan level 5):
  - escapování výstupů v administraci (`_e` → `esc_html_e`, `wp_die`, `printf`, `paginate_links`),
  - názvy tabulek přes placeholder `%i`, LIKE se zástupnými znaky přes `prepare()`,
  - `addslashes` v inline JS nahrazeno `esc_js`.
- **Opravené chyby, které našel lint:**
  - Skládání řádků iCal (RFC 5545) rozdělovalo vícebajtové znaky UTF‑8 (čeština) mezi dva řádky.
  - Zrušení vrací i `WP_Error`, ale typy to neuváděly. Volající kód s tím přitom počítal.
  - iCal: zbytečné `createFromFormat(...)?->` nahrazeno `Pneukarnik_Clock::at()`. Datum a čas jdou ze sloupců DATE/TIME, takže jsou vždy platné.
- **Migrace 1.2 → 1.3** se na čerstvé instalaci nespouští. Dřív sahala na tabulku, která ještě nebyla (mu-plugin v testech).
- **Vytvoření Rezervace** s tělem, které není JSON objekt (řetězec, číslo), vrací 422 místo pádu serveru. Hlídá to test.

## Nalezené problémy

Seřazené podle ticketu, který je vyřeší. Nic z toho dnes neběží v provozu, plugin běží jen na `/nova/`.

### Rezervační model (#4, #5)

1. **Kapacita není jedna dílna.** Výpočet Termínů, zámek i `UNIQUE KEY uq_slot (service_id, booking_date, time_start)` pracují pro každou Službu zvlášť. Dvě různé Služby jde zarezervovat na stejný čas. **Vyřešeno v #4:** obsazenost se počítá napříč Službami, `uq_slot` odstraněn (DB 1.4).
2. **Překryv se nehlídá.** Kontroluje se jen shodný začátek. Delší Služba začínající dřív se s jinou Rezervací překryje, stejně jako na starém webu. **Vyřešeno v #4:** Termín se nabízí i ukládá, jen když celý úsek nic nepřekrývá.
3. **Zámek proti souběhu nestačí.** `SELECT … FOR UPDATE` na neexistujícím řádku nezamkne překrývající se úseky, jen shodný začátek (a to díky UNIQUE). Zamykat se musí celý den dílny. **Vyřešeno v #4:** zámek dne přes `GET_LOCK` a uvnitř transakce kontrola překryvu. Hlídá to test souběhu v Playwrightu.
4. **Transakce uvnitř `Pneukarnik_Booking::create()`.** Volá `START TRANSACTION`/`COMMIT`, a tím v testech (`WP_UnitTestCase` běží v transakci) commitne testovací data. Testy vytváření Rezervací s tím musí počítat. **Vyřešeno v #4:** v testech plugin místo vlastní transakce použije savepoint (`Pneukarnik_DB::use_savepoints`).
5. **Mřížka Termínů.** Krok je `Délka + time_gap`, ne nastavitelná mřížka od začátku bloku Pracovní doby. **Vyřešeno v #4:** nastavitelný krok mřížky od začátku bloku, `time_gap` zrušen.
6. **Rezervace má jen jednu Službu** (`service_id`). **Vyřešeno v #5:** Služby Rezervace jsou v tabulce `pneukarnik_booking_services` s názvem, Délkou a cenou z okamžiku vytvoření, sloupec `service_id` odstraněn (DB 1.5). REST `/slots` i `/bookings` berou `service_ids`.
7. **Cache Termínů.** Transient na 1 min (klíč Služba + den) se po nové Rezervaci smaže jen pro tutéž Službu. U jedné dílny ovlivní Rezervace všechny Služby daného dne. **Vyřešeno v #4:** cache Termínů zrušena, výpočet je levný.
8. **Minulé dny.** Výpočet Termínů je nabízí (filtruje jen dnešek), vytvoření je pak odmítne. Chybí nastavitelný předstih a horizont. **Vyřešeno v #4:** předstih i horizont jsou nastavitelné, minulé dny Termíny nemají.
9. **Telefonickou Rezervaci nejde zadat mimo Pracovní dobu.** Zadání Provozovatelem jde přes stejnou validaci jako web.
10. **Zrušený Termín už nejde znovu zarezervovat.** Zámek i `UNIQUE KEY uq_slot` ignorují stav, zatímco výpočet Termínů bere jako obsazené jen potvrzené Rezervace. Web Termín nabídne, vytvoření pak vrátí 409. **Vyřešeno v #4.**
11. **Nekonečná smyčka ve výpočtu Termínů.** Při `Délka + time_gap ≤ 0` se smyčka nikdy nepohne. Délka 0 i záporný `time_gap` jde uložit, server je nekontroluje, a každý `/slots` pak skončí vyčerpáním paměti. **Částečně v #4:** `time_gap` zrušen, krok mřížky je min. 5 min a Služba s Délkou 0 nemá Termíny.
12. **Pád na nečekaných typech a datech.** Validace volá `trim`/`preg_match` na hodnoty z JSON bez přetypování (`strict_types`), takže telefon poslaný jako číslo shodí server. Datum se kontroluje jen formátem: `2027-13-01` projde a `DateTimeImmutable` pak vyhodí výjimku (500), v `/slots` i ve vytvoření. **Vyřešeno v #4:** pole se ověřují bez pádů a datum se kontroluje přes `checkdate`.

### Výjimky a Sezóny (#6, #7)

13. **Výjimky jsou jen jednorázová data.** Chybí „opakovat každý rok“ a státní svátky ČR (včetně Velikonoc). **Vyřešeno v #6:** Výjimka má rozsah od–do a volbu „opakovat každý rok“ (tabulka `pneukarnik_day_exceptions`, DB 1.6, `pneukarnik_closed_dates` převedena a odstraněna). Svátky ČR se počítají v kódu a jde je jednotlivě vypnout.
14. **Sezóna se počítá podle dnešního data, ne podle data Termínu.** Je jen jedna (rozsah MM‑DD + „vynutit“). Chybí jarní a podzimní a leasingové datum. **Vyřešeno v #7:** jarní a podzimní Sezóna (od–do a leasingové datum, opakují se každý rok) se určuje podle data Termínu. Pravidla dne (jen sezónní Služby, leasingové datum) platí pro `/slots`, `/available-days` i vytvoření online Rezervace. Stará nastavení Sezóny se při DB 1.7 smažou.

### Zrušení a e‑maily (#8, #10)

15. **Lhůta zrušení ve dnech.** `cancellation_days` porovnává kalendářní dny, zadání chce hodiny před Termínem (default 24 h). **Vyřešeno v #8:** Lhůta zrušení v hodinách (`pneukarnik_cancellation_hours`, default 24), počítá se od začátku Termínu. Testy přes přelom měsíce i roku.
16. **Platnost tokenu je max. 30 dní od vytvoření.** Rezervaci vzdálenější než 30 dní proto nejde zrušit odkazem. **Vyřešeno v #8:** odkaz platí do začátku Termínu, sloupec `cancel_token_expires_at` odstraněn (DB 1.8).
17. **Odkaz v e‑mailu nedává smysl s novými URL.** Vede na `/zrusit-rezervaci?id&token`, který v nových URL neexistuje. Navíc endpoint chce i e‑mail, takže se ho stránka musí doptat. **Vyřešeno v #8:** odkaz `/rezervace/zruseni/?r={token}`, REST `GET`/`POST /cancellation` jen s tokenem. Starý `/cancel` odstraněn, `/bookings/{id}/cancel` jen pro Provozovatele.
18. **Zrušení Provozovatelem přes REST** pouští jen `manage_options`, ne capability „spravovat rezervace“.
19. **E‑mail Provozovateli chodí vždy**, zadání ho chce zapínatelný. Odesílatel „Jan Kárník Autoservis“ je v kódu natvrdo. **Vyřešeno v #8:** upozornění na novou a na zrušenou online Rezervaci jde zvlášť vypnout, odesílatel je název webu, odpověď jde Provozovateli (Reply-To). E‑maily jsou HTML s textovou alternativou, úvod, podpis a „co si vzít s sebou“ se upravují v Nastavení.
20. **Rate limiting** počítá jen úspěšné Rezervace (zkoušení neomezí). Bere `REMOTE_ADDR`, takže za proxy sdílí limit všichni. Zrušení limit nemá. **Vyřešeno v #10:** limity v `Pneukarnik_Rate_Limit`, zvlášť pro vytvoření (počítá vytvořené Rezervace, obsazenost prozradí i `/slots`) a pro Zrušení odkazem (počítá každý pokus, REST i formulář stránky), oba nastavitelné, pevné hodinové okno, IPv6 po /64. Za proxy vrátí skutečnou IP filtr `pneukarnik_client_ip`.
21. **Poznámka zákazníka** jde přes `sanitize_text_field`, které smaže konce řádků. **Vyřešeno v #4:** `sanitize_textarea_field`.

### Administrace, PDF, iCal (#11, #12)

22. **Oprávnění jsou nekonzistentní.** Nastavení a uzavřené dny chtějí `manage_options`, seznam vlastní capabilities. Zadání: capabilities „spravovat“ a „prohlížet rezervace“.
23. **Mazání uzavřeného dne jde přes GET.** Nonce sice má, ale data mění GET požadavek. **Vyřešeno v #6:** Výjimka se maže formulářem POST s nonce.
24. **Formuláře administrace nepřesměrují.** Zpracování POST (Zrušení, nová Rezervace, Nastavení, uzavřené dny) běží až uvnitř stránky, po odeslání hlavičky administrace. `wp_safe_redirect` pak selže na „headers already sent“ a uživatel místo hlášky uvidí useknutou stránku. Zpracování patří do `load-{$hook}` nebo `admin_post_*`. **Částečně v #4 a #6:** Nastavení a Výjimky se zpracují v `load-{stránka}`, ostatní formuláře řeší #11.
25. **PDF neumí UTF‑8.** FPDF čeština se transliteruje (`iconv //TRANSLIT`) a hlavičky jsou bez diakritiky. Zvážit tFPDF s TTF fontem.
26. **iCal feed obsahuje osobní údaje** (jméno, telefon, poznámka). Chrání ho jen tajný token v URL. To je v pořádku, token jde přegenerovat a porovnává se přes `hash_equals`.
27. **iCal neescapuje text.** Jméno, telefon a firma jdou do SUMMARY/DESCRIPTION bez escapování podle RFC 5545 (`\n`, `,`, `;`, `\`) a ze vstupu se z nich neodstraňují konce řádků. Veřejně vytvořená Rezervace tak může do kalendáře Provozovatele vložit vlastní řádky i celé události. **Vyřešeno v #4:** escapování TEXT podle RFC 5545, hlídá to test.

### Služby, nastavení, GDPR (#3, #15, #20)

28. **Služba je neveřejný CPT.** Má ikonu jako třídu Font Awesome a slevu přímo ve Službě. Model se mění: Kategorie, detail Služby, Akce jako samostatný typ. **Vyřešeno v #3:** Služba má Kategorii, strukturovaná pole detailu a veřejné adresy. Pole starého pluginu (`_service_icon`, `_service_index`, `_service_is_autoservice`, sleva) se už nečtou, jejich převod řeší #21.
29. **Kontakty a sociální sítě v Nastavení.** Sociální sítě se zadávají jako ruční JSON a je tam embed URL mapy. Patří do jednoho místa kontaktů a mapa bude načítaná až po kliknutí.
30. **Anonymizace po 2 letech**, zadání chce 1 rok. Zpracuje max. 200 záznamů za běh a nic dalšího v tom běhu neopakuje.
31. **Výmaz osobních údajů (GDPR eraser) vynechá záznamy.** Stránkuje přes `OFFSET` nad `customer_email = %s`, ale každá stránka e‑mail přepíše. Další stránka pak přeskočí dosud neanonymizované řádky a výmaz se přesto ohlásí jako hotový (u zákazníka s víc než 25 Rezervacemi).

### Bezpečnost: v pořádku

- Veřejné odpovědi REST neobsahují osobní údaje.
- SQL jde všude přes `prepare()`.
- Token Zrušení je v DB jen jako hash a porovnává se v konstantním čase.
- Zápisy v administraci mají nonce a kontrolu oprávnění.
- REST zápisy přihlášených chrání nonce z cookie autentizace WordPressu.

## Co testy zatím nepokrývají

Administrace, PDF, iCal, GDPR a e‑maily jsem v tomto ticketu ověřil jen ručně na lokálním webu:

- stránky administrace se vykreslí bez chyb,
- PDF se stáhne,
- Rezervace jde vytvořit i zrušit a duplicita vrátí 409.

Testy k nim přidají tickety, které je přepracují.
