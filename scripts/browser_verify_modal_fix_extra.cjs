/**
 * Supplementary verification for the dialogs NOT covered by
 * browser_verify_modal_fix.cjs:
 *   - SMS logs detail dialog
 *   - SMS history detail dialog
 *   - Store settings currency confirmation dialog
 *   - Store settings language confirmation dialog
 *   - SMS auto-rules ("Deploy Rule")
 *
 * Uses the same simple, reliable pattern as browser_debug_autorules.cjs:
 * one await-free Runtime.evaluate per step plus Node-side polling, so a slow
 * Alpine render can never stall the CDP channel.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = Number(process.env.CDP_PORT || 9342);
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8123';
const OUT_DIR = path.join(__dirname, '..', 'storage', 'app', 'browser-check');
const USER_DATA = path.join(OUT_DIR, 'edge-profile-extra5');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let ws, seq = 0;
const pending = new Map();
const evs = new Map();
const consoleErrors = [];
const pageErrors = [];

const on = (m, f) => { if (!evs.has(m)) evs.set(m, new Set()); evs.get(m).add(f); };
const off = (m, f) => { const s = evs.get(m); if (s) s.delete(f); };
const wait = (m, t = 20000) => new Promise((res, rej) => {
    const to = setTimeout(() => { off(m, h); rej(new Error('timeout wait ' + m)); }, t);
    const h = (p) => { clearTimeout(to); off(m, h); res(p); };
    on(m, h);
});
const send = (method, params = {}) => new Promise((res, rej) => {
    const id = ++seq;
    pending.set(id, { res, rej });
    ws.send(JSON.stringify({ id, method, params }));
    setTimeout(() => { if (pending.has(id)) { pending.delete(id); rej(new Error('timeout ' + method)); } }, 15000);
});
const evaluate = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, userGesture: true });
    if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails).slice(0, 300));
    return r.result.value;
};
const nav = async (url) => {
    const l = wait('Page.loadEventFired').catch(() => null);
    await send('Page.navigate', { url });
    await l;
    for (let i = 0; i < 60; i++) { try { if (await evaluate('document.readyState') === 'complete') break; } catch (e) { } await sleep(150); }
    await sleep(800);
};
const esc = async () => {
    await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27 });
};
const clickPoint = async (x, y) => {
    const b = { x: Math.round(x), y: Math.round(y), button: 'left', clickCount: 1 };
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...b });
    await send('Input.dispatchMouseEvent', { type: 'mousePressed', ...b });
    await send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...b });
};

const PANEL_SEL = `/max-w-(sm|md|lg|xl|2xl|3xl|4xl|5xl|6xl|7xl)/`;

/** Synchronous: is a dialog panel currently visible? */
const OPEN_CHECK = `(() => {
  const vis = (el) => el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 40;
  const panels = [...document.querySelectorAll('div')].filter(el => vis(el) && ${PANEL_SEL}.test(String(el.className || '')));
  return panels.length > 0;
})()`;

/** Node-side poll for the dialog to appear. */
const waitOpen = async (timeout = 6000) => {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try { if (await evaluate(OPEN_CHECK) === true) return true; } catch (e) { }
        await sleep(250);
    }
    return false;
};
const waitClosed = async (timeout = 5000) => {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try { if (await evaluate(OPEN_CHECK) === false) return true; } catch (e) { }
        await sleep(250);
    }
    return false;
};

/** Open a dialog by flipping the flag on the Alpine component owning it. */
const openByFlag = (flag) => `(() => {
  for (const host of document.querySelectorAll('[x-data]')) {
    const stack = host._x_dataStack;
    if (!stack) continue;
    for (let i = stack.length - 1; i >= 0; i--) {
      const d = stack[i];
      if (d && Object.prototype.hasOwnProperty.call(d, '${flag}')) { d['${flag}'] = true; return 'set-${flag}'; }
    }
  }
  return 'component-not-found';
})()`;

const openByHelper = (flag, helper) => `(() => {
  for (const host of document.querySelectorAll('[x-data]')) {
    const stack = host._x_dataStack;
    if (!stack) continue;
    for (let i = stack.length - 1; i >= 0; i--) {
      const d = stack[i];
      if (d && Object.prototype.hasOwnProperty.call(d, '${flag}')) { ${helper} return 'helper-called'; }
    }
  }
  return 'component-not-found';
})()`;

