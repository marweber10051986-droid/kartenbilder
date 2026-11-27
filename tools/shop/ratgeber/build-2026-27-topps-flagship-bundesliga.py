import json, html
from itertools import groupby

cl = {int(k): v for k, v in json.load(open('2026-27-topps-flagship-bundesliga.json')).items()}
E = html.escape
SET = '2026-27 Topps Flagship Bundesliga'

GROUPS = [
    ('bundesliga', 'Bundesliga', 1, 144, 'Die 18 Klubs der Bundesliga, sortiert nach Verein. RC steht für Rookie Card.'),
    ('future-stars', 'Future Stars', 145, 159, 'Junge Spieler, denen Topps den Durchbruch zutraut.'),
    ('zweite-bundesliga', '2. Bundesliga', 160, 195, 'Je zwei Spieler aus allen 18 Klubs der 2. Bundesliga.'),
    ('league-leaders', 'League Leaders', 196, 200, 'Die Besten der Vorsaison.'),
]

def row(nr):
    name, team, rc = cl[nr]
    r = ' <span class="rk-cl-rc">RC</span>' if rc else ''
    return (f'<tr><td class="rk-cl-nr">#{nr}</td><td class="rk-cl-sp">{E(name)}{r}</td>'
            f'<td class="rk-cl-tm">{E(team)}</td><td class="rk-cl-shop" data-nr="{nr}" data-k=""></td></tr>')

def table(a, b):
    return ('<div class="rk-cl-scroll"><table><thead><tr><th>Nr.</th><th>Karte</th><th>Verein</th><th>Im Shop</th></tr></thead><tbody>'
            + ''.join(row(n) for n in range(a, b + 1)) + '</tbody></table></div>')

rcs = [n for n in range(1, 201) if cl[n][2]]

FORMATS = [
    ('Jumbo Box', '8 Packs mit je 25 Karten, dazu 1 Black Edge Pack mit 3 Karten', '3 Autogramme oder Relics pro Box'),
    ('Display Box', '28 Packs mit je 10 Karten', ''),
    ('Power Pack Display', '14 Packs mit je 15 Karten', '14 Limited Edition Inserts, 56 weitere Inserts'),
    ('Value Blaster', '6 Packs mit je 10 Karten', '4 Green Voltage Parallels, 2 Vision Inserts'),
    ('Mega Tin', '4 Packs mit je 10 Karten', '2 Purple Voltage Parallels, 3 On Fire Inserts, 1 Chrome Icons Insert'),
    ('Eco Pack', '3 Packs mit je 10 Karten', '1 Blue Voltage Parallel, 2 Limited Edition Inserts'),
    ('Starter Pack', 'Sammelmappe, Guide und 1 Pack mit 10 Karten', '1 Golden Spektrum Karte'),
]
UNNUM = [('Blue Voltage', 'nur Eco Pack'), ('Green Voltage', 'nur Value Blaster'), ('Purple Voltage', 'nur Mega Tin')]
HOLO = [('Pink Holo', '/399'), ('Yellow Holo', '/299'), ('Purple Holo', '/199'), ('Blue Holo', '/150'), ('Green Holo', '/99'),
        ('Black & White Holo', '/75'), ('Gold Holo', '/50'), ('Orange Holo', '/25'), ('Black Holo', '/10'), ('Red Holo', '/5'), ('Platinum Holo', '1/1')]
JUMBO = [('Yellow', '/299'), ('Purple', '/199'), ('Green', '/99'), ('Black & White', '/75'), ('Gold', '/50'), ('Orange', '/25'),
         ('Black', '/10'), ('Red', '/5'), ('FoilFractor', '1/1'), ('Printing Plates', '1/1')]
INSERTS = [('All Kings', 'AK', 10, ''), ('Beast Mode', 'BM', 25, ''), ('Black Edge', 'BE', 50, 'nur Jumbo Box'), ('Chrome Icons', 'CI', 20, ''),
           ('Diamond Rookies', 'DR', 5, ''), ('Global Gladiators', 'GG', 25, ''), ('Golden Spektrum', 'GS', 20, ''), ('Home Pitch', 'HP', 20, ''),
           ('Kinetic', 'KT', 25, ''), ('Limited Edition', 'LE', 20, 'Eco Pack und Power Pack'), ('Matchwinner', 'MW', 40, ''),
           ('On Fire', 'OF', 20, 'nur Mega Tin'), ('Prime Time', 'PT', 27, ''), ('Stars of the Bundesliga', 'SB', 10, ''), ('Tifo', 'TF', 3, ''),
           ('Ultra Beast Mode', 'UBM', 10, ''), ('Vision', 'VS', 10, 'nur Value Blaster')]

