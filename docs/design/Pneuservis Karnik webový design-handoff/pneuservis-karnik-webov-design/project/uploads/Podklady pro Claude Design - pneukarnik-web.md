# Podklady pro Claude Design — pneukarnik-web

Oct 4, 2026 · @Vítek Dvořák

## O čem web je

Web lokálního pneuservisu a autoservisu ve Znojmě, který má dvě úlohy: představit Služby a nechat Zákazníka objednat se online na konkrétní Termín. Dokument popisuje obsah, strukturu a chování webu tak, jak jsou dnes v kódu. Vizuální podobu záměrně neřeší.

- **Provozovatel:** Pneuservis a autoservis Jan Kárník (krátce „Pneuservis Kárník“, logo s iniciálami JVK).
- **Jazyk:** čeština. Německá verze se zatím neřeší.
- **Technika:** vlastní WordPress šablona a rezervační plugin. Vše na webu se vykresluje na serveru, jen rezervační formulář potřebuje JavaScript.
- **Bez cookies vyžadujících souhlas:** žádná cookie lišta. Mapa se načte z Google Maps až po kliknutí, recenze se stahují na serveru, analytika běží bez cookies.
- **Přepnutí ze starého webu:** leden nebo únor 2027, mimo sezónu přezouvání. Staré adresy se přesměrují na nové.
- **Texty:** většina textů v obsahu je zatím zástupná (v hranatých závorkách). První verzi napíše vývojář, Provozovatel ji zkontroluje.

Mimo rozsah první verze: zákaznické účty, platby online, blog a novinky, poptávka pneumatik, samostatná stránka pro firmy a leasing, německá verze.

## Kdo web používá

Veřejnou část používá Zákazník bez účtu, administraci jen Provozovatel.

| Kdo | Kdo to je | Co na webu chce | Jak často |
| --- | --- | --- | --- |
| Zákazník | fyzická osoba nebo firma s vozidlem | zjistit, co servis dělá a za kolik; objednat se na Termín; zavolat; najít adresu a otevírací dobu | 2–4× ročně, hlavně před přezutím |
| Leasingový zákazník | řidič vozidla leasingové společnosti | totéž, navíc vědět, odkdy se v Sezóně může objednat | před Sezónou |
| Provozovatel | lidé z firmy, kteří web spravují | vidět Rezervace v kalendáři, zapsat telefonickou objednávku, spravovat Služby, Akce, Oznámení a otevírací dobu | denně |

Principy, které z toho plynou:

- **Telefon a Rezervovat jsou na každé stránce.** Hlavička je nese vždy, na Úvodu jsou obě akce vidět bez scrollování.
- **Žádná registrace ani přihlášení pro Zákazníka** (ADR 0002). Místo účtu: zapamatování údajů v prohlížeči, odkaz „Objednat znovu“ v e‑mailu a sezónní Připomínka přezutí.
- **Telefon je vždy záchranná cesta.** Kde online nejde něco dokončit (rezervace vypnutá, po Lhůtě zrušení, neplatný odkaz, bez JavaScriptu), web nabídne Zavolat.
- **Prázdné se nevykresluje.** Sekce bez obsahu (žádná Akce, vypnuté recenze, nevyplněné volitelné pole Služby) na stránce vůbec není.

## Pojmy

Web i texty používají jednotné pojmy z `CONTEXT.md`. Ve výkresech a textech je dobré držet stejná slova.

