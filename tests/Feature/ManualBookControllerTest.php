<?php

namespace Tests\Feature;

use App\Models\Auth\DatabaseUser;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualBookControllerTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_the_ba_online_manual(): void
    {
        $this->get(route('manual-book.faq.ba-online'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_other_than_ed1_is_forbidden_from_the_manual(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'ADM']);

        $this->actingAs($user)
            ->get(route('manual-book.faq.ba-online'))
            ->assertForbidden();
    }

    public function test_ed1_can_view_ba_online_manual_content_from_json(): void
    {
        $user = (new DatabaseUser)->forceFill(['userid' => 'ED1']);

        $this->actingAs($user)
            ->get(route('manual-book.faq.ba-online'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('ManualBook/BaOnline')
                    ->where('manual.title', 'BA Online')
                    ->where(
                        'manual.announcement',
                        'FYI Per 19 Oct 2026, Berita Acara Online sudah Live dengan scope Pembatalan Struk.',
                    )
                    ->where('manual.scope', 'Pembatalan Struk')
                    ->where(
                        'manual.access.0.url',
                        'http://itcc.indomaret.lan/berita-acara-itcc/login',
                    )
                    ->etc()
            );
    }
}
