<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\CitizenMatchReview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CitizenDeduplicationService
{
    public function __construct(
        private readonly CitizenIdentityService $identity,
        private readonly AuditLogService $auditLog,
    ) {}

    public function run(bool $apply): array
    {
        $profiles = [];
        $invalidContacts = 0;

        Citizen::withTrashed()
            ->where('contact_consent', true)
            ->orderBy('id')
            ->chunkById(200, function ($citizens) use (&$profiles, &$invalidContacts, $apply): void {
                foreach ($citizens as $citizen) {
                    try {
                        $fingerprints = $this->identity->fingerprints([
                            'email' => $citizen->email,
                            'phone' => $citizen->phone,
                            'contact_consent' => true,
                        ]);
                    } catch (InvalidArgumentException) {
                        $invalidContacts++;

                        continue;
                    }

                    if ($apply && ($citizen->email_fingerprint !== $fingerprints['email_fingerprint']
                        || $citizen->phone_fingerprint !== $fingerprints['phone_fingerprint'])) {
                        $citizen->forceFill($fingerprints)->saveQuietly();
                    }

                    $profiles[] = [
                        'id' => $citizen->id,
                        'active' => ! $citizen->trashed(),
                        ...$fingerprints,
                    ];
                }
            });

        $activeProfiles = array_values(array_filter($profiles, static fn (array $profile): bool => $profile['active']));
        $exactGroups = $this->exactDuplicateGroups($activeProfiles);
        $ambiguousPairs = $this->ambiguousPairs($activeProfiles);
        $mergedCount = 0;
        $reviewCount = 0;

        if ($apply) {
            foreach ($exactGroups as $group) {
                $mergedCount += $this->mergeGroup($group);
            }

            foreach ($ambiguousPairs as [$first, $second]) {
                $identityKey = $this->identity->reviewIdentityKeyForPair($first, $second);

                if (CitizenMatchReview::query()->where('identity_key', $identityKey)->exists()) {
                    continue;
                }

                CitizenMatchReview::query()->create([
                    'citizen_id' => min($first['id'], $second['id']),
                    'candidate_citizen_ids' => [max($first['id'], $second['id'])],
                    'identity_key' => $identityKey,
                    'reason' => 'legacy_shared_contact',
                    'status' => 'pending',
                ]);
                $reviewCount++;
            }
        }

        return [
            'profiles' => count($profiles),
            'exact_groups' => count($exactGroups),
            'exact_profiles_to_merge' => array_sum(array_map(static fn (array $group): int => count($group) - 1, $exactGroups)),
            'ambiguous_pairs' => count($ambiguousPairs),
            'invalid_contacts' => $invalidContacts,
            'merged_profiles' => $mergedCount,
            'review_cases_created' => $reviewCount,
        ];
    }

    private function exactDuplicateGroups(array $profiles): array
    {
        $groups = [];

        foreach ($profiles as $profile) {
            if ($profile['email_fingerprint'] === null || $profile['phone_fingerprint'] === null) {
                continue;
            }

            $key = $profile['email_fingerprint'].'|'.$profile['phone_fingerprint'];
            $groups[$key][] = $profile['id'];
        }

        return array_values(array_filter($groups, static fn (array $ids): bool => count($ids) > 1));
    }

    private function ambiguousPairs(array $profiles): array
    {
        $pairs = [];

        foreach (['email_fingerprint', 'phone_fingerprint'] as $field) {
            $groups = [];

            foreach ($profiles as $profile) {
                if ($profile[$field] !== null) {
                    $groups[$profile[$field]][] = $profile;
                }
            }

            foreach ($groups as $group) {
                for ($left = 0; $left < count($group); $left++) {
                    for ($right = $left + 1; $right < count($group); $right++) {
                        $first = $group[$left];
                        $second = $group[$right];
                        $sameBothContacts = $first['email_fingerprint'] !== null
                            && $first['phone_fingerprint'] !== null
                            && $first['email_fingerprint'] === $second['email_fingerprint']
                            && $first['phone_fingerprint'] === $second['phone_fingerprint'];

                        if ($sameBothContacts) {
                            continue;
                        }

                        $key = min($first['id'], $second['id']).':'.max($first['id'], $second['id']);
                        $pairs[$key] = $first['id'] < $second['id']
                            ? [$first, $second]
                            : [$second, $first];
                    }
                }
            }
        }

        return array_values($pairs);
    }

    private function mergeGroup(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $citizens = Citizen::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

            if ($citizens->count() < 2) {
                return 0;
            }

            $canonical = $citizens->first();
            $merged = 0;

            foreach ($citizens->skip(1) as $duplicate) {
                $reportCount = $duplicate->reports()->count();
                $duplicate->reports()->update(['citizen_id' => $canonical->id]);
                $this->auditLog->record($duplicate, null, 'citizen.duplicates_merged', [
                    'citizen_id' => $duplicate->id,
                ], [
                    'canonical_citizen_id' => $canonical->id,
                    'report_count' => $reportCount,
                    'source' => 'deduplication_command',
                ]);
                $duplicate->delete();
                $merged++;
            }

            return $merged;
        });
    }
}
