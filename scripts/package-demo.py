#!/usr/bin/env python3
"""Create a source-only MMQP demo ZIP, audited before it replaces the output.

Run from any directory: python scripts/package-demo.py [--output release.zip]
The application may be installed and running locally: databases, environment
files, runtime examples and dependencies are never copied into the archive.
"""
from __future__ import annotations

import argparse
from collections import Counter
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import stat
import subprocess
import sys
import tempfile
from zipfile import ZIP_DEFLATED, ZipFile

ROOT_FILES = {'.dockerignore', '.gitattributes', '.gitignore', 'Dockerfile',
              'compose.yaml', 'README.md', 'LICENSE', 'LICENSE.md'}
HUB_FILES = {'.editorconfig', '.env.example', '.gitattributes', '.gitignore',
             'artisan', 'composer.json', 'composer.lock', 'package.json',
             'package-lock.json', 'phpunit.xml', 'postcss.config.js',
             'requirements-consumos.txt', 'tailwind.config.js', 'vite.config.js'}
ANDROID_FILES = {'.gitignore', 'README.md', 'build.gradle.kts', 'gradle.properties',
                 'gradlew', 'gradlew.bat', 'settings.gradle.kts'}
OMIT_DIRECTORIES = {'.git', '.github', '.idea', '.local', '.gradle',
                    '.playwright-cli', '.phpunit.cache', '__pycache__',
                    'node_modules', 'vendor', 'venv', '.venv', 'output',
                    'backups'}
OMIT_EXTENSIONS = {'.sqlite', '.sqlite3', '.db', '.sql', '.bak', '.backup', '.log',
                   '.joblib', '.pkl', '.pickle', '.pem', '.key', '.jks',
                   '.keystore', '.apk', '.aab', '.pyc', '.pdf', '.jpg', '.jpeg',
                   '.csv', '.xlsx', '.xls', '.zip'}
TEXT_SOURCE = {'.php', '.py', '.js', '.mjs', '.cjs', '.css', '.md', '.txt',
               '.xml', '.kts', '.kt', '.toml', '.properties', '.sh', '.ps1',
               '.yml', '.yaml', '.ini', '.conf'}


def is_link(path: Path) -> bool:
    attributes = path.lstat()
    return path.is_symlink() or bool(getattr(attributes, 'st_file_attributes', 0)
                                     & getattr(stat, 'FILE_ATTRIBUTE_REPARSE_POINT', 0x400))


def allowed(relative: str) -> bool:
    path = PurePosixPath(relative)
    parts = path.parts
    if not parts or any(part in OMIT_DIRECTORIES for part in parts):
        return False
    if path.name.startswith('.env') and relative != 'mmqp_hub/.env.example':
        return False
    if path.suffix.lower() in OMIT_EXTENSIONS or any(s in path.name.lower() for s in ('.sqlite-', '.sqlite3-', '.db-')):
        return False
    if len(parts) == 1:
        return relative in ROOT_FILES
    if parts[0] == 'docker':
        return len(parts) == 2 and path.suffix in {'.sh', '.conf', '.ini'}
    if parts[0] == 'docs':
        return path.suffix == '.md'
    if parts[0] == 'scripts':
        return path.suffix in {'.py', '.php', '.sh', '.ps1'}
    if parts[0] == 'algoritmo':
        if len(parts) == 2:
            return path.suffix == '.py' or path.name in {'requirements.txt', 'requirements-pdf.txt'}
        return parts[1] == 'tests' and path.suffix == '.py'
    if parts[0] == 'mmqp_hub':
        child = '/'.join(parts[1:])
        if len(parts) == 2:
            return child in HUB_FILES
        if parts[1] == 'storage':
            return path.name == '.gitignore'
        if child.startswith('bootstrap/cache/'):
            return child == 'bootstrap/cache/.gitignore'
        if parts[1] in {'app', 'bootstrap', 'config', 'routes', 'scripts'}:
            return path.suffix in {'.php', '.py'}
        if parts[1] == 'database':
            return child == 'database/.gitignore' or (len(parts) > 3
                    and parts[2] in {'migrations', 'factories', 'seeders'} and path.suffix == '.php')
        if parts[1] in {'resources', 'tests'}:
            return path.suffix in TEXT_SOURCE
        if parts[1] == 'public':
            if 'storage' in parts or path.name == 'hot':
                return False
            if child == 'public/build/manifest.json':
                return True
            if child.startswith('public/build/assets/'):
                return path.suffix in {'.css', '.js', '.woff', '.woff2'}
            return path.suffix in {'.php', '.js', '.txt'} or child in {
                'public/.htaccess', 'public/manifest.json', 'public/favicon.ico',
                'public/icon-192.png', 'public/icon-512.png'}
        return False
    if parts[0] == 'fichaje_app':
        child = '/'.join(parts[1:])
        if 'build' in parts:
            return False
        if len(parts) == 2:
            return child in ANDROID_FILES
        if parts[1] == 'gradle':
            return child in {'gradle/libs.versions.toml', 'gradle/wrapper/gradle-wrapper.jar',
                             'gradle/wrapper/gradle-wrapper.properties'}
        if child == 'app/build.gradle.kts' or child == 'app/.gitignore':
            return True
        if child.startswith('app/src/'):
            return path.suffix in {'.kt', '.xml'} or ('res' in parts
                    and path.name.startswith('ic_launcher') and path.suffix in {'.png', '.webp'})
    return False


