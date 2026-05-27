<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria.auditorias', function (Blueprint $table) {
            if (! Schema::hasColumn('auditoria.auditorias', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('auditoria.auditorias', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('auditoria.auditorias', 'modulo')) {
                $table->string('modulo', 80)->nullable()->after('accion');
            }

            if (! Schema::hasColumn('auditoria.auditorias', 'descripcion')) {
                $table->string('descripcion', 255)->nullable()->after('modulo');
            }

            if (! Schema::hasColumn('auditoria.auditorias', 'estado')) {
                $table->string('estado', 30)->default('exitoso')->after('descripcion');
            }

            if (Schema::hasColumn('auditoria.auditorias', 'usuario_id')) {
                $table->dropColumn('usuario_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auditoria.auditorias', function (Blueprint $table) {
            if (! Schema::hasColumn('auditoria.auditorias', 'usuario_id')) {
                $table->unsignedBigInteger('usuario_id')->nullable();
            }

            foreach (['ip_address', 'user_agent', 'modulo', 'descripcion', 'estado'] as $column) {
                if (Schema::hasColumn('auditoria.auditorias', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
