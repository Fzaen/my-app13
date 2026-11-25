<?php

namespace App\Http\Controllers;

use App\Models\ExportModel\LogisticsReceiving;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LogisticsReceivingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Logistics/Receiving', [
            'supplierRows' => LogisticsReceiving::suppliers(),
            'purchaseOrderRows' => LogisticsReceiving::purchaseOrders(),
            'itemRows' => LogisticsReceiving::items(),
        ]);
    }

    public function supplierReturns(string $supplier): JsonResponse
    {
        return response()->json([
            'rows' => LogisticsReceiving::supplierReturns($supplier),
        ]);
    }
}
