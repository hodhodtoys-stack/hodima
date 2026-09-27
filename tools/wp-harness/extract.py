import json, re, sys
def blocks(html):
    out = []
    for m in re.finditer(r'<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>', html, re.S | re.I):
        try: out.append(json.loads(m.group(1)))
        except Exception as e: out.append({'__INVALID__': m.group(1)[:200]})
    return out
def nodes(b):
    if isinstance(b, dict) and '@graph' in b: ns = b['@graph']
    elif isinstance(b, list): ns = b
    else: ns = [b]
    res = []
    for n in ns:
        if isinstance(n, dict):
            n = {k: v for k, v in n.items() if k != '@context'}
        res.append(n)
    return res
def canon(v): return json.dumps(v, ensure_ascii=False, sort_keys=True)
def effective(html):
    """Google-style view: nodes sharing @id are one entity; every key keeps the set of distinct values seen."""
    ents, anon = {}, []
    for b in blocks(html):
        for n in nodes(b):
            if isinstance(n, dict) and '@id' in n:
                e = ents.setdefault(n['@id'], {})
                for k, v in n.items():
                    if k == '@id': continue
                    vals = v if isinstance(v, list) and k != '@type' else [v]
                    s = e.setdefault(k, [])
                    for x in vals:
                        c = canon(x)
                        if c not in s: s.append(c)
            else:
                anon.append(canon(n))
    for e in ents.values():
        for k in e: e[k] = sorted(e[k])
    return {'entities': ents, 'anonymous': sorted(anon), 'script_count': len(blocks(html))}
if __name__ == '__main__':
    html = open(sys.argv[1], encoding='utf-8').read()
    if len(sys.argv) > 2 and sys.argv[2] == 'raw':
        for b in blocks(html): print(json.dumps(b, ensure_ascii=False, indent=1))
    else:
        print(json.dumps(effective(html), ensure_ascii=False, indent=1, sort_keys=True))