| Pojem | Význam | Nepoužívat |
| --- | --- | --- |
| Služba | trvalá položka nabídky s vlastní stránkou (Přezutí pneu, Geometrie…) | produkt, úkon |
| Kategorie | jedna ze dvou skupin Služeb: **Pneuservis** nebo **Autoservis** | typ služby, sekce |
| Akce | časově omezená cena u jedné Služby s platností od–do; po skončení zmizí | sleva, novinka, promo |
| Rezervace | dohodnutý čas dílny pro jedno vozidlo a jednu nebo víc Služeb | objednávka, booking |
| Termín | datum a čas začátku Rezervace | slot |
| Zrušení | ukončení Rezervace odkazem z e‑mailu, do Lhůty zrušení | storno |
| Lhůta zrušení | kolik hodin před Termínem jde ještě zrušit online (výchozí 24 h) |  |
| Pracovní doba | 0–2 bloky pro každý den v týdnu; na webu „Otevírací doba“ |  |
| Výjimka | konkrétní den zavřeno nebo jinak (dovolená, svátek), může se opakovat každý rok | uzavírka |
| Sezóna | jarní a podzimní období přezouvání; v ní jdou online jen sezónní Služby |  |
| Pohotovost | nonstop telefonická pomoc mimo otevírací dobu; nerezervuje se, volá se | SOS |
| Oznámení | krátké provozní sdělení s platností od–do (dovolená, začátek sezóny) | novinka, aktualita |
| Průvodce | trvalá informační stránka odpovídající na častou otázku | článek, blog |
| Objednat znovu | odkaz v e‑mailu, který otevře formulář s předvyplněnými údaji |  |
| Připomínka přezutí | sezónní e‑mail se samostatným souhlasem | newsletter |

Vztahy: každá Služba patří do jedné Kategorie, Akce vždy k jedné Službě, Oznámení se týká provozu jako celku. Dílna je jedna, takže se dvě Rezervace nikdy nepřekrývají.

## Mapa webu

Veřejný web má pět stránek v hlavním menu a čtyři, na které se chodí z patičky nebo z e‑mailu. Hlavička a patička jsou na všech stránkách stejné.

&#91;embedded content: mapa webu · veřejné stránky a jejich adresy\]

Detail Služby vede pod svou Kategorii, Potvrzení se otevře jen po odeslání formuláře. K tomu je ještě stránka 404. Úvod odkazuje na všechny stránky z menu i na jednotlivé Služby.

**Hlavní menu** (pořadí z kódu): Pneuservis · Autoservis · O nás · Kontakt · Rezervace.

**Administrace** (jen Provozovatel, standardní WordPress): Služby · Akce · Oznámení · Průvodci · Rezervace (kalendář den/týden s rychlým zadáním, seznam s filtry, PDF denní přehled, Výjimky, převod ze starého webu) · Nastavení (online rezervace, Pracovní doba, mřížka a lhůty, Sezóny, texty e‑mailů, Připomínka, kontakty, Pohotovost, Proč k nám, Google recenze, Matomo, iCal).

## Obsah stránek

Každá stránka je níže rozepsaná na sekce v pořadí, v jakém jsou v kódu. Texty v uvozovkách jsou přesně tak, jak jsou na webu. Poznámka „z Nastavení“ znamená, že obsah zadává Provozovatel v administraci a na stránce se objeví jen vyplněný.

### Společné pro všechny stránky

**Pruh Oznámení** (nad hlavičkou, jen když nějaké platí)

- nejnovější platné Oznámení: nadpis + krátký text,
- tlačítko „Zavřít oznámení“; zavřené zůstane skryté do zavření prohlížeče,
- na stránce Rezervace se nezobrazí, pokud je stejné Oznámení u formuláře.

**Hlavička**

- Pohotovost (jen když je zapnutá): slovo „Pohotovost“, telefon Pohotovosti, volitelný popis. Má být v hlavičce výrazná.
- název webu jako odkaz na Úvod (logo JVK),
- telefon (klikací `tel:`) a tlačítko „Rezervovat“,
- hlavní menu: Pneuservis · Autoservis · O nás · Kontakt · Rezervace.

**Patička**

- kontakt: název firmy, adresa, telefon, e‑mail, IČ, DIČ (z Nastavení),
- seznam „Průvodci“ s odkazy (jen zveřejnění),
- sociální sítě (z Nastavení, otevírají se v novém okně),
- odkazy: O nás · Kontakt · Ochrana osobních údajů.

### Úvod `/`

Sekce v tomto pořadí, každá bez obsahu se vynechá:

