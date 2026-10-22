# Otázky pro klienta

Otázky na Provozovatele, které vývojář sám nerozhodne. Průběžně doplňované během přípravy nového webu.
Až přijde odpověď, zapište ji pod otázku a otázku označte `[x]`.
Provozovateli jdou tyto otázky jako rozhovor s ChatGPT, viz `docs/podklady-od-klienta/`.

---

## Firma a fakta o ní

- [x] **Od kdy firma funguje?** Na webu je „od roku 1991“. Platí to?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: od roku 1991, rodinná firma ve druhé generaci. Od té doby přibyly stroje a lidé, nověji dekarbonizace, nová klimatizace a diagnostika. Jak firma začínala, neví. Úvodní věta O nás: „Jsme rodinný pneuservis a autoservis ve Znojmě, který funguje od roku 1991.“
- [x] **Barum Continental:** Jste pořád „jediná znojemská franšíza Barum Continental“? Pokud ne, jaký je teď vztah k Barum/Continental?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: tvrzení „jediná znojemská franšíza Barum Continental“ potvrdil.
- [x] **BestDrive:** Jste pořád pobočkou nebo partnerem sítě BestDrive? Platí věrnostní karta BestDrive i u vás? Máte k tomu od BestDrive nějaké podklady (logo, texty, podmínky)?
  > Částečně z webu BestDrive: pobočku vede jako „Franšízová pobočka sítě BestDrive – Jan Kárník, Znojmo“, text O nás z toho vychází. O věrnostní kartě a o Barum tam nic není. Výčet nabídky (pneumatiky, litá i ocelová kola, autodoplňky) je obecný pro celou síť „dle zaměření pobočky“, pro Znojmo nepotvrzený.
  > Výhody klubu na webu jen odkazem na [Klub BestDrive](https://www.bestdrive.cz/klub.html) (registrace online, slevy se uplatní číslem karty), ať nezastarají. Od Provozovatele potřebujeme jen potvrdit, že karta u něj platí a kdy zákazník číslo karty nahlásí.
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: franšíza BestDrive, věrnostní karta u něj platí (slevy, kartu zákazník dostane přes BestDrive). Jak ji u něj uplatnit a jestli má a pošle podklady od BestDrive, neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): na webu „Číslo karty nahlaste při placení.“, Provozovatel jen potvrdí. Podklady od BestDrive nebudou.
- [x] **Nonstop pohotovost:** Funguje pořád? Na jakém čísle, co pokrývá (defekt na cestě, odtah, výjezd) a v jaké oblasti?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: funguje v omezeném režimu, ne nonstop: Po–Pá 8:00–17:00, o víkendech ne. Telefon 775565326, defekt na cestě a výjezd k autu, Znojmo a okolí. Výjezd za pevnou cenu, částku neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): cenu výjezdu na web nedávat, řekne ji do telefonu.
- [x] **Tým:** Kolik lidí v servisu pracuje? Chcete je na webu představit (jméno, fotka, čím se zabývá)?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: 6 lidí. Na webu jen společně jako „náš tým“, bez jmen a fotek.
- [x] **Vybavení:** Jaké stroje a technologie stojí za zmínku (geometrie, diagnostika, vyvažovačka, plnění klimatizace…)? Konkrétní značky a typy zákazníky přesvědčí víc než obecné „profesionální stroje“.
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: diagnostika TEXA. Značky ostatních strojů (geometrie, vyvažovačka, montážní stroj, plnění klimatizace, ozon, dekarbonizace) neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): značky strojů neuvádět, jmenovitě jen diagnostika TEXA.
- [x] **Čím se lišíte od konkurence?** Proč k vám zákazníci chodí? (Na tom postavíme úvodní stránku.)
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: schválil řádky Proč k nám: Zkušenosti od roku 1991 (Servisu se věnujeme od roku 1991.), Pneu i autoservis (Vše vyřídíte na jednom místě.), Osobní přístup (Zákazníci se k nám dlouhodobě vracejí.), Rychlé objednání (Často se dostanete na řadu ještě tentýž den.), Férové ceny (Ceny odpovídají odvedené práci.). Tentýž den záleží na vytížení, hlavně v Sezóně.

## Kontakty a provoz

