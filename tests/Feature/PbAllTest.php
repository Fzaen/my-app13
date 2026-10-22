<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PbAllTest extends TestCase
{
    private const DATABASE_SITE = 'spi1m_banjarbaru_sim';

    public function test_guest_is_redirected_to_login_from_pb_all(): void
    {
        $this->get('/pb/all')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_read_pb_all_from_selected_sim_database(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'SIM-TEST']);

        $this->actingAs($user)
            ->withSession(['selected_database' => self::DATABASE_SITE])
            ->get('/pb/all')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Pb/All')
                    ->has('rows')
                    ->etc()
            );
    }
}
