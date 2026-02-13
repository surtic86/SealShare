<?php

namespace Database\Factories;

use App\Models\Share;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Share>
 */
class ShareFactory extends Factory
{
    protected $model = Share::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(16),
            'password' => null,
            'encryption_key' => null,
            'encryption_salt' => bin2hex(random_bytes(32)),
            'expires_at' => null,
            'max_downloads' => null,
            'download_count' => 0,
            'total_size' => 0,
        ];
    }

    public function withPassword(string $password = 'secret'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => bcrypt($password),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function withMaxDownloads(int $max = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'max_downloads' => $max,
        ]);
    }

    public function expiresInHours(int $hours = 24): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addHours($hours),
        ]);
    }
}
