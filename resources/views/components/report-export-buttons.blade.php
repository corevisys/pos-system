@props(['route'])

{{--
    Phase 6: ONE shared export control replacing the dead Copy / Excel / PDF
    button sets on the decorative reports.

    It needs no per-page JS changes: on first paint it wraps window.fetch, and
    whenever a report /data request goes out it records that request's query
    string (`window.__reportFilters`). Export then re-issues the SAME filters to
    the SAME store-scoped /data route with `?export=<mode>`, which the shared
    EnsureReportExport middleware renders as a CSV download or print view.
--}}
<div {{ $attributes->merge(['class' => 'flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1']) }}
     x-data="reportExportButtons(@js($route))">
    <button type="button" @click="copyNow()" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
    <button type="button" @click="exportNow('csv')" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
    <button type="button" @click="exportNow('pdf')" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
</div>

@once
<script>
    // Record the filters of the most recent report data request, once per page.
    window.__reportFilters = window.__reportFilters || {};
    if (!window.__reportFetchWrapped) {
        window.__reportFetchWrapped = true;
        const _fetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            try {
                const url = typeof input === 'string' ? input : (input && input.url) || '';
                if (url.indexOf('/reports/') !== -1 && url.indexOf('/data') !== -1) {
                    const qs = url.split('?')[1] || '';
                    const params = new URLSearchParams(qs);
                    const obj = {};
                    params.forEach((v, k) => { if (k !== 'export') obj[k] = v; });
                    window.__reportFilters = obj;
                }
            } catch (e) { /* non-fatal */ }
            return _fetch(input, init);
        };
    }

    function reportExportButtons(route) {
        return {
            buildUrl(mode) {
                const params = new URLSearchParams(window.__reportFilters || {});
                params.set('export', mode);
                return route + '?' + params.toString();
            },
            exportNow(mode) { window.location.href = this.buildUrl(mode); },
            copyNow() {
                // Ask the server for the current rows as CSV-shaped text is
                // unnecessary — the on-screen table is already the export. Copy
                // the visible table rows as tab-separated text.
                const table = document.querySelector('table');
                if (!table) { if (window.showError) showError('No table to copy.'); return; }
                const rows = Array.from(table.querySelectorAll('tr')).map(tr =>
                    Array.from(tr.querySelectorAll('th,td')).map(td => td.innerText.trim()).join('\t')
                ).join('\n');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(rows).then(() => {
                        if (window.showSuccess) showSuccess('Copied to clipboard.');
                    });
                }
            }
        };
    }
</script>
@endonce
