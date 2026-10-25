<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => fn () => Plan::query()->firstOrCreate(
                ['code' => Plan::PAS_TAHUNAN_EARLY],
                ['name' => 'Langganan Tahunan (Early Bird)', 'price_sen' => 7900, 'duration_months' => 12],
            )->id,
            'amount_sen' => 7900,
            'payment_method' => PaymentMethod::Chip,
            'status' => OrderStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => ['payment_method' => PaymentMethod::BankTransfer]);
    }
}
