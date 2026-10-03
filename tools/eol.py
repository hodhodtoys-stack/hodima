"""Restore line endings after edits.

Many files in this repo use CRLF (some mixed). Editors/scripts often rewrite a
whole file as LF, so `git diff` shows every line as changed. For each modified
tracked file this keeps unchanged lines byte-identical to HEAD and writes
changed/added lines with the file's dominant ending.

Usage (from anywhere inside the repo):  python3 tools/eol.py
"""
import os
os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
import subprocess, difflib, sys
files = subprocess.run(['git','diff','--name-only'],capture_output=True,text=True).stdout.split()
for f in files:
    if not os.path.exists(f): continue  # فایل حذف‌شده
    if f.endswith(('.zip','.png','.jpg','.jpeg','.gif','.webp','.woff','.woff2','.mo')): continue  # فایل باینری
    orig = subprocess.run(['git','show','HEAD:'+f],capture_output=True).stdout.decode('utf-8').splitlines(keepends=True)
    cur  = open(f,encoding='utf-8',newline='').read().splitlines(keepends=True)
    strip = lambda l: l.rstrip('\r\n')
    crlf = sum(1 for l in orig if l.endswith('\r\n')); lf = sum(1 for l in orig if l.endswith('\n') and not l.endswith('\r\n'))
    dom = '\r\n' if crlf >= lf else '\n'
    a=[strip(l) for l in orig]; b=[strip(l) for l in cur]
    out=[]
    for tag,i1,i2,j1,j2 in difflib.SequenceMatcher(None,a,b,autojunk=False).get_opcodes():
        if tag=='equal':
            out.extend(orig[i1:i2])
        else:
            for k in range(j1,j2):
                last = (k==len(b)-1) and not cur[-1].endswith(('\n','\r'))
                out.append(b[k] + ('' if last else dom))
    # preserve final newline state of original if last line equal
    open(f,'w',encoding='utf-8',newline='').write(''.join(out))
    print(f, 'dom=CRLF' if dom=='\r\n' else 'dom=LF', f'crlf={crlf} lf={lf}')
