<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount_issued = fake()->randomFloat(2, 100, 1000);

        return [
            'id' => fake()->uuid(),
            'customer_id' => Customer::factory()->create()->id,
            'reference' => 'LN' . fake()->numerify('########'),
            'state' => 'ACTIVE',
            'amount_issued' => $amount_issued,
            'amount_to_pay' => $amount_issued * 1.2,
        ];
    }
}
