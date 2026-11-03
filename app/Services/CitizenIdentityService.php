<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\CitizenMatchReview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;

class CitizenIdentityService
{
    public function resolveForReport(array $data): Citizen
    {
        $fingerprints = $this->fingerprints($data);
        $this->lockFingerprints(array_values($fingerprints));

        [$citizenId, $candidateIds, $reason] = $this->findCandidateIds($fingerprints);

        if ($citizenId !== null) {
            return Citizen::query()->findOrFail($citizenId);
        }

        $citizen = $this->persistCitizen($data, $fingerprints);

        if ($reason !== null) {
            CitizenMatchReview::query()->create([
                'citizen_id' => $citizen->id,
                'candidate_citizen_ids' => $candidateIds,
                'identity_key' => $this->identityKey($fingerprints),
                'reason' => $reason,
                'status' => 'pending',
            ]);
        }

        return $citizen;
    }

    public function createCitizen(array $data): Citizen
    {
        $fingerprints = $this->fingerprints($data);
        $this->lockFingerprints(array_values($fingerprints));

        return $this->persistCitizen($data, $fingerprints);
    }

    public function updateCitizen(Citizen $citizen, array $data): void
    {
        $fingerprints = $this->fingerprints($data);
        $this->lockFingerprints([
            $citizen->email_fingerprint,
            $citizen->phone_fingerprint,
            ...array_values($fingerprints),
        ]);

        $citizen->fill($data);
        $citizen->forceFill($fingerprints)->save();
    }

    public function normalizePhone(string $phone): string
    {
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $parsed = $phoneUtil->parse(trim($phone), 'MX');

            if (! $phoneUtil->isValidNumber($parsed)) {
                throw new InvalidArgumentException('Invalid phone number.');
            }

            return $phoneUtil->format($parsed, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            throw new InvalidArgumentException('Invalid phone number.');
        }
    }

    public function fingerprints(array $data): array
    {
        $fingerprints = [
            'email_fingerprint' => null,
            'phone_fingerprint' => null,
        ];

        if (! (bool) ($data['contact_consent'] ?? false)) {
            return $fingerprints;
        }

        $email = $this->normalizeEmail($data['email'] ?? null);
        $phone = filled($data['phone'] ?? null)
            ? $this->normalizePhone((string) $data['phone'])
            : null;

        if ($email === null && $phone === null) {
            return $fingerprints;
        }

        $key = $this->key();

        if ($email !== null) {
            $fingerprints['email_fingerprint'] = hash_hmac('sha256', 'email:'.$email, $key);
        }

        if ($phone !== null) {
            $fingerprints['phone_fingerprint'] = hash_hmac('sha256', 'phone:'.$phone, $key);
        }

        return $fingerprints;
    }

    public function reviewIdentityKeyForPair(array $first, array $second): string
    {
        $parts = array_values(array_filter([
            $first['email_fingerprint'] ?? null,
            $first['phone_fingerprint'] ?? null,
            $second['email_fingerprint'] ?? null,
            $second['phone_fingerprint'] ?? null,
        ]));
        sort($parts, SORT_STRING);

        return hash_hmac('sha256', 'legacy-review:'.implode('|', $parts), $this->key());
    }

    public function lockFingerprints(array $fingerprints): void
    {
        $keys = array_values(array_unique(array_filter($fingerprints)));
        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            $now = now();
            DB::table('citizen_identity_locks')->insertOrIgnore([
                'lock_key' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('citizen_identity_locks')
                ->where('lock_key', $key)
                ->update(['updated_at' => $now]);
            DB::table('citizen_identity_locks')
                ->where('lock_key', $key)
                ->lockForUpdate()
                ->first();
        }
    }

    private function persistCitizen(array $data, array $fingerprints): Citizen
    {
        $citizen = Citizen::query()->create([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'contact_consent' => (bool) ($data['contact_consent'] ?? false),
        ]);

        $citizen->forceFill($fingerprints)->save();

        return $citizen;
    }

    private function findCandidateIds(array $fingerprints): array
    {
        $emailIds = $this->matchingCitizenIds('email_fingerprint', $fingerprints['email_fingerprint']);
        $phoneIds = $this->matchingCitizenIds('phone_fingerprint', $fingerprints['phone_fingerprint']);

        if ($fingerprints['email_fingerprint'] !== null && $fingerprints['phone_fingerprint'] !== null) {
            if (count($emailIds) === 1 && count($phoneIds) === 1 && $emailIds[0] === $phoneIds[0]) {
                return [$emailIds[0], [], null];
            }
        } else {
            $ids = $fingerprints['email_fingerprint'] !== null ? $emailIds : $phoneIds;

            if (count($ids) === 1) {
                return [$ids[0], [], null];
            }
        }

        $candidateIds = array_values(array_unique([...$emailIds, ...$phoneIds]));

        if ($candidateIds === []) {
            return [null, [], null];
        }

        $reason = $emailIds !== [] && $phoneIds !== [] && array_intersect($emailIds, $phoneIds) === []
            ? 'conflicting_contacts'
            : (count($candidateIds) > 1 ? 'ambiguous_match' : 'partial_match');

        return [null, $candidateIds, $reason];
    }

    private function matchingCitizenIds(string $column, ?string $fingerprint): array
    {
        if ($fingerprint === null) {
            return [];
        }

        return Citizen::query()
            ->where('contact_consent', true)
            ->where($column, $fingerprint)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        return mb_strtolower(trim($email), 'UTF-8');
    }

    private function identityKey(array $fingerprints): string
    {
        $parts = array_values(array_filter($fingerprints));
        sort($parts, SORT_STRING);

        return hash_hmac('sha256', implode('|', $parts), $this->key());
    }

    private function key(): string
    {
        $key = (string) config('services.citizen_matching.key');

        if (strlen($key) < 32) {
            throw new RuntimeException('Citizen matching key is not configured.');
        }

        return $key;
    }
}
