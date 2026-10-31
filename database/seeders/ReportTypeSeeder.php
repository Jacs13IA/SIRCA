<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\ReportType;
use Illuminate\Database\Seeder;

class ReportTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'servicios-publicos' => [
                'Luminaria apagada',
                'Poste sin lámpara',
                'Lámpara intermitente',
                'Cableado dañado',
                'Levantamiento eléctrico',
            ],
            'proteccion-civil' => [
                'Árbol en riesgo',
                'Incendio',
                'Riesgo eléctrico',
                'Situación de emergencia',
            ],
        ];

        foreach ($types as $departmentSlug => $names) {
            $department = Department::query()->where('slug', $departmentSlug)->firstOrFail();

            foreach ($names as $name) {
                $slug = str($name)->slug()->toString();

                ReportType::withTrashed()->updateOrCreate(
                    ['department_id' => $department->id, 'slug' => $slug],
                    ['name' => $name, 'is_active' => true, 'deleted_at' => null],
                );
            }
        }
    }
}
