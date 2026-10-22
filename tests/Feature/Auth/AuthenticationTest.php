<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\DatabaseUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const DATABASE_SITE = 'spi1m_banjarbaru_production';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.providers.users.driver' => 'tbmaster',
            'auth.providers.users.model' => DatabaseUser::class,
            'database.sites.'.self::DATABASE_SITE.'.connection' => 'sqlite',
        ]);
        Auth::forgetGuards();

        Schema::create('tbmaster_user', function (Blueprint $table): void {
            $table->string('userid')->primary();
            $table->string('userpassword');
        });
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('spibms1m');
    }

    public function test_guest_can_open_login_with_an_unavailable_saved_database(): void
    {
        config([
            'database.sites.'.self::DATABASE_SITE.'.connection' => 'unavailable_pgsql',
            'database.connections.unavailable_pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '1',
                'database' => 'unavailable',
                'username' => 'unavailable',
                'password' => 'unavailable',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'disable',
            ],
        ]);

        $this->withSession(['selected_database' => self::DATABASE_SITE])
            ->get('/login')
            ->assertOk();
    }

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_legacy_user_can_authenticate_on_the_selected_database(): void
    {
        DB::table('tbmaster_user')->insert([
            'userid' => 'ADM',
            'userpassword' => md5('123'),
        ]);

        $response = $this->post('/login', [
            'database' => self::DATABASE_SITE,
            'userid' => 'adm',
            'password' => '123',
        ]);

        $this->assertAuthenticated();
        $response->assertSessionHas('selected_database', self::DATABASE_SITE);
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('ADM')
            ->assertSee('spibms1m');
    }

    public function test_invalid_database_is_rejected(): void
    {
        $response = $this->post('/login', [
            'database' => 'unlisted_database',
            'userid' => 'adm',
            'password' => '123',
        ]);

        $response->assertSessionHasErrors('database');
        $response->assertSessionMissing('selected_database');
        $this->assertGuest();
    }

    public function test_database_connection_failure_returns_to_login_without_a_server_error(): void
    {
        config([
            'database.sites.'.self::DATABASE_SITE.'.connection' => 'unavailable_pgsql',
            'database.connections.unavailable_pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '1',
                'database' => 'unavailable',
                'username' => 'unavailable',
                'password' => 'unavailable',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'disable',
            ],
        ]);

        DB::purge('unavailable_pgsql');

        $response = $this->post('/login', [
            'database' => self::DATABASE_SITE,
            'userid' => 'ED1',
            'password' => 'not-used',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('database');
        $response->assertSessionMissing('selected_database');
        $this->assertGuest();
    }

    public function test_invalid_md5_password_is_rejected(): void
    {
        DB::table('tbmaster_user')->insert([
            'userid' => 'ADM',
            'userpassword' => md5('123'),
        ]);

        $response = $this->post('/login', [
            'database' => self::DATABASE_SITE,
            'userid' => 'adm',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('userid');
        $response->assertSessionMissing('selected_database');
        $this->assertGuest();
    }

    public function test_lowercase_userid_matches_uppercase_legacy_record_with_plaintext_password(): void
    {
        DB::table('tbmaster_user')->insert([
            'userid' => 'ED1',
            'userpassword' => '456123',
        ]);

        $response = $this->post('/login', [
            'database' => self::DATABASE_SITE,
            'userid' => 'ed1',
            'password' => '456123',
        ]);

        $this->assertAuthenticated();
        $response->assertSessionHas('selected_database', self::DATABASE_SITE);
    }

    public function test_users_can_logout(): void
    {
        DB::table('tbmaster_user')->insert([
            'userid' => 'ADM',
            'userpassword' => md5('123'),
        ]);

        $this->post('/login', [
            'database' => self::DATABASE_SITE,
            'userid' => 'adm',
            'password' => '123',
        ]);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('selected_database');
    }

    public function test_session_lasts_one_day_and_survives_browser_close(): void
    {
        $this->assertSame(1440, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_opening_login_redirects_authenticated_user_to_dashboard(): void
    {
        DB::table('tbmaster_user')->insert([
            'userid' => 'ADM',
            'userpassword' => md5('123'),
        ]);

        $this->post('/login', [
            'database' => self::DATABASE_SITE,
            'userid' => 'adm',
            'password' => '123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get('/login')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('selected_database', self::DATABASE_SITE);

        $this->assertAuthenticated();
    }
}
