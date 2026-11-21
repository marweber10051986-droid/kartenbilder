import sys, math, os
import numpy as np, cv2
from PIL import Image, ImageDraw, ImageFilter, ImageFont

SRC = '/home/claude/rg/src/'
OUT = '/home/claude/rg/out/'
FONT = '/home/claude/rg/BarlowCondensed-Bold.ttf'
W, H = 1200, 675


def card(name, radius=0.035):
    im = Image.open(SRC + name + '.webp').convert('RGB')
    a = np.asarray(im).astype(int)
    border = np.concatenate([a[:8].reshape(-1, 3), a[-8:].reshape(-1, 3), a[:, :8].reshape(-1, 3), a[:, -8:].reshape(-1, 3)])
    bg = np.median(border, 0)
    d = np.abs(a - bg).max(2)
    m = (d > 22).astype(np.uint8) * 255
    m = cv2.morphologyEx(m, cv2.MORPH_CLOSE, np.ones((15, 15), np.uint8))
    cs, _ = cv2.findContours(m, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    x, y, w, h = cv2.boundingRect(max(cs, key=cv2.contourArea))
    c = im.crop((x + 2, y + 2, x + w - 2, y + h - 2))
    r = int(c.width * radius)
    mask = Image.new('L', c.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, c.width - 1, c.height - 1), r, fill=255)
    c = c.convert('RGBA')
    c.putalpha(mask)
    return c


def background():
    y, x = np.mgrid[0:H, 0:W]
    img = np.zeros((H, W, 3), float) + np.array([10, 10, 12])

    def glow(cx, cy, rad, col, strength):
        dist = np.sqrt((x - cx) ** 2 + (y - cy) ** 2) / rad
        f = np.clip(1 - dist, 0, 1) ** 2 * strength
        return f[..., None] * np.array(col)
    img += glow(W * 0.3, -80, 700, [0, 170, 175], 0.55)
    img += glow(W * 0.85, H + 60, 650, [225, 30, 120], 0.45)
    img += glow(W * 0.05, H * 0.9, 420, [255, 59, 48], 0.18)
    bg = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
    # feines Raster
    d = ImageDraw.Draw(bg, 'RGBA')
    for gx in range(0, W, 40):
        d.line([(gx, 0), (gx, H)], fill=(255, 255, 255, 6))
    for gy in range(0, H, 40):
        d.line([(0, gy), (W, gy)], fill=(255, 255, 255, 6))
    return bg.convert('RGBA')


def place(bg, c, cx, cy, height, angle, glowcol=(0, 0, 0)):
    s = height / c.height
    c = c.resize((int(c.width * s), int(height)), Image.LANCZOS)
    rot = c.rotate(angle, resample=Image.BICUBIC, expand=True)
    sh = Image.new('RGBA', rot.size, (0, 0, 0, 0))
    alpha = rot.split()[3]
    sh.putalpha(alpha.point(lambda v: int(v * 0.75)))
    pad = 60
    shl = Image.new('RGBA', (rot.width + pad * 2, rot.height + pad * 2), (0, 0, 0, 0))
    shl.paste(sh, (pad, pad))
    shl = shl.filter(ImageFilter.GaussianBlur(22))
    bg.alpha_composite(shl, (int(cx - shl.width / 2 + 10), int(cy - shl.height / 2 + 18)))
    bg.alpha_composite(rot, (int(cx - rot.width / 2), int(cy - rot.height / 2)))
    return (cx, cy, rot.width, rot.height)


def fan(names, out, heights=None, spread=230, angles=None):
    bg = background()
    n = len(names)
    angles = angles or ([-11, 0, 11] if n == 3 else [-14, -5, 5, 14] if n == 4 else [-7, 7])
    heights = heights or ([470] * n)
    order = list(range(n))
    mid = (n - 1) / 2
    # mittlere Karte zuletzt (oben)
    order.sort(key=lambda i: -abs(i - mid))
    for i in order:
        cx = W / 2 + (i - mid) * spread
        cy = H / 2 + abs(i - mid) * 22 + 8
        place(bg, card(names[i]), cx, cy, heights[i] if abs(i - mid) > 0.6 else heights[i] + 30, angles[i])
    save(bg, out)
    return bg


