<?php

namespace App\Http\Controllers;

use App\Exports\PbDateReportExport;
use App\Exports\SalesReportExport;
use App\Models\ExportModel\PbDateReport;
use App\Models\ExportModel\PurchaseOrderReport;
use App\Models\ExportModel\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseOrderReportController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const REPORTS = [
        'pb-proses' => 'PB Proses',
        'pb-proses-item' => 'PB Proses Item',
        'pb-kirim-list' => 'List Kirim All',
        'pb-belum-pin' => 'List Belum PIN',
        'pb-ambil-spi' => 'Ambil di SPI',
        'pb-request-kirim' => 'List Request Kirim',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const DEFAULT_FILTERS = [
        'pb-proses' => ['recid' => 'proses', 'pin' => 'all'],
        'pb-proses-item' => ['recid' => 'batas'],
        'pb-kirim-list' => ['recid' => 'all', 'ekspedisi' => 'kirim'],
        'pb-belum-pin' => ['recid' => 'proses', 'pin' => 'belumpin'],
        'pb-ambil-spi' => ['recid' => 'proses', 'pin' => 'ambil'],
        'pb-request-kirim' => ['request' => '1'],
    ];

    public function index(Request $request, string $report): Response
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $filters = $this->validatedFilters($request, $report);
        $search = trim($filters['search'] ?? '');
        $perPage = (int) ($filters['per_page'] ?? 25);
        $query = $this->reportQuery(
            $report,
            $filters,
            DB::getDefaultConnection(),
            $search,
        );

        $rows = $query->simplePaginate($perPage)->appends($filters);

        return Inertia::render('Pb/Report', [
            'report' => $report,
            'reportTitle' => self::REPORTS[$report],
            'maxPeriodMonths' => ReportCatalog::maxPeriodMonths($report),
            'maxExportPeriodMonths' => ReportCatalog::maxExportPeriodMonths($report),
            'filters' => [
                ...$filters,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'rows' => $rows,
        ]);
    }

    public function copy(Request $request, string $report): JsonResponse
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $filters = $this->validatedFilters($request, $report);
        $search = trim($filters['search'] ?? '');
        $rows = $this->reportQuery(
            $report,
            $filters,
            DB::getDefaultConnection(),
            $search,
        )->get();

        return response()->json(['rows' => $rows]);
    }

    public function export(Request $request, string $report): BinaryFileResponse
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        $filters = $this->validatedFilters($request, $report, true);
        $search = trim($filters['search'] ?? '');
        $dateFilters = [
            'tglstart' => $filters['tglstart'],
            'tglend' => $filters['tglend'],
        ];
        $connectionName = DB::getDefaultConnection();

        $export = $report === 'pb-proses-item'
            ? new SalesReportExport(
                $report,
                [
                    ...$dateFilters,
                    'recid' => $filters['recid'] ?? 'batas',
                    'ket_item' => $filters['ket_item'] ?? '',
                ],
                $connectionName,
                $search,
            )
            : new PbDateReportExport(
                [...$dateFilters, ...$this->reportFilters($filters)],
                $connectionName,
                $search,
            );

        return Excel::download(
            $export,
            sprintf('%s_%s_%s.xlsx', $report, $dateFilters['tglstart'], $dateFilters['tglend']),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportQuery(
        string $report,
        array $filters,
        string $connectionName,
        string $search,
    ): Builder {
        $dateFilters = [
            'tglstart' => $filters['tglstart'],
            'tglend' => $filters['tglend'],
        ];

        if ($report === 'pb-proses-item') {
            return PurchaseOrderReport::forTable(
                $report,
                [
                    ...$dateFilters,
                    'recid' => $filters['recid'] ?? 'batas',
                    'ket_item' => $filters['ket_item'] ?? '',
                ],
                $connectionName,
                $search,
            );
        }

        return PbDateReport::query(
            [...$dateFilters, ...$this->reportFilters($filters)],
            $connectionName,
            $search,
        );
    }

    /**
     * @return array{
     *     tglstart: string,
     *     tglend: string,
     *     recid?: string|null,
     *     ket_item?: string|null,
     *     ekspedisi?: string|null,
     *     pin?: string|null,
     *     kdmm?: string|null,
     *     nopb?: string|null,
     *     request?: bool|null,
     *     search?: string|null,
     *     per_page?: int|string|null,
     *     page?: int|string|null
     * }
     */
    private function validatedFilters(Request $request, string $report, bool $isExport = false): array
    {
        $defaults = [
            'tglstart' => $report === 'pb-proses'
                ? now()->startOfMonth()->subMonthNoOverflow()->toDateString()
                : now()->subDays(6)->toDateString(),
            'tglend' => now()->toDateString(),
            ...(self::DEFAULT_FILTERS[$report] ?? []),
        ];

        $request->merge(array_replace($defaults, $request->query->all()));

        $validator = Validator::make($request->all(), [
            'tglstart' => ['required', 'date_format:Y-m-d', 'before_or_equal:tglend'],
            'tglend' => ['required', 'date_format:Y-m-d', 'after_or_equal:tglstart'],
            'recid' => [
                'nullable',
                Rule::in(['all', 'proses', 'batas', 'notnull', 'null', 'B', '1', '2', '3', '5', '6']),
            ],
            ...($report === 'pb-proses-item'
                ? ['ket_item' => ['nullable', Rule::in(['PICKING', 'BATAL', 'PICKING SCAN 0'])]]
                : []),
            ...($report !== 'pb-proses'
                ? ['ekspedisi' => ['nullable', Rule::in(['Ambil', 'Indopaket', 'kirim'])]]
                : []),
            'pin' => ['nullable', Rule::in([
                'all',
                'belumpin',
                'sudahpin',
                'tms',
                'pinb',
                'indopaket',
                'ambil',
            ])],
            'kdmm' => ['nullable', 'string', 'max:20'],
            'nopb' => ['nullable', 'string', 'max:30'],
            'request' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($request, $report, $isExport): void {
            if ($validator->errors()->hasAny(['tglstart', 'tglend'])) {
                return;
            }

            $maxPeriodMonths = $isExport
                ? ReportCatalog::maxExportPeriodMonths($report)
                : ReportCatalog::maxPeriodMonths($report);

            if ($maxPeriodMonths === null) {
                return;
            }

            $startDate = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('tglstart')->toString());
            $endDate = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('tglend')->toString());

            if ($endDate->greaterThanOrEqualTo($startDate->addMonthsNoOverflow($maxPeriodMonths))) {
                $validator->errors()->add(
                    'tglend',
                    'Rentang '.($isExport ? 'ekspor ' : '')."laporan ini maksimal {$maxPeriodMonths} bulan. Pilih tanggal yang lebih pendek.",
                );
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|bool|null>
     */
    private function reportFilters(array $filters): array
    {
        return [
            'recid' => $filters['recid'] ?? null,
            'ekspedisi' => $filters['ekspedisi'] ?? null,
            'pin' => $filters['pin'] ?? null,
            'kdmm' => $filters['kdmm'] ?? null,
            'nopb' => $filters['nopb'] ?? null,
            'request' => (bool) ($filters['request'] ?? false),
        ];
    }
}
