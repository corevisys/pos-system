/** Focus + a11y check for the Languages Activate dialog (and the shared x-modal). */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = 9350;
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8123';
const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile-focus');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let ws, seq = 0;
const pending = new Map();
const evs = new Map();
const on = (m, f) => { if (!evs.has(m)) evs.set(m, new Set()); evs.get(m).add(f); };
const off = (m, f) => { const s = evs.get(m); if (s) s.delete(f); };
const wait = (m, t = 20000) => new Promise((res, rej) => { const to = setTimeout(() => { off(m, h); rej(new Error('timeout ' + m)); }, t); const h = (p) => { clearTimeout(to); off(m, h); res(p); }; on(m, h); });
const send = (method, params = {}) => new Promise((res, rej) => { const id = ++seq; pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params })); setTimeout(() => { if (pending.has(id)) { pending.delete(id); rej(new Error('timeout ' + method)); } }, 15000); });
const evaluate = async (expr) => { const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, userGesture: true }); if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 300)); return r.result.value; };
const nav = async (url) => { const l = wait('Page.loadEventFired').catch(() => null); await send('Page.navigate', { url }); await l; for (let i = 0; i < 60; i++) { try { if (await evaluate('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); } await sleep(700); };

(async () => {
    fs.mkdirSync(USER_DATA, { recursive: true });
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${PORT}`, `--user-data-dir=${USER_DATA}`, '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });
    const out = {};
    try {
        let target;
        for (let i = 0; i < 80; i++) { try { const l = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json(); target = l.find(t => t.type === 'page' && t.webSocketDebuggerUrl && !String(t.url).startsWith('devtools')); if (target) break; } catch (e) { } await sleep(300); }
        ws = new WebSocket(target.webSocketDebuggerUrl);
        ws.addEventListener('message', (m) => { const msg = JSON.parse(m.data); if (msg.id && pending.has(msg.id)) { const { res, rej } = pending.get(msg.id); pending.delete(msg.id); msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result); return; } const s = evs.get(msg.method); if (s) [...s].forEach(f => f(msg.params)); });
        await new Promise((r) => ws.addEventListener('open', r));
        await send('Page.enable'); await send('Runtime.enable');

        await nav(BASE + '/login');
        const st = await evaluate(`(() => { const e = document.querySelector('input[name="email"]'); if (!e) return 'auth'; e.value='admin.dhaka@corevisys.com'; document.querySelector('input[name="password"]').value='password'; e.closest('form').requestSubmit(); return 'submitted'; })()`);
        if (st === 'submitted') { await wait('Page.loadEventFired', 20000).catch(() => null); await sleep(1200); }

        await nav(BASE + '/settings/languages');
        out.beforeOpenFocus = await evaluate('document.activeElement ? document.activeElement.tagName : null');
        await evaluate(`[...document.querySelectorAll('button')].find(b => /^Activate Language$/i.test(b.getAttribute('title')||'')).click()`);
        await sleep(1200);
        out.afterOpen = await evaluate(`(() => {
            const a = document.activeElement;
            const panel = document.getElementById('activate-language-panel');
            return {
                activeTag: a ? a.tagName : null,
                activeId: a ? a.id : null,
                activeIsPanel: !!(a && panel && a === panel),
                panelExists: !!panel,
                panelTabIndex: panel ? panel.getAttribute('tabindex') : null,
                focusInsidePanel: !!(a && panel && panel.contains(a)),
            };
        })()`);

        // Escape -> focus / interactivity restored
        await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
        await sleep(900);
        out.afterEscape = await evaluate(`(() => {
            const panel = document.getElementById('activate-language-panel');
            const vis = panel && getComputedStyle(panel).display !== 'none' && panel.getBoundingClientRect().height > 10;
            return {
                panelVisible: !!vis,
                bodyLocked: document.body.classList.contains('overflow-y-hidden'),
                canClickAddLanguage: !!document.elementFromPoint(innerWidth/2, innerHeight/2),
            };
        })()`);
        out.ok = true;
    } catch (e) {
        out.error = String(e && e.stack || e);
    } finally {
        try { await send('Browser.close'); } catch (e) { }
        edge.kill();
        fs.writeFileSync(path.join(OUT_DIR, 'focus-report.json'), JSON.stringify(out, null, 2));
        console.log(JSON.stringify(out, null, 2));
    }
})();
