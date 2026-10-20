<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\Google2FAController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use Tests\TestCase;

final class AuthPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default'                                    => 'sqlite',
            'database.connections.sqlite.database'                => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }

    public function test_remembered_authentication_expires_after_ninety_days(): void
    {
        $durationInMinutes = 90 * 24 * 60;
        $this->assertSame($durationInMinutes, config('session.lifetime'));

        $guard = Auth::guard('web');
        $user = new User;
        $user->forceFill([
            'id'             => 1,
            'email'          => 'session-test@example.test',
            'password'       => 'not-a-real-password',
            'remember_token' => str_repeat('a', 60),
        ]);

        $guard->login($user, true);

        $cookie = app('cookie')->queued($guard->getRecallerName());

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta($durationInMinutes * 60, $cookie->getMaxAge(), 10);
    }

    public function test_two_factor_login_preserves_remember_me(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('google2fa_secret')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id'               => 1,
            'name'             => 'Test User',
            'email'            => 'session-test@example.test',
            'password'         => 'not-a-real-password',
            'google2fa_secret' => 'test-secret',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $session = app('session')->driver();
        $session->put('user_id', 1);
        $session->put('garad_remember_login', true);

        $request = Request::create('/2fa/login', 'POST', [
            'one_time_password' => '123456',
        ]);
        $request->setLaravelSession($session);

        Google2FA::shouldReceive('verifyGoogle2FA')
            ->once()
            ->with('test-secret', '123456')
            ->andReturn(true);

        (new Google2FAController)->verify($request);

        $guard = Auth::guard('web');
        $cookie = app('cookie')->queued($guard->getRecallerName());

        $this->assertTrue(Auth::check());
        $this->assertTrue($session->get('save_login_2fa'));
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(90 * 24 * 60 * 60, $cookie->getMaxAge(), 10);
    }

    public function test_email_otp_login_preserves_remember_me(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('otp')->nullable();
            $table->string('type')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id'         => 1,
            'name'       => 'Test User',
            'email'      => 'otp-test@example.test',
            'password'   => 'not-a-real-password',
            'otp'        => '123456',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = app('session')->driver();
        $session->put('garad_remember_login', true);

        $request = Request::create('/verify-otp', 'POST', [
            'otp' => ['1', '2', '3', '4', '5', '6'],
        ]);
        $request->setLaravelSession($session);

        (new AuthenticatedSessionController)->verifyOtp($request);

        $guard = Auth::guard('web');
        $cookie = app('cookie')->queued($guard->getRecallerName());

        $this->assertTrue(Auth::check());
        $this->assertNull(DB::table('users')->value('otp'));
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(90 * 24 * 60 * 60, $cookie->getMaxAge(), 10);
    }
}
