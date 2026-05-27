<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.categorias')) {
            return;
        }

        Schema::table('catalogo.categorias', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo.categorias', 'estado_resultados')) {
                $table->string('estado_resultados', 30)->default('pendiente');
            }

            if (! Schema::hasColumn('catalogo.categorias', 'resultados_finalizados_at')) {
                $table->timestamp('resultados_finalizados_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.categorias')) {
            return;
        }

        Schema::table('catalogo.categorias', function (Blueprint $table) {
            if (Schema::hasColumn('catalogo.categorias', 'resultados_finalizados_at')) {
                $table->dropColumn('resultados_finalizados_at');
            }

            if (Schema::hasColumn('catalogo.categorias', 'estado_resultados')) {
                $table->dropColumn('estado_resultados');
            }
        });
    }
};
