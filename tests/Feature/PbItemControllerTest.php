<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PbItemControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const DATABASE_SITE = 'spi1m_banjarbaru_sim';

    public function test_pb_item_details_include_the_order_items_and_cashback_rows(): void
    {
        $this->createItemTables();
        $this->insertItemRows();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.items.details', [
                'tglpb' => '2026-09-10',
                'pb' => '123456',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.pb', '123456')
            ->assertJsonPath('items.0.ket', 'PICKING')
            ->assertJsonPath('items.0.ket_item', 'PICKING')
            ->assertJsonCount(1, 'cashbacks')
            ->assertJsonPath('cashbacks.0.kode_promo', 'PROMO-TEST')
            ->assertJsonPath('cashbacks.0.tipe', 'Hadiah Test');
    }

    public function test_pb_item_details_include_transactions_across_the_selected_report_period(): void
    {
        $this->createItemTables();
        $this->insertItemRows();
        DB::table('tbtr_obi_h')->insert([
            'obi_tglpb' => '2026-09-10 14:30:00',
            'obi_tgltrans' => '2026-09-11 14:30:00',
            'obi_nopb' => '123456/001',
            'obi_notrans' => '00043',
            'obi_kdmember' => 'MEM001',
            'obi_recid' => '1',
            'obi_tglstruk' => '2026-09-11',
        ]);
        DB::table('tbtr_obi_d')->insert([
            'obi_tgltrans' => '2026-09-11 14:30:00',
            'obi_notrans' => '00043',
            'obi_prdcd' => '1234560',
            'obi_recid' => '1',
            'obi_hargasatuan' => 100,
            'obi_ppn' => 11,
            'obi_qtyorder' => 2,
            'obi_qtyrealisasi' => 1,
        ]);
        DB::table('promo_klikigr')->insert([
            'tgl_trans' => '2026-09-11 14:30:00',
            'no_pb' => '123456/001',
            'kode_promo' => 'PROMO-SECOND-DAY',
            'prdcd' => '1234560',
            'kelipatan' => 1,
            'cashback_order' => 10,
            'cashback_real' => 5,
            'gift_order' => null,
            'tipe_promo' => 'CASHBACK',
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.items.details', [
                'tglpb' => '2026-09-10',
                'tglstart' => '2026-09-10',
                'tglend' => '2026-09-11',
                'pb' => '123456',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonCount(2, 'cashbacks');
    }

    public function test_pb_location_preview_renders_the_item_location_for_printing(): void
    {
        $this->createItemTables();
        $this->insertItemRows();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.items.location', [
                'tglpb' => '2026-09-10',
                'pb' => '123456',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/ItemLocationPreview')
                    ->where('rows.0.pb', '123456')
                    ->where('rows.0.lokasi', 'D01.01.B.01.01')
                    ->etc()
            );
    }

    public function test_pb_location_preview_can_return_rows_as_json_for_a_modal(): void
    {
        $this->createItemTables();
        $this->insertItemRows();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.items.location', [
                'tglpb' => '2026-09-10',
                'pb' => '123456',
            ]))
            ->assertOk()
            ->assertJsonPath('rows.0.pb', '123456')
            ->assertJsonPath('rows.0.lokasi', 'D01.01.B.01.01');
    }

    public function test_pb_process_item_copy_returns_all_matching_item_rows(): void
    {
        $this->createItemTables();
        $this->insertItemRows();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $response = $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->getJson(route('pb.reports.copy', [
                'report' => 'pb-proses-item',
                'tglstart' => '2026-09-10',
                'tglend' => '2026-09-10',
                'recid' => '1',
                'search' => 'Produk Test',
            ]))
            ->assertOk();

        $response
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.pb', '123456')
            ->assertJsonPath('rows.0.ket_item', 'PICKING')
            ->assertJsonPath('rows.0.prd_deskripsipanjang', 'Produk Test')
            ->assertJsonStructure([
                'rows' => [[
                    'obi_qty_hitungulang',
                    'obi_qtyba',
                ]],
            ]);
    }

    private function createItemTables(): void
    {
        Schema::create('tbtr_obi_h', function (Blueprint $table): void {
            $table->timestamp('obi_tglpb');
            $table->timestamp('obi_tgltrans');
            $table->string('obi_nopb');
            $table->string('obi_notrans');
            $table->string('obi_kdmember');
            $table->string('obi_recid')->nullable();
            $table->date('obi_tglstruk')->nullable();
        });

        Schema::create('tbtr_obi_d', function (Blueprint $table): void {
            $table->timestamp('obi_tgltrans');
            $table->string('obi_notrans');
            $table->string('obi_prdcd');
            $table->string('obi_recid')->nullable();
            $table->decimal('obi_hargasatuan', 12, 2);
            $table->decimal('obi_ppn', 12, 2);
            $table->decimal('obi_qtyorder', 12, 2);
            $table->decimal('obi_qtyrealisasi', 12, 2);
            $table->integer('obi_urut_pick')->nullable();
            $table->timestamp('obi_pick_dt')->nullable();
            $table->timestamp('obi_scan_dt')->nullable();
            $table->string('obi_picker')->nullable();
            $table->decimal('obi_hargaweb', 12, 2)->nullable();
            $table->decimal('obi_qty_hitungulang', 12, 2)->nullable();
            $table->decimal('obi_qtyba', 12, 2)->nullable();
        });

        Schema::create('tbmaster_customer', function (Blueprint $table): void {
            $table->string('cus_kodemember');
            $table->string('cus_kodeigr');
            $table->string('cus_namamember');
        });

        Schema::create('tbmaster_prodmast', function (Blueprint $table): void {
            $table->string('prd_prdcd');
            $table->string('prd_deskripsipanjang');
        });

        Schema::create('tbtr_packing_obi', function (Blueprint $table): void {
            $table->timestamp('pobi_tgltransaksi');
            $table->string('pobi_notransaksi');
            $table->string('pobi_prdcd');
            $table->decimal('pobi_qty', 12, 2)->nullable();
            $table->string('pobi_create_by')->nullable();
            $table->string('pobi_nocontainer')->nullable();
        });

        Schema::create('tbmaster_lokasi', function (Blueprint $table): void {
            $table->string('lks_prdcd')->nullable();
            $table->string('lks_tiperak');
            $table->string('lks_koderak');
            $table->string('lks_kodesubrak');
            $table->string('lks_shelvingrak');
            $table->string('lks_nourut');
        });

        Schema::create('promo_klikigr', function (Blueprint $table): void {
            $table->timestamp('tgl_trans');
            $table->string('no_pb');
            $table->string('kode_promo');
            $table->string('prdcd');
            $table->decimal('kelipatan', 12, 2);
            $table->decimal('cashback_order', 12, 2);
            $table->decimal('cashback_real', 12, 2);
            $table->string('gift_order')->nullable();
            $table->string('tipe_promo');
        });
    }

    private function insertItemRows(): void
    {
        DB::table('tbtr_obi_h')->insert([
            'obi_tglpb' => '2026-09-10 14:30:00',
            'obi_tgltrans' => '2026-09-10 14:30:00',
            'obi_nopb' => '123456/001',
            'obi_notrans' => '00042',
            'obi_kdmember' => 'MEM001',
            'obi_recid' => '1',
            'obi_tglstruk' => '2026-09-10',
        ]);
        DB::table('tbtr_obi_d')->insert([
            'obi_tgltrans' => '2026-09-10 14:30:00',
            'obi_notrans' => '00042',
            'obi_prdcd' => '1234560',
            'obi_recid' => '1',
            'obi_hargasatuan' => 100,
            'obi_ppn' => 11,
            'obi_qtyorder' => 2,
            'obi_qtyrealisasi' => 1,
            'obi_urut_pick' => 1,
            'obi_picker' => 'PICKER-TEST',
        ]);
        DB::table('tbmaster_customer')->insert([
            'cus_kodemember' => 'MEM001',
            'cus_kodeigr' => '1M',
            'cus_namamember' => 'Member Test',
        ]);
        DB::table('tbmaster_prodmast')->insert([
            'prd_prdcd' => '1234560',
            'prd_deskripsipanjang' => 'Produk Test',
        ]);
        DB::table('tbtr_packing_obi')->insert([
            'pobi_tgltransaksi' => '2026-09-10 14:30:00',
            'pobi_notransaksi' => '00042',
            'pobi_prdcd' => '1234560',
            'pobi_qty' => 1,
            'pobi_create_by' => 'PACKER-TEST',
            'pobi_nocontainer' => 'KOLI-01',
        ]);
        DB::table('tbmaster_lokasi')->insert([
            'lks_prdcd' => '1234560',
            'lks_tiperak' => 'B',
            'lks_koderak' => 'D01',
            'lks_kodesubrak' => '01',
            'lks_shelvingrak' => '01',
            'lks_nourut' => '01',
        ]);
        DB::table('promo_klikigr')->insert([
            'tgl_trans' => '2026-09-10 14:30:00',
            'no_pb' => '123456/001',
            'kode_promo' => 'PROMO-TEST',
            'prdcd' => '1234560',
            'kelipatan' => 1,
            'cashback_order' => 10,
            'cashback_real' => 5,
            'gift_order' => 'Hadiah Test',
            'tipe_promo' => 'CASHBACK',
        ]);
    }
}
