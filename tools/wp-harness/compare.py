import json, sys, os, glob
B, A = sys.argv[1], sys.argv[2]
short = lambda s: s.replace('https://hodima.test', '')
for f in sorted(glob.glob(B + '/*.json')):
    name = os.path.basename(f)[:-5]
    b = json.load(open(f)); a = json.load(open(os.path.join(A, name + '.json')))
    out = []
    if a['script_count'] > 1: out.append(f'  !! after has {a["script_count"]} scripts')
    for eid in sorted(set(b['entities']) | set(a['entities'])):
        be, ae = b['entities'].get(eid), a['entities'].get(eid)
        if be is None: out.append(f'  + NEW ENTITY {short(eid)} {ae.get("@type")}'); continue
        if ae is None: out.append(f'  - LOST ENTITY {short(eid)} {be.get("@type")}'); continue
        for k in sorted(set(be) | set(ae)):
            bv, av = be.get(k, []), ae.get(k, [])
            if bv != av:
                lost = [v for v in bv if v not in av]; new = [v for v in av if v not in bv]
                out.append(f'  ~ {short(eid)} .{k}: lost={[short(x)[:140] for x in lost]} new={[short(x)[:140] for x in new]}')
    ba, aa = b['anonymous'], a['anonymous']
    if ba != aa:
        out.append(f'  ~ anonymous: before={len(ba)} after={len(aa)} lost={[x[:100] for x in ba if x not in aa]} new={[x[:100] for x in aa if x not in ba]}')
    print(f'== {name}: scripts {b["script_count"]} -> {a["script_count"]}' + ('' if out else '  (identical)'))
    for l in out: print(l)
