// Vergleichspreise mit EU Sicht. Run in a logged in https://www.ebay.de/robots.txt tab.
// Set window.CARDS first (mkcards.py), then paste this file and call window.euRun() (do not await, it takes minutes).
// CARDS row: [key, query, kind "tcg"|"sports", lang, name, number, parallel, serial, auto 0/1, chrome 0/1, own item id, variants "reverse,shatterfoil"]
// Per query it reads two eBay pages: sold (worldwide, last 90 days) and active Sofort-Kaufen sorted by price + shipping.
// Every result keeps price, shipping to Germany and the country it ships from ("aus ..." missing = Deutschland).
// Poll: window.EU.done + '/' + window.EU.total + ' ' + window.EU.stop. Result: window.EU.out = {key: vgl}, lines in a <pre> via window.euDump().
// Stops on an eBay bot check and never tries to get around it.
(() => {
  const SHIP_DE = 1.90; // what the shop charges for a card
  const EUL = ["Deutschland", "Österreich", "Frankreich", "Italien", "Spanien", "Niederlande", "Belgien", "Luxemburg", "Polen", "Tschechien", "Tschechische", "Dänemark", "Schweden", "Finnland", "Irland", "Portugal", "Griechenland", "Ungarn", "Slowakei", "Slowenien", "Kroatien", "Rumänien", "Bulgarien", "Litauen", "Lettland", "Estland", "Malta", "Zypern"];
  const isEU = (o) => EUL.some((c) => o.startsWith(c));
  const MON = { jan: 0, feb: 1, mär: 2, mar: 2, apr: 3, mai: 4, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, okt: 9, oct: 9, nov: 10, dez: 11, dec: 11 };
  const today = new Date(); const iso = today.toISOString().slice(0, 10);
  const BAD = /\b(psa|bgs|sgc|cgc|beckett|graded|gem\s?mint|lot|lots|bundle|konvolut|sammlung|choose|pick|dropdown|wähle|auswahl|complete set|team set|proxy|custom|reprint|digital|sealed|box|booster|display|pack)\b/;
  const PAR = /\b(refractor|prizm|foil|holo|parallel|gold|silver|rainbow|red|blue|green|purple|orange|pink|black|white|sapphire|xfractor|wave|mojo|shimmer|crackle|lava|ice|cracked|sp|ssp|variation|var|numbered|auto|autograph|signed|patch|relic|chrome|mosaic|optic|velocity|disco|hyper|neon|camo|logofractor|aqua|teal|yellow|bronze|platinum|vintage stock|independence day|xtra points|press proof|leather|clear|sandglitter|glitter|diamante|border|foilboard|holofoil|cosmic|exclusive|fanatics|x-fractor|raywave|geometric|negative|checkerboard|zebra|sparkle|logo|hologram|holographic|mini|team color)\b|\/\s?\d+/;
  const STOP = new Set(["the", "of", "and", "foil", "parallel", "rookie", "rookies", "rc", "base", "[basis]", "variation"]);
  const VAR = ["reverse", "shatterfoil", "cosmos", "masterball", "pokeball", "pokéball"];
  const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = (c, title) => {
    const [, , kind, lang, name, number, parallel, serial, auto, chrome, , variants] = c;
    const t = title.toLowerCase().replace(/\s+/g, " ");
    if (BAD.test(t)) return false;
    const num = number.toLowerCase();
    if (kind === "tcg") {
      const [a, b] = num.split("/");
      if (b) { const A = /^\d+$/.test(a) ? "0*" + parseInt(a, 10) : esc(a), B = /^\d+$/.test(b) ? "0*" + parseInt(b, 10) : esc(b);
        if (!new RegExp("(?<!\\d)" + A + "\\s*/\\s*" + B + "(?!\\d)").test(t)) return false; }
      else if (!t.replace(/\s/g, "").includes(num.replace(/\s/g, ""))) return false;
      const ko = /korean|koreanisch|\bkor\b|korea/.test(t), jp = /japan|japanisch|\bjap\b|\bjpn\b|\bjp\b/.test(t), en = /\benglish\b|\benglisch\b|\beng\b/.test(t);
      if (lang === "Koreanisch" && !ko) return false;
      if (lang === "Japanisch" && !jp) return false;
      if (lang === "Deutsch" && (ko || jp || en)) return false;
      const own = (variants || "").split(",").filter(Boolean);
      if (VAR.some((v) => own.includes(v) !== t.includes(v))) return false;
      const nm = name.replace(/ [(].*?[)]/g, "").toLowerCase(); const alt = (name.match(/[(](.+?)[)]/) || [])[1];
      const keys = (nm.match(/[a-zäöüß]{3,}/g) || []).filter((w) => w !== "ex" && w !== "mega");
      return keys.some((w) => t.includes(w)) || (alt && t.includes(alt.toLowerCase().split(" ")[0]));
    }
    const nn = name.toLowerCase().replace(/\./g, "").split(" "); let last = nn[nn.length - 1];
    if (["jr", "ii", "iii", "iv", "sr"].includes(last) && nn.length > 1) last = nn[nn.length - 2];
    const tt0 = t.replace(/\./g, "");
    if (!tt0.includes(last)) return false;
    if (!new RegExp("(?<![a-z0-9])" + esc(num) + "(?![a-z0-9])").test(t.replace(/#/g, " "))) return false;
    if (auto) { if (!/\bauto|autograph|signed|signature/.test(t)) return false; }
    else if (/\bauto\b|autograph|signed/.test(t)) return false;
    const ser = (serial.match(/\/(\d+)/) || [])[1];
    if (ser) { if (!new RegExp("/\\s*" + ser + "\\b").test(t)) return false; }
    else if (/\/\s*\d{1,4}\b/.test(t)) return false;
    const par = parallel.trim();
    if (!par || par === "[Basis]" || par === "Base") {
      let tt = tt0.split(last).join(" ");
      if (chrome) tt = tt.replace(/chrome|optic|mosaic|prizm/g, " ");
      if (PAR.test(tt)) return false;
    } else {
      if (par.toLowerCase() === "football foil") return t.includes("football foil");
      const words = (par.toLowerCase().match(/[a-z0-9&]+/g) || []).filter((w) => !STOP.has(w) && w.length > 1);
      if (words.length && !words.every((w) => t.includes(w))) return false;
      const COL = ["pink", "purple", "red", "blue", "green", "gold", "orange", "black", "yellow", "aqua", "teal", "bronze"];
      if (COL.some((w) => !words.includes(w) && new RegExp("\\b" + w + "\\b").test(t.split(last).join(" ")))) return false;
      if (!/border|diamante|glitter|logo/.test(par.toLowerCase()) && /border|diamante|sandglitter|foilboard|team color|fanatics/.test(t)) return false;
    }
    return true;
  };
  const num = (s) => parseFloat(s.replace(/\./g, "").replace(",", "."));
  const pdate = (s) => { const m = s.match(/(\d+)\.\s*(\w+)\.?\s*(\d{4})?/); if (!m) return null; const mo = MON[m[2].slice(0, 3).toLowerCase()]; if (mo == null) return null;
    let y = m[3] ? +m[3] : today.getFullYear(); let d = new Date(y, mo, +m[1]); if (!m[3] && d > today) d = new Date(y - 1, mo, +m[1]); return d; };
  // one result card -> {t title, p price, s shipping (null unknown), o origin, d date|null, bo, id}
  const parseCard = (el) => {
    const x = el.textContent.replace(/\s+/g, " ");
    const ti = ((el.querySelector(".s-card__title, .s-item__title") || {}).textContent || "").replace(/\s+/g, " ").replace(/Neues Angebot|Wird in neuem Fenster oder Tab geöffnet/g, "").trim();
    if (!ti || /^Shop on eBay/i.test(ti)) return null;
    const pm = x.match(/EUR\s?([\d.]+,\d\d)/); if (!pm) return null;
    let s = null; const sm = x.match(/\+\s*(?:ca\.\s*)?EUR\s?([\d.]+,\d\d)\s*(?:Versand|Lieferung)/);
    if (sm) s = num(sm[1]); else if (/Kostenlos(?:e|er)? (?:Versand|Lieferung)/.test(x)) s = 0;
    const om = x.match(/(?:Versand|Lieferung|Versandkosten)\s*aus ([A-ZÄÖÜ][a-zäöüß]+(?: [A-ZÄÖÜ][a-zäöüß]+)?)/) || x.match(/(?:EUR\s?[\d.]+,\d\d|Gebot|Gebote|Sofort-Kaufen|Preisvorschlag)\s*aus ([A-ZÄÖÜ][a-zäöüß]+(?: [A-ZÄÖÜ][a-zäöüß]+)?)/);
    const dm = x.match(/Verkauft\s+(\d+\.\s*\w+\.?\s*\d{4})/);
    const id = (((el.querySelector('a[href*="/itm/"]') || {}).href || "").match(/itm\/(\d+)/) || [])[1] || "";
    return { t: ti, p: num(pm[1]), s, o: om ? om[1] : "Deutschland", d: dm ? pdate(dm[1]) : null, bo: /Preisvorschlag akzeptiert/.test(x), id };
  };
  const page = async (q, sold) => {
    const url = "https://www.ebay.de/sch/i.html?_nkw=" + encodeURIComponent(q).replace(/%20/g, "+") + (sold ? "&LH_Sold=1&LH_Complete=1" : "&LH_BIN=1&_sop=15") + "&_ipg=60";
    const r = await fetch(url, { credentials: "include" }); const h = await r.text();
    if (/splashui|challenge|captcha/i.test(r.url + h.slice(0, 4000)) && !/s-card|s-item/.test(h)) return "CHALLENGE";
    const d = new DOMParser().parseFromString(h, "text/html"); const out = []; let stop = false;
    if (!d.querySelector("li.s-card, li.s-item, .srp-river-answer, .srp-save-null-search")) return "EMPTY"; // eBay sometimes answers a burst with an empty page
    for (const el of d.querySelectorAll("li.s-card, li.s-item, .srp-river-answer")) {
      if (el.classList.contains("srp-river-answer")) { if (/weniger Suchbegriffe|Ergebnisse für/i.test(el.textContent)) stop = true; continue; }
      if (stop) break;
      const c = parseCard(el); if (c && (!sold || c.d)) out.push(c);
    }
    return out;
  };
  const med = (a) => { const s = [...a].sort((x, y) => x - y), n = s.length; return n ? (n % 2 ? s[(n - 1) / 2] : (s[n / 2 - 1] + s[n / 2]) / 2) : null; };
  const r2 = (v) => Math.round(v * 100) / 100;
  const up = (v) => (v > 0 ? Math.max(0.99, r2(v < 10 ? Math.ceil(v * 2) / 2 - 0.01 : Math.ceil(v) - 0.01)) : null);   // x,49 / x,99 up
  const down = (v) => (v > 0 ? Math.max(0.99, r2(v < 10 ? Math.floor(v * 2) / 2 - 0.01 : Math.floor(v) - 0.01)) : 0.99); // x,49 / x,99 down
  const OWN = () => new Set(window.CARDS.map((c) => c[10]).filter(Boolean));
  const evalCard = (c, sold, act, own) => {
    let hits = sold.filter((h) => h.d && (today - h.d) / 864e5 <= 90 && h.p >= 0.2 && match(c, h.t)).map((h) => ({ ...h, v: h.bo ? h.p * 0.85 : h.p }));
    hits.sort((a, b) => b.d - a.d); hits = hits.slice(0, 15);
    if (hits.length >= 3) { const m0 = med(hits.map((h) => h.v)); hits = hits.filter((h) => h.v <= 3 * m0); }
    const all = hits.slice(0, 10), eu = hits.filter((h) => isEU(h.o)).slice(0, 10), imp = hits.filter((h) => !isEU(h.o) && h.s != null);
    const r = { n: all.length, d: iso, q: c[1] };
    if (all.length) { const v = all.map((h) => h.v); r.m = r2(med(v)); r.lo = r2(Math.min(...v)); r.hi = r2(Math.max(...v)); }
    if (eu.length) r.eu = { n: eu.length, m: r2(med(eu.map((h) => h.v))) };
    if (imp.length) r.imp = r2(med(imp.map((h) => h.v + h.s)));      // what a German buyer paid incl. shipping for a card from outside the EU (before customs)
    const a = act.filter((h) => h.s != null && !own.has(h.id) && match(c, h.t));
    const ae = a.filter((h) => isEU(h.o)).sort((x, y) => x.p + x.s - (y.p + y.s));
    const an = a.filter((h) => !isEU(h.o)).sort((x, y) => x.p + x.s - (y.p + y.s));
    if (ae.length) r.kon = { n: ae.length, t: r2(ae[0].p + ae[0].s), p: ae[0].p, id: ae[0].id, o: ae[0].o };
    if (an.length) r.ab = r2(an[0].p + an[0].s);                     // cheapest offer from outside the EU incl. shipping
    // suggestion (price without the shop's shipping)
    const base = r.eu ? r.eu.m * 1.1 : (r.m ? r.m * 1.1 : null);
    if (r.kon) {
      r.vs = down(r.kon.t - SHIP_DE); r.bs = "kon";                   // as cheap as the cheapest EU offer incl. shipping
      if (r.eu && r.eu.n >= 3 && r.vs < r.eu.m * 0.6) { r.vs = up(r.eu.m); r.bs = "kon_eu"; } // a single dumping offer: stay at what EU buyers pay
    } else {
      const landed = [r.imp, r.ab].filter((x) => x > 0); const L = landed.length ? Math.min(...landed) : null;
      let capv = L ? (L - SHIP_DE) * 0.85 : null;
      if (capv) capv = Math.min(capv, r.m ? Math.max(3 * r.m, r.m + 5) : capv * 0.7); // never more than 3x (or +5 €) of what the card really sells for                  // no EU competition: up to a bit under the import price
      const v = Math.max(base || 0, capv || 0);
      if (v > 0) { r.vs = up(v); r.bs = capv && capv > (base || 0) ? "imp" : (r.eu ? "eu" : "welt"); }
    }
    r.sic = !!(r.kon || (r.bs !== "imp" && ((r.eu && r.eu.n >= 3) || r.n >= 3))); // an import based price is room to move, never a firm target
    return r;
  };
  window.EU = window.EU || { raw: {}, out: {}, done: 0, total: 0, stop: "" };
  window.euRun = async (conc = 2) => {
    const E = window.EU; E.stop = ""; E.run = 1; const own = OWN();
    const qs = [...new Set(window.CARDS.map((c) => c[1]))]; E.total = qs.length; let i = 0;
    const work = async () => {
      while (i < qs.length && !E.stop) {
        const q = qs[i++];
        if (!E.raw[q]) {
          try {
            let s, a;
            for (let t = 0; t < 3; t++) { s = await page(q, true); if (s !== "EMPTY") break; await new Promise((r) => setTimeout(r, 8000 * (t + 1))); }
            if (s === "CHALLENGE") { E.stop = "eBay Prüfseite bei: " + q; break; }
            await new Promise((r) => setTimeout(r, 900 + Math.random() * 900));
            for (let t = 0; t < 3; t++) { a = await page(q, false); if (a !== "EMPTY") break; await new Promise((r) => setTimeout(r, 8000 * (t + 1))); }
            if (a === "CHALLENGE") { E.stop = "eBay Prüfseite bei: " + q; break; }
            if (s === "EMPTY" || a === "EMPTY") { E.empty = (E.empty || 0) + 1; continue; } // leave this card without a new check
            E.raw[q] = { s, a };
          } catch (e) { E.raw[q] = { s: [], a: [], err: String(e).slice(0, 60) }; }
        }
        for (const c of window.CARDS.filter((c) => c[1] === q)) E.out[c[0]] = evalCard(c, E.raw[q].s, E.raw[q].a, own);
        E.done++; await new Promise((r) => setTimeout(r, 700 + Math.random() * 700));
      }
    };
    await Promise.all([...Array(conc)].map(work)); E.run = 0;
  };
  window.euDump = () => { document.body.innerHTML = "<pre></pre>"; document.querySelector("pre").textContent = JSON.stringify(window.EU.out); return Object.keys(window.EU.out).length; };
  return "eu_prices ready";
})()
