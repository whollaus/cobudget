#!/usr/bin/env python3
"""Install the supplied CoBudget package on a new SQLite instance for each supported major."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import tarfile
import tempfile
import xml.etree.ElementTree as ET

HERE = Path(__file__).resolve().parent
APP = HERE.parents[1]


def copy_server(source, server):
    # Only take distribution code and bundled apps. Never copy local config, databases or app links.
    shipped = json.loads((source / 'core/shipped.json').read_text())['shippedApps']
    manifest = json.loads((source / 'core/signature.json').read_text())['hashes']
    roots = sorted({p.split('/')[0] for p in manifest})
    server.mkdir()
    (server / 'apps').mkdir()
    (server / 'config').mkdir()
    for directory, names, target in [(source, roots, server), (source / 'apps', shipped, server / 'apps')]:
        paths = []
        for name in names:
            path = directory / name
            if (Path(name).name != name or name in ('', '.', '..', 'apps', 'config', 'data', 'cobudget')
                    or path.is_symlink()):
                raise ValueError(f'Unexpected distribution path: {path}')
            paths.append(path)
        subprocess.run(['cp', '-cR' if sys.platform == 'darwin' else '-a', *paths, target], check=True)
    (server / 'config/config.php').write_text("<?php $CONFIG = ['appstoreenabled' => false, 'updatechecker' => false];\n")


def install(archive, major, version, template, php):
    with tempfile.TemporaryDirectory(prefix=f'cobudget-fresh-{major}-') as scratch:
        base = Path(scratch).resolve()
        server = base / 'server'
        print(f'Installing fresh Nextcloud {major} and CoBudget {version} …', flush=True)
        copy_server(template, server)
        env = {'CI': '1', 'PATH': os.environ['PATH'], 'HOME': str(base), 'TMPDIR': str(base), 'OC_PASS': secrets.token_urlsafe(32)}
        command = [php, '-d', 'memory_limit=512M']

        def occ(*args, output=False):
            result = subprocess.run([*command, 'occ', *args], cwd=server, env=env, text=True,
                                    stdout=subprocess.PIPE if output else None)
            if result.returncode:
                # maintenance:install receives a temporary password on the command line.
                raise RuntimeError(f'Nextcloud {major}: occ {args[0]} exited with {result.returncode}')
            return result.stdout

        occ('maintenance:install', '--database=sqlite', '--admin-user=release-admin',
            '--admin-pass=' + secrets.token_urlsafe(32), '--data-dir=' + str(base / 'data'), '--no-interaction')
        if (server / 'apps/cobudget').exists():
            raise RuntimeError('CoBudget already exists on fresh instance')
        archive.extractall(server / 'apps', filter='data')
        occ('app:enable', 'cobudget')
        enabled = json.loads(occ('app:list', '--output=json', output=True))['enabled']
        if enabled.get('cobudget') != version:
            raise RuntimeError(f'Expected enabled CoBudget {version}, got {enabled.get("cobudget")}')
        if (server / 'apps/cobudget/appinfo/signature.json').exists():
            occ('integrity:check-app', 'cobudget')
        occ('user:add', 'release-user', '--password-from-env')
        (base / 'fresh-install-test').touch()
        for phase in ('create', 'verify'):
            result = subprocess.run([*command, HERE / 'fresh-install-payments.php', phase], cwd=server,
                                    env=env, check=True, stdout=subprocess.PIPE, text=True)
            if f'PASS payments {phase}' not in result.stdout.splitlines():
                raise RuntimeError(f'Payment fixture did not complete {phase}: {result.stdout}')
            print(result.stdout.strip(), flush=True)
        occ('cobudget:background-jobs:check')
        occ('cobudget:integrity:check')
        status = json.loads(occ('status', '--output=json', output=True))
        if (status['installed'] is not True or status['maintenance'] or status['needsDbUpgrade']
                or int(status['version'].split('.')[0]) != major):
            raise RuntimeError(f'Unexpected final Nextcloud status: {status}')
        print(f'PASS Nextcloud {status["versionstring"]}, CoBudget {version}, SQLite, payments and shares', flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    parser.add_argument('--templates', type=Path, default=APP.parent / 'local-nextcloud',
                        help='Directory containing <major>/server distributions')
    parser.add_argument('--php', default=os.environ.get('PHP_BIN', 'php'))
    args = parser.parse_args()
    php = shutil.which(args.php)
    if not php:
        parser.error(f'Cannot find PHP: {args.php}')
    with args.archive.open('rb') as file:
        print('Testing', args.archive, 'SHA-256', hashlib.file_digest(file, 'sha256').hexdigest(), flush=True)
        file.seek(0)
        with tarfile.open(fileobj=file, mode='r:gz') as archive:
            for member in archive.getmembers():
                path = Path(member.name)
                if (path.parts[:1] != ('cobudget',) or '..' in path.parts
                        or not (member.isfile() or member.isdir())):
                    raise ValueError(f'Unexpected archive entry: {member.name}')
            info = ET.fromstring(archive.extractfile('cobudget/appinfo/info.xml').read())
            if info.findtext('id') != 'cobudget':
                parser.error('Expected a CoBudget archive')
            nc = info.find('dependencies/nextcloud')
            first, last = (int(nc.attrib[key]) for key in ('min-version', 'max-version'))
            if first > last:
                parser.error('Invalid Nextcloud version range')
            for major in range(first, last + 1):
                install(archive, major, info.findtext('version'), args.templates / str(major) / 'server', php)


if __name__ == '__main__':
    main()
