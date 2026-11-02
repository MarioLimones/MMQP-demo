// FullCalendar 6 renders weekday labels on non-link anchors and labels its view
// harness without a role. Keep those names on elements that support them.
export function dayHeaderDidMount({ el }) {
    const cushion = el.querySelector('a.fc-col-header-cell-cushion[aria-label]');
    if (!cushion || cushion.matches('[href], [role="link"], [role="button"], [data-navlink], [tabindex="0"]')) return;

    const label = cushion.getAttribute('aria-label');
    if (!label) return;

    el.setAttribute('aria-label', label);
    cushion.removeAttribute('aria-label');
}

export function viewDidMount({ el }) {
    const harness = el.closest('.fc-view-harness');
    if (harness?.hasAttribute('aria-labelledby')) {
        harness.setAttribute('role', 'group');
    }
}
