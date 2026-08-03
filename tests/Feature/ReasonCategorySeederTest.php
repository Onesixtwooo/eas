<?php

namespace Tests\Feature;

use Database\Seeders\ReasonCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReasonCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_active_reason_categories_without_duplicates(): void
    {
        $this->seed(ReasonCategorySeeder::class);
        $this->seed(ReasonCategorySeeder::class);

        $this->assertDatabaseCount('reason_categories', 10);
        $this->assertDatabaseHas('reason_categories', ['name' => 'Illness or Medical Condition', 'is_active' => true]);
        $this->assertDatabaseHas('reason_categories', ['name' => 'Other', 'is_active' => true]);
    }
}
