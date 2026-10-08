# Nabídky a připomínky bez souhlasu, s možností odmítnout

**Nahrazeno ADR 0004.**

Připomínka přezutí, Žádost o hodnocení a Rozesílky chodí Zákazníkovi, který u Provozovatele už byl a při online rezervaci je neodmítl (nezaškrtnuté „Neposílat“ s výčtem, co chodí), ne jen tomu, kdo zaškrtl souhlas. Opírá se to o § 7 odst. 3 zákona 480/2004 Sb. (vlastní zákazník, vlastní podobné služby, možnost odmítnout při zadání e‑mailu a v každém e‑mailu). Opt‑in by k e‑mailům pustil jen menšinu Zákazníků a Žádost o hodnocení, kterou ÚOOÚ bere jako obchodní sdělení, by bez souhlasu stejně potřebovala tenhle režim, takže jsme ho zvolili pro všechny Nabídky a připomínky.

## Consequences

- Platí jen pro skutečné zákazníky: e‑maily začnou chodit až po proběhlém Termínu nezrušené Rezervace. Kdo se jen objednal, nic nedostane.
- Odmítnutí ve formuláři i odhlášení z e‑mailu platí do další online rezervace, ve které Zákazník „Neposílat“ nezaškrtne: měl v ní možnost odmítnout znovu a nevyužil ji. Odhlásit se jde i pak v každém e‑mailu.
- Zákazník zadaný Provozovatelem (telefonicky) odmítnout nemohl, proto se k Nabídkám a připomínkám dostane jen výslovným souhlasem odkazem z potvrzení Rezervace.
- Zákazníci převedení ze starého webu odmítnout nemohli: nedostanou nic, dokud se neobjednají přes nový web. Výjimka: souhlas „informace o slevách“ ze starého webu platí pro Rozesílky.
- Před spuštěním ověřit u právníka.
