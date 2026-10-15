#!/usr/bin/env python3
"""Builds the info.json release asset that the plugin's GitHubUpdater reads.

Usage: build-info.py <tag> > info.json

Fails when the tag, the Version plugin header and BGHL_CONNECTOR_VERSION
disagree, or when CHANGELOG.md has no entry for the version.
"""

import html
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

REPOSITORY_URL = 'https://github.com/dokker/bricks-ghl-connector'
ROOT = Path(__file__).resolve().parents[2]
CHANGELOG_VERSIONS = 5


def fail(message):
    print(f'error: {message}', file=sys.stderr)
    sys.exit(1)


def header(source, name):
    match = re.search(rf'^\s*\*\s*{re.escape(name)}:\s*(.+)$', source, re.M)
    return match.group(1).strip() if match else ''


def inline(text):
    text = html.escape(text, quote=False)
    text = re.sub(r'`([^`]+)`', r'<code>\1</code>', text)
    return re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', text)


def body_html(body):
    """Renders the Keep a Changelog subset used in CHANGELOG.md."""
    out = []
    items = []
    paragraph = []

    def flush():
        if items:
            out.append('<ul>' + ''.join(f'<li>{inline(item)}</li>' for item in items) + '</ul>')
            items.clear()
        if paragraph:
            out.append(f'<p>{inline(" ".join(paragraph))}</p>')
            paragraph.clear()

    for line in body.splitlines():
        stripped = line.strip()
        if not stripped or re.match(r'^\[[^\]]+\]: ', stripped):
            flush()
        elif stripped.startswith('### '):
            flush()
            out.append(f'<p><strong>{inline(stripped[4:])}</strong></p>')
        elif stripped.startswith('- '):
            if paragraph:
                flush()
            items.append(stripped[2:])
        elif items and line.startswith(' '):
            items[-1] += ' ' + stripped
        else:
            if items:
                flush()
            paragraph.append(stripped)

    flush()
    return '\n'.join(out)


def changelog_html(changelog):
    parts = []
    for section in re.split(r'^## ', changelog, flags=re.M)[1:CHANGELOG_VERSIONS + 1]:
        title, _, body = section.partition('\n')
        parts.append(f'<h4>{inline(title.replace("[", "").replace("]", ""))}</h4>')
        parts.append(body_html(body))
    return '\n'.join(parts)


def main():
    if len(sys.argv) != 2:
        fail('usage: build-info.py <tag>')

    tag = sys.argv[1]
    version = tag.removeprefix('v')
    plugin = (ROOT / 'bricks-ghl-connector.php').read_text(encoding='utf-8')
    changelog = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')

    header_version = header(plugin, 'Version')
    constant = re.search(r"define\('BGHL_CONNECTOR_VERSION',\s*'([^']+)'\)", plugin)
    constant_version = constant.group(1) if constant else ''

    if not re.fullmatch(r'\d+\.\d+\.\d+', version):
        fail(f'tag {tag} is not vX.Y.Z')
    if header_version != version:
        fail(f'tag {tag} does not match the Version header ({header_version})')
    if constant_version != version:
        fail(f'tag {tag} does not match BGHL_CONNECTOR_VERSION ({constant_version})')
    if not re.search(rf'^## \[{re.escape(version)}\]', changelog, re.M):
        fail(f'CHANGELOG.md has no entry for {version}')

    info = {
        'name': header(plugin, 'Plugin Name'),
        'slug': 'bricks-ghl-connector',
        'version': version,
        'author': header(plugin, 'Author'),
        'homepage': REPOSITORY_URL,
        'download_url': f'{REPOSITORY_URL}/releases/download/{tag}/bricks-ghl-connector.zip',
        'requires': header(plugin, 'Requires at least'),
        'requires_php': header(plugin, 'Requires PHP'),
        'tested': header(plugin, 'Tested up to'),
        'last_updated': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S GMT'),
        'sections': {
            'description': f'<p>{inline(header(plugin, "Description"))}</p>',
            'changelog': changelog_html(changelog),
        },
    }

    json.dump(info, sys.stdout, indent=2, ensure_ascii=False)
    sys.stdout.write('\n')


main()
