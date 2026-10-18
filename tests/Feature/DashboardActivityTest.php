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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_students_are_distinct_before_the_limit_and_analytics_count_all_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $students = collect([1, 2])->map(function ($number) use ($course, $section) {
            $user = User::factory()->create(['role' => 'student', 'name' => "Student {$number}"]);

            return Student::create(['user_id' => $user->id, 'student_number' => "2026-{$number}",
                'course_id' => $course->id, 'section_id' => $section->id, 'year_level' => 1]);
        });
        $faculty = Faculty::create(['name' => 'Instructor']);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Programming', 'course_id' => $course->id]);
        $year = AcademicYear::create(['name' => '2026-2027']);
        $semester = Semester::create(['name' => 'First']);
        $reason = ReasonCategory::create(['name' => 'Illness']);
        $base = ['subject_id' => $subject->id, 'facilitator_id' => $faculty->id,
            'academic_year_id' => $year->id, 'semester_id' => $semester->id,
            'absence_date' => today()->subDay(), 'reason_category_id' => $reason->id,
            'explanation' => 'Illness', 'status' => 'approved', 'slip_remark' => 'EXCUSED',
            'submitted_at' => now(), 'approved_at' => now()];

        ExcuseRequest::create($base + ['student_id' => $students[1]->id,
            'created_at' => now()->subDays(2)]);
        for ($i = 0; $i < 45; $i++) {
            ExcuseRequest::create($base + ['student_id' => $students[0]->id,
                'created_at' => now()->subMinutes($i)]);
        }

        $analyticsQueries = 0;
        DB::listen(function ($query) use (&$analyticsQueries) {
            if (str_contains($query->sql, 'day_0_late')) $analyticsQueries++;
        });

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('requests', fn ($requests) => $requests->count() === 2
                && $requests->pluck('student_id')->sort()->values()->all() === $students->pluck('id')->sort()->values()->all())
            ->assertViewHas('analyticsByPeriod', function ($periods) {
                $today = $periods['day']->last();

                return $today['late'] === 2 && $today['excused'] === 2
                    && $today['confirmed'] === 46 && $today['completed'] === 0;
            });
        $this->assertSame(1, $analyticsQueries);
    }

    public function test_excused_counts_follow_the_latest_slip_amendment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 1]);
        $faculty = Faculty::create(['name' => 'Instructor']);
        $subject = Subject::create(['code' => 'IT101', 'name' => 'Programming', 'course_id' => $course->id]);
        $year = AcademicYear::create(['name' => '2026-2027']);
        $semester = Semester::create(['name' => 'First']);
        $reason = ReasonCategory::create(['name' => 'Illness']);
        $base = [
            'subject_id' => $subject->id, 'facilitator_id' => $faculty->id,
            'academic_year_id' => $year->id, 'semester_id' => $semester->id,
            'absence_date' => today()->subDay(), 'reason_category_id' => $reason->id,
            'explanation' => 'Illness', 'status' => 'approved',
            'submitted_at' => now(), 'approved_at' => now(),
        ];
        $student = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'student_number' => '2026-1', 'course_id' => $course->id,
            'section_id' => $section->id, 'year_level' => 1,
        ]);
        $request = ExcuseRequest::create($base + [
            'student_id' => $student->id, 'slip_remark' => 'EXCUSED',
        ]);
        $request->slipAmendments()->create(['slip_remark' => 'CONDITIONAL', 'remarks' => 'Revised result.']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('excusedStudents', 0)
            ->assertViewHas('analyticsByPeriod', fn ($periods) => $periods['day']->last()['excused'] === 0);

        $request->slipAmendments()->create(['slip_remark' => 'EXCUSED', 'remarks' => 'Final result.']);
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('excusedStudents', 1)
            ->assertViewHas('analyticsByPeriod', fn ($periods) => $periods['day']->last()['excused'] === 1);
    }
}
