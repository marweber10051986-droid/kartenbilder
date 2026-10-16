# Tools für den Kartenbestand (rookie_business)

Werden vom täglichen Morgen Abgleich benutzt. Keine Zugangsdaten, keine Käuferdaten.

* `sync_read.js` liest aktive und verkaufte Angebote aus dem Verkäufer Cockpit (im eingeloggten Chrome, Tab auf ebay.de/robots.txt). Platzhalter `__INDEX__` und `__FULL__` vorher ersetzen.
* `apply_sync.py LINES HEADLINE OUTDIR [META.json]` macht daraus Update Dateien für die App Datenbank.
* `rbhash.py` Hash für die Zuordnung Paket SKU und Titel.
* `mkq.py DB_DIR [LIMIT] [OUTDIR]` baut die Suchbegriffe für die Vergleichspreise (älteste zuerst).
* `mkcards.py DB_DIR [LIMIT] > cards.js` baut `window.CARDS` für `eu_prices.js` (aktive Karten, ohne EU Vergleich oder älteste zuerst).
* `eu_prices.js` holt pro Suchbegriff verkaufte und aktive eBay Angebote mit Versand und Herkunftsland und rechnet: Verkäufe weltweit und in der EU, günstigstes EU Angebot inkl. Versand, Importpreis und Preisvorschlag (`vgl.vs`). Läuft im eingeloggten Chrome Tab, bricht bei einer eBay Prüfseite ab.
* `price_read.js` holt verkaufte eBay Angebote pro Suchbegriff (bricht bei einer eBay Prüfseite ab).
* `pmatch.py cards.json raw.json YYYY-MM-DD` filtert passende Verkäufe und rechnet den Median.
* `apply_prices.py vgl.json OUTDIR` macht daraus Update Dateien (`cards.NN.vgl`), alte Felder ohne neuen Wert werden gelöscht.
* Älter, nur Verkäufe ohne EU Sicht: `price_read.js`, `pmatch.py`, `pmatch.js`.
* `scan1600.py OUTDIR SCAN…` macht aus Flachbett Scans (1200 dpi) die eBay Bilder: Scan bleibt wie er ist, gleicher Maßstab für alle, mit eigener Hintergrundfarbe auf 1600 x 1600 erweitert.
