<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'folio' => $this->folio,
            'status' => ['code' => $this->status?->code, 'name' => $this->status?->name],
            'report_type' => $this->reportType?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}