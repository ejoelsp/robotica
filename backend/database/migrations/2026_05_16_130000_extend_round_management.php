<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalogo.rondas')) {
            Schema::table('catalogo.rondas', function (Blueprint $table) {
                if (Schema::hasColumn('catalogo.rondas', 'fecha_hora')) {
                    $table->dropColumn('fecha_hora');
                }

                if (! Schema::hasColumn('catalogo.rondas', 'orden')) {
                    $table->unsignedInteger('orden')->default(1)->after('tipo');
                }

                if (! Schema::hasColumn('catalogo.rondas', 'cantidad_intentos')) {
                    $table->unsignedInteger('cantidad_intentos')->default(1)->after('orden');
                }

                if (! Schema::hasColumn('catalogo.rondas', 'clasifican_cantidad')) {
                    $table->unsignedInteger('clasifican_cantidad')->nullable()->after('cantidad_intentos');
                }

                if (! Schema::hasColumn('catalogo.rondas', 'criterio_clasificacion')) {
                    $table->string('criterio_clasificacion', 40)->default('mayor_puntaje')->after('clasifican_cantidad');
                }

                if (! Schema::hasColumn('catalogo.rondas', 'ronda_origen_id')) {
                    $table->unsignedBigInteger('ronda_origen_id')->nullable()->after('criterio_clasificacion');
                }

                if (! Schema::hasColumn('catalogo.rondas', 'es_final')) {
                    $table->boolean('es_final')->default(false)->after('ronda_origen_id');
                }
            });

            DB::statement('CREATE INDEX IF NOT EXISTS rondas_categoria_orden_index ON catalogo.rondas (categoria_id, orden)');

            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'rondas_ronda_origen_id_foreign'
                    ) THEN
                        ALTER TABLE catalogo.rondas
                        ADD CONSTRAINT rondas_ronda_origen_id_foreign
                        FOREIGN KEY (ronda_origen_id)
                        REFERENCES catalogo.rondas(id)
                        ON UPDATE CASCADE
                        ON DELETE SET NULL;
                    END IF;
                END
                $$;
            ");
        }

        if (! Schema::hasTable('resultados.ronda_participantes')) {
            Schema::create('resultados.ronda_participantes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ronda_id');
                $table->unsignedBigInteger('inscripcion_id');
                $table->unsignedBigInteger('equipo_id');
                $table->string('estado', 30)->default('pendiente');
                $table->unsignedBigInteger('origen_clasificacion_id')->nullable();
                $table->timestamps();

                $table->unique(['ronda_id', 'inscripcion_id'], 'ronda_participantes_ronda_inscripcion_unique');
                $table->index(['ronda_id', 'estado'], 'ronda_participantes_ronda_estado_index');
            });

            DB::statement("
                ALTER TABLE resultados.ronda_participantes
                ADD CONSTRAINT ronda_participantes_ronda_id_foreign
                FOREIGN KEY (ronda_id)
                REFERENCES catalogo.rondas(id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
            ");

            DB::statement("
                ALTER TABLE resultados.ronda_participantes
                ADD CONSTRAINT ronda_participantes_inscripcion_id_foreign
                FOREIGN KEY (inscripcion_id)
                REFERENCES vinculaciones.inscripciones(id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
            ");

            DB::statement("
                ALTER TABLE resultados.ronda_participantes
                ADD CONSTRAINT ronda_participantes_equipo_id_foreign
                FOREIGN KEY (equipo_id)
                REFERENCES catalogo.equipos(id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
            ");

            DB::statement("
                ALTER TABLE resultados.ronda_participantes
                ADD CONSTRAINT ronda_participantes_origen_clasificacion_id_foreign
                FOREIGN KEY (origen_clasificacion_id)
                REFERENCES resultados.clasificaciones(id)
                ON UPDATE CASCADE
                ON DELETE SET NULL
            ");
        }

        if (Schema::hasTable('resultados.resultados')) {
            DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_estado_check');
            DB::statement("
                ALTER TABLE resultados.resultados
                ADD CONSTRAINT resultados_estado_check
                CHECK (estado IN ('borrador', 'pendiente', 'registrado', 'publicado', 'no_participa', 'no_se_presento', 'descalificado', 'anulado'))
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('resultados.resultados')) {
            DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_estado_check');
            DB::statement("
                ALTER TABLE resultados.resultados
                ADD CONSTRAINT resultados_estado_check
                CHECK (estado IN ('borrador', 'registrado', 'publicado', 'anulado'))
            ");
        }

        Schema::dropIfExists('resultados.ronda_participantes');

        if (Schema::hasTable('catalogo.rondas')) {
            DB::statement('ALTER TABLE catalogo.rondas DROP CONSTRAINT IF EXISTS rondas_ronda_origen_id_foreign');
            DB::statement('DROP INDEX IF EXISTS catalogo.rondas_categoria_orden_index');

            Schema::table('catalogo.rondas', function (Blueprint $table) {
                $columns = [
                    'es_final',
                    'ronda_origen_id',
                    'criterio_clasificacion',
                    'clasifican_cantidad',
                    'cantidad_intentos',
                    'orden',
                ];

                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column) => Schema::hasColumn('catalogo.rondas', $column)
                ));

                if (! empty($existing)) {
                    $table->dropColumn($existing);
                }

                if (! Schema::hasColumn('catalogo.rondas', 'fecha_hora')) {
                    $table->dateTime('fecha_hora')->nullable();
                }
            });
        }
    }
};
