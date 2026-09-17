/**
 * Real-browser reproduction + verification probe for the Languages
 * "Activate" confirmation modal bug.
 *
 * Drives Microsoft Edge over the Chrome DevTools Protocol (no npm deps:
 * uses Node >= 20 native fetch + WebSocket).
 *
 * Usage:
 *   node scripts/browser_probe_languages_modal.cjs
 *
 * Env overrides:
 *   EDGE_PATH, CDP_PORT, APP_BASE, PROBE_USER, PROBE_PASS
 *
 * Writes storage/app/browser-check/probe-report.json and prints a summary.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = process.env.EDGE_PATH || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = Number(process.env.CDP_PORT || 9333);
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8123';
const USER = process.env.PROBE_USER || 'admin.dhaka@corevisys.com';
const PASS = process.env.PROBE_PASS || 'password';
const LANG_URL = BASE + '/settings/languages';

const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile');
const REPORT = path.join(OUT_DIR, 'probe-report.json');

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/* ───────────────────────────── CDP transport ───────────────────────────── */

class Cdp {
    constructor(url) {
        this.url = url;
        this.seq = 0;
        this.pending = new Map();
        this.listeners = new Map();
        this.consoleMessages = [];
        this.pageErrors = [];
    }

    connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.url);
            this.ws.addEventListener('open', () => resolve());
            this.ws.addEventListener('error', () => reject(new Error('websocket error')));
            this.ws.addEventListener('message', (ev) => {
                const msg = JSON.parse(ev.data);
                if (msg.id && this.pending.has(msg.id)) {
                    const { res, rej } = this.pending.get(msg.id);
                    this.pending.delete(msg.id);
                    msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result);
                    return;
                }
                if (!msg.method) return;
                this.collect(msg.method, msg.params);
                const set = this.listeners.get(msg.method);
                if (set) [...set].forEach((fn) => fn(msg.params));
            });
        });
    }

    collect(method, params) {
        if (method === 'Runtime.consoleAPICalled') {
            this.consoleMessages.push({
                level: params.type,
                text: (params.args || [])
                    .map((a) => (a.value !== undefined ? String(a.value) : a.description || a.type))
                    .join(' '),
            });
        } else if (method === 'Runtime.exceptionThrown') {
            const d = params.exceptionDetails || {};
            this.pageErrors.push({
                text: d.text,
                description: (d.exception && d.exception.description) || '',
                url: d.url,
                line: d.lineNumber,
            });
        } else if (method === 'Log.entryAdded') {
            const e = params.entry || {};
            if (e.level === 'error') {
                this.pageErrors.push({ text: e.text, url: e.url, source: e.source });
            } else {
                this.consoleMessages.push({ level: e.level, text: e.text });
            }
        }
    }

    on(method, fn) {
        if (!this.listeners.has(method)) this.listeners.set(method, new Set());
        this.listeners.get(method).add(fn);
    }

    off(method, fn) {
        const set = this.listeners.get(method);
        if (set) set.delete(fn);
    }

    send(method, params = {}) {
        const id = ++this.seq;
        return new Promise((res, rej) => {
            this.pending.set(id, { res, rej });
            this.ws.send(JSON.stringify({ id, method, params }));
            setTimeout(() => {
                if (this.pending.has(id)) {
                    this.pending.delete(id);
                    rej(new Error(`CDP timeout: ${method}`));
                }
            }, 30000);
        });
    }

    waitEvent(method, timeout = 20000) {
        return new Promise((res, rej) => {
            const t = setTimeout(() => {
                this.off(method, h);
                rej(new Error(`timeout waiting for ${method}`));
            }, timeout);
            const h = (p) => {
                clearTimeout(t);
                this.off(method, h);
                res(p);
            };
            this.on(method, h);
        });
    }

    async evaluate(expr) {
        const r = await this.send('Runtime.evaluate', {
            expression: expr,
            returnByValue: true,
            awaitPromise: true,
            userGesture: true,
        });
        if (r.exceptionDetails) {
            throw new Error('evaluate failed: ' + JSON.stringify(r.exceptionDetails).slice(0, 800));
        }
        return r.result.value;
    }

    async navigate(url) {
        const loaded = this.waitEvent('Page.loadEventFired', 25000).catch(() => null);
        await this.send('Page.navigate', { url });
        await loaded;
        await this.readyState();
        await sleep(400);
    }

    async readyState() {
        for (let i = 0; i < 80; i++) {
            try {
                const s = await this.evaluate('document.readyState');
                if (s === 'complete') return true;
            } catch (e) {
                /* navigating */
            }
            await sleep(150);
        }
        return false;
    }

    async clickAt(x, y) {
        const base = { x: Math.round(x), y: Math.round(y), button: 'left', clickCount: 1 };
        await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...base });
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...base });
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...base });
    }

    async pressEscape() {
        const key = { key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 };
        await this.send('Input.dispatchKeyEvent', { type: 'rawKeyDown', ...key });
        await this.send('Input.dispatchKeyEvent', { type: 'keyUp', ...key });
    }
}