1. **Hero:** nadpis = název firmy, text „Pneuservis a autoservis. Objednejte se online, nebo zavolejte.“, telefon + „Rezervovat“. Musí být vidět bez scrollování.
2. **„Co pro vás uděláme“:** dvě dlaždice Pneuservis a Autoservis vedoucí na rozcestníky.
3. **„Nejžádanější služby“:** karty Služeb označených jako nejžádanější (karta viz Rozcestník).
4. **„Aktuální akce“:** jen když nějaká platí. Každá položka: název Akce (odkaz na Službu), „Služba: akceční cena“, „Akce platí do 31. 3. 2027.“
5. **„Proč k nám“:** seznam důvodů, jeden na řádek (rok založení, BestDrive, vybavení…), z Nastavení.
6. **„Hodnocení na Google“:** souhrn „4,8 z 5 (123 hodnocení)“, několik recenzí (hvězdičky 1–5, text, autor s odkazem, datum), odkaz „Všechna hodnocení na Google“. Bez fotek autorů.
7. **„Otevírací doba“:** tabulka 7 dní od dneška. Řádky „Dnes“, „Zítra“, pak názvy dnů, ke každému datum; hodnota „8:00–12:00, 13:00–17:00“ nebo „Zavřeno“, u Výjimky poznámka v závorce (např. „Dovolená“). Dnešní řádek je odlišený.
8. **„Kde nás najdete“:** mapa po kliknutí (viz Mapa níže).

### Rozcestník Kategorie `/pneuservis/`, `/autoservis/`

- nadpis = název Kategorie,
- karty zveřejněných Služeb v pořadí z administrace,
- **karta Služby:** štítek „Akce“ (když platí), název s odkazem na detail, perex (1–2 věty), cena,
- cena má tři podoby: „600 Kč“, „od 600 Kč“, „Cena dle vozu“,
- bez Služeb: „Služby připravujeme.“

Služby převedené ze starého webu (podle starých adres): Přezutí pneu, Sezónní uskladnění, Prodej pneu, Geometrie, Příprava na STK, Oprava čelních skel, Doplnění klimatizace, Výměna brzdových destiček, Výměna výfuku, Výměna oleje, Výměna tlumičů, Výměna žárovek, Výměna autobaterie, Autodiagnostika, Nástřik podvozku proti korozi, Dekarbonizace, Dezinfekce vozidla ozonem, Oprava defektu kol na elektrokoloběžkách, Servisní prohlídka před dovolenou. Které budou na novém webu a v jaké Kategorii, potvrdí Provozovatel.

### Detail Služby `/pneuservis/{služba}`, `/autoservis/{služba}`

Povinné jsou jen název s perexem, cena a výzva k akci. Ostatní části se zobrazí, jen když jsou vyplněné.

1. **Hlavička:** odkaz na Kategorii (drobeček), název Služby, perex.
2. **Blok Akce** (jen když platí): štítek „Akce“, název Akce, akceční cena, popis, „Akce platí do …“.
3. **„Co zahrnuje“:** seznam položek.
4. **„Jak to probíhá“:** odstavce textu + „Jak dlouho to trvá: zhruba 30 minut“.
5. **„Cena“:** částka (tři podoby jako na kartě) + „Cena zahrnuje: …“.
6. **„Co si vzít s sebou“:** seznam položek.
7. **„Časté dotazy“:** otázky, které se rozbalí na odpověď.
8. **Výzva k akci:** „Rezervovat“ (jen u Služby rezervovatelné online, otevře formulář s touto Službou předvybranou) + „Zavolat {telefon}“.
9. **„Související služby“:** seznam odkazů.

Služba „jen na telefon“ (nerezervovatelná online) má jen Zavolat.

### Rezervace `/rezervace/`

Nadpis „Rezervace termínu“. Nad formulářem platná Oznámení označená „zobrazit i u rezervace“ (třeba podmínky pro leasing). Formulář je jedna stránka, části jdou za sebou:

