<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification
{
    public function __construct(
        private string $employeeName,
        private string $leaveType,
        private string $startDate,
        private string $endDate,
        private int    $days,
    ) {}

    public function via(mixed $_notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $_notifiable): array
    {
        return [
            'message'       => "Leave request: {$this->employeeName}",
            'subtitle'      => ucfirst($this->leaveType) . " · {$this->days} day(s) from {$this->startDate}",
            'employee_name' => $this->employeeName,
            'leave_type'    => $this->leaveType,
            'start_date'    => $this->startDate,
            'end_date'      => $this->endDate,
            'days'          => $this->days,
        ];
    }
}
