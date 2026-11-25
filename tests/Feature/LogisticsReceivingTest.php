<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use App\Models\ExportModel\PromotionCheck;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogisticsReceivingTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_receiving_page(): void
    {
        $this->get(route('logistics.receiving'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_supplier_return_details(): void
    {
        $this->get(route('logistics.receiving.supplier-returns', [
            'supplier' => 'K1124',
        ]))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_promo_check(): void
    {
        $this->get(route('info.promo', ['kode' => 'CBNF2']))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_get_promo_suggestions(): void
    {
        $this->getJson(route('info.promo.suggestions', ['search' => 'CBWG']))
            ->assertUnauthorized();
    }

    public function test_promo_suggestion_search_requires_two_non_whitespace_characters(): void
    {
        $this->actingAs((new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']))
            ->getJson(route('info.promo.suggestions', ['search' => '  ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    public function test_authenticated_user_receives_promo_suggestions_from_code_and_name_search(): void
    {
        $response = null;
        $queries = DB::connection()->pretend(function () use (&$response): void {
            $response = $this->actingAs((new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']))
                ->getJson(route('info.promo.suggestions', ['search' => 'CBWG5']));
        });

        $response->assertOk()->assertExactJson(['suggestions' => []]);
        $this->assertCount(1, $queries);
        $sql = strtolower($queries[0]['query']);
        $this->assertStringContainsString("promo_code ilike '%cbwg5%'", $sql);
        $this->assertStringContainsString("promo_name ilike '%cbwg5%'", $sql);
        $this->assertStringContainsString('tbtr_cashback_hdr', $sql);
        $this->assertStringContainsString('tbtr_gift_hdr', $sql);
    }

    public function test_old_promo_url_redirects_to_info_url_preserving_query(): void
    {
        $this->actingAs((new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']))
            ->get('/cek/promo?kode=CBNF2')
            ->assertRedirect(route('info.promo', ['kode' => 'CBNF2']));
    }

    public function test_old_plu_url_redirects_to_info_url_preserving_query(): void
    {
        $this->actingAs((new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']))
            ->get('/data/plu?plu=1234560')
            ->assertRedirect(route('plu.info', ['plu' => '1234560']));
    }

    public function test_info_routes_use_the_new_urls(): void
    {
        $this->assertSame('/info/detail-plu', route('plu.info', [], false));
        $this->assertSame('/info/detail-promo', route('info.promo', [], false));
    }

    public function test_promo_member_igr_code_matches_the_selected_connection(): void
    {
        $this->assertSame('1M', PromotionCheck::memberIgrCode('spibms1m'));
        $this->assertSame('1M', PromotionCheck::memberIgrCode('simspibms1m'));
        $this->assertSame('27', PromotionCheck::memberIgrCode('igrbms'));
        $this->assertSame('27', PromotionCheck::memberIgrCode('simigrbms'));
        $this->assertSame('4C', PromotionCheck::memberIgrCode('spibms4c'));
        $this->assertSame('4C', PromotionCheck::memberIgrCode('simspibms4c'));
        $this->assertSame('4C', PromotionCheck::memberIgrCode('spi4c_palangkaraya_production'));
        $this->assertSame('4E', PromotionCheck::memberIgrCode('simspibms4e'));
        $this->assertSame('4X', PromotionCheck::memberIgrCode('simspibms4x'));
    }
}
