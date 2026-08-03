<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicPeriodSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            AcademicYear::query()->update(['is_current' => false]);
            Semester::query()->update(['is_current' => false]);

            AcademicYear::updateOrCreate(
                ['name' => '2026-2027'],
                ['is_current' => true]
            );

            Semester::updateOrCreate(
                ['name' => 'First Semester'],
                ['is_current' => true]
            );
        });
    }
}