| Část | Pole a prvky | Poznámka |
| --- | --- | --- |
| Služba | výběr „Služba“; tlačítko „+ přidat další službu“ přidá řádek „Další služba“ s „Odebrat“ | až 10 Služeb; čas Rezervace = součet jejich Délek |
| Kola | „Kola mám uskladněná u vás“ | jen u Služeb, kde se na to Provozovatel ptá |
| Leasing | „Vozidlo je na leasing“ → odkryje „Leasingová společnost“ | mění, které dny jsou v Sezóně volné |
| Den | měsíční kalendář Po–Ne, šipky předchozí/další měsíc | volné dny klikací, ostatní zašedlé; pod ním stavový text |
| Volné termíny | seznam časů „8:00–9:00“ k výběru (jeden) | načte se po výběru dne |
| Vaše údaje | Jméno nebo firma · Telefon · E‑mail · SPZ · Značka a model (nepovinné) · Poznámka (nepovinné) | nápověda u poznámky: „Další přání, která mezi službami nenajdete, napište sem.“ |
| Zapamatovat | „Zapamatovat údaje na tomto zařízení“ + vysvětlení; „Smazat uložené údaje“ | ukládá jen v prohlížeči |
| Souhlas | „Souhlasím se zpracováním osobních údajů pro vyřízení rezervace.“ + odkaz na Zásady | povinný |
| Připomínka | „Připomeňte mi před každou sezónou, že je čas přezout (nepovinné).“ + „Přijde e‑mailem dvakrát do roka, odhlásit se jde jedním kliknutím v každé připomínce.“ | nepovinný |
| Odeslat | tlačítko „Rezervovat“, nad ním místo pro celkovou chybu |  |

Alternativní stavy stránky místo formuláře:

- online rezervace vypnutá → zpráva Provozovatele + „Zavolat …“,
- žádná Služba nejde online → „Online teď nejde objednat žádná služba. Zavolejte nám na …“,
- vypnutý JavaScript → „Rezervace potřebuje zapnutý JavaScript. Objednat se můžete i telefonicky na …“.

### Potvrzení `/rezervace/potvrzeni/`

Nadpis „Rezervace přijata“, text „Děkujeme, těšíme se na vás. Potvrzení jsme poslali také e‑mailem.“ Pod ním souhrn: Termín („pondělí 1. 3. 2027 v 8:30“), Služba/Služby, SPZ, případně Leasing a Kola „uskladněná u nás“. Odkaz „Zpět na úvod“. Bez platného odkazu stránka neexistuje (404).

### Zrušení `/rezervace/zruseni/`

Otevírá se jen odkazem z e‑mailu. Nadpis „Zrušení rezervace“, souhrn Rezervace (Termín, Služby, SPZ), pak jeden z šesti stavů (viz Klíčové toky).

### Průvodce `/pruvodce/{název}`

- štítek „Průvodce“, nadpis, perex (na jakou otázku odpovídá),
- text s mezititulky (volný editor),
- závěrečný blok „Objednejte se: Přezutí pneu“ + odkaz „Co Služba zahrnuje a kolik stojí“ + Rezervovat/Zavolat.

Tři Průvodci pro start: „Kdy přezout na zimní a letní pneumatiky“, „Uskladnění pneumatik u nás“, „Kdy pneumatiky vyměnit“. Seznam Průvodců je v patičce, samostatný výpis nemá.

### O nás `/o-nas/`

Volná stránka z editoru se sekcemi: Historie · Tým · BestDrive a Barum (včetně věrnostní karty BestDrive) · Galerie (mřížka fotek). Texty doplní Provozovatel.

### Kontakt `/kontakt/`

1. „Adresa a spojení“: firma, adresa, telefon, e‑mail,
2. „Otevírací doba“: stejná tabulka 7 dní jako na Úvodu,
3. „Jak k nám“: popis příjezdu a parkování (text stránky),
4. „Mapa“,
5. „Fakturační údaje“: firma, adresa, IČ, DIČ.

### Odhlášení z e‑mailů `/odhlaseni/`

Odhlásí hned po otevření odkazu. Úspěch: „Hotovo, Připomínky přezutí vám už posílat nebudeme.“ + „Kdybyste si to rozmysleli, stačí při příští rezervaci zaškrtnout souhlas s připomínkou.“ Neúspěch: vysvětlení + telefon a e‑mail. Vždy „Zpět na úvod“.

### Ochrana osobních údajů `/ochrana-osobnich-udaju/`

Textová stránka: Správce · Jaké údaje a proč · Jak dlouho · Cookies a měření návštěvnosti · Vaše práva.

### Stránka nenalezena (404)

