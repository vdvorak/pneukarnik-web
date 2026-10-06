# Zadání nového webu pneukarnik.cz

Rozhodnutí z úvodního rozboru obsahu a funkcí (2026-10-03). Pojmy viz `CONTEXT.md`, zdůvodnění zásadních voleb viz `docs/adr/`, dnešní stav viz `docs/puvodni-web.md`, otevřené otázky na Provozovatele viz `docs/otazky-pro-klienta.md`.
Design se řeší samostatně.

## Rámec

- Nový web pro **Pneuservis a autoservis Jan Kárník** (krátce „Pneuservis Kárník“, logo JVK).
- **Vlastní WordPress šablona** (ADR 0001). Starý pokus `~/dev/AI/pneukarnik` slouží jen jako inspirace.
- Rezervační backend: plugin `pneukarnik-booking` ze starého pokusu se **po revizi použije jako základ** a upraví podle tohoto zadání.
- Jazyk: čeština. **Německá verze se zatím neřeší.**
- Vše níže patří do **první verze**.

## Stránky

```
/                         Úvod
/pneuservis               rozcestník Kategorie Pneuservis
/pneuservis/{sluzba}      detail Služby
/autoservis               rozcestník Kategorie Autoservis
/autoservis/{sluzba}      detail Služby
/rezervace                rezervace (+ potvrzení, zrušení)
/o-nas                    historie, tým, BestDrive/Barum, galerie
/kontakt                  adresa, mapa, příjezd, IČ/DIČ, Pracovní doba
/pruvodce/...             Průvodci
/ochrana-osobnich-udaju
```

**Úvod** (v tomto pořadí): aktivní Oznámení → hero (CTA Rezervovat + telefon, obojí viditelné bez scrollování) → dlaždice Pneuservis / Autoservis → nejžádanější Služby → Aktuální akce (jen pokud nějaká platí) → proč my (rok založení, BestDrive, vybavení) → Google recenze → Pracovní doba a mapa.

**Detail Služby** (povinné jsou jen 1, 4 a 8):
1. název + perex,
2. co zahrnuje,
3. jak to probíhá a jak dlouho to trvá,
4. cena + co zahrnuje (nebo „cena dle vozu“),
5. co si vzít s sebou,
6. časté dotazy,
7. aktivní Akce,
8. CTA Rezervovat / Zavolat + související Služby.

První verzi textů napíše vývojář, Provozovatel je zkontroluje.

**Průvodci:** Kdy přezout na zimní a letní pneu · Uskladnění pneumatik u nás · Kdy pneumatiky vyměnit. Každý končí odkazem na rezervaci.

## Obsah spravovaný v administraci

- **Služby** (strukturovaná pole, ne volný editor): Kategorie, Délka, cena, sezónní ano/ne, pořadí a pole detailu.
- **Akce**: Služba, cena, platnost od–do. Zobrazení: štítek na kartě, blok v detailu, sekce na Úvodu.
- **Oznámení**: text, platnost od–do, „zobrazit i u rezervace“. Může jich být víc, nahoře se ukazuje nejnovější.
- **Pracovní doba** pro každý den v týdnu (0–2 bloky), **Výjimky** (zavřeno / jiná doba, opakovat každý rok), státní svátky ČR předvyplněné.
- **Sezóny** jarní a podzimní (od–do, datum pro leasing).
- **Google recenze**: zapnout/vypnout.
- **Pohotovost**: pokud platí, telefon výrazně v hlavičce.
- Klíčové texty e‑mailů (úvod, podpis, co si vzít s sebou).

## Rezervace

- Jedna dílna, **jedno vozidlo najednou**. Rezervace se nepřekrývají.
- Rezervace má **jednu nebo více Služeb**. Zabírá dílnu po dobu součtu jejich Délek.
- Termíny začínají na **mřížce** s krokem nastavitelným v adminu (např. 30 min). Termín je volný, jen když je volný celý úsek a vejde se do jednoho bloku Pracovní doby. Dnes jen s nastavitelným předstihem.
- V **Sezóně** lze online rezervovat jen sezónní Služby.
- **Leasingový zákazník**: zaškrtne „vozidlo je na leasing“ + uvede společnost. Systém vynutí leasingové datum Sezóny (k čemu se datum vztahuje, upřesní Provozovatel).
- **Formulář:** jméno/firma, telefon, e‑mail, SPZ, značka a model (volitelné), poznámka, leasing + společnost, „kola mám uskladněná u vás“ (u pneuslužeb, pokud to Provozovatel chce), souhlas GDPR, souhlas s Připomínkou přezutí (nepovinný).
- Bez registrace a účtů (ADR 0002). Místo toho:
  - předvyplnění údajů v prohlížeči,
  - odkaz „Objednat znovu“ v e‑mailu (předvyplní údaje, Služby a uskladněná kola, Termín vybírá Zákazník znovu),
  - **Připomínka přezutí** před každou Sezónou s odkazem, který předvyplní údaje a sezónní Služby a uskladněná kola z minulé sezónní Rezervace (Termín vybírá Zákazník sám).
- **Zrušení** odkazem z e‑mailu do Lhůty zrušení (nastavitelná, default 24 h). Po lhůtě se zobrazí telefon. Změna Termínu = Zrušení + nová Rezervace s předvyplněnými údaji.
- Potvrzení e‑mailem zákazníkovi. Upozornění Provozovateli podle jeho odpovědi.
- **Rezervace do kalendáře** Zákazníka: soubor `.ics` s jednou událostí (tlačítko „Přidat do kalendáře“ na stránce Potvrzení a příloha potvrzovacího e‑mailu). Bez jména, kontaktu a poznámky Zákazníka.

## Administrace rezervací

- **Kalendářní pohled** (den/týden) s rychlým zadáním Rezervace (telefonické objednávky, blokují čas) + seznam s filtry.
- Úprava a zrušení Rezervace (e‑mail zákazníkovi).
- **PDF denní přehled**.
- **iCal** odběr rezervací do kalendáře v telefonu (tajný odkaz jen pro Provozovatele).

## Provoz, právo, převod

- **GDPR:** Rezervace starší než 1 rok se automaticky anonymizují. Souhlas s Připomínkou přezutí je samostatný. Text zásad napíše vývojář, Provozovatel schválí.
- **Bez cookies vyžadujících souhlas:**
  - analytika Matomo v cookieless režimu + Google Search Console,
  - mapa jako obrázek nebo načtení až po kliknutí,
  - recenze stahované na serveru.
  - (Změní se, pokud Provozovatel bude platit reklamu.)
- **E‑maily** z adresy na doméně, SMTP + SPF/DKIM.
- **Převod dat:** všechny budoucí Rezervace + minulé do 1 roku. Souhlasy „informace o slevách“ se převedou jen s původním účelem, pro Připomínku přezutí se sbírá nový souhlas. Stará tabulka se archivuje a po roce smaže.
- **Přesměrování 301:**
  - příspěvky o dekarbonizaci → detail Služby,
  - ostatní příspěvky → Úvod,
  - `/cancel-subscription` → nové odhlášení,
  - kotvy `#sluzby` atd. → nové stránky.
- **Postup:** vývoj lokálně (Docker) + testovací kopie na subdoméně za heslem. **Přepnutí v lednu nebo únoru 2027**, mimo sezónu. `/nova/` a starý pokus se při přepnutí odstraní.

## Mimo rozsah

- Německá verze (odloženo).
- Zákaznické účty a uložená vozidla (ADR 0002).
- Samostatná stránka pro firmy a leasing.
- Poptávkový formulář na pneumatiky.
- Blog / Novinky.
- Platby online.
