<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_refund_was_created_on_overpayment(): void
    {
        $loan = Loan::factory()->create([
            'amount_to_pay' => '100.00',
            'amount_paid' => '90.00',
            'state' => Loan::STATE_ACTIVE,
        ]);

        $payload = [
            'paymentDate' => Carbon::now()->toIso8601String(),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'amount' => '20.50', // overpay by 10.50
            'description' => $loan->{Loan::COLUMN_REFERENCE},
            'refId' => fake()->uuid(),
        ];

        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $loan->refresh();
        $this->assertEquals(Loan::STATE_PAID, $loan->state);

        $this->assertDatabaseHas('refunds', [
            'payment_reference' => $payload['refId'],
            'amount' => '10.50',
            'status' => Refund::STATUS_PENDING,
        ]);
    }

    public function test_missing_column_is_rejected(): void
    {
        $loan = Loan::factory()->create([
            'amount_to_pay' => '100.00',
            'amount_paid' => '0.00',
            'state' => Loan::STATE_ACTIVE,
        ]);

        foreach(['paymentDate', 'firstname', 'lastname', 'amount', 'description', 'refId'] as $missing_field) {
            $payload = [
                'paymentDate' => Carbon::now()->toIso8601String(),
                'firstname' => 'John',
                'lastname' => 'Doe',
                'amount' => '50.00',
                'description' => $loan->{Loan::COLUMN_REFERENCE},
                'refId' => fake()->uuid(),
            ];
            unset($payload[$missing_field]);

            $this->postJson('api/payment/store', $payload)
                ->assertStatus(400)
                ->assertJsonValidationErrors([$missing_field]);
        }
    }

    public function test_duplicate_is_rejected(): void
    {
        $loan = Loan::factory()->create([
            'amount_to_pay' => '100.00',
            'amount_paid' => '0.00',
            'state' => Loan::STATE_ACTIVE,
        ]);

        $payload = [
            'paymentDate' => Carbon::now()->toIso8601String(),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'amount' => '50.00',
            'description' => $loan->{Loan::COLUMN_REFERENCE},
            'refId' => fake()->uuid(),
        ];

        // First attempt should succeed
        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        // Second attempt with same refId should fail with 409 Conflict
        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(409)
            ->assertJsonValidationErrors(['refId']);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $loan = Loan::factory()->create([
            'amount_to_pay' => '100.00',
            'amount_paid' => '0.00',
            'state' => Loan::STATE_ACTIVE,
        ]);

        $payload = [
            'paymentDate' => 'invalid-date-format',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'amount' => '50.00',
            'description' => $loan->{Loan::COLUMN_REFERENCE},
            'refId' => fake()->uuid(),
        ];

        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(400)
            ->assertJsonValidationErrors(['paymentDate']);
    }

    public function test_nonexistent_loan_is_rejected(): void
    {
        $payload = [
            'paymentDate' => Carbon::now()->toIso8601String(),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'amount' => '50.00',
            'description' => 'LN999999999', // Non-existent loan reference
            'refId' => fake()->uuid(),
        ];

        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_inactive_loan_is_rejected(): void
    {
        $loan = Loan::factory()->create([
            'amount_to_pay' => '100.00',
            'amount_paid' => '100.00',
            'state' => Loan::STATE_PAID,
        ]);

        $payload = [
            'paymentDate' => Carbon::now()->toIso8601String(),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'amount' => '50.00',
            'description' => $loan->{Loan::COLUMN_REFERENCE},
            'refId' => fake()->uuid(),
        ];

        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $loan = Loan::factory()->create([
            'amount_to_pay' => '100.00',
            'amount_paid' => '0.00',
            'state' => Loan::STATE_ACTIVE,
        ]);

        $payload = [
            'paymentDate' => Carbon::now()->toIso8601String(),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'amount' => '-10.00', // Negative amount
            'description' => $loan->{Loan::COLUMN_REFERENCE},
            'refId' => fake()->uuid(),
        ];

        $this->postJson('/api/payment/store', $payload)
            ->assertStatus(400)
            ->assertJsonValidationErrors(['amount']);
    }
}

