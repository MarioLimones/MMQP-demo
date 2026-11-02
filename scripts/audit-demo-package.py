#!/usr/bin/env python3
"""Inspect a demo source directory or ZIP without displaying sensitive values.

Usage: python scripts/audit-demo-package.py [directory-or-zip]
Dependencies copied temporarily for local QA (vendor/node_modules) are skipped;
they must also be excluded from the release archive. A clean release contains
source, generated frontend assets, launch icons and directory placeholders only.
This check is a release guard, not a guarantee that arbitrary future edits are
free of personal information: new fixtures still require a human review.
"""
from __future__ import annotations

import argparse
from collections import Counter
import json
from pathlib import Path, PurePosixPath
import re
import sys
from zipfile import ZipFile, is_zipfile

DEPENDENCIES = {'vendor', 'node_modules'}
FORBIDDEN_DIRECTORIES = {'.git', '.idea', '.local', '.gradle', '.playwright-cli',
                         '__pycache__', '.phpunit.cache', 'output', 'backups'}
FORBIDDEN_EXTENSIONS = {'.sql', '.sqlite', '.sqlite3', '.db', '.bak', '.backup',
                        '.log', '.joblib', '.pkl', '.pickle', '.pem', '.key',
                        '.jks', '.keystore', '.apk', '.aab', '.pyc', '.pdf',
                        '.jpg', '.jpeg', '.csv', '.xlsx', '.xls'}
FORBIDDEN_FILES = {'recover.json', 'auth.json', 'data.json',
                   'datos_entrenamiento.json', 'datos_entrenamiento_historico.json',
                   'informe_entrenamiento.json', 'modelo_cobertura.json',
                   'user_json.json', 'consumos-report-extracted.json',
                   'test_payload_manual.json', '.phpunit.result.cache',
                   'local.properties'}
TEXT_EXTENSIONS = {'.php', '.py', '.js', '.mjs', '.cjs', '.json', '.xml', '.html',
                   '.css', '.md', '.txt', '.ini', '.conf', '.sh', '.ps1', '.yml',
                   '.yaml', '.kts', '.kt', '.toml', '.properties', '.example'}
