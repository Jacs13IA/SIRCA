<?php

namespace App\Http\Requests;

use App\Models\CitizenMatchReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResolveCitizenMatchReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('resolve', $this->route('review')) ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['same_person', 'distinct'])],
            'canonical_citizen_id' => [
                'required_if:resolution,same_person',
                'nullable',
                'integer',
                Rule::exists('citizens', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var CitizenMatchReview|null $review */
            $review = $this->route('review');
            $canonicalId = $this->input('canonical_citizen_id');

            if ($this->input('resolution') === 'same_person'
                && $review
                && ! in_array((int) $canonicalId, $review->candidate_citizen_ids, true)) {
                $validator->errors()->add('canonical_citizen_id', 'Selecciona uno de los ciudadanos candidatos.');
            }

            if ($review && (int) $canonicalId === $review->citizen_id) {
                $validator->errors()->add('canonical_citizen_id', 'El ciudadano provisional no puede fusionarse consigo mismo.');
            }
        }];
    }
}
