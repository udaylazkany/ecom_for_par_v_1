<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class Super_Admin extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create(['name'=>'ITE','email'=>'ITE@gmail.com','password'=>'12345678','role'=>'admin']);
    }
}
