<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_supervisor_cannot_open_department_catalog(): void
    {
        $role = Role::query()->where('slug', 'supervisor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('admin.departments.index'))->assertForbidden();
    }

    public function test_administrator_can_create_department_and_report_type(): void
    {
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $administrator = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($administrator)->post(route('admin.departments.store'), [
            'name' => 'Servicios Públicos',
            'slug' => 'servicios-publicos',
            'description' => 'Atención municipal.',
        ])->assertRedirect(route('admin.departments.index'));

        $department = Department::query()->where('slug', 'servicios-publicos')->firstOrFail();

        $this->post(route('admin.report-types.store'), [
            'department_id' => $department->id,
            'name' => 'Luminaria apagada',
            'slug' => 'luminaria-apagada',
        ])->assertRedirect(route('admin.report-types.index'));

        $this->assertDatabaseHas('report_types', [
            'department_id' => $department->id,
            'slug' => 'luminaria-apagada',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'department.created', 'auditable_id' => $department->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_type.created', 'auditable_type' => \App\Models\ReportType::class]);
    }
}