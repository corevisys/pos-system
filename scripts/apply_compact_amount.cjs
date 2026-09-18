/*
 * One-off codemod: replace POS card amount spans (server-side string
 * concatenation of $currencySymbol + number.toFixed(...)) with the
 * reusable <x-money> component.
 *
 * Idempotent-ish: it only matches the original concatenation pattern, so
 * re-running after the conversion is a no-op (reports 0 replacements).
 */
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'resources', 'views', 'module', 'sales', 'pos.blade.php');
let src = fs.readFileSync(file, 'utf8');

const rules = [
    // held-order amount (inside a link -> intercept click)
    {
        name: 'hold',
        re: /<span class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ parseFloat\((hold\.grand_total)\)\.toFixed\(2\)"><\/span>/g,
        to: '<x-money :value="parseFloat($2) || 0" intercept class="$1" />',
    },
    // customer snapshot due badge
    {
        name: 'due',
        re: /<span class="([^"]*)" x-text="'Due ' \+ '\{\{ \$currencySymbol \}\}' \+ (customerSnapshotDue)\.toFixed\(2\)"><\/span>/g,
        to: '<span class="$1 inline-flex items-center gap-0.5">Due <x-money :value="$2" /></span>',
    },
    // coupon "Saved" line
    {
        name: 'saved',
        re: /<span class="([^"]*)" x-text="'Saved ' \+ '\{\{ \$currencySymbol \}\}' \+ parseFloat\((couponAmount) \|\| 0\)\.toFixed\(2\)"><\/span>/g,
        to: '<span class="$1 inline-flex items-center gap-0.5">Saved <x-money :value="parseFloat($2) || 0" /></span>',
    },
    // totals card "Subtotal" prefix
    {
        name: 'subtotal-label',
        re: /<span x-text="'Subtotal ' \+ '\{\{ \$currencySymbol \}\}' \+ (subtotal)\.toFixed\(2\)"><\/span>/g,
        to: '<span class="inline-flex items-center gap-0.5">Subtotal <x-money :value="$1" /></span>',
    },
    // totals card "Disc −" prefix
    {
        name: 'disc-label',
        re: /<span x-show="totalDiscount > 0" class="([^"]*)" x-text="'Disc −' \+ '\{\{ \$currencySymbol \}\}' \+ (totalDiscount)\.toFixed\(2\)"><\/span>/g,
        to: '<span x-show="totalDiscount > 0" class="$1 inline-flex items-center gap-0.5">Disc −<x-money :value="$2" /></span>',
    },
    // EMI schedule row cell
    {
        name: 'emi-schedule',
        re: /<td class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ row\.amount\.toFixed\(2\)"><\/td>/g,
        to: '<td class="$1"><x-money :value="row.amount" /></td>',
    },
    // (parseFloat(x) || 0).toFixed(2)
    {
        name: 'parsefloat-2',
        re: /<span class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ \(parseFloat\(([A-Za-z0-9_.]+)\) \|\| 0\)\.toFixed\(2\)"><\/span>/g,
        to: '<x-money :value="parseFloat($2) || 0" class="$1" />',
    },
    // (totalDiscount - couponAmount).toFixed(2)
    {
        name: 'diff-2',
        re: /<span class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ \(totalDiscount - couponAmount\)\.toFixed\(2\)"><\/span>/g,
        to: '<x-money :value="totalDiscount - couponAmount" class="$1" />',
    },
    // (parseFloat(x) || 0).toFixed(0)
    {
        name: 'parsefloat-0',
        re: /<span class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ \(parseFloat\((emiProcessingFee)\) \|\| 0\)\.toFixed\(0\)"><\/span>/g,
        to: '<x-money :value="parseFloat($2) || 0" class="$1" />',
    },
    // x.toFixed(0)
    {
        name: 'simple-0',
        re: /<span class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ (emiMonthly)\.toFixed\(0\)"><\/span>/g,
        to: '<x-money :value="$2" class="$1" />',
    },
    // remaining simple x.toFixed(2)  (generic — MUST be last)
    {
        name: 'simple-2',
        re: /<span class="([^"]*)" x-text="'\{\{ \$currencySymbol \}\}' \+ ([A-Za-z0-9_.]+)\.toFixed\(2\)"><\/span>/g,
        to: '<x-money :value="$2" class="$1" />',
    },
];

let total = 0;
for (const rule of rules) {
    const matches = src.match(rule.re);
    const count = matches ? matches.length : 0;
    if (count) {
        src = src.replace(rule.re, rule.to);
    }
    total += count;
    console.log(`${rule.name}: ${count}`);
}

fs.writeFileSync(file, src, 'utf8');
console.log(`TOTAL replacements: ${total}`);