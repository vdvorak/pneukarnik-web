# Pneuservis a autoservis Jan Kárník

Web lokálního pneuservisu a autoservisu ve Znojmě: prezentace služeb a online rezervace termínů.

## Language

### Aktéři

**Provozovatel**:
Pneuservis a autoservis Jan Kárník, tedy firma a lidé, kteří ji provozují a web spravují přes administraci.
_Avoid_: admin, majitel, servis (jako osoba)

**Zákazník**:
Kdokoli, kdo si u Provozovatele objednává práci na vozidle: fyzická osoba, firma i leasingová společnost.
_Avoid_: klient, uživatel

**Leasingový zákazník**:
Zákazník, který objednává vozidlo leasingové společnosti a řídí se jejími dohodnutými podmínkami. Objednává se stejným způsobem jako ostatní Zákazníci.
_Avoid_: leasingovka, firemní klient

### Nabídka

**Služba**:
Trvalá položka nabídky Provozovatele (např. Přezutí pneu, Geometrie), která má vlastní stránku s detailem. Provozovatel ji přidává a upravuje v administraci.
_Avoid_: produkt, položka, úkon

**Kategorie**:
Jedna ze dvou skupin, do kterých patří každá Služba: **Pneuservis** nebo **Autoservis**. Určuje adresu Služby a rozcestník pro vyhledávače a na stránce Služby dělí seznam nadpisy. Zákazník podle ní nenaviguje: hledá rovnou Službu.
_Avoid_: typ služby, sekce

**Akce**:
Časově omezená nabídka s platností od–do, vázaná na Službu: akční cena (např. zaváděcí cena dekarbonizace), nebo výhoda bez ceny (např. kontrola brzd zdarma ke geometrii). Když platnost skončí, Akce zmizí a Služba zůstane. Na webu ji nese karta a detail její Služby, která jde po dobu Akce napřed; vlastní sekci nemá.
_Avoid_: sleva, novinka, promo

**Délka**:
Doba, po kterou Služba zabírá dílnu, včetně rezervy na úklid a zpoždění. Nastavuje ji Provozovatel u každé Služby.
_Avoid_: duration, trvání, časový rozestup

### Rezervace a provoz

**Rezervace**:
Závazek, že dílna bude v daném Termínu patřit jednomu Zákazníkovi pro jednu nebo více Služeb. Zabírá dílnu po dobu součtu Délek svých Služeb. Vytváří ji Zákazník online, nebo Provozovatel v administraci (telefonické objednávky).
_Avoid_: objednávka, booking, slot

**Termín**:
Datum a čas začátku Rezervace. Začátky leží na mřížce s krokem nastaveným Provozovatelem.
_Avoid_: slot, čas, datum

**Zrušení**:
Ukončení Rezervace, po kterém se její čas uvolní. Zákazník může Rezervaci zrušit odkazem z e‑mailu nejpozději ve **Lhůtě zrušení** před Termínem, Provozovatel kdykoli. Změna Termínu = Zrušení + nová Rezervace.
_Avoid_: storno, smazání, odrezervování

**Pracovní doba**:
Pro každý den v týdnu 0–2 bloky (např. 8:00–12:00 a 13:00–17:00). Rezervace musí celá ležet uvnitř jednoho bloku.
_Avoid_: otevírací hodiny (v textech webu ano, v modelu ne)

**Výjimka**:
Konkrétní den, kdy je zavřeno nebo platí jiná Pracovní doba (dovolená, svátek, zkrácený den). Může se opakovat každý rok.
_Avoid_: zavřený den, closed date, uzavírka

**Sezóna**:
Období přezouvání. Jsou dvě, **jarní** a **podzimní**, každá s datem od–do. Během Sezóny lze online rezervovat jen Služby označené jako sezónní. Sezóna určuje i datum, od kterého se mohou objednávat Leasingoví zákazníci.
_Avoid_: pneu sezóna, season

**Pohotovost**:
Nonstop telefonická pomoc Zákazníkovi mimo otevírací dobu (rozsah upřesní Provozovatel). Nejde ji rezervovat, Zákazník volá.
_Avoid_: havarijní služba, SOS

### Komunikace

**Oznámení**:
Krátké provozní sdělení Provozovatele s platností od–do (např. dovolená, začátek sezóny přezouvání, termíny pro Leasingové zákazníky). Aktivních může být víc najednou. Na webu se zobrazuje nejnovější a vybraná Oznámení se ukazují i u rezervace. Není to článek ani blog.
_Avoid_: novinka, aktualita, příspěvek

**Průvodce**:
Trvalá informační stránka, která odpovídá na častou otázku Zákazníků (např. kdy přezout, jak přečíst rozměr pneumatiky). Na rozdíl od Oznámení nemá platnost.
_Avoid_: článek, blog, rada, poradna

**Objednat znovu**:
Odkaz v e‑mailu k Rezervaci (potvrzení, Zrušení), který otevře rezervační formulář s kontaktními údaji, Službami a „Kola mám uskladněná u vás“ z této Rezervace. Termín vybírá Zákazník znovu, Službu, kterou už nejde objednat online, také. Náhrada zákaznického účtu (ADR 0002), spolu s volitelným zapamatováním údajů v prohlížeči.
_Avoid_: rebook, opakovaná objednávka

**Připomínka přezutí**:
Sezónní e‑mail Zákazníkovi, který k němu dal samostatný souhlas, s odkazem na rezervaci předvyplněnou kontaktními údaji z jeho poslední Rezervace a sezónními Službami a „Kola mám uskladněná u vás“ z jeho poslední Rezervace se sezónní Službou (jen Služby, které jde dál objednat online). Termín vybírá Zákazník sám. Odesílá se před každou Sezónou.
_Avoid_: newsletter, marketingový e‑mail

## Relationships

- Každá **Služba** patří právě do jedné **Kategorie**.
- **Akce** se vždy vztahuje k jedné **Službě**. Služba může mít v čase více Akcí.
- **Oznámení** se nevztahuje ke Službě, týká se provozu jako celku.
- Provozovatel má jednu dílnu a obsluhuje **vždy jen jedno vozidlo najednou**.
- Dvě **Rezervace** se nesmí časově překrývat (dílna je jedna).
- **Rezervace** obsahuje jednu nebo více **Služeb**. Doba, po kterou zabírá dílnu, je součet jejich **Délek**.
- **Leasingový zákazník** si v **Sezóně** může zarezervovat přezutí až od data stanoveného pro leasing (zda se datum vztahuje k vytvoření Rezervace, nebo k Termínu, upřesní Provozovatel).
- **Zákazník** nemá účet. Každá rezervace nese jeho údaje samostatně (viz ADR 0002).

## Example dialogue

> **Dev:** "Dekarbonizace je na webu dvakrát, jednou jako novinka a jednou jako služba. Co z toho je co?"
> **Provozovatel:** "Dekarbonizaci děláme trvale, to je **Služba** v **Kategorii** Autoservis. Ta zaváděcí cena byla **Akce** na pár měsíců."
> **Dev:** "A text o tom, že leasingovky se objednávají až od 1. 4.?"
> **Provozovatel:** "To je **Oznámení**. Platí do konce sezóny a pak ho stáhneme."

## Flagged ambiguities

- „Novinky“ na původním webu míchaly **Akce** (zaváděcí cena) a popis **Služby** (co je dekarbonizace). Rozděleno: popis patří do detailu Služby, cena s platností do Akce.
- „Pneuservis/autoservis“ se používalo jako název firmy i jako druh práce. Rozhodnutí: firma = **Provozovatel** („Pneuservis a autoservis Jan Kárník“), druh práce = **Kategorie**.