/** ONE evaluate: locate overlay+panel and measure every invariant. */
const MEASURE = `(() => {
  const vis = (el) => el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 40;
  const panels = [...document.querySelectorAll('div')].filter(el => vis(el) && ${PANEL_SEL}.test(String(el.className || '')));
  const panel = panels[0];
  if (!panel) return { panelFound: false };
  let ov = panel;
  while (ov && ov !== document.body && getComputedStyle(ov).position !== 'fixed') ov = ov.parentElement;
  if (!ov || ov === document.body) return { panelFound: false, reason: 'no-fixed-overlay-ancestor' };

  const pr = panel.getBoundingClientRect();
  const px = Math.round(pr.left + pr.width / 2);
  const py = Math.round(Math.min(Math.max(pr.top + 20, 5), Math.max(5, innerHeight - 10)));
  const stack = document.elementsFromPoint(px, py) || [];
  const top = stack[0] || null;

  const out = {
    panelFound: true,
    panelCls: String(panel.className).slice(0, 70),
    overlayCls: String(ov.className).slice(0, 70),
    probePoint: { x: px, y: py, inViewport: py > 0 && py < innerHeight, stackLength: stack.length },
    panelTopMost: !!top && (top === panel || panel.contains(top)),
    topMostCls: top ? String(top.className).slice(0, 60) : null,
    panelZ: getComputedStyle(panel).zIndex,
    panelPosition: getComputedStyle(panel).position,
    bodyScrollLocked: document.body.classList.contains('overflow-y-hidden'),
    insidePoint: { x: px, y: py },
    backdropPoint: { x: 6, y: Math.round(ov.getBoundingClientRect().height / 2) },
  };

  let node = panel, blurred = null;
  while (node && node !== document.documentElement) {
    const cs = getComputedStyle(node);
    const bf = cs.backdropFilter || cs.webkitBackdropFilter;
    if ((cs.filter && cs.filter !== 'none') || (bf && bf !== 'none')) { blurred = String(node.className).slice(0, 60); break; }
    node = node.parentElement;
  }
  out.panelHasBlurredAncestor = blurred;

  const leaf = [...ov.querySelectorAll('*')].find(el => {
    if (el === panel || el.contains(panel)) return false;
    const cs = getComputedStyle(el);
    const bf = cs.backdropFilter || cs.webkitBackdropFilter;
    return bf && bf !== 'none' && cs.display !== 'none';
  });
  let root = leaf;
  while (root && root.parentElement && root !== ov && root.parentElement !== ov && !root.parentElement.contains(panel)) root = root.parentElement;
  out.backdropCls = root ? String(root.className).slice(0, 70) : null;
  out.backdropZ = root ? getComputedStyle(root).zIndex : null;
  const pz = parseInt(getComputedStyle(panel).zIndex, 10);
  const bz = root ? parseInt(getComputedStyle(root).zIndex, 10) : NaN;
  out.panelAboveBackdrop = Number.isFinite(pz) && Number.isFinite(bz) && pz > bz;
  return out;
})()`;

