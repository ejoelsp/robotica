<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE vinculaciones.inscripciones
            ADD COLUMN comprobante_pago varchar(255) NULL
        ');

        DB::statement('
            ALTER TABLE vinculaciones.inscripciones
            ADD COLUMN fecha_subida_comprobante timestamp without time zone NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE vinculaciones.inscripciones
            DROP COLUMN IF EXISTS fecha_subida_comprobante
        ');

        DB::statement('
            ALTER TABLE vinculaciones.inscripciones
            DROP COLUMN IF EXISTS comprobante_pago
        ');
    }
};