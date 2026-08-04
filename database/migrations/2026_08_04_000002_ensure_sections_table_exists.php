<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sections')) {
            Schema::create('sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->unsignedTinyInteger('year_level');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['course_id', 'year_level', 'name']);
            });
        }

        $courseId = DB::table('courses')->where('code', 'BSIT')->value('id');

        if (! $courseId) {
            return;
        }

        $now = now();

        foreach (range(1, 5) as $yearLevel) {
            foreach (range('A', 'G') as $block) {
                DB::table('sections')->updateOrInsert(
                    [
                        'course_id' => $courseId,
                        'year_level' => $yearLevel,
                        'name' => $block,
                    ],
                    [
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // This is a repair migration; do not remove a table that may contain student data.
    }
};
