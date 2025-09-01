<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    private function sendEmail($to, $subject, $body)
    {
        // Simulate sending an email
        Log::info("Email sent to $to with subject '$subject'. Body: $body");
        Mail::raw($body, function ($message) use ($to, $subject) {
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
