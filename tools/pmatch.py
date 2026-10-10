"""Match eBay sold listings to cards. usage: pmatch.py cards.json raw.json today(YYYY-MM-DD) > vgl.json
raw.json = {query: ["10. Okt. 2026|12,50|X|title", ...]}"""
import json, re, sys, statistics, datetime as dt
cards = json.load(open(sys.argv[1])); raw = json.load(open(sys.argv[2]))
today = dt.date.fromisoformat(sys.argv[3]) if len(sys.argv) > 3 else dt.date.today()
MON = {"jan": 1, "feb": 2, "mär": 3, "mar": 3, "apr": 4, "mai": 5, "may": 5, "jun": 6, "jul": 7, "aug": 8, "sep": 9, "okt": 10, "oct": 10, "nov": 11, "dez": 12, "dec": 12}
def pdate(s):
    m = re.match(r"(\d+)\.\s*(\w+)\.?\s*(\d{4})", s.strip())
    if not m: return None
    mo = MON.get(m.group(2)[:3].lower())
    return dt.date(int(m.group(3)), mo, int(m.group(1))) if mo else None
BAD = r"\b(psa|bgs|sgc|cgc|beckett|graded|gem\s?mint|lot|lots|bundle|konvolut|sammlung|choose|pick|wähle|auswahl|you pick|complete set|team set|proxy|custom|reprint|digital|sealed|box|booster|display|pack)\b"
PARWORDS = r"\b(refractor|prizm|foil|holo|parallel|gold|silver|rainbow|red|blue|green|purple|orange|pink|black|white|sapphire|xfractor|wave|mojo|shimmer|crackle|lava|ice|cracked|sp|ssp|variation|var|/\d+|numbered|auto|autograph|signed|patch|relic|chrome|mosaic|optic|velocity|disco|hyper|neon|camo|tie.?dye|logofractor|aqua|teal|yellow|bronze|platinum|vintage stock|independence day|xtra points|press proof|holo)\b"
STOP = {"the", "of", "and", "foil", "parallel", "rookie", "rookies", "rc", "base", "[basis]", "variation"}

def norm(s): return re.sub(r"\s+", " ", s.lower())

def match(card, title):
    t = norm(title)
    if re.search(BAD, t): return False
    num = card["number"].lower()
    if card["kind"] == "tcg":
        a, _, b = num.partition("/")
        if b:
            if not re.search(rf"(?<!\d)0*{int(a) if a.isdigit() else re.escape(a)}\s*/\s*0*{int(b) if b.isdigit() else re.escape(b)}(?!\d)", t): return False
        elif not re.search(rf"(?<![a-z0-9]){re.escape(num)}(?![a-z0-9])", t.replace(" ", "")) and num not in t: return False
        lang = card["lang"]
        ko, jp = re.search(r"korean|koreanisch|\bkor\b|korea", t), re.search(r"japan|japanisch|\bjap\b|\bjpn\b|\bjp\b", t)
        en = re.search(r"\benglish\b|\benglisch\b|\beng\b|\ben\b", t)
        if lang == "Koreanisch" and not ko: return False
        if lang == "Japanisch" and not jp: return False
        if lang == "Deutsch" and (ko or jp or en): return False
        nm = re.sub(r" [(].*?[)]", "", card["name"]).lower()
        alt = re.search(r"[(](.+?)[)]", card["name"])
        key = [w for w in re.findall(r"[a-zäöüß]{3,}", nm) if w not in ("ex", "mega")]
        ok = any(w in t for w in key) or (alt and alt.group(1).lower().split()[0] in t)
        return bool(ok)
    # sports
    last = card["name"].lower().replace(".", "").split()[-1]
    if last in ("jr", "ii", "iii", "sr") and len(card["name"].split()) > 1: last = card["name"].lower().replace(".", "").split()[-2]
    if last not in t.replace(".", ""): return False
    if not re.search(rf"(?<![a-z0-9]){re.escape(num)}(?![a-z0-9])", t.replace("#", " ")): return False
    if card["auto"]:
        if not re.search(r"\bauto|autograph|signed|signature", t): return False
    elif re.search(r"\bauto\b|autograph|signed", t): return False
    ser = re.search(r"/(\d+)", card["serial"])
    if ser:
        if not re.search(rf"/\s*{ser.group(1)}\b", t): return False
    elif re.search(r"/\s*\d{1,4}\b", t) and not re.search(r"\b\d{1,3}/\d{1,3}\b.*\b(tcg|pokemon)", t): return False
    par = card["parallel"].strip()
    if par in ("", "[Basis]", "Base"):
        tt = re.sub(re.escape(last), "", t)
        for w in ("chrome", "optic", "mosaic", "prizm") if any(x in card["title"].lower() for x in ("chrome", "optic", "mosaic", "prizm")) else ():
            tt = tt.replace(w, "")
        if re.search(PARWORDS, tt): return False
    else:
        words = [w for w in re.findall(r"[a-z0-9&]+", par.lower()) if w not in STOP and len(w) > 1]
        if words and not all(w in t for w in words): return False
    return True

out = {}
for c in cards:
    lines = raw.get(c["q"])
    if lines is None: continue
    hits = []
    for l in lines:
        if l.startswith("ERR"): continue
        d, p, bo, ti = (l.split("|", 3) + ["", "", "", ""])[:4]
        dd = pdate(d)
        if not dd or (today - dd).days > 90 or p == "?": continue
        if not match(c, ti): continue
        v = float(p.replace(".", "").replace(",", "."))
        if bo == "BO": v *= 0.85
        hits.append((dd, v, ti))
    hits.sort(key=lambda h: h[0], reverse=True); hits = hits[:10]
    if len(hits) >= 3:
        med = statistics.median(h[1] for h in hits)
        hits = [h for h in hits if h[1] <= 3 * med]
    vals = [h[1] for h in hits]
    rec = {"n": len(vals), "d": today.isoformat(), "q": c["q"]}
    if vals: rec.update(m=round(statistics.median(vals), 2), lo=round(min(vals), 2), hi=round(max(vals), 2))
    out[c["key"]] = {"vgl": rec, "_t": [h[2][:90] for h in hits[:3]]}
json.dump(out, sys.stdout, ensure_ascii=False)
