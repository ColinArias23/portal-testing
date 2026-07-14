<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'type' => $this->type,
            'category' => $this->category,
            'severity' => $this->severity,
        ];
    }
}