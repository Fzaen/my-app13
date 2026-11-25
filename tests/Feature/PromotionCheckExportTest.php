<?php

namespace Tests\Feature;

use App\Exports\PromotionCheckTabExport;
use App\Models\Auth\DatabaseUser;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromotionCheckExportTest extends TestCase
{
    private const DATABASE_SITE = 'spi1m_banjarbaru_sim';

    public function test_guest_is_redirected_from_promo_excel_export(): void
    {
        $this->get(route('info.promo.export', [
            'tab' => 'descriptions',
            'kode' => 'GLF73',
        ]))->assertRedirect(route('login'));
    }

    public function test_promo_excel_export_rejects_an_unknown_tab(): void
    {
        $this->actingAs((new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']))
            ->getJson(route('info.promo.export', [
                'tab' => 'unknown',
                'kode' => 'GLF73',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tab']);
    }

    public function test_promo_tab_export_generates_a_valid_xlsx_file(): void
    {
        $rows = collect([
            (object) [
                'promotion_type' => 'GIFT',
                'promo_code' => 'GLF73',
                'promo_name' => 'Gift Promo',
                'start_date' => '25-NOV-26',
                'end_date' => '30-NOV-26',
                'mechanism' => 'HADIAH',
                'value' => 1,
                'minimum' => 10,
                'created_by' => 'SIM-TEST',
            ],
        ]);
        $xlsx = Excel::raw(new PromotionCheckTabExport($rows, 'descriptions'), ExcelFormat::XLSX);

        $this->assertStringStartsWith('PK', $xlsx);
    }

    #[DataProvider('promoTabs')]
    public function test_authenticated_user_can_download_each_promo_tab_as_excel(
        string $tab,
        array $headings,
        array $tables,
    ): void {
        Excel::fake();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $queries = DB::connection(self::DATABASE_SITE)->pretend(function () use ($tab, $user): void {
            $this->actingAs($user)
                ->withSession(['selected_database' => self::DATABASE_SITE])
                ->get(route('info.promo.export', [
                    'tab' => $tab,
                    'kode' => 'GLF73',
                ]))
                ->assertOk();
        });

        $promotionQuery = collect($queries)->first(
            fn (array $query): bool => str_contains(strtolower($query['query']), $tables[0]),
        );

        $this->assertNotNull($promotionQuery);
        $sql = strtolower($promotionQuery['query']);
        $this->assertStringContainsString('glf73', $sql);

        foreach ($tables as $table) {
            $this->assertStringContainsString($table, $sql);
        }

        Excel::assertDownloaded(
            "detail-promo-{$tab}.xlsx",
            fn (PromotionCheckTabExport $export): bool => $export->headings() === $headings,
        );
    }

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function promoTabs(): array
    {
        return [
            'descriptions' => [
                'descriptions',
                ['Jenis', 'Kode', 'Nama Promo', 'Mulai', 'Selesai', 'Mekanisme', 'Nilai / Hadiah', 'Minimum', 'Dibuat Oleh'],
                ['tbtr_cashback_hdr', 'tbtr_gift_hdr'],
            ],
            'recipients' => [
                'recipients',
                ['Jenis', 'Member', 'Nama', 'Kode Promo', 'No. Transaksi', 'No. PB', 'Tanggal', 'Hadiah', 'Nilai', 'Kasir'],
                ['m_promosi_h', 'm_gift_h'],
            ],
            'registered' => [
                'registered',
                ['Jenis', 'Status', 'Member', 'Nama', 'Kode Promo'],
                ['tbtr_cashback_member', 'tbtr_gift_member'],
            ],
            'details' => [
                'details',
                ['Jenis', 'PLU', 'Deskripsi', 'Nilai', 'Kelipatan', 'Minimum', 'Maksimum', 'Maks. Member / Hari', 'Alokasi', 'Kode Promo'],
                ['tbtr_cashback_dtl', 'tbtr_gift_dtl'],
            ],
        ];
    }
}
