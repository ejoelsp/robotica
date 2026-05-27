<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados.plantillas_certificados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('competencia_id');
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('tipo_certificado', 30);
            $table->string('archivo_plantilla', 500);
            $table->jsonb('configuracion_textos')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();

            $table->index(['competencia_id', 'anio', 'tipo_certificado'], 'plantillas_cert_comp_anio_tipo_index');
            $table->index('activo', 'plantillas_certificados_activo_index');
        });

        Schema::create('resultados.certificados_generados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('competencia_id');
            $table->unsignedBigInteger('categoria_id');
            $table->unsignedBigInteger('equipo_id');
            $table->unsignedBigInteger('inscripcion_id');
            $table->unsignedBigInteger('inscripcion_integrante_id');
            $table->unsignedBigInteger('plantilla_certificado_id');
            $table->string('tipo_certificado', 30);
            $table->string('archivo_pdf', 500);
            $table->jsonb('datos_json')->nullable();
            $table->timestamp('fecha_generacion')->nullable();
            $table->timestamps();

            $table->index(['competencia_id', 'categoria_id', 'equipo_id'], 'certificados_comp_cat_equipo_index');
            $table->index('inscripcion_integrante_id', 'certificados_integrante_index');
            $table->unique(
                ['inscripcion_integrante_id', 'plantilla_certificado_id'],
                'certificados_integrante_plantilla_unique'
            );
        });

        DB::statement("
            ALTER TABLE resultados.plantillas_certificados
            ADD CONSTRAINT plantillas_certificados_tipo_check
            CHECK (tipo_certificado IN ('participacion', 'primer_lugar', 'segundo_lugar', 'tercer_lugar'))
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_tipo_check
            CHECK (tipo_certificado IN ('participacion', 'primer_lugar', 'segundo_lugar', 'tercer_lugar'))
        ");

        DB::statement("
            ALTER TABLE resultados.plantillas_certificados
            ADD CONSTRAINT plantillas_certificados_competencia_id_foreign
            FOREIGN KEY (competencia_id)
            REFERENCES catalogo.competencias(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE resultados.plantillas_certificados
            ADD CONSTRAINT plantillas_certificados_creado_por_foreign
            FOREIGN KEY (creado_por)
            REFERENCES seguridad.users(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_competencia_id_foreign
            FOREIGN KEY (competencia_id)
            REFERENCES catalogo.competencias(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_categoria_id_foreign
            FOREIGN KEY (categoria_id)
            REFERENCES catalogo.categorias(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_equipo_id_foreign
            FOREIGN KEY (equipo_id)
            REFERENCES catalogo.equipos(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_inscripcion_id_foreign
            FOREIGN KEY (inscripcion_id)
            REFERENCES vinculaciones.inscripciones(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_integrante_id_foreign
            FOREIGN KEY (inscripcion_integrante_id)
            REFERENCES vinculaciones.inscripcion_integrantes(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE resultados.certificados_generados
            ADD CONSTRAINT certificados_generados_plantilla_id_foreign
            FOREIGN KEY (plantilla_certificado_id)
            REFERENCES resultados.plantillas_certificados(id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados.certificados_generados');
        Schema::dropIfExists('resultados.plantillas_certificados');
    }
};
