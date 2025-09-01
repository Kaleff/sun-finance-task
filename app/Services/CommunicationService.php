<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

class CommunicationService
{
    public function sendPaymentConfirmation(Loan $loan)
    {
        // Simulate sending a payment confirmation (e.g., email or SMS)
        $customer = $loan->customer;
        if (!$customer) {
            Log::warning('Customer not found for loan reference: ' . $loan->reference);
            return;
        }

        if($customer->{Customer::COLUMN_EMAIL}) {
            $this->sendEmail(
                to: $customer->{Customer::COLUMN_EMAIL},
                subject: 'Payment Confirmation',
                body: 'Thank you, ' .
                    $customer->{Customer::COLUMN_FIRST_NAME} . ' ' .
                    $customer->{Customer::COLUMN_LAST_NAME} . ', your payment has been received.'
            );
        }

        if($customer->{Customer::COLUMN_PHONE}) {
            $this->sendSms(
                to: $customer->{Customer::COLUMN_PHONE},
                message: 'Thank you, ' .
                    $customer->{Customer::COLUMN_FIRST_NAME} . ' ' .
                    $customer->{Customer::COLUMN_LAST_NAME} . ', your payment has been received.'
            );
        }
    }

    public function sendLoanPaidNotification(Loan $loan)
    {
        $customer = $loan->customer;
        if (!$customer) {
            Log::warning('Customer not found for loan reference: ' . $loan->reference);
            return;
        }

        if($customer->{Customer::COLUMN_EMAIL}) {
            $this->sendEmail(
                to: $customer->{Customer::COLUMN_EMAIL},
                subject: 'Loan Paid',
                body: 'Thank you, ' .
                    $customer->{Customer::COLUMN_FIRST_NAME} . ' ' .
                    $customer->{Customer::COLUMN_LAST_NAME} . ', your loan has been paid in full.'
            );
        }

        if($customer->{Customer::COLUMN_PHONE}) {
            $this->sendSms(
                to: $customer->{Customer::COLUMN_PHONE},
                message: 'Thank you, ' .
                    $customer->{Customer::COLUMN_FIRST_NAME} . ' ' .
                    $customer->{Customer::COLUMN_LAST_NAME} . ', your loan has been paid in full.'
            );
        }
    }

    public function sendRejectedPayments(array $rejected_payments)
    {
        if (empty($rejected_payments)) {
            return;
        }

        $rows = [];
        foreach ($rejected_payments as $payment) {
            $customer_name = $payment['customer_name'] ?? ($payment['payer_name'] ?? '');
            $payment_reference = $payment['payment_reference'] ?? ($payment['ref'] ?? '');
            $loan_reference = $payment['loan_reference'] ?? ($payment['description'] ?? '');
            $amount = isset($payment['amount']) ? number_format((float) $payment['amount'], 2, '.', '') : '';
            $reason = $payment['error'] ?? $payment['code'] ?? '';

            $rows[] = [
                'customer_name' => $customer_name,
                'payment_reference' => $payment_reference,
                'loan_reference' => $loan_reference,
                'amount' => $amount,
                'reason' => $reason,
            ];
        }

        $table_rows_html = '';
        foreach ($rows as $row) {
            $table_rows_html .= '<tr>'
                . '<td style="padding:8px;border:1px solid #ddd;">' . e($row['customer_name']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;">' . e($row['payment_reference']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;">' . e($row['loan_reference']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">' . e($row['amount']) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;">' . e($row['reason']) . '</td>'
                . '</tr>';
        }

        $html_body = '<p>The following payments were rejected during import:</p>'
            . '<table style="border-collapse:collapse;width:100%;">'
            . '<thead><tr>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f6f6f6;text-align:left;">Customer</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f6f6f6;text-align:left;">Payment Reference</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f6f6f6;text-align:left;">Loan Reference</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f6f6f6;text-align:right;">Amount</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f6f6f6;text-align:left;">Reason</th>'
            . '</tr></thead>'
            . '<tbody>'
            . $table_rows_html
            . '</tbody></table>';

        $to = 'support@example.com';
        $subject = 'Rejected payments';

        $this->sendEmail($to, $subject, $html_body);
    }

    private function sendEmail($to, $subject, $body)
    {
        // Simulate sending an email
        Log::info("Email sent to $to with subject '$subject'. Body: $body");
        Mail::html($body, function ($message) use ($to, $subject) {
            $message->to($to)
                    ->subject($subject);
        });
    }

    private function sendSms($to, $message)
    {
        // Simulate sending an SMS
        Log::info("SMS sent to $to. Message: $message");
    }
}