Nadpis „Stránka nenalezena“ a „Zpět na úvod“.

### Opakované prvky

- **Mapa:** nic se nenačítá od Googlu, dokud Zákazník neklikne na „Zobrazit mapu“. Vedle text „Mapa se načte z Google Maps až po kliknutí.“, pod ní adresa a „Otevřít v Google Maps“.
- **Štítek Akce:** stejné slovo „Akce“ na kartě i v detailu.
- **Telefon + Rezervovat:** stejná dvojice v hlavičce, v hero a na konci Průvodce.
- **Tabulka otevírací doby:** na Úvodu i v Kontaktu, vždy 7 dní dopředu.

## Klíčové toky

Hlavní tok je online rezervace; všechny ostatní se k ní vracejí přes odkazy v e‑mailech. Pravidla počítá server, stránka jen ukazuje, co je volné.

### Online rezervace

1. Zákazník přijde z hlavičky, hero, detailu Služby nebo Průvodce. Z detailu Služby a Průvodce je Služba už předvybraná.
2. Vybere jednu nebo víc Služeb. U pneuslužeb se může objevit „Kola mám uskladněná u vás“.
3. Volitelně zaškrtne leasing a doplní společnost.
4. V kalendáři vybere volný den. Bez vybrané Služby kalendář říká „Nejdřív vyberte službu, pak uvidíte volné dny.“
5. Vybere jeden z volných Termínů „8:00–9:00“. Konec = začátek + součet Délek.
6. Vyplní údaje. Když je má zapamatované nebo přišel přes „Objednat znovu“, jsou už předvyplněné.
7. Zaškrtne souhlas se zpracováním, volitelně Připomínku a zapamatování.
8. Odešle „Rezervovat“. Chyby se ukáží u konkrétních polí, celková chyba nad tlačítkem.
9. Pokračuje na stránku Potvrzení a dostane e‑mail. Provozovatel dostane upozornění (když ho má zapnuté).

Změnit Službu nebo leasing znovu přepočítá volné dny i Termíny.

**Stavové texty kalendáře a Termínů** („…“ = načítání):

- „Načítám volné dny…“, „Načítám volné termíny…“
- „Zašedlé dny nemají volný termín.“
- „V tomto měsíci nejsou volné termíny. Zkuste další měsíc.“
- „V tento den nejsou volné termíny. Zkuste prosím jiný den.“
- v Sezóně: „Od {od} do {do} je sezóna přezouvání a online jde objednat jen přezutí a související služby. Ostatní služby v tu dobu objednáváme telefonicky na …“
- leasing: „Vozidla na leasing objednáváme v jarní sezóně až od {datum}, tak to určují leasingové společnosti.“

**Chyby při odeslání:**

- „Vybraný termín si mezitím rezervoval někdo jiný. Vyberte prosím jiný.“
- „Vybraný termín už není v nabídce. Vyberte prosím jiný.“
- „Odeslali jste příliš mnoho rezervací. Zavolejte nám prosím na …“
- „Rezervaci se nepodařilo odeslat. Zkuste to znovu.“
- u polí: „Vyberte službu.“, „Vyberte den.“, „Vyberte termín.“, „Zadejte telefon, například 777 123 456.“, „SPZ může obsahovat jen písmena a číslice.“, „Bez souhlasu nemůžeme rezervaci přijmout.“

### Zrušení odkazem z e‑mailu

1. Zákazník klikne v potvrzovacím e‑mailu na „Zrušit rezervaci“.
2. Stránka ukáže souhrn Rezervace a stav.
3. V povoleném stavu potvrdí tlačítkem „Zrušit rezervaci“. Samé otevření odkazu nic neruší.
4. Přijde mu e‑mail o Zrušení s „Objednat znovu“, Provozovatel dostane upozornění.

