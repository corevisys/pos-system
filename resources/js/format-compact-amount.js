/* ══════════════════════════════════════════════════════════════════
   COMPACT AMOUNT FORMATTER — SINGLE SOURCE OF TRUTH
   ----------------------------------------------------------------
   Convert a raw amount into the compact display label used by every
   POS card amount, WITHOUT losing the exact value.

   This is the ONLY place the compact threshold table is defined. Every
   consumer calls this one function; no view re-implements the rules.

   Threshold table (Indian numbering - lakh / crore):
     < 1,000                 -> plain        (999)
     1,000 - 99,999          -> K            (1K, 1.23K, 12.5K)
     1,00,000 - 99,99,999    -> L            (1.25L, 12.5L)
     >= 1,00,00,000          -> Cr           (1Cr)

   Rounding rule: divide by the unit divisor, round to at most 2
   decimals, then trim trailing zeros (and a trailing dot). So `1K`
   (zero decimals) only when the rounded value is integral, and
   `12.5K` shows one decimal because 12.50 trims its trailing zero.
   ═════════════════════════════════════════════════════════════════ */

export const COMPACT_THRESHOLDS = [
    { divisor: 1e7, unit: 'Cr' }, // >= 1,00,00,000 (crore)
    { divisor: 1e5, unit: 'L' },  // >= 1,00,000    (lakh)
    { divisor: 1e3, unit: 'K' },  // >= 1,000       (thousand)
];

/**
 * Trim a fixed-decimal number string to at most `maxDecimals`, removing
 * trailing zeros (and any dangling decimal point).
 * `12.50` -> `12.5`, `12.00` -> `12`, `1.23` -> `1.23`.
 */
export function trimDecimals(value, maxDecimals = 2) {
    let out = Number(value).toFixed(maxDecimals);
    if (out.indexOf('.') !== -1) {
        out = out.replace(/0+$/, '').replace(/\.$/, '');
    }
    return out;
}

/**
 * @param {number|string} amount
 * @returns {{ value:number, unit:string, compact:string, exact:string, numeric:number }}
 *   - value   : the rounded/trimmed number shown next to the unit
 *   - unit    : '' | 'K' | 'L' | 'Cr'
 *   - compact : the exact string a card renders (no currency symbol)
 *   - exact   : full-precision plain number (grouped), never lossy
 *   - numeric : the raw numeric amount
 */
export function formatCompactAmount(amount) {
    const numeric = Number(amount) || 0;
    const abs = Math.abs(numeric);
    const sign = numeric < 0 ? '-' : '';

    // Below the first threshold: plain integer display.
    let value = Math.round(abs);
    let unit = '';

    for (const tier of COMPACT_THRESHOLDS) {
        if (abs >= tier.divisor) {
            value = Number(trimDecimals(abs / tier.divisor, 2));
            unit = tier.unit;
            break;
        }
    }

    return {
        value,
        unit,
        compact: sign + value + unit,
        // Full precision, never lossy. Grouped for readability in tooltips.
        exact: sign + abs.toLocaleString('en-US', { maximumFractionDigits: 2 }),
        numeric,
    };
}

export default formatCompactAmount;