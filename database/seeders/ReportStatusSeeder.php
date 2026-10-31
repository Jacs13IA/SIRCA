<?php

namespace Database\Seeders;

use App\Models\ReportStatus;
use Illuminate\Database\Seeder;

class ReportStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'registrado', 'name' => 'Registrado', 'sort_order' => 1],
            ['code' => 'asignado', 'name' => 'Asignado', 'sort_order' => 2],
            ['code' => 'en_proceso', 'name' => 'En proceso', 'sort_order' => 3],
            ['code' => 'atendido', 'name' => 'Atendido', 'sort_order' => 4],
            ['code' => 'cerrado', 'name' => 'Cerrado', 'sort_order' => 5, 'is_final' => true],
            ['code' => 'cancelado', 'name' => 'Cancelado', 'sort_order' => 6, 'is_final' => true, 'is_cancellation' => true],
        ] as $status) {
            ReportStatus::withTrashed()->updateOrCreate(
                ['code' => $status['code']],
                [
                    'name' => $status['name'],
                    'sort_order' => $status['sort_order'],
                    'is_final' => $status['is_final'] ?? false,
                    'is_cancellation' => $status['is_cancellation'] ?? false,
                    'deleted_at' => null,
                ],
            );
        }
    }
}
