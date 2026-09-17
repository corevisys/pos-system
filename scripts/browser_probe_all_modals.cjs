/**
 * Decisive stacking test across every dialog that uses the
 * "fixed overlay + absolutely-positioned blurred backdrop sibling +
 *  display:inline-block static panel" pattern.
 *
 * For each opened dialog it reports:
 *   - the element returned by document.elementsFromPoint() at the panel's
 *     centre (i.e. what actually receives a click there)
 *   - whether that top-most element is the blurred backdrop
 *   - the same measurement after display:none-ing ONLY the blur layer
 *   - a PNG screenshot so the washed-out viewport can be seen directly.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = Number(process.env.CDP_PORT || 9335);
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8123';
const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile-all');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class Cdp {
    constructor(url) { this.url = url; this.seq = 0; this.pending = new Map(); this.ev = new Map(); this.pageErrors = []; this.consoleMessages = []; }
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
                if (msg.method === 'Log.entryAdded' && msg.params.entry.level === 'error') this.pageErrors.push(msg.params.entry.text);
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
        if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 500));
        return r.result.value;
    }
    async nav(url) { const l = this.wait('Page.loadEventFired').catch(() => null); await this.send('Page.navigate', { url }); await l; for (let i = 0; i < 60; i++) { try { if (await this.eval('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); } await sleep(500); }
    async shot(name) {
        const r = await this.send('Page.captureScreenshot', { format: 'png' });
        const f = path.join(OUT_DIR, name + '.png');
        fs.writeFileSync(f, Buffer.from(r.data, 'base64'));
        return f;
    }
}

/**
 * Measure the currently-open dialog.
 * Panel = the visible element whose class contains "sm:max-w-" (the dialog card).
 */
const MEASURE = `(() => {
  const vis = (el) => el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 20;

  const overlays = [...document.body.children].filter(el => vis(el) && el.classList.contains('fixed') && el.classList.contains('inset-0'));
  if (!overlays.length) return { open: false };

  const out = { open: true, dialogs: [] };

  for (const ov of overlays) {
    const all = [ov, ...ov.querySelectorAll('*')];
    const panels = all.filter(el => {
      if (!vis(el)) return false;
      const c = String(el.className || '');
      return /sm:max-w-/.test(c) && /rounded/.test(c);
    });
    if (!panels.length) { out.dialogs.push({ overlayCls: String(ov.className), panel: 'NONE' }); continue; }

    const panel = panels[0];
    const pr = panel.getBoundingClientRect();
    const px = Math.round(pr.left + pr.width / 2);
    const py = Math.round(pr.top + pr.height / 2);

    const blurLayer = all.find(el => {
      const cs = getComputedStyle(el);
      const bf = cs.backdropFilter || cs.webkitBackdropFilter;
      return bf && bf !== 'none';
    });

    const stack = () => document.elementsFromPoint(px, py).map(e => e.tagName + '.' + String(e.className).slice(0, 60));
    const before = stack();
    let afterHide = null;
    if (blurLayer) {
      const prev = blurLayer.style.display;
      blurLayer.style.display = 'none';
      afterHide = stack();
      blurLayer.style.display = prev;
    }
    const csPanel = getComputedStyle(panel);
    out.dialogs.push({
      overlayCls: String(ov.className),
      panelCls: String(panel.className).slice(0, 140),
      panelDisplay: csPanel.display,
      panelPosition: csPanel.position,
      panelZ: csPanel.zIndex,
      blurLayerCls: blurLayer ? String(blurLayer.className) : null,
      blurLayerPosition: blurLayer ? getComputedStyle(blurLayer).position : null,
      topMostAtPanelCentre: before[0],
      panelIsClickTarget: before[0] === panel,
      topMostIsBlurLayer: !!(blurLayer && before[0] === blurLayer),
      stack: before.slice(0, 6),
      stackAfterHidingBlurLayer: afterHide ? afterHide.slice(0, 4) : null,
      panelRect: [px, py, Math.round(pr.width), Math.round(pr.height)],
    });
  }
  return out;
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
    throw new Error('no target');
}

const CLOSE_ALL = `(async () => {
  for (let i = 0; i < 6; i++) {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    await new Promise(r => setTimeout(r, 120));
  }
  return true;
})()`;

(async () => {
    fs.mkdirSync(USER_DATA, { recursive: true });
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${PORT}`, `--user-data-dir=${USER_DATA}`, '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });
    const out = { results: [] };
    let cdp;
    try {
        const t = await getTarget();
        cdp = new Cdp(t.webSocketDebuggerUrl);
        await cdp.connect();
        await cdp.send('Page.enable'); await cdp.send('Runtime.enable'); await cdp.send('Log.enable');

        await cdp.nav(BASE + '/login');
        await cdp.eval(`(() => {
            const f = document.querySelector('form[action*="login"]') || document.querySelector('form');
            document.querySelector('input[name="email"]').value = 'admin.dhaka@corevisys.com';
            document.querySelector('input[name="password"]').value = 'password';
            f.requestSubmit();
        })()`);
        await cdp.wait('Page.loadEventFired', 20000).catch(() => null);
        await cdp.eval(`localStorage.setItem('darkMode','false')`);

        const pages = [
            { key: 'languages', url: BASE + '/settings/languages', triggers: ['Activate Language'] },
            { key: 'currency', url: BASE + '/settings/currency', triggers: ['Activate Currency', 'Add Currency'] },
            { key: 'units', url: BASE + '/settings/units', triggers: ['New Unit', 'Add Unit'] },
            { key: 'tax', url: BASE + '/settings/tax', triggers: ['Add Tax', 'New Tax', 'Add Individual'] },
        ];

        for (const p of pages) {
            await cdp.nav(p.url);
            let i = 0;
            for (const label of p.triggers) {
                const clicked = await cdp.eval(`(() => {
                    const b = [...document.querySelectorAll('button')].find(x => {
                        const t = (x.textContent || '').trim();
                        const ti = x.getAttribute('title') || '';
                        return t.includes(${JSON.stringify(label)}) || ti.includes(${JSON.stringify(label)});
                    });
                    if (!b) return false;
                    b.click();
                    return true;
                })()`);
                if (!clicked) { out.results.push({ page: p.key, label, clicked: false }); continue; }
                await sleep(1100);
                const measured = await cdp.eval(MEASURE);
                const shot = await cdp.shot(`modal-${p.key}-${i}`);
                out.results.push({ page: p.key, label, clicked: true, screenshot: path.basename(shot), ...measured });
                await cdp.eval(CLOSE_ALL);
                await sleep(500);
                i++;
            }
        }

        out.pageErrors = cdp.pageErrors;
        out.consoleErrorCount = 0;
    } catch (e) {
        out.error = String((e && e.stack) || e);
    } finally {
        try { if (cdp) await cdp.send('Browser.close'); } catch (e) { }
        edge.kill();
        fs.writeFileSync(path.join(OUT_DIR, 'all-modals-report.json'), JSON.stringify(out, null, 2));
        console.log(JSON.stringify(out, null, 2));
    }
})();
