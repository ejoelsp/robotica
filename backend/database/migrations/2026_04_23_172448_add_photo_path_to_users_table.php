<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguridad.users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('seguridad.users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};