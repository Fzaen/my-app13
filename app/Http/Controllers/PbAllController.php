<?php

namespace App\Http\Controllers;

use App\Exports\PbSummaryExport;
use App\Models\FrontEnd\ObiOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PbAllController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('Pb/All', [
            'rows' => ObiOrder::getDailySummaryForLastSevenDays(),
            'search' => trim($filters['search'] ?? ''),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return Excel::download(new PbSummaryExport(trim($filters['search'] ?? '')), 'pb-all-h7.xlsx');
    }
}
