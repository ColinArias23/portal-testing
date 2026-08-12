<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'fileName' => $this->original_filename,
            'fileSize' => $this->size_bytes,
            'uploadedAt' => $this->updated_at?->toIso8601String(),
            'previewUrl' => route('account.signatures.preview', ['type' => $this->type->value]),
        ];
    }
}
