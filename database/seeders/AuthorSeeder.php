<?php

namespace Database\Seeders;

use App\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    public function run(): void
    {
        $team = [
            'Muhammad Ahsan' => 'Founder & Technology Lead',
            'Muzammil Ahmed' => 'Project Coordinator & Full Stack Developer',
            'Ibad Rajput' => 'Full Stack Engineer',
            'Maleeha Asif' => 'Graphic Designer',
            'Muhammad Khizar Khan' => 'Senior App Developer',
            'Mang Eberechimerem' => 'Business Development Specialist',
            'Jawad Ahmed' => 'Senior Full Stack Developer',
        ];

        foreach ($team as $name => $bio) {
            Author::firstOrCreate(['name' => $name], ['bio' => $bio]);
        }
    }
}
