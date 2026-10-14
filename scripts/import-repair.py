#!/usr/bin/env python3
"""Import private Telegram data into local storage, outside the source tree."""
from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import tempfile
import zipfile
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent.parent
DATA_ROOT = Path(os.environ.get('REPAIR_DATA_ROOT', PROJECT_ROOT / 'storage/app/private/repair-data')).resolve()
SECTION_IDS = list(json.loads((PROJECT_ROOT / 'resources/repair/locales/ru.json').read_text(encoding='utf-8'))['sections'])
URL_RE = re.compile(r'https?://[^\s<>]+')


def safe_media(root: Path, value: str) -> Path:
    relative = Path(value)
    if not value or relative.is_absolute() or '..' in relative.parts:
        raise ValueError(f'Некорректный путь вложения: {value}')
    path = (root / relative).resolve()
    if not path.is_relative_to(root.resolve()) or not path.is_file():
        raise ValueError(f'Вложение отсутствует: {value}')
    return path


def copy_source(source: Path, destination: Path) -> Path:
    if source.is_dir():
        shutil.copytree(source, destination)
    elif source.is_file() and source.suffix.lower() == '.zip':
        destination.mkdir()
        with zipfile.ZipFile(source) as archive:
            entries = archive.infolist()
            if len(entries) > 20_000 or sum(entry.file_size for entry in entries) > 2_000_000_000:
                raise ValueError('ZIP-архив слишком большой')
            for entry in entries:
                parts = Path(entry.filename).parts
                if entry.is_dir():
                    continue
                if entry.filename.startswith('/') or '..' in parts or (entry.external_attr >> 16) & 0o170000 == 0o120000:
                    raise ValueError(f'Некорректный путь в архиве: {entry.filename}')
                target = (destination / entry.filename).resolve()
                if not target.is_relative_to(destination.resolve()):
                    raise ValueError(f'Некорректный путь в архиве: {entry.filename}')
                target.parent.mkdir(parents=True, exist_ok=True)
                with archive.open(entry) as incoming, target.open('wb') as outgoing:
                    shutil.copyfileobj(incoming, outgoing)
    else:
        raise ValueError('Укажите папку или ZIP-файл')
    return destination


def locate(root: Path, filename: str) -> Path:
    matches = [path.parent for path in root.rglob(filename) if path.is_file()]
    if len(matches) != 1:
        raise ValueError(f'Ожидается один {filename}, найдено: {len(matches)}')
    return matches[0]


def plain_text(value) -> str:
    if isinstance(value, str):
        return value
    if isinstance(value, list):
        return ''.join(part if isinstance(part, str) else str(part.get('text', '')) for part in value)
    return ''


def make_raw_catalog(archive: Path) -> dict:
    data = json.loads((archive / 'result.json').read_text(encoding='utf-8'))
    if not isinstance(data.get('messages'), list):
        raise ValueError('В result.json нет списка messages')
    sections = [{'id': section_id, 'messages': []} for section_id in SECTION_IDS]
    target = sections[-1]['messages']
    seen = set()
    for raw in data['messages']:
        if raw.get('type') != 'message':
            continue
        message_id = raw.get('id')
        if not isinstance(message_id, int) or message_id in seen:
            raise ValueError('Некорректный или повторяющийся ID сообщения')
        seen.add(message_id)
        body = plain_text(raw.get('text'))
        message = {'id': message_id, 'date': raw.get('date', ''), 'author': raw.get('from', ''),
                   'source': f'../../Исходный_экспорт/messages.html#message{message_id}', 'text': body}
        links = list(dict.fromkeys(URL_RE.findall(body)))
        if links:
            message['links'] = [{'url': url} for url in links]
        for field, output in (('photo', 'images'), ('file', 'attachments')):
            if raw.get(field):
                media = str(raw[field])
                safe_media(archive, media)
                message[output] = [{'path': 'archive/' + media, 'name': raw.get('file_name') or Path(media).name}]
        target.append(message)
    return {'schema_version': 1, 'source': 'archive/result.json', 'sections': sections}


def validate_parsed(root: Path) -> dict:
    catalog = json.loads((root / 'catalog.json').read_text(encoding='utf-8'))
    sections = catalog.get('sections')
    if not isinstance(sections, list) or {s.get('id') for s in sections} != set(SECTION_IDS):
        raise ValueError('Разделы catalog.json не совпадают с разделами приложения')
    seen = set()
    for section in sections:
        if not isinstance(section.get('messages'), list):
            raise ValueError('У раздела отсутствует список messages')
        for message in section['messages']:
            message_id = message.get('id')
            if not isinstance(message_id, int) or message_id in seen:
                raise ValueError('Некорректный или повторяющийся ID сообщения')
            seen.add(message_id)
            for field in ('images', 'attachments'):
                for item in message.get(field, []):
                    safe_media(root, item.get('path', ''))
    return catalog


def install(source: Path, kind: str) -> None:
    DATA_ROOT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='.import-', dir=DATA_ROOT) as temporary:
        staged = Path(temporary) / 'source'
        copy_source(source.resolve(), staged)
        selected = locate(staged, 'result.json' if kind == 'export' else 'catalog.json')
        if kind == 'export':
            catalog = make_raw_catalog(selected)
            repair = DATA_ROOT / 'repair'
            repair.mkdir(exist_ok=True)
            target = DATA_ROOT / 'archive'
            replace_directory(selected, target)
            catalog_path = repair / 'catalog.json'
            pending_path = repair / '.catalog.json.import'
            pending_path.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
            os.replace(pending_path, catalog_path)
            print(f'Импортировано {len(catalog["sections"][-1]["messages"])} сообщений в «Неразобранное».')
        else:
            catalog = validate_parsed(selected)
            replace_directory(selected, DATA_ROOT / 'repair')
            print(f'Импортировано {sum(len(s["messages"]) for s in catalog["sections"])} разобранных сообщений.')
    print(f'Данные: {DATA_ROOT}')


def replace_directory(source: Path, destination: Path) -> None:
    backup = destination.with_name(destination.name + '.previous-import')
    if backup.exists():
        shutil.rmtree(backup)
    if destination.exists():
        destination.rename(backup)
    try:
        source.rename(destination)
    except Exception:
        if backup.exists():
            backup.rename(destination)
        raise
    if backup.exists():
        shutil.rmtree(backup)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('kind', choices=('export', 'parsed'), help='export: Telegram result.json; parsed: catalog.json')
    parser.add_argument('source', type=Path, help='папка или ZIP-файл')
    args = parser.parse_args()
    install(args.source, args.kind)
