<?php

/**
 * Compact amount formatter — threshold boundaries + single-source-of-truth.
 *
 * The compact-number rule table lives in exactly ONE place:
 *   resources/js/format-compact-amount.js
 * and is exposed to the UI as window.formatCompactAmount via resources/js/app.js.
 *
 * These tests import that REAL module in a node subprocess (so they exercise the
 * shipped code, not a PHP re-implementation) and assert:
 *   - every threshold boundary from the spec
 *   - the accepted cascade outputs (99,999 -> "100K"; 99,99,999 -> "100L")
 *   - the exact value is never lossy
 *   - the same raw amount yields the same output regardless of the caller
 *   - the rule table is defined in only one file
 */

use Illuminate\Support\Facades\File;

/**
 * Run the real formatter module against a list of {input} cases in node.
 *
 * @param  array<int, array{input: int|float|string}>  $cases
 * @return array<int, array{compact: string, exact: string, value: int|float, unit: string}>
 */
function run_compact_formatter(array $cases): array
{
    $module = resource_path('js/format-compact-amount.js');
    expect(is_file($module))->toBeTrue("Formatter module missing: {$module}");

    // file:/// URL so a .mjs in the temp dir can import an absolute path.
    $moduleUrl = 'file:///' . str_replace('\\', '/', $module);

    $driver = tempnam(sys_get_temp_dir(), 'compact_driver_') . '.mjs';
    $outFile = tempnam(sys_get_temp_dir(), 'compact_out_') . '.json';

    // NOTE: use a placeholder (not {$json}) so the heredoc does not try to
    // interpolate it — the JSON is injected after the template is built.
    $js = <<<JS
import { writeFileSync } from 'node:fs';
import { formatCompactAmount } from '{$moduleUrl}';

const cases = __CASES__;
const results = cases.map(({ input }) => {
    const r = formatCompactAmount(input);
    return { compact: r.compact, exact: r.exact, value: r.value, unit: r.unit };
});

writeFileSync(process.argv[2], JSON.stringify(results));
JS;

    file_put_contents($driver, str_replace('__CASES__', json_encode($cases), $js));

    $output = [];
    exec(
        escapeshellarg(node_binary()) . ' ' . escapeshellarg($driver) . ' ' . escapeshellarg($outFile) . ' 2>&1',
        $output,
        $code
    );

    $raw = is_file($outFile) ? file_get_contents($outFile) : '';

    @unlink($driver);
    @unlink($outFile);

    expect($code)->toBe(0, "node formatter driver failed:\n" . implode("\n", $output));

    $decoded = json_decode($raw, true);
    expect($decoded)->toBeArray('Formatter driver produced no JSON: ' . $raw);

    return $decoded;
}

test('compact formatter maps every threshold boundary to the confirmed format', function () {
    // input => [compact, exact, value, unit]
    $expectations = [
        0         => ['0', '0', 0, ''],
        999       => ['999', '999', 999, ''],
        1000      => ['1K', '1,000', 1, 'K'],
        1234      => ['1.23K', '1,234', 1.23, 'K'],
        12500     => ['12.5K', '12,500', 12.5, 'K'],
        99999     => ['100K', '99,999', 100, 'K'],   // accepted cascade
        100000    => ['1L', '100,000', 1, 'L'],
        125000    => ['1.25L', '125,000', 1.25, 'L'],
        1250000   => ['12.5L', '1,250,000', 12.5, 'L'],
        9999999   => ['100L', '9,999,999', 100, 'L'], // accepted cascade
        10000000  => ['1Cr', '10,000,000', 1, 'Cr'],
        15000000  => ['1.5Cr', '15,000,000', 1.5, 'Cr'],
    ];

    $cases = array_map(fn ($in) => ['input' => $in], array_keys($expectations));
    $results = run_compact_formatter($cases);

    expect($results)->toHaveCount(count($expectations));

    $inputs = array_keys($expectations);
    foreach ($results as $i => $result) {
        [$compact, $exact, $value, $unit] = $expectations[$inputs[$i]];

        expect($result['compact'])->toBe($compact, "compact mismatch for {$inputs[$i]}");
        expect($result['exact'])->toBe($exact, "exact mismatch for {$inputs[$i]}");
        expect($result['unit'])->toBe($unit, "unit mismatch for {$inputs[$i]}");
        expect((float) $result['value'])->toBe((float) $value, "value mismatch for {$inputs[$i]}");
    }
});

