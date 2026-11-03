<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\CitizenMatchReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CitizenMatchReviewService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly CitizenIdentityService $identity,
    ) {}

    public function resolve(CitizenMatchReview $review, array $data, User $actor): void
    {
        DB::transaction(function () use ($review, $data, $actor): void {
            $review = CitizenMatchReview::query()->lockForUpdate()->findOrFail($review->id);

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages(['resolution' => 'Esta revisión ya fue atendida.']);
            }

            $citizen = Citizen::withTrashed()->findOrFail($review->citizen_id);
            $resolution = $data['resolution'];

            if ($resolution === 'same_person') {
                $canonical = Citizen::query()->findOrFail($data['canonical_citizen_id']);

                if (! in_array($canonical->id, $review->candidate_citizen_ids, true)) {
                    throw ValidationException::withMessages(['canonical_citizen_id' => 'El ciudadano no pertenece a esta revisión.']);
                }

                $this->identity->lockFingerprints([
                    $citizen->email_fingerprint,
                    $citizen->phone_fingerprint,
                    $canonical->email_fingerprint,
                    $canonical->phone_fingerprint,
                ]);

                $reportCount = $citizen->reports()->count();
                $citizen->reports()->update(['citizen_id' => $canonical->id]);
                $this->auditLog->record($citizen, $actor, 'citizen.duplicates_merged', [
                    'citizen_id' => $citizen->id,
                ], [
                    'canonical_citizen_id' => $canonical->id,
                    'report_count' => $reportCount,
                ]);
                $citizen->delete();
            } else {
                $this->auditLog->record($citizen, $actor, 'citizen.match_marked_distinct', [], [
                    'candidate_citizen_ids' => $review->candidate_citizen_ids,
                ]);
            }

            $review->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
        });
    }
}
