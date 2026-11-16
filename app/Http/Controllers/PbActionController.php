<?php

namespace App\Http\Controllers;

use App\Models\ExportModel\PbDateReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PbActionController extends Controller
{
    /**
     * @var list<string>
     */
    private const GIFT_INPUT_USERS = [
        'HAINANI',
        'AMINAH',
        'HUSAINI',
        'FAUZAN',
        'ALEXANDER',
    ];

    /**
     * @var list<string>
     */
    private const REQUEST_TYPES = [
        'RIT 1 PAGI',
        'RIT 2 SIANG',
        'RIT 3 SORE',
    ];

    /**
     * @var list<string>
     */
    private const REPORTS = [
        'pb-proses',
        'pb-proses-item',
        'pb-kirim-list',
        'pb-belum-pin',
        'pb-ambil-spi',
        'pb-request-kirim',
    ];

    /**
     * @var list<string>
     */
    private const RECID_FILTERS = [
        'all',
        'proses',
        'batas',
        'notnull',
        'null',
        'B',
        '1',
        '2',
        '3',
        '5',
        '6',
    ];

    public function createGift(Request $request): Response
    {
        $order = $this->findGiftOrder($this->validateOrderIdentifiers($request));
        $connection = DB::connection();
        $this->assertGiftCanBeSaved($connection, $order);

        return Inertia::render('Pb/Action', [
            'action' => 'gift',
            'mode' => 'create',
            'record' => [
                'tglpb' => (string) $order->obi_tglpb,
                'nopb' => (string) $order->obi_nopb,
                'pb' => (string) $order->pb,
                'notrans' => str_pad((string) $order->obi_notrans, 5, '0', STR_PAD_LEFT),
                'kdmm' => (string) $order->obi_kdmember,
                'namamm' => $order->cus_namamember,
                'gift_name' => $order->ads_hadiah,
                'plu' => (string) $order->ads_prdcd,
                'qty' => (int) $order->ads_qty,
                'kodepromo' => (string) $order->ads_kode,
            ],
            'loyaltyRecords' => [],
            'selectedProgram' => null,
            'returnFilters' => $this->validatedReturnFilters($request, (string) $order->obi_tglpb),
        ]);
    }

    public function storeGift(Request $request): RedirectResponse
    {
        $input = $request->validate([
            ...$this->orderIdentifierRules(),
            'penginput' => ['required', Rule::in(self::GIFT_INPUT_USERS)],
        ]);

        $order = $this->findGiftOrder($input);
        $returnFilters = $this->validatedReturnFilters($request, (string) $order->obi_tglpb);
        $connection = DB::connection();

        $connection->transaction(function () use ($connection, $order, $input): void {
            $this->assertGiftCanBeSaved($connection, $order);

            $gift = [
                'hdh_nopb' => (string) $order->pb,
                'hdh_mm' => (string) $order->obi_kdmember,
                'hdh_tgl' => (string) $order->obi_tglpb,
                'hdh_notrans' => str_pad((string) $order->obi_notrans, 5, '0', STR_PAD_LEFT),
                'hdh_prdcd' => (string) $order->ads_prdcd,
                'hdh_qty' => (int) $order->ads_qty,
                'hdh_create_at' => now(),
                'hdh_nama' => $input['penginput'],
                'hdh_kodepromo' => $order->ads_kode,
            ];

            $connection->table('tmp.hadiah')->insert($gift);

            $bonusPromos = ['ADSDEC3', 'ADS26JAN4', 'ADS26FEB3', 'ADS26MAR3'];

            if (in_array($order->ads_kode, $bonusPromos, true)) {
                $gift['hdh_nopb'] = (string) $order->pb.'B';
                $gift['hdh_prdcd'] = in_array($order->ads_kode, ['ADS26FEB3', 'ADS26MAR3'], true)
                    ? '0060410'
                    : '1252630';
                $gift['hdh_qty'] = 3;

                $connection->table('tmp.hadiah')->insert($gift);
            }
        });

        return $this->redirectToReport($returnFilters, 'Data hadiah berhasil disimpan.');
    }

    public function loyalty(Request $request): Response
    {
        $input = $request->validate([
            'kdmm' => ['required', 'string', 'max:20'],
            'program' => ['nullable', 'string', 'max:100'],
        ]);

        $connection = DB::connection();
        $loyaltyRecords = $connection->table('tmp.spi1m_loyalty')
            ->where('ly_kode', $input['kdmm'])
            ->whereNull('ly_tgl_kirim')
            ->whereNotNull('ly_program')
            ->orderBy('ly_program')
            ->get(['ly_program', 'ly_hadiah', 'ly_periode']);

        abort_if($loyaltyRecords->isEmpty(), 404);

        $selectedProgram = $input['program'] ?? $loyaltyRecords->first()->ly_program;
        abort_unless(
            $loyaltyRecords->contains(
                fn (object $record): bool => $record->ly_program === $selectedProgram,
            ),
            404,
        );

        return Inertia::render('Pb/Action', [
            'action' => 'loyalty',
            'mode' => 'update',
            'record' => [
                'kdmm' => $input['kdmm'],
                'namamm' => $connection->table('tbmaster_customer')
                    ->where('cus_kodemember', $input['kdmm'])
                    ->value('cus_namamember'),
            ],
            'loyaltyRecords' => $loyaltyRecords
                ->map(fn (object $record): array => [
                    'program' => $record->ly_program,
                    'gift' => $record->ly_hadiah,
                    'period' => $record->ly_periode,
                ])
                ->values()
                ->all(),
            'selectedProgram' => $selectedProgram,
            'returnFilters' => $this->validatedReturnFilters($request, now()->toDateString()),
        ]);
    }

    public function updateLoyalty(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'kdmm' => ['required', 'string', 'max:20'],
            'program' => ['required', 'string', 'max:100'],
            'tgl_kirim' => ['required', 'date_format:Y-m-d'],
            'ttd' => ['required', 'string', 'max:100'],
        ]);

        $returnFilters = $this->validatedReturnFilters($request, now()->toDateString());
        $connection = DB::connection();

        $connection->transaction(function () use ($connection, $input): void {
            $loyaltyQuery = $connection->table('tmp.spi1m_loyalty')
                ->where('ly_kode', $input['kdmm'])
                ->where('ly_program', $input['program'])
                ->whereNull('ly_tgl_kirim');
            $records = (clone $loyaltyQuery)->lockForUpdate()->get(['ly_program']);

            abort_if($records->isEmpty(), 404);
            abort_if($records->count() !== 1, 409, 'Data loyalty memiliki lebih dari satu baris.');

            $updated = $loyaltyQuery->update([
                'ly_tgl_kirim' => $input['tgl_kirim'],
                'ly_ttd' => $input['ttd'],
                'ly_create_dt' => now(),
            ]);

            abort_unless($updated === 1, 409, 'Data loyalty sudah berubah. Muat ulang halaman.');
        });

        return $this->redirectToReport($returnFilters, 'Data loyalty berhasil diperbarui.');
    }

    public function requestForm(Request $request, string $mode): Response
    {
        abort_unless(in_array($mode, ['create', 'edit'], true), 404);

        $order = $this->findOrder($this->validateOrderIdentifiers($request));
        $connection = DB::connection();
        $existingRequest = $this->findShippingRequest($connection, $order);

        if ($mode === 'create') {
            abort_if($existingRequest !== null, 409, 'Request kirim sudah tersedia. Muat ulang laporan.');
        } else {
            abort_if($existingRequest === null, 404);
        }

        return Inertia::render('Pb/Action', [
            'action' => 'request',
            'mode' => $mode,
            'record' => [
                'tglpb' => (string) $order->obi_tglpb,
                'nopb' => (string) $order->obi_nopb,
                'pb' => (string) $order->pb,
                'notrans' => (string) $order->obi_notrans,
                'kdmm' => (string) $order->obi_kdmember,
                'namamm' => $order->cus_namamember,
                'tgl_kirim' => $existingRequest?->req_tgl_kirim
                    ? (string) $existingRequest->req_tgl_kirim
                    : '',
                'request' => $existingRequest?->req_request ?? '',
            ],
            'loyaltyRecords' => [],
            'selectedProgram' => null,
            'returnFilters' => $this->validatedReturnFilters($request, (string) $order->obi_tglpb),
        ]);
    }

    public function saveRequest(Request $request): RedirectResponse
    {
        $input = $request->validate([
            ...$this->orderIdentifierRules(),
            'mode' => ['required', Rule::in(['create', 'edit'])],
            'tgl_kirim' => ['required', 'date_format:Y-m-d'],
            'request' => ['required', Rule::in(self::REQUEST_TYPES)],
        ]);

        $order = $this->findOrder($input);
        $returnFilters = $this->validatedReturnFilters($request, (string) $order->obi_tglpb);
        $connection = DB::connection();

        $connection->transaction(function () use ($connection, $input, $order): void {
            $requests = $this->shippingRequestQuery($connection, $order)
                ->lockForUpdate()
                ->get(['req_nopb']);

            abort_if($requests->count() > 1, 409, 'Data request kirim memiliki lebih dari satu baris.');

            $existingRequest = $requests->first();

            if ($input['mode'] === 'create') {
                abort_if($existingRequest !== null, 409, 'Request kirim sudah dibuat. Muat ulang laporan.');

                $connection->table('tmp.spi1m_requestkirim')->insert([
                    'req_nopb' => (string) $order->pb,
                    'req_tglpb' => (string) $order->obi_tglpb,
                    'req_kodemm' => (string) $order->obi_kdmember,
                    'req_request' => $input['request'],
                    'req_tgl_kirim' => $input['tgl_kirim'],
                ]);

                return;
            }

            abort_if($existingRequest === null, 404);

            $updated = $this->shippingRequestQuery($connection, $order)->update([
                'req_request' => $input['request'],
                'req_tgl_kirim' => $input['tgl_kirim'],
                'req_modify_dt' => now(),
            ]);

            abort_unless($updated === 1, 409, 'Request kirim sudah berubah. Muat ulang laporan.');
        });

        $message = $input['mode'] === 'create'
            ? 'Request kirim berhasil ditambahkan.'
            : 'Request kirim berhasil diperbarui.';

        return $this->redirectToReport($returnFilters, $message);
    }

    /**
     * @param  array<string, mixed>  $identifiers
     */
    private function findGiftOrder(array $identifiers): object
    {
        $date = $identifiers['tglpb'];
        $query = PbDateReport::query([
            'tglstart' => $date,
            'tglend' => $date,
            'recid' => 'all',
            'nopb' => $identifiers['nopb'],
            'kdmm' => $identifiers['kdmm'],
        ], DB::getDefaultConnection());

        $order = $query
            ->where('pb_tgl.obi_nopb', $identifiers['nopb'])
            ->where('pb_tgl.obi_notrans', $identifiers['notrans'])
            ->first();

        abort_if($order === null, 404);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $identifiers
     */
    private function findOrder(array $identifiers): object
    {
        $startDate = CarbonImmutable::createFromFormat('!Y-m-d', $identifiers['tglpb']);
        $endDate = $startDate->addDay();
        $order = DB::connection()->table('tbtr_obi_h as order_header')
            ->leftJoin('tbmaster_customer as customer', function (JoinClause $join): void {
                $join->on('customer.cus_kodemember', '=', 'order_header.obi_kdmember')
                    ->where('customer.cus_kodeigr', '=', '1M');
            })
            ->selectRaw("RTRIM(SUBSTR(order_header.obi_nopb, 1, 7), '/') AS pb")
            ->selectRaw('DATE(order_header.obi_tglpb) AS obi_tglpb')
            ->addSelect([
                'order_header.obi_nopb',
                'order_header.obi_notrans',
                'order_header.obi_kdmember',
                'customer.cus_namamember',
            ])
            ->where('order_header.obi_tglpb', '>=', $startDate->toDateTimeString())
            ->where('order_header.obi_tglpb', '<', $endDate->toDateTimeString())
            ->where('order_header.obi_nopb', $identifiers['nopb'])
            ->whereRaw(
                'CAST(order_header.obi_notrans AS NUMERIC) = CAST(? AS NUMERIC)',
                [$identifiers['notrans']],
            )
            ->where('order_header.obi_kdmember', $identifiers['kdmm'])
            ->first();

        abort_if($order === null, 404);

        return $order;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function orderIdentifierRules(): array
    {
        return [
            'tglpb' => ['required', 'date_format:Y-m-d'],
            'nopb' => ['required', 'string', 'max:30'],
            'notrans' => ['required', 'string', 'regex:/^\d{1,12}$/'],
            'kdmm' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrderIdentifiers(Request $request): array
    {
        return $request->validate($this->orderIdentifierRules());
    }

    private function assertGiftCanBeSaved(Connection $connection, object $order): void
    {
        abort_if(
            $order->ads_hadiah === null
                || $order->ads_prdcd === null
                || $order->ads_qty === null,
            404,
        );

        abort_unless(
            is_numeric($order->ads_qty)
                && (float) $order->ads_qty > 0
                && floor((float) $order->ads_qty) === (float) $order->ads_qty,
            409,
            'Kuantitas hadiah pada promo tidak valid.',
        );

        $exists = $connection->table('tmp.hadiah')
            ->whereIn('hdh_nopb', [(string) $order->pb, (string) $order->pb.'B'])
            ->exists();

        abort_if($exists, 409, 'Data hadiah untuk PB ini sudah tersimpan.');
    }

    private function findShippingRequest(Connection $connection, object $order): ?object
    {
        $requests = $this->shippingRequestQuery($connection, $order)->get();

        abort_if($requests->count() > 1, 409, 'Data request kirim memiliki lebih dari satu baris.');

        return $requests->first();
    }

    private function shippingRequestQuery(Connection $connection, object $order): Builder
    {
        return $connection->table('tmp.spi1m_requestkirim')
            ->where('req_nopb', (string) $order->pb)
            ->where('req_tglpb', (string) $order->obi_tglpb)
            ->where('req_kodemm', (string) $order->obi_kdmember);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReturnFilters(Request $request, string $fallbackDate): array
    {
        $request->merge([
            'return_report' => $request->input('return_report') ?? 'pb-proses',
            'return_tglstart' => $request->input('return_tglstart') ?? $fallbackDate,
            'return_tglend' => $request->input('return_tglend') ?? $fallbackDate,
            'return_recid' => $request->input('return_recid') ?? 'all',
            'return_request' => $request->input('return_request') ?? false,
        ]);

        $filters = $request->validate([
            'return_report' => ['required', Rule::in(self::REPORTS)],
            'return_tglstart' => ['required', 'date_format:Y-m-d', 'before_or_equal:return_tglend'],
            'return_tglend' => ['required', 'date_format:Y-m-d', 'after_or_equal:return_tglstart'],
            'return_recid' => ['required', Rule::in(self::RECID_FILTERS)],
            'return_ekspedisi' => ['nullable', Rule::in(['Ambil', 'Indopaket'])],
            'return_pin' => ['nullable', Rule::in(['belumpin', 'sudahpin'])],
            'return_kdmm' => ['nullable', 'string', 'max:20'],
            'return_nopb' => ['nullable', 'string', 'max:30'],
            'return_request' => ['required', 'boolean'],
            'return_search' => ['nullable', 'string', 'max:100'],
            'return_per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'return_page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $returnFilters = [
            'report' => $filters['return_report'],
            'tglstart' => $filters['return_tglstart'],
            'tglend' => $filters['return_tglend'],
            'recid' => $filters['return_recid'],
            'request' => $filters['return_request'],
            'ekspedisi' => $filters['return_ekspedisi'] ?? null,
            'pin' => $filters['return_pin'] ?? null,
            'kdmm' => $filters['return_kdmm'] ?? null,
            'nopb' => $filters['return_nopb'] ?? null,
            'search' => $filters['return_search'] ?? null,
            'per_page' => $filters['return_per_page'] ?? null,
            'page' => $filters['return_page'] ?? null,
        ];

        return array_filter(
            $returnFilters,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function redirectToReport(array $filters, string $message): RedirectResponse
    {
        return redirect()
            ->route('pb.reports.show', $filters)
            ->with('success', $message);
    }
}
