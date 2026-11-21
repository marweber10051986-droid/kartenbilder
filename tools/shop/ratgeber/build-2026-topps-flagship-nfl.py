import json, re, html, unicodedata

cl = {int(k): v for k, v in json.load(open('/home/claude/cl/cl.json')).items()}

def k(name, nr):
    if 271 <= nr <= 300:
        return ''
    n = re.sub(r' (Jr\.|Sr\.|II|III|IV)$', '', name).split(' ')[-1]
    n = unicodedata.normalize('NFKD', n).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z]', '', n)

E = html.escape

GROUPS = [
    ('veteranen', 'Veteranen', 1, 260, 'Die Stars der Liga, sortiert nach Teams.'),
    ('future-stars', 'Future Stars', 261, 270, 'Junge Spieler aus dem Vorjahr, denen Topps den Durchbruch zutraut.'),
    ('team-cards', 'Team Cards', 271, 280, ''),
    ('league-leaders', 'League Leaders', 281, 290, 'Die Bestwerte der Saison, je drei Spieler auf einer Karte.'),
    ('combo-cards', 'Combo Cards', 291, 300, ''),
    ('rookies', 'Rookie Cards (RC)', 301, 400, 'Die ersten Flagship Rookies des Draft Jahrgangs 2026.'),
]

def table(a, b):
    rows = []
    for nr in range(a, b + 1):
        name, team = cl[nr]
        rc = ' <span class="rk-cl-rc">RC</span>' if nr >= 301 else ''
        rows.append(
            f'<tr><td class="rk-cl-nr">#{nr}</td><td class="rk-cl-sp">{E(name)}{rc}</td>'
            f'<td class="rk-cl-tm">{E(team)}</td><td class="rk-cl-shop" data-nr="{nr}" data-k="{k(name, nr)}"></td></tr>')
    return ('<div class="rk-cl-scroll"><table><thead><tr><th>Nr.</th><th>Karte</th><th>Team</th><th>Im Shop</th></tr></thead><tbody>'
            + ''.join(rows) + '</tbody></table></div>')

UNNUM = [
    ('Holo Foil', 'alle Formate'), ('Rainbow Foil', 'alle Formate'), ('Aqua Holo Foil', ''), ('Aqua Rainbow Foil', ''),
    ('Sandglitter', 'nur Jumbo Box'), ('Diamante Foil', 'nur Hanger Box'), ('Pink Diamante Foil', 'nur Hanger Box'),
    ('Football', 'nur Value Box'),
]
NUM = [
    ('Gold', '/2026', ''), ('Pink Holo Foil', '/1200', ''), ('Yellow Holo Foil', '/399', ''), ('Purple Holo Foil', '/250', ''),
    ('Blue Holo Foil', '/150', ''), ('Green Holo Foil', '/99', ''), ('Green Rainbow Foil', '/99', 'Hobby'),
    ('Green Diamante Foil', '/99', 'Hanger'), ('Football Green', '/99', 'Value Box'), ('Independence Day', '/76', ''),
    ('Black', '/70', ''), ('Gold Holo Foil', '/50', ''), ('Gold Rainbow Foil', '/50', 'Hobby'), ('Gold Diamante', '/50', 'Hanger'),
    ('Football Gold', '/50', 'Value Box'), ('Sandglitter Gold', '/50', 'Jumbo'), ('Canvas', '/50', ''), ('Camo', '/25', ''),
    ('Orange Holo Foil', '/25', ''), ('Orange Rainbow Foil', '/25', 'Hobby'), ('Orange Diamante Foil', '/25', 'Hanger'),
    ('Football Orange', '/25', 'Value Box'), ('Sandglitter Orange', '/25', 'Jumbo'), ('Black Holo Foil', '/10', ''),
    ('Black Rainbow Foil', '/10', 'Hobby'), ('Black Diamante Foil', '/10', 'Hanger'), ('Football Black', '/10', 'Value Box'),
    ('Sandglitter Black', '/10', 'Jumbo'), ('Red Holo Foil', '/5', ''), ('Red Rainbow Foil', '/5', 'Hobby'),
    ('Red Diamante Foil', '/5', 'Hanger'), ('Football Red', '/5', 'Value Box'), ('Sandglitter Red', '/5', 'Jumbo'),
    ('Rose Gold Holo Foil', '1/1', ''), ('Foilfractor', '1/1', 'Hobby'), ('Football Rose Gold', '1/1', 'Value Box'),
]

