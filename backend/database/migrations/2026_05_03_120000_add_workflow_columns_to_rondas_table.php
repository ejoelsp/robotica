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
            if (! Schema::hasColumn('catalogo.rondas', 'tipo')) {
                $table->string('tipo', 30)->default('libre');
            }

            if (! Schema::hasColumn('catalogo.rondas', 'estado')) {
                $table->string('estado', 30)->default('borrador');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.rondas')) {
            return;
        }

        Schema::table('catalogo.rondas', function (Blueprint $table) {
            if (Schema::hasColumn('catalogo.rondas', 'estado')) {
                $table->dropColumn('estado');
            }

            if (Schema::hasColumn('catalogo.rondas', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
