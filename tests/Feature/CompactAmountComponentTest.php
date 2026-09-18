<?php

/**
 * Compact amount COMPONENT contract — <x-money> + <x-stat-card :money>.
 *
 * Companion to CompactAmountFormatterTest.php (which pins the threshold table
 * inside resources/js/format-compact-amount.js). These tests pin the Blade
 * INTEGRATION contract that the app-wide rollout depends on:
 *
 *   - <x-money> renders the compact label through window.formatCompactAmount
 *     at runtime, and always carries a non-lossy exact value + reveal tooltip.
 *   - `value` is passed through VERBATIM as a raw Alpine expression (never
 *     evaluated as PHP server-side) — this is what lets live report amounts
 *     like `parseFloat(record.total.replace(/,/g,''))` work.
 *   - <x-stat-card> has a BACKWARD-COMPATIBLE `:money` prop: default false
 *     preserves the original verbatim rendering exactly; true delegates to
 *     <x-money>.
 *   - The `<x-money :value="...">` PHP-binding form is confined to the single
 *     documented exception (stat-card.blade.php). Anywhere else it would make
 *     Blade evaluate an Alpine expression as PHP and break the page.
 *
 * NOTE on the stat-card assertions: an anonymous component that CONTAINS
 * another anonymous component cannot be rendered through `Blade::render()`
 * (the nested component's compiled `$component` handle is unavailable in that
 * harness). That is a test-harness limitation only — the app renders it
 * normally. The stat-card contract is therefore pinned structurally against
 * its source, which is exactly what "backward compatible" means here.
 */

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/**
 * Render a single top-level Blade component usage to HTML.
 */
function render_blade_component(string $blade): string
{
    return Blade::render($blade);
}

/**
 * Read a view with its {{-- ... --}} comments stripped, so structural scans
 * match real markup rather than prose that merely mentions a component.
 */
function blade_source(string $relativePath): string
{
    $path = resource_path('views/' . str_replace('\\', '/', $relativePath));
    $raw = File::get($path);

    return preg_replace('/\{\{--.*?--\}\}/s', '', $raw);
}

/*
|--------------------------------------------------------------------------
| <x-money> runtime contract
|--------------------------------------------------------------------------
*/

test('x-money passes the raw Alpine expression to the runtime formatter', function () {
    // The compact label is computed CLIENT-SIDE, so the static HTML must carry
    // the exact expression inside window.formatCompactAmount(...).compact.
    $html = render_blade_component('<x-money value="12500" />');

    expect($html)->toContain('window.formatCompactAmount(12500).compact');
    expect($html)->toContain('<span class="tabular-nums"');
});

test('x-money carries a non-lossy exact value and a reveal tooltip', function () {
    $html = render_blade_component('<x-money value="99999" />');

    // Exact value is exposed to Alpine as a formatted binding — always present,
    // never lossy (it re-rays the SAME raw expression).
    expect($html)->toContain(':data-exact-amount="(Number(99999) || 0).toFixed(2)"');
    // The exact reveal uses the SAME shared formatter + the .amount-tip class.
    expect($html)->toContain('window.formatCompactAmount(99999).exact');
    expect($html)->toContain('amount-tip');
    expect($html)->toContain('x-cloak');
});

test('x-money keeps comma-grouped report expressions verbatim for Alpine', function () {
    // Report payloads arrive number_format()-ed ("1,23,456.00"), so live call
    // sites wrap them in parseFloat(...replace(/,/g,'')). That expression is a
    // string to the server AND to Alpine: the component must interpolate it
    // VERBATIM (never PHP-evaluate it). Blade entity-encodes the single quotes.
    $expr = "parseFloat(record.total.replace(/,/g,''))";
    $html = render_blade_component('<x-money value="' . $expr . '" />');

    $escaped = str_replace("'", '&#039;', $expr);

    expect($html)->toContain('window.formatCompactAmount(' . $escaped . ').compact');
    expect($html)->toContain('window.formatCompactAmount(' . $escaped . ').exact');
    expect($html)->toContain(':data-exact-amount="(Number(' . $escaped . ') || 0).toFixed(2)"');
});

test('x-money honours an explicit symbol and defaults to the shared currency symbol', function () {
    $explicit = render_blade_component('<x-money value="12500" symbol="Rs." />');
    expect($explicit)->toContain("'Rs.'");

    // With no symbol prop the component falls back to the view-shared
    // $currencySymbol (AppServiceProvider::resolveCurrencySymbol).
    $default = render_blade_component('<x-money value="12500" />');
    expect($default)->toContain("'$'");
});

test('x-money prefixes the same symbol on the compact label and the exact reveal', function () {
    // Compact label uses the symbol inside a single-quoted x-text; the tooltip
    // prints it bare. Both must carry the SAME resolved symbol, otherwise the
    // hover reveal would show a different currency than the label.
    $html = render_blade_component('<x-money value="15000000" symbol="€" />');

    expect(substr_count($html, "'€'"))->toBe(1, 'Compact label must use the resolved symbol');
    expect(substr_count($html, '>€<'))->toBe(1, 'Tooltip reveal must use the same symbol');
});

