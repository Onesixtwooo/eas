<?php

namespace Tests\Feature;

use App\Models\InstructorAssignment;
use App\Models\Section;
use Database\Seeders\BsitFacultySubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BsitFacultySubjectSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_subjects_instructors_and_block_specific_assignments(): void
    {
        $this->seed(BsitFacultySubjectSeeder::class);

        $this->assertDatabaseCount('subjects', 35);
        $this->assertDatabaseCount('faculty', 19);

        $sectionA = Section::where('year_level', 3)->where('name', 'A')->firstOrFail();
        $sectionB = Section::where('year_level', 3)->where('name', 'B')->firstOrFail();

        $assignmentA = InstructorAssignment::with(['faculty', 'subject'])
            ->where('section_id', $sectionA->id)->whereHas('subject', fn ($query) => $query->where('code', 'CC106'))->firstOrFail();
        $assignmentB = InstructorAssignment::with(['faculty', 'subject'])
            ->where('section_id', $sectionB->id)->whereHas('subject', fn ($query) => $query->where('code', 'CC106'))->firstOrFail();

        $this->assertSame('Mr. Odiether A. Catabona', $assignmentA->faculty->name);
        $this->assertSame('Mr. Vladimir B. Figueroa', $assignmentB->faculty->name);
    }
}
