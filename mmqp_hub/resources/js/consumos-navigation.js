/** Local navigation preserves the editor and the calendar when changing sections. */
export function initializeConsumosNavigation(document, view = document.defaultView) {
    const navigation = document.getElementById('consumos-section-nav');
    const prepare = document.getElementById('consumos-prepare-panel');
    const history = document.getElementById('consumos-history-panel');
    if (!navigation || !prepare || !history) return;
    const prepareLink = document.getElementById('consumos-prepare-link');
    const historyLink = document.getElementById('consumos-history-link');
    const pagePath = view.location.pathname;

    function sectionFromUrl(url) {
        const section = url.searchParams.get('section');
        if (section === 'preparar' || section === 'historial') return section;
        return url.searchParams.has('month') || url.searchParams.has('day') || url.hash.startsWith('#consumos-history')
            ? 'historial' : 'preparar';
    }
    function sectionUrl(section) {
        const url = new view.URL(view.location.href);
        url.searchParams.set('section', section);
        url.hash = '';
        return url;
    }
    function render() {
        const section = sectionFromUrl(new view.URL(view.location.href));
        prepare.hidden = section !== 'preparar';
        history.hidden = section !== 'historial';
        for (const [link, name] of [[prepareLink, 'preparar'], [historyLink, 'historial']]) {
            if (section === name) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
            link.href = sectionUrl(name).href;
        }
    }
    navigation.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[aria-controls]');
        if (link !== prepareLink && link !== historyLink) return;
        event.preventDefault();
        const section = link === historyLink ? 'historial' : 'preparar';
        if (section !== sectionFromUrl(new view.URL(view.location.href))) {
            view.history.pushState(view.history.state, '', sectionUrl(section).href);
        }
        render();
    });
    view.addEventListener('popstate', () => { if (view.location.pathname === pagePath) render(); });
    view.addEventListener('hashchange', () => { if (view.location.pathname === pagePath) render(); });
    render();
}

if (typeof document !== 'undefined') initializeConsumosNavigation(document);
