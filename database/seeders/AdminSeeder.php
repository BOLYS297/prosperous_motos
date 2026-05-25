<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // admin
        User::updateOrCreate(
            ['nom_utilisateur' => 'admin'],
            [
                'email' => 'admin@quincaillerie.test',
                'password' => bcrypt('admin123'),
                'role' => 'super_admin',
            ]
        );
    }
}