| Stav | Text na stránce | Další nabídka |
| --- | --- | --- |
| Před Lhůtou | „Rezervaci můžete zrušit nejpozději 28. 2. 2027 v 8:30. Termín se tím uvolní pro ostatní.“ | tlačítko Zrušit rezervaci |
| Zrušeno | „Rezervace je zrušená a termín jsme uvolnili. Potvrzení jsme vám poslali e‑mailem.“ | Objednat znovu |
| Po Lhůtě | „Online šlo rezervaci zrušit nejpozději … Zavolejte nám prosím na …“ | Zavolat, Objednat znovu |
| Už zrušená | „Tato rezervace už je zrušená.“ | Objednat znovu |
| Příliš pokusů | „Z vašeho připojení přišlo příliš mnoho pokusů o zrušení. Rezervace zůstává, zkuste to prosím za hodinu.“ | Zavolat, Objednat znovu |
| Neplatný odkaz | „Odkaz pro zrušení je neplatný nebo už vypršel.“ | Zavolat, Objednat znovu |

Změna Termínu nemá vlastní tok: je to Zrušení a nová Rezervace s předvyplněnými údaji.

### Objednat znovu

Odkaz v e‑mailu (potvrzení, Zrušení) otevře `/rezervace/` a doplní jméno, telefon, e‑mail, SPZ a vozidlo z původní Rezervace. Službu a Termín vybírá Zákazník znovu.

### Připomínka přezutí a odhlášení

1. Zákazník při rezervaci zaškrtne nepovinný souhlas.
2. Před každou Sezónou (nastavený počet dní předem) mu přijde e‑mail „Je čas přezout“, nejvýš jednou za Sezónu.
3. Tlačítko „Objednat přezutí“ otevře předvyplněný formulář.
4. Odkaz „Odhlásit Připomínky přezutí“ odhlásí jedním kliknutím na stránce `/odhlaseni/`.

### Telefonická objednávka (administrace)

Provozovatel zapíše Rezervaci v kalendáři administrace. Blokuje čas stejně jako online Rezervace, takže se na web už nenabídne.

## E‑maily a další výstupy

Web posílá šest druhů e‑mailů, všechny se stejnou stavbou: nadpis → odstavce → tabulka údajů → seznam → jedno hlavní tlačítko → odkazy → podpis s kontakty. Každý má HTML i textovou verzi.

| E‑mail | Komu | Předmět | Obsah | Hlavní tlačítko |
| --- | --- | --- | --- | --- |
| Potvrzení rezervace | Zákazník | „Potvrzení rezervace na {den}“ | nadpis „Rezervace přijata“, úvod, údaje (Termín od–do, Služby, SPZ, Vozidlo, Leasing, Uskladněná kola, Adresa), „Co si vzít s sebou“, do kdy jde zrušit, telefon pro změny, odkaz Objednat znovu | Zrušit rezervaci |
| Zrušení | Zákazník | „Rezervace na {den} je zrušená“ | „Vaše rezervace je zrušená a termín jsme uvolnili.“, údaje, případně důvod (když rušil Provozovatel) | Objednat znovu |
| Nový odkaz ke zrušení | Zákazník převedený ze starého webu | „Nový odkaz ke zrušení rezervace na {den}“ | „Vaše rezervace platí“, údaje, do kdy jde zrušit | Zrušit rezervaci |
| Připomínka přezutí | Zákazník se souhlasem | „Je čas přezout: jarní sezóna začíná {den}“ | úvod, „Naše jarní sezóna přezouvání začíná … Objednejte se včas…“, „Raději zavoláte? Jsme na …“, proč e‑mail dostává, odkaz na odhlášení | Objednat přezutí |
| Nová rezervace | Provozovatel | „Nová rezervace: {den}, {jméno}“ | údaje Rezervace + kontakt Zákazníka a poznámka | Otevřít v administraci |
| Zrušená rezervace | Provozovatel | „Zrušená rezervace: {den}, {jméno}“ | „Zákazník rezervaci zrušil odkazem z e‑mailu. Termín je znovu volný.“ + údaje | Otevřít v administraci |

Texty, které Provozovatel mění v Nastavení (výchozí znění):

- **Úvod potvrzení:** „Dobrý den, děkujeme za rezervaci. Těšíme se na vás.“
- **Co si vzít s sebou:** „Technický průkaz vozidla“, „Klíč k pojistným šroubům kol“
- **Podpis:** „S pozdravem, Pneuservis a autoservis Jan Kárník“
- **Úvod Připomínky:** „Dobrý den, blíží se sezóna přezouvání a o termíny bývá velký zájem.“

