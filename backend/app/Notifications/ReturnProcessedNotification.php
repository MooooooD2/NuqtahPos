<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ReturnProcessedNotification extends Notification
{
    public function via(mixed $_notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $_notifiable): array
    {
        return [];
    }
}
