<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguridad.users', function (Blueprint $table) {
            if (Schema::hasColumn('seguridad.users', 'institucion')) {
                $table->dropColumn('institucion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguridad.users', function (Blueprint $table) {
            if (! Schema::hasColumn('seguridad.users', 'institucion')) {
                $table->string('institucion', 255)->nullable()->after('telefono');
            }
        });
    }
};