test('compact formatter is caller-agnostic (same raw amount -> same output)', function () {
    // The same amount expressed as an int, a float and a numeric string must
    // produce identical output — proving the format does not depend on caller.
    $cases = [
        ['input' => 12500],
        ['input' => 12500.0],
        ['input' => '12500'],
    ];

    $results = run_compact_formatter($cases);

    expect($results[0]['compact'])->toBe('12.5K');
    expect($results[0])->toBe($results[1]);
    expect($results[1])->toBe($results[2]);

    // Negative sign handling is symmetric with the positive value.
    $negatives = run_compact_formatter([['input' => -12500]]);
    expect($negatives[0]['compact'])->toBe('-12.5K');
    expect($negatives[0]['exact'])->toBe('-12,500');
});

test('compact formatter exposes exact value that round-trips to the raw amount', function () {
    $cases = [
        ['input' => 99999],
        ['input' => 9999999],
        ['input' => 12345678],
    ];

    $results = run_compact_formatter($cases);

    foreach ($results as $i => $result) {
        $raw = (float) $cases[$i]['input'];
        // "1,23,45,678"-style grouping strips commas cleanly back to the raw value.
        expect((float) str_replace(',', '', $result['exact']))->toBe($raw);
    }
});

test('compact format rules are defined in exactly one place', function () {
    // Every JS/Blade file that references the formatter must CALL it, never
    // re-define the threshold table. Exactly one file owns the definition.
    $definition = 'export function formatCompactAmount';

    $jsFiles = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($f) => $f->getExtension() === 'js');

    $owners = $jsFiles
        ->filter(fn ($f) => str_contains(File::get($f->getPathname()), $definition))
        ->map(fn ($f) => $f->getRelativePathname())
        ->values()
        ->all();

    expect($owners)->toBe(['format-compact-amount.js']);

    // No Blade view may re-implement the compact threshold table.
    $bladeFiles = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => $f->getExtension() === 'blade.php');

    foreach ($bladeFiles as $file) {
        $contents = File::get($file->getPathname());
        expect($contents)->not->toContain($definition, "Blade view re-defines the formatter: {$file->getRelativePathname()}");
        expect($contents)->not->toContain("unit: 'Cr'", "Blade view re-defines the compact table: {$file->getRelativePathname()}");
    }
});

test('x-money call sites pass value as a raw Alpine expression, never a PHP binding', function () {
    // Regression guard: `<x-money :value="subtotal" />` makes Blade evaluate
    // the RHS as PHP, but these are Alpine runtime getters -> "Undefined
    // constant" and the whole POS page fails to render. Call sites must use a
    // plain `value="..."` attribute (interpolated verbatim by the component).
    $pos = resource_path('views/module/sales/pos.blade.php');
    $contents = File::get($pos);

    $callSites = preg_match_all('/<x-money\b[^>]*>/s', $contents, $matches);
    expect($callSites)->toBeGreaterThan(0, 'Expected <x-money> call sites in pos.blade.php');

    foreach ($matches[0] as $tag) {
        expect($tag)->not->toMatch('/<x-money[^>]*\s:value=/s', "POS <x-money> must not use a PHP binding (:value): {$tag}");
        expect($tag)->toMatch('/<x-money[^>]*\svalue="/s', "POS <x-money> must pass a plain value=\"...\" attribute: {$tag}");
    }
});