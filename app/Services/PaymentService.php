<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
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
        $payment = $this->mapApiToAttributes($payment_data);

        $loan_ref = $payment[Payment::COLUMN_LOAN_REFERENCE] ?? null;
        $payment_amount_cents = isset($payment[Payment::COLUMN_AMOUNT])
            ? (int) round((float) $payment[Payment::COLUMN_AMOUNT] * 100)
            : 0;

        // Defensive fetch (request validation should have ensured existence for HTTP callers)
        $loan = $this->fetchActiveLoan((string) $loan_ref ?? '');
        if (!$loan) {
            return [
                'data' => null,
                'error' => 'Active loan not found',
                'message' => 'The active loan specified by the refId ' . ($loan_ref ?? '') . ' was not found.'
            ];
        }

        // Business logic: determine payment state, new loan amount_paid and any refund
        [
            'payment_state' => $payment_state,
            'new_amount_paid_cents' => $new_amount_paid_cents,
            'refund_amount_cents' => $refund_amount_cents
        ] = $this->computeAssignment($loan, $payment_amount_cents);

        // Prepare attributes for persistence
        $payment[Payment::COLUMN_STATE] = $payment_state;
        $payment[Payment::COLUMN_CODE] = Payment::CODE_SUCCESS;
        $payment[Payment::COLUMN_SOURCE] = Payment::SOURCE_API;
        // Store amount as formatted string to avoid float noise
        $payment[Payment::COLUMN_AMOUNT] = number_format($payment_amount_cents / 100, 2, '.', '');

        try {
            [$created_payment, $created_refund, $updated_loan] = $this->persistSinglePayment($payment, $loan, $new_amount_paid_cents, $refund_amount_cents);
        } catch (\Throwable $e) {
            Log::error("Error creating payment: {$e->getMessage()}", ['attrs' => $payment, 'loan_ref' => $loan_ref]);
            return [
                'data' => null,
                'error' => 'Payment creation failed',
                'message' => $e->getMessage()
            ];
        }

        return [
            'data' => [
                'payment' => $created_payment ? $created_payment->toArray() : $payment,
                'loan' => $updated_loan ? $updated_loan->toArray() : $loan->toArray(),
                'refund' => $created_refund ? $created_refund->toArray() : ($refund_amount_cents !== null ? ['amount' => number_format($refund_amount_cents / 100, 2, '.', '')] : null)
            ],
            'error' => null,
            'message' => 'Payment created successfully'
        ];
    }

    /**
     * Fetch an active loan by its reference.
     */
    private function fetchActiveLoan(string $loan_reference): ?Loan
    {
        return Loan::where(Loan::COLUMN_REFERENCE, $loan_reference)
            ->where(Loan::COLUMN_STATE, Loan::STATE_ACTIVE)
            ->first();
    }

    /**
     * Map API payload keys to DB column names and normalize values.
     */
    private function mapApiToAttributes(array $input): array
    {
        $payment = [];
        foreach (self::COLUMN_MAPPING as $api_key => $db_key) {
            if (!array_key_exists($api_key, $input)) {
                continue;
            }
            $value = $input[$api_key];

            if ($db_key === Payment::COLUMN_AMOUNT) {
                $payment[$db_key] = is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
            } elseif ($db_key === Payment::COLUMN_PAYMENT_DATE) {
                try {
                    $payment[$db_key] = Carbon::parse($value)->toDateTimeString();
                } catch (\Throwable $e) {
                    $payment[$db_key] = null;
                }
            } elseif ($db_key === Payment::COLUMN_SSN) {
                $ssn = trim((string)$value);
                $payment[$db_key] = $ssn === '' ? null : $ssn;
            } else {
                $payment[$db_key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $payment;
    }

    /**
     * Business calculation: returns [payment_state, new_amount_paid_cents, refund_amount_cents|null]
     */
    private function computeAssignment(Loan $loan, int $payment_amount_cents): array
    {
        $loan_amount_paid = (float) $loan[Loan::COLUMN_AMOUNT_PAID];
        $loan_amount_to_pay = (float) $loan[Loan::COLUMN_AMOUNT_TO_PAY];
        $loan_amount_paid_cents = (int) round($loan_amount_paid * 100);
        $loan_amount_to_pay_cents = (int) round($loan_amount_to_pay * 100);

        $new_amount_paid_cents = $loan_amount_paid_cents + $payment_amount_cents;

        $refund_amount_cents = null;
        if ($new_amount_paid_cents > $loan_amount_to_pay_cents) {
            $payment_state = Payment::STATE_PARTIALLY_ASSIGNED;
            $refund_amount_cents = $new_amount_paid_cents - $loan_amount_to_pay_cents;
        } else {
            $payment_state = Payment::STATE_ASSIGNED;
        }

        return [
            'payment_state' => $payment_state,
            'new_amount_paid_cents' => $new_amount_paid_cents,
            'refund_amount_cents' => $refund_amount_cents
        ];
    }

    /**
     * Persist payment, update loan and optionally create a refund inside a DB transaction.
     * Returns [created_payment_model|null, created_refund_model|null, updated_loan_model]
     */
    private function persistSinglePayment(array $payment, Loan $loan, int $new_amount_paid_cents, ?int $refund_amount_cents): array
    {
        $created_payment = null;
        $created_refund = null;

        DB::transaction(function () use (&$created_payment, &$created_refund, $payment, $loan, $new_amount_paid_cents, $refund_amount_cents) {
            $created_payment = Payment::create($payment);

            $loan_amount_to_pay_cents = (int) round((float) $loan->{Loan::COLUMN_AMOUNT_TO_PAY} * 100);

            // Update loan totals/state, store potential float values as strings to avoid precision issues
            $loan->{Loan::COLUMN_AMOUNT_PAID} = number_format($new_amount_paid_cents / 100, 2, '.', '');
            $loan->{Loan::COLUMN_STATE} = $new_amount_paid_cents >= $loan_amount_to_pay_cents
                ? Loan::STATE_PAID
                : $loan->{Loan::COLUMN_STATE};
            $loan->save();

            // Create refund if required (amount saved as formatted string)
            if ($refund_amount_cents !== null && $refund_amount_cents > 0) {
                $created_refund = Refund::create([
                    Refund::COLUMN_PAYMENT_REFERENCE => $payment[Payment::COLUMN_PAYMENT_REFERENCE],
                    Refund::COLUMN_AMOUNT => number_format($refund_amount_cents / 100, 2, '.', ''),
                    Refund::COLUMN_STATUS => Refund::STATUS_PENDING,
                ]);
            }
        });

        return [$created_payment, $created_refund, $loan->fresh()];
    }
}
