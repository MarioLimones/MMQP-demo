/* One queue per immutable draft identity. No save request can open a new draft. */
class ScheduleDraftSession {
    constructor(draft, onState = () => {}) {
        this.id = draft.id;
        this.week = draft.semana_id;
        this.version = draft.version;
        this.onState = onState;
        this.queue = Promise.resolve();
        this.savedFingerprint = null;
        this.pending = null;
        this.blocked = false;
        this.closed = draft.estado !== 'borrador';
        this.publishing = false;
    }

    static async request(url, body) {
        const response = await fetch(url, {
            method: 'POST', headers: {
                'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            }, body: JSON.stringify(body),
        });
        const data = await response.json();
        if (!response.ok) {
            const error = new Error(data.message || 'No se pudo guardar. Reintenta cuando vuelva la conexión.');
            error.status = response.status;
            throw error;
        }
        return data;
    }

    static async open(week) {
        return (await this.request('/api/horarios-semanales/borrador/abrir', { semana_id: week })).draft;
    }

    markLoaded(content) { this.savedFingerprint = JSON.stringify(content); }
    dirty(content) { return this.savedFingerprint !== JSON.stringify(content); }

    save(content) {
        if (this.closed || this.publishing || this.blocked) {
            return Promise.reject(new Error('Revisa el conflicto y vuelve a abrir el borrador antes de guardar.'));
        }
        // Capture values now, before any week change or later editor mutation.
        const snapshot = JSON.parse(JSON.stringify(content));
        this.queue = this.queue.catch(() => {}).then(() => this.performSave(snapshot));
        return this.queue;
    }

    async performSave(content, force = false) {
        if (this.closed || this.blocked) throw new Error('El borrador ya no admite guardados. Recarga para revisar.');
        try {
            // After a lost response, retry the exact request/key before sending newer changes.
            if (this.pending) await this.sendPending();
            if (!force && !this.dirty(content)) return this.version;
            this.onState('Guardando borrador…');
            this.pending = {
                fingerprint: JSON.stringify(content),
                body: { ...content, draft_id: this.id, semana_id: this.week,
                    version: this.version, request_id: crypto.randomUUID() },
            };
            await this.sendPending();
            return this.version;
        } catch (error) {
            if (error.status === 409 || error.status === 404) this.blocked = true;
            if (error.status && error.status < 500) this.pending = null;
            this.onState(error.message, error);
            throw error;
        }
    }

    async sendPending() {
        const pending = this.pending;
        const data = await ScheduleDraftSession.request('/api/horarios-semanales/borrador', pending.body);
        this.version = data.draft.version;
        this.savedFingerprint = pending.fingerprint;
        this.pending = null;
        this.onState('Borrador guardado · pendiente de publicar');
    }

    publish(content, roles) {
        if (this.closed || this.blocked || this.publishing) return Promise.reject(new Error('Revisa el estado del borrador antes de publicar.'));
        this.publishing = true;
        const snapshot = JSON.parse(JSON.stringify(content));
        this.queue = this.queue.catch(() => {}).then(async () => {
            await this.performSave(snapshot, true);
            const result = await ScheduleDraftSession.request(`/admin/horarios/resultado/${this.id}/publicar`, {
                semana_id: this.week, version: this.version, roles: [...roles],
            });
            this.closed = true;
            return result;
        }).catch(error => {
            if (error.status === 409 || error.status === 404) this.blocked = true;
            this.onState(error.message, error);
            throw error;
        }).finally(() => { this.publishing = false; });
        return this.queue;
    }
}

globalThis.ScheduleDraftSession = ScheduleDraftSession;
