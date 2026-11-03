<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappConnection>
 */
class WhatsappConnectionFactory extends Factory
{
    protected $model = WhatsappConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $waId = fake()->unique()->numerify('57300#######');

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'wa_id' => $waId,
            'active_wa_id' => $waId,
            'phone_number_id' => fake()->numerify('###########'),
            'assistant_conversation_id' => null,
            'verified_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active_wa_id' => null,
            'revoked_at' => now(),
        ]);
    }
}