(async () => {
    fs.mkdirSync(USER_DATA, { recursive: true });
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${PORT}`, `--user-data-dir=${USER_DATA}`, '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });

    const report = { checks: [], scenarios: {} };
    const rec = (n, p, d) => { report.checks.push({ name: n, pass: !!p, detail: d }); console.log((p ? 'PASS  ' : 'FAIL  ') + n + (d ? '  :: ' + d : '')); };

    let target;
    for (let i = 0; i < 80; i++) {
        try { const l = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json(); target = l.find(t => t.type === 'page' && t.webSocketDebuggerUrl); if (target) break; } catch (e) { }
        await sleep(300);
    }
    ws = new WebSocket(target.webSocketDebuggerUrl);
    ws.addEventListener('message', (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && pending.has(msg.id)) { const { res, rej } = pending.get(msg.id); pending.delete(msg.id); msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result); return; }
        if (msg.method === 'Runtime.exceptionThrown') pageErrors.push((msg.params.exceptionDetails || {}).text);
        if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') consoleErrors.push((msg.params.args || []).map(a => a.value ?? a.description).join(' '));
        if (msg.method === 'Log.entryAdded' && msg.params.entry.level === 'error') consoleErrors.push(msg.params.entry.text);
        const s = evs.get(msg.method); if (s) [...s].forEach(f => f(msg.params));
    });
    await new Promise((r) => ws.addEventListener('open', r));
    await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');

    const verify = async (key, url, opener) => {
        console.log('\n-- ' + key + ' ' + url);
        try { await nav(BASE + url); } catch (e) { console.log('   nav err', String(e).slice(0, 90)); }
        let setResult;
        try { setResult = await evaluate(opener); } catch (e) { setResult = 'openFailed:' + String(e).slice(0, 90); }
        const opened = await waitOpen();
        let m = { panelFound: false };
        if (opened) { try { m = await evaluate(MEASURE); } catch (e) { m = { panelFound: false, err: String(e).slice(0, 120) }; } }
        report.scenarios[key] = { url, setResult, opened, ...m };

        if (!opened) { rec(key + ': dialog opens', false, 'open=' + setResult); return; }

        rec(key + ': dialog opens', true);
        rec(key + ': panel top-most at its centre (not the blur layer)', m.panelTopMost, `topMost=${m.topMostCls} panelZ=${m.panelZ}/${m.panelPosition} stackLen=${m.probePoint && m.probePoint.stackLength}`);
        rec(key + ': panel has no blurred ancestor', !m.panelHasBlurredAncestor, m.panelHasBlurredAncestor || 'none');
        rec(key + ': backdrop layer explicit z-index below the panel', m.panelAboveBackdrop, `panelZ=${m.panelZ} backdropZ=${m.backdropZ} (${m.backdropCls})`);
        rec(key + ': body scroll locked while open', m.bodyScrollLocked === true, 'locked=' + m.bodyScrollLocked);

        // click inside -> stays open
        await clickPoint(m.insidePoint.x, m.insidePoint.y);
        await sleep(700);
        const stillOpen = await evaluate(OPEN_CHECK);
        rec(key + ': click inside panel keeps it open', stillOpen === true, 'open=' + stillOpen);

        // backdrop click -> closes
        if (stillOpen === true) {
            await clickPoint(m.backdropPoint.x, m.backdropPoint.y);
            const closedByBackdrop = await waitClosed(3500);
            rec(key + ': backdrop click closes', closedByBackdrop === true, 'closed=' + closedByBackdrop);
        } else {
            try { await esc(); await waitClosed(3000); } catch (e) { }
        }

        // reopen -> Escape closes
        try { await evaluate(opener); } catch (e) { }
        const reopened = await waitOpen(4000);
        if (reopened) {
            await esc();
            const closedByEsc = await waitClosed(4500);
            rec(key + ': Escape closes', closedByEsc === true, 'closed=' + closedByEsc);
            if (!closedByEsc) {
                await evaluate(`(() => { const b = [...document.querySelectorAll('button')].find(x => /^\\s*(Cancel|Exit Diagnostic View|Close)\\s*$/i.test(x.textContent)); if (b) b.click(); })()`);
                await waitClosed(2500);
            }
        } else {
            rec(key + ': Escape closes', false, 'could not re-open');
        }
        const locked = await evaluate(`document.body.classList.contains('overflow-y-hidden')`);
        rec(key + ': scroll lock released after close', locked === false, 'locked=' + locked);
    };

    try {
        await nav(BASE + '/login');
        const st = await evaluate(`(() => {
            const e = document.querySelector('input[name="email"]');
            if (!e) return 'already-authenticated';
            e.value = 'admin.dhaka@corevisys.com';
            document.querySelector('input[name="password"]').value = 'password';
            e.closest('form').requestSubmit();
            return 'submitted';
        })()`);
        if (st === 'submitted') { await wait('Page.loadEventFired', 20000).catch(() => null); await sleep(1400); }
        await evaluate(`localStorage.setItem('darkMode','false')`);

        await verify('autoRules', '/sms/auto-rules', openByFlag('isModalOpen'));
        const detailHelper = `d.openDetailModal({ phone: '01700000000', customer: { customer_name: 'Probe' }, message: 'probe message', status: 'sent', error_code: null, api_response: {}, created_at: new Date().toISOString(), rule_id: null });`;
        await verify('smsLogs', '/sms/logs', openByHelper('isDetailModalOpen', detailHelper));
        await verify('smsHistory', '/sms/history', openByHelper('isDetailModalOpen', detailHelper));
        await verify('storeCurrency', '/settings/store', openByFlag('showCurrencyConfirmModal'));
        await verify('storeLanguage', '/settings/store', openByFlag('showLanguageConfirmModal'));

        report.consoleErrors = consoleErrors.slice(0, 6);
        report.pageErrors = pageErrors.slice(0, 6);
        rec('zero console/page errors', consoleErrors.length === 0 && pageErrors.length === 0, JSON.stringify(consoleErrors.slice(0, 3)));
    } catch (e) {
        report.fatal = String((e && e.stack) || e);
        console.log('FATAL', report.fatal);
    } finally {
        try { await send('Browser.close'); } catch (e) { }
        edge.kill();
        report.summary = {
            total: report.checks.length,
            pass: report.checks.filter(c => c.pass).length,
            fail: report.checks.filter(c => !c.pass).map(c => c.name + ' :: ' + (c.detail || '')),
        };
        fs.writeFileSync(path.join(OUT_DIR, 'verify-report-extra.json'), JSON.stringify(report, null, 2));
        console.log('\nSUMMARY', JSON.stringify(report.summary, null, 2));
    }
})();
