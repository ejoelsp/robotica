<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EvaluacionJuezService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RepararPodioFinal extends Command
{
    protected $signature = 'resultados:reparar-podio
        {competencia_id : ID de la competencia}
        {categoria_id : ID de la categoria}
        {--admin-id= : ID del usuario admin que ejecuta la reparacion}
        {--admin-email= : Correo del usuario admin que ejecuta la reparacion}';

    protected $description = 'Reconstruye el podio final de una categoria de enfrentamiento';

    public function handle(EvaluacionJuezService $service): int
    {
        $competenciaId = (int) $this->argument('competencia_id');
        $categoriaId = (int) $this->argument('categoria_id');
        $admin = $this->resolverAdmin();

        if (! $admin) {
            $this->error('Debes indicar --admin-id o --admin-email para ejecutar la reparacion.');

            return self::FAILURE;
        }

        try {
            $vista = $service->repararPodioFinalEnfrentamiento($competenciaId, $categoriaId, $admin);
        } catch (ValidationException $exception) {
            $mensaje = collect($exception->errors())->flatten()->first() ?: 'No se pudo reparar el podio.';
            $this->error($mensaje);

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = collect($vista['rows'] ?? []);

        $this->info('Podio final reparado correctamente.');
        $this->line(sprintf(
            'Competencia ID: %s | Categoria: %s | Ronda: %s',
            (string) ($vista['scope']['competencia_id'] ?? $competenciaId),
            (string) ($vista['scope']['categoria_nombre'] ?? $categoriaId),
            (string) ($vista['scope']['ronda_nombre'] ?? 'Final')
        ));

        if ($rows->isNotEmpty()) {
            $this->table(
                ['Posicion', 'Equipo', 'Institucion', 'Resultado', 'Estado'],
                $rows->map(function (array $row) {
                    return [
                        (string) ($row['posicion'] ?? '-'),
                        (string) ($row['equipo_nombre'] ?? '-'),
                        (string) ($row['institucion'] ?? '-'),
                        (string) ($row['resultado_label'] ?? '-'),
                        (string) ($row['estado_publicacion'] ?? '-'),
                    ];
                })->all()
            );
        }

        return self::SUCCESS;
    }

    private function resolverAdmin(): ?User
    {
        $adminId = $this->option('admin-id');
        if (is_numeric($adminId) && (int) $adminId > 0) {
            return User::query()->find((int) $adminId);
        }

        $adminEmail = trim((string) $this->option('admin-email'));
        if ($adminEmail !== '') {
            return User::query()->where('email', $adminEmail)->first();
        }

        return null;
    }
}
