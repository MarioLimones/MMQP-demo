// Local recovery only. Restoring the HTML and fetching its report remain explicit UI actions.
export const CONSUMOS_DRAFT_SCHEMA = 1;
export const CONSUMOS_DRAFT_MAX_BYTES = 1048576;
export const CONSUMOS_DRAFT_DOCUMENT_MAX_BYTES = 5 * CONSUMOS_DRAFT_MAX_BYTES;
export const CONSUMOS_DRAFT_MAX_PAGES = 20;

const nullableId = value => value === null || (Number.isSafeInteger(value) && value > 0);
const bytes = value => new TextEncoder().encode(value).byteLength;
const uuid = value => typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value);

function snapshotOf(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const id = value.id ?? null;
    const version = value.version ?? null;
    const importId = value.import_id ?? null;
    if (!nullableId(id) || !nullableId(version) || !nullableId(importId)) return null;
    if ((id === null) !== (version === null)) return null;
    if (typeof value.title !== 'string' || value.title.length > 255 || typeof value.grid_html !== 'string') return null;
    const skipped = value.skipped_product_ids;
    if (skipped !== undefined && (!Array.isArray(skipped) || skipped.length > 1000 || new Set(skipped).size !== skipped.length
        || skipped.some(product => typeof product !== 'string' || !product || product.length > 255))) return null;
    const pages = value.pages;
    const activePage = value.active_page_id;
    if (pages !== undefined) {
        if (!Array.isArray(pages) || !pages.length || pages.length > CONSUMOS_DRAFT_MAX_PAGES
            || pages.some(page => !page || typeof page !== 'object' || Array.isArray(page) || !uuid(page.id)
                || typeof page.title !== 'string' || page.title.length > 255
                || (page.grid_html !== null && typeof page.grid_html !== 'string'))
            || new Set(pages.map(page => page.id.toLowerCase())).size !== pages.length) return null;
        if (activePage !== undefined && (!uuid(activePage) || !pages.some(page => page.id.toLowerCase() === activePage.toLowerCase()))) return null;
    } else if (activePage !== undefined) return null;
    // Allowlist: no tokens, session data, supplied consumption amounts or whole report copies.
    return {
        id, version, title: value.title, import_id: importId, grid_html: value.grid_html,
        ...(skipped !== undefined ? { skipped_product_ids: [...skipped] } : {}),
        ...(pages !== undefined ? { pages: pages.map(page => ({ id: page.id, title: page.title, grid_html: page.grid_html })) } : {}),
        ...(activePage !== undefined ? { active_page_id: activePage } : {}),
    };
}

function oversized(snapshot, raw) {
    const limit = snapshot.pages ? CONSUMOS_DRAFT_DOCUMENT_MAX_BYTES : CONSUMOS_DRAFT_MAX_BYTES;
    return bytes(raw) > limit || bytes(snapshot.grid_html) > CONSUMOS_DRAFT_MAX_BYTES
        || snapshot.pages?.some(page => page.grid_html !== null && bytes(page.grid_html) > CONSUMOS_DRAFT_MAX_BYTES);
}

