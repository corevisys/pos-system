/** Focused debug: why does the SMS auto-rules dialog not register as open? */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = 9338;
const BASE = 'http://127.0.0.1:8123';
const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile-debug');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let seq = 0; const pending = new Map(); let ws;
const evs = new Map();
const send = (method, params = {}) => new Promise((res, rej) => { const id = ++seq; pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params })); setTimeout(() => { if (pending.has(id)) { pending.delete(id); rej(new Error('timeout ' + method)); } }, 20000); });
const on = (m, f) => { if (!evs.has(m)) evs.set(m, new Set()); evs.get(m).add(f); };
const off = (m, f) => { const s = evs.get(m); if (s) s.delete(f); };
const wait = (m, t = 20000) => new Promise((res, rej) => { const to = setTimeout(() => { off(m, h); rej(new Error('timeout ' + m)); }, t); const h = (p) => { clearTimeout(to); off(m, h); res(p); }; on(m, h); });
const evaluate = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true, userGesture: true });
    if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 400));
    return r.result.value;
};
const nav = async (url) => { const l = wait('Page.loadEventFired').catch(() => null); await send('Page.navigate', { url }); await l; for (let i = 0; i < 60; i++) { try { if (await evaluate('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); } await sleep(500); };

(async () => {
    fs.mkdirSync(USER_DATA, { recursive: true });
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${PORT}`, `--user-data-dir=${USER_DATA}`, '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });
    let target;
    for (let i = 0; i < 80; i++) {
        try { const l = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json(); target = l.find(t => t.type === 'page' && t.webSocketDebuggerUrl); if (target) break; } catch (e) { }
        await sleep(300);
    }
    ws = new WebSocket(target.webSocketDebuggerUrl);
    ws.addEventListener('message', (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && pending.has(msg.id)) { const { res, rej } = pending.get(msg.id); pending.delete(msg.id); msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result); return; }
        const s = evs.get(msg.method); if (s) [...s].forEach(f => f(msg.params));
    });
    await new Promise((r) => ws.addEventListener('open', r));
    await send('Page.enable'); await send('Runtime.enable');

    const out = {};
    try {
        await nav(BASE + '/login');
        const st = await evaluate(`(() => { const e = document.querySelector('input[name="email"]'); if (!e) return 'auth'; e.value='admin.dhaka@corevisys.com'; document.querySelector('input[name="password"]').value='password'; e.closest('form').requestSubmit(); return 'submitted'; })()`);
        if (st === 'submitted') { await wait('Page.loadEventFired', 20000).catch(() => null); await sleep(1200); }

        await nav(BASE + '/sms/auto-rules');
        out.beforeClick = await evaluate(`(() => ({
            hasRoot: !!document.querySelector('[x-data]'),
            triggerFound: !!([...document.querySelectorAll('button')].find(b => /Deploy Rule/i.test(b.textContent))),
            fixedInsetCount: document.querySelectorAll('.fixed.inset-0').length,
            bodyLocked: document.body.classList.contains('overflow-y-hidden'),
        }))()`);

        await evaluate(`[...document.querySelectorAll('button')].find(b => /Deploy Rule/i.test(b.textContent)).click()`);
        await sleep(1400);

        // Read the Alpine component state directly (Alpine v3 keeps it on
        // _x_dataStack for the element that declared x-data).
        const readState = `(() => {
            const host = [...document.querySelectorAll('[x-data]')].find(el => /isModalOpen/.test(el.getAttribute('x-data') || ''));
            if (!host) return { error: 'host-not-found' };
            const stack = host._x_dataStack || [];
            const data = stack[stack.length - 1] || {};
            return { isModalOpen: data.isModalOpen, hasEscapeHandler: !!host.__escapeAttached };
        })()`;
        out.beforeEscapeState = await evaluate(readState);

        // Escape via CDP, then poll for closure.
        out.escape = {};
        try {
            await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
            out.escape.dispatch = 'cdp';
        } catch (e) {
            await evaluate(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true }))`);
            out.escape.dispatch = 'synthetic:' + String(e).slice(0, 80);
        }
        for (let i = 0; i < 12; i++) {
            await sleep(250);
            const closed = await evaluate(`(() => {
                const vis = (el) => el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 40;
                return ![...document.querySelectorAll('div')].some(el => vis(el) && /max-w-2xl/.test(String(el.className || '')));
            })()`);
            if (closed) { out.escape.closedAfterMs = (i + 1) * 250; break; }
        }
        out.escape.stillOpen = await evaluate(`(() => {
            const vis = (el) => el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 40;
            return [...document.querySelectorAll('div')].some(el => vis(el) && /max-w-2xl/.test(String(el.className || '')));
        })()`);
        out.escape.bodyStillLocked = await evaluate(`document.body.classList.contains('overflow-y-hidden')`);
        out.escape.stateAfter = await evaluate(readState);
        out.escape.overlayDisplay = await evaluate(`(() => {
            const el = [...document.querySelectorAll('div')].find(d => /items-center justify-center p-4 sm:p-6 text-left/.test(String(d.className)));
            if (!el) return 'overlay-not-found';
            return getComputedStyle(el).display + ' | inline:' + (el.getAttribute('style') || '');
        })()`);

        out.afterClick = await evaluate(`(() => {
            const all = [...document.querySelectorAll('div')].filter(d => String(d.className).includes('inset-0'));
            return {
                bodyLocked: document.body.classList.contains('overflow-y-hidden'),
                divsWithInset: all.map(d => ({
                    cls: String(d.className).slice(0, 90),
                    display: getComputedStyle(d).display,
                    pos: getComputedStyle(d).position,
                    z: getComputedStyle(d).zIndex,
                    w: Math.round(d.getBoundingClientRect().width),
                    h: Math.round(d.getBoundingClientRect().height),
                })).slice(0, 8),
                panelCandidates: [...document.querySelectorAll('div')].filter(d => /max-w-2xl/.test(String(d.className))).map(d => ({
                    cls: String(d.className).slice(0, 100),
                    display: getComputedStyle(d).display,
                    h: Math.round(d.getBoundingClientRect().height),
                    z: getComputedStyle(d).zIndex,
                    pos: getComputedStyle(d).position,
                })).slice(0, 5),
                xcloakCount: document.querySelectorAll('[x-cloak]').length,
            };
        })()`);
    } catch (e) {
        out.error = String(e);
    } finally {
        try { await send('Browser.close'); } catch (e) { }
        edge.kill();
        fs.writeFileSync(path.join(OUT_DIR, 'debug-autorules.json'), JSON.stringify(out, null, 2));
        console.log(JSON.stringify(out, null, 2));
    }
})();
