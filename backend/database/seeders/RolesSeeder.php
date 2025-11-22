<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'juez', 'competidor'];

        foreach ($roles as $rol) {
            DB::table('seguridad.roles')->updateOrInsert(
                ['nombre' => $rol],
                ['nombre' => $rol, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}

