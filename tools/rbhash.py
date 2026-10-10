import unicodedata, re
def norm(s):
    return re.sub(r'\s+', ' ', unicodedata.normalize('NFC', s)).strip()
def h(sku, title):
    x = 0x811c9dc5
    for c in norm(sku + '|' + title).encode('utf-8'):
        x ^= c
        x = (x * 0x01000193) & 0xffffffff
    s, d = '', '0123456789abcdefghijklmnopqrstuvwxyz'
    while True:
        s = d[x % 36] + s; x //= 36
        if not x: break
    return s
