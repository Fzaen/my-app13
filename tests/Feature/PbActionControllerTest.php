<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PbActionControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array<string, array{string}>
     */
    public static function pbWriteRoutes(): array
    {
        return [
            'gift' => ['pb.actions.gift.store'],
            'loyalty' => ['pb.actions.loyalty.update'],
            'request' => ['pb.actions.request.save'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedPbWriteIps(): array
    {
        return [
            'office 30' => ['192.168.198.30'],
            'office 35' => ['192.168.198.35'],
            'office 40' => ['192.168.198.40'],
            'office 41' => ['192.168.198.41'],
            'office 42' => ['192.168.198.42'],
            'office 43' => ['192.168.198.43'],
            'office 44' => ['192.168.198.44'],
            'office 45' => ['192.168.198.45'],
            'server IPv4 localhost' => ['127.0.0.1'],
            'server IPv6 localhost' => ['::1'],
            'office 30 as IPv4-mapped IPv6' => ['::ffff:192.168.198.30'],
        ];
    }

    public function test_guest_is_redirected_to_login_from_a_pb_action_form(): void
    {
        $this->get(route('pb.actions.loyalty.index', ['kdmm' => 'MEM001']))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_driver_details(): void
    {
        $this->getJson(route('pb.actions.driver-details', ['pb' => '123456']))
            ->assertUnauthorized();
    }

    #[DataProvider('pbWriteRoutes')]
    public function test_pb_action_write_endpoints_reject_unlisted_ips(string $routeName): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.198.186'])
            ->actingAs($user)
            ->post(route($routeName))
            ->assertForbidden();
    }

    #[DataProvider('allowedPbWriteIps')]
    public function test_each_configured_pb_write_ip_reaches_form_validation(string $ipAddress): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->actingAs($user)
            ->post(route('pb.actions.gift.store'))
            ->assertSessionHasErrors(['tglpb', 'nopb', 'notrans', 'kdmm', 'penginput']);
    }

    public function test_whitelisted_user_receives_json_validation_errors_for_gift_modal_submission(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.198.30'])
            ->actingAs($user)
            ->postJson(route('pb.actions.gift.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tglpb',
                'nopb',
                'notrans',
                'kdmm',
                'penginput',
            ]);
    }

    public function test_whitelisted_user_can_create_a_shipping_request(): void
    {
        $this->createOrderTables();
        $this->createShippingRequestTable();
        $this->insertOrder();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.198.30'])
            ->actingAs($user)
            ->post(route('pb.actions.request.save'), [
                'tglpb' => '2026-09-10',
                'nopb' => '123456/001',
                'notrans' => '42',
                'kdmm' => 'MEM001',
                'mode' => 'create',
                'tgl_kirim' => '2026-09-12',
                'request' => 'RIT 2 SIANG',
            ]);

        $response->assertRedirect();
        $request = $this->temporaryTable('spi1m_requestkirim')->first();

        $this->assertSame('123456', $request->req_nopb);
        $this->assertSame('2026-09-10', $request->req_tglpb);
        $this->assertSame('MEM001', $request->req_kodemm);
        $this->assertSame('RIT 2 SIANG', $request->req_request);
        $this->assertSame('2026-09-12', $request->req_tgl_kirim);
    }

    public function test_authenticated_user_can_open_a_shipping_request_form_from_a_pb_order(): void
    {
        $this->createOrderTables();
        $this->createShippingRequestTable();
        $this->insertOrder();
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.actions.request.form', [
                'mode' => 'create',
                'tglpb' => '2026-09-10',
                'nopb' => '123456/001',
                'notrans' => '42',
                'kdmm' => 'MEM001',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Action')
                    ->where('action', 'request')
                    ->where('mode', 'create')
                    ->where('record.pb', '123456')
                    ->where('record.tglpb', '2026-09-10')
                    ->etc()
            );
    }

    public function test_authenticated_user_can_load_a_shipping_request_as_json_for_the_modal(): void
    {
        $this->createOrderTables();
        $this->createShippingRequestTable();
        $this->insertOrder();
        $this->temporaryTable('spi1m_requestkirim')->insert([
            'req_nopb' => '123456',
            'req_tglpb' => '2026-09-10',
            'req_kodemm' => 'MEM001',
            'req_request' => 'RIT 2 SIANG',
            'req_tgl_kirim' => '2026-09-12',
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.actions.request.form', [
                'mode' => 'edit',
                'tglpb' => '2026-09-10',
                'nopb' => '123456/001',
                'notrans' => '42',
                'kdmm' => 'MEM001',
                'return_report' => 'pb-proses',
                'return_tglstart' => '2026-09-01',
                'return_tglend' => '2026-09-10',
                'return_recid' => 'proses',
            ]))
            ->assertOk()
            ->assertJsonPath('mode', 'edit')
            ->assertJsonPath('record.pb', '123456')
            ->assertJsonPath('record.request', 'RIT 2 SIANG')
            ->assertJsonPath('record.tgl_kirim', '2026-09-12')
            ->assertJsonPath('returnFilters.report', 'pb-proses')
            ->assertJsonPath('returnFilters.recid', 'proses');
    }

    public function test_whitelisted_user_can_update_a_shipping_request(): void
    {
        $this->createOrderTables();
        $this->createShippingRequestTable();
        $this->insertOrder();
        $this->temporaryTable('spi1m_requestkirim')->insert([
            'req_nopb' => '123456',
            'req_tglpb' => '2026-09-10',
            'req_kodemm' => 'MEM001',
            'req_request' => 'RIT 1 PAGI',
            'req_tgl_kirim' => '2026-09-11',
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.198.35'])
            ->actingAs($user)
            ->post(route('pb.actions.request.save'), [
                'tglpb' => '2026-09-10',
                'nopb' => '123456/001',
                'notrans' => '42',
                'kdmm' => 'MEM001',
                'mode' => 'edit',
                'tgl_kirim' => '2026-09-13',
                'request' => 'RIT 3 SORE',
            ]);

        $response->assertRedirect();
        $request = $this->temporaryTable('spi1m_requestkirim')->first();

        $this->assertSame('RIT 3 SORE', $request->req_request);
        $this->assertSame('2026-09-13', $request->req_tgl_kirim);
        $this->assertNotNull($request->req_modify_dt);
    }

    public function test_whitelisted_user_can_update_a_loyalty_delivery(): void
    {
        $this->createLoyaltyTable();
        $this->temporaryTable('spi1m_loyalty')->insert([
            'ly_kode' => 'MEM001',
            'ly_program' => 'LOYALTY-2026',
            'ly_hadiah' => 'Hadiah',
            'ly_periode' => '2026',
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.198.40'])
            ->actingAs($user)
            ->post(route('pb.actions.loyalty.update'), [
                'kdmm' => 'MEM001',
                'program' => 'LOYALTY-2026',
                'tgl_kirim' => '2026-09-12',
                'ttd' => 'PETUGAS',
            ]);

        $response->assertRedirect();
        $loyalty = $this->temporaryTable('spi1m_loyalty')->first();

        $this->assertSame('2026-09-12', $loyalty->ly_tgl_kirim);
        $this->assertSame('PETUGAS', $loyalty->ly_ttd);
        $this->assertNotNull($loyalty->ly_create_dt);
    }

    public function test_loyalty_update_requires_a_delivery_date_and_sender_signature(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.198.30'])
            ->actingAs($user)
            ->post(route('pb.actions.loyalty.update'), [
                'kdmm' => 'MEM001',
                'program' => 'LOYALTY-2026',
            ])
            ->assertSessionHasErrors(['tgl_kirim', 'ttd']);
    }

    public function test_authenticated_user_can_open_the_pending_loyalty_form_from_pb(): void
    {
        $this->createLoyaltyTable();
        Schema::create('tbmaster_customer', function (Blueprint $table): void {
            $table->string('cus_kodemember');
            $table->string('cus_namamember');
        });
        DB::table('tbmaster_customer')->insert([
            'cus_kodemember' => 'MEM001',
            'cus_namamember' => 'Member Test',
        ]);
        $this->temporaryTable('spi1m_loyalty')->insert([
            'ly_kode' => 'MEM001',
            'ly_program' => 'LOYALTY-2026',
            'ly_hadiah' => 'Hadiah',
            'ly_periode' => '2026',
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->get(route('pb.actions.loyalty.index', [
                'kdmm' => 'MEM001',
                'program' => 'LOYALTY-2026',
            ]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/Action')
                    ->where('action', 'loyalty')
                    ->where('selectedProgram', 'LOYALTY-2026')
                    ->where('record.namamm', 'Member Test')
                    ->where('loyaltyRecords.0.gift', 'Hadiah')
                    ->etc()
            );
    }

    public function test_loyalty_details_only_include_records_that_have_not_been_sent(): void
    {
        $this->createLoyaltyTable();
        Schema::create('tbmaster_customer', function (Blueprint $table): void {
            $table->string('cus_kodemember');
            $table->string('cus_namamember');
        });
        DB::table('tbmaster_customer')->insert([
            'cus_kodemember' => 'MEM001',
            'cus_namamember' => 'Member Test',
        ]);
        $this->temporaryTable('spi1m_loyalty')->insert([
            [
                'ly_kode' => 'MEM001',
                'ly_program' => 'LOYALTY-PENDING',
                'ly_hadiah' => 'Hadiah Pending',
                'ly_periode' => '2026',
                'ly_tgl_kirim' => null,
                'ly_ttd' => '',
            ],
            [
                'ly_kode' => 'MEM001',
                'ly_program' => 'LOYALTY-SENT',
                'ly_hadiah' => 'Hadiah Terkirim',
                'ly_periode' => '2025',
                'ly_tgl_kirim' => '2026-11-17',
                'ly_ttd' => 'PETUGAS',
            ],
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.actions.loyalty.details', ['kdmm' => 'MEM001']))
            ->assertOk()
            ->assertJsonPath('member.name', 'Member Test')
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.program', 'LOYALTY-PENDING')
            ->assertJsonPath('records.0.gift', 'Hadiah Pending')
            ->assertJsonPath('records.0.sent_at', null);
    }

    public function test_driver_details_are_filtered_by_the_requested_pb(): void
    {
        Schema::create('tbtr_serahterima_ipp', function (Blueprint $table): void {
            $table->string('sti_noawb')->nullable();
            $table->string('sti_noorder');
            $table->string('sti_tipeproses')->nullable();
            $table->string('sti_penerima')->nullable();
            $table->decimal('sti_codvalue', 12, 2)->nullable();
            $table->string('sti_driverid')->nullable();
            $table->string('sti_drivername')->nullable();
            $table->string('sti_driverphone')->nullable();
            $table->string('sti_vehicleno')->nullable();
            $table->timestamp('sti_tglserahterima')->nullable();
        });
        DB::table('tbtr_serahterima_ipp')->insert([
            [
                'sti_noawb' => 'AWB-001',
                'sti_noorder' => 'A123456',
                'sti_tipeproses' => 'SERAH',
                'sti_penerima' => 'Member Test',
                'sti_codvalue' => 125000,
                'sti_driverid' => 'DRV-01',
                'sti_drivername' => 'Driver Test',
                'sti_tglserahterima' => '2026-11-17 10:30:00',
            ],
            [
                'sti_noawb' => 'AWB-002',
                'sti_noorder' => '999999',
                'sti_tipeproses' => 'SERAH',
                'sti_penerima' => null,
                'sti_codvalue' => null,
                'sti_driverid' => null,
                'sti_drivername' => null,
                'sti_tglserahterima' => null,
            ],
        ]);
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->getJson(route('pb.actions.driver-details', ['pb' => '123456']))
            ->assertOk()
            ->assertJsonCount(1, 'drivers')
            ->assertJsonPath('drivers.0.order_number', 'A123456')
            ->assertJsonPath('drivers.0.driver_name', 'Driver Test');
    }

    private function createOrderTables(): void
    {
        Schema::create('tbtr_obi_h', function (Blueprint $table): void {
            $table->timestamp('obi_tglpb');
            $table->string('obi_nopb');
            $table->string('obi_notrans');
            $table->string('obi_kdmember');
        });

        Schema::create('tbmaster_customer', function (Blueprint $table): void {
            $table->string('cus_kodemember');
            $table->string('cus_kodeigr');
            $table->string('cus_namamember');
        });

        DB::table('tbmaster_customer')->insert([
            'cus_kodemember' => 'MEM001',
            'cus_kodeigr' => '1M',
            'cus_namamember' => 'Member Test',
        ]);
    }

    private function createShippingRequestTable(): void
    {
        Schema::create('spi1m_requestkirim', function (Blueprint $table): void {
            $table->string('req_nopb');
            $table->date('req_tglpb');
            $table->string('req_kodemm');
            $table->string('req_request')->nullable();
            $table->timestamp('req_create_dt')->useCurrent();
            $table->date('req_tgl_kirim');
            $table->timestamp('req_modify_dt')->nullable();
            $table->primary(['req_nopb', 'req_tglpb', 'req_kodemm']);
        });
    }

    private function createLoyaltyTable(): void
    {
        Schema::create('spi1m_loyalty', function (Blueprint $table): void {
            $table->string('ly_kode');
            $table->string('ly_program');
            $table->string('ly_hadiah')->nullable();
            $table->string('ly_periode')->nullable();
            $table->date('ly_tgl_kirim')->nullable();
            $table->string('ly_ttd')->nullable();
            $table->timestamp('ly_create_dt')->nullable();
            $table->primary(['ly_kode', 'ly_program']);
        });
    }

    private function insertOrder(): void
    {
        DB::table('tbtr_obi_h')->insert([
            'obi_tglpb' => '2026-09-10 14:30:00',
            'obi_nopb' => '123456/001',
            'obi_notrans' => '00042',
            'obi_kdmember' => 'MEM001',
        ]);
    }

    private function temporaryTable(string $table): Builder
    {
        return DB::connection()->table(config('database.temporary_schema').'.'.$table);
    }
}
