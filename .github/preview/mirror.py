"""Mirror the public pages of a running catlab-events into static HTML.

Crawls from a few start pages, follows links to public pages only, and
downloads the CSS, JS, fonts and images those pages use. Every link is
rewritten to a relative path, so the result works under any URL prefix
(GitHub Pages serves it at /<repo>/pr/<number>/).

Links to anything that is not mirrored (login, admin, checkout posts...)
point at preview-info/unavailable.html instead. No file or folder starts
with an underscore, so GitHub Pages' Jekyll step cannot drop any. Forms are not functional.

    python3 mirror.py --site http://127.0.0.1:8000 --assets http://127.0.0.1:8099 \
        --out _site --banner "Preview of #94"
"""
import argparse, html, os, posixpath, re, sys, urllib.error, urllib.request
from collections import deque
from urllib.parse import urljoin, urlsplit, urlunsplit

# Public pages worth mirroring. Anything else is "unavailable".
PAGES = [
    r'/', r'/events', r'/calendar', r'/archive', r'/press', r'/competitions',
    r'/competitions/\d+', r'/events/\d+', r'/e/\d+(/[^/]*)?', r'/s/[^/]+(/[^/]*)?',
    r'/events/\d+/register(/\d+)?', r'/events/\d+/waitinglist', r'/events/\d+/scores',
    r'/organisations/\d+', r'/venues/\d+', r'/author/\d+(/[^/]*)?',
    r'/documents/nl/(privacy|tos)',
]
PAGES = [re.compile('^' + p + '$') for p in PAGES]
STATIC = re.compile(r'\.(css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|otf|json|xml|txt|webmanifest)$', re.I)
START = ['/', '/events', '/calendar', '/archive']
MAX_PAGES = 300
UNAVAILABLE = 'preview-info/unavailable.html'

ATTR = re.compile(r'''(\s(?:href|src|action|poster|data-src|data-href|data-background|content)\s*=\s*)(["'])(.*?)\2''', re.I | re.S)
SRCSET = re.compile(r'''(\s(?:srcset|data-srcset)\s*=\s*)(["'])(.*?)\2''', re.I | re.S)
CSSURL = re.compile(r'''url\(\s*(["']?)([^"')]+)\1\s*\)''', re.I)
FORM = re.compile(r'<form\b[^>]*>', re.I)


