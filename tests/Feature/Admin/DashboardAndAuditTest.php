<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\RoleSeeder::class, \Database\Seeders\ReportStatusSeeder::class]);
    }

    public function test_administrator_can_view_dashboard_and_audit(): void
    {
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $administrator = User::factory()->create(['role_id' => $role->id]);
        AuditLog::query()->create(['actor_user_id' => $administrator->id, 'event' => 'report.test']);

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()->assertSee('Resumen de reportes');
        $this->get(route('admin.audit.index'))->assertOk()->assertSee('report.test');
    }

    public function test_department_staff_cannot_view_global_audit(): void
    {
        $role = Role::query()->where('slug', 'supervisor')->firstOrFail();
        $department = Department::query()->create(['name' => 'Servicios Públicos', 'slug' => 'servicios-publicos', 'is_active' => true]);
        $supervisor = User::factory()->create(['role_id' => $role->id, 'department_id' => $department->id]);

        $this->actingAs($supervisor)->get(route('admin.audit.index'))->assertForbidden();
    }
}