def save(bg, out):
    bg.convert('RGB').save(OUT + out + '.webp', 'WEBP', quality=82, method=6)
    bg.convert('RGB').resize((600, 338), Image.LANCZOS).save(OUT + out + '-klein.webp', 'WEBP', quality=80, method=6)


def label(d, xy, text, size, fill=(255, 255, 255, 255), anchor='mm'):
    d.text(xy, text, font=ImageFont.truetype(FONT, size), fill=fill, anchor=anchor)


if __name__ == '__main__':
    F = '2026-topps-flagship-nfl'
    # 1 Checkliste
    fan(['caleb-williams-35-chicago-bears-' + F, 'fernando-mendoza-rookie-301-las-vegas-raiders-' + F,
         'jeremiyah-love-rookie-309-arizona-cardinals-' + F], 'rg-checkliste-2026-topps-flagship-nfl')
    # 2 Rookie Card
    fan(['carnell-tate-rookie-305-tennessee-titans-2026-topps-heritage-nfl', 'fernando-mendoza-rookie-272-las-vegas-raiders-2026-topps-heritage-nfl',
         'jeremiyah-love-rookie-133-arizona-cardinals-2026-topps-heritage-nfl'], 'rg-rookie-card')
    # 3 Parallels: vier Versionen nebeneinander
    fan(['caleb-williams-35-chicago-bears-' + F, 'pete-werner-44-new-orleans-saints-diamante-' + F,
         'pete-werner-44-new-orleans-saints-football-leather-foil-' + F, 'roquan-smith-17-baltimore-ravens-rainbow-foil-2026-topps-nfl'],
        'rg-parallels', spread=210, heights=[420] * 4)
    # 7 Chrome vs Prizm
    bg = background()
    place(bg, card('brock-bowers-pp-31-las-vegas-raiders-power-players-refractor-2025-topps-chrome-nfl'), W / 2 - 230, H / 2 + 10, 500, 6)
    place(bg, card('amon-ra-st-brown-187-detroit-lions-2025-panini-prizm-nfl'), W / 2 + 230, H / 2 + 10, 500, -6)
    d = ImageDraw.Draw(bg)
    d.ellipse((W / 2 - 62, H / 2 - 52, W / 2 + 62, H / 2 + 72), fill=(255, 59, 48, 255))
    label(d, (W / 2, H / 2 + 10), 'VS', 84)
    save(bg, 'rg-chrome-vs-prizm')
    # 8 NFL Rookies kaufen
    fan(['fernando-mendoza-rookie-91tr-1-las-vegas-raiders-1991-topps-rookies-' + F, 'carnell-tate-rookie-318-tennessee-titans-football-leather-foil-' + F,
         'jeremiyah-love-rookie-309-arizona-cardinals-' + F], 'rg-nfl-rookie-cards-kaufen')
    # 9 Japanische Pokemon
    fan(['bronzong-099-172-holo-pokemon-s12a-vstar-universe-japanisch', 'aegislash-durengard-111-187-holo-pokemon-sv8a-terastal-festival-ex-japanisch',
         'arctibax-frospino-052-190-holo-pokemon-sv4a-shiny-treasure-ex-japanisch'], 'rg-japanische-pokemon')
    # 4 Grading: Karte im Slab mit neutralem Etikett
    bg = background()
    c = card('caleb-williams-35-chicago-bears-' + F)
    ch = 430
    c = c.resize((int(c.width * ch / c.height), ch), Image.LANCZOS)
    sw, shh = c.width + 70, ch + 190
    slab = Image.new('RGBA', (sw, shh), (0, 0, 0, 0))
    sd = ImageDraw.Draw(slab)
    sd.rounded_rectangle((0, 0, sw - 1, shh - 1), 22, fill=(225, 235, 245, 70), outline=(240, 248, 255, 200), width=5)
    sd.rounded_rectangle((18, 18, sw - 19, 128), 10, fill=(245, 245, 245, 255))
    sd.rectangle((18, 18, 30, 128), fill=(255, 59, 48, 255))
    label(sd, (48, 52), '2026 TOPPS', 30, (20, 20, 20, 255), 'lm')
    label(sd, (48, 92), 'C. WILLIAMS #35', 30, (20, 20, 20, 255), 'lm')
    label(sd, (sw - 40, 52), 'GEM MT', 26, (20, 20, 20, 255), 'rm')
    label(sd, (sw - 40, 96), '10', 52, (255, 59, 48, 255), 'rm')
    sd.rounded_rectangle((26, 146, sw - 27, shh - 27), 8, fill=(255, 255, 255, 25))
    slab.alpha_composite(c, (35, 158))
    place(bg, slab, W / 2, H / 2 + 6, 610, -4)
    save(bg, 'rg-grading')
    # 5 Lagern: Karte im Toploader und Karte im Team Bag
    bg = background()
    for nm, cx, ang, kind in (('jayden-daniels-196-washington-commanders-' + F, W / 2 - 200, 5, 'tl'), ('caleb-williams-35-chicago-bears-' + F, W / 2 + 210, -5, 'tb')):
        c = card(nm)
        ch = 400
        c = c.resize((int(c.width * ch / c.height), ch), Image.LANCZOS)
        if kind == 'tl':
            hw, hh = c.width + 56, ch + 56
            h = Image.new('RGBA', (hw, hh), (0, 0, 0, 0))
            hd = ImageDraw.Draw(h)
            hd.rounded_rectangle((0, 0, hw - 1, hh - 1), 14, fill=(230, 240, 250, 55), outline=(245, 250, 255, 210), width=6)
            hd.rectangle((hw / 2 - 40, 0, hw / 2 + 40, 10), fill=(10, 10, 12, 255))
            h.alpha_composite(c, (28, 34))
        else:
            hw, hh = c.width + 30, ch + 70
            h = Image.new('RGBA', (hw, hh), (0, 0, 0, 0))
            hd = ImageDraw.Draw(h)
            hd.rectangle((0, 0, hw - 1, hh - 1), fill=(235, 240, 245, 45), outline=(245, 250, 255, 150), width=3)
            hd.rectangle((0, 0, hw - 1, 46), fill=(235, 240, 245, 70))
            h.alpha_composite(c, (15, 52))
        place(bg, h, cx, H / 2 + 10, 520, ang)
    d = ImageDraw.Draw(bg)
    label(d, (W / 2 - 200, H - 34), 'TOPLOADER', 34, (255, 255, 255, 220))
    label(d, (W / 2 + 210, H - 34), 'TEAM BAG', 34, (255, 255, 255, 220))
    save(bg, 'rg-lagern')
    # 6 Zustand: Karte mit Lupe auf der Ecke
    bg = background()
    c = card('jayden-daniels-196-washington-commanders-' + F)
    place(bg, c, W / 2 - 120, H / 2 + 10, 540, -3)
    big = card('jayden-daniels-196-washington-commanders-' + F, radius=0.035)
    s = 900 / big.height
    big = big.resize((int(big.width * s), 900), Image.LANCZOS)
    zoom = big.crop((big.width - 140, -100, big.width + 100, 140))
    lens = Image.new('RGBA', (240, 240), (10, 10, 12, 255))
    lens.alpha_composite(zoom, (0, 0))
    m = Image.new('L', (240, 240), 0)
    ImageDraw.Draw(m).ellipse((0, 0, 239, 239), fill=255)
    lens.putalpha(m)
    ring = Image.new('RGBA', (270, 270), (0, 0, 0, 0))
    ImageDraw.Draw(ring).ellipse((0, 0, 269, 269), fill=(255, 59, 48, 255))
    ring.alpha_composite(lens, (15, 15))
    bg.alpha_composite(ring, (int(W / 2 + 120), 60))
    d = ImageDraw.Draw(bg)
    d.line([(W / 2 + 62, 130), (W / 2 + 125, 170)], fill=(255, 59, 48, 255), width=6)
    label(d, (W / 2 + 255, 372), 'ECKEN, KANTEN,', 40, (255, 255, 255, 235))
    label(d, (W / 2 + 255, 414), 'OBERFLÄCHE', 40, (255, 255, 255, 235))
    save(bg, 'rg-zustand')
    print('ok', sorted(os.listdir(OUT)))
