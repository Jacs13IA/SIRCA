<?php

namespace App\Services;

use App\Models\Report;
use App\Models\StagedEvidence;
use App\Models\Evidence;
use Illuminate\Database\QueryException;
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

        $attempts = 0;
        $lastException = null;

        while ($attempts < 5) {
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

                return [
                    'reference' => $reference,
                    'expires_at' => $staged->expires_at->toIso8601String(),
                ];
            } catch (QueryException $exception) {
                $lastException = $exception;

                if (! $this->isRetryableReferenceStorageError($exception)) {
                    Storage::disk('local')->delete($path);

                    throw $exception;
                }
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($path);

                throw $exception;
            }

            usleep(250000 * ($attempts + 1));
            $attempts++;
        }

        Storage::disk('local')->delete($path);

        throw $lastException instanceof QueryException
            ? $lastException
            : new RuntimeException('No fue posible crear la referencia de evidencia temporal.');
    }

    private function isRetryableReferenceStorageError(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'database is locked')
            || str_contains($message, 'database table is locked')
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'disk i/o error');
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

    public function isAvailable(string $reference): bool
    {
        return StagedEvidence::query()
            ->where('reference_hash', hash('sha256', $reference))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->exists();
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