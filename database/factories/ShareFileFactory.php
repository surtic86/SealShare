<?php

namespace Database\Factories;

use App\Models\Share;
use App\Models\ShareFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareFile>
 */
class ShareFileFactory extends Factory
{
    protected $model = ShareFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'share_id' => Share::factory(),
            'original_name' => fake()->word().'.txt',
            'relative_path' => null,
            'stored_path' => 'shares/'.fake()->uuid().'.enc',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'mime_type' => 'text/plain',
        ];
    }
}
