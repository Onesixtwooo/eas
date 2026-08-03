<?php

namespace Tests\Feature;

use Database\Seeders\AcademicPeriodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPeriodSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_one_current_academic_year_and_semester(): void
    {
        $this->seed(AcademicPeriodSeeder::class);
        $this->seed(AcademicPeriodSeeder::class);

        $this->assertDatabaseHas('academic_years', ['name' => '2026-2027', 'is_current' => true]);
        $this->assertDatabaseHas('semesters', ['name' => 'First Semester', 'is_current' => true]);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('semesters', 1);
    }
}
