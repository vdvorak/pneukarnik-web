# FPDF 1.86

Knihovna pro generování PDF (denní přehled Rezervací), zdroj http://www.fpdf.org/.
Licence: permisivní, bez omezení použití, i v komerčních aplikacích a s úpravami.
Přibalená beze změn, mimo kontrolu lintu.

## Font pro češtinu

`font/DejaVuSansCondensed.php|.z` a `font/DejaVuSansCondensed-Bold.php|.z` jsou DejaVu Sans
Condensed 2.37 převedené nástrojem `makefont` z FPDF 1.86 do kódování cp1250 (podmnožina
znaků cp1250, vložená do PDF). Text se před výstupem převádí z UTF‑8 do cp1250.

Znovu vygenerovat (makefont je v balíku FPDF 1.86 z fpdf.org, font např. z balíku fonts-dejavu-core):

```sh
php makefont/makefont.php DejaVuSansCondensed.ttf cp1250
php makefont/makefont.php DejaVuSansCondensed-Bold.ttf cp1250
```

Licence fontu: `font/DejaVu-LICENSE.txt` (Bitstream Vera, změny DejaVu jsou public domain).
