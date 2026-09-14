<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Resolve the absolute path to the `node` binary.
 *
 * The inline-JS syntax checks run `node --check` in a subprocess. In a long full
 * suite run the inherited PATH seen by exec() is not always reliable, which produced
 * spurious "'node' is not recognized" failures even though node is installed.
 * Resolving an absolute path (and caching it) makes those checks deterministic.
 */
function node_binary(): string
{
    static $node = null;
    if ($node !== null) {
        return $node;
    }

    foreach ([
        'C:/Program Files/nodejs/node.exe',
        'C:/Program Files (x86)/nodejs/node.exe',
    ] as $candidate) {
        if (is_file($candidate)) {
            return $node = $candidate;
        }
    }

    $out = [];
    @exec('where node 2>NUL', $out);
    if (!empty($out[0]) && is_file(trim($out[0]))) {
        return $node = trim($out[0]);
    }

    return $node = 'node';
}

/**
 * Skip the current test unless it is running on SQLite.
 *
 * A handful of tests are SELF-CONTAINED SQLite harnesses: they construct their own
 * SQLite database file, create their own schema, and drive it from parallel `php`
 * worker processes via proc_open (a genuine cross-process concurrency probe). They
 * do NOT touch the application's connection, so they cannot be "run against MySQL"
 * without being rewritten as MySQL worker harnesses (which would need MySQL
 * credentials, a throwaway schema and cleanup).
 *
 * When the suite is run on MySQL (phpunit.mysql.xml) these are skipped and reported
 * as such, so the MySQL gate reflects the tests that actually exercise the app.
 * They continue to run in full whenever the suite runs on SQLite (the default).
 */
function skipUnlessSqlite(string $reason = 'SQLite-only concurrency/migration harness; not applicable to the MySQL gate.'): void
{
    if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
        \PHPUnit\Framework\Assert::markTestSkipped($reason);
    }
}
