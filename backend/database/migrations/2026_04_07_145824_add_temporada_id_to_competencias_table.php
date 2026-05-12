<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo.competencias', function (Blueprint $table) {
            $table->unsignedBigInteger('temporada_id')->nullable()->after('id');
        });

        DB::statement('
            ALTER TABLE catalogo.competencias
            ADD CONSTRAINT competencias_temporada_id_foreign
            FOREIGN KEY (temporada_id)
            REFERENCES catalogo.temporadas(id)
            ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE catalogo.competencias
            DROP CONSTRAINT IF EXISTS competencias_temporada_id_foreign
        ');

        Schema::table('catalogo.competencias', function (Blueprint $table) {
            $table->dropColumn('temporada_id');
        });
    }
};