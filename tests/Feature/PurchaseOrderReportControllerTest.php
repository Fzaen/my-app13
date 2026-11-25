<?php

namespace Tests\Feature;

use App\Exports\PbDateReportExport;
use App\Exports\SalesReportExport;
use App\Models\Auth\DatabaseUser;
use App\Models\ExportModel\PbDateReport;
use App\Models\ExportModel\PurchaseOrderReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseOrderReportControllerTest extends TestCase
{
    private const DATABASE_SITE = 'spi1m_banjarbaru_sim';

    public function test_guest_is_redirected_to_login_from_a_pb_report(): void
    {
        $this->get(route('pb.reports.show', ['report' => 'pb-proses']))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_the_full_report_copy_endpoint(): void
    {
        $this->get(route('pb.reports.copy', ['report' => 'pb-proses']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_a_pb_report_with_database_pagination(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-07',
                'search' => 'TEST-PB',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Report')
                    ->where('report', 'pb-proses')
                    ->where('filters.tglstart', '2026-09-01')
                    ->where('filters.tglend', '2026-09-07')
                    ->where('filters.recid', 'proses')
                    ->where('maxPeriodMonths', 6)
                    ->where('maxExportPeriodMonths', 2)
                    ->where('filters.search', 'TEST-PB')
                    ->where('rows.per_page', 25)
                    ->has('rows.data')
                    ->etc()
            );
    }

    public function test_unknown_pb_report_returns_not_found(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', ['report' => 'pb-not-supported']))
            ->assertNotFound();
    }

    public function test_removed_pb_item_h7_report_returns_not_found(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', ['report' => 'pb-peritem']))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_pb_item_detail_endpoints(): void
    {
        $this->get(route('pb.items.details', [
            'tglpb' => '2026-09-10',
            'pb' => '123456',
        ]))->assertRedirect(route('login'));

        $this->get(route('pb.items.location', [
            'tglpb' => '2026-09-10',
            'pb' => '123456',
        ]))->assertRedirect(route('login'));
    }

    public function test_pb_item_detail_endpoints_reject_invalid_pb_numbers(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.items.details', [
                'tglpb' => '2026-09-10',
                'pb' => '123ABC',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pb']);
    }

    public function test_pb_report_rejects_an_invalid_date_range(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'tglstart' => '2026-09-08',
                'tglend' => '2026-09-07',
            ]))
            ->assertSessionHasErrors(['tglstart', 'tglend']);
    }

    public function test_pb_process_defaults_to_last_month_and_process_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 12:00:00'));

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.show', ['report' => 'pb-proses']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Report')
                    ->where('filters.tglstart', '2026-10-01')
                    ->where('filters.tglend', '2026-11-13')
                    ->where('filters.recid', 'proses')
                    ->etc()
            );
    }

    public function test_pb_kirim_list_defaults_to_indopaket_and_spi_pickups(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.show', [
                'report' => 'pb-kirim-list',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-07',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Report')
                    ->where('filters.recid', 'all')
                    ->where('filters.ekspedisi', 'kirim')
                    ->has('rows.data')
                    ->etc()
            );
    }

    public function test_pb_process_page_can_jump_to_a_requested_page(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-07',
                'page' => 3,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Report')
                    ->where('rows.current_page', 3)
                    ->etc()
            );
    }

    public function test_pb_process_page_rejects_periods_longer_than_six_months(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'tglstart' => '2026-05-01',
                'tglend' => '2026-11-01',
            ]))
            ->assertSessionHasErrors([
                'tglend' => 'Rentang laporan ini maksimal 6 bulan. Pilih tanggal yang lebih pendek.',
            ]);
    }

    public function test_pb_process_export_rejects_periods_longer_than_two_months(): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.export', [
                'report' => 'pb-proses',
                'tglstart' => '2026-06-01',
                'tglend' => '2026-08-01',
            ]))
            ->assertSessionHasErrors([
                'tglend' => 'Rentang ekspor laporan ini maksimal 2 bulan. Pilih tanggal yang lebih pendek.',
            ]);
    }

    public function test_pb_process_export_accepts_last_month_start_through_today(): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.export', [
                'report' => 'pb-proses',
                'tglstart' => '2026-11-01',
                'tglend' => '2026-11-14',
            ]))
            ->assertOk();

        Excel::assertDownloaded('pb-proses_2026-11-01_2026-11-14.xlsx');
    }

    public function test_pb_excel_export_uses_pin_filter_and_ignores_legacy_expedition_filter(): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $search = "PB-TEST%' OR 1=1 --";

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.export', [
                'report' => 'pb-proses',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
                'recid' => 'proses',
                'pin' => 'ambil',
                'ekspedisi' => 'Indopaket',
                'search' => $search,
            ]))
            ->assertOk();

        Excel::assertDownloaded(
            'pb-proses_2026-09-01_2026-09-02.xlsx',
            function (PbDateReportExport $export) use ($search): bool {
                $query = $export->query();

                return $query->getConnection()->getName() === self::DATABASE_SITE
                    && str_contains(strtolower($query->toSql()), 'ilike ?')
                    && ! str_contains($query->toSql(), $search)
                    && in_array('%'.$search.'%', $query->getBindings(), true)
                    && in_array('%Ambil%', $query->getBindings(), true)
                    && ! in_array('Indopaket%', $query->getBindings(), true)
                    && array_diff(['1', '2', '3', '4', '5'], $query->getBindings()) === [];
            },
        );
    }

    public function test_pb_date_excel_export_generates_a_workbook_for_a_large_struk_range(): void
    {
        $sourceRows = DB::connection(self::DATABASE_SITE)
            ->table('tbtr_obi_h')
            ->where('obi_tglpb', '>=', '2026-06-01')
            ->where('obi_tglpb', '<', '2026-07-01')
            ->where('obi_recid', '6')
            ->count();
        $this->assertGreaterThan(500, $sourceRows);

        $reportQueryCount = 0;
        DB::connection(self::DATABASE_SITE)->listen(
            function (QueryExecuted $query) use (&$reportQueryCount): void {
                if (str_contains($query->sql, 'WITH filtered_obi AS')) {
                    $reportQueryCount++;
                }
            },
        );

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $response = $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.export', [
                'report' => 'pb-proses',
                'tglstart' => '2026-06-01',
                'tglend' => '2026-06-30',
                'recid' => '6',
            ]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString(
            'pb-proses_2026-06-01_2026-06-30.xlsx',
            $response->headers->get('content-disposition'),
        );
        $this->assertGreaterThan(0, (int) $response->headers->get('content-length'));
        $this->assertSame(1, $reportQueryCount);
    }

    public function test_pb_report_rejects_unsupported_filter_values(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'recid' => "1' OR 1=1 --",
            ]))
            ->assertSessionHasErrors(['recid']);
    }

    public function test_pb_process_item_rejects_an_unsupported_item_status(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses-item',
                'ket_item' => "PICKING' OR 1=1 --",
            ]))
            ->assertSessionHasErrors(['ket_item']);
    }

    public function test_pb_report_rejects_an_unsupported_page_size(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'per_page' => 1000,
            ]))
            ->assertSessionHasErrors(['per_page']);
    }

    #[DataProvider('statusFilters')]
    public function test_pb_report_keeps_the_selected_status_filter(string $status): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.show', [
                'report' => 'pb-proses',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-01',
                'recid' => $status,
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Report')
                    ->where('filters.recid', $status)
                    ->has('rows.data')
                    ->etc()
            );
    }

    public function test_pb_date_report_returns_the_legacy_detail_columns_for_existing_orders(): void
    {
        $filters = [
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-01',
            'recid' => 'all',
        ];
        $row = PbDateReport::query($filters, self::DATABASE_SITE)->first();

        $this->assertNotNull($row);
        $this->assertArrayHasKey('pin', (array) $row);
        $this->assertArrayHasKey('ket_pin', (array) $row);
        $this->assertArrayHasKey('ttl_order', (array) $row);
        $this->assertArrayHasKey('awi_cost', (array) $row);
        $this->assertArrayHasKey('ads_hadiah', (array) $row);
        $this->assertArrayHasKey('jam_dsp', (array) $row);
        $this->assertArrayHasKey('req', (array) $row);

        $export = new PbDateReportExport($filters, self::DATABASE_SITE);

        $this->assertCount(23, $export->headings());
        $this->assertCount(23, $export->map($row));
    }

    public function test_pb_date_query_includes_tms_pin_status_and_oldest_pb_ordering(): void
    {
        $query = PbDateReport::query([
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-07',
            'recid' => 'proses',
        ], self::DATABASE_SITE);
        $sql = $query->toSql();

        $this->assertStringContainsString(
            "WHEN st.sti_pin IS NOT NULL AND st.sti_receivername = '' THEN 'MASIH TMS'",
            $sql,
        );
        $this->assertStringContainsString(
            "WHEN st.sti_pin IS NOT NULL AND st.sti_receivername != '' THEN 'SUDAH INPUT pin'",
            $sql,
        );
        $this->assertStringContainsString('ind.ads_kode', $sql);
        $this->assertStringContainsString('COUNT(*) AS LOYALTY', $sql);
        $this->assertStringContainsString('ly_tgl_kirim IS NULL', $sql);
        $this->assertStringContainsString(
            'order by "pb_tgl"."tgl" asc, "pb_tgl"."pb" asc',
            $sql,
        );
    }

    #[DataProvider('pinFilters')]
    public function test_pb_date_query_applies_the_selected_pin_filter(
        string $pin,
        array $expectedBindings,
    ): void {
        $query = PbDateReport::query([
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-07',
            'recid' => 'proses',
            'pin' => $pin,
        ], self::DATABASE_SITE);

        foreach ($expectedBindings as $expectedBinding) {
            $this->assertContains($expectedBinding, $query->getBindings());
        }
    }

    public function test_pb_kirim_list_query_includes_indopaket_and_spi_pickups(): void
    {
        $filters = [
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-07',
            'recid' => 'all',
            'ekspedisi' => 'kirim',
        ];
        $dateFilters = [
            'tglstart' => $filters['tglstart'],
            'tglend' => $filters['tglend'],
        ];
        $dateReportQuery = PbDateReport::query($filters, self::DATABASE_SITE);
        $exportQuery = PurchaseOrderReport::forExport(
            'pb-kirim-list',
            $dateFilters,
            self::DATABASE_SITE,
        );

        foreach ([$dateReportQuery, $exportQuery] as $query) {
            $bindings = $query->getBindings();

            $this->assertContains('%Indopaket%', $bindings);
            $this->assertContains('%Ambil%', $bindings);
            $this->assertContains('5', $bindings);
            $this->assertContains('1', $bindings);
        }
    }

    public function test_pb_date_query_omits_the_unused_campaign_promo_join_and_column(): void
    {
        $query = PbDateReport::query([
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-07',
            'recid' => 'proses',
        ], self::DATABASE_SITE, 'CKKN3');
        $sql = strtolower($query->toSql());

        $this->assertStringNotContainsString('prmc', $sql);
        $this->assertStringNotContainsString('kdpromo', $sql);
    }

    public function test_pb_process_item_table_applies_status_and_search_to_item_rows(): void
    {
        $query = PurchaseOrderReport::forTable('pb-proses-item', [
            'tglstart' => '2026-09-10',
            'tglend' => '2026-09-10',
            'recid' => '2',
        ], self::DATABASE_SITE, 'TEST-PLU');

        $this->assertStringContainsString('pobi_qty', $query->toSql());
        $this->assertStringContainsString('obi_recid', $query->toSql());
        $this->assertStringContainsString(
            "WHEN detail.obi_recid IS NULL THEN 'PICKING'",
            $query->toSql(),
        );
        $this->assertStringContainsString(
            "WHEN detail.obi_recid = '1' THEN 'PICKING SCAN 0'",
            $query->toSql(),
        );
        $this->assertStringContainsString(
            "WHEN detail.obi_recid = '2' THEN 'BATAL'",
            $query->toSql(),
        );
        $this->assertStringContainsString(
            "WHEN order_header.obi_recid LIKE 'B%' THEN 'BATAL'",
            $query->toSql(),
        );
        $this->assertStringNotContainsString(
            "WHEN detail.obi_recid = '1' THEN 'PICKING'",
            $query->toSql(),
        );
        $this->assertStringContainsString('obi_qty_hitungulang', $query->toSql());
        $this->assertStringContainsString('obi_qtyba', $query->toSql());
        $this->assertStringNotContainsString('tbmaster_customer', $query->toSql());
        $this->assertStringContainsString(
            'detail.obi_tgltrans::date = order_header.obi_tgltrans::date',
            $query->toSql(),
        );
        $this->assertSame(1, substr_count(strtolower($query->toSql()), 'order by'));
        $this->assertTrue(in_array('2', $query->getBindings(), true));
        $this->assertTrue(in_array('%TEST-PLU%', $query->getBindings(), true));
    }

    #[DataProvider('processItemStatuses')]
    public function test_pb_process_item_filter_targets_the_matching_item_recid(
        string $status,
        string $expectedPredicate,
        ?string $expectedBinding,
    ): void {
        $query = PurchaseOrderReport::forTable('pb-proses-item', [
            'tglstart' => '2026-09-10',
            'tglend' => '2026-09-10',
            'recid' => 'all',
            'ket_item' => $status,
        ], self::DATABASE_SITE);

        $this->assertStringContainsString($expectedPredicate, $query->toSql());

        if ($expectedBinding !== null) {
            $bindings = $query->getBindings();
            $this->assertSame($expectedBinding, end($bindings));
        }
    }

    public function test_pb_location_preview_query_joins_store_locations_to_item_rows(): void
    {
        $query = PurchaseOrderReport::locationPreview([
            'tglstart' => '2026-09-10',
            'tglend' => '2026-09-10',
            'nopb' => '123456',
            'recid' => 'all',
        ], self::DATABASE_SITE);

        $this->assertStringContainsString('tbmaster_lokasi', $query->toSql());
        $this->assertStringContainsString('lks_koderak', $query->toSql());
        $this->assertStringContainsString('123456', implode('|', $query->getBindings()));
    }

    public function test_pb_process_item_export_uses_selected_status_and_search_filters(): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $search = 'TEST-PLU';

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.export', [
                'report' => 'pb-proses-item',
                'tglstart' => '2026-09-10',
                'tglend' => '2026-09-10',
                'recid' => '2',
                'ket_item' => 'PICKING SCAN 0',
                'search' => $search,
            ]))
            ->assertOk();

        Excel::assertDownloaded(
            'pb-proses-item_2026-09-10_2026-09-10.xlsx',
            function (SalesReportExport $export) use ($search): bool {
                $query = $export->query();

                return $query->getConnection()->getName() === self::DATABASE_SITE
                    && str_contains($query->toSql(), 'obi_recid')
                    && str_contains($query->toSql(), '"detail"."obi_recid" = ?')
                    && in_array('1', $query->getBindings(), true)
                    && in_array('2', $query->getBindings(), true)
                    && in_array('%'.$search.'%', $query->getBindings(), true)
                    && $export->headings() === [
                        'PB', 'TGL PB', 'NO TRANS', 'PLU', 'DESK', 'QTY ORDER', 'QTY REAL',
                        'QTY PACKING', 'SELISIH', 'PICKER', 'PACKER', 'HARGA', 'NO KOLI',
                        'JAM PICK', 'JAM SCAN', 'KET', 'ITEM', 'QTY HITUNG ULANG', 'QTY BA',
                    ];
            },
        );
    }

    public static function tableReports(): array
    {
        return [
            'PB in process' => ['pb-proses'],
            'PB process item detail' => ['pb-proses-item'],
            'PB shipping list' => ['pb-kirim-list'],
            'PB without PIN' => ['pb-belum-pin'],
            'PB pickup' => ['pb-ambil-spi'],
            'PB shipping requests' => ['pb-request-kirim'],
        ];
    }

    public static function statusFilters(): array
    {
        return [
            'all statuses' => ['all'],
            'PB process' => ['proses'],
            'not sent' => ['null'],
            'picking' => ['1'],
            'packing' => ['2'],
            'ready for DSP' => ['3'],
            'DSP' => ['5'],
            'receipt' => ['6'],
            'cancelled' => ['B'],
        ];
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function pinFilters(): array
    {
        return [
            'belum PIN' => ['belumpin', ['BELUM INPUT pin', 'SUDAH pin B 1x']],
            'sudah input PIN' => ['sudahpin', ['SUDAH INPUT pin', 'SUDAH INPUT pin RECREATE']],
            'masih TMS' => ['tms', ['MASIH TMS']],
            'PIN B' => ['pinb', ['SUDAH pin B 1x', 'SUDAH pin B 2x']],
            'Indopaket delivery' => ['indopaket', ['%Indopaket%']],
            'ambil di SPI' => ['ambil', ['%Ambil%']],
        ];
    }

    public static function processItemStatuses(): array
    {
        return [
            'picking' => ['PICKING', '"detail"."obi_recid" is null', null],
            'cancelled item' => ['BATAL', '"detail"."obi_recid" = ?', '2'],
            'picking scan zero' => ['PICKING SCAN 0', '"detail"."obi_recid" = ?', '1'],
        ];
    }

    #[DataProvider('tableReports')]
    public function test_pb_menu_report_renders_its_report_page(string $report): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('pb.reports.show', [
                'report' => $report,
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
                'search' => 'TEST',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Report')
                    ->where('report', $report)
                    ->has('rows.data')
                    ->etc()
            );
    }
}
