#!/usr/bin/env bash
# Rebuilds assets/templates/Agenda_Item_Report_Template.dotx from
# scripts/build_report_template.js. Needs node with the `docx` npm package
# available (npm install -g docx) and python3.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/assets/templates/Agenda_Item_Report_Template.dotx"
TMP="$(mktemp --suffix=.docx)"
trap 'rm -f "$TMP"' EXIT

mkdir -p "$(dirname "$OUT")"
node "$ROOT/scripts/build_report_template.js" "$TMP"

# A .dotx is a .docx whose main part is typed as a template
python3 - "$TMP" "$OUT" <<'EOF'
import sys, zipfile
src, dst = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(src) as zin, zipfile.ZipFile(dst, 'w', zipfile.ZIP_DEFLATED) as zout:
    for item in zin.infolist():
        data = zin.read(item.filename)
        if item.filename == '[Content_Types].xml':
            data = data.replace(
                b'wordprocessingml.document.main+xml',
                b'wordprocessingml.template.main+xml')
        elif item.filename == 'word/document.xml':
            # Write checkbox glyphs as text runs, the form Word itself saves;
            # some renderers misdraw docx-js's <w:sym> form as ticked
            data = data.replace(
                b'<w:r><w:sym w:char="2610" w:font="MS Gothic"/></w:r>',
                '<w:r><w:rPr><w:rFonts w:ascii="MS Gothic" w:eastAsia="MS Gothic" w:hAnsi="MS Gothic"/></w:rPr><w:t>\u2610</w:t></w:r>'.encode())
        zout.writestr(item, data)
print(f'Wrote {dst}')
EOF
