<?php

namespace App\Http\Controllers;

use App\Models\AcTransaction;
use App\Models\DbStore;
use App\Models\DbWarehouseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.2 — Owner-gated consolidated reporting.
 *
 * These endpoints read ACROSS stores using the sanctioned
 * withoutGlobalScope('store_id') + explicit grouping pattern (the same one
 * DashboardController::computeStoreStats uses). Every endpoint is hard-gated to the
 * cross-store identities (Owner / Developer); branch admins are rejected with 403,
 * and a ?store_id= / ?all_stores= parameter can never widen a branch admin's scope.
 *
 * Customer/due rollups are deliberately EXCLUDED here: customer sharing is an open
 * decision (see the gap analysis) and must not be assumed.
 */
class ConsolidatedReportController extends Controller
{
    public function ledger(Request $request)
    {
        $this->authorizeCrossStore();

        $stores = DbStore::orderBy('id')->get();
        $storeFilter = $this->resolveStoreFilter($request);

        $query = AcTransaction::withoutGlobalScope('store_id')
            ->select(
                'store_id',
                DB::raw('COALESCE(SUM(debit_amt), 0) as total_debit'),
                DB::raw('COALESCE(SUM(credit_amt), 0) as total_credit')
            )
            ->groupBy('store_id');

        if ($storeFilter !== null) {
            $query->where('store_id', $storeFilter);
        }

        $byStore = $query->get()->keyBy('store_id');

        $rows = $stores->when($storeFilter !== null, fn ($c) => $c->where('id', $storeFilter))
            ->map(function (DbStore $store) use ($byStore) {
                $row = $byStore->get($store->id);
                $debit = (float) ($row->total_debit ?? 0);
                $credit = (float) ($row->total_credit ?? 0);
                return [
                    'store'        => $store,
                    'total_debit'  => $debit,
                    'total_credit' => $credit,
                    'net'          => $debit - $credit,
                ];
            })->values();

        $totals = [
            'total_debit'  => $rows->sum('total_debit'),
            'total_credit' => $rows->sum('total_credit'),
            'net'          => $rows->sum('net'),
            'store_count'  => $rows->count(),
        ];

        return view('consolidated.ledger', compact('rows', 'totals', 'stores', 'storeFilter'));
    }

    public function stock(Request $request)
    {
        $this->authorizeCrossStore();

        $stores = DbStore::orderBy('id')->get();
        $storeFilter = $this->resolveStoreFilter($request);

        $query = DbWarehouseItem::withoutGlobalScope('store_id')
            ->select(
                'store_id',
                DB::raw('COALESCE(SUM(available_qty), 0) as total_qty'),
                DB::raw('COUNT(DISTINCT item_id) as item_count'),
                DB::raw('COUNT(DISTINCT warehouse_id) as warehouse_count')
            )
            ->groupBy('store_id');

        if ($storeFilter !== null) {
            $query->where('store_id', $storeFilter);
        }

        $byStore = $query->get()->keyBy('store_id');

        $rows = $stores->when($storeFilter !== null, fn ($c) => $c->where('id', $storeFilter))
            ->map(function (DbStore $store) use ($byStore) {
                $row = $byStore->get($store->id);
                return [
                    'store'           => $store,
                    'total_qty'       => (float) ($row->total_qty ?? 0),
                    'item_count'      => (int) ($row->item_count ?? 0),
                    'warehouse_count' => (int) ($row->warehouse_count ?? 0),
                ];
            })->values();

        $totals = [
            'total_qty'  => $rows->sum('total_qty'),
            'store_count'=> $rows->count(),
        ];

        return view('consolidated.stock', compact('rows', 'totals', 'stores', 'storeFilter'));
    }

    /**
     * Hard gate: only cross-store identities may read consolidated data. A branch
     * admin is rejected regardless of any request parameter.
     */
    private function authorizeCrossStore(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->canViewAllStores(),
            403,
            'Consolidated reporting is restricted to the Owner.'
        );
    }

    /**
     * Optional per-store filter, Owner-only by virtue of the gate above. 'all' (or
     * absent) means the all-stores aggregate. Branch admins can never reach this.
     */
    private function resolveStoreFilter(Request $request): ?int
    {
        $raw = $request->query('store_id');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return null;
        }

        return (int) $raw;
    }
}
