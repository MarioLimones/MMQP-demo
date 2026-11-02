import test from 'node:test';
import assert from 'node:assert/strict';
import { createConsumosDraftStore, CONSUMOS_DRAFT_MAX_BYTES, CONSUMOS_DRAFT_DOCUMENT_MAX_BYTES, CONSUMOS_DRAFT_MAX_PAGES } from '../../resources/js/consumos-draft.js';

function memoryStorage() {
    const data = new Map();
    return {
        data,
        getItem: key => data.get(key) ?? null,
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: key => data.delete(key),
    };
}
const key = 'consumos:database-a:user-2:sheet-19';
const snapshot = (changes = {}) => ({ id: 19, version: 3, title: 'Prueba manual', import_id: 7, grid_html: '<tbody><tr><td data-consumo-sources="[&quot;source-12&quot;]"></td></tr></tbody>', ...changes });
const makeStore = (storage, changes = {}) => createConsumosDraftStore({ storage, key, ...changes });
const pageId = index => `10000000-0000-4000-8000-${String(index).padStart(12, '0')}`;
const page = (index, changes = {}) => ({
    id: pageId(index), title: `Hoja ${index}`,
    grid_html: `<tr><td data-consumo-field="product">4 QUESOS</td><td data-consumo-field="consumo" data-consumo-sources="[&quot;source-${index}&quot;]"></td></tr>`,
    ...changes,
});
const documentSnapshot = (changes = {}) => snapshot({ pages: [page(1), page(2)], active_page_id: pageId(2), ...changes });

test('recovery stores only the snapshot allowlist and never applies it to the saved sheet', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const original = snapshot({ token: 'secret', suppliers: [{ consumption: 'invented' }] });
    const written = store.write(original);
    assert.equal(written.ok, true);
    assert.equal(store.inspect(snapshot()).canRestore, true);
    assert.deepEqual(store.read().snapshot, snapshot());
    assert.equal(store.read().schema_version, 1);
    assert.equal(store.read().base_sheet_id, 19);
    assert.equal(store.read().base_version, 3);
    assert.ok(Number.isFinite(Date.parse(store.read().changed_at)));
    assert.equal(storage.getItem(key).includes('secret'), false);
    assert.equal(storage.getItem(key).includes('invented'), false);
    original.title = 'Edited outside the store';
    assert.equal(store.read().snapshot.title, 'Prueba manual');
    const read = store.read(); read.snapshot.title = 'Mutated recovery';
    assert.equal(store.read().snapshot.title, 'Prueba manual');
});

test('draft compatibility checks sheet, server version and report independently', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    store.write(snapshot());
    for (const [changed, reason] of [
        [{ id: 20 }, 'different-sheet'], [{ version: 4 }, 'server-changed'], [{ version: 2 }, 'server-changed'], [{ import_id: 8 }, 'different-import'],
    ]) {
        const result = store.inspect(snapshot(changed));
        assert.equal(result.canRestore, false);
        assert.equal(result.reason, reason);
        assert.ok(result.draft);
        assert.equal(store.read().snapshot.title, 'Prueba manual');
    }
});

test('unsaved sheets are recoverable without assigning an invented saved version', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const newSheet = snapshot({ id: null, version: null, title: '' });
    assert.equal(store.write(newSheet).ok, true);
    assert.equal(store.inspect(newSheet).canRestore, true);
    assert.equal(store.inspect(snapshot()).reason, 'different-sheet');
    const revision = store.read().revision;
    assert.equal(store.clearAfterSave({ id: 27, version: 1, import_id: 7 }, { revision }).ok, true);
    assert.equal(store.read(), null);
});

