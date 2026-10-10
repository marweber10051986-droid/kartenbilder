#!/usr/bin/env python3
"""Turn eBay sync lines into ArtifactData update files.
Line format:  SKU.pos st av price_cents item_id sold_qty rev_cents date [views watchers days_left] [| ebay title]
meta.fs keeps the first sync date per item id (id:YYYY-MM-DD), written into ebay.fs.
Usage: apply_sync.py LINES HEADLINE OUTDIR [META_IN.json]
Writes OUTDIR/<SKU>.json (update payload per package) and OUTDIR/meta-sync.json (full meta doc for set).
"""
import json, os, sys, re, datetime
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from rbhash import h

lines_path, head, outdir = sys.argv[1], sys.argv[2], sys.argv[3]
meta = json.load(open(sys.argv[4])) if len(sys.argv) > 4 and os.path.exists(sys.argv[4]) else {}
os.makedirs(outdir, exist_ok=True)
idx = {}
fs = dict(e.split(':', 1) for e in (meta.get('fs') or '').split() if ':' in e)
for e in (meta.get('index') or '').split():
    k, v = e.split(':'); idx[k] = v
pk = {}
stamp = re.search(r'RB (\S+)', head).group(1)
TODAY = stamp[:10]
n = 0
for raw in open(lines_path, encoding='utf-8'):
    raw = raw.strip()
    if not raw or raw.startswith('RB ') or raw.startswith('?'):
        continue
    main, _, title = raw.partition(' | ')
    f = main.split()
    slot, st, av, pr, iid, q, rev, dt = f[:8]
    sku, pos = slot.rsplit('.', 1)
    eb = {'id': iid, 'st': st, 'av': int(av), 'pr': int(pr) / 100, 'q': int(q), 'rev': int(rev) / 100, 'dt': '' if dt == '-' else dt}
    if len(f) >= 11:
        eb['v'] = int(f[8]); eb['w'] = int(f[9])
        if f[10] != '-': eb['tr'] = int(f[10])
    if iid not in fs:  # first time seen: estimate start from the 30 day cycle
        back = max(0, 30 - eb['tr']) if 'tr' in eb else 0
        fs[iid] = (datetime.date.fromisoformat(TODAY) - datetime.timedelta(days=back)).isoformat()
    eb['fs'] = fs[iid]
    if title:
        eb['t'] = title.strip()
        k = h(sku, eb['t'])
        for kk in [kk for kk, vv in idx.items() if vv.split('.')[0] == sku and pos in vv.split('.')[1].split(',')]:
            if kk != k:
                ps = [p for p in idx[kk].split('.')[1].split(',') if p != pos]
                if ps: idx[kk] = sku + '.' + ','.join(ps)
                else: del idx[kk]
        idx[k] = sku + '.' + pos if k not in idx else idx[k] + ',' + pos
    pk.setdefault(sku, {})[pos] = {'ebay': eb}
    n += 1
m = dict(re.findall(r'(aktiv|verkauft|beendet|bestellungen|umsatz|ohne_zuordnung|angebote_moeglich) (\d+)', head))
for sku, cards in pk.items():
    json.dump({'cards': cards}, open(os.path.join(outdir, sku + '.json'), 'w'), ensure_ascii=False)
meta.update({'last': stamp, 'aktiv': int(m.get('aktiv', 0)), 'verkauft': int(m.get('verkauft', 0)),
             'bestellungen': int(m.get('bestellungen', 0)), 'umsatz': int(m.get('umsatz', 0)) / 100,
             'offen': int(m.get('ohne_zuordnung', 0)), 'angebote': int(m.get('angebote_moeglich', 0)),
             'fs': ' '.join(f'{k}:{v}' for k, v in sorted(fs.items())),
             'index': ' '.join(f'{k}:{v}' for k, v in idx.items())})
json.dump(meta, open(os.path.join(outdir, 'meta-sync.json'), 'w'), ensure_ascii=False)
print('cards', n, 'packages', len(pk), 'index', len(idx), 'meta', {k: v for k, v in meta.items() if k not in ('index', 'fs')})
