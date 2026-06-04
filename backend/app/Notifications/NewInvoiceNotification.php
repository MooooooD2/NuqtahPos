<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NewInvoiceNotification extends Notification
{
    public function __construct(
        private string  $invoiceNumber,
        private float   $total,
        private string  $paymentMethod,
        private ?string $customerName = null,
    ) {}

    public function via(mixed $_notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $_notifiable): array
    {
        $customer = $this->customerName ? " — {$this->customerName}" : '';
        return [
            'message'        => "New sale #{$this->invoiceNumber}{$customer}",
            'subtitle'       => number_format($this->total, 2) . ' · ' . ucfirst($this->paymentMethod),
            'invoice_number' => $this->invoiceNumber,
            'total'          => $this->total,
            'payment_method' => $this->paymentMethod,
            'customer_name'  => $this->customerName,
        ];
    }
}
