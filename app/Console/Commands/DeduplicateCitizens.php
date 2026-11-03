<?php

namespace App\Console\Commands;

use App\Services\CitizenDeduplicationService;
use Illuminate\Console\Command;

class DeduplicateCitizens extends Command
{
    protected $signature = 'citizens:deduplicate {--apply : Save fingerprints, merge exact matches, and queue ambiguous matches}';

    protected $description = 'Analyze citizen duplicates; use --apply to write reviewed-safe changes.';

    public function handle(CitizenDeduplicationService $deduplication): int
    {
        $apply = (bool) $this->option('apply');
        $result = $deduplication->run($apply);

        $this->info('Perfiles con consentimiento analizados: '.$result['profiles']);
        $this->info('Grupos exactos (correo y teléfono): '.$result['exact_groups']);
        $this->info('Perfiles candidatos a fusión: '.$result['exact_profiles_to_merge']);
        $this->info('Pares ambiguos para revisión: '.$result['ambiguous_pairs']);
        $this->info('Teléfonos inválidos omitidos: '.$result['invalid_contacts']);

        if ($apply) {
            $this->info('Perfiles consolidados: '.$result['merged_profiles']);
            $this->info('Revisiones creadas: '.$result['review_cases_created']);
        } else {
            $this->comment('Simulación sin cambios. Ejecuta con --apply después de revisar los conteos.');
        }

        return self::SUCCESS;
    }
}
