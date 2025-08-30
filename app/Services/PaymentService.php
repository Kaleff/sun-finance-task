<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    // API to DB column mapping
    private const COLUMN_MAPPING = [
        'paymentDate' => Payment::COLUMN_PAYMENT_DATE,
        'firstname' => Payment::COLUMN_PAYER_NAME,
        'lastname' => Payment::COLUMN_PAYER_SURNAME,
        'amount' => Payment::COLUMN_AMOUNT,
        'description' => Payment::COLUMN_LOAN_REFERENCE,
        'refId' => Payment::COLUMN_PAYMENT_REFERENCE,
    ];

    public function createPayment(array $payment_data): array
    {
        // Map API columns to DB columns
        $payment = [];
        $refund = null;
        foreach($payment_data as $key => $value) {
            if (isset(self::COLUMN_MAPPING[$key])) {
                $payment[self::COLUMN_MAPPING[$key]] = $value;
            }
        }

        $loan = $this->fetchActiveLoan($payment[Payment::COLUMN_LOAN_REFERENCE]);
        if(!$loan) {
            return [
                'data' => null,
                'error' => 'Active loan not found',
                'message' => 'The loan specified by the refId ' . $payment[Payment::COLUMN_LOAN_REFERENCE] . ' was not found.'
            ];
        }

        $loan[Loan::COLUMN_AMOUNT_PAID] += (float) $payment[Payment::COLUMN_AMOUNT];
        if ($loan[Loan::COLUMN_AMOUNT_PAID] > $loan[Loan::COLUMN_AMOUNT_TO_PAY]) {
            $loan[Loan::COLUMN_STATE] = Loan::STATE_PAID;
            $payment[Payment::COLUMN_STATE] = Payment::STATE_PARTIALLY_ASSIGNED;
            $refund = [
                Refund::COLUMN_PAYMENT_REFERENCE => $payment[Payment::COLUMN_PAYMENT_REFERENCE],
                Refund::COLUMN_AMOUNT => $loan[Loan::COLUMN_AMOUNT_PAID] - $loan[Loan::COLUMN_AMOUNT_TO_PAY],
                Refund::COLUMN_STATUS => Refund::STATUS_PENDING,
            ];
        } else {
            $payment[Payment::COLUMN_STATE] = Payment::STATE_ASSIGNED;
            if ($loan[Loan::COLUMN_AMOUNT_PAID] == $loan[Loan::COLUMN_AMOUNT_TO_PAY]) {
                $loan[Loan::COLUMN_STATE] = Loan::STATE_PAID;
            }
        }
        $payment[Payment::COLUMN_CODE] = Payment::CODE_SUCCESS;

        try {
            DB::transaction(function () use ($payment, $loan, &$refund) {
                Payment::insert($payment);
                $loan->save();
                if ($refund) {
                    Refund::create($refund);
                    // Convert amount to string to avoid precision issues converting to json
                    $refund[Refund::COLUMN_AMOUNT] = (string) $refund[Refund::COLUMN_AMOUNT];
                }
            });
        } catch (\Exception $e) {
            Log::error("Error creating payment: {$e->getMessage()}");
            return [
                'data' => null,
                'error' => 'Payment creation failed',
                'message' => $e->getMessage()
            ];
        }

        return [
            'data' => [
                'payment' => $payment,
                'loan' => $loan->toArray(),
                'refund' => $refund ? $refund : null
            ],
            'error' => null,
            'message' => 'Payment created successfully'
        ];
    }

    private function fetchActiveLoan(string $loan_reference): ?Loan
    {
        return Loan::where(Loan::COLUMN_REFERENCE, $loan_reference)
            ->where(Loan::COLUMN_STATE, Loan::STATE_ACTIVE)
            ->first();
    }
}
