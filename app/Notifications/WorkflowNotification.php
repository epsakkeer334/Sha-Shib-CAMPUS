<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * One in-app (database) notification about a workflow step. The data is built by
 * App\Services\AppNotifier from config/workflow_notifications.php.
 */
class WorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(public array $data)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return $this->data;
    }
}