FAQ = [
    ('Wie viele Karten hat 2026-27 Topps Flagship Bundesliga?',
     'Das Base Set hat 200 Karten: #1 bis #144 sind die 18 Bundesliga Klubs, #145 bis #159 Future Stars, #160 bis #195 die 2. Bundesliga und #196 bis #200 League Leaders.'),
    ('Wann ist Topps Flagship Bundesliga 2026/27 erschienen?',
     'Das Set kam am 12. November 2026 in den Handel. Es gibt Jumbo Boxen, Display Boxen, Value Blaster, Mega Tins, Eco Packs, Power Packs und ein Starter Pack mit Sammelmappe.'),
    ('Wie viele Rookie Cards sind im Base Set?',
     f'Im Base Set tragen {len(rcs)} Karten das RC Logo, zum Beispiel Mathis Albert #23 und Kauã Prates #26 von Borussia Dortmund, Kennet Eichhorn #89 von Bayer 04 Leverkusen oder Kofi Amoako #61 vom Hamburger SV.'),
    ('Welche Parallels gibt es nur in der Jumbo Box?',
     'Die Jumbo Box hat eigene nummerierte Parallels von Yellow /299 bis Red /5, dazu FoilFractor 1/1 und Printing Plates. Außerdem gibt es die Black Edge Inserts nur dort.'),
    ('Sind die Karten in eurem Shop echt gescannt?',
     'Ja. Jede Karte ist einzeln von vorne und hinten gescannt. Du bekommst genau die Karte, die du auf den Bildern siehst.'),
]

STYLE = open('2026-topps-flagship-nfl-checkliste.html').read().split('<style>')[1].split('</style>')[0]

clubs = []
for team, grp in groupby(range(1, 145), key=lambda n: cl[n][1]):
    g = list(grp)
    clubs.append((team, g[0], g[-1]))

