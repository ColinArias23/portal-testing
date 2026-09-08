<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes, AutoUppercase, HasFactory;

    protected array $uppercaseExclude = [
        'status',
        'details',
        'reason',
        'cancellation_reason',
        'request_number', 
        'created_at',
        'updated_at',
        'deleted_at',
        'submitted_at',
        'finalized_at',
        'cancelled_at',
    ];

    protected $fillable = [
        'request_number',
        'employee_id',
        'leave_type_id',
        'rescheduled_from_id',
        'status',
        'start_date',
        'end_date',
        'is_half_day',
        'total_days',
        'reason',
        'details',
        'is_confidential',
        'current_step_order',
        'total_steps',
        'submitted_at',
        'finalized_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => LeaveRequestStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_half_day' => 'boolean',
        'is_confidential' => 'boolean',
        'total_days' => 'decimal:2',
        'details' => 'array',
        'submitted_at' => 'datetime',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LeaveRequest $request) {
            $request->request_number ??= static::generateRequestNumber();
        });
    }

    public static function generateRequestNumber(): string
    {
        $year = now()->year;
        $sequence = \Illuminate\Support\Facades\DB::transaction(function () use ($year) {
            $count = static::withTrashed()
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return $count + 1;
        });

        return sprintf('LR-%d-%06d', $year, $sequence);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(LeaveRequestApprovalStep::class)->orderBy('step_order');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'rescheduled_from_id');
    }

    public function rescheduledTo(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'rescheduled_from_id');
    }

    public function currentStep(): ?LeaveRequestApprovalStep
    {
        return $this->approvalSteps->firstWhere('step_order', $this->current_step_order);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeaveRequestDocument::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LeaveRequestLog::class)->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($this->hasOrganizationWideLeaveVisibility($user)) {
            return $query;
        }

        $subordinateEmployeeIds = $this->subordinateEmployeeIdsFor($user);

        return $query->where(function ($q) use ($user, $subordinateEmployeeIds) {
            $q->where('employee_id', $user->employee_id)
                ->orWhereIn('employee_id', $subordinateEmployeeIds)
                ->orWhereHas('approvalSteps', fn ($aq) => $aq->where('approver_id', $user->id));
        })->where(function ($q) use ($user) {
            $q->where('is_confidential', false)
                ->orWhere('employee_id', $user->employee_id)
                ->orWhereHas('approvalSteps', fn ($aq) => $aq->where('approver_id', $user->id));
        });
    }

    protected function subordinateEmployeeIdsFor(User $user): \Illuminate\Support\Collection
    {
        if (! $user->employee_id) {
            return collect();
        }

        $headDepartmentIds = Department::query()
            ->where('head_employee_id', $user->employee_id)
            ->pluck('id');

        if ($headDepartmentIds->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->where(function ($q) use ($headDepartmentIds) {
                foreach ($headDepartmentIds as $departmentId) {
                    $q->orWhereJsonContains('department_ids', $departmentId)
                        ->orWhereJsonContains('department_ids', (string) $departmentId);
                }
            })
            ->pluck('id');
    }

    public function hasOrganizationWideLeaveVisibility(User $user): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'HR']);
    }
}