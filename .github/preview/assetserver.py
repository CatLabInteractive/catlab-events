"""Stand-in for CentralStorage in the PR previews.

Serves the demo posters in ./assets by asset key, resized the way
CentralStorage does it: ?width=W scales, ?width=W&height=H crops to fit.

    python3 assetserver.py [port] [bind address]

The bind address defaults to 127.0.0.1. The docker compose `assets`
service passes 0.0.0.0, so the port is reachable from outside the container.
"""
import http.server, io, os, sys
from urllib.parse import urlparse, parse_qs
from PIL import Image, ImageOps

ASSETS = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'assets')
PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8099
BIND = sys.argv[2] if len(sys.argv) > 2 else '127.0.0.1'


class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        url = urlparse(self.path)
        key = url.path.rsplit('/', 1)[-1]
        query = parse_qs(url.query)
        path = os.path.join(ASSETS, os.path.basename(key))
        if not os.path.isfile(path):
            self.send_response(404)
            self.end_headers()
            return
        image = Image.open(path)
        width = int(query.get('width', [0])[0] or 0)
        height = int(query.get('height', [0])[0] or 0)
        if width and height:
            image = ImageOps.fit(image, (width, height))
        elif width:
            image = image.resize((width, round(image.height * width / image.width)))
        fmt = 'PNG' if key.endswith('.png') else 'JPEG'
        buffer = io.BytesIO()
        image.save(buffer, fmt)
        self.send_response(200)
        self.send_header('Content-Type', 'image/' + fmt.lower())
        self.end_headers()
        self.wfile.write(buffer.getvalue())

    def log_message(self, *args):
        pass


http.server.ThreadingHTTPServer((BIND, PORT), Handler).serve_forever()
