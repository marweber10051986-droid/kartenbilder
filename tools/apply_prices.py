"""usage: apply_prices.py vgl.json OUTDIR  -> OUTDIR/<SKU>.json = {"cards": {"NN": {"vgl": {...}}}} (merge update per package)"""
import json, sys, os
v = json.load(open(sys.argv[1])); out = sys.argv[2]; os.makedirs(out, exist_ok=True)
pk = {}
for key, r in v.items():
    sku, k = key.rsplit(".", 1)
    pk.setdefault(sku, {})[k] = {"vgl": r["vgl"]}
for sku, cards in pk.items():
    json.dump({"cards": cards}, open(os.path.join(out, sku + ".json"), "w"), ensure_ascii=False)
print(len(v), "Karten in", len(pk), "Paketen")
