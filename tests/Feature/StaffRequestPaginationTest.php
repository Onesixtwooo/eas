<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRequestPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_groups_limit_loaded_requests_and_page_a_students_full_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_number' => '2026-1001',
            'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1,
        ]);
        $faculty = Faculty::create(['name' => 'Instructor']);
        $subject = Subject::create(['code' => 'S101', 'name' => 'Subject', 'course_id' => $course->id]);
        $year = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);

        for ($i = 1; $i <= 24; $i++) {
            ExcuseRequest::create([
                'student_id' => $student->id, 'subject_id' => $subject->id,
                'facilitator_id' => $faculty->id, 'academic_year_id' => $year->id,
                'semester_id' => $semester->id, 'reason_category_id' => $reason->id,
                'absence_date' => today()->subDays($i), 'explanation' => "Request {$i}",
                'status' => 'submitted', 'submitted_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get(route('requests.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('View all 24 matching requests')
            ->assertViewHas('studentGroups', fn ($groups) => $groups->first()->requests->count() === 10
                && $groups->first()->visible_requests_count === 24);

        $this->get(route('requests.index', ['status' => 'all', 'student_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('historyPages', fn ($pages) => $pages->total() === 24 && $pages->count() === 15);
        $this->get(route('requests.index', ['status' => 'all', 'student_id' => $student->id, 'request_page' => 2]))
            ->assertOk()
            ->assertViewHas('historyPages', fn ($pages) => $pages->total() === 24 && $pages->count() === 9);
    }
}
