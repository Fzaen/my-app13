<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SlReportControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const DATABASE_SITE = 'spi1m_banjarbaru_sim';

    public function test_picker_report_filters_delivery_dates_and_calculates_picker_summary(): void
    {
        $this->createReportTables();
        $this->insertHeader('PB-1', 'TR-1', '2026-06-10 09:00:00', '2026-06-10 14:00:00', '3');
        $this->insertDetail('TR-1', '2026-06-10 09:00:00', '1234560', 'PICKER-A', 2, 1, 100);
        $this->insertDetail('TR-1', '2026-06-10 09:00:00', '1234570', 'PICKER-A', 0, 0, 50);
        $this->insertHeader('PB-2', 'TR-2', '2026-06-11 09:00:00', '2026-06-11 14:00:00', '3');
        $this->insertDetail('TR-2', '2026-06-11 09:00:00', '1234570', 'PICKER-B', 1, 1, 50);

        $this->actingAs($this->authenticatedUser())
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('sl.picker', [
                'tglstart' => '2026-06-10',
                'tglend' => '2026-06-10',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Sl/Picker')
                    ->where('rows.0.obi_picker', 'PICKER-A')
                    ->where('rows.0.jml_pb', 1)
                    ->where('rows.0.jml_item', 2)
                    ->where('rows.0.item_sebagian', 1)
                    ->where('rows.0.item_terpicking', 0)
                    ->where('rows.0.item_nol', 1)
                    ->where('rows.0.rph_pb', fn (mixed $value): bool => (float) $value === 200.0)
                    ->where('filters.tglstart', '2026-06-10')
                    ->where('filters.tglend', '2026-06-10')
                    ->etc()
            );
    }

    public function test_picker_details_return_only_the_selected_picker_and_product_information(): void
    {
        $this->createReportTables();
        $this->insertHeader('PB-1', 'TR-1', '2026-06-10 09:00:00', '2026-06-10 14:00:00', '3');
        $this->insertDetail('TR-1', '2026-06-10 09:00:00', '1234560', 'PICKER-A', 2, 2, 100);
        for ($pickOrder = 2; $pickOrder <= 51; $pickOrder++) {
            $this->insertDetail(
                'TR-1',
                '2026-06-10 09:00:00',
                '1234560',
                'PICKER-A',
                2,
                2,
                100,
                0,
                0,
                $pickOrder,
            );
        }
        DB::table('tbmaster_prodmast')->insert([
            'prd_prdcd' => '1234560',
            'prd_deskripsipanjang' => 'Produk Test',
            'prd_kodedivisi' => '1',
            'prd_flagigr' => 'Y',
            'prd_flagidm' => 'N',
            'prd_flagomi' => 'N',
            'prd_flagobi' => 'N',
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->getJson(route('sl.picker.details', [
                'tglstart' => '2026-06-10',
                'tglend' => '2026-06-10',
                'picker' => 'PICKER-A',
                'unassigned' => '0',
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('data.0.obi_nopb', 'PB-1')
            ->assertJsonPath('data.0.plu', '123456')
            ->assertJsonPath('data.0.prd_deskripsipanjang', 'Produk Test')
            ->assertJsonPath('data.0.flag_produk', 'IGR_ONLY')
            ->assertJsonPath('data.0.ket_picking', 'TERPICKING');

        $this->assertNotNull($response->json('next_page_url'));
    }

    public function test_picker_filter_is_treated_as_a_literal_value(): void
    {
        $this->createReportTables();
        $this->insertHeader('PB-1', 'TR-1', '2026-06-10 09:00:00', '2026-06-10 14:00:00', '3');
        $this->insertDetail('TR-1', '2026-06-10 09:00:00', '1234560', 'PICKER-A', 2, 2, 100);

        $this->actingAs($this->authenticatedUser())
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->getJson(route('sl.picker.details', [
                'tglstart' => '2026-06-10',
                'tglend' => '2026-06-10',
                'picker' => "PICKER-A' OR 1=1 --",
                'unassigned' => '0',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unassigned_picker_details_include_rows_without_a_picker_name(): void
    {
        $this->createReportTables();
        $this->insertHeader('PB-1', 'TR-1', '2026-06-10 09:00:00', '2026-06-10 14:00:00', '3');
        $this->insertDetail('TR-1', '2026-06-10 09:00:00', '1234560', null, 2, 1, 100);

        $this->actingAs($this->authenticatedUser())
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->getJson(route('sl.picker.details', [
                'tglstart' => '2026-06-10',
                'tglend' => '2026-06-10',
                'picker' => '',
                'unassigned' => '1',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.obi_picker', '- TANPA PICKER -')
            ->assertJsonPath('data.0.ket_picking', 'SEBAGIAN');
    }

    public function test_picker_report_rejects_a_reversed_date_range(): void
    {
        $this->actingAs($this->authenticatedUser())
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('sl.picker', [
                'tglstart' => '2026-06-11',
                'tglend' => '2026-06-10',
            ]))
            ->assertSessionHasErrors('tglend');
    }

    public function test_picking_report_shows_status_buckets_and_monthly_service_level(): void
    {
        $this->createReportTables();
        $today = DB::selectOne('SELECT CURRENT_DATE AS today')->today;
        $todayAtNine = "{$today} 09:00:00";

        $this->insertHeader(
            'PB-SL-1',
            'TR-SL-1',
            $todayAtNine,
            "{$today} 17:00:00",
            '5',
            [
                'obi_kdmember' => 'MEM-1',
                'obi_maxsertim' => "{$today} 14:00:00",
                'obi_tipebayar' => 'Kartu',
                'obi_draftstruk' => "{$today} 13:00:00",
                'obi_kdekspedisi' => 'NEXTDAY',
            ],
        );
        $this->insertDetail('TR-SL-1', $todayAtNine, '1234560', 'PICKER-A', 2, 2, 100, 11, 5);
        $this->insertHeader(
            'PB-SL-2',
            'TR-SL-2',
            $todayAtNine,
            "{$today} 17:00:00",
            'B1',
            [
                'obi_kdmember' => 'MEM-2',
                'obi_maxsertim' => "{$today} 14:00:00",
                'obi_sendpick' => "{$today} 10:00:00",
                'obi_tipebayar' => 'COD',
                'obi_alasanbtl' => 'STOCK KOSONG',
            ],
        );
        DB::table('payment_klikigr')->insert([
            'kode_member' => 'MEM-2',
            'no_pb' => 'PB-SL-2',
            'no_trans' => 'TR-SL-2',
            'tgl_bayar' => "{$today} 09:00:00",
        ]);

        $this->actingAs($this->authenticatedUser())
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get(route('sl.picking'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Sl/Picking')
                    ->where('statusRows.0.status', 'SIAP STRUK')
                    ->where('statusRows.0.td2', 1)
                    ->where('statusRows.1.status', 'BATAL - STOCK KOSONG')
                    ->where('statusRows.1.btl', 1)
                    ->where('slRows.0.jam14_jml_pb', 1)
                    ->where('slRows.0.jam14_jml_item', fn (mixed $value): bool => (int) $value === 1)
                    ->where('slRows.0.jam14_sudah', 1)
                    ->where('slRows.0.jam14_sesuai', 1)
                    ->where('slRows.0.jam14_telat', 0)
                    ->where('slRows.0.jam14_rph', fn (mixed $value): bool => (float) $value === 212.0)
                    ->etc()
            );
    }

    public function test_sl_reports_redirect_unauthenticated_visitors_to_login(): void
    {
        $this->get(route('sl.picker'))->assertRedirect(route('login'));
    }

    private function createReportTables(): void
    {
        Schema::create('tbtr_obi_h', function (Blueprint $table): void {
            $table->temporary();
            $table->timestamp('obi_tglpb');
            $table->timestamp('obi_tgltrans');
            $table->string('obi_nopb');
            $table->string('obi_notrans');
            $table->string('obi_kdmember')->nullable();
            $table->string('obi_recid')->nullable();
            $table->timestamp('obi_maxdeliverytime')->nullable();
            $table->timestamp('obi_maxsertim')->nullable();
            $table->timestamp('obi_sendpick')->nullable();
            $table->string('obi_kdekspedisi')->nullable();
            $table->string('obi_alasanbtl')->nullable();
            $table->string('obi_tipebayar')->nullable();
            $table->timestamp('obi_draftstruk')->nullable();
        });

        Schema::create('tbtr_obi_d', function (Blueprint $table): void {
            $table->temporary();
            $table->timestamp('obi_tgltrans');
            $table->string('obi_notrans');
            $table->string('obi_prdcd')->nullable();
            $table->string('obi_picker')->nullable();
            $table->string('obi_recid')->nullable();
            $table->decimal('obi_qtyorder', 12, 2)->nullable();
            $table->decimal('obi_qtyrealisasi', 12, 2)->nullable();
            $table->decimal('obi_hargasatuan', 12, 2)->nullable();
            $table->decimal('obi_ppn', 12, 2)->nullable();
            $table->decimal('obi_diskon', 12, 2)->nullable();
            $table->integer('obi_urut_pick')->nullable();
            $table->integer('obi_urut_scan')->nullable();
        });

        Schema::create('tbmaster_prodmast', function (Blueprint $table): void {
            $table->temporary();
            $table->string('prd_prdcd');
            $table->string('prd_deskripsipanjang')->nullable();
            $table->string('prd_kodedivisi')->nullable();
            $table->string('prd_flagigr')->nullable();
            $table->string('prd_flagidm')->nullable();
            $table->string('prd_flagomi')->nullable();
            $table->string('prd_flagobi')->nullable();
        });

        Schema::create('payment_klikigr', function (Blueprint $table): void {
            $table->temporary();
            $table->string('kode_member')->nullable();
            $table->string('no_pb')->nullable();
            $table->string('no_trans')->nullable();
            $table->timestamp('tgl_bayar')->nullable();
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertHeader(
        string $pb,
        string $transaction,
        string $orderDate,
        string $deliveryDate,
        ?string $status,
        array $overrides = [],
    ): void {
        DB::table('tbtr_obi_h')->insert(array_merge([
            'obi_tglpb' => $orderDate,
            'obi_tgltrans' => $orderDate,
            'obi_nopb' => $pb,
            'obi_notrans' => $transaction,
            'obi_kdmember' => 'MEM-DEFAULT',
            'obi_recid' => $status,
            'obi_maxdeliverytime' => $deliveryDate,
            'obi_maxsertim' => $deliveryDate,
            'obi_sendpick' => null,
            'obi_kdekspedisi' => 'NEXTDAY',
            'obi_alasanbtl' => null,
            'obi_tipebayar' => null,
            'obi_draftstruk' => null,
        ], $overrides));
    }

    private function insertDetail(
        string $transaction,
        string $transactionDate,
        string $productCode,
        ?string $picker,
        float $orderedQuantity,
        float $realizedQuantity,
        float $unitPrice,
        float $tax = 0,
        float $discount = 0,
        int $pickOrder = 1,
    ): void {
        DB::table('tbtr_obi_d')->insert([
            'obi_tgltrans' => $transactionDate,
            'obi_notrans' => $transaction,
            'obi_prdcd' => $productCode,
            'obi_picker' => $picker,
            'obi_recid' => '5',
            'obi_qtyorder' => $orderedQuantity,
            'obi_qtyrealisasi' => $realizedQuantity,
            'obi_hargasatuan' => $unitPrice,
            'obi_ppn' => $tax,
            'obi_diskon' => $discount,
            'obi_urut_pick' => $pickOrder,
            'obi_urut_scan' => $pickOrder,
        ]);
    }

    private function authenticatedUser(): DatabaseUser
    {
        return (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
    }
}
