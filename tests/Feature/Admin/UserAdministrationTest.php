<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_administrators_can_open_user_management(): void
    {
        $operator = $this->userWithRole('operator');

        $this->actingAs($operator)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_administrator_can_create_an_operator_assigned_to_a_department(): void
    {
        $administrator = $this->userWithRole('administrator');
        $department = Department::query()->create([
            'name' => 'Servicios Públicos',
            'slug' => 'servicios-publicos',
            'is_active' => true,
        ]);

        $this->actingAs($administrator)
            ->post(route('admin.users.store'), [
                'name' => 'Operador de prueba',
                'email' => 'operator@example.test',
                'password' => 'SIRCAOperator2026',
                'password_confirmation' => 'SIRCAOperator2026',
                'role_id' => Role::query()->where('slug', 'operator')->value('id'),
                'department_id' => $department->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'operator@example.test',
            'department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'auditable_type' => User::class,
        ]);
    }

    public function test_administrator_cannot_deactivate_their_own_account(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)
            ->delete(route('admin.users.destroy', $administrator))
            ->assertForbidden();
    }

    public function test_administrator_can_restore_a_deactivated_user(): void
    {
        $administrator = $this->userWithRole('administrator');
        $operator = $this->userWithRole('operator');
        $operator->delete();

        $this->actingAs($administrator)
            ->patch(route('admin.users.restore', $operator))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $operator->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.restored', 'auditable_id' => $operator->id]);
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::query()->where('slug', $slug)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }
}