#!/usr/bin/env python3
"""Exercise the registered CoBudget setup check and Nextcloud's HTTP client against a loopback fixture."""
import argparse
from http.server import BaseHTTPRequestHandler, HTTPServer
import json
import os
from pathlib import Path
import subprocess
from tempfile import TemporaryDirectory
from threading import Thread

CHECK = r'''
define('OC_CONSOLE', true);
require 'lib/base.php';
OC_App::loadApps();
$registrations = OCP\Server::get(OC\AppFramework\Bootstrap\Coordinator::class)->getRegistrationContext()->getSetupChecks();
foreach ($registrations as $registration) {
    if ($registration->getService() === OCA\CoBudget\SetupChecks\ApiRequestBodyLimit::class) {
        echo json_encode(OCP\Server::get($registration->getService())->run(), JSON_THROW_ON_ERROR);
        return;
    }
}
throw new RuntimeException('CoBudget setup check is not registered.');
'''


class BoundaryFixture(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_POST(self):
        transfer = self.headers.get('Transfer-Encoding')
        if transfer == 'chunked' and 'Content-Length' not in self.headers:
            body = bytearray()
            while True:
                size = int(self.rfile.readline(), 16)
                if size == 0:
                    self.rfile.readline()
                    break
                body.extend(self.rfile.read(size))
                assert self.rfile.readline() == b'\r\n'
            framing = 'chunked'
        else:
            assert transfer is None
            body = self.rfile.read(int(self.headers['Content-Length']))
            framing = 'fixed'
        size = len(body)
        assert size in (65536, 65537) and body == b'{}'.ljust(size)
        assert 'Authorization' not in self.headers and 'Cookie' not in self.headers
        mode, path = self.path[1:].split('/', 1)
        assert path in PATHS
        self.server.observed.append((path, framing, size))
        code = 401
        if mode == 'unavailable' or (mode == 'index-only' and path == PATHS[1]):
            code = 404
        elif mode == 'redirect':
            code = 307
        elif size > 65536 and not (mode == 'partial' and path == PATHS[-1] and framing == 'chunked'):
            code = 413
        self.send_response(code)
        if code == 401:
            self.send_header('WWW-Authenticate', 'Basic realm="Proxy"' if mode == 'proxy' else 'Bearer realm="CoBudget API"')
        if code == 307:
            self.send_header('Location', '/unexpected-redirect')
        self.send_header('Content-Length', '0')
        self.end_headers()


PATHS = (
    'index.php/apps/cobudget/api/v1/entries',
    'apps/cobudget/api/v1/entries',
    'index.php//apps/cobudget/api/v1/entries',
)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('nextcloud', nargs='+', type=Path, help='Local Nextcloud server directory with CoBudget enabled')
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    with TemporaryDirectory() as config_dir, HTTPServer(('127.0.0.1', 0), BoundaryFixture) as fixture:
        # Load the installation's configuration by reference. Only this PHP process
        # sees the temporary URL. Never write to the installation's config directory.
        config = Path(config_dir)
        (config / 'config.php').write_text("""<?php
            $source = getenv('COBUDGET_TEST_CONFIG');
            require $source . '/config.php';
            $values = $CONFIG;
            foreach (glob($source . '/*.config.php') as $file) {
                $CONFIG = [];
                require $file;
                $values = array_replace_recursive($values, $CONFIG);
            }
            $CONFIG = $values;
            $CONFIG['overwrite.cli.url'] = getenv('COBUDGET_TEST_URL');
            $CONFIG['config_is_read_only'] = true;
        """)
        worker = Thread(target=fixture.serve_forever, daemon=True)
        worker.start()
        try:
            for server in args.nextcloud:
                for mode, expected in [('protected', 'success'), ('index-only', 'success'), ('partial', 'warning'),
                                       ('proxy', 'warning'), ('redirect', 'warning'), ('unavailable', 'warning')]:
                    fixture.observed = []
                    env = {**os.environ, 'NEXTCLOUD_CONFIG_DIR': config_dir,
                           'COBUDGET_TEST_CONFIG': str((server / 'config').resolve()),
                           'COBUDGET_TEST_URL': f'http://127.0.0.1:{fixture.server_port}/{mode}'}
                    output = subprocess.check_output([args.php, '-d', 'memory_limit=512M', '-r', CHECK],
                                                     cwd=server, env=env, text=True)
                    result = json.loads(output)
                    assert result['severity'] == expected and fixture.observed, (mode, result)
                    if mode in ('protected', 'partial'):
                        assert set(fixture.observed) == {
                            (path, framing, size) for path in PATHS
                            for framing in ('fixed', 'chunked') for size in (65536, 65537)
                        }
                    if mode == 'partial':
                        missing_description = result['description']
                    if mode == 'proxy':
                        assert result['description'] != missing_description
                    if expected == 'warning':
                        assert result['linkToDoc'].endswith('/admin/cobudget#cobudget-api-request-limit')
                        assert result['descriptionParameters']['guide']['link'] == result['linkToDoc']
                    print(f'PASS {server}: {mode} ({len(fixture.observed)} requests)')
        finally:
            fixture.shutdown()
            worker.join()


if __name__ == '__main__':
    main()
