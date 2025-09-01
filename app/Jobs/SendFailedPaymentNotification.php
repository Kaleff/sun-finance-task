<?php

namespace App\Jobs;

use App\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFailedPaymentNotification implements ShouldQueue
{
    use Queueable;

    public array $rejected_payments = [];
    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(array $rejected_payments)
    {
        $this->rejected_payments = $rejected_payments;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $communication_service = app(CommunicationService::class);

        try {
            $communication_service->sendRejectedPayments($this->rejected_payments);
        } catch (Throwable $e) {
            Log::error('SendFailedPaymentNotification failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