test('corrupt, future-schema and malformed records are retained until explicit discard', () => {
    const storage = memoryStorage();
    let store = makeStore(storage);
    store.write(snapshot());
    const valid = JSON.parse(storage.getItem(key));
    const malformed = [
        '{broken', JSON.stringify({ ...valid, schema_version: 99 }),
        JSON.stringify({ ...valid, changed_at: 'not a date' }),
        JSON.stringify({ ...valid, base_version: 99 }),
        JSON.stringify({ ...valid, snapshot: { ...valid.snapshot, id: 19, version: null } }),
        JSON.stringify({ ...valid, snapshot: { ...valid.snapshot, grid_html: null } }),
        JSON.stringify({ ...valid, snapshot: { ...valid.snapshot, import_id: -1 } }),
    ];
    for (const raw of malformed) {
        storage.setItem(key, raw);
        store = makeStore(storage);
        assert.equal(store.read(), null);
        assert.equal(store.inspect(snapshot()).reason, 'invalid');
        assert.equal(store.write(snapshot()).ok, false);
        assert.equal(store.discard().ok, false);
        assert.equal(storage.getItem(key), raw);
        assert.equal(store.discard({ allowInvalid: true }).ok, true);
        assert.equal(storage.getItem(key), null);
    }
});

test('different database and user scopes cannot recover or overwrite each other', () => {
    const storage = memoryStorage();
    const first = makeStore(storage);
    first.write(snapshot());
    const otherKey = 'consumos:database-b:user-2:sheet-19';
    const second = makeStore(storage, { key: otherKey });
    assert.equal(second.inspect(snapshot()).reason, 'missing');
    storage.setItem(otherKey, storage.getItem(key));
    const copied = makeStore(storage, { key: otherKey });
    assert.equal(copied.inspect(snapshot()).reason, 'invalid');
    assert.equal(copied.write(snapshot()).ok, false);
    assert.ok(first.read());
});

test('a second editor with an older observed revision cannot overwrite the newer local draft', () => {
    const storage = memoryStorage();
    const first = makeStore(storage);
    const second = makeStore(storage);
    first.write(snapshot({ title: 'First tab' }));
    const raw = storage.getItem(key);
    assert.equal(second.write(snapshot({ title: 'Second tab' })).reason, 'conflict');
    assert.equal(storage.getItem(key), raw);
    // Reading to present a recovery banner must not silently acknowledge the conflict.
    second.read();
    assert.equal(second.write(snapshot()).reason, 'conflict');
    const reopened = makeStore(storage);
    assert.equal(reopened.write(snapshot({ title: 'Explicitly recovered' })).ok, true);
    assert.equal(first.write(snapshot()).reason, 'conflict');
});

test('failed or stale save confirmations never remove local recovery', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const { draft } = store.write(snapshot());
    const raw = storage.getItem(key);
    for (const saved of [undefined, {}, { id: 19, version: 3, import_id: 7 }, { id: 20, version: 4, import_id: 7 }, { id: 19, version: 4, import_id: 8 }, { id: 19, version: 9, import_id: 7 }]) {
        assert.equal(store.clearAfterSave(saved, { revision: draft.revision }).reason, 'unconfirmed-save');
        assert.equal(storage.getItem(key), raw);
    }
    assert.equal(store.clearAfterSave({ id: 19, version: 4, import_id: 7 }).reason, 'conflict');
    assert.equal(store.clearAfterSave({ id: 19, version: 4, import_id: 7 }, { revision: draft.revision }).cleared, true);
    assert.equal(storage.getItem(key), null);
});

test('later edits and a later tab revision survive an earlier confirmed server save', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const { draft } = store.write(snapshot());
    store.write(snapshot({ title: 'Edit while saving' }));
    const raw = storage.getItem(key);
    assert.equal(store.clearAfterSave({ id: 19, version: 4, import_id: 7 }, { revision: draft.revision }).reason, 'conflict');
    assert.equal(storage.getItem(key), raw);
    const another = makeStore(storage);
    const current = store.read();
    another.write(snapshot({ title: 'Other tab while saving' }));
    assert.equal(store.clearAfterSave({ id: 19, version: 4, import_id: 7 }, { revision: current.revision }).reason, 'conflict');
    assert.equal(another.read().snapshot.title, 'Other tab while saving');
});

test('a local draft cannot be replaced by another sheet or an older base version', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    store.write(snapshot());
    const raw = storage.getItem(key);
    assert.equal(store.write(snapshot({ id: 20 })).reason, 'different-base');
    assert.equal(store.write(snapshot({ version: 2 })).reason, 'different-base');
    assert.equal(storage.getItem(key), raw);
    // Explicit caller state after a confirmed save can rebase later edits to the new version.
    assert.equal(store.write(snapshot({ version: 4 })).ok, true);
    assert.equal(store.inspect(snapshot({ version: 4 })).canRestore, true);
});

