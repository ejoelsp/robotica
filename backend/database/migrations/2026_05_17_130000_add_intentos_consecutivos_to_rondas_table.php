<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.rondas')) {
            return;
        }

        Schema::table('catalogo.rondas', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo.rondas', 'intentos_consecutivos')) {
                $table->boolean('intentos_consecutivos')->default(false)->after('cantidad_intentos');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.rondas')) {
            return;
        }

        Schema::table('catalogo.rondas', function (Blueprint $table) {
            if (Schema::hasColumn('catalogo.rondas', 'intentos_consecutivos')) {
                $table->dropColumn('intentos_consecutivos');
            }
        });
    }
};
