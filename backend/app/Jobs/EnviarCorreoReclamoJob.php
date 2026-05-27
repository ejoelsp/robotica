<?php

namespace App\Jobs;

use App\Models\Incidencia;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class EnviarCorreoReclamoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $reclamoId
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $reclamo = Incidencia::query()
            ->with('categoria:id,competencia_id')
            ->find($this->reclamoId);

        if (! $reclamo || $reclamo->tipo !== 'reclamo') {
            return;
        }

        $documento = $this->documento($reclamo);

        foreach ($this->destinatarios($documento) as $destinatario) {
            EnviarCorreoReclamoDestinatarioJob::dispatch(
                $this->reclamoId,
                $destinatario['email'],
                $destinatario['name']
            )->afterCommit();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function documento(Incidencia $reclamo): array
    {
        return [
            'codigo' => $reclamo->codigo,
            'fecha' => optional($reclamo->fecha_envio ?? $reclamo->created_at)?->format('d/m/Y H:i'),
            'evento' => [
                'id' => $reclamo->categoria?->competencia_id,
                'nombre' => $reclamo->equipo_snapshot['evento'] ?? 'Evento',
                'logo_url' => $reclamo->equipo_snapshot['logo_url'] ?? null,
            ],
            'categoria' => [
                'nombre' => $reclamo->equipo_snapshot['categoria'] ?? 'Categoría',
            ],
            'equipo' => $reclamo->equipo_snapshot ?? [],
            'integrantes' => $reclamo->integrantes_snapshot ?? [],
            'jueces' => $reclamo->jueces_snapshot ?? [],
            'prototipo_nombre' => $reclamo->prototipo_nombre,
            'institucion' => $reclamo->institucion,
            'descripcion' => $reclamo->descripcion,
        ];
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    private function destinatarios(array $documento): array
    {
        $competenciaId = $documento['evento']['id'] ?? null;

        $comite = DB::table('catalogo.comite_organizadores')
            ->where('competencia_id', $competenciaId)
            ->where('estado', true)
            ->whereNotNull('correo')
            ->get(['nombres', 'apellidos', 'correo']);

        $admins = User::query()
            ->join('seguridad.roles', 'seguridad.roles.id', '=', 'seguridad.users.role_id')
            ->where('seguridad.roles.nombre', 'admin')
            ->whereNotNull('seguridad.users.email')
            ->get(['seguridad.users.name', 'seguridad.users.last_name', 'seguridad.users.email']);

        return $comite
            ->map(fn ($item) => [
                'email' => (string) $item->correo,
                'name' => trim((string) $item->nombres . ' ' . (string) $item->apellidos),
            ])
            ->merge($admins->map(fn ($item) => [
                'email' => (string) $item->email,
                'name' => trim((string) $item->name . ' ' . (string) $item->last_name),
            ]))
            ->filter(fn (array $item) => filter_var($item['email'], FILTER_VALIDATE_EMAIL))
            ->unique('email')
            ->values()
            ->all();
    }
}