class Mirror:
    def __init__(self, site, assets, out, banner):
        self.site = site.rstrip('/')
        self.assets = assets.rstrip('/') if assets else None
        self.out = out
        self.banner = banner
        self.origins = self._origins(self.site)
        self.asset_origins = self._origins(self.assets) if self.assets else set()
        self.queue = deque()
        self.seen = set()
        self.files = {}  # url -> local path (or None when it failed)
        self.pages = 0
        self.failed = []

    @staticmethod
    def _origins(base):
        parts = urlsplit(base)
        return {parts.netloc, parts.netloc.replace('127.0.0.1', 'localhost')}

    # --- url -> local file ------------------------------------------------

    def classify(self, url):
        """Return (kind, absolute url) for a url as found in a page."""
        url = html.unescape(url.strip())
        if not url or url.startswith(('#', 'mailto:', 'tel:', 'javascript:', 'data:', '{{')):
            return None, url
        absolute = urljoin(self.site + '/', url) if not url.startswith('//') else 'http:' + url
        parts = urlsplit(absolute)
        if parts.netloc in self.asset_origins:
            return 'asset', absolute
        if parts.netloc not in self.origins:
            return None, url
        path = parts.path or '/'
        if path != '/' and path.endswith('/'):
            path = path.rstrip('/')
        if any(p.match(path) for p in PAGES) and not path.endswith(('.html', '.php')):
            return 'page', urlunsplit(('http', parts.netloc, path, parts.query, ''))
        if STATIC.search(path):
            return 'static', urlunsplit(('http', parts.netloc, path, '', ''))
        return 'unavailable', absolute

    @staticmethod
    def _query_suffix(query):
        return re.sub(r'[^A-Za-z0-9._-]+', '_', query).strip('_')

    def local_path(self, kind, url):
        parts = urlsplit(url)
        if kind == 'page':
            path = parts.path.strip('/')
            if parts.query:
                path = posixpath.join(path, 'query-' + self._query_suffix(parts.query))
            return posixpath.join(path, 'index.html') if path else 'index.html'
        if kind == 'static':
            return parts.path.lstrip('/')
        if kind == 'asset':
            name = parts.path.rsplit('/', 1)[-1] or 'asset'
            stem, ext = posixpath.splitext(name)
            if parts.query:
                stem += '__' + self._query_suffix(parts.query)
            return posixpath.join('preview-assets', stem + ext)
        return UNAVAILABLE

    @staticmethod
    def relative(target, from_file):
        rel = posixpath.relpath(target, posixpath.dirname(from_file) or '.')
        if rel.endswith('index.html') and (rel == 'index.html' or rel.endswith('/index.html')):
            rel = rel[:-len('index.html')] or './'
        return rel

    # --- fetching ---------------------------------------------------------

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None

    opener = urllib.request.build_opener(NoRedirect)

    def fetch(self, url):
        request = urllib.request.Request(url, headers={
            'X-Forwarded-Proto': 'http',
            'User-Agent': 'catlab-events-preview',
            'Accept-Language': 'nl',
        })
        try:
            with self.opener.open(request, timeout=60) as response:
                return response.status, response.headers.get('Content-Type', ''), response.read()
        except urllib.error.HTTPError as error:
            if 300 <= error.code < 400 and error.headers.get('Location'):
                return error.code, 'redirect', error.headers['Location'].encode()
            return error.code, error.headers.get('Content-Type', ''), error.read()
        except Exception as error:  # connection refused, timeout...
            return 0, '', str(error).encode()

    def resolve(self, kind, url):
        """Local path for a url, queueing it if it is new. None = keep as is."""
        if kind == 'unavailable':
            return UNAVAILABLE
        local = self.local_path(kind, url)
        if url not in self.seen:
            self.seen.add(url)
            if kind == 'page' and self.pages >= MAX_PAGES:
                return UNAVAILABLE
            if kind == 'page':
                self.pages += 1
            self.queue.append((kind, url, local))
        return local

    def rewrite_url(self, raw, from_file, page_url):
        if raw.strip().startswith(('#', 'mailto:', 'tel:', 'javascript:', 'data:')):
            return raw
        fragment = ''
        value = html.unescape(raw)
        if '#' in value:
            value, fragment = value.split('#', 1)
            fragment = '#' + fragment
        kind, absolute = self.classify(urljoin(page_url, value) if value else page_url)
        if kind is None:
            return raw
        local = self.resolve(kind, absolute)
        return html.escape(self.relative(local, from_file) + fragment, quote=True)

    # --- rewriting --------------------------------------------------------

    def rewrite_css(self, text, from_file, base_url):
        def repl(m):
            value = m.group(2).strip()
            if value.startswith('data:'):
                return m.group(0)
            kind, absolute = self.classify(urljoin(base_url, value))
            if kind not in ('static', 'asset'):
                return m.group(0)
            local = self.resolve(kind, absolute)
            return 'url(%s%s%s)' % (m.group(1), self.relative(local, from_file), m.group(1))
        return CSSURL.sub(repl, text)

    def rewrite_html(self, text, from_file, page_url):
        def attr(m):
            name = m.group(1).strip().lower()
            value = m.group(3)
            if name.startswith('content'):
                # Only rewrite meta content that is a url of ours (og:image...).
                if not re.match(r'(https?:)?//', value):
                    return m.group(0)
            return m.group(1) + m.group(2) + self.rewrite_url(value, from_file, page_url) + m.group(2)

        def srcset(m):
            items = []
            for item in m.group(3).split(','):
                bits = item.strip().split(None, 1)
                if not bits:
                    continue
                bits[0] = self.rewrite_url(bits[0], from_file, page_url)
                items.append(' '.join(bits))
            return m.group(1) + m.group(2) + ', '.join(items) + m.group(2)

        def form(m):
            tag = re.sub(r'''\smethod\s*=\s*(["']).*?\1''', '', m.group(0), flags=re.I)
            return tag[:-1] + ' method="get">'

        text = ATTR.sub(attr, text)
        text = SRCSET.sub(srcset, text)
        text = CSSURL.sub(lambda m: m.group(0) if m.group(2).startswith('data:') else
                          'url(%s%s%s)' % (m.group(1), self.rewrite_url(m.group(2), from_file, page_url), m.group(1)), text)
        text = FORM.sub(form, text)
        if self.banner:
            text = re.sub(r'</body>', self.banner_html() + '</body>', text, count=1, flags=re.I)
        return text

    def banner_html(self):
        return ('<div style="position:fixed;left:8px;bottom:8px;z-index:2147483647;padding:4px 10px;'
                'border-radius:999px;background:rgba(20,20,20,.78);color:#fff;font:12px/1.4 system-ui,sans-serif;'
                'pointer-events:none">%s</div>' % html.escape(self.banner))

    # --- main loop --------------------------------------------------------

    def write(self, local, data):
        path = os.path.join(self.out, local)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'wb') as handle:
            handle.write(data)

    def run(self, start):
        for path in start:
            kind, url = self.classify(path)
            self.resolve(kind, url)
        while self.queue:
            kind, url, local = self.queue.popleft()
            fetch_url = url
            if kind != 'asset':
                parts = urlsplit(url)
                fetch_url = urlunsplit(urlsplit(self.site)[:2] + parts[2:])
            status, content_type, body = self.fetch(fetch_url)
            if content_type == 'redirect' and kind == 'page':
                # Static hosting cannot redirect: write a page that does.
                target = self.rewrite_url(urljoin(url, body.decode()), local, url)
                self.write(local, REDIRECT_HTML.format(url=target).encode('utf-8'))
                print('  redirect %s -> %s' % (local, html.unescape(target)))
                continue
            if status != 200:
                self.failed.append((status, url))
                print('  %s %s' % (status or 'ERR', url), file=sys.stderr)
                if kind == 'page' and status >= 500:
                    # Keep the error page: a broken page is what a preview should show.
                    self.write(local, body)
                continue
            if kind == 'page' and 'html' in content_type:
                body = self.rewrite_html(body.decode('utf-8', 'replace'), local, url).encode('utf-8')
            elif local.endswith('.css'):
                body = self.rewrite_css(body.decode('utf-8', 'replace'), local, url).encode('utf-8')
            self.write(local, body)
            print('  %s %s' % (kind, local))
        self.write(UNAVAILABLE, UNAVAILABLE_HTML.encode('utf-8'))


