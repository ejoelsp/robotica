<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo.competencias', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo.competencias', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('imagen_url');
            }
        });

        Schema::table('resultados.incidencias', function (Blueprint $table) {
            if (! Schema::hasColumn('resultados.incidencias', 'codigo')) {
                $table->string('codigo', 50)->nullable()->unique();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'estado')) {
                $table->string('estado', 50)->default('pendiente')->index();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'fecha_envio')) {
                $table->timestamp('fecha_envio')->nullable();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'pdf_path')) {
                $table->string('pdf_path', 500)->nullable();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'jueces_snapshot')) {
                $table->jsonb('jueces_snapshot')->nullable();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'equipo_snapshot')) {
                $table->jsonb('equipo_snapshot')->nullable();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'integrantes_snapshot')) {
                $table->jsonb('integrantes_snapshot')->nullable();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'prototipo_nombre')) {
                $table->string('prototipo_nombre', 255)->nullable();
            }

            if (! Schema::hasColumn('resultados.incidencias', 'institucion')) {
                $table->string('institucion', 255)->nullable();
            }
        });

        DB::statement("
            ALTER TABLE resultados.incidencias
            DROP CONSTRAINT IF EXISTS incidencias_tipo_check
        ");

        DB::statement("
            ALTER TABLE resultados.incidencias
            ADD CONSTRAINT incidencias_tipo_check
            CHECK (tipo IN ('observacion', 'penalizacion', 'reclamo'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE resultados.incidencias
            DROP CONSTRAINT IF EXISTS incidencias_tipo_check
        ");

        DB::statement("
            ALTER TABLE resultados.incidencias
            ADD CONSTRAINT incidencias_tipo_check
            CHECK (tipo IN ('observacion', 'penalizacion'))
        ");

        Schema::table('resultados.incidencias', function (Blueprint $table) {
            $table->dropColumn([
                'codigo',
                'estado',
                'fecha_envio',
                'pdf_path',
                'jueces_snapshot',
                'equipo_snapshot',
                'integrantes_snapshot',
                'prototipo_nombre',
                'institucion',
            ]);
        });

        Schema::table('catalogo.competencias', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });
    }
};
