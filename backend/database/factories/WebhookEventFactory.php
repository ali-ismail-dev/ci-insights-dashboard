<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => null,
            'provider' => 'github',
            'event_type' => $this->faker->randomElement(['pull_request', 'check_run', 'status', 'push']),
            'action' => null,
            'delivery_id' => (string) Str::uuid(),
            'signature' => $this->faker->sha256,
            'signature_verified' => true,
            'verified_at' => now(),
            'payload' => ['dummy' => true],
            'status' => 'pending',
            'source_ip' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'headers' => [],
        ];
    }
}
