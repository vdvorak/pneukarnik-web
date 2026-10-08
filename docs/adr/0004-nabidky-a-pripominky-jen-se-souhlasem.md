# Nabídky a připomínky jen se souhlasem, každý druh zvlášť

Nahrazuje ADR 0003. Připomínka přezutí a Rozesílky chodí jen Zákazníkovi, který s nimi výslovně souhlasil, každý druh zvlášť: dvě nezaškrtnutá políčka v online rezervaci („Připomenout mi e‑mailem přezutí před každou sezónou.“, „Posílat mi e‑mailem vaše akce.“), u Rezervace zadané Provozovatelem stejná dvě políčka na stránce z odkazu v potvrzení. Soft opt‑in podle § 7 odst. 3 zákona 480/2004 Sb. jsme opustili. Jedno políčko „Neposílat“ spojovalo tři různé účely (službu, reklamu a prosbu o hodnocení), takže potřebovalo výčet toho, co chodí, a pravidlo, že odmítnutí platí jen do další online rezervace. Zákazníkům se to nedalo vysvětlit jednou větou a byl to přesně ten vzorec, který lidé u e‑mailů nesnášejí.

Žádost o hodnocení e‑mailem zrušena. ÚOOÚ ji bere jako obchodní sdělení, takže by bez soft opt‑inu potřebovala vlastní souhlas, a třetí políčko kvůli jednomu e‑mailu nestojí za to. O hodnocení Provozovatel požádá osobně (QR kód na pultu nebo na účtence).

## Consequences

- Souhlas platí hned, ne až po návštěvě. Nezaškrtnuté políčko nic nemění, dřívější souhlas ani odvolání. Zaškrtnuté po odvolání je nový souhlas.
- Připomínku přezutí dostane menšina Zákazníků než se soft opt‑inem, Akce ještě menší. Připomínka je užitečná, takže u ní čekáme vyšší podíl.
- Zákazníci převedení ze starého webu: souhlas „informace o slevách“ dál platí pro Akce, jinak nedostanou nic, dokud nesouhlasí.
- Data ze soft opt‑inu (nároky, odmítnutí ve formuláři, zapamatované návštěvy, Žádosti o hodnocení) migrace 1.17 smaže. Odvolání odkazem zůstanou.
