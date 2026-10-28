<?php

namespace Database\Factories;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfile>
 */
class BusinessProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'category' => fake()->word(),
            'location' => fake()->city(),
            'description' => fake()->sentence(),
            'product_type' => fake()->word(),
            'packaging_type' => fake()->word(),
            'preferred_material' => fake()->word(),
            'monthly_quantity' => fake()->numberBetween(100, 10000),
            'product_categories' => fake()->word(),
            'materials' => fake()->word(),
            'minimum_order_quantity' => fake()->numberBetween(100, 10000),
            'monthly_capacity' => fake()->numberBetween(1000, 100000),
            'production_lead_time_days' => fake()->numberBetween(3, 30),
            'onboarding_step' => 3,
            'completed_at' => now(),
        ];
    }
}
