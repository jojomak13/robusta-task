<?php

namespace Database\Factories;

use App\Models\Bus;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bus_id' => Bus::query()->inRandomOrder()->first()->id,
            'route_id' => Route::query()->inRandomOrder()->first()->id,
            'moves_at' => $this->faker->dateTimeBetween('now', '+3 days')
        ];
    }
}
