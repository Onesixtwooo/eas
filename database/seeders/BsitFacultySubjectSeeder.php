<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\InstructorAssignment;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class BsitFacultySubjectSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::updateOrCreate(
            ['code' => 'BSIT'],
            ['name' => 'BS Information Technology', 'is_active' => true]
        );

        foreach (range(1, 5) as $year) {
            foreach (range('A', 'G') as $block) {
                Section::firstOrCreate(
                    ['course_id' => $course->id, 'year_level' => $year, 'name' => $block],
                    ['is_active' => true]
                );
            }
        }

        foreach ($this->assignments() as $row) {
            $faculty = Faculty::updateOrCreate(
                ['name' => $row['instructor']],
                ['designation' => 'Course Facilitator']
            );
            $subject = Subject::updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['subject'], 'course_id' => $course->id, 'year_level' => $row['year'], 'is_active' => true]
            );

            $blocks = $row['blocks'] ?? [null];
            foreach ($blocks as $block) {
                $sectionId = $block
                    ? Section::where('course_id', $course->id)->where('year_level', $row['year'])->where('name', $block)->value('id')
                    : null;

                InstructorAssignment::updateOrCreate([
                    'faculty_id' => $faculty->id,
                    'course_id' => $course->id,
                    'subject_id' => $subject->id,
                    'year_level' => $row['year'],
                    'section_id' => $sectionId,
                ], ['is_active' => true]);
            }
        }
    }

    private function assignments(): array
    {
        return [
            ['instructor'=>'Mr. Jerick C. Barnatia','code'=>'FL101','subject'=>'Foreign Language','year'=>4],
            ['instructor'=>'Mr. Jerick C. Barnatia','code'=>'MM101','subject'=>'Multimedia Systems','year'=>3],
            ['instructor'=>'Ms. Princess Lea Ann D. Calina, MSIT','code'=>'CC101','subject'=>'Introduction to Computing','year'=>1],
            ['instructor'=>'Ms. Princess Lea Ann D. Calina, MSIT','code'=>'GE12','subject'=>'The Entrepreneurial Mind for Information Technology','year'=>4],
            ['instructor'=>'Mr. Odiether A. Catabona','code'=>'CC105','subject'=>'Information Management','year'=>2],
            ['instructor'=>'Mr. Odiether A. Catabona','code'=>'PF101','subject'=>'Object-Oriented Programming','year'=>2],
            ['instructor'=>'Mr. Odiether A. Catabona','code'=>'PT101','subject'=>'Platform Technologies','year'=>2],
            ['instructor'=>'Mr. Odiether A. Catabona','code'=>'CC106','subject'=>'Application Development and Emerging Technologies','year'=>3,'blocks'=>['A']],
            ['instructor'=>'Mr. Lorenz C. Del Rosario','code'=>'GE1','subject'=>'Understanding the Self','year'=>1],
            ['instructor'=>'Ms. Hazel T. Figueroa','code'=>'HCI102','subject'=>'Human Computer Interaction 2','year'=>3],
            ['instructor'=>'Ms. Hazel T. Figueroa','code'=>'MS101','subject'=>'Discrete Structures','year'=>2],
            ['instructor'=>'Ms. Hazel T. Figueroa','code'=>'NET102','subject'=>'Networking 2','year'=>3,'blocks'=>['A']],
            ['instructor'=>'Ms. Hazel T. Figueroa','code'=>'SPT5','subject'=>'VSS Specialization 5','year'=>4],
            ['instructor'=>'Mr. Vladimir B. Figueroa','code'=>'SIA101','subject'=>'Systems Integration and Architecture','year'=>3],
            ['instructor'=>'Mr. Vladimir B. Figueroa','code'=>'IAS101','subject'=>'Information Assurance and Security 1','year'=>3],
            ['instructor'=>'Mr. Vladimir B. Figueroa','code'=>'SPT1','subject'=>'Specialization 1: Cybersecurity','year'=>4],
            ['instructor'=>'Mr. Vladimir B. Figueroa','code'=>'CC106','subject'=>'Application Development and Emerging Technologies','year'=>3,'blocks'=>['B']],
            ['instructor'=>'Mr. Vladimir B. Figueroa','code'=>'SA101','subject'=>'Systems Administration and Maintenance','year'=>3],
            ['instructor'=>'Mrs. Rose Ann Jade V. Gloria','code'=>'GE8','subject'=>'Art Appreciation','year'=>2],
            ['instructor'=>'Mrs. Rose Ann Jade V. Gloria','code'=>'GE10','subject'=>'Readings in Philippine History with Indigenous People','year'=>2],
            ['instructor'=>'Ms. Madel Meriales','code'=>'PE1','subject'=>'Movement Competency Training','year'=>1],
            ['instructor'=>'Mr. Jose Monteverde','code'=>'REED1','subject'=>'Salvation History','year'=>1],
            ['instructor'=>'Ms. Divine Paraiso','code'=>'GE3','subject'=>'Ethics','year'=>1,'blocks'=>['A','B']],
            ['instructor'=>'Ms. Kyla Melissa A. Pascual','code'=>'GE3','subject'=>'Ethics','year'=>1,'blocks'=>['C','D']],
            ['instructor'=>'Mr. Diosdado A. Reyes','code'=>'NSTP1','subject'=>'National Service Training Program 1','year'=>1],
            ['instructor'=>'Mr. Edward Reolente','code'=>'PE3','subject'=>'Choice of Dance, Sports, Martial Arts, and Group Exercises','year'=>2],
            ['instructor'=>'Ms. Romabelle Cheline Sawit','code'=>'IPT102','subject'=>'Integrative Programming and Technologies 2','year'=>3],
            ['instructor'=>'Ms. Romabelle Cheline Sawit','code'=>'NET102','subject'=>'Networking 2','year'=>3,'blocks'=>['B']],
            ['instructor'=>'Mr. Jeffrey B. Santiago','code'=>'GE2','subject'=>'Gender and Society','year'=>1,'blocks'=>['D']],
            ['instructor'=>'Mr. Joseph V. Agustin','code'=>'GE2','subject'=>'Gender and Society','year'=>1,'blocks'=>['A','B','C']],
            ['instructor'=>'Rev. Fr. Renz Jane S. Valente','code'=>'REED3','subject'=>'Ecclesiology','year'=>2],
            ['instructor'=>'Mr. Elizor M. Villanueva, LPT','code'=>'CC102','subject'=>'Computer Programming 1','year'=>1],
            ['instructor'=>'Mr. Elizor M. Villanueva, LPT','code'=>'IM101','subject'=>'Fundamentals of Database Systems','year'=>2],
            ['instructor'=>'Mr. Elizor M. Villanueva, LPT','code'=>'CAP102','subject'=>'Capstone Project and Research 2','year'=>4],
            ['instructor'=>'Mr. Joel P. Altura','code'=>'MATHPLUS','subject'=>'Mathematics for Information Technology','year'=>1],
            ['instructor'=>'Mr. Joel P. Altura','code'=>'ENGPLUS','subject'=>'English for Information Technology','year'=>1],
            ['instructor'=>'Mr. Joel P. Altura','code'=>'WS102','subject'=>'Web Systems and Technologies 2','year'=>3],
            ['instructor'=>'Mr. Joel P. Altura','code'=>'SPT2','subject'=>'Fundamentals of Mobile Programming','year'=>4],
            ['instructor'=>'Mr. Joel P. Altura','code'=>'SP102','subject'=>'Social and Professional Issues 2','year'=>3],
        ];
    }
}
