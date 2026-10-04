# Staré URL a jejich přesměrování

Adresy starého webu pneukarnik.cz a kam vedou na novém (#22). Sestaveno ze zálohy živého webu k 2026‑10‑03 (`docs/puvodni-web.md`):

- publikovaný obsah v `hwjcw_posts` a struktura odkazů `/%year%/%monthnum%/%day%/%postname%/`,
- adresy, které si pamatoval Yoast SEO (`hwjcw_yoast_indexable`, i starší obsah z let 2020–2021),
- položky menu (`_menu_item_url`) a kotvy v šablonách `tmechanics-theme`.

Search Console zatím k dispozici není. Až bude, doplnit sem adresy z přehledu „Stránky“, které tu chybí.

Přesměrování dělá `Pneukarnik_Old_Urls` v pluginu (301, podle cesty, takže funguje se starým obsahem v databázi i bez něj) a kotvy skript `pneukarnik_old_anchors()` na Úvodu. Pravidla:

- příspěvky `/rok/měsíc/den/slug/` a `?p=ID`: o dekarbonizaci na detail Služby Dekarbonizace (slug `dekarbonizace`, bez ní na Autoservis), ostatní na Úvod,
- archivy podle data, rubriky, autora a štítku na Úvod,
- `/service/{slug}/` na zveřejněnou Službu se stejným slugem, jinak na zveřejněnou Službu převedenou z té staré (#21), jinak na Úvod,
- `/galery/…` na galerii stránky O nás, ostatní staré typy obsahu (`/closed/`, `/warning/` …) a `/nova/` na Úvod,
- `/cancel-subscription?email=…` na odhlášení (#19, `Pneukarnik_Booking_Pages`).

Obrázky `/wp-content/uploads/…` zůstávají na místě, přesměrování nepotřebují.

Playwright (`tests/e2e/stare-url.spec.ts`) projde obě tabulky. Cíle Služeb platí pro test, který si zveřejní Služby `prezuti-pneu` (Pneuservis) a `dekarbonizace` (Autoservis). Ostatní staré Služby na novém webu zatím nejsou, proto vedou na Úvod.

## Adresy (301)

| Stará adresa | Cíl | Zdroj |
|---|---|---|
| `/2021/01/08/black-friday/` | `/` | příspěvek „Elektrokoloběžky“ |
| `/2023/12/13/nove-dekarbonizace-motoru-je-mozna-i-u-nas-zavadejici-ceny/` | `/autoservis/dekarbonizace/` | příspěvek |
| `/2023/12/14/dekarbonizace-motoru/` | `/autoservis/dekarbonizace/` | příspěvek |
| `/?p=290` | `/` | příspěvek „Elektrokoloběžky“ |
| `/?p=957` | `/autoservis/dekarbonizace/` | příspěvek |
| `/?p=976` | `/autoservis/dekarbonizace/` | příspěvek |
| `/2020/10/01/bestdrive/` | `/` | Yoast |
| `/2020/10/01/o-nas/` | `/o-nas/` | Yoast |
| `/2020/10/02/img/` | `/` | Yoast |
| `/2023/12/` | `/` | archiv podle data |
| `/2023/` | `/` | archiv podle data |
| `/category/nezarazene/` | `/` | Yoast |
| `/author/admin/` | `/` | Yoast |
| `/author/pneuservis/` | `/` | Yoast |
| `/pneuservis-autoservis/` | `/` | stránka „Home“ |
| `/pneuservis-jan-karnik/` | `/` | stránka „Pneuservis Jan Kárník“ |
| `/zasady-cookies-eu/` | `/ochrana-osobnich-udaju/` | stránka Complianz |
| `/dategenerator/` | `/` | Yoast |
| `/cancel-subscription?email=e2e-stare-url%40example.test` | `/odhlaseni/?email=e2e-stare-url%40example.test` | stránka, odkaz z e‑mailů |
| `/service/` | `/` | archiv Služeb |
| `/service/prezuti-pneu/` | `/pneuservis/prezuti-pneu/` | Služba |
| `/service/dekarbonizace/` | `/autoservis/dekarbonizace/` | Služba |
| `/service/geometrie/` | `/` | Služba |
| `/service/priprava-na-stk/` | `/` | Služba |
| `/service/sezoni-uskladneni/` | `/` | Služba |
| `/service/oprava-celnich-skel/` | `/` | Služba |
| `/service/doplneni-klimatizace/` | `/` | Služba |
| `/service/vymena-brzdovych-desticek/` | `/` | Služba |
| `/service/vymena-vyfuku/` | `/` | Služba |
| `/service/vymena-oleje/` | `/` | Služba |
| `/service/vymena-tlumicu/` | `/` | Služba |
| `/service/vymena-zarovek/` | `/` | Služba |
| `/service/autodiagnostika/` | `/` | Služba |
| `/service/nastrik-podvozku-proti-korozi/` | `/` | Služba |
| `/service/oprava-defektukol-na-elektrokolobezkach/` | `/` | Služba |
| `/service/servisni-prohlidka-pred-dovolenouuterka-zdarma/` | `/` | Služba |
| `/service/780/` | `/` | Služba „Dezinfekce vozidla ozonem“ |
| `/service/prodej-pneu/` | `/` | Yoast |
| `/service/vymena-autobaterii/` | `/` | Yoast |
| `/galery/interier/` | `/o-nas/#galerie` | Yoast |
| `/galery/interier-2/` | `/o-nas/#galerie` | Yoast |
| `/closed/24-12-stedry-den/` | `/` | Yoast (zavřené dny, stejně i ostatní `/closed/…`) |
| `/closed/1-5-svatek-prace/` | `/` | Yoast |
| `/warning/vzdy-ve-vozidle/` | `/` | Yoast |
| `/nova/` | `/` | starý pokus o nový web |

## Kotvy staré jednostránky

| Stará adresa | Cíl | Zdroj |
|---|---|---|
| `/#services` | `/#sluzby` | menu „Služby“ |
| `/#reservations` | `/rezervace/` | menu „Rezervace“, tlačítka |
| `/#contacts` | `/kontakt/` | menu „Kontakty“ |
| `/#about` | `/o-nas/` | menu „O nás“ |
| `/#galery` | `/o-nas/#galerie` | menu „Galerie“ |
| `/#map` | `/kontakt/` | odkaz na mapu |
| `/#location` | `/kontakt/` | šablona |
| `/#rezervace` | `/rezervace/` | zadání #22 |
| `/#kontakty` | `/kontakt/` | zadání #22 |
| `/#o-nas` | `/o-nas/` | zadání #22 |
| `/#galerie` | `/o-nas/#galerie` | zadání #22 |
| `/#sluzby` | `/#sluzby` | zadání #22, sekce Kategorií na Úvodu |
