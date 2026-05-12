<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE catalogo.equipos
            ALTER COLUMN capitan_user_id DROP NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE catalogo.equipos
            ALTER COLUMN capitan_user_id SET NOT NULL
        ');
    }
};