"""Flatbed scan as it is (card, shadow, rounded corners, scanner background) scaled and extended to 1600x1600.
Same scale for every scan, so the card keeps the same size in every image. The extra space is filled with the
scan's own background colour, with a soft blend so there is no visible seam.
Usage: scan1600_pad.py OUTDIR IMG [IMG ...]"""
import sys, os, cv2, numpy as np
OUT, C = sys.argv[1], 1600
SCALE = 1600 / 4560   # 1200 dpi scans are up to ~4540 px high: same factor for all
FEATHER = 40
os.makedirs(OUT, exist_ok=True)
for p in sys.argv[2:]:
    img = cv2.imread(p)
    sm = cv2.resize(img, (round(img.shape[1] * SCALE), round(img.shape[0] * SCALE)), interpolation=cv2.INTER_AREA)
    h, w = sm.shape[:2]
    b = 6
    border = np.concatenate([sm[:b].reshape(-1, 3), sm[-b:].reshape(-1, 3), sm[:, :b].reshape(-1, 3), sm[:, -b:].reshape(-1, 3)])
    bg = np.median(border, axis=0)
    canvas = np.empty((C, C, 3), np.float32); canvas[:] = bg
    # alpha ramps from 0 at the scan edge to 1 after FEATHER px, so the scan fades into the fill colour
    yy = np.minimum(np.arange(h), np.arange(h)[::-1])[:, None]
    xx = np.minimum(np.arange(w), np.arange(w)[::-1])[None, :]
    a = np.clip(np.minimum(yy, xx) / FEATHER, 0, 1)[..., None].astype(np.float32)
    ox, oy = (C - w) // 2, (C - h) // 2
    region = canvas[oy:oy + h, ox:ox + w]
    canvas[oy:oy + h, ox:ox + w] = sm * a + region * (1 - a)
    name = os.path.splitext(os.path.basename(p))[0] + '.jpg'
    cv2.imwrite(os.path.join(OUT, name), np.clip(canvas, 0, 255).astype(np.uint8), [cv2.IMWRITE_JPEG_QUALITY, 92])
    print(name, 'scan %dx%d -> %dx%d on %dx%d, fill %s' % (img.shape[1], img.shape[0], w, h, C, C, bg.astype(int).tolist()))
