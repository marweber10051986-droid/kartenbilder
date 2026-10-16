#!/usr/bin/env python3
"""Remove scan lines (thin straight streaks from the scanner glass) from flatbed card scans.

The image keeps its size, background, shadow and corners. Only the streak is corrected.
A bright streak adds light (strong on dark areas, invisible on white): out = in + k*(255-in).
A dark streak dims: out = in*(1-k). k is measured per row from the pixels beside the streak,
so the print texture under the streak stays intact.

Only vertical, bright streaks are touched (that is how glass streaks show up on this scanner).

Usage: scanlines.py IN [OUT]   (OUT default: IN with -clean before the extension)
Exit code 0, prints the lines found as  x_center slope strength.
"""
import sys, os
import cv2, numpy as np
from scipy.ndimage import median_filter

MIN_STRENGTH = 6      # grey levels in the block median
MIN_CONS = 0.70       # share of row blocks that show the line
SLOPES = np.arange(-0.015, 0.01501, 0.0005)
HALF = 9              # half width of the corrected strip
REF = (13, 19)        # reference columns beside the line (offset range)


def find_lines(g):
    H, W = g.shape
    hp = g - median_filter(g, size=(1, 15))
    B = 100; nb = H // B
    prof = np.stack([hp[i * B:(i + 1) * B].mean(0) for i in range(nb)])
    cent = (np.arange(nb) + 0.5) * B - H / 2
    xs = np.arange(W)
    cands = []
    for s in SLOPES:
        sh = np.stack([np.interp(xs, xs - s * c, prof[i]) for i, c in enumerate(cent)])
        med = np.median(sh, 0)
        cons = np.where(med > 0, (sh > 4).mean(0), (sh < -4).mean(0))
        score = np.abs(med) * cons
        ok = np.where((np.abs(med) >= MIN_STRENGTH) & (cons >= MIN_CONS))[0]
        for x in ok:
            cands.append((score[x], s, int(x), float(med[x]), float(cons[x])))
    cands.sort(reverse=True)
    lines = []
    for c in cands:
        if any(abs(c[2] - l[2]) < 2 * HALF for l in lines):
            continue
        lines.append(c)
    return lines


def is_design_edge(sm, x0, s, H):
    """A thin light/dark rim at a colour boundary belongs to the card print. Skip it."""
    rows = np.arange(0, H, 7)
    xc = x0 + s * (rows - H / 2)
    xl = np.clip(np.round(xc - 14).astype(int), 0, sm.shape[1] - 1)
    xr = np.clip(np.round(xc + 14).astype(int), 0, sm.shape[1] - 1)
    d = np.abs(sm[rows, xl] - sm[rows, xr])
    return float(np.median(d)) > 18


def near_background(g, x0, s, H):
    """The bright rim right at the card edge looks like a streak. If one side of the line is
    smooth scanner background (no print texture), it is the card edge. Skip it."""
    rows = np.arange(50, H - 50, 25)
    sides = []
    for a, b in ((-40, -12), (12, 40)):
        st = []
        for r in rows:
            xc = int(round(x0 + s * (r - H / 2)))
            lo, hi = max(0, xc + a), min(g.shape[1], xc + b)
            if hi - lo < 5:
                st.append(0.0); continue
            blk = g[r - 3:r + 4, lo:hi]
            st.append(float(np.median(np.abs(np.diff(blk, axis=1)))))   # texture, not the shadow gradient
        sides.append(np.median(st))
    return min(sides) < 2.5


