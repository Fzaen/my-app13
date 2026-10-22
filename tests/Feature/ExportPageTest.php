<?php

namespace Tests\Feature;

use App\Exports\SalesReportExport;
use App\Models\Auth\DatabaseUser;
use App\Models\ExportModel\LogisticsReport;
use App\Models\ExportModel\PromotionReport;
use App\Models\ExportModel\PurchaseOrderReport;
use App\Models\ExportModel\ReportCatalog;
use App\Models\ExportModel\SalesReport;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportPageTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_export_page(): void
    {
        $this->get('/data/export/sales')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_export_page_with_sales_and_pb_reports(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get('/data/export/sales')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Data/Export/Export')
                    ->where('reportOptions', ReportCatalog::options())
                    ->etc()
            );
    }

    public function test_authenticated_user_can_download_sales_per_item_xlsx_with_distinct_member_and_supplier_filters(): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $fileName = 'sales-peritem_2026-09-01_2026-09-02.xlsx';

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => 'sales-peritem',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
                'member' => 'GJ8500',
                'supplier' => 'S1483',
            ])
            ->assertOk();

        Excel::assertDownloaded($fileName, function (SalesReportExport $export): bool {
            $query = $export->query();
            $sql = strtolower($query->toSql());
            $bindings = $query->getBindings();

            return $query->getConnection() === DB::connection('spi1m_banjarbaru_sim')
                && str_contains($sql, 'trjd_cus_kodemember')
                && str_contains($sql, 'prd_kodesupplier')
                && in_array('GJ8500', $bindings, true)
                && in_array('S1483', $bindings, true)
                && count($query->columns) === count($export->headings());
        });
    }

    public static function otherSalesReports(): array
    {
        return [
            'sales per item member' => ['sales-peritem-member'],
            'member item detail' => ['exportmember-peritem'],
            'sales item per date' => ['sales-peritem-pertgl'],
            'sales item per month' => ['sales-peritem-perbulan'],
            'sales per member' => ['sales-permember'],
            'sales per member per month' => ['sales-permember-perbulan'],
            'sales per AWB' => ['sales-awb'],
            'sales per receipt' => ['sales-perstruk'],
            'sales armada by member' => ['seles-armada-month-bytglpb-member'],
            'sales armada by month' => ['seles-armada-month-bytglpb'],
            'sales per supplier' => ['sales-persupplier'],
            'sales per department' => ['sales-perdepartement'],
        ];
    }

    public static function purchaseOrderReports(): array
    {
        return [
            'PB per transaction date' => ['pb-pertanggal'],
            'PB item detail' => ['pb-peritem'],
            'PB picking per date' => ['pb-picking-pertanggal'],
            'PB shipping per date' => ['pb-kirim-pertanggal'],
            'PB entry time' => ['pb-jam-masuk'],
            'PB backorder items' => ['pb-bark'],
            'PB handover status' => ['pb-sertim'],
        ];
    }

    public static function promotionReports(): array
    {
        return [
            'active cashback' => ['promo-cb-aktif'],
            'cashback by period' => ['promo-cb-periode'],
            'member MyPoint details' => ['promo-detail-mypoint'],
            'active gift' => ['promo-gift-aktif'],
            'all HO gifts' => ['promo-gift-allho'],
            'gift by period' => ['promo-gift-periode'],
            'restricted items' => ['promo-item-larangan'],
            'MyPoint balance' => ['promo-mypoint'],
            'transaction restrictions' => ['promo-pembatasan'],
            'items without promotion' => ['promo-tidak-ada-promo'],
        ];
    }

    public static function logisticsReports(): array
    {
        return [
            'all BPB items' => ['log-bpb-all'],
            'BPB from ICC' => ['log-bpb-icc'],
            'BPB noodles' => ['log-bpb-nodles'],
            'BPB supplier contact' => ['log-bpb-picsupplier'],
            'freshness and supplier returns' => ['log-kesegaran'],
            'SPI master' => ['log-master-spi'],
            'PB to MD' => ['log-pb-md'],
            'PO by all PLU' => ['log-po-all-plu'],
            'supplier service level' => ['log-sl-supplier'],
            'stock analysis' => ['log-stock'],
            'TAC by period' => ['log-tac'],
        ];
    }

    #[DataProvider('purchaseOrderReports')]
    public function test_authenticated_user_can_download_each_purchase_order_report(string $report): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $fileName = sprintf('%s_2026-09-01_2026-09-02.xlsx', $report);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => $report,
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
            ])
            ->assertOk();

        Excel::assertDownloaded($fileName, function (SalesReportExport $export): bool {
            $query = $export->query();

            return $query->toSql() !== ''
                && count($query->columns) === count($export->headings());
        });
    }

    #[DataProvider('promotionReports')]
    public function test_authenticated_user_can_download_each_promotion_report(string $report): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $fileName = sprintf('%s_2026-09-01_2026-09-02.xlsx', $report);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => $report,
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
                'member' => 'GJ8500',
            ])
            ->assertOk();

        Excel::assertDownloaded($fileName, function (SalesReportExport $export): bool {
            $query = $export->query();

            return $query->getConnection() === DB::connection('spi1m_banjarbaru_sim')
                && $query->toSql() !== ''
                && count($query->columns) === count($export->headings());
        });
    }

    #[DataProvider('logisticsReports')]
    public function test_authenticated_user_can_download_each_logistics_report(string $report): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $fileName = sprintf('%s_2026-09-01_2026-09-30.xlsx', $report);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => $report,
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-30',
                'plu' => '1234560',
                'member' => 'GJ8500',
            ])
            ->assertOk();

        Excel::assertDownloaded($fileName, function (SalesReportExport $export): bool {
            $query = $export->query();

            return $query->getConnection() === DB::connection('spi1m_banjarbaru_sim')
                && $query->toSql() !== ''
                && count($query->columns) === count($export->headings());
        });
    }

    public function test_stock_report_uses_the_latest_three_completed_months(): void
    {
        $this->travelTo('2026-10-14 09:00:00');

        $query = LogisticsReport::forExport('log-stock', [
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-30',
        ], 'sqlite');
        $sql = $query->toSql();

        $this->assertStringContainsString('RSL_QTY_07 + RSL_QTY_08 + RSL_QTY_09', $sql);
        $this->assertStringContainsString("SLV_PERIODE = '202609'", $sql);
        $this->assertStringContainsString("pid = '092026'", $sql);
        $this->assertStringNotContainsString('202309', $sql);
        $this->assertSame(89, count($query->columns));
        $this->travelBack();
    }

    public function test_logistics_report_binds_dates_and_supplier_filter_values(): void
    {
        $maliciousSupplier = "S1483' OR 1=1 --";
        $query = LogisticsReport::forExport('log-tac', [
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-30',
            'supplier' => $maliciousSupplier,
        ], 'sqlite');

        $this->assertStringNotContainsString($maliciousSupplier, $query->toSql());
        $this->assertContains('2026-09-01', $query->getBindings());
        $this->assertContains('2026-09-30', $query->getBindings());
        $this->assertContains($maliciousSupplier, $query->getBindings());
    }

    #[DataProvider('otherSalesReports')]
    public function test_authenticated_user_can_download_each_additional_sales_report(string $report): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $fileName = sprintf('%s_2026-09-01_2026-09-02.xlsx', $report);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => $report,
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
                'member' => 'GJ8500',
            ])
            ->assertOk();

        Excel::assertDownloaded($fileName, function (SalesReportExport $export): bool {
            $query = $export->query();

            return $query->getConnection() === DB::connection('spi1m_banjarbaru_sim')
                && $query->toSql() !== ''
                && count($query->columns) === count($export->headings());
        });
    }

    public function test_sales_export_writes_columns_with_matching_headings_as_text(): void
    {
        $export = new SalesReportExport('sales-peritem', [], 'sqlite');
        $worksheet = (new Spreadsheet)->getActiveSheet();
        $textHeadings = [
            'PLU',
            'PRDCD',
            'PRD_PRDCD',
            'KTP',
            'DEPT',
            'KATB',
            'DEP',
            'TRJD_PRDCD',
            'DIV',
            'MEMBER',
            'NOPB',
            'NOSTRUK',
            'KODESUPPLIER',
            'PB',
            'NOTRANS',
            'OBI_NOSJ',
            'OBI_COD_PINCODE',
            'POBI_NOCONTAINER',
            'OBI_URUT_PICK',
        ];

        foreach (range('A', 'S') as $index => $column) {
            $worksheet->setCellValue($column.'1', 'EXPORT_'.$textHeadings[$index].'_CODE');
            $cell = $worksheet->getCell($column.'2');

            $this->assertTrue($export->bindValue($cell, '001234'));
            $cell = $worksheet->getCell($column.'2');
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame('001234', $cell->getValue());
        }

        $worksheet->setCellValue('T1', 'QTY');
        $numericCell = $worksheet->getCell('T2');

        $export->bindValue($numericCell, 123);
        $numericCell = $worksheet->getCell('T2');

        $this->assertSame(DataType::TYPE_NUMERIC, $numericCell->getDataType());
        $this->assertSame(123, $numericCell->getValue());
    }

    public function test_mypoint_export_preserves_transaction_and_phone_identifiers_as_text(): void
    {
        $export = new SalesReportExport('promo-detail-mypoint', [], 'sqlite');
        $worksheet = (new Spreadsheet)->getActiveSheet();

        foreach (['TRX', 'NO_HP', 'TOKO'] as $index => $heading) {
            $column = chr(ord('A') + $index);
            $worksheet->setCellValue($column.'1', $heading);
            $cell = $worksheet->getCell($column.'2');

            $this->assertTrue($export->bindValue($cell, '001234'));
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame('001234', $cell->getValue());
        }
    }

    public function test_sales_export_rejects_an_end_date_before_the_start_date(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => 'sales-peritem',
                'tglstart' => '2026-09-02',
                'tglend' => '2026-09-01',
            ])
            ->assertSessionHasErrors(['tglstart', 'tglend']);
    }

    public function test_sales_export_rejects_a_report_that_is_not_supported(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => 'not-a-sales-report',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
            ])
            ->assertSessionHasErrors(['laporan']);
    }

    public function test_member_item_detail_requires_a_member_code(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => 'exportmember-peritem',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
            ])
            ->assertSessionHasErrors(['member']);
    }

    public function test_mypoint_detail_export_requires_a_member_code(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => 'promo-detail-mypoint',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
            ])
            ->assertSessionHasErrors(['member']);
    }

    public function test_mypoint_report_binds_date_and_member_filters_as_values(): void
    {
        $query = PromotionReport::forExport('promo-mypoint', [
            'tglstart' => '2026-09-01',
            'tglend' => '2026-09-30',
            'member' => 'GJ8500',
        ], 'sqlite');

        $this->assertContains('2026-09-01', $query->getBindings());
        $this->assertContains('2026-09-30', $query->getBindings());
        $this->assertContains('GJ8500', $query->getBindings());
        $this->assertSame(11, count($query->columns));
    }

    public function test_pb_item_export_binds_the_plu_filter_as_a_value(): void
    {
        Excel::fake();

        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
        $maliciousPlu = "123456') OR 1=1 --";

        $this->actingAs($user)
            ->withSession(['selected_database' => 'spi1m_banjarbaru_sim'])
            ->post(route('sales.export.download'), [
                'laporan' => 'pb-peritem',
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
                'plu' => $maliciousPlu,
            ])
            ->assertOk();

        Excel::assertDownloaded('pb-peritem_2026-09-01_2026-09-02.xlsx', function (SalesReportExport $export) use ($maliciousPlu): bool {
            $query = $export->query();

            return ! str_contains($query->toSql(), $maliciousPlu)
                && in_array($maliciousPlu, $query->getBindings(), true);
        });
    }

    public function test_sales_report_query_uses_the_currently_selected_connection(): void
    {
        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('sqlite');

        try {
            $query = SalesReport::salesPerItem([
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
            ]);

            $this->assertSame(DB::connection('sqlite'), $query->getConnection());
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }

    public function test_purchase_order_report_query_uses_the_currently_selected_connection(): void
    {
        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('sqlite');

        try {
            $query = PurchaseOrderReport::forExport('pb-pertanggal', [
                'tglstart' => '2026-09-01',
                'tglend' => '2026-09-02',
            ]);

            $this->assertSame(DB::connection('sqlite'), $query->getConnection());
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }
}
