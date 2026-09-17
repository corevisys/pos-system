/**
 * Verification probe (STEP 3/4) for the dialog stacking fix.
 *
 * For every dialog of the "fixed overlay + positioned blurred backdrop sibling
 * + static inline-block panel" family it asserts, in a real browser:
 *   PASS/FAIL  panel is the top-most element at its own centre (not the blur layer)
 *   PASS/FAIL  no ancestor of the panel carries filter/backdrop-filter
 *   PASS/FAIL  clicking Cancel closes it
 *   PASS/FAIL  clicking inside the panel body keeps it open
 *   PASS/FAIL  clicking the backdrop closes it
 *   PASS/FAIL  Escape closes it
 *   PASS/FAIL  body scroll lock is applied while open and released after close
 *   PASS/FAIL  the page is fully interactive again after close
 * Plus: Languages confirm actually activates the language (DB backed), light+dark
 * screenshots, and a zero-console-error check.
 *
 * Writes storage/app/browser-check/verify-report.json + PNG screenshots.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = Number(process.env.CDP_PORT || 9336);
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8123';
const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile-verify');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class Cdp {
    constructor(url) { this.url = url; this.seq = 0; this.pending = new Map(); this.ev = new Map(); this.pageErrors = []; this.consoleErrors = []; }
    connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.url);
            this.ws.addEventListener('open', () => resolve());
            this.ws.addEventListener('error', () => reject(new Error('ws error')));
            this.ws.addEventListener('message', (m) => {
                const msg = JSON.parse(m.data);
                if (msg.id && this.pending.has(msg.id)) {
                    const { res, rej } = this.pending.get(msg.id); this.pending.delete(msg.id);
                    msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result); return;
                }
                if (msg.method === 'Runtime.exceptionThrown') this.pageErrors.push((msg.params.exceptionDetails || {}).text);
                if (msg.method === 'Runtime.consoleAPICalled') {
                    const p = msg.params;
                    const text = (p.args || []).map(a => a.value ?? a.description ?? a.type).join(' ');
                    if (p.type === 'error') this.consoleErrors.push(text);
                    else if (/Tracking Prevention/.test(text) === false && p.type === 'warning') this.consoleErrors.push('[warn] ' + text);
                }
                if (msg.method === 'Log.entryAdded' && msg.params.entry.level === 'error') {
                    this.consoleErrors.push(msg.params.entry.text);
                }
                const set = this.ev.get(msg.method); if (set) [...set].forEach(f => f(msg.params));
            });
        });
    }
    on(m, f) { if (!this.ev.has(m)) this.ev.set(m, new Set()); this.ev.get(m).add(f); }
    off(m, f) { const s = this.ev.get(m); if (s) s.delete(f); }
    send(method, params = {}) {
        const id = ++this.seq;
        return new Promise((res, rej) => {
            this.pending.set(id, { res, rej });
            this.ws.send(JSON.stringify({ id, method, params }));
            setTimeout(() => { if (this.pending.has(id)) { this.pending.delete(id); rej(new Error('timeout ' + method)); } }, 20000);
        });
    }
    wait(method, timeout = 20000) {
        return new Promise((res, rej) => {
            const t = setTimeout(() => { this.off(method, h); rej(new Error('timeout ' + method)); }, timeout);
            const h = (p) => { clearTimeout(t); this.off(method, h); res(p); };
            this.on(method, h);
        });
    }
    async eval(expression) {
        const r = await this.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture: true });
        if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 500));
        return r.result.value;
    }
    async nav(url) {
        const l = this.wait('Page.loadEventFired').catch(() => null);
        await this.send('Page.navigate', { url });
        await l;
        for (let i = 0; i < 60; i++) { try { if (await this.eval('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); }
        await sleep(500);
    }
    async click(sel) {
        const pt = await this.eval(`(() => {
            const el = (${sel});
            if (!el) return null;
            const r = el.getBoundingClientRect();
            return { x: Math.round(r.left + r.width/2), y: Math.round(r.top + r.height/2) };
        })()`);
        if (!pt) return false;
        const base = { x: pt.x, y: pt.y, button: 'left', clickCount: 1 };
        await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...base });
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...base });
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...base });
        return true;
    }
    async clickPoint(x, y) {
        const base = { x: Math.round(x), y: Math.round(y), button: 'left', clickCount: 1 };
        await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...base });
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...base });
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...base });
    }
    async esc() {
        try {
            await this.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27, text: '\u001b' });
            await this.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
            return 'cdp';
        } catch (e) {
            // Fall back to a synthetic window event (Alpine listens on window).
            await this.eval(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true }))`);
            return 'synthetic';
        }
    }
    async shot(name) {
        const r = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(OUT_DIR, name + '.png'), Buffer.from(r.data, 'base64'));
    }
    async shotPanel(name) {
        const clip = await this.eval(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && el.classList.contains('fixed') && getComputedStyle(el).display !== 'none');
            if (!ov) return null;
            const p = [...ov.querySelectorAll('[class*="sm:max-w-"]')].find(e => getComputedStyle(e).display !== 'none');
            if (!p) return null;
            const r = p.getBoundingClientRect();
            return { x: Math.max(0, r.left - 40), y: Math.max(0, r.top - 40), width: r.width + 80, height: r.height + 80, scale: 1 };
        })()`);
        if (!clip) return;
        const r = await this.send('Page.captureScreenshot', { format: 'png', clip });
        fs.writeFileSync(path.join(OUT_DIR, name + '.png'), Buffer.from(r.data, 'base64'));
    }
}

/** State of the currently open dialog (overlay may live anywhere in the DOM). */
const STATE = `(() => {
  const vis = (el) => el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 10;
  const boxOf = (el) => { const r = el.getBoundingClientRect(); return { w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top) }; };
  const candidates = [...document.querySelectorAll('.fixed.inset-0')].filter(el => {
    if (!vis(el)) return false;
    return getComputedStyle(el).position === 'fixed' && el.getBoundingClientRect().width > 200;
  });
  const out = {
    bodyScrollLocked: document.body.classList.contains('overflow-y-hidden'),
    bodyOverflowY: getComputedStyle(document.body).overflowY,
    candidateCount: candidates.length,
    candidates: candidates.slice(0, 6).map(el => ({ cls: String(el.className).slice(0, 70), parent: el.parentElement ? el.parentElement.tagName : null, ...boxOf(el) })),
  };

  // Deterministic: the open dialog is the candidate that CONTAINS a visible panel.
  let ov = null, panel = null;
  for (const c of candidates) {
    const ps = [c, ...c.querySelectorAll('*')].filter(el => vis(el) && /sm:max-w-/.test(String(el.className || '')) && /rounded/.test(String(el.className || '')));
    if (ps.length) { ov = c; panel = ps[0]; break; }
  }
  out.open = !!ov;
  if (!ov) {
    const c = candidates[0];
    out.panel = 'NO-OPEN-DIALOG';
    if (c) {
      out.descendantCount = c.querySelectorAll('*').length;
      out.maxWidthClasses = [...c.querySelectorAll('*')].map(e => String(e.className || '')).filter(x => /max-w-/.test(x)).slice(0, 8);
      out.firstChildCls = c.firstElementChild ? String(c.firstElementChild.className).slice(0, 120) : null;
      out.textSample = (c.innerText || '').trim().slice(0, 120);
    }
    return out;
  }
  const pr = panel.getBoundingClientRect();
  const stack = document.elementsFromPoint(Math.round(pr.left + pr.width/2), Math.round(pr.top + pr.height/2));
  const top = stack[0];
  out.panelTopMost = top === panel || panel.contains(top);
  out.topMostCls = String(top.className).slice(0, 70);
  out.panelZ = getComputedStyle(panel).zIndex;
  out.panelPosition = getComputedStyle(panel).position;
  let node = panel, blurredAncestor = null;
  while (node && node !== document.documentElement) {
    const cs = getComputedStyle(node);
    const bf = cs.backdropFilter || cs.webkitBackdropFilter;
    if ((cs.filter && cs.filter !== 'none') || (bf && bf !== 'none')) { blurredAncestor = String(node.className).slice(0,70); break; }
    node = node.parentElement;
  }
  out.panelHasBlurredAncestor = blurredAncestor;
  // The backdrop LAYER ROOT is the direct child of the overlay that contains the
  // blurred element. Its explicit z-index is what orders it against the panel;
  // the inner blurred div may legitimately stay z-index auto because it is
  // painted inside the root's stacking context.
  // Only consider blur layers that are NOT ancestors of the panel (the backdrop
  // must be a sibling layer, never a parent of the dialog).
  const blurredLeaf = [ov, ...ov.querySelectorAll('*')].find(el => {
    if (el === panel || el.contains(panel)) return false;
    const cs = getComputedStyle(el);
    const bf = cs.backdropFilter || cs.webkitBackdropFilter;
    return bf && bf !== 'none' && vis(el);
  });
  // The backdrop LAYER ROOT = the highest ancestor of the blurred element that
  // still does NOT contain the panel (i.e. the sibling layer whose explicit
  // z-index orders it against the panel).
  let layerRoot = blurredLeaf;
  while (
    layerRoot && layerRoot.parentElement &&
    layerRoot !== ov && layerRoot.parentElement !== ov &&
    !layerRoot.parentElement.contains(panel)
  ) {
    layerRoot = layerRoot.parentElement;
  }
  if (layerRoot && (layerRoot === panel || layerRoot.contains(panel))) layerRoot = null;
  out.backdropLeafCls = blurredLeaf ? String(blurredLeaf.className).slice(0, 80) : null;
  out.backdropLeafZ = blurredLeaf ? getComputedStyle(blurredLeaf).zIndex : null;
  out.backdropRootCls = layerRoot ? String(layerRoot.className).slice(0, 90) : null;
  out.blurLayerCls = out.backdropRootCls;
  out.blurLayerZ = layerRoot ? getComputedStyle(layerRoot).zIndex : null;
  out.blurLayerPosition = layerRoot ? getComputedStyle(layerRoot).position : null;
  // Real invariant: the panel must not be a descendant of the backdrop layer and
  // the backdrop layer root must sit at a lower explicit z-index than the panel.
  out.panelNotInsideBackdrop = !(layerRoot && (layerRoot === panel || layerRoot.contains(panel)));
  out.panelAboveBackdrop = (() => {
    const pz = parseInt(getComputedStyle(panel).zIndex, 10);
    const bz = layerRoot ? parseInt(getComputedStyle(layerRoot).zIndex, 10) : NaN;
    return Number.isFinite(pz) && Number.isFinite(bz) && pz > bz;
  })();
  // Also record the unresolved ancestors for debugging.
  out.backdropChain = [];
  let dbg = blurredLeaf;
  while (dbg && dbg !== ov.parentElement) { out.backdropChain.push(String(dbg.className).slice(0, 70) + ' z=' + getComputedStyle(dbg).zIndex + ' pos=' + getComputedStyle(dbg).position); dbg = dbg.parentElement; }
  const cancel = [...ov.querySelectorAll('button')].find(b => /^\\s*Cancel\\s*$/i.test(b.textContent));
  out.hasCancel = !!cancel;
  const r2 = panel.getBoundingClientRect();
  out.insidePoint = { x: Math.round(r2.left + r2.width/2), y: Math.round(r2.top + 24) };
  return out;
})()`;