Výstupy jen pro Provozovatele: **PDF denní přehled** Rezervací (čas, Služba, jméno nebo firma, poznámka) a **iCal** odběr do kalendáře v telefonu přes tajný odkaz.

## Pravidla, která mění obsah

Velká část obsahu se mění podle data a nastavení, ne podle ruční úpravy stránky. Návrh musí počítat s oběma stavy každého prvku: zobrazený i chybějící.

### Rezervace

| Pravidlo | Výchozí hodnota | Co to dělá na webu |
| --- | --- | --- |
| Jedno vozidlo najednou | pevné | Termíny se nepřekrývají |
| Krok mřížky Termínů | 30 min | časy začínají 8:00, 8:30, 9:00… |
| Předstih pro dnešek | 60 min | dnešní Termíny jen hodinu dopředu |
| Horizont | 60 dní | kalendář dál nepustí |
| Lhůta zrušení | 24 h | pak už jen telefon |
| Max. Služeb v Rezervaci | 10 | ostatní do poznámky |
| Rezervace musí ležet v jednom bloku Pracovní doby | pevné | dlouhá Služba nemá Termíny těsně před polednem |
| V Sezóně jen sezónní Služby | podle Sezón | ostatní Služby mají v Sezóně dny zašedlé a vysvětlení |
| Leasing v Sezóně až od leasingového data | podle Sezón | dny před ním zašedlé a vysvětlení |
| Výjimky a státní svátky ČR | svátky předvyplněné | zavřené dny v kalendáři i v otevírací době |
| Online rezervace lze vypnout | zapnuto | místo formuláře zpráva a telefon |

### Obsah podle platnosti

- **Akce** se zobrazí jen od–do (oba dny včetně), pak sama zmizí. Místa: štítek na kartě, blok v detailu Služby, sekce na Úvodu.
- **Oznámení** se zobrazí jen od–do. Aktivních může být víc: nahoře jen nejnovější, u rezervace všechna označená „i u rezervace“.
- **Pohotovost** je v hlavičce jen zapnutá a s telefonem.
- **Recenze** jen zapnuté a stažené. Když Google selže, zůstávají poslední stažená data.
- **Průvodce** nejde zveřejnit bez Služby pro rezervaci; **Službu** ani **Akci** bez povinných polí.
- **Kontaktní údaje** jsou na jednom místě v Nastavení a plní hlavičku, patičku, Kontakt i e‑maily. Prázdné pole se nikde neobjeví.

### Soukromí

- Žádná cookie lišta: web nemá cookies vyžadující souhlas. Změní se jen při placené reklamě.
- Mapa se načte až po kliknutí, recenze bez obrázků od Googlu.
- Zavřené Oznámení si web pamatuje jen do zavření prohlížeče, zapamatované údaje jen v prohlížeči Zákazníka.
- Rezervace se 1 rok po Termínu anonymizují.
- Souhlas s Připomínkou je samostatný a nepovinný, nikdy předem zaškrtnutý.
- Potvrzení, Zrušení a Odhlášení se neindexují ve vyhledávačích; v adrese nikdy nejsou osobní údaje, jen tajný token.

### Vyhledávače a staré adresy

- Každá Služba a Průvodce má vlastní titulek a popis pro Google, výchozí „název – název firmy“ a perex.
- Staré adresy (příspěvky, `/service/{služba}`, kotvy jednostránky jako `#services`, `#galery`) se trvale přesměrují na nové stránky.

### Otevřené otázky, které ovlivní obsah

Odpovědi Provozovatele chybí (`docs/otazky-pro-klienta.md`):

- rok založení, vztah k BestDrive a Barum, tým a vybavení (sekce Proč k nám, O nás),
- rozsah Pohotovosti a její telefon,
- ceník přezutí a uskladnění podle velikosti kola, „od“ vs. „dle vozu“,
- které Služby jdou online a které jen na telefon,
- logo ve vektoru a nové fotky (dnešní jsou z ledna 2022, logo jen 200×200 px),
- souhlas se zobrazením Google recenzí a odkazy na sociální sítě.
