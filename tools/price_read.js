// Run in a https://www.ebay.de/robots.txt tab (logged in). Paste after setting window.QS = [...queries].
// Reads eBay sold listings per query into window.RAW; stops on a bot check and never tries to get around it.
window.RAW = window.RAW || {}; window.__pstop = '';
window.soldOne = async q => {
  const url = 'https://www.ebay.de/sch/i.html?_nkw=' + encodeURIComponent(q).replace(/%20/g, '+') + '&LH_Sold=1&LH_Complete=1&_ipg=60';
  const r = await fetch(url, { credentials: 'include' }); const h = await r.text();
  if (/splashui|challenge|captcha/i.test(r.url + h.slice(0, 4000)) && !/s-card|s-item/.test(h)) return 'CHALLENGE';
  const d = new DOMParser().parseFromString(h, 'text/html'); const out = []; let stop = false;
  for (const el of d.querySelectorAll('li.s-card, li.s-item, .srp-river-answer')) {
    const t = el.textContent.replace(/\s+/g, ' ');
    if (el.classList.contains('srp-river-answer')) { if (/weniger Suchbegriffe/i.test(t)) stop = true; continue; }
    if (stop) break;
    const dm = t.match(/Verkauft\s+(\d+\.\s*\w+\.?\s*\d{4})/); if (!dm) continue;
    const pm = t.match(/EUR\s?([\d.]+,\d\d)/);
    const ti = (el.querySelector('.s-card__title, .s-item__title') || el).textContent.replace(/\s+/g, ' ').replace(/Neues Angebot/, '').trim().slice(0, 120);
    out.push(dm[1] + '|' + (pm ? pm[1] : '?') + '|' + (/Preisvorschlag akzeptiert/.test(t) ? 'BO' : 'X') + '|' + ti);
    if (out.length >= 40) break;
  }
  return out;
};
window.runP = async (qs, conc = 3) => {
  window.__prun = 1; let i = 0;
  const work = async () => { while (i < qs.length && !window.__pstop) { const q = qs[i++]; if (window.RAW[q]) continue;
    try { const r = await soldOne(q); if (r === 'CHALLENGE') { window.__pstop = 'eBay Prüfseite bei: ' + q; break; } window.RAW[q] = r; }
    catch (e) { window.RAW[q] = ['ERR ' + String(e).slice(0, 60)]; }
    await new Promise(r => setTimeout(r, 900 + Math.random() * 900)); } };
  await Promise.all([...Array(conc)].map(work)); window.__prun = 0;
};
// window.runP(window.QS);  then poll: Object.keys(RAW).length, __prun, __pstop
// dump: document.body.innerHTML='<pre></pre>'; document.querySelector('pre').textContent=JSON.stringify(RAW); then get_page_text
'price_read ready'