test('UTF-8 size limit includes the envelope and retains the prior recoverable draft', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    store.write(snapshot());
    const raw = storage.getItem(key);
    assert.equal(store.write(snapshot({ grid_html: 'a'.repeat(CONSUMOS_DRAFT_MAX_BYTES) })).reason, 'too-large');
    assert.equal(store.write(snapshot({ grid_html: 'ñ'.repeat(CONSUMOS_DRAFT_MAX_BYTES / 2) })).reason, 'too-large');
    assert.equal(storage.getItem(key), raw);
    storage.setItem(key, ' '.repeat(CONSUMOS_DRAFT_MAX_BYTES + 1));
    assert.equal(makeStore(storage).inspect(snapshot()).reason, 'invalid');
});

test('storage quota, access, remove and status-view failures do not break editing', () => {
    const storage = memoryStorage();
    const statuses = [];
    const store = makeStore(storage, { onStatus: value => statuses.push(value.reason) });
    store.write(snapshot());
    const original = storage.getItem(key);
    storage.setItem = () => { throw new Error('QuotaExceededError'); };
    assert.equal(store.write(snapshot({ title: 'Unsaved later edit' })).reason, 'storage-error');
    assert.equal(storage.getItem(key), original);
    assert.ok(statuses.includes('storage-error'));
    storage.removeItem = () => { throw new Error('Blocked'); };
    assert.equal(store.discard({ revision: store.read().revision }).reason, 'storage-error');
    storage.getItem = () => { throw new Error('SecurityError'); };
    assert.equal(store.read(), null);
    assert.equal(store.write(snapshot()).reason, 'unavailable');
    const brokenStatus = makeStore(memoryStorage(), { onStatus: () => { throw new Error('View failed'); } });
    assert.equal(brokenStatus.write(snapshot()).ok, true);
    assert.equal(createConsumosDraftStore({}).write(snapshot()).reason, 'unavailable');
});

test('scheduled recovery debounces to the last snapshot and flushes before navigation', async () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const value = snapshot({ title: 'First' });
    store.schedule(value, 15);
    value.title = 'External mutation';
    store.schedule(snapshot({ title: 'Last' }), 15);
    assert.equal(store.read(), null);
    await new Promise(resolve => setTimeout(resolve, 35));
    assert.equal(store.read().snapshot.title, 'Last');
    store.schedule(snapshot({ title: 'Before page exit' }), 10000);
    assert.equal(store.flush().ok, true);
    assert.equal(store.read().snapshot.title, 'Before page exit');
    store.dispose();
});

test('pending edits are retained after an earlier save and may be rebased after confirmation', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const { draft } = store.write(snapshot());
    store.schedule(snapshot({ title: 'Later pending edit' }), 10000);
    assert.equal(store.clearAfterSave({ id: 19, version: 4, import_id: 7 }, { revision: draft.revision }).reason, 'conflict');
    assert.equal(store.flush().ok, true);
    assert.equal(store.read().snapshot.title, 'Later pending edit');
    assert.equal(store.write(snapshot({ version: 4, title: 'Later pending edit' })).ok, true);
    store.dispose();
});

test('explicit discard checks displayed revision and cancels queued recovery writes', async () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const { draft } = store.write(snapshot());
    assert.equal(store.discard({ revision: 'old revision' }).reason, 'conflict');
    store.schedule(snapshot({ title: 'Discard me too' }), 15);
    assert.equal(store.discard({ revision: draft.revision }).ok, true);
    await new Promise(resolve => setTimeout(resolve, 35));
    assert.equal(store.read(), null);
    store.dispose();
});

test('invalid snapshots never replace recovery and disposal cannot write again', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    store.write(snapshot());
    const raw = storage.getItem(key);
    for (const changes of [{ id: -1 }, { version: null }, { import_id: '7' }, { title: null }, { title: 'x'.repeat(256) }, { grid_html: {} }]) {
        assert.equal(store.write(snapshot(changes)).reason, 'invalid-snapshot');
        assert.equal(storage.getItem(key), raw);
    }
    store.schedule(snapshot({ title: 'Flushed on dispose' }), 10000);
    assert.equal(store.dispose().ok, true);
    assert.equal(store.read().snapshot.title, 'Flushed on dispose');
    assert.equal(store.write(snapshot()).reason, 'disposed');
    assert.equal(store.schedule(snapshot()).reason, 'disposed');
});

