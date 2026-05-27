<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.temporadas') || ! Schema::hasTable('catalogo.competencias')) {
            return;
        }

        $anios = DB::table('catalogo.competencias')
            ->whereNull('temporada_id')
            ->whereNotNull('fecha_inicio')
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM fecha_inicio)::int as anio')
            ->pluck('anio');

        foreach ($anios as $anio) {
            $temporadaId = DB::table('catalogo.temporadas')
                ->where('anio', (int) $anio)
                ->value('id');

            if (! $temporadaId) {
                $temporadaId = DB::table('catalogo.temporadas')->insertGetId([
                    'nombre' => 'Temporada ' . (int) $anio,
                    'anio' => (int) $anio,
                ]);
            }

            DB::table('catalogo.competencias')
                ->whereNull('temporada_id')
                ->whereYear('fecha_inicio', (int) $anio)
                ->update(['temporada_id' => $temporadaId]);
        }
    }

    public function down(): void
    {
        // No se revierte para no desasociar competencias existentes.
    }
};