FAQ = [
    ('Wie viele Karten hat 2026 Topps Flagship NFL?',
     'Das Base Set hat 400 Karten. Die Nummern 1 bis 260 sind Veteranen, 261 bis 270 Future Stars, 271 bis 280 Team Cards, 281 bis 290 League Leaders, 291 bis 300 Combo Cards und 301 bis 400 die Rookie Cards.'),
    ('Wann ist 2026 Topps Flagship Football erschienen?',
     'Das Set kam am 21. August 2026 in den Handel. Es ist das erste Topps Flagship Football Set seit 2015.'),
    ('Wer ist auf dem Cover von 2026 Topps Flagship NFL?',
     'Fernando Mendoza von den Las Vegas Raiders, der erste Pick im NFL Draft 2026. Seine Rookie Card ist die Nummer 301.'),
    ('Woran erkenne ich eine Rainbow Foil Parallel?',
     'Halte die Karte schräg ins Licht. Die Base Karte hat einen cremeweißen Rahmen mit feiner, bräunlicher Struktur. Bei der Rainbow Foil wirkt der Rahmen kühler und silbergrau, mit feinen bunten Glitzerpunkten, die je nach Winkel die Farbe wechseln.'),
    ('Sind die Karten in eurem Shop echt gescannt?',
     'Ja. Jede Karte im Shop ist einzeln von vorne und hinten gescannt. Du bekommst genau die Karte, die du auf den Bildern siehst.'),
]

