<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_configured_administrator_and_soft_deletes_the_demo_user(): void
    {
        $password = Str::random(40);

        config([
            'sirca.administrator.name' => 'Administrador SIRCA',
            'sirca.administrator.email' => 'admin@example.test',
            'sirca.administrator.password' => $password,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->seed();

        $administrator = User::query()->with('role')->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('administrator', $administrator->role->slug);
        $this->assertTrue(password_verify($password, $administrator->password));
        $this->assertSoftDeleted('users', ['email' => 'test@example.com']);
        $this->assertDatabaseCount('report_statuses', 6);
        $this->assertDatabaseCount('report_types', 9);
    }
}
