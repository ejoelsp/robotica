<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo.categorias', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo.categorias', 'max_integrantes')) {
                $table->unsignedTinyInteger('max_integrantes')
                    ->default(2)
                    ->after('costo_inscripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogo.categorias', function (Blueprint $table) {
            if (Schema::hasColumn('catalogo.categorias', 'max_integrantes')) {
                $table->dropColumn('max_integrantes');
            }
        });
    }
};