def select_files(root: Path) -> tuple[list[Path], Counter]:
    selected = []
    counts = Counter()
    for current, directories, files in os.walk(root, followlinks=False):
        for folder in list(directories):
            path = Path(current) / folder
            if (folder in OMIT_DIRECTORIES or is_link(path)
                    or path.relative_to(root).parts[:2] in {('algoritmo', 'horarios'), ('algoritmo', 'horarios_jmportar')}
                    or (folder == 'build' and 'fichaje_app' in path.relative_to(root).parts)):
                directories.remove(folder)
                counts['directories_excluded'] += 1
        for filename in files:
            path = Path(current) / filename
            if is_link(path) or not allowed(path.relative_to(root).as_posix()):
                counts['files_excluded'] += 1
                continue
            selected.append(path)
    return sorted(selected, key=lambda p: p.relative_to(root).as_posix()), counts


def package(root: Path, output: Path) -> dict:
    if output.suffix.lower() != '.zip':
        raise ValueError('Output must have a .zip extension.')
    if not output.parent.is_dir() or is_link(output.parent):
        raise ValueError('Output directory must exist and must not be a link.')
    if output.exists() and is_link(output):
        raise ValueError('Output must not be a link.')
    auditor = root / 'scripts/audit-demo-package.py'
    if not auditor.is_file():
        raise ValueError('The demo package auditor is missing.')
    files, counts = select_files(root)
    required = {'Dockerfile', 'compose.yaml', 'README.md', 'mmqp_hub/.env.example',
                'mmqp_hub/artisan', 'mmqp_hub/database/seeders/DemoSeeder.php',
                'mmqp_hub/public/build/manifest.json'}
    selected = {p.relative_to(root).as_posix() for p in files}
    if not required <= selected:
        raise ValueError('Required demo source or compiled assets are missing; build the frontend first.')
    with (root / 'mmqp_hub/public/build/manifest.json').open(encoding='utf-8') as stream:
        manifest = json.load(stream)
    for entry in manifest.values():
        for artifact in [entry.get('file'), *entry.get('css', []), *entry.get('assets', [])]:
            if artifact and 'mmqp_hub/public/build/' + artifact not in selected:
                raise ValueError('A compiled artifact referenced by the Vite manifest is missing.')
    fd, temporary_name = tempfile.mkstemp(prefix='mmqp-demo-release-', suffix='.zip', dir=output.parent)
    os.close(fd)
    temporary = Path(temporary_name)
    try:
        with ZipFile(temporary, 'w', ZIP_DEFLATED, compresslevel=9, strict_timestamps=False) as archive:
            for path in files:
                archive.write(path, 'MMQP-demo/' + path.relative_to(root).as_posix())
        audit = subprocess.run([sys.executable, '-B', str(auditor), str(temporary)],
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
                               encoding='utf-8', errors='replace', check=False)
        if audit.returncode:
            # The auditor emits only paths/categories. Never print source snippets.
            print(audit.stdout, file=sys.stderr)
            raise ValueError('Release audit failed; the previous ZIP was preserved.')
        with temporary.open('rb') as stream:
            digest = hashlib.file_digest(stream, 'sha256').hexdigest()
        size = temporary.stat().st_size
        os.replace(temporary, output)
        sidecar = output.with_suffix(output.suffix + '.sha256')
        sidecar.write_text(digest + '  ' + output.name + '\n', encoding='ascii')
        return {'status': 'PASS', 'archive': output.name, 'files': len(files),
                'bytes': size, 'sha256': digest, **counts}
    finally:
        temporary.unlink(missing_ok=True)


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=root.parent / 'MMQP-demo.zip')
    arguments = parser.parse_args()
    try:
        result = package(root, arguments.output.absolute())
    except (ValueError, OSError, json.JSONDecodeError) as error:
        print(json.dumps({'status': 'FAIL', 'reason': str(error)}, ensure_ascii=False))
        return 1
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == '__main__':
    sys.exit(main())
