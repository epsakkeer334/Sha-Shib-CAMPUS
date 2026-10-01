<?php

namespace App\Traits;

use App\Models\Admin\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait RecordsAuditTrail
{
    /**
     * Write one row to audit_trail. Failures are logged and never break the main flow.
     *
     * @param  array  $meta  e.g. ['old' => [...], 'new' => [...], 'reason' => '...', 'description' => '...']
     */
    protected function audit(string $action, string $module, $reference = null, array $meta = []): void
    {
        try {
            $user = Auth::user();

            AuditTrail::create([
                'user_id' => $user?->id,
                'institute_id' => $user?->institute_id,
                'action' => $action,
                'module' => $module,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'ip_address' => Request::ip(),
                'meta' => array_filter($meta, fn ($value) => $value !== null) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write audit trail: ' . $e->getMessage());
        }
    }

    protected function auditCreate($model, string $module, ?string $description = null): void
    {
        $this->audit('create', $module, $model, [
            'new' => $model->toArray(),
            'description' => $description ?? "Created new {$module} record",
        ]);
    }

    protected function auditUpdate($model, string $module, array $oldValues, array $newValues, ?string $description = null): void
    {
        $this->audit('update', $module, $model, [
            'old' => $oldValues,
            'new' => $newValues,
            'description' => $description ?? "Updated {$module} record",
        ]);
    }

    protected function auditDelete($model, string $module, ?string $description = null): void
    {
        $this->audit('delete', $module, $model, [
            'old' => $model->toArray(),
            'description' => $description ?? "Deleted {$module} record",
        ]);
    }

    protected function auditApprove($model, string $module, ?string $reason = null, ?string $description = null): void
    {
        $this->audit('approve', $module, $model, [
            'reason' => $reason,
            'description' => $description ?? "Approved {$module} record",
        ]);
    }

    protected function auditReject($model, string $module, ?string $reason = null, ?string $description = null): void
    {
        $this->audit('reject', $module, $model, [
            'reason' => $reason,
            'description' => $description ?? "Rejected {$module} record",
        ]);
    }

    /**
     * BiC dues-block bypass, Super Admin overrides, etc. A reason is mandatory.
     */
    protected function auditBypass($model, string $module, string $reason, ?array $oldValues = null, ?array $newValues = null): void
    {
        $this->audit('bypass', $module, $model, [
            'old' => $oldValues,
            'new' => $newValues,
            'reason' => $reason,
        ]);
    }
}
