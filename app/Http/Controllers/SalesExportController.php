<?php

namespace App\Http\Controllers;

use App\Exports\SalesReportExport;
use App\Http\Requests\ExportSalesRequest;
use App\Models\ExportModel\ReportCatalog;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesExportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Data/Export/Export', [
            'csrfToken' => csrf_token(),
            'reportOptions' => ReportCatalog::options(),
        ]);
    }

    public function export(ExportSalesRequest $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $fileName = sprintf(
            '%s_%s_%s.xlsx',
            $filters['laporan'],
            $filters['tglstart'],
            $filters['tglend'],
        );

        return Excel::download(
            new SalesReportExport($filters['laporan'], $filters, DB::getDefaultConnection()),
            $fileName,
        );
    }
}
