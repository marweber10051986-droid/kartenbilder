const IDX = "__INDEX__"; const FULL = __FULL__;
const map = {}; for (const e of IDX.split(' ')) { const [k, v] = e.split(':'); if (!k) continue; const [sku, ps] = v.split('.'); map[k] = ps.split(',').map(p => sku + '.' + p); }
const norm = s => s.normalize('NFC').replace(/\s+/g, ' ').trim();
const hh = s => { let x = 0x811c9dc5; for (const c of new TextEncoder().encode(norm(s))) { x ^= c; x = Math.imul(x, 0x01000193) >>> 0; } return x.toString(36); };
const cents = s => { const m = (s || '').match(/(\d{1,3}(?:\.\d{3})*),(\d\d)/); return m ? parseInt(m[1].replace(/\./g, '')) * 100 + parseInt(m[2]) : 0; };
const get = async u => new DOMParser().parseFromString(await (await fetch(u, { credentials: 'include' })).text(), 'text/html');
const tot = d => { const m = d.body.textContent.match(/\d+\s*[-–]\s*\d+\s*von\s*([\d\.]+)/); return m ? parseInt(m[1].replace(/\./g, '')) : 0; };
const L = {};
async function grid(base, ended) {
  let off = 0, total = 1;
  while (off < total) {
    const d = await get(base + (base.includes('?') ? '&' : '?') + 'offset=' + off + '&limit=200');
    total = tot(d);
    for (const r of d.querySelectorAll('tr.grid-row')) {
      const c = n => r.querySelector('td.shui-dt-column__' + n);
      const a = r.querySelector('td.shui-dt-column__title a');
      const id = r.getAttribute('data-id');
      const sku = (c('listingSKU') || {}).textContent?.trim() || '';
      const title = norm(a ? a.textContent : '');
      let st = 'A';
      if (ended) st = /Verkauft/.test((c('soldStatus') || {}).textContent || '') ? 'V' : 'E';
      const num = n => parseInt(((c(n) || {}).textContent || '').replace(/\D+/g, ' ').trim().split(' ')[0]) || 0;
      const trm = ((c('timeRemaining') || {}).textContent || '').match(/(\d+)\s*T/);
      L[id] = { id, sku, title, st, av: parseInt((c('availableQuantity') || {}).textContent) || 0, pr: cents((c('price') || {}).textContent), q: 0, rev: 0, dt: '', v: num('visitCount'), w: num('watchCount'), tr: ended ? '-' : (trm ? +trm[1] : 0) };
    }
    off += 200; if (!total) break;
  }
}
await grid('/sh/lst/active', false);
await grid('/sh/lst/ended', true);
const MON = { Jan: 1, Feb: 2, Mar: 3, Mär: 3, Apr: 4, May: 5, Mai: 5, Jun: 6, Jul: 7, Aug: 8, Sep: 9, Oct: 10, Okt: 10, Nov: 11, Dec: 12, Dez: 12 };
let ooff = 0, otot = 1, nOrd = 0;
while (ooff < otot) {
  const d = await get('/sh/ord/?filter=status:ALL_ORDERS&limit=200&offset=' + ooff);
  otot = tot(d);
  let cur = null; const orders = [];
  for (const r of d.querySelectorAll('tr.order-info, tr.item-info')) {
    if (r.classList.contains('order-info')) {
      const oc = [...r.querySelectorAll('td.order-default-cell')].map(t => t.textContent.replace(/\s+/g, ' ').trim());
      const ids = [...new Set([...r.querySelectorAll('a')].map(a => ((a.getAttribute('href') || '').match(/itm\/(\d+)|itemId=(\d+)/) || []).slice(1).find(Boolean)).filter(Boolean))];
      const dm = (oc[4] || '').match(/(\d+)\.\s*(\w{3})/);
      cur = { q: parseInt(oc[1]) || 1, sub: cents(oc[2]), dt: dm ? dm[1] + '.' + (MON[dm[2]] || 0) + '.' : '', items: [], oids: ids };
      orders.push(cur);
    } else if (cur) {
      const ids = [...r.querySelectorAll('a')].map(a => ((a.getAttribute('href') || '').match(/itm\/(\d+)|itemId=(\d+)/) || []).slice(1).find(Boolean)).filter(Boolean);
      const q = parseInt((r.querySelector('td.item-quantity') || {}).textContent) || 1;
      if (ids[0]) cur.items.push({ id: ids[0], q });
    }
  }
  for (const o of orders) {
    nOrd++;
    const items = o.items.length ? o.items : (o.oids[0] ? [{ id: o.oids[0], q: o.q }] : []);
    const w = items.reduce((s, i) => s + ((L[i.id] || {}).pr || 1) * i.q, 0) || 1;
    for (const i of items) {
      const l = L[i.id]; if (!l) continue;
      l.q += i.q; l.rev += Math.round(o.sub * ((l.pr || 1) * i.q) / w);
      if (!l.dt) l.dt = o.dt;
    }
  }
  ooff += 200; if (!otot) break;
}
const out = []; const unm = []; const snap = {}; let old = {};
try { old = JSON.parse(localStorage.getItem('rb_sync_last') || '{}'); } catch (e) {}
const fresh = !Object.keys(old).length; const taken = new Set(); const pending = [];
const emit = (l, slot) => {
  const vals = [l.st, l.av, l.pr, l.id, l.q, l.rev, l.dt || '-', l.v, l.w, l.tr].join(' ');
  const line = slot ? slot + ' ' + vals : '? ' + l.sku + ' | ' + l.title + ' | ' + vals;
  snap[l.id] = line; if (FULL || old[l.id] !== line) (slot ? out : unm).push(line);
};
for (const id of Object.keys(L).sort()) {
  const l = L[id]; const m = l.sku.match(/^([A-Z]+-[A-Z]{2}-\d{3})(?:-(\d{1,2}))?$/); const pkg = m ? m[1] : l.sku;
  let slot = m && m[2] ? pkg + '.' + m[2].padStart(2, '0') : null;            // per card SKU (SC-AA-012-07)
  if (!slot && old[id] && old[id][0] !== '?') slot = old[id].split(' ')[0];   // known from the last sync
  if (slot) { taken.add(slot); emit(l, slot); } else pending.push([hh(pkg + '|' + l.title), l]);
}
for (const [k, l] of pending) { const s = (map[k] || []).find(x => !taken.has(x)); if (s) taken.add(s); emit(l, s || null); }
window.__rbPending = snap;
const offd = await get('/sh/lst/active?offers=sendNewOffers&limit=200'); const offN = offd.querySelectorAll('tr.grid-row').length;
const cnt = s => Object.values(L).filter(l => l.st === s).length;
const head = 'RB ' + new Date().toISOString().slice(0, 16) + ' aktiv ' + cnt('A') + ' verkauft ' + cnt('V') + ' beendet ' + cnt('E') + ' bestellungen ' + nOrd + ' umsatz ' + Object.values(L).reduce((a, l) => a + l.rev, 0) + ' zugeordnet ' + Object.keys(snap).length + ' ohne_zuordnung ' + (Object.values(snap).filter(x => x[0] === '?').length) + ' geaendert ' + (out.length + unm.length) + ' angebote_moeglich ' + offN + ' snapshot ' + (fresh ? 'leer' : Object.keys(old).length);
document.body.innerHTML = '<pre></pre>'; document.querySelector('pre').textContent = [head].concat(out.sort(), unm).join('\n');
head
