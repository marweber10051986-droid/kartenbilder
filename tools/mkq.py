import json, glob, re, os, sys
H = os.path.dirname(os.path.abspath(__file__))
# usage: mkq.py DB_DIR [LIMIT] [OUTDIR]   picks active cards without vgl first, then the oldest vgl
DB = sys.argv[1] if len(sys.argv) > 1 else os.path.join(H, "db/pakete")
LIMIT = int(sys.argv[2]) if len(sys.argv) > 2 else 0
OUT = sys.argv[3] if len(sys.argv) > 3 else H

def lang_of(c):
    t = (c.get("ebay", {}).get("t") or "") + " " + (c.get("title") or "")
    for k in ("Koreanisch", "Japanisch", "Englisch"):
        if k in t: return k
    return "Deutsch"

def query(c, kind):
    if kind == "tcg":
        name = c["name"]; num = c.get("number") or ""
        m = re.search(r"[(](.+?)[)]", name)
        lang = lang_of(c)
        if lang in ("Koreanisch", "Japanisch") and m:
            q = f"{m.group(1)} {num} " + ("korean" if lang == "Koreanisch" else "japanese")
        else:
            q = f"{re.sub(r' [(].*?[)]', '', name)} {num}"
        return q, lang
    yr = (c.get("year") or "")[:4]
    st = c.get("set") or ""
    brand = "Panini" if "Panini" in st else ("Fleer" if "Fleer" in st else "Topps")
    q = f"{c['name']} {c['number']}"
    if c.get("auto"): q += " auto"
    s = re.search(r"/\d+", c.get("serial") or "")
    if s: q += " " + s.group(0)
    return f"{yr} {brand} {q}", "Englisch"

rows = []
for f in sorted(glob.glob(DB + "/*.json")):
    d = json.load(open(f)); kind = d.get("kind")
    for k, c in sorted(d["cards"].items()):
        e = c.get("ebay") or {}
        if e.get("st") != "A": continue
        q, lang = query(c, kind)
        rows.append({"key": f'{d["sku"]}.{k}', "id": e["id"], "kind": kind, "q": q, "lang": lang,
                     "name": c["name"], "number": str(c.get("number") or ""), "parallel": c.get("parallel") or "",
                     "serial": c.get("serial") or "", "auto": bool(c.get("auto")), "pr": e.get("pr"),
                     "title": e.get("t") or c.get("title") or "", "_d": (c.get("vgl") or {}).get("d", "")})
rows.sort(key=lambda r: r["_d"])
if LIMIT:
    keep = []
    for r in rows:
        if len(set(x["q"] for x in keep) | {r["q"]}) > LIMIT: break
        keep.append(r)
    qs0 = set(x["q"] for x in keep); keep = [r for r in rows if r["q"] in qs0]; rows = keep
json.dump(rows, open(os.path.join(OUT, "cards.json"), "w"), ensure_ascii=False)
qs = sorted(set(r["q"] for r in rows))
json.dump(qs, open(os.path.join(OUT, "queries.json"), "w"), ensure_ascii=False)
print(len(rows), "aktive Karten,", len(qs), "Suchen")
