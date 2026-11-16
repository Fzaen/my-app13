<?php

namespace Tests\Feature;

use App\Exports\PbDateReportExport;
use App\Models\Auth\DatabaseUser;
use App\Models\ExportModel\PbDateReport;
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

    public function test_pb_excel_export_keeps_search_as_a_bound_filter(): void
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
                'recid' => '3',
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
                    && in_array('Indopaket%', $query->getBindings(), true)
                    && in_array('3', $query->getBindings(), true);
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

    public static function tableReports(): array
    {
        return [
            'PB in process' => ['pb-proses'],
            'PB item detail' => ['pb-peritem'],
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