test('mapping recovery preserves explicit skipped products through the allowlist without aliasing caller arrays', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const skipped = ['new-product-a', 'missing-product-b'];
    const value = snapshot({ id: null, version: null, skipped_product_ids: skipped, secret: 'excluded', report_rows: [{ name: 'excluded' }] });
    assert.equal(store.write(value).ok, true);
    skipped.push('changed-outside-store');
    assert.deepEqual(store.read().snapshot.skipped_product_ids, ['new-product-a', 'missing-product-b']);
    assert.equal(storage.getItem(key).includes('excluded'), false);
    const read = store.read(); read.snapshot.skipped_product_ids.push('changed-read-copy');
    assert.deepEqual(store.read().snapshot.skipped_product_ids, ['new-product-a', 'missing-product-b']);
});

test('malformed skip decisions never replace a recoverable mapping draft', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    assert.equal(store.write(snapshot({ skipped_product_ids: ['original'] })).ok, true);
    const original = storage.getItem(key);
    for (const skipped_product_ids of [null, 'product', [null], [''], ['x'.repeat(256)], ['same', 'same'], Array.from({ length: 1001 }, (_, index) => `product-${index}`)]) {
        assert.equal(store.write(snapshot({ skipped_product_ids })).reason, 'invalid-snapshot');
        assert.equal(storage.getItem(key), original);
    }
    assert.equal(store.write(snapshot({ skipped_product_ids: [] })).ok, true);
    assert.deepEqual(store.read().snapshot.skipped_product_ids, []);
});

test('multi-page recovery retains page order, active page and separate bindings for repeated product names', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const value = documentSnapshot();
    value.pages[0].secret = 'excluded-page-token';
    value.pages[0].suppliers = [{ consumption: 'excluded-consumption' }];
    assert.equal(store.write(value).ok, true);
    value.pages[0].title = 'Changed outside the store';
    value.pages.reverse();

    const reopened = makeStore(storage);
    assert.equal(reopened.inspect(snapshot()).canRestore, true);
    const recovered = reopened.read().snapshot;
    assert.deepEqual(recovered, documentSnapshot());
    assert.match(recovered.pages[0].grid_html, /source-1/);
    assert.doesNotMatch(recovered.pages[0].grid_html, /source-2/);
    assert.match(recovered.pages[1].grid_html, /source-2/);
    assert.doesNotMatch(recovered.pages[1].grid_html, /source-1/);
    assert.equal(storage.getItem(key).includes('excluded-'), false);
    recovered.pages[0].grid_html = 'Changed read copy';
    recovered.pages.push(page(3));
    assert.deepEqual(reopened.read().snapshot, documentSnapshot());

    const switched = documentSnapshot({ active_page_id: pageId(1), grid_html: page(1).grid_html });
    assert.equal(reopened.write(switched).ok, true);
    assert.equal(reopened.read().snapshot.active_page_id, pageId(1));
    assert.deepEqual(reopened.read().snapshot.pages, [page(1), page(2)]);
});

test('legacy snapshots retain their exact shape and optional page metadata is not invented', () => {
    const store = makeStore(memoryStorage());
    assert.equal(store.write(snapshot()).ok, true);
    assert.deepEqual(Object.keys(store.read().snapshot), ['id', 'version', 'title', 'import_id', 'grid_html']);
    const withEmptyLayout = documentSnapshot({ pages: [page(1, { grid_html: null })], active_page_id: undefined });
    assert.equal(store.write(withEmptyLayout).ok, true);
    assert.deepEqual(store.read().snapshot.pages, [page(1, { grid_html: null })]);
    assert.equal(Object.hasOwn(store.read().snapshot, 'active_page_id'), false);
    assert.equal(store.write(snapshot()).ok, true);
    assert.equal(Object.hasOwn(store.read().snapshot, 'pages'), false);
});

