<?php

namespace Database\Factories;

use App\Models\Check;
use App\Models\Feed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Check>
 */
class CheckFactory extends Factory
{
    protected $model = Check::class;

    public function definition(): array
    {
        return [
            'feed_id' => Feed::factory(),
            'status' => 200,
            'headers' => [],
            'hash' => null,
            'is_valid' => false,
        ];
    }
}