REDIRECT_HTML = '''<!doctype html><html><head><meta charset="utf-8">
<meta http-equiv="refresh" content="0; url={url}"><link rel="canonical" href="{url}"></head>
<body><a href="{url}">Continue</a></body></html>
'''

UNAVAILABLE_HTML = '''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Not in the preview</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:32em;margin:15vh auto;padding:0 16px;color:#222}</style></head>
<body><h1>Not in the preview</h1><p>This PR preview is a static snapshot of the public pages with demo data.
Logging in, ordering and other pages that need the live app are not part of it.</p>
<p><a href="javascript:history.back()">Go back</a></p></body></html>
'''


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--site', required=True)
    parser.add_argument('--assets')
    parser.add_argument('--out', required=True)
    parser.add_argument('--banner', default='')
    parser.add_argument('--start', action='append')
    args = parser.parse_args()

    mirror = Mirror(args.site, args.assets, args.out, args.banner)
    mirror.run(args.start or START)
    print('Mirrored %d pages; %d urls failed.' % (mirror.pages, len(mirror.failed)))
    broken = [u for s, u in mirror.failed if s == 0 or s >= 500]
    for url in broken:
        # Shown as an annotation on the run; the preview still deploys.
        print('::warning title=Preview page failed::%s' % url)


if __name__ == '__main__':
    main()
