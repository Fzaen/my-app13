<?php

namespace App\Http\Controllers;

use App\Models\ExportModel\PurchaseOrderReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PbItemController extends Controller
{
    public function details(Request $request): JsonResponse
    {
        $input = $request->validate([
            'tglpb' => ['required', 'date_format:Y-m-d'],
            'tglstart' => ['nullable', 'date_format:Y-m-d', 'required_with:tglend'],
            'tglend' => ['nullable', 'date_format:Y-m-d', 'required_with:tglstart', 'after_or_equal:tglstart'],
            'pb' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
        ]);
        $detailStartDate = $input['tglstart'] ?? $input['tglpb'];
        $detailEndDate = $input['tglend'] ?? $input['tglpb'];
        $filters = [
            'tglstart' => $input['tglpb'],
            'tglend' => $input['tglpb'],
            'detail_tglstart' => $detailStartDate,
            'detail_tglend' => $detailEndDate,
            'nopb' => $input['pb'],
            'recid' => 'all',
        ];
        $connection = DB::connection();
        $items = PurchaseOrderReport::forTable(
            'pb-peritem',
            $filters,
            $connection->getName(),
        )->get();
        $cashbacks = $connection->table('promo_klikigr')
            ->whereRaw('tgl_trans::date >= ?::date AND tgl_trans::date <= ?::date', [
                $detailStartDate,
                $detailEndDate,
            ])
            ->whereRaw("RTRIM(SUBSTRING(no_pb, 1, 7), '/') = ?", [$input['pb']])
            ->select([
                'kode_promo',
                'prdcd as plu',
                'kelipatan',
                'cashback_order as cb_order',
                'cashback_real as cb_real',
            ])
            ->selectRaw('COALESCE(gift_order, tipe_promo) AS tipe')
            ->orderBy('kode_promo')
            ->orderBy('prdcd')
            ->get();

        return response()->json([
            'items' => $items,
            'cashbacks' => $cashbacks,
        ]);
    }

    public function location(Request $request): JsonResponse|Response
    {
        $input = $request->validate([
            'tglpb' => ['required', 'date_format:Y-m-d'],
            'tglstart' => ['nullable', 'date_format:Y-m-d', 'required_with:tglend'],
            'tglend' => ['nullable', 'date_format:Y-m-d', 'required_with:tglstart', 'after_or_equal:tglstart'],
            'pb' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
        ]);
        $detailStartDate = $input['tglstart'] ?? $input['tglpb'];
        $detailEndDate = $input['tglend'] ?? $input['tglpb'];
        $rows = PurchaseOrderReport::locationPreview([
            'tglstart' => $input['tglpb'],
            'tglend' => $input['tglpb'],
            'detail_tglstart' => $detailStartDate,
            'detail_tglend' => $detailEndDate,
            'nopb' => $input['pb'],
            'recid' => 'all',
        ])->get();

        abort_if($rows->isEmpty(), 404);

        if ($request->expectsJson()) {
            return response()->json(['rows' => $rows]);
        }

        return Inertia::render('Pb/ItemLocationPreview', [
            'rows' => $rows,
        ]);
    }
}
