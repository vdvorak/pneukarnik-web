# Původní web: inventura funkčnosti

Stav živého webu `pneukarnik.cz` k 2026-10-03. Zdroj: šablona `tmechanics-theme` (Timber/Twig, WP 7.0.6), záloha v `~/Downloads/pneukarnik.cz-2026-10-03-1ecd1414f9/`.
Slouží jako checklist, aby nový web o nic, co se dnes používá, nepřišel. Neslouží jako vzor implementace.

## Obsah (CPT přes plugin Pods)

| Post type | Co drží |
|---|---|
| `service` | Služba: název, ikona (obrázek), `duration` (min), cena „od“, `seasonal`, `index` (pořadí), autoservis/pneuservis |
| `global` | Jediný záznam s konfigurací: sezóna od–do, příznak `pneu_season`, pracovní doba 1 a 2 (od–do), krok rezervací v minutách |
| `closed` | Zavřené dny: `from`, volitelně `to`, `always` = opakuje se každý rok (svátky) |
| `social` | Odkazy na sociální sítě |
| `galery` | Fotky galerie |
| `email_tmp` | Šablony hromadných e‑mailů |
| `post` | „Novinky“ |

Další plugins: Complianz (cookies), Contact Form 7, WP Mail SMTP, Email Log, W3 Total Cache, Duplicator. Je tam i `pneukarnik-booking` ze starého pokusu (`/nova/`).

## Rezervace (`api.php` + `assets/js/reservation.js`)

- **Kapacita:** jedno vozidlo najednou (jedna dílna).
- **Sloty:** začátky na pevné mřížce (krok z `global`, default 60 min) v rámci pracovní doby 1 a 2. Služba zabírá `[začátek, začátek + duration)`. Slot je nabízen, když:
  - nezačíná uvnitř jiné rezervace,
  - konec služby nepřesáhne konec pracovní doby,
  - dnes je aspoň 1 h dopředu.
- **Chyba:** kontroluje se jen to, jestli *začátek* nové rezervace padne do existující. Delší služba začínající před existující rezervací se s ní může překrýt.
- **Sezóna:** pokud datum termínu leží v `season_start`–`season_end` (default 1. 3.–30. 11.), lze rezervovat **jen sezónní služby**. Příznak `pneu_season` vynucuje sezónu jen v seznamu služeb, ne při výpočtu slotů (nekonzistentní).
- **Víkendy** jsou natvrdo zavřené. Zavřené dny z `closed` se zobrazují v otevírací době (widget 7 dní dopředu).
- **Formulář:** služba, datum, čas, jméno/společnost, SPZ, e‑mail, telefon, poznámka, souhlas GDPR, „chci dostávat informace o slevách“.
- **Potvrzení:** e‑mail zákazníkovi s **klíčem pro zrušení** (20 znaků). Provozovatel o nové rezervaci e‑mail **nedostává**.
- **Zrušení zákazníkem:** zadáním klíče na webu, nejpozději den před termínem (porovnává se jen den v měsíci, takže přes přelom měsíce to nefunguje). Při zrušení přijde e‑mail zákazníkovi i Provozovateli.

## Administrace

- **Dashboard widget „Rezervace“:** nadcházející rezervace po dnech, úprava údajů, zrušení s e‑mailem zákazníkovi.
- **Dashboard widget „Nástroje“:** hledání v rezervacích, ruční přidání e‑mailu do seznamu, hromadný e‑mail ze šablony (odesílání je v kódu zakomentované).
- **PDF denní přehled** (FPDF): čas, služba, jméno/společnost, poznámka.
- **Pneukarnik → Konfigurace:** sezóna, pracovní doby, krok.
- **Info Banner:** jedno oznámení (nadpis, text, typ, barva, lze zavřít).
- **Newsletter:** `allow_newsletters` u rezervace, odhlášení odkazem s e‑mailem v URL (bez tokenu).

## Nedotažené pokusy v kódu

- `api_new.php`: REST API `pneukarnik/v1` (rezervace, zrušení, tisk, newsletter, **vozidla**) a v `functions.php` **registrace a přihlášení** zákazníků s CORS pro `localhost`. Pozůstatek pokusu o zákaznické účty, viz ADR 0002.

## Bezpečnostní problémy (živý web)

Podle kódu `api.php`, který načítá WP a nemá kontrolu oprávnění:

- PDF denního přehledu (jména, poznámky zákazníků) je dostupné **bez přihlášení**.
- Úprava údajů rezervace podle ID je dostupná **bez přihlášení**.
- Odhlášení z newsletteru jde provést pro libovolný e‑mail.
- SQL dotazy se skládají z neescapovaných vstupů (hledání v adminu, ověření klíče, kontrola obsazenosti).
- Veřejné REST endpointy pro registraci uživatelů a vozidla (`api_new.php`, `functions.php`).
