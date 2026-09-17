/**
 * Focused stacking-context comparison:
 *   Languages "Activate" confirm modal  vs  Currency "Add" modal
 * Both use the *identical* "fixed overlay + absolute blurred backdrop sibling
 * + static inline-block panel" markup, yet only Languages is un-clickable.
 *
 * Prints, for the opened modal on each page:
 *   - the overlay's direct children in DOM order with their stacking info
 *   - document.elementsFromPoint() paint stack at the panel's centre
 *   - the same stack after forcing the blurred backdrop to display:none
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = Number(process.env.CDP_PORT || 9334);
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8123';
const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile-stack');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class Cdp {
    constructor(url) { this.url = url; this.seq = 0; this.pending = new Map(); this.ev = new Map(); this.consoleMessages = []; this.pageErrors = []; }
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
                if (msg.method === 'Runtime.consoleAPICalled') this.consoleMessages.push((msg.params.args || []).map(a => a.value ?? a.description ?? a.type).join(' '));
                if (msg.method === 'Runtime.exceptionThrown') this.pageErrors.push((msg.params.exceptionDetails || {}).text);
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
            setTimeout(() => { if (this.pending.has(id)) { this.pending.delete(id); rej(new Error('timeout ' + method)); } }, 25000);
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
        if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 600));
        return r.result.value;
    }
    async nav(url) { const l = this.wait('Page.loadEventFired').catch(() => null); await this.send('Page.navigate', { url }); await l; for (let i = 0; i < 60; i++) { try { if (await this.eval('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); } await sleep(400); }
}

const SNAPSHOT = `(() => {
    const ov = [...document.body.children].find(el => el.classList.contains('inset-0') && el.classList.contains('fixed') && getComputedStyle(el).display !== 'none');
    if (!ov) return { found: false };

    const info = (el) => {
        if (!el) return null;
        const cs = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        return {
            tag: el.tagName,
            cls: String(el.className),
            zIndex: cs.zIndex, position: cs.position, opacity: cs.opacity,
            filter: cs.filter, backdropFilter: cs.backdropFilter || cs.webkitBackdropFilter,
            transform: cs.transform, translate: cs.translate, scale: cs.scale,
            willChange: cs.willChange, isolation: cs.isolation, contain: cs.contain,
            mixBlendMode: cs.mixBlendMode,
            pointerEvents: cs.pointerEvents,
            rect: [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)],
            inlineStyle: el.getAttribute('style'),
        };
    };

    const children = [...ov.children].map(info);

    // Walk to find the real dialog panel: the inline-block card with a form/buttons.
    const panel = [...ov.querySelectorAll('div')].find(d => {
        const cs = getComputedStyle(d);
        return cs.display === 'inline-block' && /shadow-modal|card/.test(String(d.className)) && d.getBoundingClientRect().height > 40;
    });
    const pr = panel ? panel.getBoundingClientRect() : null;

    const stackNow = pr ? document.elementsFromPoint(Math.round(pr.x + pr.width / 2), Math.round(pr.y + pr.height / 2))
        .map(e => e.tagName + '.' + String(e.className).slice(0, 70)) : [];

    // Temporarily remove the blur layer and re-test the paint stack.
    const blurLayer = [...ov.querySelectorAll('div')].find(d => {
        const cs = getComputedStyle(d);
        return (cs.backdropFilter && cs.backdropFilter !== 'none') || (cs.webkitBackdropFilter && cs.webkitBackdropFilter !== 'none');
    });
    let stackNoBlur = [], blurLayerCls = null, blurParentCls = null;
    if (blurLayer && pr) {
        blurLayerCls = String(blurLayer.className);
        blurParentCls = String(blurLayer.parentElement.className);
        const prev = blurLayer.style.display;
        blurLayer.style.display = 'none';
        stackNoBlur = document.elementsFromPoint(Math.round(pr.x + pr.width / 2), Math.round(pr.y + pr.height / 2))
            .map(e => e.tagName + '.' + String(e.className).slice(0, 70));
        blurLayer.style.display = prev;
    }
    // Also hide only the blur *property* (keep layout) to isolate the cause.
    let stackNoBlurProp = [];
    if (blurLayer && pr) {
        const prev = blurLayer.style.backdropFilter;
        blurLayer.style.backdropFilter = 'none';
        stackNoBlurProp = document.elementsFromPoint(Math.round(pr.x + pr.width / 2), Math.round(pr.y + pr.height / 2))
            .map(e => e.tagName + '.' + String(e.className).slice(0, 70));
        blurLayer.style.backdropFilter = prev;
    }

    return {
        found: true,
        overlayCls: String(ov.className),
        children,
        panel: info(panel),
        blurLayerCls,
        blurParentCls,
        stackNow,
        stackNoBlur,
        stackNoBlurProp,
    };
})()`;

async function getTarget() {
    for (let i = 0; i < 80; i++) {
        try {
            const list = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
            const p = list.find(t => t.type === 'page' && t.webSocketDebuggerUrl);
            if (p) return p;
        } catch (e) { }
        await sleep(300);
    }
    throw new Error('no page target');
}

(async () => {
    fs.mkdirSync(USER_DATA, { recursive: true });
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${PORT}`, `--user-data-dir=${USER_DATA}`, '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });
    const out = {};
    let cdp;
    try {
        const t = await getTarget();
        cdp = new Cdp(t.webSocketDebuggerUrl);
        await cdp.connect();
        await cdp.send('Page.enable'); await cdp.send('Runtime.enable');

        await cdp.nav(BASE + '/login');
        await cdp.eval(`(() => {
            const f = document.querySelector('form[action*="login"]') || document.querySelector('form');
            document.querySelector('input[name="email"]').value = 'admin.dhaka@corevisys.com';
            document.querySelector('input[name="password"]').value = 'password';
            f.requestSubmit();
        })()`);
        await cdp.wait('Page.loadEventFired', 20000).catch(() => null);
        await cdp.eval(`localStorage.setItem('darkMode','false')`);

        /* Languages confirm modal */
        await cdp.nav(BASE + '/settings/languages');
        await cdp.eval(`[...document.querySelectorAll('button')].find(b => /^Activate Language$/i.test(b.title)).click()`);
        await sleep(1200);
        out.languages = await cdp.eval(SNAPSHOT);

        /* Currency add modal */
        await cdp.nav(BASE + '/settings/currency');
        await cdp.eval(`[...document.querySelectorAll('button')].find(b => /Add Currency/i.test(b.textContent)).click()`);
        await sleep(1200);
        out.currency = await cdp.eval(SNAPSHOT);

        /* Units add modal — find its trigger by any "add" button */
        await cdp.nav(BASE + '/settings/units');
        out.unitsTrigger = await cdp.eval(`[...document.querySelectorAll('button')].map(b => b.textContent.trim()).filter(t => t && t.length < 40).slice(0, 20)`);
        await cdp.eval(`(() => { const b = [...document.querySelectorAll('button')].find(x => /add|new/i.test(x.textContent) && x.className.includes('btn-primary')); if (b) b.click(); return !!b; })()`);
        await sleep(1200);
        out.units = await cdp.eval(SNAPSHOT);

        out.pageErrors = cdp.pageErrors;
        out.console = cdp.consoleMessages.slice(0, 20);
    } catch (e) {
        out.error = String(e && e.stack || e);
    } finally {
        try { if (cdp) await cdp.send('Browser.close'); } catch (e) { }
        edge.kill();
        const f = path.join(OUT_DIR, 'stack-report.json');
        fs.writeFileSync(f, JSON.stringify(out, null, 2));
        console.log('written', f);
        console.log(JSON.stringify(out, null, 2).slice(0, 12000));
    }
})();