- [x] **E-mail:** Zůstane `j.v.karnik@volny.cz`, nebo chcete adresu na doméně (např. `servis@pneukarnik.cz`)?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: zůstává `j.v.karnik@volny.cz`.
- [x] **Otevírací doba:** Platí Po–Pá 8:00–12:00 a 13:00–17:00, So–Ne zavřeno? Mění se během sezóny přezouvání (např. otevřeno i v sobotu)?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: platí, v Sezóně beze změny. Obvyklou dovolenou neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): dovolenou zadá Provozovatel do Výjimek, až ji bude znát.
- [x] **Příjezd a parkování:** Je u servisu kde parkovat? Je něco, co je dobré vědět při příjezdu (vjezd z ulice, dvůr…)?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: parkuje se přímo u servisu. Orientační body a příjezdovou trasu neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): na Kontaktu jen „Zaparkujete přímo u servisu.“ a mapa, bez popisu trasy.
- [x] **Čekárna:** Může zákazník počkat na místě? Je k dispozici káva, Wi‑Fi?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: u většiny Služeb může zákazník počkat v servisu. U Geometrie je lepší vrátit se později, u Výměny oleje doporučeno a u Výměny tlumičů nutné nechat auto v servisu. Káva a Wi‑Fi: neodpověděl.
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: čekárna je. Káva a Wi‑Fi: neodpověděl.
  > Rozhodnuto s vývojářem (2026‑10‑22): kávu a Wi‑Fi neuvádět.
- [x] **Platba:** Jde platit kartou? Fakturace pro firmy?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: hotově, kartou, fakturou pro firmy.
- [x] **Náhradní vozidlo:** Nabízíte ho?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: ne.
- [x] **Sociální sítě:** Máte Facebook, Instagram nebo Google Firmu? Pošlete prosím odkazy.
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: odkazy na Facebook, Instagram a Google Mapy pošle později.
  > Od Provozovatele (přes vývojáře): Facebook https://www.facebook.com/jvkpneu, Instagram https://www.instagram.com/pneuservisjan/, Google Firma https://share.google/v4htT35a3nmeGdn5g.

## Služby a ceny

- [x] **Ceny pneuservisu:** Chcete na webu ceník přezutí a uskladnění podle velikosti kola (např. do 15", 16–17", 18"+, SUV, dodávka)? Pokud ano, pošlete prosím ceny.
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: jednotná cena přezutí 650 Kč, uskladnění 370 Kč za sezónu za sadu 4 kol. Ceník podle velikosti kola nemá.
- [x] **Ceny autoservisu:** U kterých služeb ukázat „od X Kč“ a u kterých raději „cena dle vozu“? Jsou současné ceny na webu aktuální?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: pevná cena Příprava na STK 390 Kč, Nástřik podvozku 1 500 Kč, Prohlídka před dovolenou 399 Kč, Dekarbonizace 1 690 Kč. „Od“ Geometrie 860 Kč, Servis klimatizace 750 Kč, Dezinfekce ozonem 299 Kč (dřív 600 Kč), Autodiagnostika od 635 Kč u vozů do roku 2022 a od 750 Kč od roku 2022. Dle vozu výměny destiček, výfuku, tlumičů a žárovek, Oprava čelních skel podle rozsahu poškození, Výměna oleje „od“ podle množství oleje bez částky.
- [x] **Co cena zahrnuje:** Např. přezutí = demontáž, montáž, vyvážení, ventilek? Uskladnění = za sezónu, včetně mytí kol?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: přezutí = demontáž a montáž kol, vyvážení, kontrola tlaku a optimalizace kol. Uskladnění = za sezónu za sadu 4 kol, včetně mytí a kontroly.
- [x] **Prodej pneumatik:** Prodáváte pneumatiky? Jaké značky? Chcete na webu formulář „Poptat pneumatiky“ (rozměr, sezóna, počet kusů)?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: prodává všechny druhy pneumatik a formulář chce (rozměr, letní/zimní, počet kusů). Značky a objednání na přání neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): značky neuvádět, konkrétní pneumatiky nabídne servis na Poptávku.
- [x] **Chybějící služby:** Děláte něco, co na webu není? Např. opravy defektů, vyvažování, plnění dusíkem, pneu pro motocykly, dodávky, SUV, prodej disků.
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: ano, všechno z návrhu: opravy defektů, vyvažování kol, huštění dusíkem, pneu pro motocykly, dodávky a SUV, prodej kol a disků, prodej pneumatik, opravy pneumatik na elektrokoloběžkách. Ceny, časy a podrobnosti neví. Žádnou z 17 původních Služeb nepřestal dělat.
  > Rozhodnuto s vývojářem (2026‑10‑22): nové služby jen zmínkou v textu kategorie Pneuservis s cenou „na dotaz“, bez online rezervace. Opravy na elektrokoloběžkách pokrývá Služba Duše a pláště na elektrokoloběžky. Samostatnou Službu založit, až ji bude chtít rezervovat online.
