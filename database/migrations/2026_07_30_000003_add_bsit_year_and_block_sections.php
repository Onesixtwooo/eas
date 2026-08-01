<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $courseId = DB::table('courses')->where('code', 'BSIT')->value('id');

        if (! $courseId) {
            return;
        }

        $now = now();

        foreach (range(1, 5) as $yearLevel) {
            foreach (range('A', 'G') as $block) {
                $exists = DB::table('sections')
                    ->where('course_id', $courseId)
                    ->where('year_level', $yearLevel)
                    ->where('name', $block)
                    ->exists();

                if (! $exists) {
                    DB::table('sections')->insert([
                        'course_id' => $courseId,
                        'name' => $block,
                        'year_level' => $yearLevel,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $courseId = DB::table('courses')->where('code', 'BSIT')->value('id');

        if ($courseId) {
            DB::table('sections')
                ->where('course_id', $courseId)
                ->whereBetween('year_level', [1, 5])
                ->whereIn('name', range('A', 'G'))
                ->whereNotIn('id', DB::table('students')->select('section_id'))
                ->delete();
        }
    }
};
