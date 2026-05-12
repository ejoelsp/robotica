<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Eliminar constraint actual si existe
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_estado_check
        ");

        // 2) Normalizar estados viejos a los nuevos estados válidos
        DB::statement("
            UPDATE vinculaciones.inscripciones
            SET estado = 'pendiente_pago'
            WHERE estado IN ('pendiente', 'pendiente de pago')
        ");

        DB::statement("
            UPDATE vinculaciones.inscripciones
            SET estado = 'revision'
            WHERE estado IN ('en_revision', 'en revisión')
        ");

        DB::statement("
            UPDATE vinculaciones.inscripciones
            SET estado = 'confirmado'
            WHERE estado IN ('confirmada')
        ");

        // 3) Crear nuevo constraint correcto
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            ADD CONSTRAINT inscripciones_estado_check
            CHECK (estado IN ('pendiente_pago', 'revision', 'confirmado'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_estado_check
        ");

        DB::statement("
            UPDATE vinculaciones.inscripciones
            SET estado = 'pendiente'
            WHERE estado = 'pendiente_pago'
        ");

        DB::statement("
            UPDATE vinculaciones.inscripciones
            SET estado = 'confirmada'
            WHERE estado = 'confirmado'
        ");

        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            ADD CONSTRAINT inscripciones_estado_check
            CHECK (estado IN ('pendiente', 'confirmada'))
        ");
    }
};