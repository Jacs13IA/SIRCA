<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveCitizenMatchReviewRequest;
use App\Models\Citizen;
use App\Models\CitizenMatchReview;
use App\Services\CitizenMatchReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CitizenMatchReviewController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CitizenMatchReview::class);

        $reviews = CitizenMatchReview::query()
            ->where('status', 'pending')
            ->with('citizen:id,name,email,phone')
            ->latest()
            ->paginate(20);
        $candidateIds = $reviews->getCollection()
            ->flatMap(fn (CitizenMatchReview $review): array => $review->candidate_citizen_ids)
            ->unique()
            ->all();
        $candidates = Citizen::query()->whereIn('id', $candidateIds)->get(['id', 'name', 'email', 'phone'])->keyBy('id');
        $profiles = $reviews->getCollection()
            ->pluck('citizen')
            ->filter()
            ->merge($candidates->values())
            ->unique('id');
        $contactHints = $profiles->mapWithKeys(fn (Citizen $citizen): array => [
            $citizen->id => [
                'email' => $this->maskEmail($citizen->email),
                'phone' => $this->maskPhone($citizen->phone),
            ],
        ]);

        return view('admin.citizens.reviews', [
            'reviews' => $reviews,
            'candidates' => $candidates,
            'contactHints' => $contactHints,
        ]);
    }

    public function resolve(
        ResolveCitizenMatchReviewRequest $request,
        CitizenMatchReview $review,
        CitizenMatchReviewService $reviews,
    ): RedirectResponse {
        $reviews->resolve($review, $request->validated(), $request->user());

        return to_route('admin.citizen-match-reviews.index')->with('status', 'La revisión se resolvió correctamente.');
    }

    private function maskEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1, 'UTF-8').'***'.($domain !== '' ? '@'.$domain : '');
    }

    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return $digits === '' ? null : '***'.substr($digits, -4);
    }
}
