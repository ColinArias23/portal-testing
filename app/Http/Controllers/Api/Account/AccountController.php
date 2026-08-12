<?php

namespace App\Http\Controllers\Api\Account;

use App\Enums\SignatureType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateAccountProfileRequest;
use App\Http\Requests\Account\UploadSignatureRequest;
use App\Http\Resources\Account\EmployeeSignatureResource;
use App\Services\Account\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $account)
    {
        $this->middleware('auth:sanctum');
    }

    public function updateProfile(UpdateAccountProfileRequest $request): JsonResponse
    {
        $this->account->updateContactNumber($request->user(), $request->validated('contact_number'));

        return response()->json([
            'message' => 'Profile updated successfully.',
        ]);
    }

    public function signatures(Request $request): JsonResponse
    {
        try {
            $signatures = $this->account->signatures($request->user());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to load signatures at this time.',
            ], 503);
        }

        return response()->json([
            'data' => [
                'primary' => $signatures->has(SignatureType::Primary->value)
                    ? new EmployeeSignatureResource($signatures->get(SignatureType::Primary->value))
                    : null,
                'countersign' => $signatures->has(SignatureType::Countersign->value)
                    ? new EmployeeSignatureResource($signatures->get(SignatureType::Countersign->value))
                    : null,
            ],
        ]);
    }

    public function uploadSignature(UploadSignatureRequest $request, string $type): JsonResponse
    {
        $signatureType = $this->resolveType($type);

        $signature = $this->account->uploadSignature(
            $request->user(),
            $signatureType,
            $request->file('signature'),
        );

        return response()->json([
            'data' => new EmployeeSignatureResource($signature),
            'message' => 'Signature saved successfully.',
        ]);
    }

    public function deleteSignature(Request $request, string $type): JsonResponse
    {
        $signatureType = $this->resolveType($type);

        $this->account->deleteSignature($request->user(), $signatureType);

        return response()->json([
            'message' => 'Signature deleted.',
        ]);
    }

    public function previewSignature(Request $request, string $type): mixed
    {
        $signatureType = $this->resolveType($type);

        $response = $this->account->streamSignature($request->user(), $signatureType);

        abort_if($response === null, 404, 'No signature uploaded.');

        return $response;
    }

    public function activity(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', config('account.activity_log.default_limit'));

        try {
            $logs = $this->account->activityLog($request->user(), $limit);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to load activity log at this time.',
            ], 503);
        }

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'type' => $log->signature_type->label(),
                'action' => $log->action->value,
                'fileName' => $log->original_filename,
                'timestamp' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }

    private function resolveType(string $type): SignatureType
    {
        $signatureType = SignatureType::tryFrom($type);

        if (! $signatureType) {
            throw ValidationException::withMessages([
                'type' => 'Invalid signature type. Must be "primary" or "countersign".',
            ]);
        }

        return $signatureType;
    }
}