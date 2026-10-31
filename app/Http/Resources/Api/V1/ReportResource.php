<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'folio' => $this->folio,
            'status' => ['code' => $this->status?->code, 'name' => $this->status?->name],
            'report_type' => ['id' => $this->reportType?->id, 'name' => $this->reportType?->name],
            'department' => $this->department?->name,
            'description' => $this->description,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'citizen' => ['name' => $this->citizen?->name],
            'created_at' => $this->created_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}