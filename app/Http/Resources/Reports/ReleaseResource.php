<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'title' => $this->title,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'reports' => ReportSummaryResource::collection($this->whenLoaded('reports')),
        ];
    }
}