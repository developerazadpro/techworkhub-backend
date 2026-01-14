<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Skill;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            'electrician',
            'plumber',
            'carpenter',
            'painter',
            'ac-repair',
            'cleaning',
            'gardening',
            'masonry',
        ];

        foreach ($skills as $skillName) {
            Skill::updateOrCreate(
                ['name' => $skillName],
                ['name' => $skillName]
            );
        }
    }
}