const OPENERS = {
    languages: `[...document.querySelectorAll('button')].find(b => /^Activate Language$/i.test(b.getAttribute('title')||'') || /Set Active/i.test(b.textContent))`,
    units: `[...document.querySelectorAll('button')].find(b => /New Unit/i.test(b.textContent))`,
    tax: `[...document.querySelectorAll('button')].find(b => /New Tax/i.test(b.textContent))`,
    currency: `[...document.querySelectorAll('button')].find(b => /New Currency|Add Currency/i.test(b.textContent))`,
    paymentTypes: `[...document.querySelectorAll('button')].find(b => /New Payment Type/i.test(b.textContent))`,
    blacklist: `[...document.querySelectorAll('button')].find(b => /Blacklist a Number/i.test(b.textContent))`,
    storeCurrency: `(() => { const s = document.querySelector('select[name="currency_id"]'); return s; })()`,
};

async function listPageTargets() {
    const list = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
    return list.filter(t => t.type === 'page' && t.webSocketDebuggerUrl);
}

/**
 * Pick a usable page target: prefer our own about:blank tab and never attach to
 * an internal devtools document (which cannot read localStorage). If a target
 * turns out to be unusable (wrong origin), fall through to the next one.
 */
async function attachUsableTarget() {
    let targets = [];
    for (let i = 0; i < 80; i++) {
        try {
            targets = await listPageTargets();
            if (targets.length) break;
        } catch (e) { /* browser not up yet */ }
        await sleep(300);
    }
    if (!targets.length) throw new Error('no target');

    const usable = [
        ...targets.filter(t => String(t.url).startsWith('about:blank')),
        ...targets.filter(t => !String(t.url).startsWith('about:blank') && !String(t.url).startsWith('devtools://')),
    ];

    let lastErr = null;
    for (const t of usable) {
        const cdp = new Cdp(t.webSocketDebuggerUrl);
        try {
            await cdp.connect();
            await cdp.send('Page.enable');
            await cdp.send('Runtime.enable');
            await cdp.send('Log.enable');
            await cdp.nav(BASE + '/login');
            // The page target may still report about:blank briefly; poll the origin.
            let origin = null;
            for (let i = 0; i < 40; i++) {
                try { origin = await cdp.eval('location.origin'); } catch (e) { origin = null; }
                if (origin === BASE) break;
                await sleep(250);
            }
            if (origin === BASE) return cdp;
            lastErr = new Error('unusable target origin=' + origin);
            try { await cdp.send('Browser.close'); } catch (e) { /* ignore */ }
        } catch (e) {
            lastErr = e;
        }
    }
    throw lastErr || new Error('no usable target');
}

