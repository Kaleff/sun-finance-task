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

class SendLoanPaidConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $loan_id;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(string $loan_id)
    {
        $this->loan_id = $loan_id;
    }

    public function handle(): void
    {
        $communication_service = app(CommunicationService::class);

        $loan = Loan::find($this->loan_id);
        if (!$loan) {
            Log::warning("SendLoanPaidNotification: loan not found", ['loan_id' => $this->loan_id]);
            return;
        }

        try {
            $communication_service->sendLoanPaidNotification($loan);
        } catch (Throwable $e) {
            Log::error('SendLoanPaidNotification failed', ['loan_id' => $this->loan_id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
