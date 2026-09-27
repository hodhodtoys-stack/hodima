"""Graph integrity per page: duplicate top-level @id inside one script, and
references ({"@id": X} with no other key) that no node on the page defines.
Cross-page refs (topic cluster parents/children, related product) are expected.
Usage: python3 integrity.py <outdir> [<outdir> ...]"""
import json, sys, glob, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); import extract

def walk(x, defined, refs):
    if isinstance(x, dict):
        if '@id' in x:
            (refs if len(x) == 1 else defined).append(x['@id'])
        for v in x.values(): walk(v, defined, refs)
    elif isinstance(x, list):
        for v in x: walk(v, defined, refs)

for d in sys.argv[1:]:
    print('###', d)
    for f in sorted(glob.glob(os.path.join(d, '*.html'))):
        html = open(f, encoding='utf-8').read()
        dup, defined, refs = [], [], []
        for b in extract.blocks(html):
            seen = set()
            for n in extract.nodes(b):
                if isinstance(n, dict) and '@id' in n:
                    if n['@id'] in seen: dup.append(n['@id'])
                    seen.add(n['@id'])
            walk(b, defined, refs)
        dangling = sorted(set(refs) - set(defined))
        short = lambda s: s.replace('https://hodima.test', '')
        print(f'  {os.path.basename(f):18} dup={[short(x) for x in dup]} dangling={[short(x) for x in dangling]}')