(async () => {
    fs.mkdirSync(USER_DATA, { recursive: true });
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${PORT}`, `--user-data-dir=${USER_DATA}`, '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });

    const report = { checks: [], scenarios: {}, consoleErrors: [], pageErrors: [] };
    let cdp;
    const rec = (name, pass, detail) => report.checks.push({ name, pass: !!pass, detail });

    try {
        cdp = await attachUsableTarget();
        report.targetUrl = await cdp.eval('location.href').catch(() => 'unknown');

        await cdp.nav(BASE + '/login');
        const loginState = await cdp.eval(`(() => {
            const email = document.querySelector('input[name="email"]');
            if (!email) return 'already-authenticated';
            const f = email.closest('form');
            email.value = 'admin.dhaka@corevisys.com';
            document.querySelector('input[name="password"]').value = 'password';
            f.requestSubmit();
            return 'submitted';
        })()`);
        if (loginState === 'submitted') {
            await cdp.wait('Page.loadEventFired', 20000).catch(() => null);
            for (let i = 0; i < 40; i++) { try { if (await cdp.eval('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); }
            await sleep(500);
        }
        report.loginState = loginState;
        await cdp.eval(`localStorage.setItem('darkMode','false')`);

        // Confirm we are authenticated before running scenarios.
        await cdp.nav(BASE + '/settings/languages');
        report.authenticated = await cdp.eval(`location.pathname === '/settings/languages'`);
        if (!report.authenticated) throw new Error('not authenticated: landed on ' + (await cdp.eval('location.pathname')));

        const pages = [
            ['languages', '/settings/languages', 'Activate Language'],
            ['currency', '/settings/currency', 'New Currency'],
            ['units', '/settings/units', 'New Unit'],
            ['tax', '/settings/tax', 'New Tax'],
            ['paymentTypes', '/settings/payment-types', 'New Payment Type'],
            ['blacklist', '/sms/blacklist', 'Blacklist a Number'],
        ];

        for (const [key, url, label] of pages) {
            await cdp.nav(BASE + url);
            const scenario = { url, opened: false, steps: {} };

            const opened = await cdp.eval(`(() => { const b = ${OPENERS[key]} ? ${OPENERS[key]} : null; return !!b; })()`);
            if (!opened) { report.scenarios[key] = { url, skipped: 'trigger button not found' }; rec(`${key}: trigger`, false, 'trigger not found'); continue; }

            await cdp.click(`(${OPENERS[key]})`);
            // Poll: Alpine applies x-show asynchronously and the element must also
            // be laid out before elementsFromPoint() can report the real paint order.
            let st = null;
            for (let attempt = 0; attempt < 24; attempt++) {
                st = await cdp.eval(STATE);
                if (st && st.open === true && st.panelTopMost === true) break;
                await sleep(250);
            }
            scenario.opened = st.open;
            rec(`${key}: dialog opens`, st.open, st.topMostCls || null);
            rec(`${key}: panel is top-most at its centre (not the blur layer)`, st.panelTopMost, `topMost=${st.topMostCls} panelZ=${st.panelZ} pos=${st.panelPosition}`);
            rec(`${key}: panel has no blurred ancestor`, !st.panelHasBlurredAncestor, st.panelHasBlurredAncestor || 'none');
            rec(`${key}: backdrop layer has explicit z-index below the panel`, st.blurLayerZ !== null && st.blurLayerZ !== 'auto' && st.panelAboveBackdrop && st.panelNotInsideBackdrop,
                `blurLayerZ=${st.blurLayerZ} ${st.blurLayerPosition} panelZ=${st.panelZ} above=${st.panelAboveBackdrop} panelOutsideBackdrop=${st.panelNotInsideBackdrop}`);
            rec(`${key}: body scroll locked while open`, st.bodyScrollLocked === true, `overflow-y=${st.bodyOverflowY}`);
            if (!st.panelTopMost) { report.scenarios[key] = { ...scenario, steps: { afterOpen: st } }; continue; }

            if (key === 'languages') { await cdp.shot('verify-light-languages-full'); await cdp.shotPanel('verify-light-languages-panel'); }

            // 1) click INSIDE the panel -> must stay open
            await cdp.clickPoint(st.insidePoint.x, st.insidePoint.y);
            await sleep(600);
            let s2 = await cdp.eval(STATE);
            rec(`${key}: click inside panel keeps it open`, s2.open === true, `open=${s2.open}`);

            // 2) Cancel -> closes
            await cdp.click(`(() => {
                const cands = [...document.querySelectorAll('.fixed.inset-0')].filter(el => getComputedStyle(el).display !== 'none' && getComputedStyle(el).position === 'fixed' && el.getBoundingClientRect().width > 200);
                for (const ov of cands) {
                    const p = [...ov.querySelectorAll('[class*="sm:max-w-"]')].find(e => getComputedStyle(e).display !== 'none');
                    if (!p) continue;
                    const b = [...p.querySelectorAll('button')].find(x => /^\\s*Cancel\\s*$/i.test(x.textContent));
                    if (b) return b;
                }
                return null;
            })()`);
            await sleep(800);
            let s3 = await cdp.eval(STATE);
            rec(`${key}: Cancel closes the dialog`, s3.open === false, `open=${s3.open}`);
            rec(`${key}: scroll lock released after Cancel`, s3.bodyScrollLocked === false, `locked=${s3.bodyScrollLocked}`);

            // 3) Escape -> closes
            await cdp.click(`(${OPENERS[key]})`);
            await sleep(900);
            const escMode = await cdp.esc();
            await sleep(700);
            let s4 = await cdp.eval(STATE);
            rec(`${key}: Escape closes the dialog`, s4.open === false, `open=${s4.open} via=${escMode}`);

            // 4) backdrop click -> closes
            await cdp.click(`(${OPENERS[key]})`);
            await sleep(900);
            const bd = await cdp.eval(`(() => {
                const cands = [...document.querySelectorAll('.fixed.inset-0')].filter(el => getComputedStyle(el).display !== 'none' && getComputedStyle(el).position === 'fixed' && el.getBoundingClientRect().width > 200);
                for (const ov of cands) {
                    const p = [...ov.querySelectorAll('[class*="sm:max-w-"]')].find(e => getComputedStyle(e).display !== 'none');
                    if (p) return { x: 5, y: Math.round(ov.getBoundingClientRect().height / 2) };
                }
                return null;
            })()`);
            if (bd) {
                // Confirm the point really lands on the backdrop (outside the panel).
                const onBackdrop = await cdp.eval(`(() => {
                    const el = document.elementFromPoint(${bd.x}, ${bd.y});
                    return el ? String(el.className).slice(0, 60) : null;
                })()`);
                scenario.backdropHitTarget = onBackdrop;
                await cdp.clickPoint(bd.x, bd.y);
            }
            await sleep(800);
            let s5 = await cdp.eval(STATE);
            rec(`${key}: backdrop click closes the dialog`, s5.open === false, `open=${s5.open}`);

            // 5) page interactive again
            const interactive = await cdp.eval(`(() => {
                const el = document.elementFromPoint(innerWidth/2, innerHeight/2);
                return !!el && !el.className.toString().includes('backdrop-blur');
            })()`);
            rec(`${key}: page interactive after close`, interactive, null);

            scenario.steps = { afterOpen: st, afterCancel: s3, afterEscape: s4, afterBackdrop: s5 };
            report.scenarios[key] = scenario;
        }

        /* ── Languages: Confirm & Activate really activates the language ── */
        await cdp.nav(BASE + '/settings/languages');
        const before = await cdp.eval(`(() => {
            const rows = [...document.querySelectorAll('tbody tr')];
            return rows.map(r => ({ name: r.querySelector('td') ? r.querySelector('td').innerText.trim() : '', active: /Active/.test(r.innerText) }));
        })()`);
        await cdp.click(`(${OPENERS.languages})`);
        await sleep(900);
        const nav = cdp.wait('Page.loadEventFired', 20000).catch(() => null);
        await cdp.click(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('fixed') && el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
            if (!ov) return null;
            return [...ov.querySelectorAll('button')].find(b => /Confirm/i.test(b.textContent)) || null;
        })()`);
        await nav;
        for (let i = 0; i < 40; i++) { try { if (await cdp.eval('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); }
        await sleep(900);
        const after = await cdp.eval(`(() => {
            const banner = document.querySelector('h2');
            const rows = [...document.querySelectorAll('tbody tr')];
            return { banner: banner ? banner.textContent.trim() : null, path: location.pathname,
                     rows: rows.map(r => ({ name: r.querySelector('td') ? r.querySelector('td').innerText.trim() : '', active: /Active/.test(r.innerText) })) };
        })()`);
        const activated = after.banner && /Bangla/i.test(after.banner);
        rec('languages: Confirm & Activate activates the language', activated, `banner=${after.banner}`);
        report.scenarios.activate = { before, after };

        // restore English as the active language via the same UI
        await cdp.click(`(() => { const b = [...document.querySelectorAll('button')].find(x => /^Activate Language$/i.test(x.getAttribute('title')||'')); return b; })()`);
        await sleep(900);
        const nav2 = cdp.wait('Page.loadEventFired', 20000).catch(() => null);
        await cdp.click(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('fixed') && el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
            if (!ov) return null;
            return [...ov.querySelectorAll('button')].find(b => /Confirm/i.test(b.textContent)) || null;
        })()`);
        await nav2;
        for (let i = 0; i < 40; i++) { try { if (await cdp.eval('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); }
        await sleep(800);
        const restored = await cdp.eval(`(document.querySelector('h2')||{}).textContent || ''`);
        rec('languages: restored English as active', /English/i.test(restored), `banner=${restored}`);

        /* ── Dark mode ── */
        await cdp.eval(`localStorage.setItem('darkMode','true')`);
        await cdp.nav(BASE + '/settings/languages');
        await cdp.click(`(${OPENERS.languages})`);
        let dark = null;
        for (let attempt = 0; attempt < 24; attempt++) {
            dark = await cdp.eval(STATE);
            if (dark && dark.open === true && dark.panelTopMost === true) break;
            await sleep(250);
        }
        rec('dark mode: panel is top-most (languages)', dark.panelTopMost, `topMost=${dark.topMostCls}`);
        rec('dark mode: panel has no blurred ancestor', !dark.panelHasBlurredAncestor, dark.panelHasBlurredAncestor || 'none');
        await cdp.shot('verify-dark-languages-full');
        await cdp.shotPanel('verify-dark-languages-panel');
        await cdp.esc();
        await sleep(600);
        const darkClosed = await cdp.eval(STATE);
        rec('dark mode: Escape closes', darkClosed.open === false, `open=${darkClosed.open}`);
        await cdp.eval(`localStorage.setItem('darkMode','false')`);

        /* ── Regression: shared x-modal component (items + users delete dialogs) ── */
        for (const [key, url, name] of [['itemsDelete', '/items/list', 'confirm-delete-item'], ['usersDelete', '/users/list', 'confirm-delete-user']]) {
            await cdp.nav(BASE + url);
            const opened = await cdp.eval(`(() => {
                const host = document.querySelector('[x-data*="focusables"]');
                if (!host) return 'component-not-found';
                host.__x ? null : null;
                window.dispatchEvent(new CustomEvent('open-modal', { detail: '${name}' }));
                return 'dispatched';
            })()`);
            let st = null;
            for (let attempt = 0; attempt < 24; attempt++) {
                st = await cdp.eval(STATE);
                if (st && st.open === true && st.panelTopMost === true) break;
                await sleep(250);
            }
            rec(`x-modal ${key}: opens`, st.open, opened);
            rec(`x-modal ${key}: panel is top-most (dialog not washed out)`, st.panelTopMost, `topMost=${st.topMostCls} panelZ=${st.panelZ} blurLayerZ=${st.blurLayerZ}`);
            rec(`x-modal ${key}: backdrop z-index explicit and below panel`, st.panelAboveBackdrop, `panelZ=${st.panelZ} blurLayerZ=${st.blurLayerZ}`);
            await cdp.eval(`window.dispatchEvent(new CustomEvent('close-modal', { detail: '${name}' }))`);
            await sleep(600);
            const st2 = await cdp.eval(STATE);
            rec(`x-modal ${key}: closes`, st2.open === false, null);
        }

        report.consoleErrors = cdp.consoleErrors;
        report.pageErrors = cdp.pageErrors;
        rec('zero console errors', cdp.consoleErrors.length === 0 && cdp.pageErrors.length === 0, JSON.stringify(cdp.consoleErrors.slice(0, 5)));
    } catch (e) {
        report.fatal = String((e && e.stack) || e);
    } finally {
        try { if (cdp) await cdp.send('Browser.close'); } catch (e) { }
        edge.kill();
        const pass = report.checks.filter(c => c.pass).length;
        const fail = report.checks.filter(c => !c.pass);
        report.summary = { total: report.checks.length, pass, fail: fail.length, failures: fail.map(f => f.name + ' :: ' + (f.detail || '')) };
        fs.writeFileSync(path.join(OUT_DIR, 'verify-report.json'), JSON.stringify(report, null, 2));
        console.log('SUMMARY', JSON.stringify(report.summary, null, 2));
        if (report.fatal) console.log('FATAL', report.fatal);
    }
})();