function revisionId() {
    return globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

/**
 * key must include a non-secret database namespace, user ID and sheet ID (or "new").
 * read/inspect never apply a draft to the editor or delete an incompatible draft.
 * Storage and callback failures are contained, so recovery cannot break editing.
 */
export function createConsumosDraftStore({ storage, key, onStatus } = {}) {
    let expectedRaw;
    let pending = null;
    let timer = null;
    let disposed = false;
    const validKey = typeof key === 'string' && key.length > 0 && key.length <= 512;

    function status(reason, message) {
        try { onStatus?.({ reason, message }); } catch { /* A status view must not break editing. */ }
    }
    function failure(reason, message) {
        status(reason, message);
        return { ok: false, reason };
    }
    function readRaw() {
        try {
            if (!validKey || !storage || typeof storage.getItem !== 'function') throw new Error('unavailable');
            return { ok: true, raw: storage.getItem(key) };
        } catch { return failure('unavailable', 'El navegador no permite guardar el borrador local. La ficha sigue disponible para guardarla.'); }
    }
    function decode(raw) {
        if (raw === null) return { ok: true, draft: null };
        try {
            if (typeof raw !== 'string' || bytes(raw) > CONSUMOS_DRAFT_DOCUMENT_MAX_BYTES) throw new Error('size');
            const record = JSON.parse(raw);
            const snapshot = snapshotOf(record.snapshot);
            if (record.schema_version !== CONSUMOS_DRAFT_SCHEMA || record.scope !== key || !snapshot
                || oversized(snapshot, raw)
                || typeof record.revision !== 'string' || !record.revision || record.revision.length > 128
                || typeof record.changed_at !== 'string' || !Number.isFinite(Date.parse(record.changed_at))
                || record.base_sheet_id !== snapshot.id || record.base_version !== snapshot.version) throw new Error('invalid');
            // Return normalized data rather than any extra properties from persisted JSON.
            return { ok: true, draft: {
                schema_version: CONSUMOS_DRAFT_SCHEMA, scope: key, revision: record.revision,
                changed_at: record.changed_at, base_sheet_id: snapshot.id, base_version: snapshot.version, snapshot,
            } };
        } catch { return failure('invalid', 'El borrador local no es válido. Se conserva para que puedas descartarlo sin cambiar la ficha guardada.'); }
    }

    const initial = readRaw();
    expectedRaw = initial.ok ? initial.raw : undefined;

    function read() {
        const current = readRaw();
        return current.ok ? decode(current.raw).draft ?? null : null;
    }
    function inspect(currentSheet = {}) {
        const current = readRaw();
        if (!current.ok) return { draft: null, canRestore: false, reason: current.reason };
        const decoded = decode(current.raw);
        if (!decoded.ok) return { draft: null, canRestore: false, reason: decoded.reason };
        const draft = decoded.draft;
        if (!draft) return { draft: null, canRestore: false, reason: 'missing' };
        if (draft.base_sheet_id !== (currentSheet.id ?? null)) return { draft, canRestore: false, reason: 'different-sheet' };
        if (draft.base_version !== (currentSheet.version ?? null)) return { draft, canRestore: false, reason: 'server-changed' };
        if (draft.snapshot.import_id !== (currentSheet.import_id ?? null)) return { draft, canRestore: false, reason: 'different-import' };
        return { draft, canRestore: true, reason: 'compatible' };
    }
    function recordFor(snapshot) {
        return {
            schema_version: CONSUMOS_DRAFT_SCHEMA, scope: key, revision: revisionId(),
            changed_at: new Date().toISOString(), base_sheet_id: snapshot.id, base_version: snapshot.version, snapshot,
        };
    }
    function tooLarge(snapshot) {
        return failure('too-large', snapshot.pages
            ? 'La copia local supera 5 MB en total o 1 MB por hoja. Guarda el documento para conservar todo el trabajo.'
            : 'La copia local supera 1 MB. Guarda la ficha para conservar todo el trabajo.');
    }
    function write(value) {
        if (disposed) return failure('disposed', 'El almacén de borradores ya está cerrado.');
        const snapshot = snapshotOf(value);
        if (!snapshot) return failure('invalid-snapshot', 'No se ha podido guardar una copia válida de la ficha en este navegador.');
        const current = readRaw();
        if (!current.ok) return current;
        if (current.raw !== expectedRaw) return failure('conflict', 'Otra pestaña ha cambiado el borrador local. Se conserva su copia; guarda tu ficha o vuelve a abrirla para revisarla.');
        const decoded = decode(current.raw);
        if (!decoded.ok) return decoded;
        if (decoded.draft && (decoded.draft.base_sheet_id !== snapshot.id || (snapshot.version !== null && snapshot.version < decoded.draft.base_version))) {
            return failure('different-base', 'El borrador corresponde a otra ficha o a una versión posterior. Se conserva sin sustituirlo.');
        }
        const draft = recordFor(snapshot);
        const raw = JSON.stringify(draft);
        if (oversized(snapshot, raw)) return tooLarge(snapshot);
        try {
            storage.setItem(key, raw);
            expectedRaw = raw;
            status('stored', 'Borrador conservado en este navegador.');
            return { ok: true, draft };
        } catch { return failure('storage-error', 'No hay espacio o permiso para conservar el borrador local. La ficha sigue disponible para guardarla.'); }
    }
    function cancelTimer() {
        if (timer !== null) clearTimeout(timer);
        timer = null;
    }
    function schedule(value, delay = 500) {
        if (disposed) return failure('disposed', 'El almacén de borradores ya está cerrado.');
        const snapshot = snapshotOf(value);
        if (!snapshot) return failure('invalid-snapshot', 'No se ha podido preparar una copia válida de la ficha.');
        if (oversized(snapshot, JSON.stringify(recordFor(snapshot)))) return tooLarge(snapshot);
        cancelTimer();
        pending = snapshot;
        timer = setTimeout(flush, Number.isFinite(delay) ? Math.max(0, Math.min(delay, 10000)) : 500);
        return { ok: true, scheduled: true };
    }
    function flush() {
        cancelTimer();
        if (!pending) return { ok: true, draft: read(), unchanged: true };
        const snapshot = pending;
        const result = write(snapshot);
        // Retain failed pending writes for an explicit retry; no looping quota/conflict retries.
        if (result.ok) pending = null;
        return result;
    }
    function removeCurrent(current) {
        try {
            storage.removeItem(key);
            expectedRaw = null;
            status('cleared', 'Borrador local eliminado.');
            return { ok: true, cleared: true };
        } catch { return failure('storage-error', 'No se ha podido eliminar el borrador local.'); }
    }
    function clearAfterSave(savedSheet, { revision } = {}) {
        const current = readRaw();
        if (!current.ok) return current;
        const decoded = decode(current.raw);
        if (!decoded.ok) return decoded;
        const draft = decoded.draft;
        if (!draft) return { ok: true, cleared: false };
        if (pending || !revision || draft.revision !== revision) return failure('conflict', 'Hay cambios posteriores a la copia guardada. Su borrador local se conserva.');
        const validSave = Number.isSafeInteger(savedSheet?.id) && savedSheet.id > 0
            && Number.isSafeInteger(savedSheet.version) && savedSheet.version > 0
            && (draft.base_sheet_id === null ? savedSheet.version === 1
                : savedSheet.id === draft.base_sheet_id && savedSheet.version === draft.base_version + 1)
            && (savedSheet.import_id ?? null) === draft.snapshot.import_id;
        if (!validSave) return failure('unconfirmed-save', 'La versión guardada no confirma este borrador. La copia local se conserva.');
        return removeCurrent(current);
    }
    // Explicit user discard. A valid draft needs its displayed revision; invalid data needs explicit opt-in.
    function discard({ revision, allowInvalid = false } = {}) {
        const current = readRaw();
        if (!current.ok) return current;
        const decoded = decode(current.raw);
        if ((!decoded.ok && !allowInvalid) || (decoded.draft && decoded.draft.revision !== revision)) {
            return failure('conflict', 'El borrador ha cambiado. Revísalo antes de descartarlo.');
        }
        const result = removeCurrent(current);
        if (result.ok) { cancelTimer(); pending = null; }
        return result;
    }
    function dispose({ flushPending = true } = {}) {
        const result = flushPending ? flush() : { ok: true };
        cancelTimer();
        pending = null;
        disposed = true;
        return result;
    }
    return { read, inspect, write, schedule, flush, clearAfterSave, discard, dispose };
}
