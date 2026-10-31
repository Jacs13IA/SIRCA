<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EvidenceService
{
    public function store(Report $report, ?User $actor, UploadedFile $file, string $source = 'staff'): Evidence
    {
        $path = $file->store("reports/{$report->id}/evidence", 'local');

        try {
            return DB::transaction(function () use ($report, $actor, $file, $path, $source): Evidence {
                $evidence = $report->evidences()->create([
                    'uploaded_by_user_id' => $actor?->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                ]);

                $report->auditLogs()->create([
                    'actor_user_id' => $actor?->id,
                    'event' => 'report.evidence_uploaded',
                    'new_values' => ['evidence_id' => $evidence->id, 'mime_type' => $evidence->mime_type, 'source' => $source],
                ]);

                $report->activities()->create([
                    'user_id' => $actor?->id,
                    'type' => 'evidence',
                    'description' => 'Se adjuntó evidencia: '.$evidence->original_name,
                    'metadata' => ['evidence_id' => $evidence->id, 'source' => $source],
                ]);

                return $evidence;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}