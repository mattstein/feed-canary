<?php

namespace Database\Factories;

use App\Models\Feed;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FeedFactory extends Factory
{
    protected $model = Feed::class;

    public function definition(): array
    {
        return [
            'url' => 'https://'.$this->faker->domainName().'/feed',
            'email' => $this->faker->unique()->safeEmail(),
            'type' => $this->faker->randomElement([
                'application/xml',
                'application/rss+xml',
                'application/atom+xml',
                'application/json',
                'application/x-rss+xml',
                'text/xml',
            ]),
            'status' => Feed::STATUS_HEALTHY,
            'confirmed' => true,
            'last_checked' => now(),
            'last_notified' => null,
            'confirmation_code' => null,
        ];
    }

    public function failing(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Feed::STATUS_FAILING,
            ];
        });
    }

    public function unconfirmed(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'confirmed' => false,
                'confirmation_code' => Str::random(),
            ];
        });
    }
}
