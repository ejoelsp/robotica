<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguridad.users', function (Blueprint $table) {
            if (! Schema::hasColumn('seguridad.users', 'estado')) {
                $table->boolean('estado')->default(true)->after('photo_path');
            }
        });

        if (Schema::hasColumn('seguridad.users', 'estado')) {
            DB::table('seguridad.users')
                ->update([
                    'estado' => DB::raw('email_verified_at IS NOT NULL AND must_change_password = false'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('seguridad.users', function (Blueprint $table) {
            if (Schema::hasColumn('seguridad.users', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};