/* ─────────────────────── in-page diagnostic collectors ─────────────────── */

const OPEN_MODAL = `(() => {
    const btn = [...document.querySelectorAll('button[title]')]
        .find(b => /^Activate Language$/i.test(b.title))
        || [...document.querySelectorAll('button')].find(b => /Set Active/i.test(b.textContent));
    if (!btn) return { opened: false, reason: 'activate button not found' };
    btn.click();
    return { opened: true, title: btn.title, text: btn.textContent.trim() };
})()`;

const DIAGNOSTICS = `(() => {
    const desc = (el, name) => {
        if (!el) return { name, missing: true };
        const cs = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        return {
            name,
            tag: el.tagName,
            cls: String(el.className).slice(0, 220),
            rect: { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) },
            display: cs.display,
            visibility: cs.visibility,
            opacity: cs.opacity,
            position: cs.position,
            zIndex: cs.zIndex,
            filter: cs.filter,
            backdropFilter: cs.backdropFilter || cs.webkitBackdropFilter || 'none',
            transform: cs.transform,
            pointerEvents: cs.pointerEvents,
            overflow: cs.overflow,
            isolation: cs.isolation,
            contain: cs.contain,
            mixBlendMode: cs.mixBlendMode,
        };
    };

    const out = {
        url: location.href,
        viewport: { w: innerWidth, h: innerHeight },
        alpineLoaded: typeof window.Alpine !== 'undefined',
        darkMode: document.documentElement.classList.contains('dark'),
        bodyClass: String(document.body.className),
        bodyOverflowY: getComputedStyle(document.body).overflowY,
        htmlOverflowY: getComputedStyle(document.documentElement).overflowY,
        bodyChildren: [...document.body.children].map(el => el.tagName + '.' + String(el.className).slice(0, 90)),
        activeElement: document.activeElement
            ? document.activeElement.tagName + '|' + String(document.activeElement.className).slice(0, 80)
            : null,
        xCloakRuleApplied: (() => {
            const d = document.createElement('div');
            d.setAttribute('x-cloak', '');
            document.body.appendChild(d);
            const hidden = getComputedStyle(d).display === 'none';
            d.remove();
            return hidden;
        })(),
    };

    const overlays = [...document.body.children].filter(el => {
        const cs = getComputedStyle(el);
        return el.classList && el.classList.contains('fixed') && el.classList.contains('inset-0') && cs.display !== 'none';
    });
    out.visibleFixedOverlaysOnBody = overlays.length;

    const overlay = overlays[overlays.length - 1];
    if (!overlay) {
        out.modal = null;
        return out;
    }

    out.teleportedToBody = overlay.parentElement === document.body;
    out.teleportParentTag = overlay.parentElement.tagName;

    const confirmBtn = [...overlay.querySelectorAll('button')].find(b => /Confirm/i.test(b.textContent));
    const cancelBtn = [...overlay.querySelectorAll('button')].find(b => /^\\s*Cancel\\s*$/i.test(b.textContent));
    const panel = confirmBtn
        ? confirmBtn.closest('div[class*="shadow-modal"], div[class*="card"]')
        : null;
    const backdropWrap = overlay.querySelector(':scope > div > div');
    const backdropInner = backdropWrap ? backdropWrap.firstElementChild : null;

    const chain = [overlay, overlay.firstElementChild, backdropWrap, backdropInner, panel].filter(Boolean);
    out.chain = chain.map((el, i) => desc(el, 'chain[' + i + ']'));
    out.overlay = desc(overlay, 'overlay');
    out.backdropWrap = desc(backdropWrap, 'backdropWrap');
    out.backdropInner = desc(backdropInner, 'backdropInner');
    out.panel = desc(panel, 'panel');
    out.cancelBtn = desc(cancelBtn, 'cancelBtn');
    out.confirmBtn = desc(confirmBtn, 'confirmBtn');

    // Does the PANEL itself, or any of its ancestors, carry filter/backdrop-filter?
    out.blurredPanelAncestors = [];
    let node = panel;
    while (node && node !== document.documentElement) {
        const cs = getComputedStyle(node);
        const f = cs.filter && cs.filter !== 'none';
        const bf = (cs.backdropFilter || cs.webkitBackdropFilter) && (cs.backdropFilter || cs.webkitBackdropFilter) !== 'none';
        if (f || bf) out.blurredPanelAncestors.push({ cls: String(node.className).slice(0, 160), filter: cs.filter, backdropFilter: cs.backdropFilter });
        node = node.parentElement;
    }

    // Clipping ancestors that would break position:fixed if NOT teleported.
    out.clippingAncestorsOfMainContent = [];
    node = document.querySelector('main') || document.body;
    while (node && node !== document.documentElement) {
        const cs = getComputedStyle(node);
        if (/hidden|auto|scroll|clip/.test(cs.overflow + cs.overflowX + cs.overflowY)) {
            out.clippingAncestorsOfMainContent.push(String(node.className).slice(0, 120));
        }
        node = node.parentElement;
    }

    // Hit testing with real geometry (what the browser would actually deliver clicks to).
    const hit = (el, label) => {
        if (!el) return { label, found: false };
        const r = el.getBoundingClientRect();
        const px = Math.round(r.left + r.width / 2);
        const py = Math.round(r.top + r.height / 2);
        const top = document.elementFromPoint(px, py);
        return {
            label,
            point: { x: px, y: py },
            targetTag: top ? top.tagName : null,
            targetCls: top ? String(top.className).slice(0, 120) : null,
            targetText: top ? (top.textContent || '').trim().slice(0, 40) : null,
            insidePanel: !!(panel && top && (panel === top || panel.contains(top))),
            insideBackdrop: !!(backdropInner && top && (backdropInner === top || backdropInner.contains(top))),
        };
    };
    out.hitTest = {
        panelCenter: hit(panel, 'panelCenter'),
        cancelCenter: hit(cancelBtn, 'cancelCenter'),
        confirmCenter: hit(confirmBtn, 'confirmCenter'),
        overlayTopLeft: hit(overlay, 'overlay center'),
    };
    // Backdrop point deliberately outside the panel (top-left corner of viewport).
    const topLeft = document.elementFromPoint(4, 4);
    out.hitTest.backdropOutsidePanel = {
        label: 'backdropOutsidePanel',
        point: { x: 4, y: 4 },
        targetTag: topLeft ? topLeft.tagName : null,
        targetCls: topLeft ? String(topLeft.className).slice(0, 120) : null,
        insidePanel: !!(panel && topLeft && (panel === topLeft || panel.contains(topLeft))),
        insideBackdrop: !!(backdropInner && topLeft && (backdropInner === topLeft || backdropInner.contains(topLeft))),
    };

    // Explicit stacking comparison required by the report.
    out.zi = {
        backdropWrap: out.backdropWrap.zIndex,
        backdropInner: out.backdropInner.zIndex,
        backdropInnerPosition: out.backdropInner.position,
        panel: out.panel.zIndex,
        panelPosition: out.panel.position,
        sidebar: (() => { const s = document.querySelector('aside'); return s ? getComputedStyle(s).zIndex : null; })(),
        header: (() => { const h = document.querySelector('header'); return h ? getComputedStyle(h).zIndex : null; })(),
        footer: (() => { const f = document.querySelector('footer'); return f ? getComputedStyle(f).zIndex : null; })(),
    };
    return out;
})()`;