- [x] **Délka služeb:** Jak dlouho trvají jednotlivé služby (kvůli rezervaci termínů)?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: Geometrie 180 minut, ostatní Služby autoservisu a Duše a pláště na elektrokoloběžky 60 minut, Uskladnění samostatný blok nepotřebuje. U Přezutí neví, podle velikosti disků a typu vozidla.
  > Rozhodnuto s vývojářem (2026‑10‑22): Přezutí si nechá dnešní Délku (stejnou jako na starém webu), Provozovatel ji upraví po první Sezóně. Časté dotazy a co si vzít s sebou, kde je nezná, zůstanou prázdné, detail Služby je neukáže.
- [x] **Akce a termín:** Platí akční cena, když se zákazník objedná v době akce, ale na termín až po jejím konci? Web zatím ukazuje akční cenu ve výběru služby podle dne objednání, ne podle termínu.
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: přeskočil. Žádnou Akci teď nemá.
  > Rozhodnuto s vývojářem (2026‑10‑22): akční cena platí podle dne objednání, jak to web dělá, protože ji Zákazník při objednání viděl.

## Rezervace

- [x] **Leasingoví zákazníci:** Jaké podmínky mají leasingové společnosti? Platí pro ně jen jiné období pro objednávání přezutí (léto od 1. 4., zima od 1. 10.), nebo i něco dalšího? Jak poznáte, že jde o leasingový vůz?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: podmínka: přezutí musí schválit leasingová společnost. Společnosti a jak leasingové auto pozná, neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): leasingové auto se pozná podle leasingové společnosti, kterou Zákazník vybere ve formuláři.
- [x] **Leasing: co znamená „od 1. 4.“?** Že se leasingový zákazník může **objednat** (vytvořit rezervaci) až 1. 4. a později, nebo že **termín přezutí** musí být 1. 4. a později (objednat se může klidně v březnu)? A platí to jen pro přezutí, nebo pro všechny služby?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: platí jen pro přezutí. Letní přezutí od 1. 4., zimní musí být hotové do 1. 11. (ne „od 1. 10.“). Jestli jde o den objednání, nebo Termín, neví. Web hlídá leasingové období Sezóny (od, do) jen u sezónních Služeb a vůči Termínu. „Do 1. 11.“ je nastavené jako poslední den 31. 10., ověřit telefonem. U leasingové společnosti web připomene, že přezutí musí předem schválit.
  > Rozhodnuto s vývojářem (2026‑10‑22): jde o Termín přezutí, jak web hlídá. Zimní přezutí nejpozději 31. 10.
- [x] **Telefonické objednávky:** Kam si dnes zapisujete zákazníky, kteří se objednají po telefonu (diář, papír, administrace webu)? Pokud ne do webu, může se stát, že si online někdo zarezervuje stejný čas.
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: zapisuje je do administrace webu.
- [x] **Co si vzít s sebou:** Na webu je „technický průkaz, pojistný šroub, ofocený TP e-mailem při výměně dílů nebo oleje“. Platí to? Je toho víc?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: u Přezutí bezpečnostní klíč k diskům, u Geometrie, Přípravy na STK a Výměny tlumičů technický průkaz, u ostatních nic nebo neví. Ofocený TP e‑mailem nezmínil.
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: technický průkaz a pojistný šroub, při výměně dílů nebo oleje předem e‑mailem fotografii technického průkazu (tedy jak na starém webu).
- [x] **Které služby jdou rezervovat online** a které jen po telefonu?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: všechny online, Uskladnění jen spolu s Přezutím. Omezení online rezervace v Sezóně (otázka „Sezóna přezouvání“) tím vyřešené není.
- [x] **Kapacita:** Kolik aut můžete obsluhovat současně?
  > Jedna dílna, jedno auto najednou (odpověděl vývojář).
