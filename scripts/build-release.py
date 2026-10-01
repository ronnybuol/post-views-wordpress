#!/usr/bin/env python3
"""Build an installable WordPress ZIP with a stable plugin folder name."""
from pathlib import Path
import hashlib
import re
import zipfile

root = Path(__file__).resolve().parents[1]
plugin = root / 'zona-simple-views'
header = (plugin / 'zona-simple-views.php').read_text()
version = re.search(r'^\s*\* Version: (\d+\.\d+\.\d+)\s*$', header, re.M).group(1)
destination = root / 'releases'
destination.mkdir(exist_ok=True)
archive = destination / f'zona-simple-views-{version}.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in sorted(plugin.rglob('*')):
        if not path.is_file():
            continue
        info = zipfile.ZipInfo(path.relative_to(root).as_posix(), (2026, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        output.writestr(info, path.read_bytes())
with zipfile.ZipFile(archive) as output:
    assert output.testzip() is None
    assert f'zona-simple-views/assets/quick-edit.js' in output.namelist()
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(destination / f'zona-simple-views-{version}.sha256').write_text(f'{digest}  {archive.name}\n')
print(f'{archive}\nSHA256: {digest}')
