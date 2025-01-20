<?php

namespace Database\Factories;

use App\Models\ConnectionFailure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConnectionFailure>
 */
class ConnectionFailureFactory extends Factory
{
    protected $model = ConnectionFailure::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => $this->faker->url,
            'message' => 'Could not resolve host',
        ];
    }
}
