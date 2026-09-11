<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'cash_drawer_reconciliations';
    private string $column = 'reconciliation_code';
    private string $globalUnique = 'cash_drawer_reconciliations_reconciliation_code_unique';
    private string $compositeUnique = 'uq_cdr_store_reconciliation_code';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        // 1. Pre-check: fail loudly if any (store_id, reconciliation_code) duplicates already exist.
        $duplicates = DB::table($this->table)
            ->select('store_id', $this->column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($this->column)
            ->where($this->column, '!=', '')
            ->groupBy('store_id', $this->column)
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates->map(fn($d) => "store_id={$d->store_id}, code={$d->{$this->column}} (count={$d->total})")->implode('; ');
            throw new \RuntimeException(
                "Cannot migrate {$this->table}: duplicate ({$this->column}) values found within the same store: [{$details}]. " .
                "Resolve these duplicates before running this migration."
            );
        }

        // 2. Drop global unique and add composite unique (store_id, reconciliation_code)
        Schema::table($this->table, function (Blueprint $table) {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $indexes = collect(DB::select("SHOW INDEX FROM {$this->table}"))->pluck('Key_name')->all();
                if (in_array($this->globalUnique, $indexes, true)) {
                    $table->dropUnique($this->globalUnique);
                }
            } else {
                try {
                    $table->dropUnique($this->globalUnique);
                } catch (\Throwable $e) {
                    // Ignore if does not exist
                }
            }

            $table->unique(['store_id', $this->column], $this->compositeUnique);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $indexes = collect(DB::select("SHOW INDEX FROM {$this->table}"))->pluck('Key_name')->all();
                if (in_array($this->compositeUnique, $indexes, true)) {
                    $table->dropUnique($this->compositeUnique);
                }
            } else {
                try {
                    $table->dropUnique($this->compositeUnique);
                } catch (\Throwable $e) {
                    // Ignore if not found
                }
            }

            $table->unique($this->column, $this->globalUnique);
        });
    }
};