def is_flare(im, x0, s):
    """A streak from the scanner glass adds a stable share k of the free room (255-value)
    along its whole length. A printed line does not follow that rule."""
    S = strip(im, x0, s, np.array([-1, 0, 1])).mean(1)
    refo = np.r_[-np.arange(*REF)[::-1], np.arange(*REF)]
    R = strip(im, x0, s, refo).mean(1)
    H = im.shape[0]; B = 100
    ks = []
    for i in range(H // B):
        sl = slice(i * B, (i + 1) * B)
        room = (255 - R[sl]).sum()
        if room / (B * 3) < 60:
            continue
        ks.append((S[sl] - R[sl]).sum() / room)
    if len(ks) < 8:
        return False
    ks = np.array(ks); m = np.median(ks)
    return m > 0.08 and (np.abs(ks - m) < 0.5 * m).mean() >= 0.75


def strip(im, x0, s, offs):
    H = im.shape[0]
    rows = np.arange(H, dtype=np.float32)
    xc = (x0 + s * (rows - H / 2)).astype(np.float32)
    mx = xc[:, None] + offs[None, :].astype(np.float32)
    my = np.repeat(rows[:, None], len(offs), 1)
    return cv2.remap(im, mx, my, cv2.INTER_LINEAR, borderMode=cv2.BORDER_REFLECT)


def fix_line(im, x0, s, bright):
    H, W = im.shape[:2]
    offs = np.arange(-HALF, HALF + 1)
    refo = np.r_[-np.arange(*REF)[::-1], np.arange(*REF)]
    S = strip(im, x0, s, offs)                    # H x n x 3
    R = strip(im, x0, s, refo).mean(1)            # H x 3
    # k per channel = sum(S-R) / sum(room) over a window of rows,
    # so the print texture averages out before the ratio is taken
    if bright:
        num = S - R[:, None]
        den = np.broadcast_to(255 - R[:, None], S.shape)
    else:
        num = R[:, None] - S
        den = np.broadcast_to(R[:, None], S.shape)
    win = 41
    box = lambda a: cv2.blur(np.ascontiguousarray(a, np.float32), (1, win), borderType=cv2.BORDER_REPLICATE)
    kk = box(num) / np.maximum(box(den), 20)
    kk = np.clip(kk, 0, 0.6)
    kk[:, :2] *= np.array([0.3, 0.7])[None, :, None]; kk[:, -2:] *= np.array([0.7, 0.3])[None, :, None]
    # write back: every pixel in the strip gets k by its distance to the line centre
    out = im.copy()
    xc = x0 + s * (np.arange(H) - H / 2)
    lo = int(max(0, np.floor(xc.min()) - HALF - 1)); hi = int(min(W, np.ceil(xc.max()) + HALF + 2))
    sub = out[:, lo:hi]
    dx = np.arange(lo, hi)[None, :] - xc[:, None]   # H x w
    inside = np.abs(dx) <= HALF
    fi = np.clip(dx + HALF, 0, 2 * HALF)
    i0 = np.floor(fi).astype(int); i1 = np.minimum(i0 + 1, 2 * HALF); t = fi - i0
    rr = np.repeat(np.arange(H)[:, None], dx.shape[1], 1)
    t = t[..., None]
    kx = np.where(inside[..., None], kk[rr, i0] * (1 - t) + kk[rr, i1] * t, 0)
    if bright:
        sub = (sub - 255 * kx) / (1 - kx)
    else:
        sub = sub / (1 - kx)
    out[:, lo:hi] = sub
    return out


def clean(path, outpath=None):
    im = cv2.imread(path, cv2.IMREAD_COLOR)
    if im is None:
        raise SystemExit('cannot read ' + path)
    imf = im.astype(np.float32)
    g = cv2.cvtColor(im, cv2.COLOR_BGR2GRAY).astype(np.float32)
    sm = cv2.GaussianBlur(g, (0, 0), 3)
    found = []
    for score, s, x, med, cons in find_lines(g):
        if med <= 0:                       # the glass streaks of this scanner are always bright
            continue
        if is_design_edge(sm, x, s, g.shape[0]):
            continue
        if near_background(g, x, s, g.shape[0]):
            continue
        if not is_flare(imf, x, s):
            continue
        found.append((False, x, s, med))
    for transpose, x, s, med in found:
        if transpose:
            imf = np.ascontiguousarray(fix_line(np.ascontiguousarray(imf.transpose(1, 0, 2)), x, s, med > 0).transpose(1, 0, 2))
        else:
            imf = fix_line(imf, x, s, med > 0)
    out = np.clip(np.round(imf), 0, 255).astype(np.uint8)
    if outpath is None:
        b, e = os.path.splitext(path); outpath = b + '-clean' + (e or '.png')
    params = [cv2.IMWRITE_WEBP_QUALITY, 85] if outpath.lower().endswith('.webp') else []
    cv2.imwrite(outpath, out, params)
    for t, x, s, med in found:
        print('streak at x', x, 'slope', round(s, 4), 'strength', round(med, 1))
    if not found:
        print('no scan lines found')
    return outpath, found


if __name__ == '__main__':
    a = [x for x in sys.argv[1:] if not x.startswith('--')]
    clean(a[0], a[1] if len(a) > 1 else None)