TOKENS = [
    ('private_key', re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----')),
    ('github_token', re.compile(r'\bgh[pousr]_[A-Za-z0-9_]{25,}\b')),
    ('cloud_access_key', re.compile(r'\bAKIA[A-Z0-9]{16}\b')),
    ('external_ai_key', re.compile(r'\b(?:AIza|AQ\.)(?:[A-Za-z0-9_-]){25,}\b')),
    ('openai_key', re.compile(r'\bsk-(?:proj-|svcacct-)?[A-Za-z0-9_-]{30,}\b')),
    ('analytics_identifier', re.compile(r'(?:data-cf-' r'beacon|cloudflareinsights\.com/beacon)', re.I)),
]
SUSPICIOUS_ASSIGNMENT = re.compile(
    r'\b(?:API_KEY|APP_KEY|DB_PASSWORD|MAIL_PASSWORD|VAPID_PRIVATE_KEY|AWS_SECRET_ACCESS_KEY)'
    r'\s*[=:]\s*[\'\"]?([A-Za-z0-9_+/.=:-]{20,})', re.I)


def classify_path(name: str) -> list[str]:
    path = PurePosixPath(name)
    reasons = []
    if path.is_absolute() or '..' in path.parts or '\\' in name:
        reasons.append('unsafe_archive_path')
    if any(part in FORBIDDEN_DIRECTORIES for part in path.parts):
        reasons.append('private_or_runtime_directory')
    if path.suffix.lower() in FORBIDDEN_EXTENSIONS or re.search(r'\.(sqlite3?|db)-', path.name, re.I):
        reasons.append('data_dump_document_or_runtime_file')
    if path.name in FORBIDDEN_FILES or path.name.startswith('PHOTO-'):
        reasons.append('operational_data_artifact')
    if path.name.startswith('.env') and path.name != '.env.example':
        reasons.append('environment_file')
    parts = path.parts
    if 'storage' in parts and 'mmqp_hub' in parts and path.name != '.gitignore':
        reasons.append('runtime_storage_content')
    if 'bootstrap' in parts and 'cache' in parts and path.name != '.gitignore':
        reasons.append('cached_application_configuration')
    if 'fichaje_app' in parts and 'build' in parts:
        reasons.append('android_build_output')
    if 'algoritmo' in parts and any(part in {'horarios', 'horarios_jmportar'} for part in parts):
        reasons.append('historical_schedule_directory')
    if path.suffix.lower() in {'.png', '.webp', '.ico', '.gif'}:
        public_icon = path.name in {'icon-192.png', 'icon-512.png', 'favicon.ico'} and 'public' in parts
        android_icon = 'fichaje_app' in parts and 'res' in parts and path.name.startswith('ic_launcher')
        if not public_icon and not android_icon:
            reasons.append('unreviewed_image')
    return reasons


def inspect_text(name: str, data: bytes) -> list[tuple[str, int]]:
    path = PurePosixPath(name)
    if path.suffix not in TEXT_EXTENSIONS and path.name not in {'Dockerfile', '.gitignore', '.dockerignore', '.gitattributes'}:
        return []
    text = data.decode('utf-8-sig', errors='replace')
    findings = []
    for line_number, line in enumerate(text.splitlines(), 1):
        for code, pattern in TOKENS:
            if pattern.search(line):
                findings.append((code, line_number))
        if SUSPICIOUS_ASSIGNMENT.search(line):
            findings.append(('embedded_credential_assignment', line_number))
    return findings


def scan(target: Path) -> tuple[list[dict], Counter]:
    findings = []
    counts = Counter()

    def inspect(name, read, is_archive=False):
        parts = PurePosixPath(name).parts
        if any(part in DEPENDENCIES for part in parts):
            counts['dependency_files_skipped'] += 1
            if is_archive:
                findings.append({'file': name, 'code': 'dependency_in_release_archive'})
            return
        counts['files_checked'] += 1
        for code in classify_path(name):
            findings.append({'file': name, 'code': code})
        for code, line_number in inspect_text(name, read()):
            findings.append({'file': name, 'line': line_number, 'code': code})

    if target.is_file() and is_zipfile(target):
        with ZipFile(target) as archive:
            for info in archive.infolist():
                if not info.is_dir():
                    inspect(info.filename, lambda info=info: archive.read(info), True)
    elif target.is_dir():
        # Do not traverse dependency symlinks or junctions used during local QA.
        import os
        for parent, directories, files in os.walk(target, followlinks=False):
            for folder in list(directories):
                if folder in DEPENDENCIES:
                    directories.remove(folder)
                    counts['dependency_directories_skipped'] += 1
                elif folder in FORBIDDEN_DIRECTORIES:
                    directories.remove(folder)
                    name = (Path(parent) / folder).relative_to(target).as_posix()
                    findings.append({'file': name, 'code': 'private_or_runtime_directory'})
            for filename in files:
                path = Path(parent) / filename
                name = path.relative_to(target).as_posix()
                if path.is_symlink():
                    findings.append({'file': name, 'code': 'symlink_in_source_distribution'})
                else:
                    inspect(name, path.read_bytes)
    else:
        raise ValueError('Expected an existing directory or ZIP archive.')
    return findings, counts


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('target', nargs='?', type=Path, default=Path(__file__).resolve().parents[1])
    args = parser.parse_args()
    try:
        findings, counts = scan(args.target.resolve())
    except (ValueError, OSError) as error:
        print(json.dumps({'status': 'ERROR', 'reason': type(error).__name__}))
        return 2
    print(json.dumps({'status': 'FAIL' if findings else 'PASS', **counts,
                      'findings': findings}, ensure_ascii=False, indent=2))
    return 1 if findings else 0


if __name__ == '__main__':
    sys.exit(main())
