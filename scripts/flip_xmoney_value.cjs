/*
 * Idempotent codemod: flip <x-money :value="…" /> to <x-money value="…" />.
 *
 * WHY: the POS card amounts are ALPINE runtime getters (e.g. `subtotal`,
 * `customerSnapshotDue`, `parseFloat(cashAmount) || 0`). Blade's `:value`
 * binding evaluates the RHS as a PHP expression, which throws
 * "Undefined constant" and aborts the whole POS page render. A plain
 * `value="…"` attribute is passed through untouched and interpolated into
 * the component as a raw client-side expression.
 */
const fs = require('fs');
const path = require('path');

const targets = [
    'resources/views/module/sales/pos.blade.php',
];

let grandTotal = 0;

for (const rel of targets) {
    const file = path.resolve(process.cwd(), rel);
    let src = fs.readFileSync(file, 'utf8');

    const matches = src.match(/<x-money\s+:value=/g) || [];
    if (matches.length === 0) {
        console.log(`  ${rel}: already converted (0 remaining)`);
        continue;
    }

    src = src.replace(/<x-money\s+:value=/g, '<x-money value=');
    fs.writeFileSync(file, src);
    grandTotal += matches.length;
    console.log(`  ${rel}: converted ${matches.length}`);
}

console.log(`TOTAL converted: ${grandTotal}`);