intro = f'''<!-- wp:html -->
<style>{STYLE}</style>
<div class="rk-cl" data-rkcl="topps-flagship-bundesliga|fussball|2026">
<p><strong>2026-27 Topps Flagship Bundesliga</strong> ist das neue Topps Hauptset für die Bundesliga und die 2. Bundesliga. Hier findest du die komplette Checkliste mit allen 200 Karten, jeder Nummer und jedem Verein, dazu alle Parallels, Inserts und Box Formate. In der Spalte <strong>Im Shop</strong> siehst du sofort, welche Karte wir gerade da haben und was sie kostet.</p>

<div class="rk-cl-fakten">
<div><b>200</b><span>Karten im Base Set</span></div>
<div><b>{len(rcs)}</b><span>Rookie Cards im Base Set</span></div>
<div><b>12.11.</b><span>2026 erschienen</span></div>
<div><b><span class="rk-cl-count"></span></b><span>Nummern gerade bei uns auf Lager</span></div>
</div>

<h2>Das Wichtigste in Kürze</h2>
<p>Mit Flagship bringt Topps zur Saison 2026/27 ein klassisches Sammelset für die Bundesliga. Damit tritt Flagship die Nachfolge von Match Attax Bundesliga an. Im Set sind alle 18 Klubs der Bundesliga mit 5 bis 12 Karten pro Verein, dazu 36 Karten aus der 2. Bundesliga. Die Jumbo Box bringt 3 Autogramme oder Relics und ein eigenes Black Edge Pack.</p>

<h2>Aufbau des Sets</h2>
<div class="rk-cl-scroll"><table><thead><tr><th>Nummern</th><th>Teil</th><th>Karten</th></tr></thead><tbody>
<tr><td>1 bis 144</td><td><a href="#bundesliga">Bundesliga</a></td><td>144</td></tr>
<tr><td>145 bis 159</td><td><a href="#future-stars">Future Stars</a></td><td>15</td></tr>
<tr><td>160 bis 195</td><td><a href="#zweite-bundesliga">2. Bundesliga</a></td><td>36</td></tr>
<tr><td>196 bis 200</td><td><a href="#league-leaders">League Leaders</a></td><td>5</td></tr>
</tbody></table></div>

<h2>Rookies der Topklubs</h2>
<p>Borussia Dortmund hat gleich fünf Rookie Cards im Set: <strong>Mathis Albert #23</strong>, <strong>Kauã Prates #26</strong>, <strong>Justin Lerma #28</strong>, <strong>Luca Reggiani #29</strong> und <strong>Samuele Inácio #30</strong>. Beim FC Bayern sind es <strong>Cassiano Kiala #114</strong> und <strong>Maycon Cardozo #116</strong>, bei Bayer 04 Leverkusen <strong>Kennet Eichhorn #89</strong> und <strong>Montrell Culbreath #90</strong>, bei RB Leipzig <strong>Ayodele Thomas #78</strong>, <strong>Samba Konaté #79</strong> und <strong>Abdoul Koné #85</strong>. Dazu kommen <strong>Otto Stange #55</strong> und <strong>Kofi Amoako #61</strong> vom Hamburger SV.</p>
<p>Bei den Future Stars stecken unter anderem <strong>Lennart Karl #146</strong>, <strong>Yan Diomande #147</strong> und <strong>Saïd El Mala #149</strong>. Die League Leaders zeigen Harry Kane #197, Michael Olise #198, Gregor Kobel #199 und Yan Diomande #200.</p>
[rk_karten serie="topps-flagship-bundesliga" kat="fussball" sort="preis" n="4" titel="Die teuersten Flagship Bundesliga Karten auf Lager"]

<h2>Box Formate</h2>
<div class="rk-cl-scroll"><table><thead><tr><th>Format</th><th>Inhalt</th><th>Garantiert</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td><td>{E(c) or "nicht angegeben"}</td></tr>' for a, b, c in FORMATS)}
</tbody></table></div>

<h2>Alle Parallels</h2>
<p>Jede Base Karte gibt es in mehreren Farben. Die Voltage Parallels sind nicht nummeriert und stecken nur in bestimmten Formaten, die Holo Parallels sind auf die angegebene Stückzahl begrenzt. Was eine Parallel ist, erklären wir im Ratgeber <a href="/ratgeber/parallels-refractors-numbered-karten/">Parallels, Refractors und nummerierte Karten</a>.</p>
<h3>Ohne Nummerierung</h3>
<div class="rk-cl-scroll"><table><thead><tr><th>Parallel</th><th>Wo zu finden</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td></tr>' for a, b in UNNUM)}
</tbody></table></div>
<h3>Holo Parallels, nummeriert</h3>
<div class="rk-cl-scroll"><table><thead><tr><th>Parallel</th><th>Auflage</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td></tr>' for a, b in HOLO)}
</tbody></table></div>
<h3>Nur in der Jumbo Box</h3>
<div class="rk-cl-scroll"><table><thead><tr><th>Parallel</th><th>Auflage</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td></tr>' for a, b in JUMBO)}
</tbody></table></div>

<h2>Inserts</h2>
<p>18 Insert Sets mit eigenem Kürzel vor der Nummer, zum Beispiel SB-07 für Stars of the Bundesliga. Inserts aus dem Set findest du bei uns in der Kategorie <a href="/produktkategorie/sportkarten/fussball/">Fußball Karten</a>.</p>
<div class="rk-cl-scroll"><table><thead><tr><th>Insert</th><th>Kürzel</th><th>Karten</th><th>Nur in</th></tr></thead><tbody>
{''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td><td>{c}</td><td>{E(d)}</td></tr>' for a, b, c, d in INSERTS)}
<tr><td>Golden Spektrum Redemptions</td><td>GSR</td><td>20</td><td>je 1/1</td></tr>
</tbody></table></div>
[rk_karten serie="topps-flagship-bundesliga" kat="fussball" suche="Stars of the Bundesliga|Chrome Icons|Global Gladiators|Matchwinner|Beast Mode" sort="zufall" n="4" titel="Flagship Bundesliga Inserts auf Lager"]

<h2>Autogramme und Relics</h2>
<p>Base Autographs gibt es von 149 Karten, dazu Legend Autographs, Class of 75-76, Black Edge, Matchwinner, On Fire, Stars of the Bundesliga und Vision Autographs. Base Relics mit 39 Karten gibt es nur in der Jumbo Box.</p>

<h2>Komplette Checkliste</h2>
<div class="rk-cl-such-feld"><input type="search" id="rk-cl-q" placeholder="Spieler, Verein oder Nummer suchen" aria-label="Checkliste durchsuchen"><label><input type="checkbox" id="rk-cl-da"> nur Karten auf Lager</label></div>
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
<div class="rk-cl-cta"><strong>Deine Karte ist gerade nicht da?</strong> Gib uns einen <a href="/suchauftrag/">Suchauftrag</a>, dann melden wir uns, sobald wir sie haben. Alle Bundesliga Karten, die wir auf Lager haben, findest du auch unter <a href="/produktkategorie/sportkarten/fussball/">Fußball Karten</a>.</div>
{faq_html}
<p style="font-size:12px;opacity:.55">Quelle der Checkliste: Vorab Angaben von Topps, zusammengestellt von Rookie Business. Topps kann Details noch ändern. Stand: November 2026.</p>
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
open('2026-27-topps-flagship-bundesliga-checkliste.html', 'w').write(page)
print(len(page), len(rcs))
