"""usage: mkcards.py DB_DIR [LIMIT] > cards.js
Writes `window.CARDS=[...]` for eu_prices.js: active cards, missing or oldest `vgl` first.
LIMIT counts searches (one search can serve several cards). Uses the queries from mkq.py."""
import json, glob, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
DB = sys.argv[1]; LIMIT = int(sys.argv[2]) if len(sys.argv) > 2 else 0
import importlib.util
spec = importlib.util.spec_from_file_location("mkqmod", os.path.join(os.path.dirname(os.path.abspath(__file__)), "mkq.py"))
src = open(spec.origin).read().split("rows = []")[0]           # only the helpers (lang_of, query)
ns = {"__file__": spec.origin, "sys": type("S", (), {"argv": ["x", DB]})}
exec(compile(src.replace("import json, glob, re, os, sys", "import json, glob, re, os"), "mkq", "exec"), ns)
VAR = ("reverse", "shatterfoil", "cosmos", "masterball", "pokeball", "pokéball")
rows = []
for f in sorted(glob.glob(DB + "/*.json")):
    d = json.load(open(f)); kind = d.get("kind")
    for k, c in sorted(d["cards"].items()):
        e = c.get("ebay") or {}
        if e.get("st") != "A": continue
        q, lang = ns["query"](c, kind)
        title = (e.get("t") or c.get("title") or "")
        own = (str(c.get("parallel") or "") + " " + title).lower()
        par = c.get("parallel") or ""
        chrome = 1 if any(x in title.lower() for x in ("chrome", "optic", "mosaic", "prizm")) else 0
        rows.append(([f'{d["sku"]}.{k}', q, "tcg" if kind == "tcg" else "sports", lang, c["name"], str(c.get("number") or ""),
                      "" if kind == "tcg" else ("" if par in ("[Basis]", "Base") else par), c.get("serial") or "",
                      1 if c.get("auto") else 0, chrome, e.get("id", ""), ",".join(v for v in VAR if v in own) if kind == "tcg" else ""],
                     (c.get("vgl") or {}).get("d", "") if "bs" in (c.get("vgl") or {}) else ""))
rows.sort(key=lambda r: r[1])
if LIMIT:
    qs = []
    for r, _ in rows:
        if r[1] not in qs: qs.append(r[1])
        if len(qs) >= LIMIT: break
    rows = [r for r in rows if r[0][1] in qs]
print("window.CARDS=" + json.dumps([r for r, _ in rows], ensure_ascii=False, separators=(",", ":")) + ";window.CARDS.length")