/* ───────────────────────────────── main ────────────────────────────────── */

async function getPageTarget() {
    for (let i = 0; i < 80; i++) {
        try {
            const res = await fetch(`http://127.0.0.1:${PORT}/json/list`);
            const list = await res.json();
            const page = list.find((t) => t.type === 'page' && t.webSocketDebuggerUrl);
            if (page) return page;
        } catch (e) {
            /* not up yet */
        }
        await sleep(300);
    }
    throw new Error('CDP page target never appeared');
}

async function main() {
    fs.mkdirSync(OUT_DIR, { recursive: true });
    fs.mkdirSync(USER_DATA, { recursive: true });

    const edge = spawn(
        EDGE,
        [
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-features=msEdgeSidebarV2',
            `--remote-debugging-port=${PORT}`,
            `--user-data-dir=${USER_DATA}`,
            '--window-size=1440,900',
            'about:blank',
        ],
        { stdio: 'ignore' }
    );

    const report = { startedAt: new Date().toISOString(), steps: [], console: [], pageErrors: [] };
    let cdp;

    try {
        const target = await getPageTarget();
        cdp = new Cdp(target.webSocketDebuggerUrl);
        await cdp.connect();
        await cdp.send('Page.enable');
        await cdp.send('Runtime.enable');
        await cdp.send('Log.enable');

        /* 1 — login */
        await cdp.navigate(BASE + '/login');
        const loggedIn = cdp.waitEvent('Page.loadEventFired', 25000).catch(() => null);
        await cdp.evaluate(`(() => {
            const f = document.querySelector('form[action*="login"]') || document.querySelector('form');
            if (!f) return 'no form';
            const set = (sel, v) => { const el = document.querySelector(sel); if (el) { el.value = v; el.dispatchEvent(new Event('input', {bubbles:true})); } };
            set('input[name="email"]', ${JSON.stringify(USER)});
            set('input[name="password"]', ${JSON.stringify(PASS)});
            f.requestSubmit ? f.requestSubmit() : f.submit();
            return 'submitted';
        })()`);
        await loggedIn;
        await cdp.readyState();
        await sleep(600);
        report.steps.push({ step: 'login', landedOn: await cdp.evaluate('location.pathname') });

        /* 2 — Languages page (light mode) */
        await cdp.evaluate(`localStorage.setItem('darkMode','false')`);
        await cdp.navigate(LANG_URL);
        report.steps.push({
            step: 'languages-page-light',
            path: await cdp.evaluate('location.pathname'),
            hasActivateBtn: await cdp.evaluate(
                `!![...document.querySelectorAll('button')].find(b => /^Activate Language$/i.test(b.title) || /Set Active/i.test(b.textContent))`
            ),
        });

        /* 3 — open the modal and take diagnostics */
        const openResult = await cdp.evaluate(OPEN_MODAL);
        await sleep(900);
        const diag = await cdp.evaluate(DIAGNOSTICS);
        report.steps.push({ step: 'modal-open-light', openResult, diagnostics: diag });

        /* 4 — Escape closes? */
        await cdp.pressEscape();
        await sleep(600);
        report.steps.push({
            step: 'escape-close',
            overlayStillVisible: await cdp.evaluate(
                `[...document.body.children].some(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none')`
            ),
            bodyOverflowAfter: await cdp.evaluate('getComputedStyle(document.body).overflowY'),
        });

        /* 5 — reopen, click backdrop OUTSIDE panel closes? */
        await cdp.evaluate(OPEN_MODAL);
        await sleep(800);
        const backPt = await cdp.evaluate(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
            const r = ov.getBoundingClientRect();
            return { x: 8, y: Math.round(r.height / 2) };
        })()`);
        await cdp.clickAt(backPt.x, backPt.y);
        await sleep(700);
        report.steps.push({
            step: 'backdrop-click-close',
            clickPoint: backPt,
            overlayStillVisible: await cdp.evaluate(
                `[...document.body.children].some(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none')`
            ),
        });

        /* 6 — reopen, click Cancel closes? */
        await cdp.evaluate(OPEN_MODAL);
        await sleep(800);
        const cancelPt = await cdp.evaluate(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
            const b = [...ov.querySelectorAll('button')].find(x => /^\\s*Cancel\\s*$/i.test(x.textContent));
            if (!b) return null;
            const r = b.getBoundingClientRect();
            return { x: Math.round(r.left + r.width/2), y: Math.round(r.top + r.height/2) };
        })()`);
        report.steps.push({ step: 'cancel-button-located', cancelPt });
        if (cancelPt) {
            await cdp.clickAt(cancelPt.x, cancelPt.y);
            await sleep(700);
        }
        report.steps.push({
            step: 'cancel-click-close',
            overlayStillVisible: await cdp.evaluate(
                `[...document.body.children].some(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none')`
            ),
            bodyOverflowAfter: await cdp.evaluate('getComputedStyle(document.body).overflowY'),
            pageInteractive: await cdp.evaluate(
                `!!document.elementFromPoint(innerWidth/2, innerHeight/2)`
            ),
        });

        /* 7 — click inside the panel body should NOT close it */
        await cdp.evaluate(OPEN_MODAL);
        await sleep(800);
        const insidePt = await cdp.evaluate(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
            const cb = [...ov.querySelectorAll('button')].find(b => /Confirm/i.test(b.textContent));
            const panel = cb.closest('div[class*="shadow-modal"], div[class*="card"]');
            const r = panel.getBoundingClientRect();
            return { x: Math.round(r.left + r.width/2), y: Math.round(r.top + 30) };
        })()`);
        await cdp.clickAt(insidePt.x, insidePt.y);
        await sleep(600);
        report.steps.push({
            step: 'inside-panel-click-keeps-open',
            clickPoint: insidePt,
            overlayStillVisible: await cdp.evaluate(
                `[...document.body.children].some(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none')`
            ),
        });

        /* 8 — confirm & activate actually works */
        const confirmPt = await cdp.evaluate(`(() => {
            const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
            const b = [...ov.querySelectorAll('button')].find(x => /Confirm/i.test(x.textContent));
            if (!b) return null;
            const r = b.getBoundingClientRect();
            return { x: Math.round(r.left + r.width/2), y: Math.round(r.top + r.height/2), formAction: b.form ? b.form.getAttribute('action') : null };
        })()`);
        const navDone = cdp.waitEvent('Page.loadEventFired', 20000).catch(() => null);
        if (confirmPt) await cdp.clickAt(confirmPt.x, confirmPt.y);
        await navDone;
        await cdp.readyState();
        await sleep(900);
        report.steps.push({
            step: 'confirm-activate',
            confirmPt,
            landedOn: await cdp.evaluate('location.pathname'),
            flashText: await cdp.evaluate(`(document.body.innerText.match(/[^\\n]*ctivat[^\\n]*/g) || []).slice(0, 4)`),
        });

        /* 9 — dark mode pass */
        await cdp.evaluate(`localStorage.setItem('darkMode','true')`);
        await cdp.navigate(LANG_URL);
        const darkOpen = await cdp.evaluate(OPEN_MODAL);
        await sleep(900);
        const darkDiag = await cdp.evaluate(DIAGNOSTICS);
        report.steps.push({ step: 'modal-open-dark', openResult: darkOpen, diagnostics: darkDiag });
        const darkEscape = cdp.waitEvent('Page.loadEventFired', 5000).catch(() => null);
        await cdp.pressEscape();
        await sleep(500);
        report.steps.push({
            step: 'dark-escape-close',
            overlayStillVisible: await cdp.evaluate(
                `[...document.body.children].some(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none')`
            ),
        });

        /* 10 — regression: other pages that use the shared modal / same pattern */
        for (const p of [
            ['units', BASE + '/settings/units'],
            ['tax', BASE + '/settings/tax'],
            ['currency', BASE + '/settings/currency'],
        ]) {
            await cdp.navigate(p[1]);
            const info = await cdp.evaluate(`(() => {
                const addBtn = [...document.querySelectorAll('button')].find(b => /Add (Unit|Tax|Currency)/i.test(b.textContent));
                if (!addBtn) return { found: false };
                addBtn.click();
                return { found: true, label: addBtn.textContent.trim() };
            })()`);
            await sleep(900);
            const opened = await cdp.evaluate(`(() => {
                const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && getComputedStyle(el).display !== 'none');
                if (!ov) return { open: false };
                const r = ov.getBoundingClientRect();
                const mid = document.elementFromPoint(Math.round(innerWidth/2), Math.round(innerHeight/2));
                const panelish = document.querySelector('.max-w-lg, .max-w-md, [class*="shadow-modal"]');
                return {
                    open: true,
                    visible: r.width > 0 && r.height > 0,
                    centerInsidePanel: !!(panelish && (panelish === mid || panelish.contains(mid))),
                    centerTarget: mid ? mid.tagName + '.' + String(mid.className).slice(0, 60) : null,
                };
            })()`);
            report.steps.push({ step: 'regression-' + p[0], clicked: info, modal: opened });
            await cdp.pressEscape();
            await sleep(400);
        }

        report.console = cdp.consoleMessages;
        report.pageErrors = cdp.pageErrors;
        report.ok = true;
    } catch (err) {
        report.ok = false;
        report.error = String(err && err.stack ? err.stack : err);
        if (cdp) {
            report.console = cdp.consoleMessages;
            report.pageErrors = cdp.pageErrors;
        }
    } finally {
        if (cdp && cdp.ws) {
            try {
                await cdp.send('Browser.close');
            } catch (e) {
                /* ignore */
            }
        }
        try {
            edge.kill();
        } catch (e) {
            /* ignore */
        }
        report.finishedAt = new Date().toISOString();
        fs.writeFileSync(REPORT, JSON.stringify(report, null, 2));
        console.log('report written to', REPORT);
        console.log('ok:', report.ok, report.error ? '\nerror: ' + report.error : '');
    }
}

main();
