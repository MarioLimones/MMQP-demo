/** Update the calendar without unloading the editor, its draft or the app shell. */
export function initializeHistoryCalendar(document, view = document.defaultView) {
    const root = () => document.getElementById('consumos-history');
    if (!root()) return;
    let pending = null;
    let sequence = 0;
    const pagePath = view.location.pathname;

    function message(calendar, text, error = false) {
        const status = calendar.querySelector('[data-history-status]');
        if (status) { status.textContent = text; status.dataset.error = String(error); }
    }

    async function navigate(url, { push = true, focus = null } = {}) {
        const current = root();
        if (!current) return;
        pending?.abort();
        const request = ++sequence;
        const controller = new view.AbortController();
        pending = controller;
        const endpoint = new view.URL(current.dataset.historyUrl, view.location.href);
        for (const key of ['month', 'day']) {
            if (url.searchParams.has(key)) endpoint.searchParams.set(key, url.searchParams.get(key));
        }
        current.setAttribute('aria-busy', 'true');
        message(current, 'Cargando registros…');
        try {
            const response = await view.fetch(endpoint.href, {
                headers: { Accept: 'text/html' }, signal: controller.signal,
            });
            if (!response.ok || response.redirected) throw new Error('History unavailable');
            const template = document.createElement('template');
            template.innerHTML = await response.text();
            const next = template.content.querySelector('#consumos-history');
            if (!next?.querySelector('#consumos-history-day')) throw new Error('Invalid history response');
            if (request !== sequence || current !== root()) return;

            if (current.dataset.historyMonth === next.dataset.historyMonth) {
                const day = current.querySelector('#consumos-history-day');
                const nextDay = next.querySelector('#consumos-history-day');
                day.replaceWith(nextDay);
                for (const link of current.querySelectorAll('[data-history-date]')) {
                    const replacement = next.querySelector(`[data-history-date="${link.dataset.historyDate}"]`);
                    if (!replacement) continue;
                    link.parentElement.classList.toggle('is-selected', replacement.parentElement.classList.contains('is-selected'));
                    link.setAttribute('aria-label', replacement.getAttribute('aria-label'));
                }
                current.dataset.historyDay = next.dataset.historyDay;
            } else {
                current.replaceWith(next);
            }

            const calendar = root();
            if (push) {
                // Preserve unrelated editor query parameters and the normal shareable URL.
                const target = new view.URL(view.location.href);
                for (const key of ['month', 'day']) target.searchParams.delete(key);
                target.searchParams.set('month', calendar.dataset.historyMonth);
                target.searchParams.set('day', calendar.dataset.historyDay);
                target.hash = url.hash;
                view.history.pushState(view.history.state, '', target.href);
            }
            if (focus && !focus.isConnected) {
                const replacement = focus.id ? document.getElementById(focus.id)
                    : [...calendar.querySelectorAll('a')].find(link => focus.getAttribute('aria-label')
                        ? link.getAttribute('aria-label') === focus.getAttribute('aria-label')
                        : link.getAttribute('href') === focus.getAttribute('href'));
                replacement?.focus({ preventScroll: true });
            }
            const heading = calendar.querySelector('#consumos-history-day-title')?.textContent.trim();
            message(calendar, `Registros actualizados: ${heading}.`);
        } catch (error) {
            if (error.name !== 'AbortError' && request === sequence && current === root()) {
                message(current, 'No se han podido cargar los registros. Vuelve a seleccionar el día o el mes para reintentarlo.', true);
            }
        } finally {
            if (request === sequence) { current.removeAttribute('aria-busy'); pending = null; }
        }
    }

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('#consumos-history a[href]');
        if (!link || link.target || link.hasAttribute('download') || !link.getAttribute('href').includes('?')) return;
        const url = new view.URL(link.href, view.location.href);
        if (url.origin !== view.location.origin || url.pathname !== pagePath || !url.searchParams.has('month')) return;
        event.preventDefault();
        navigate(url, { focus: link });
    });
    document.addEventListener('submit', event => {
        const form = event.target.closest('#consumos-history .consumos-history-month-form');
        if (!form || event.defaultPrevented || !form.checkValidity()) return;
        event.preventDefault();
        const url = new view.URL(form.action, view.location.href);
        url.searchParams.set('month', new view.FormData(form).get('month'));
        navigate(url, { focus: form.querySelector('input') });
    });
    view.addEventListener('popstate', () => {
        const url = new view.URL(view.location.href);
        const calendar = root();
        if (url.pathname !== pagePath || !calendar) return;
        // Section-only navigation must not fetch or reset a hidden calendar.
        if (url.searchParams.get('section') === 'preparar'
            || (!url.searchParams.has('month') && !url.searchParams.has('day'))) return;
        if (url.searchParams.get('month') === calendar.dataset.historyMonth && url.searchParams.get('day') === calendar.dataset.historyDay) return;
        navigate(url, { push: false });
    });
}

if (typeof document !== 'undefined') initializeHistoryCalendar(document);
