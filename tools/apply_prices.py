"""usage: apply_prices.py vgl.json OUTDIR   (vgl.json = {key: vgl} from eu_prices.js, or {key: {"vgl": ...}})
  -> OUTDIR/<SKU>.json = {"cards": {"NN": {"vgl": {...}}}} (merge update per package)"""
import json, sys, os
v = json.load(open(sys.argv[1])); out = sys.argv[2]; os.makedirs(out, exist_ok=True)
pk = {}
for key, r in v.items():
    sku, k = key.rsplit(".", 1)
    vg = dict(r["vgl"] if "vgl" in r else r)
    for f in ("m", "lo", "hi", "eu", "imp", "kon", "ab", "vs", "bs"):  # nested updates merge, so clear an old median when nothing was found
        if f not in vg: vg[f] = {"__delete__": True}
    pk.setdefault(sku, {})[k] = {"vgl": vg}
for sku, cards in pk.items():
    json.dump({"cards": cards}, open(os.path.join(out, sku + ".json"), "w"), ensure_ascii=False)
print(len(v), "Karten in", len(pk), "Paketen")
