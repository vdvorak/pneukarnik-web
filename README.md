# pneukarnik-web

Nový web [pneukarnik.cz](https://pneukarnik.cz) (Pneuservis a autoservis Jan Kárník): vlastní WordPress šablona a rezervační plugin.

- Zadání: `docs/zadani.md`, spec a tickety v GitHub Issues (#1).
- Pojmy: `CONTEXT.md`. Rozhodnutí: `docs/adr/`.
- Revize převzatého pluginu: `docs/revize-pluginu.md`.

## Struktura

```
wp-content/themes/pneukarnik/           šablona (ADR 0001)
wp-content/plugins/pneukarnik-booking/  rezervace: model, REST API, administrace
tests/phpunit/                          testy pluginu přes REST (WP test suite)
tests/e2e/                              kouřové testy v prohlížeči (Playwright)
docker/                                 lokální prostředí
```

## Lokální vývoj

Potřebuješ Docker s Compose v2, `make` a Node.js ≥ 20 (pro Playwright).

```sh
make up      # spustí WordPress se šablonou a pluginem
```

- Web: http://localhost:8080
- Administrace: http://localhost:8080/wp-admin (`admin` / `admin`)
- Jiný port: `make up WP_PORT=8081` (stejný port pak předávej i testům)

Šablona a plugin jsou do kontejneru připojené přímo z repa, změny se projeví hned.
`make up` jde spustit opakovaně, nastavení jen dorovná.

Další příkazy jsou v `make help`:

| Příkaz | Co dělá |
|---|---|
| `make down` | zastaví kontejnery, data zůstanou |
| `make reset` | smaže i data lokálního webu |
| `make wp ARGS="plugin list"` | WP‑CLI nad lokálním webem |
| `make logs` | logy WordPressu |

## Testy a lint

```sh
make check   # lint + všechny testy
```

| Příkaz | Co dělá |
|---|---|
| `make test-php` | PHPUnit nad REST API pluginu. Jeden soubor: `make test-php ARGS="--filter ClockTest"` |
| `make test-e2e` | Playwright proti lokálnímu webu (sám spustí `make up`) |
| `make test` | obojí |
| `make lint` | PHPCS (WordPress Coding Standards), PHPStan level 5, kontrola typů TypeScriptu |
| `make fix` | automatické opravy formátování (PHPCBF) |

PHP nástroje běží v kontejneru `tools` (stejné PHP 8.3 a WordPress 7.1 jako web). Testy PHPUnit používají samostatnou databázi `wordpress_test`.
Playwright běží na hostiteli, prohlížeč Chromium si při prvním spuštění stáhne sám.

### Jak psát testy

- **Hlavní vstup je REST API pluginu.** Testy dědí z `Pneukarnik_REST_Test_Case` a volají `$this->rest( 'GET', '/slots', [...] )`. Netestují interní třídy, aby refaktoring vnitřku nic neshodil.
- **Čas** se nastavuje přes `Pneukarnik_Clock::freeze( '2027-03-01 08:30' )`. Řetězec je místní čas v Europe/Prague. Nikdy nespoléhej na skutečné datum. Po každém testu se hodiny vrací automaticky.
- **Prohlížeč** jen na kouřové testy: vykreslení stránek, jedna celá rezervace, přesměrování.

E‑maily lokálně neodcházejí (v kontejneru není poštovní server). V testech se zachytávají přes filtr `pre_wp_mail`.
