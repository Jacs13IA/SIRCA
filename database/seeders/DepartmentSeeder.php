<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Servicios Públicos Municipales',
                'slug' => 'servicios-publicos',
                'description' => 'Atención de alumbrado público y servicios municipales.',
            ],
            [
                'name' => 'Protección Civil',
                'slug' => 'proteccion-civil',
                'description' => 'Atención de riesgos y emergencias para la población.',
            ],
        ] as $department) {
            Department::withTrashed()->updateOrCreate(
                ['slug' => $department['slug']],
                [...$department, 'is_active' => true, 'deleted_at' => null],
            );
        }
    }
}
