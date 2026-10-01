<?php

namespace App\Traits;

use App\Models\admin\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Log an activity
     *
     * @param string $action
     * @param string $module
     * @param mixed $model
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param string|null $reason
     * @param string|null $description
     * @return void
     */
    protected function logActivity(
        string $action,
        string $module,
        $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $description = null
    ) {
        try {
            $user = Auth::user();

            // If no user is authenticated, use system user (you can modify this)
            $userId = $user ? $user->id : 1; // Default to system user if not authenticated

            ActivityLog::create([
                'action' => $action,
                'module' => $module,
                'model_id' => $model ? $model->id : null,
                'model_type' => $model ? get_class($model) : null,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'reason' => $reason,
                'description' => $description,
                'user_id' => $userId,
                'institute_id' => $user && method_exists($user, 'institute_id') ? $user->institute_id : null,
                'user_ip' => Request::ip(),
                'performed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            \Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }

    /**
     * Log a create activity
     */
    protected function logCreate($model, string $module, ?string $description = null)
    {
        $this->logActivity(
            action: 'create',
            module: $module,
            model: $model,
            newValues: $model->toArray(),
            description: $description ?? "Created new {$module} record",
        );
    }

    /**
     * Log an update activity with changes
     */
    protected function logUpdate($model, string $module, array $changes = [], ?string $description = null)
    {
        $oldValues = [];
        $newValues = [];

        foreach ($changes as $field) {
            if ($model->isDirty($field)) {
                $oldValues[$field] = $model->getOriginal($field);
                $newValues[$field] = $model->getAttribute($field);
            }
        }

        if (empty($oldValues) && empty($newValues)) {
            // If no specific fields provided, log all changes
            $oldValues = $model->getOriginal();
            $newValues = $model->getAttributes();
        }

        $this->logActivity(
            action: 'update',
            module: $module,
            model: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            description: $description ?? "Updated {$module} record",
        );
    }

    /**
     * Log a delete activity
     */
    protected function logDelete($model, string $module, ?string $description = null)
    {
        $this->logActivity(
            action: 'delete',
            module: $module,
            model: $model,
            oldValues: $model->toArray(),
            description: $description ?? "Deleted {$module} record",
        );
    }

    /**
     * Log an approve activity
     */
    protected function logApprove($model, string $module, string $reason = null, ?string $description = null)
    {
        $this->logActivity(
            action: 'approve',
            module: $module,
            model: $model,
            reason: $reason,
            description: $description ?? "Approved {$module} record",
        );
    }

    /**
     * Log a reject activity
     */
    protected function logReject($model, string $module, string $reason = null, ?string $description = null)
    {
        $this->logActivity(
            action: 'reject',
            module: $module,
            model: $model,
            reason: $reason,
            description: $description ?? "Rejected {$module} record",
        );
    }

    /**
     * Log an override activity
     */
    protected function logOverride($model, string $module, ?array $oldValues = null, ?array $newValues = null, ?string $reason = null, ?string $description = null)
    {
        $this->logActivity(
            action: 'override',
            module: $module,
            model: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
            description: $description ?? "Overrode {$module} record",
        );
    }
}
