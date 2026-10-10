// In-browser version of pmatch.py. Needs window.RAW (from price_read.js) and window.CARDS =
// [[key, query, kind, lang, name, number, parallel, serial, auto(0/1), chromeFlag(0/1)], ...]
// Result: window.VGL = {key: {n, m, lo, hi, d, q}} and a compact text in a <pre> (one line per card).
(() => {
  const MON = { jan: 0, feb: 1, mär: 2, mar: 2, apr: 3, mai: 4, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, okt: 9, oct: 9, nov: 10, dez: 11, dec: 11 };
  const today = new Date(); const iso = today.toISOString().slice(0, 10);
  const BAD = /\b(psa|bgs|sgc|cgc|beckett|graded|gem\s?mint|lot|lots|bundle|konvolut|sammlung|choose|pick|dropdown|wähle|auswahl|complete set|team set|proxy|custom|reprint|digital|sealed|box|booster|display|pack)\b/;
  const PAR = /\b(refractor|prizm|foil|holo|parallel|gold|silver|rainbow|red|blue|green|purple|orange|pink|black|white|sapphire|xfractor|wave|mojo|shimmer|crackle|lava|ice|cracked|sp|ssp|variation|var|numbered|auto|autograph|signed|patch|relic|chrome|mosaic|optic|velocity|disco|hyper|neon|camo|logofractor|aqua|teal|yellow|bronze|platinum|vintage stock|independence day|xtra points|press proof|leather|clear|sandglitter|glitter|diamante|border|foilboard|holofoil|cosmic|exclusive|fanatics|x-fractor|raywave|geometric|negative|checkerboard|zebra|sparkle|logo|hologram|holographic|mini|team color)\b|\/\s?\d+/;
  const STOP = new Set(["the", "of", "and", "foil", "parallel", "rookie", "rookies", "rc", "base", "[basis]", "variation"]);
  const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = (c, title) => {
    const [, , kind, lang, name, number, parallel, serial, auto, chrome] = c;
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
      const words = (par.toLowerCase().match(/[a-z0-9&]+/g) || []).filter((w) => !STOP.has(w) && w.length > 1);
      if (words.length && !words.every((w) => t.includes(w))) return false;
      const COL = ["pink", "purple", "red", "blue", "green", "gold", "orange", "black", "yellow", "aqua", "teal", "bronze"];
      if (COL.some((w) => !words.includes(w) && new RegExp("\\b" + w + "\\b").test(t.split(last).join(" ")))) return false;
      if (!/border|diamante|glitter|logo/.test(par.toLowerCase()) && /border|diamante|sandglitter|foilboard|team color|fanatics/.test(t)) return false;
    }
    return true;
  };
  const pdate = (s) => { const m = s.match(/(\d+)\.\s*(\w+)\.?\s*(\d{4})?/); if (!m) return null; const mo = MON[m[2].slice(0, 3).toLowerCase()]; if (mo == null) return null;
    let y = m[3] ? +m[3] : today.getFullYear(); let d = new Date(y, mo, +m[1]); if (!m[3] && d > today) d = new Date(y - 1, mo, +m[1]); return d; };
  const VGL = {}, lines = [];
  for (const c of window.CARDS) {
    const ls = window.RAW[c[1]]; if (!ls) continue;
    let hits = [];
    for (const l of ls) { if (l.startsWith("ERR")) continue; const [d, p, bo, ...rest] = l.split("|"); const ti = rest.join("|").replace(/Wird in neuem Fenster oder Tab geöffnet/g, "");
      const dd = pdate(d); if (!dd || (today - dd) / 864e5 > 90 || p === "?") continue; if (!match(c, ti)) continue;
      let v = parseFloat(p.replace(/\./g, "").replace(",", ".")); if (v < 0.2) continue; if (bo === "BO") v *= 0.85; hits.push([dd, v, ti]); }
    hits.sort((a, b) => b[0] - a[0]); hits = hits.slice(0, 10);
    const med = (a) => { const s = [...a].sort((x, y) => x - y), n = s.length; return n ? (n % 2 ? s[(n - 1) / 2] : (s[n / 2 - 1] + s[n / 2]) / 2) : null; };
    if (hits.length >= 3) { const m0 = med(hits.map((h) => h[1])); hits = hits.filter((h) => h[1] <= 3 * m0); }
    const vals = hits.map((h) => h[1]); const r = { n: vals.length, d: iso, q: c[1] };
    if (vals.length) { r.m = Math.round(med(vals) * 100) / 100; r.lo = Math.round(Math.min(...vals) * 100) / 100; r.hi = Math.round(Math.max(...vals) * 100) / 100; }
    VGL[c[0]] = r;
    lines.push(c[0] + "|" + r.n + "|" + (r.m ?? "") + "|" + (r.lo ?? "") + "|" + (r.hi ?? ""));
    (window.TT = window.TT || {})[c[0]] = hits.slice(0, 3).map((h) => h[1].toFixed(2) + " " + h[2].slice(0, 70));
  }
  window.VGL = VGL;
  document.body.innerHTML = "<pre></pre>"; document.querySelector("pre").textContent = lines.join("\n");
  return lines.length + " Karten, mit Treffern " + Object.values(VGL).filter((r) => r.n).length;
})()
