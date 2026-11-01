<?php

namespace App\Services;

use App\Models\Report;
use App\Models\StagedEvidence;
use App\Models\Evidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StagedEvidenceService
{
    public function __construct(private readonly EvidenceService $evidenceService) {}

    public function stage(UploadedFile $file): array
    {
        $path = $file->store('evidence-staging', 'local');

        if (! is_string($path)) {
            throw new RuntimeException('No fue posible almacenar la evidencia temporal.');
        }

        $reference = Str::random(64);

        try {
            $staged = StagedEvidence::query()->create([
                'reference_hash' => hash('sha256', $reference),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => (int) $file->getSize(),
                'expires_at' => now()->addMinutes(30),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return [
            'reference' => $reference,
            'expires_at' => $staged->expires_at->toIso8601String(),
        ];
    }

    public function attach(string $reference, Report $report): Evidence
    {
        $destinationPath = null;

        try {
            [$evidence, $sourcePath] = DB::transaction(function () use ($reference, $report, &$destinationPath): array {
                $staged = StagedEvidence::query()
                    ->where('reference_hash', hash('sha256', $reference))
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($staged->consumed_at !== null || $staged->expires_at->isPast()) {
                    throw ValidationException::withMessages([
                        'reference' => 'La referencia de evidencia no es válida o expiró.',
                    ]);
                }

                if (! str_starts_with($staged->file_path ?? '', 'evidence-staging/')
                    || ! Storage::disk('local')->exists($staged->file_path)) {
                    throw ValidationException::withMessages([
                        'reference' => 'La referencia de evidencia no es válida o expiró.',
                    ]);
                }

                $extension = match ($staged->mime_type) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                    default => throw ValidationException::withMessages([
                        'reference' => 'La referencia de evidencia no es válida o expiró.',
                    ]),
                };

                $destinationPath = "reports/{$report->id}/evidence/".Str::uuid().".{$extension}";

                if (! Storage::disk('local')->copy($staged->file_path, $destinationPath)) {
                    throw new RuntimeException('No fue posible asociar la evidencia temporal.');
                }

                $evidence = $this->evidenceService->registerStoredFile(
                    $report,
                    null,
                    $destinationPath,
                    $staged->original_name,
                    $staged->mime_type,
                    $staged->file_size,
                    'mcp',
                );

                $staged->update(['consumed_at' => now()]);

                return [$evidence, $staged->file_path];
            });
        } catch (Throwable $exception) {
            if ($destinationPath !== null) {
                Storage::disk('local')->delete($destinationPath);
            }

            throw $exception;
        }

        Storage::disk('local')->delete($sourcePath);

        return $evidence;
    }

    public function purgeExpired(): int
    {
        $expired = StagedEvidence::query()->where('expires_at', '<=', now())->get();

        foreach ($expired as $staged) {
            if (is_string($staged->file_path) && str_starts_with($staged->file_path, 'evidence-staging/')) {
                Storage::disk('local')->delete($staged->file_path);
            }

            $staged->forceDelete();
        }

        return $expired->count();
    }
}