intro = f'''<!-- wp:html -->
<style>
.rk-cl{{--rot:#ff3b30;--box:#141414;--linie:rgba(255,255,255,.12)}}
.rk-cl .rk-cl-fakten{{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin:28px 0}}
.rk-cl .rk-cl-fakten div{{background:var(--box);border:1px solid var(--linie);padding:14px 16px}}
.rk-cl .rk-cl-fakten b{{display:block;font-family:"Barlow Condensed",Impact,sans-serif;font-size:30px;line-height:1;color:#fff}}
.rk-cl .rk-cl-fakten span{{font-size:13px;opacity:.7}}
.rk-cl .rk-cl-scroll{{overflow-x:auto;margin:12px 0 28px}}
.rk-cl table{{width:100%;border-collapse:collapse;font-size:14px}}
.rk-cl th{{text-align:left;font-family:"Barlow Condensed",Impact,sans-serif;text-transform:uppercase;letter-spacing:.04em;font-weight:600;padding:8px 10px;border-bottom:2px solid var(--rot);white-space:nowrap}}
.rk-cl td{{padding:7px 10px;border-bottom:1px solid var(--linie);vertical-align:top}}
.rk-cl .rk-cl-nr{{white-space:nowrap;opacity:.65;width:56px}}
.rk-cl .rk-cl-tm{{opacity:.7}}
.rk-cl .rk-cl-rc{{display:inline-block;font-size:10px;font-weight:700;padding:1px 5px;margin-left:4px;border:1px solid var(--rot);color:var(--rot);vertical-align:middle}}
.rk-cl .rk-cl-shop{{min-width:150px}}
.rk-cl .rk-cl-shop a{{display:block;color:#fff;text-decoration:none;font-size:13px;line-height:1.5}}
.rk-cl .rk-cl-shop a b{{color:var(--rot);font-weight:700}}
.rk-cl .rk-cl-shop a:hover{{text-decoration:underline}}
.rk-cl .rk-cl-shop a.rk-cl-such{{opacity:.45;font-size:12px}}
.rk-cl tr.rk-cl-weg{{display:none}}
.rk-cl .rk-cl-such-feld{{position:sticky;top:0;z-index:2;background:#0a0a0a;padding:10px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center}}
.rk-cl .rk-cl-such-feld input{{flex:1;min-width:200px;background:var(--box);border:1px solid var(--linie);color:#fff;padding:11px 14px;font-size:15px}}
.rk-cl .rk-cl-such-feld label{{font-size:13px;opacity:.8;white-space:nowrap;cursor:pointer}}
.rk-cl .rk-cl-nav{{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 18px}}
.rk-cl .rk-cl-nav a{{border:1px solid var(--linie);padding:5px 10px;font-size:13px;color:#fff;text-decoration:none}}
.rk-cl .rk-cl-nav a:hover{{border-color:var(--rot)}}
.rk-cl .rk-cl-cta{{background:var(--box);border-left:3px solid var(--rot);padding:16px 18px;margin:28px 0}}
.rk-cl .rk-cl-cta a{{color:var(--rot)}}
</style>
<div class="rk-cl" data-rkcl="topps-flagship|nfl|2026">
<p><strong>2026 Topps Flagship NFL</strong> ist das erste Topps Flagship Set im Football seit 2015. Hier findest du die komplette Checkliste mit allen 400 Karten, jeder Nummer und jedem Team, dazu alle Parallels mit Auflage und die wichtigsten Rookies. In der Spalte <strong>Im Shop</strong> siehst du sofort, welche Karte wir gerade da haben und was sie kostet.</p>

<div class="rk-cl-fakten">
<div><b>400</b><span>Karten im Base Set</span></div>
<div><b>100</b><span>Rookie Cards, #301 bis #400</span></div>
<div><b>21.08.</b><span>2026 erschienen</span></div>
<div><b><span class="rk-cl-count"></span></b><span>Nummern gerade bei uns auf Lager</span></div>
</div>

<h2>Das Wichtigste in Kürze</h2>
<p>Topps hat die NFL Lizenz zurück und startet die Saison wieder mit einem Flagship Set. Das Design übernimmt den Look von 2026 Topps Baseball. Auf dem Cover der Hobby Box ist <strong>Fernando Mendoza</strong>, der erste Pick im Draft 2026. In jeder Hobby Box steckt ein Autogramm oder eine Relic Karte, eine Hobby Box hat 20 Packs mit je 12 Karten.</p>

<h2>Aufbau des Sets</h2>
<div class="rk-cl-scroll"><table><thead><tr><th>Nummern</th><th>Teil</th><th>Karten</th></tr></thead><tbody>
<tr><td>1 bis 260</td><td><a href="#veteranen">Veteranen</a></td><td>260</td></tr>
<tr><td>261 bis 270</td><td><a href="#future-stars">Future Stars</a></td><td>10</td></tr>
<tr><td>271 bis 280</td><td><a href="#team-cards">Team Cards</a></td><td>10</td></tr>
<tr><td>281 bis 290</td><td><a href="#league-leaders">League Leaders</a></td><td>10</td></tr>
<tr><td>291 bis 300</td><td><a href="#combo-cards">Combo Cards</a></td><td>10</td></tr>
<tr><td>301 bis 400</td><td><a href="#rookies">Rookie Cards</a></td><td>100</td></tr>
</tbody></table></div>

<h2>Die gefragtesten Rookies</h2>
<p>Die meisten Sammler jagen die ersten Rookie Cards der Top Picks. Allen voran <strong>Fernando Mendoza #301</strong> (Las Vegas Raiders), dazu <strong>Ty Simpson #302</strong> (Los Angeles Rams), <strong>Jeremiyah Love #309</strong> (Arizona Cardinals), <strong>Carnell Tate #318</strong> (Tennessee Titans), <strong>Makai Lemon #319</strong> (Philadelphia Eagles), <strong>Jordyn Tyson #320</strong> (New Orleans Saints) und <strong>KC Concepcion #323</strong> (Cleveland Browns). Bei den Future Stars sind <strong>Ashton Jeanty #262</strong> und <strong>Cam Ward #264</strong> beliebt.</p>
<p>Tipp: Für Team Sammler ist die Base Rookie der günstigste Einstieg. Wer mehr will, greift zur Rainbow Foil oder zu einer nummerierten Parallel.</p>

<h2>Alle Parallels</h2>
<p>Jede Karte des Base Sets gibt es in vielen Farben und Oberflächen. Die ohne Nummer findest du relativ häufig, die nummerierten sind auf die angegebene Stückzahl begrenzt. Einige gibt es nur in bestimmten Boxen. Was eine Parallel ist, erklären wir in unserem Ratgeber <a href="/ratgeber/parallels-refractors-numbered-karten/">Parallels, Refractors und nummerierte Karten</a>.</p>
<h3>Ohne Nummerierung</h3>
<div class="rk-cl-scroll"><table><thead><tr><th>Parallel</th><th>Wo zu finden</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b) or "verschiedene Formate"}</td></tr>' for a, b in UNNUM)}
</tbody></table></div>
<h3>Nummeriert</h3>
<div class="rk-cl-scroll"><table><thead><tr><th>Parallel</th><th>Auflage</th><th>Nur in</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td><td>{E(c)}</td></tr>' for a, b, c in NUM)}
</tbody></table></div>

<h3>Base oder Rainbow Foil? So erkennst du den Unterschied</h3>
<p>Auf Fotos sehen Base und Rainbow Foil fast gleich aus. Schau dir den strukturierten Rahmen an: Bei der <strong>Base Karte</strong> ist er cremeweiß mit feinen, bräunlichen Pünktchen. Bei der <strong>Rainbow Foil</strong> wirkt er kühler und silbergrau, mit feinen bunten Glitzerpunkten. Die ganze Karte sieht metallischer aus. Am besten die Karte leicht kippen, dann siehst du das Funkeln sofort. Bei uns steht die Parallel immer im Titel, du weißt also genau, was du kaufst.</p>

<h2>Varianten</h2>
<p>Neben den Parallels gibt es Varianten mit anderem Bild oder Design. Das gesamte Base Set gibt es als <strong>Golden Mirror</strong> Variante, die deutlich seltener ist. Dazu kommen <strong>1957 Rookie Variations</strong> (50 Karten, die Rookies im klassischen Design von 1957), <strong>Vintage Stock</strong> (/99), <strong>True Photo</strong> (/100), <strong>Player Number</strong> (/20, nur Hobby), <strong>Clear</strong> (/10, nur Hobby) und <strong>Team Color Border</strong>.</p>

<h2>Inserts</h2>
<p>Zu den bekanntesten Inserts gehören <strong>1991 Topps Football</strong> und <strong>1991 Topps Rookies Football</strong> (je 50 Karten im Design von 1991), <strong>NFL Stars</strong> (40 Karten) und <strong>All Kings</strong> (25 Karten, nummeriert /250). Inserts aus dem Set findest du bei uns in der Kategorie <a href="/produktkategorie/sportkarten/nfl/">NFL Karten</a>.</p>

<h2>Komplette Checkliste</h2>
<div class="rk-cl-such-feld"><input type="search" id="rk-cl-q" placeholder="Spieler, Team oder Nummer suchen" aria-label="Checkliste durchsuchen"><label><input type="checkbox" id="rk-cl-da"> nur Karten auf Lager</label></div>
<div class="rk-cl-nav">{''.join(f'<a href="#{a}">{E(b)}</a>' for a, b, *_ in GROUPS)}</div>
'''

