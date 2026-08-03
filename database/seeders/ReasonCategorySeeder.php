<?php

namespace Database\Seeders;

use App\Models\ReasonCategory;
use Illuminate\Database\Seeder;

class ReasonCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Illness or Medical Condition',
            'Medical or Dental Appointment',
            'Family Emergency',
            'Bereavement',
            'Official School Activity',
            'Religious Activity',
            'Transportation Problem',
            'Severe Weather or Natural Disaster',
            'Personal Emergency',
            'Other',
        ];

        foreach ($categories as $name) {
            ReasonCategory::updateOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
