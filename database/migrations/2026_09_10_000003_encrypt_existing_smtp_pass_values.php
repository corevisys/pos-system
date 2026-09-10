<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Phase 3.3 companion migration.
 *
 * DbStore now casts smtp_pass => 'encrypted'. Any db_store rows that still hold a
 * plaintext smtp_pass would throw a DecryptException the first time the model is
 * read. This migration transparently encrypts only the values that are not already
 * encrypted, and is idempotent (safe to re-run).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('db_store') || !Schema::hasColumn('db_store', 'smtp_pass')) {
            return;
        }

        $rows = DB::table('db_store')->select('id', 'smtp_pass')->get();

        foreach ($rows as $row) {
            $value = $row->smtp_pass;

            if ($value === null || $value === '') {
                continue;
            }

            // Already encrypted? Leave untouched.
            try {
                Crypt::decryptString($value);
                continue;
            } catch (DecryptException $e) {
                // Not encrypted — fall through and encrypt it.
            }

            DB::table('db_store')
                ->where('id', $row->id)
                ->update(['smtp_pass' => Crypt::encryptString($value)]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('db_store') || !Schema::hasColumn('db_store', 'smtp_pass')) {
            return;
        }

        $rows = DB::table('db_store')->select('id', 'smtp_pass')->get();

        foreach ($rows as $row) {
            $value = $row->smtp_pass;

            if ($value === null || $value === '') {
                continue;
            }

            try {
                $plain = Crypt::decryptString($value);
            } catch (DecryptException $e) {
                // Already plaintext — nothing to do.
                continue;
            }

            DB::table('db_store')
                ->where('id', $row->id)
                ->update(['smtp_pass' => $plain]);
        }
    }
};