body = ''
for slug, title, a, b, txt in GROUPS:
    body += f'<h3 id="{slug}">{E(title)} (#{a} bis #{b})</h3>'
    if txt:
        body += f'<p>{E(txt)}</p>'
    body += table(a, b)

faq_html = '<h2>Häufige Fragen</h2>' + ''.join(f'<h3>{E(q)}</h3><p>{E(a)}</p>' for q, a in FAQ)
ld = {'@context': 'https://schema.org', '@type': 'FAQPage', 'mainEntity': [
    {'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}} for q, a in FAQ]}

outro = f'''
<div class="rk-cl-cta"><strong>Deine Karte ist gerade nicht da?</strong> Gib uns einen <a href="/suchauftrag/">Suchauftrag</a>, dann melden wir uns, sobald wir sie haben. Alle Karten aus 2026 Topps Flagship, die wir auf Lager haben, findest du auch im <a href="/produktkategorie/sportkarten/nfl/">NFL Shop</a>.</div>
{faq_html}
<p style="font-size:12px;opacity:.55">Quelle der Checkliste: offizielle Angaben von Topps, zusammengestellt von Rookie Business. Stand: November 2026.</p>
</div>
<script type="application/ld+json">{json.dumps(ld, ensure_ascii=False)}</script>
<script>
(function(){{var q=document.getElementById('rk-cl-q'),d=document.getElementById('rk-cl-da');if(!q)return;
var rows=[].slice.call(document.querySelectorAll('.rk-cl table tbody tr')).filter(function(r){{return r.querySelector('.rk-cl-shop')}});
function f(){{var s=q.value.toLowerCase().replace('#','').trim(),o=d.checked;rows.forEach(function(r){{var t=r.textContent.toLowerCase(),nr=r.querySelector('.rk-cl-nr').textContent.replace('#','');
var hit=!s||t.indexOf(s)>-1||nr===s;if(o&&!r.querySelector('.rk-cl-da'))hit=false;r.classList.toggle('rk-cl-weg',!hit)}});}}
q.addEventListener('input',f);d.addEventListener('change',f);}})();
</script>
<!-- /wp:html -->'''

page = intro + body + outro
open('/home/claude/cl/page.html', 'w').write(page)
print(len(page))
