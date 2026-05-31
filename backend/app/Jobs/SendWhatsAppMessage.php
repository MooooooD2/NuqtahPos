<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private int $messageLogId) {}

    public function handle(WhatsAppService $service): void
    {
        $log = WhatsAppMessage::find($this->messageLogId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        $service->dispatchMessage($log);
    }

    public function failed(Throwable $e): void
    {
        WhatsAppMessage::where('id', $this->messageLogId)->update([
            'status' => 'failed',
            'error_message' => substr($e->getMessage(), 0, 500),
        ]);
    }
}
