<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExcuseRequest;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\ReasonCategory;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestPlacementHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_keeps_old_requests_in_their_original_year_and_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $secondYear = Section::create(['course_id' => $course->id, 'name' => 'A', 'year_level' => 2, 'is_active' => true]);
        $thirdYear = Section::create(['course_id' => $course->id, 'name' => 'B', 'year_level' => 3, 'is_active' => true]);
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_number' => '26-4001',
            'course_id' => $course->id, 'section_id' => $secondYear->id, 'year_level' => 2,
        ]);
        $faculty = Faculty::create(['name' => 'Instructor', 'designation' => 'Course Facilitator']);
        $yearTwoSubject = Subject::create(['code' => 'Y2-101', 'name' => 'Year Two Subject', 'course_id' => $course->id, 'year_level' => 2]);
        $yearThreeSubject = Subject::create(['code' => 'Y3-101', 'name' => 'Year Three Subject', 'course_id' => $course->id, 'year_level' => 3]);
        foreach ([[$yearTwoSubject, $secondYear], [$yearThreeSubject, $thirdYear]] as [$subject, $section]) {
            InstructorAssignment::create([
                'faculty_id' => $faculty->id, 'course_id' => $course->id,
                'subject_id' => $subject->id, 'year_level' => $section->year_level,
                'section_id' => $section->id, 'is_active' => true,
            ]);
        }
        AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        Semester::create(['name' => 'First Semester', 'is_current' => true]);
        $reason = ReasonCategory::create(['name' => 'Illness', 'is_active' => true]);

        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => today()->subDay()->toDateString(),
            'subject_ids' => [$yearTwoSubject->id],
            'reason_category_id' => $reason->id,
            'explanation' => 'I was unable to attend the class due to illness.',
            'declaration' => '1', 'intent' => 'submit',
        ])->assertSessionHasNoErrors();
        $oldRequest = ExcuseRequest::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('BSIT 2-A', $oldRequest->placementLabel());

        $this->actingAs($admin)->patch(route('admin.students.academic-placement.update', $student), [
            'year_level' => 3, 'section_id' => $thirdYear->id,
        ])->assertSessionHasNoErrors();
        $studentUser->unsetRelation('student');

        $this->actingAs($studentUser)->post(route('requests.store'), [
            'absence_date' => today()->toDateString(),
            'subject_ids' => [$yearThreeSubject->id],
            'reason_category_id' => $reason->id,
            'explanation' => 'I was unable to attend the class due to illness.',
            'declaration' => '1', 'intent' => 'submit',
        ])->assertSessionHasNoErrors();
        $newRequest = ExcuseRequest::where('student_id', $student->id)->whereKeyNot($oldRequest->id)->firstOrFail();

        $this->assertSame('BSIT 2-A', $oldRequest->fresh()->placementLabel());
        $this->assertSame('BSIT 3-B', $newRequest->placementLabel());
        $this->actingAs($studentUser)->get(route('requests.edit', $oldRequest))->assertForbidden();
        $this->patch(route('requests.update', $oldRequest), [
            'absence_date' => today()->subDay()->toDateString(),
            'subject_ids' => [$yearThreeSubject->id],
            'reason_category_id' => $reason->id,
            'explanation' => 'Attempted change to a current year subject.',
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('requests.index', ['year_level' => 2, 'status' => 'all']))
            ->assertOk()
            ->assertViewHas('studentGroups', fn ($groups) => $groups->count() === 1
                && $groups->first()->requests->modelKeys() === [$oldRequest->id]
                && $groups->first()->requests_count === 1)
            ->assertSee('BSIT 2-A');
        $this->get(route('requests.index', ['year_level' => 3, 'status' => 'all']))
            ->assertOk()
            ->assertViewHas('studentGroups', fn ($groups) => $groups->count() === 1
                && $groups->first()->requests->modelKeys() === [$newRequest->id]
                && $groups->first()->requests_count === 1)
            ->assertSee('BSIT 3-B');
        $this->get(route('requests.show', $oldRequest))->assertOk()->assertSee('BSIT 2-A');
    }
}
