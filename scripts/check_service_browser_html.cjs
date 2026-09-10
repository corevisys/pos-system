/**
 * Browser-level verification for the redesigned Service module pages.
 *
 * Reads the rendered HTML files dumped by
 * Tests\Feature\ServiceControllerFeatureTest::test_dump_rendered_pages_for_node_check
 * and for each page:
 *   1. node --check every inline <script> body (catches the "leaked JS text outside
 *      <script>" / attribute-boundary-break bug class PHPUnit cannot catch).
 *   2. Scans for raw unescaped quotes from a seeded "test\"service" name leaking
 *      outside <script> tags (the exact Add/Edit Item breakage).
 *   3. Verifies the page ships its Alpine component via Alpine.data(...), not an
 *      inline x-data="{...}" object on the page's own root.
 *
 * Usage: node scripts/check_service_browser_html.js
 * Exit code 0 = all checks pass; 1 = a check failed.
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const dir = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const files = ['services_list.html', 'services_add.html', 'services_edit.html'];

let failed = false;
function fail(msg) {
    failed = true;
    console.error('  FAIL:', msg);
}

for (const file of files) {
    const full = path.join(dir, file);
    console.log(`\n=== ${file} ===`);
    if (!fs.existsSync(full)) {
        fail(`missing rendered file: ${full}`);
        continue;
    }
    const html = fs.readFileSync(full, 'utf8');

    // ── 1. Extract every inline <script> body and node --check it ──────────
    const scriptRe = /<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi;
    let match;
    let index = 0;
    let scriptCount = 0;
    while ((match = scriptRe.exec(html)) !== null) {
        const body = match[1];
        // Skip empty / whitespace-only or src-only scripts (no inline body).
        if (!body.trim()) continue;
        scriptCount++;
        const tmp = path.join(dir, `_inline_${file}_${index++}.js`);
        fs.writeFileSync(tmp, body);
        try {
            execFileSync('node', ['--check', tmp], { stdio: 'pipe' });
            console.log(`  OK script #${scriptCount} (${body.length} bytes) node --check`);
        } catch (e) {
            fail(`inline script #${scriptCount} failed node --check: ${e.stderr?.toString().slice(0, 500)}`);
        } finally {
            fs.unlinkSync(tmp);
        }
    }
    if (scriptCount === 0) {
        fail('no inline scripts found to check');
    }

    // ── 2. Raw-quote leak scan ──────────────────────────────────────────────
    // The seeded service name is test"service. In safe HTML it renders as
    // test"service (Blade-escaped). A raw `test"service` outside a <script>
    // tag means escaping failed and JS/markup could have leaked.
    if (html.includes('test"service')) {
        fail('raw unescaped "test"service" found in rendered HTML (escaping failed)');
    } else {
        console.log('  OK no raw "test"service" leak');
    }

    // ── 3. Alpine.data pattern presence (per page) ──────────────────────────
    const expectedComponent = file === 'services_list.html'
        ? "Alpine.data('servicesListPage'"
        : file === 'services_add.html'
            ? "Alpine.data('addServiceForm'"
            : "Alpine.data('editServiceForm'";
    if (html.includes(expectedComponent)) {
        console.log(`  OK ${expectedComponent} registered`);
    } else {
        fail(`missing ${expectedComponent} registration`);
    }
}

console.log(failed ? '\nBROWSER CHECK FAILED' : '\nALL BROWSER CHECKS PASSED');
process.exit(failed ? 1 : 0);