/*
|--------------------------------------------------------------------------
| <x-stat-card :money> backward compatibility (structural contract)
|--------------------------------------------------------------------------
*/

test('stat-card exposes a backward-compatible money prop that defaults to verbatim', function () {
    $src = blade_source('components/stat-card.blade.php');

    // Default preserves the original verbatim rendering for every consumer.
    expect($src)->toContain("'money' => false");
    // The opt-in branch delegates to the one shared component.
    expect($src)->toContain('@if($money)');
    expect($src)->toContain('<x-money :value="$value" />');
    // The legacy branch is untouched: raw $value / slot, no formatting.
    expect($src)->toContain('{{ $value ?? $slot }}');
});

test('stat-card default branch introduces no compact formatting', function () {
    $src = blade_source('components/stat-card.blade.php');

    // Only the money branch may reference the formatter; there is exactly one.
    expect(substr_count($src, '<x-money'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Guard: the PHP-binding form stays confined to its single exception
|--------------------------------------------------------------------------
*/

test('x-money :value PHP binding appears only in the documented stat-card exception', function () {
    // `:value="subtotal"` makes Blade evaluate subtotal as PHP ("Undefined
    // constant") and breaks the page. The ONLY sanctioned use of the colon form
    // is stat-card.blade.php, where $value holds a raw expression STRING that
    // is deliberately re-interpolated by <x-money>.
    $allowed = 'components/stat-card.blade.php';

    $bladeFiles = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'));

    $offenders = [];

    foreach ($bladeFiles as $file) {
        if (str_ends_with(str_replace('\\', '/', $file->getRelativePathname()), $allowed)) {
            continue;
        }

        $contents = File::get($file->getPathname());

        if (preg_match('/<x-money\b[^>]*\s:value=/s', $contents)) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'x-money :value= (PHP binding) leaked outside stat-card.blade.php: ' . implode(', ', $offenders));
});

test('every x-money call site outside the exception uses a plain value attribute', function () {
    $bladeFiles = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'));

    foreach ($bladeFiles as $file) {
        $rel = str_replace('\\', '/', $file->getRelativePathname());
        // Strip comments so prose mentioning <x-money ...> is not mistaken for
        // a real call site.
        $contents = preg_replace('/\{\{--.*?--\}\}/s', '', File::get($file->getPathname()));

        if (!preg_match_all('/<x-money\b[^>]*>/s', $contents, $matches)) {
            continue;
        }

        $isException = str_ends_with($rel, 'components/stat-card.blade.php');

        foreach ($matches[0] as $tag) {
            if ($isException) {
                // The documented exception intentionally uses the colon form so
                // the passed-through string stays a raw Alpine expression.
                expect($tag)->toMatch('/\s:value=/s', "stat-card <x-money> must use :value: {$tag}");
            } else {
                expect($tag)->toMatch('/\svalue="/s', "x-money must pass a plain value=\"...\" attribute in {$rel}: {$tag}");
                expect($tag)->not->toMatch('/\s:value=/s', "x-money must never use a PHP binding (:value) in {$rel}: {$tag}");
            }
        }
    }
});

/*
|--------------------------------------------------------------------------
| REGRESSION GUARD: formatter global must exist BEFORE Alpine boots
|--------------------------------------------------------------------------
| The app-wide "blank amount" regression was an ORDERING bug, invisible to
| any PHP/static-HTML assertion: Alpine.start() walks the DOM synchronously
| and evaluates every <x-money> binding (window.formatCompactAmount(<expr>)),
| so if the global is attached AFTER start() each expression throws
| `TypeError: window.formatCompactAmount is not a function` and x-text never
| writes — every compact amount renders blank. Pin the order in BOTH the
| source and the compiled bundle the browser actually executes.
*/

test('the compact formatter global is registered before Alpine.start() in source and built bundle', function () {
    $src = File::get(base_path('resources/js/app.js'));

    $registerPos = strpos($src, 'window.formatCompactAmount = formatCompactAmount');
    // Match the real call including its terminator so prose/comment mentions
    // of "Alpine.start()" earlier in the file cannot satisfy the search.
    $startPos = strpos($src, 'Alpine.start();');

    expect($registerPos)->not->toBeFalse('app.js must register window.formatCompactAmount');
    expect($startPos)->not->toBeFalse('app.js must call Alpine.start()');
    expect($registerPos)->toBeLessThan(
        $startPos,
        'window.formatCompactAmount must be assigned BEFORE Alpine.start()'
    );

    // Same guarantee in the compiled asset (minified) the page loads.
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $bundlePath = public_path('build/' . $manifest['resources/js/app.js']['file']);
    $js = File::get($bundlePath);

    $alpinePos = strpos($js, 'window.Alpine');
    $bundleStartPos = $alpinePos === false ? false : strpos($js, '.start(', $alpinePos);
    $bundleFmtPos = strpos($js, 'window.formatCompactAmount');

    expect($bundleFmtPos)->not->toBeFalse('built bundle must register window.formatCompactAmount');
    expect($bundleStartPos)->not->toBeFalse('built bundle must call Alpine.start()');
    expect($bundleFmtPos)->toBeLessThan(
        $bundleStartPos,
        'built bundle must register window.formatCompactAmount BEFORE Alpine.start()'
    );
});