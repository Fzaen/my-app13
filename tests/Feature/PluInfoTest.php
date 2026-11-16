<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use App\Models\FrontEnd\PluInfo;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class PluInfoTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_plu_info(): void
    {
        $this->get(route('plu.info'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_plu_info_without_searching(): void
    {
        $this->actingAs($this->user())
            ->get(route('plu.info'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Data/PluInfo/Index')
                ->where('search', '')
                ->where('descriptionSearch', '')
                ->where('pluSearch', '')
                ->where('product', null)
                ->where('searchResults', [])
                ->where('message', null)
                ->etc()
            );
    }

    public function test_authenticated_user_can_search_products_by_description(): void
    {
        $results = collect([
            (object) [
                'plu' => '1234560',
                'description' => 'SUSU UHT 1 LITER',
                'unit' => 'PCS',
                'fraction' => 1,
            ],
        ]);

        $this->mock(PluInfo::class, function (MockInterface $mock) use ($results): void {
            $mock->shouldReceive('searchByDescription')
                ->once()
                ->with('SUSU')
                ->andReturn($results);
        });

        $this->actingAs($this->user())
            ->get(route('plu.info', ['search' => '  SUSU  ']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Data/PluInfo/Index')
                ->where('search', 'SUSU')
                ->where('descriptionSearch', 'SUSU')
                ->where('pluSearch', '')
                ->where('searchResults.0.plu', '1234560')
                ->where('searchResults.0.description', 'SUSU UHT 1 LITER')
                ->where('product', null)
                ->where('message', null)
                ->etc()
            );
    }

    public function test_authenticated_user_sees_a_message_when_no_description_matches(): void
    {
        $this->mock(PluInfo::class, function (MockInterface $mock): void {
            $mock->shouldReceive('searchByDescription')
                ->once()
                ->with('PRODUK TIDAK ADA')
                ->andReturn(collect());
        });

        $this->actingAs($this->user())
            ->get(route('plu.info', ['search' => 'PRODUK TIDAK ADA']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Data/PluInfo/Index')
                ->where('search', 'PRODUK TIDAK ADA')
                ->where('searchResults', [])
                ->where('message', 'Tidak ada produk dengan deskripsi tersebut.')
                ->etc()
            );
    }

    public function test_authenticated_user_can_find_a_product_by_numeric_plu(): void
    {
        $product = (object) [
            'plu' => '1234560',
            'description' => 'SUSU UHT 1 LITER',
            'stock' => 12,
            'real_stock' => 8,
        ];

        $this->mock(PluInfo::class, function (MockInterface $mock) use ($product): void {
            $mock->shouldReceive('findByInput')
                ->once()
                ->with('1234567')
                ->andReturn($product);
            $mock->shouldNotReceive('panelData');
        });

        $this->actingAs($this->user())
            ->get(route('plu.info', ['search' => '1234567']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Data/PluInfo/Index')
                ->where('search', '1234560')
                ->where('descriptionSearch', '')
                ->where('pluSearch', '1234560')
                ->where('product.plu', '1234560')
                ->where('product.real_stock', 8)
                ->where('searchResults', [])
                ->where('message', null)
                ->etc()
            );
    }

    public function test_authenticated_user_can_load_a_panel_on_demand(): void
    {
        $panelRows = collect([
            (object) ['plu' => '1234560', 'barcode' => '8991234567890'],
        ]);

        $this->mock(PluInfo::class, function (MockInterface $mock) use ($panelRows): void {
            $mock->shouldNotReceive('findByInput');
            $mock->shouldReceive('hasProduct')
                ->once()
                ->with('1234560')
                ->andReturn(true);
            $mock->shouldReceive('panelData')
                ->once()
                ->with('barcodes', '1234560')
                ->andReturn($panelRows);
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.panel', ['plu' => '1234560', 'panel' => 'barcodes']))
            ->assertOk()
            ->assertJsonPath('label', 'Barcode')
            ->assertJsonPath('rows.0.plu', '1234560')
            ->assertJsonPath('rows.0.barcode', '8991234567890');
    }

    public function test_picking_panel_includes_member_name_and_readable_status(): void
    {
        $panelRows = collect([
            (object) [
                'pb_number' => '4404502',
                'member_code' => 'GJ8672',
                'member_name' => 'PELANGGAN CONTOH',
                'pb_status' => 'DSP',
            ],
        ]);

        $this->mock(PluInfo::class, function (MockInterface $mock) use ($panelRows): void {
            $mock->shouldReceive('hasProduct')
                ->once()
                ->with('1234560')
                ->andReturn(true);
            $mock->shouldReceive('panelData')
                ->once()
                ->with('picking', '1234560')
                ->andReturn($panelRows);
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.panel', ['plu' => '1234560', 'panel' => 'picking']))
            ->assertOk()
            ->assertJsonPath('rows.0.member_code', 'GJ8672')
            ->assertJsonPath('rows.0.member_name', 'PELANGGAN CONTOH')
            ->assertJsonPath('rows.0.pb_status', 'DSP');
    }

    public function test_authenticated_user_can_load_the_net_price_panel(): void
    {
        $this->mock(PluInfo::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasProduct')
                ->once()
                ->with('1234560')
                ->andReturn(true);
            $mock->shouldReceive('panelData')
                ->once()
                ->with('price-margin', '1234560')
                ->andReturn(collect([
                    (object) [
                        'plu' => '1234560',
                        'minimum_sale_total' => 1000,
                        'cashback_per_minimum_sale' => 100,
                        'net_price' => 900,
                    ],
                ]));
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.panel', ['plu' => '1234560', 'panel' => 'price-margin']))
            ->assertOk()
            ->assertJsonPath('label', 'Harga net dan margin')
            ->assertJsonPath('rows.0.minimum_sale_total', 1000)
            ->assertJsonPath('rows.0.cashback_per_minimum_sale', 100)
            ->assertJsonPath('rows.0.net_price', 900);
    }

    public function test_authenticated_user_can_get_product_description_suggestions(): void
    {
        $suggestions = collect([
            (object) [
                'plu' => '1234560',
                'description' => 'SUSU UHT 1 LITER',
                'unit' => 'PCS',
                'fraction' => 1,
            ],
        ]);

        $this->mock(PluInfo::class, function (MockInterface $mock) use ($suggestions): void {
            $mock->shouldReceive('suggestProducts')
                ->once()
                ->with('SUSU')
                ->andReturn($suggestions);
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.suggestions', ['search' => 'SUSU']))
            ->assertOk()
            ->assertJsonPath('suggestions.0.plu', '1234560')
            ->assertJsonPath('suggestions.0.description', 'SUSU UHT 1 LITER');
    }

    public function test_authenticated_user_can_find_suggestions_by_plu(): void
    {
        $suggestions = collect([
            (object) [
                'plu' => '0003700',
                'description' => 'BIMOL MINYAK GORENG DRG 5000ml',
                'unit' => 'PCS',
                'fraction' => 1,
            ],
        ]);

        $this->mock(PluInfo::class, function (MockInterface $mock) use ($suggestions): void {
            $mock->shouldReceive('suggestProducts')
                ->once()
                ->with('0003700')
                ->andReturn($suggestions);
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.suggestions', ['search' => '0003700']))
            ->assertOk()
            ->assertJsonPath('suggestions.0.description', 'BIMOL MINYAK GORENG DRG 5000ml')
            ->assertJsonPath('suggestions.0.plu', '0003700');
    }

    public function test_description_suggestions_require_two_characters(): void
    {
        $this->actingAs($this->user())
            ->getJson(route('plu.info.suggestions', ['search' => 'S']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search' => 'The search field must be at least 2 characters.']);
    }

    public function test_guest_cannot_get_product_description_suggestions(): void
    {
        $this->getJson(route('plu.info.suggestions', ['search' => 'SUSU']))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_inline_product_summary(): void
    {
        $this->mock(PluInfo::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasProduct')
                ->once()
                ->with('1234560')
                ->andReturn(true);
            $mock->shouldReceive('panelData')
                ->once()
                ->with('cashbacks', '1234560')
                ->andReturn(collect([(object) ['promotion_code' => 'CB001']]));
            $mock->shouldReceive('panelData')
                ->once()
                ->with('gifts', '1234560')
                ->andReturn(collect([(object) ['promotion_code' => 'GF001']]));
            $mock->shouldReceive('panelData')
                ->once()
                ->with('price-margin', '1234560')
                ->andReturn(collect([(object) ['net_price' => 900]]));
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.summary', ['plu' => '1234560']))
            ->assertOk()
            ->assertJsonPath('cashbacks.0.promotion_code', 'CB001')
            ->assertJsonPath('gifts.0.promotion_code', 'GF001')
            ->assertJsonPath('priceMargin.0.net_price', 900);
    }

    public function test_guest_cannot_get_inline_product_summary(): void
    {
        $this->getJson(route('plu.info.summary', ['plu' => '1234560']))
            ->assertUnauthorized();
    }

    public function test_inline_summary_requires_a_canonical_plu(): void
    {
        $this->actingAs($this->user())
            ->getJson(route('plu.info.summary', ['plu' => '1234561']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plu' => 'The plu field format is invalid.']);
    }

    public function test_inline_summary_returns_not_found_for_an_unknown_plu(): void
    {
        $this->mock(PluInfo::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasProduct')
                ->once()
                ->with('9999990')
                ->andReturn(false);
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.summary', ['plu' => '9999990']))
            ->assertNotFound();
    }

    public function test_returns_401_when_guest_loads_a_plu_panel(): void
    {
        $this->getJson(route('plu.info.panel', ['plu' => '1234560', 'panel' => 'barcodes']))
            ->assertUnauthorized();
    }

    public function test_returns_422_when_panel_name_is_unknown(): void
    {
        $this->actingAs($this->user())
            ->getJson(route('plu.info.panel', ['plu' => '1234560', 'panel' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'panel' => 'The selected panel is invalid.',
            ]);
    }

    public function test_returns_422_when_panel_plu_is_not_canonical(): void
    {
        $this->actingAs($this->user())
            ->getJson(route('plu.info.panel', ['plu' => '1234561', 'panel' => 'barcodes']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'plu' => 'The plu field format is invalid.',
            ]);
    }

    public function test_returns_404_when_panel_plu_does_not_exist(): void
    {
        $this->mock(PluInfo::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasProduct')
                ->once()
                ->with('9999990')
                ->andReturn(false);
        });

        $this->actingAs($this->user())
            ->getJson(route('plu.info.panel', ['plu' => '9999990', 'panel' => 'barcodes']))
            ->assertNotFound();
    }

    public function test_authenticated_user_sees_a_message_when_a_plu_is_not_found(): void
    {
        $this->mock(PluInfo::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findByInput')
                ->once()
                ->with('999999999')
                ->andReturnNull();
        });

        $this->actingAs($this->user())
            ->get(route('plu.info', ['plu' => '999999999']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Data/PluInfo/Index')
                ->where('search', '999999999')
                ->where('product', null)
                ->where('message', 'PLU atau barcode tidak ditemukan.')
                ->etc()
            );
    }

    public function test_search_rejects_non_string_input(): void
    {
        $this->actingAs($this->user())
            ->get(route('plu.info', ['search' => ['invalid']]))
            ->assertSessionHasErrors('search');
    }

    private function user(): DatabaseUser
    {
        return (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);
    }
}
