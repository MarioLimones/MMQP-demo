<style>
    /* Styles belong only to the schedule history and its toolbar. */
    .schedule-history, .history-toolbar {
        --history-panel: #fff;
        --history-surface: #fff;
        --history-muted: #f1f5f9;
        --history-border: #cbd5e1;
        --history-text: #172033;
        --history-secondary: #475569;
        --history-year: #f8fafc;
        --history-year-open: #fff1f2;
        --history-year-text: #9f1239;
        --history-year-accent: #e11d48;
        --history-month-open: #eff6ff;
        --history-month-text: #1e40af;
        --history-month-accent: #93b4db;
        --history-table-head: #eaf0f6;
        --history-row-alt: #f8fafc;
        --history-row-hover: #eff6ff;
        --history-focus: #be123c;
        color: var(--history-text);
    }
    .dark .schedule-history, .dark .history-toolbar {
        --history-panel: #1b2739;
        --history-surface: #172538;
        --history-muted: #26364c;
        --history-border: #405269;
        --history-text: #f1f5f9;
        --history-secondary: #c0cddd;
        --history-year: #263448;
        --history-year-open: #442b38;
        --history-year-text: #ffe4e6;
        --history-year-accent: #fb7185;
        --history-month-open: #233c53;
        --history-month-text: #e0f2fe;
        --history-month-accent: #81a8ce;
        --history-table-head: #101c2e;
        --history-row-alt: #1d2d42;
        --history-row-hover: #2a3e58;
        --history-focus: #fda4af;
    }
    .schedule-history .history-panel { background: var(--history-panel); border-color: var(--history-border); overflow: visible; }
    .history-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
    .history-toolbar-actions { position: relative; display: flex; align-items: center; gap: .75rem; flex-shrink: 0; margin-left: auto; }
    .history-toolbar-actions > a { white-space: nowrap; }
    .history-transfer-toggle { display: flex; align-items: center; justify-content: center; gap: .375rem; min-height: 2.75rem; padding: .5rem .625rem; border-radius: .5rem; color: var(--history-secondary); font-size: .8125rem; font-weight: 600; list-style: none; cursor: pointer; }
    .history-transfer-toggle::-webkit-details-marker { display: none; }
    .history-transfer-toggle:hover, .history-transfer-menu[open] .history-transfer-toggle { color: var(--history-text); background: var(--history-muted); }
    .history-transfer-menu[open] .history-transfer-chevron { transform: rotate(180deg); }
    .history-toolbar :is(summary, a, button, input):focus-visible { outline: 3px solid var(--history-focus); outline-offset: 3px; }
    .history-toolbar .history-transfer { position: absolute; z-index: 20; top: calc(100% + .75rem); right: 0; width: min(30rem, calc(100vw - 2rem)); max-height: 60dvh; overflow-y: auto; padding: 1.25rem; background: var(--history-panel); border: 1px solid var(--history-border); border-radius: .875rem; box-shadow: 0 12px 32px rgb(0 0 0 / .16); }
    .history-toolbar .history-transfer-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .history-toolbar .history-transfer-heading h3 { font-size: 1rem; font-weight: 800; color: var(--history-text); }
    .history-toolbar .history-transfer-description { margin-top: .75rem; color: var(--history-secondary); font-size: .875rem; line-height: 1.5; }
    .history-toolbar .history-transfer-form { display: flex; flex-direction: column; align-items: stretch; gap: .875rem; margin-block: 1rem .75rem; }
    .history-toolbar .history-transfer-field { flex: 1; min-width: 0; }
    .history-toolbar .history-transfer-field label { display: block; margin-bottom: .375rem; font-size: .875rem; font-weight: 700; }
    .history-toolbar .history-transfer-field input { display: block; width: 100%; min-width: 0; min-height: 2.75rem; border: 1px solid var(--history-border); border-radius: .5rem; color: var(--history-text); background: var(--history-surface); font-size: .875rem; }
    .history-toolbar .history-transfer-field input::file-selector-button { min-height: 2.75rem; padding: .5rem .75rem; margin-right: .75rem; border: 0; border-right: 1px solid var(--history-border); color: var(--history-text); background: var(--history-muted); font: inherit; font-weight: 650; cursor: pointer; }
    .history-toolbar .history-transfer-field input::file-selector-button:hover { background: var(--history-row-hover); }
    .history-toolbar .history-transfer-field input[aria-invalid="true"] { border-color: var(--history-year-accent); }
    .history-toolbar .history-transfer-form > button { margin-top: 0; align-self: flex-end; }
    .history-toolbar .history-transfer-button { display: inline-flex; align-items: center; justify-content: center; gap: .375rem; min-height: 2.75rem; padding: .65rem .875rem; border: 1px solid transparent; border-radius: .5rem; font-size: .875rem; line-height: 1.25; font-weight: 750; white-space: nowrap; cursor: pointer; transition: background-color .15s ease; }
    .history-toolbar .history-transfer-primary { color: #fff; background: #c9252d; }
    .history-toolbar .history-transfer-primary:hover { color: #fff; background: #a81d25; }
    .history-toolbar .history-transfer-secondary { color: var(--history-text); background: var(--history-muted); border-color: var(--history-border); }
    .history-toolbar .history-transfer-secondary:hover { background: var(--history-row-hover); }
    .history-toolbar .history-transfer-help { margin-top: .375rem; color: var(--history-secondary); font-size: .8125rem; line-height: 1.5; }
    .history-toolbar .history-transfer-error { margin-top: .375rem; color: #b91c1c; font-size: .875rem; font-weight: 650; line-height: 1.5; }
    .dark .history-toolbar .history-transfer-error { color: #fecaca; }
    .schedule-history .history-notice { padding: 1rem 1.25rem; border: 1px solid; border-radius: .75rem; font-size: .875rem; line-height: 1.5; }
    .schedule-history .history-notice-success { color: #166534; background: #f0fdf4; border-color: #86c79a; }
    .schedule-history .history-notice-error { color: #991b1b; background: #fef2f2; border-color: #e8b1b1; }
    .dark .schedule-history .history-notice-success { color: #a7f3c4; background: #153b30; border-color: #3d765e; }
    .dark .schedule-history .history-notice-error { color: #fecaca; background: #44272f; border-color: #87515b; }
    .schedule-history .history-years { display: grid; gap: 1rem; padding: 1.25rem; }
    .schedule-history .history-year { min-width: 0; margin: 0; border: 1px solid var(--history-border); border-radius: .875rem; background: var(--history-year); }
    .schedule-history .history-year.is-open { border-color: var(--history-year-accent); }
    .schedule-history :is(.history-year-heading, .history-month-heading) { border-radius: inherit; }
    .schedule-history :is(.history-year-toggle, .history-month-toggle) { gap: .75rem; min-height: 3.75rem; padding: 1rem 1.125rem; text-align: left; color: var(--history-text); background: transparent; border-radius: inherit; }
    .schedule-history :is(.history-year-toggle, .history-month-toggle):hover { background: var(--history-muted); }
    .schedule-history .history-year-toggle[aria-expanded="true"] { background: var(--history-year-open); color: var(--history-year-text); border-radius: .875rem .875rem 0 0; }
    .schedule-history :is(.history-year-title, .history-month-title) { color: inherit; }
    .schedule-history .history-year-title { font-size: 1.25rem; font-weight: 800; letter-spacing: -.025em; }
    .schedule-history :is(.history-year-toggle, .history-month-toggle) svg { color: var(--history-secondary); flex-shrink: 0; }
    .schedule-history :is(.history-year-toggle, .history-month-toggle)[aria-expanded="true"] svg { color: inherit; }
    .schedule-history :is(button, a, input, .history-table-scroll):focus-visible { outline: 3px solid var(--history-focus); outline-offset: 3px; }
    .schedule-history .history-disclosure-state { margin-left: auto; font-size: .75rem; font-weight: 600; white-space: nowrap; color: var(--history-secondary); }
    .schedule-history [aria-expanded="true"] > .history-disclosure-state { color: inherit; }
    .schedule-history [aria-expanded="true"] > .history-disclosure-state::before { content: ''; display: inline-block; vertical-align: middle; width: .375rem; height: .375rem; margin-right: .375rem; border-radius: 50%; background: currentColor; }
    .schedule-history .history-count { color: var(--history-secondary); background: var(--history-surface); border: 1px solid var(--history-border); border-radius: 999px; padding: .3rem .65rem; font-size: .75rem; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; box-shadow: none; }
    .schedule-history .history-months { margin: 1rem 1rem 1.125rem; padding-left: 0; }
    .schedule-history .history-months > .history-month { margin-block: 0; }
    .schedule-history .history-months > .history-month + .history-month { margin-top: .875rem; }
    .schedule-history .history-month { border: 1px solid var(--history-border); border-radius: .75rem; background: var(--history-surface); }
    .schedule-history .history-month-toggle { min-height: 3.5rem; padding: .8rem 1rem; border: 0; }
    .schedule-history .history-month-title { font-size: 1rem; font-weight: 700; }
    .schedule-history .history-month.is-open { border-color: var(--history-month-accent); }
    .schedule-history .history-month-toggle[aria-expanded="true"] { color: var(--history-month-text); background: var(--history-month-open); border-bottom: 1px solid var(--history-month-accent); border-radius: .75rem .75rem 0 0; }
    .schedule-history .history-table-scroll { margin-top: 0; max-width: 100%; border: 0; border-radius: 0 0 .75rem .75rem; box-shadow: none; scrollbar-color: var(--history-border) var(--history-surface); }
    .schedule-history .history-table { min-width: 960px; color: var(--history-text); background: var(--history-surface); }
    .schedule-history .history-table thead th { background: var(--history-table-head); color: var(--history-secondary); border-color: var(--history-border); white-space: nowrap; font-size: .75rem; font-weight: 800; padding: .875rem 1rem; }
    .schedule-history .history-table tbody :is(td, th) { color: var(--history-text); background: var(--history-surface); border-bottom: 1px solid var(--history-border); padding: 1rem; vertical-align: middle; font-size: .875rem; }
    .schedule-history .history-table tbody tr:nth-child(even) > * { background: var(--history-row-alt); }
    .schedule-history .history-table tbody tr:last-child > * { border-bottom: 0; }
    .schedule-history .history-table tbody tr:hover > *, .schedule-history .history-table tbody tr:focus-within > * { background: var(--history-row-hover); }
    .schedule-history .history-table tbody :is(.text-slate-400, .text-slate-500) { color: var(--history-secondary); }
    .schedule-history .history-table .rounded-full { white-space: nowrap; flex-shrink: 0; }
    .schedule-history .history-week-cell { text-align: left; white-space: nowrap; }
    .schedule-history .history-state-line { display: flex; align-items: center; gap: .5rem; margin-top: .45rem; }
    .schedule-history .history-state { display: inline-flex; align-items: center; gap: .375rem; color: var(--history-secondary); background: var(--history-muted); border: 1px solid var(--history-border); border-radius: .375rem; padding: .25rem .45rem; font-size: .75rem; line-height: 1.3; font-weight: 650; white-space: nowrap; }
    .schedule-history .history-state-dot { width: .3rem; height: .3rem; border-radius: 50%; background: currentColor; flex-shrink: 0; }
    .schedule-history [data-state="publicado"] .history-state { color: #166534; background: #f0fdf4; border-color: #86c79a; }
    .schedule-history [data-state="borrador"] .history-state { color: #854d0e; background: #fffbeb; border-color: #d4ac54; }
    .schedule-history [data-state="historico"] .history-state { color: #075985; background: #f0f9ff; border-color: #8cb9d3; }
    .dark .schedule-history [data-state="publicado"] .history-state { color: #a7f3c4; background: #153b30; border-color: #3d765e; }
    .dark .schedule-history [data-state="borrador"] .history-state { color: #fde68a; background: #41341c; border-color: #846b2a; }
    .dark .schedule-history [data-state="historico"] .history-state { color: #bae6fd; background: #15364b; border-color: #426e8d; }
    .schedule-history .history-version { color: var(--history-secondary); font-size: .75rem; font-weight: 500; }
    .schedule-history .history-origin { color: var(--history-secondary); background: var(--history-muted); font-size: .75rem; letter-spacing: 0; text-transform: none; }
    .schedule-history .history-view { min-height: 2.75rem; color: #fff; background: #c9252d; font-size: .75rem; font-weight: 750; white-space: nowrap; padding: .65rem .8rem; }
    .schedule-history .history-view:hover { color: #fff; background: #a81d25; }
    .schedule-history .history-export { font-size: .75rem; }
    .schedule-history .history-delete { min-width: 2.75rem; min-height: 2.75rem; justify-content: center; color: #b91c1c; background: #fef2f2; border: 1px solid #e8b1b1; }
    .schedule-history .history-delete:hover { background: #fee2e2; }
    .dark .schedule-history .history-delete { color: #fecaca; background: #44272f; border-color: #87515b; }
    .dark .schedule-history .history-delete:hover { background: #5c303b; }
    @media (max-width: 767px) {
        .history-toolbar-actions { width: 100%; justify-content: flex-end; gap: .5rem; }
        .history-transfer-toggle { padding-inline: .5rem; }
        .history-toolbar .history-transfer { padding: 1rem; }
        .history-toolbar .history-transfer-form { flex-direction: column; }
        .history-toolbar .history-transfer-field { width: 100%; }
        .history-toolbar .history-transfer-form > button { width: 100%; margin-top: 0; }
        .schedule-history .history-years { padding: .625rem; gap: .75rem; }
        .schedule-history :is(.history-year-toggle, .history-month-toggle) { gap: .5rem; padding: .875rem .75rem; }
        .schedule-history .history-year-title { font-size: 1.125rem; }
        .schedule-history .history-disclosure-state { font-size: .6875rem; }
        .schedule-history .history-count { padding: .25rem .45rem; font-size: .6875rem; }
        .schedule-history .history-months { margin: .75rem .5rem; padding-left: 0; }
        .schedule-history .history-month-toggle { padding: .75rem .625rem; }
        .schedule-history .history-month-title { font-size: .9375rem; }
        .schedule-history .history-month-toggle[aria-expanded="false"] > .history-disclosure-state { display: none; }
        .schedule-history .history-table tbody :is(td, th), .schedule-history .history-table thead th { padding-left: .875rem; padding-right: .875rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .schedule-history *, .schedule-history *::before, .schedule-history *::after, .history-toolbar * { transition: none !important; }
    }
    @media (forced-colors: active) {
        .schedule-history [aria-expanded="true"] { outline: 2px solid Highlight; outline-offset: -3px; }
    }
</style>
<noscript>
    <style>
        .schedule-history [x-cloak], .schedule-history [x-show] { display: block !important; }
        .schedule-history .history-disclosure-state { display: none !important; }
    </style>
</noscript>
