<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comunicacion.notificaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('comunicacion.notificaciones', 'tipo')) {
                $table->string('tipo', 60)->default('notificacion_admin')->after('canal');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'categoria_id')) {
                $table->unsignedBigInteger('categoria_id')->nullable()->after('competencia_id');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'leido')) {
                $table->boolean('leido')->default(false)->after('estado');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'leido_en')) {
                $table->timestamp('leido_en')->nullable()->after('leido');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'email_destino')) {
                $table->string('email_destino', 255)->nullable()->after('enviado_en');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'error_envio')) {
                $table->text('error_envio')->nullable()->after('email_destino');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'provider_message_id')) {
                $table->string('provider_message_id', 255)->nullable()->after('error_envio');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'referencia_tipo')) {
                $table->string('referencia_tipo', 80)->nullable()->after('provider_message_id');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'referencia_id')) {
                $table->unsignedBigInteger('referencia_id')->nullable()->after('referencia_tipo');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'creado_por')) {
                $table->unsignedBigInteger('creado_por')->nullable()->after('referencia_id');
            }

            if (! Schema::hasColumn('comunicacion.notificaciones', 'datos')) {
                $table->jsonb('datos')->nullable()->after('creado_por');
            }
        });

        if (! $this->foreignKeyExists('comunicacion', 'notificaciones', 'notificaciones_categoria_id_foreign')) {
            DB::statement('ALTER TABLE comunicacion.notificaciones ADD CONSTRAINT notificaciones_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES catalogo.categorias(id) ON UPDATE CASCADE ON DELETE SET NULL');
        }

        if (! $this->foreignKeyExists('comunicacion', 'notificaciones', 'notificaciones_creado_por_foreign')) {
            DB::statement('ALTER TABLE comunicacion.notificaciones ADD CONSTRAINT notificaciones_creado_por_foreign FOREIGN KEY (creado_por) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL');
        }

        $this->createIndexIfMissing('notificaciones_tipo_index', 'CREATE INDEX notificaciones_tipo_index ON comunicacion.notificaciones USING btree (tipo)');
        $this->createIndexIfMissing('notificaciones_leido_user_id_index', 'CREATE INDEX notificaciones_leido_user_id_index ON comunicacion.notificaciones USING btree (leido, user_id)');
        $this->createIndexIfMissing('notificaciones_referencia_index', 'CREATE INDEX notificaciones_referencia_index ON comunicacion.notificaciones USING btree (referencia_tipo, referencia_id)');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('notificaciones_referencia_index');
        $this->dropIndexIfExists('notificaciones_leido_user_id_index');
        $this->dropIndexIfExists('notificaciones_tipo_index');

        if ($this->foreignKeyExists('comunicacion', 'notificaciones', 'notificaciones_creado_por_foreign')) {
            DB::statement('ALTER TABLE comunicacion.notificaciones DROP CONSTRAINT notificaciones_creado_por_foreign');
        }

        if ($this->foreignKeyExists('comunicacion', 'notificaciones', 'notificaciones_categoria_id_foreign')) {
            DB::statement('ALTER TABLE comunicacion.notificaciones DROP CONSTRAINT notificaciones_categoria_id_foreign');
        }

        Schema::table('comunicacion.notificaciones', function (Blueprint $table) {
            $columns = [
                'datos',
                'creado_por',
                'referencia_id',
                'referencia_tipo',
                'provider_message_id',
                'error_envio',
                'email_destino',
                'leido_en',
                'leido',
                'categoria_id',
                'tipo',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('comunicacion.notificaciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function foreignKeyExists(string $schema, string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', $schema)
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }

    private function createIndexIfMissing(string $name, string $sql): void
    {
        if (! DB::table('pg_indexes')->where('schemaname', 'comunicacion')->where('indexname', $name)->exists()) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $name): void
    {
        if (DB::table('pg_indexes')->where('schemaname', 'comunicacion')->where('indexname', $name)->exists()) {
            DB::statement("DROP INDEX comunicacion.{$name}");
        }
    }
};
