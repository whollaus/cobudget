#!/usr/bin/env python3
"""Destructive tests ONLY in a newly created, disposable Nextcloud installation.

Server engines are accepted only through an explicitly supplied disposable Docker
service container, or a freshly initialized local server via --db-bin. Database
names are generated here, never taken from a live Nextcloud config. The source server and repository are never modified.
"""
import argparse
import json
import os
from pathlib import Path
import secrets
import selectors
import shutil
import sqlite3
import socket
import subprocess
import sys
import tarfile
import tempfile
import time


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', required=True, type=Path, help='Nextcloud source/template; config is discarded before PHP runs')
    parser.add_argument('--php', default='php')
    parser.add_argument('--database', choices=['sqlite', 'mysql', 'pgsql'], default='sqlite')
    parser.add_argument('--db-container', help='Disposable CI database container ID')
    parser.add_argument('--db-bin', type=Path, help='MariaDB/PostgreSQL bin directory; starts and removes a private temporary server')
    parser.add_argument('--db-port', type=int)
    parser.add_argument('--redis-port', type=int, help='Optional disposable Redis service on 127.0.0.1')
    parser.add_argument('--evidence', required=True, type=Path, help='Logs only; no database dumps or configuration are retained')
    args = parser.parse_args()
    if args.db_bin and (args.database == 'sqlite' or args.db_container or args.db_port):
        parser.error('--db-bin requires mysql/pgsql and cannot use a container or external port')
    if args.database != 'sqlite' and not args.db_bin and (not args.db_container or not args.db_port):
        parser.error('Server engines require a disposable container and mapped port, or --db-bin')
    if args.db_bin:
        args.db_bin = args.db_bin.resolve()
    repo = Path(__file__).resolve().parents[2]
    source = args.source.resolve()
    if not (source / 'lib/base.php').is_file():
        parser.error('Nextcloud source missing')
    php = shutil.which(args.php)
    if not php:
        parser.error('PHP executable not found')
    args.evidence.mkdir(parents=True, exist_ok=True)
    (args.evidence / 'result.txt').unlink(missing_ok=True)
    base = Path(tempfile.mkdtemp(prefix='cobudget-upgrade-')).resolve()
    server = base / 'server'
    app = server / 'apps/cobudget'
    data = base / 'data'
    dbname = 'cb_upgrade_' + secrets.token_hex(6)
    env = {**os.environ, 'CI': '1', 'OC_PASS': secrets.token_urlsafe(24)}
    log = (args.evidence / 'run.log').open('w')
    created_database = False
    database_process = None

    def run(command, *, input_file=None, output_file=None):
        result = subprocess.run([str(x) for x in command], cwd=server, env=env,
                                stdin=input_file, stdout=output_file or log, stderr=log, timeout=300)
        if result.returncode:
            raise RuntimeError(f'Command failed ({result.returncode}); see {args.evidence / "run.log"}')

    def occ(*arguments):
        run([php, '-d', 'memory_limit=512M', server / 'occ', *arguments])

    def fixture(phase, provider=None):
        expected = ['--expected-lock-provider=' + provider] if provider else []
        run([php, '-d', 'memory_limit=512M', repo / 'tests/integration/upgrade-fixture.php',
             '--nextcloud=' + str(server), '--phase=' + phase, *expected])

    def progress(message):
        print(message, flush=True)
        log.write('\n' + message + '\n')
        log.flush()

    def db_command(binary, *arguments, interactive=False):
        if args.db_bin:
            connection = (['--no-defaults', '--protocol=TCP', '--host=127.0.0.1',
                           '--port=' + str(args.db_port)] if args.database == 'mysql'
                          else ['-h', '127.0.0.1', '-p', str(args.db_port)])
            return [args.db_bin / binary, *connection, *arguments]
        return ['docker', 'exec', *(['-i'] if interactive else []), args.db_container, binary, *arguments]

    def sql_command(sql):
        if args.database == 'mysql':
            run(db_command('mariadb', '-uroot', '-pci-only', '-e', sql))
        else:
            run(db_command('psql', '-U', 'postgres', '-v', 'ON_ERROR_STOP=1', '-c', sql))

    def start_local_database():
        nonlocal database_process
        # Never open an existing data directory or take an external database name.
        database_dir = base / 'database'
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            args.db_port = probe.getsockname()[1]
        env['PGPASSWORD'] = 'ci-only'
        if args.database == 'mysql':
            run([args.db_bin / 'mariadb-install-db', '--no-defaults', '--datadir=' + str(database_dir),
                 '--auth-root-authentication-method=normal', '--skip-test-db'])
            init = base / 'database-init.sql'
            init.write_text("ALTER USER 'root'@'localhost' IDENTIFIED BY 'ci-only';\n"
                            "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY 'ci-only';\n"
                            "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;\n")
            command = [args.db_bin / 'mariadbd', '--no-defaults', '--datadir=' + str(database_dir),
                       '--socket=' + str(base / 'db.sock'), '--pid-file=' + str(base / 'db.pid'),
                       '--bind-address=127.0.0.1', '--port=' + str(args.db_port), '--skip-log-bin',
                       '--init-file=' + str(init)]
            ready = lambda: db_command('mariadb', '-uroot', '-pci-only', '-e', 'SELECT 1')
        else:
            password = base / 'database-password'
            password.write_text('ci-only\n')
            password.chmod(0o600)
            run([args.db_bin / 'initdb', '-D', database_dir, '-U', 'postgres', '--auth=scram-sha-256',
                 '--pwfile=' + str(password), '--encoding=UTF8', '--locale=C'])
            command = [args.db_bin / 'postgres', '-D', database_dir, '-h', '127.0.0.1',
                       '-p', str(args.db_port), '-k', str(base)]
            ready = lambda: db_command('psql', '-U', 'postgres', '-d', 'postgres', '-c', 'SELECT 1')
        database_process = subprocess.Popen([str(x) for x in command], env=env, stdout=log, stderr=log)
        deadline = time.monotonic() + 60
        while time.monotonic() < deadline and database_process.poll() is None:
            result = subprocess.run([str(x) for x in ready()], env=env, stdout=log, stderr=log, timeout=5)
            if result.returncode == 0:
                return
            time.sleep(0.25)
        raise RuntimeError('Private database did not become ready; see run.log')

    def snapshot():
        shutil.copy2(server / 'config/config.php', base / 'before-config.php')
        shutil.copytree(data, base / 'before-data')
        if args.database == 'sqlite':
            with sqlite3.connect(data / 'owncloud.db') as src, sqlite3.connect(base / 'before.db') as dst:
                src.backup(dst)
        else:
            with (base / 'before.sql').open('wb') as out:
                if args.database == 'mysql':
                    run(db_command('mariadb-dump', '-uroot', '-pci-only', '--single-transaction', dbname), output_file=out)
                else:
                    # Nextcloud creates its own PostgreSQL login during installation.
                    # Preserve table/sequence owners and grants for that existing role.
                    run(db_command('pg_dump', '-U', 'postgres', dbname), output_file=out)

    def recover():
        # Only generated paths/database names are ever replaced.
        shutil.copy2(base / 'before-config.php', server / 'config/config.php')
        shutil.rmtree(data)
        shutil.copytree(base / 'before-data', data)
        if args.database == 'sqlite':
            for suffix in ['', '-wal', '-shm']:
                (data / ('owncloud.db' + suffix)).unlink(missing_ok=True)
            shutil.copy2(base / 'before.db', data / 'owncloud.db')
        else:
            sql_command(f'DROP DATABASE {dbname}')
            sql_command(f'CREATE DATABASE {dbname}')
            with (base / 'before.sql').open('rb') as inp:
                if args.database == 'mysql':
                    run(db_command('mariadb', '-uroot', '-pci-only', dbname, interactive=True), input_file=inp)
                else:
                    run(db_command('psql', '-U', 'postgres', '-v', 'ON_ERROR_STOP=1', '-d', dbname, interactive=True), input_file=inp)

    def use_app(path):
        if app.is_symlink():
            app.unlink()
        elif app.exists():
            raise RuntimeError('Refusing to replace an unexpected app directory')
        app.symlink_to(path, target_is_directory=True)

    def kill_at_barrier(command):
        # No timing guess: kill only after the real write/DDL boundary reports ready.
        with tempfile.TemporaryFile() as err:
            worker = subprocess.Popen([str(x) for x in command], cwd=server, env=env,
                                      stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=err, bufsize=0)
            ready = False
            pending = b''
            deadline = time.monotonic() + 120
            try:
                with selectors.DefaultSelector() as selector:
                    selector.register(worker.stdout, selectors.EVENT_READ)
                    while time.monotonic() < deadline:
                        if not selector.select(timeout=1):
                            if worker.poll() is not None:
                                break
                            continue
                        chunk = os.read(worker.stdout.fileno(), 4096)
                        if not chunk:
                            break
                        pending += chunk
                        if b'COBUDGET_FAULT_READY\n' in pending:
                            ready = True
                            break
                if not ready:
                    raise RuntimeError('Worker did not reach the fault barrier')
                worker.kill()  # SIGKILL: no PHP finally/destructor/shutdown cleanup.
                if worker.wait(timeout=15) != -9:
                    raise RuntimeError('Worker was not killed by SIGKILL')
            finally:
                if worker.poll() is None:
                    worker.kill()
                worker.wait(timeout=15)
                worker.stdin.close()
                worker.stdout.close()
                log.write(pending.decode(errors='replace'))
                err.seek(0)
                log.write(err.read().decode(errors='replace'))
                log.flush()

    try:
        progress(f'Creating isolated {args.database} fixture: {base}')
        # macOS clone is fast; Linux templates are copied normally. No PHP runs
        # until copied config and app links have been removed.
        subprocess.run(['cp', '-cR' if sys.platform == 'darwin' else '-a', str(source), str(server)], check=True)
        config_dir = server / 'config'
        if config_dir.is_symlink():
            config_dir.unlink()
        else:
            shutil.rmtree(config_dir)
        config_dir.mkdir()
        (config_dir / 'config.php').write_text("<?php $CONFIG = ['appstoreenabled'=>false, 'updatechecker'=>false, 'loglevel'=>3];\n")
        # Do not load any workspace apps linked into the source installation.
        for linked in (server / 'apps').iterdir():
            if linked.is_symlink():
                linked.unlink()
        if app.exists():
            shutil.rmtree(app)
        old = base / 'released-app'
        old.mkdir()
        archive = base / 'released.tar'
        with archive.open('wb') as out:
            subprocess.run(['git', '-C', str(repo), 'archive', 'v0.8.5'], stdout=out, check=True)
        with tarfile.open(archive) as tar:
            tar.extractall(old, filter='data')
        use_app(old)
        install = ['maintenance:install', '--database=' + args.database, '--admin-user=upgrade-admin',
                   '--admin-pass=' + env['OC_PASS'], '--data-dir=' + str(data), '--no-interaction']
        if args.db_bin:
            start_local_database()
        if args.database != 'sqlite':
            sql_command('SELECT VERSION()')
            sql_command(f'CREATE DATABASE {dbname}')
            created_database = True
            install += ['--database-host=127.0.0.1', '--database-port=' + str(args.db_port),
                        '--database-name=' + dbname, '--database-user=' + ('root' if args.database == 'mysql' else 'postgres'),
                        '--database-pass=ci-only']
        occ(*install)
        occ('status', '--output=json')
        run([php, '--version'])
        occ('app:enable', 'cobudget')
        (base / 'test-instance.json').write_text(json.dumps({'data': str(data), 'database': args.database, 'dbname': dbname}))
        fixture('seed')
        snapshot()

        progress('Interrupting the real upgrade after schema/data migration writes')
        fault_app = base / 'fault-app'
        shutil.copytree(repo, fault_app, ignore=shutil.ignore_patterns('.git', '.internal', 'node_modules', 'tests', 'screenshots', '.github'))
        migration = fault_app / 'lib/Migration/Version000013Date20261112000000.php'
        original = migration.read_text()
        anchor = "->where($q->expr()->eq('notified', $q->createNamedParameter(true, \\PDO::PARAM_BOOL)))->executeStatement();"
        if original.count(anchor) != 1:
            raise RuntimeError('Migration fault boundary changed; update test explicitly')
        migration.write_text(original.replace(anchor, anchor + '\n\t\tfwrite(STDOUT, "COBUDGET_FAULT_READY\\n"); fflush(STDOUT); fgets(STDIN); throw new \\RuntimeException("Expected SIGKILL");'))
        use_app(fault_app)
        kill_at_barrier([php, '-d', 'memory_limit=512M', server / 'occ', 'upgrade', '--no-interaction'])
        recover()
        use_app(old)
        fixture('rollback')
        progress('Interrupted upgrade recovered from saved database, Files, configuration and released code')

        use_app(repo)
        occ('upgrade', '--no-interaction')
        occ('cobudget:background-jobs:check')
        occ('cobudget:integrity:check')
        fixture('verify')
        progress('Released upgrade, shared graph, user mapping and generated IDs verified')
        for suite in ['annual-overview.php', 'backup-roundtrip.php', 'backup-concurrency.php']:
            run([php, '-d', 'memory_limit=512M', repo / 'tests/integration' / suite, '--nextcloud=' + str(server), '--user=upgrade-admin'])

        providers = ['database', 'redis'] if args.redis_port else ['database']
        for provider in providers:
            if provider == 'redis':
                occ('config:system:set', 'redis', 'host', '--value=127.0.0.1')
                occ('config:system:set', 'redis', 'port', '--type=integer', '--value=' + str(args.redis_port))
                occ('config:system:set', 'memcache.locking', '--value=\\OC\\Memcache\\Redis')
                run([php, repo / 'tests/integration/backup-concurrency.php', '--nextcloud=' + str(server), '--user=upgrade-admin'])
            # Short TTL only on this disposable instance. Production TTL must
            # exceed the longest live operation. We kill the owner before waiting.
            occ('config:system:set', 'filelocking.ttl', '--type=integer', '--value=15')
            fixture('prepare-crash', provider)
            progress(f'Killing restore before commit with {provider} locks')
            kill_at_barrier([php, '-d', 'memory_limit=512M', repo / 'tests/integration/upgrade-fixture.php', '--nextcloud=' + str(server), '--phase=crash-restore'])
            fixture('check-crash')
            time.sleep(16)
            fixture('recover-crash')
            occ('config:system:delete', 'filelocking.ttl')
        recover()
        use_app(old)
        fixture('rollback')
        progress('PASS: upgrade, old-archive restore, mapping, exceptions, SIGKILL recovery and rollback')
    finally:
        try:
            if created_database:
                sql_command(f'DROP DATABASE {dbname}')
        finally:
            if database_process is not None and database_process.poll() is None:
                database_process.terminate()
                try:
                    database_process.wait(timeout=30)
                except subprocess.TimeoutExpired:
                    database_process.kill()
                    database_process.wait(timeout=15)
            log.close()
            shutil.rmtree(base)
    (args.evidence / 'result.txt').write_text(f'PASS database={args.database}; providers={",".join(providers)}; source={source.name}\n')


if __name__ == '__main__':
    main()
