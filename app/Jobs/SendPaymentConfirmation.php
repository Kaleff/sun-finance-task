<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendPaymentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $loan_reference;
    public string $payment_reference;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(string $loan_reference, string $payment_reference)
    {
        $this->loan_reference = $loan_reference;
        $this->payment_reference = $payment_reference;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $communication_service = app(CommunicationService::class);

        $loan = Loan::where('reference', $this->loan_reference)->first();
        if (!$loan) {
            Log::warning('SendPaymentConfirmation: loan not found on loan: ', ['loan_reference' => $this->loan_reference]);
            return;
        }

        try {
            $communication_service->sendPaymentConfirmation($loan);
        } catch (Throwable $e) {
            Log::error('SendPaymentConfirmation failed', [
                'loan_reference' => $this->loan_reference,
                'payment_ref' => $this->payment_reference,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
