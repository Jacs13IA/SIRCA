<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Administrador general', 'slug' => 'administrator'],
            ['name' => 'Supervisor', 'slug' => 'supervisor'],
            ['name' => 'Operador', 'slug' => 'operator'],
            ['name' => 'Ciudadano', 'slug' => 'citizen'],
        ] as $role) {
            Role::withTrashed()->updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name'], 'deleted_at' => null],
            );
        }
    }
}
