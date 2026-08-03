<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@olshco.edu.ph'],
            ['name' => 'System Administrator', 'password' => 'password', 'role' => 'admin', 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'catabona.odie132004@gmail.com'],
            ['name' => 'Odie Catabona', 'password' => '0neSixtwo', 'role' => 'admin', 'is_active' => true]
        );

        $this->call(BsitFacultySubjectSeeder::class);
        $this->call(ReasonCategorySeeder::class);
        $this->call(AcademicPeriodSeeder::class);
    }
}