test('malformed pages or active-page references cannot replace or restore over a valid recovery', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    assert.equal(store.write(documentSnapshot()).ok, true);
    const raw = storage.getItem(key);
    const invalid = [
        { pages: null }, { pages: {} }, { pages: [] },
        { pages: Array.from({ length: CONSUMOS_DRAFT_MAX_PAGES + 1 }, (_, index) => page(index + 1)) },
        { pages: [null] }, { pages: [page(1, { id: 'not-a-uuid' })] },
        { pages: [page(1), page(1)] },
        { pages: [page(1, { id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' }), page(2, { id: 'AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA' })] },
        { pages: [page(1, { title: null })] }, { pages: [page(1, { title: 'x'.repeat(256) })] },
        { pages: [page(1, { grid_html: {} })] }, { pages: [page(1, { grid_html: undefined })] },
        { active_page_id: null }, { active_page_id: 'bad-id' }, { active_page_id: pageId(3) },
        { pages: undefined, active_page_id: pageId(1) },
    ];
    for (const changes of invalid) {
        assert.equal(store.write(documentSnapshot(changes)).reason, 'invalid-snapshot');
        assert.equal(store.schedule(documentSnapshot(changes)).reason, 'invalid-snapshot');
        assert.equal(storage.getItem(key), raw);

        const malformed = JSON.stringify({ ...JSON.parse(raw), snapshot: documentSnapshot(changes) });
        const isolatedStorage = memoryStorage();
        isolatedStorage.setItem(key, malformed);
        const reopened = makeStore(isolatedStorage);
        assert.equal(reopened.inspect(snapshot()).reason, 'invalid');
        assert.equal(reopened.write(documentSnapshot()).ok, false);
        assert.equal(isolatedStorage.getItem(key), malformed);
    }
});

test('multi-page budget counts UTF-8 bytes per page and the whole document envelope', () => {
    const storage = memoryStorage();
    const store = makeStore(storage);
    const large = 'a'.repeat(600000);
    const pages = [page(1, { grid_html: large }), page(2, { grid_html: large }), page(3, { grid_html: large })];
    assert.equal(store.write(documentSnapshot({ pages, grid_html: '' })).ok, true);
    assert.ok(new TextEncoder().encode(storage.getItem(key)).byteLength > CONSUMOS_DRAFT_MAX_BYTES);
    assert.equal(makeStore(storage).inspect(snapshot()).canRestore, true);
    const raw = storage.getItem(key);
    for (const oversized of [
        documentSnapshot({ pages: [page(1, { grid_html: 'ñ'.repeat(CONSUMOS_DRAFT_MAX_BYTES / 2 + 1) })], active_page_id: pageId(1) }),
        documentSnapshot({ pages: Array.from({ length: 6 }, (_, index) => page(index + 1, { grid_html: 'a'.repeat(900000) })) }),
        documentSnapshot({ grid_html: 'a'.repeat(CONSUMOS_DRAFT_MAX_BYTES + 1) }),
    ]) {
        assert.equal(store.write(oversized).reason, 'too-large');
        assert.equal(store.schedule(oversized).reason, 'too-large');
        assert.equal(storage.getItem(key), raw);
    }
    storage.setItem(key, ' '.repeat(CONSUMOS_DRAFT_DOCUMENT_MAX_BYTES + 1));
    assert.equal(makeStore(storage).inspect(snapshot()).reason, 'invalid');
});

test('invalid or oversized page edits leave an earlier pending recovery available to flush', () => {
    const store = makeStore(memoryStorage());
    assert.equal(store.schedule(documentSnapshot({ title: 'Last valid pages' }), 10000).ok, true);
    assert.equal(store.schedule(documentSnapshot({ pages: [] }), 10000).reason, 'invalid-snapshot');
    assert.equal(store.schedule(documentSnapshot({ grid_html: 'x'.repeat(CONSUMOS_DRAFT_MAX_BYTES + 1) }), 10000).reason, 'too-large');
    assert.equal(store.flush().ok, true);
    assert.equal(store.read().snapshot.title, 'Last valid pages');
    assert.deepEqual(store.read().snapshot.pages, [page(1), page(2)]);
    store.dispose();
});
