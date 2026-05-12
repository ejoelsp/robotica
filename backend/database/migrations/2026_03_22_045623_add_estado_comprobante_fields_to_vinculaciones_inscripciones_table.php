<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculaciones.inscripciones', function (Blueprint $table) {
            $table->string('estado_comprobante', 30)->default('no_subido');
            $table->string('motivo_rechazo', 150)->nullable();
            $table->text('observacion_rechazo')->nullable();
            $table->timestamp('fecha_revision_comprobante')->nullable();
            $table->unsignedBigInteger('revisado_por')->nullable();
        });

        DB::statement("
            UPDATE vinculaciones.inscripciones
            SET estado_comprobante = CASE
                WHEN estado = 'pendiente_pago' THEN 'no_subido'
                WHEN estado = 'revision' THEN 'revision'
                WHEN estado = 'confirmado' THEN 'aprobado'
                ELSE 'no_subido'
            END
        ");

        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            ADD CONSTRAINT inscripciones_estado_comprobante_check
            CHECK (estado_comprobante IN ('no_subido', 'revision', 'aprobado', 'rechazado'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_estado_comprobante_check
        ");

        Schema::table('vinculaciones.inscripciones', function (Blueprint $table) {
            $table->dropColumn([
                'estado_comprobante',
                'motivo_rechazo',
                'observacion_rechazo',
                'fecha_revision_comprobante',
                'revisado_por',
            ]);
        });
    }
};