- [x] **Sezóna přezouvání:** V jakém období ji máte (zhruba od–do na jaře a na podzim)? Dnešní web v sezóně (nastaveno 1. 3.–30. 11.) dovolí online rezervovat **jen sezónní služby**, autoservis se tedy dá objednat jen po telefonu. Je to záměr, který má platit dál?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: jaro 15. 3.–30. 4., podzim 1. 10.–15. 11. Autoservis má jít online objednat i v Sezóně, pravidlo „v Sezóně jen sezónní Služby“ je zrušené.
- [x] **Upozornění na novou rezervaci:** Dnes vám o nové rezervaci nepřijde e‑mail, vidíte ji jen v administraci. Chcete e‑mail o každé nové rezervaci? Nebo rezervace rovnou v kalendáři v telefonu (Google Kalendář apod.)?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: e‑mail o každé nové Rezervaci a administrace webu.
- [x] **Odběr novinek:** Ve formuláři je „chci dostávat informace o slevách“. Posíláte zákazníkům něco? (Odesílání je na webu vypnuté.)
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: slevy ani novinky dnes neposílá. Připomínku přezutí před Sezónou chce, se souhlasem Zákazníka.
- [x] **Víc služeb najednou:** Jak často chce zákazník při jedné návštěvě víc věcí (přezutí + geometrie, olej + klimatizace)?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: občas.
- [x] **Uskladněná kola:** Potřebujete při objednání vědět, že zákazník má u vás kola uskladněná (abyste je předem připravili)?
  > Z `docs/podklady-od-klienta/odpovedi-2.md`: ne, ptát se nechce.
- [x] **Jak dopředu:** Na kolik dní nebo týdnů dopředu se zákazník může objednat? A nejpozději kolik hodin předem?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: nejpozději den předem. Jak dlouho dopředu nejvýš, neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): Horizont zůstává 60 dní.
- [x] **Zrušení:** Do kdy může zákazník rezervaci sám zrušit (např. den předem)? Co když nepřijde?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: nejpozději den předem (Lhůta zrušení 24 hodin). Když Zákazník nepřijde, zavolají mu.
- [x] **Potvrzování:** Má být rezervace potvrzená hned automaticky, nebo ji chcete před potvrzením vidět a schválit?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: automaticky.

## Technika a přístupy

- [ ] **Doména a DNS:** Kdo spravuje doménu `pneukarnik.cz` (registrátor, DNS)? Budeme potřebovat nastavit záznamy pro e‑maily (SPF/DKIM), aby potvrzení rezervací nechodila do spamu.
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: neví.
- [x] **Hosting:** U koho je web hostovaný a kdo k němu má přístup?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: neví.
  > Webglobe. Přístup do administrace hostingu i WordPressu má vývojář (odpověděl vývojář). Jestli je tam i DNS domény, ověří v administraci.
- [x] **Google:** Máte Google Firmu (profil na Mapách) a Google Search Console? Kdo k nim má přístup?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: kdo má přístup ke Google Mapám, neví.
  > Odkaz na Google Firmu výše u Sociálních sítí. Rozhodnuto s vývojářem (2026‑10‑22): vývojář požádá na Googlu o správu profilu, Provozovatel ji schválí z e‑mailu. Search Console založí vývojář.
- [x] **Reklama:** Platíte nebo plánujete reklamu (Google Ads, Facebook/Instagram)? Pokud ano, bude web potřebovat měřicí kódy a souhlas s cookies.
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: ano, podrobnosti neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): neřešit, dokud ji Provozovatel nepřinese. Do té doby žádné měřicí kódy ani cookie lišta.

## Podklady

- [x] **Logo ve vektoru:** Máme logo jen v rozlišení 200×200 px. Máte ho ve větším rozlišení nebo ve vektoru (SVG, AI, PDF, EPS)? Případně kdo ho tvořil?
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: neví.
  > Rozhodnuto s vývojářem (2026‑10‑22): zůstává logo 200×200 px, větší web nepotřebuje.
- [ ] **Fotky:** Můžeme udělat nové fotky dílny, týmu a práce? Staré fotky na webu jsou z ledna 2022.
  > Z `docs/podklady-od-klienta/odpovedi-3.md`: ano, domluvit konkrétní termín.
  > Do galerie O nás zatím fotky z galerie starého webu (`wp-content/themes/pneukarnik/assets/img/galerie/`). Termín focení domluvit.
- [x] **Recenze:** Smíme na webu zobrazit hodnocení z Google?
  > Z `docs/podklady-od-klienta/odpovedi-1.md`: ano, hodnocení z Google smíme zobrazit.
