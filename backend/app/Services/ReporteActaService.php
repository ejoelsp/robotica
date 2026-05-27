<?php

namespace App\Services;

use App\Models\ActaReporte;
use App\Models\AsignacionJuezCategoria;
use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\Competencia;
use App\Models\Inscripcion;
use App\Models\Ronda;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReporteActaService
{
    private const PAGE_WIDTH = 595.0;
    private const PAGE_HEIGHT = 842.0;
    private const MARGIN_X = 42.0;
    private const MARGIN_TOP = 54.0;
    private const MARGIN_BOTTOM = 52.0;

    public function __construct(
        private readonly ClasificacionConsolidacionService $clasificacionService
    ) {
    }

    public function tiposReporte(): array
    {
        return [
            'inscritos' => 'Número de inscritos',
            'tabla_resultados' => 'Tabla de resultados',
            'acta_final' => 'Acta final',
        ];
    }

    public function generar(array $data, User $admin): ActaReporte
    {
        $tipo = (string) $data['tipo_reporte'];
        $competencia = Competencia::query()->findOrFail((int) $data['competencia_id']);
        $categoria = Categoria::query()
            ->where('competencia_id', $competencia->id)
            ->findOrFail((int) $data['categoria_id']);

        $ronda = null;
        if (! empty($data['ronda_id'])) {
            $ronda = Ronda::query()
                ->where('categoria_id', $categoria->id)
                ->findOrFail((int) $data['ronda_id']);
        }

        if ($tipo === 'acta_final' && ! $ronda) {
            $ronda = $this->resolverRondaFinal($categoria, $competencia);
        }

        $snapshot = $this->construirSnapshot($tipo, $competencia, $categoria, $ronda);
        $pdf = $this->construirPdf($snapshot);

        $ruta = sprintf(
            'reportes/actas/%s/%s/%s-%s.pdf',
            $competencia->id,
            $categoria->id,
            now()->format('Ymd-His'),
            Str::slug($this->tiposReporte()[$tipo] ?? $tipo)
        );

        Storage::disk('public')->put($ruta, $pdf);

        return ActaReporte::query()->create([
            'competencia_id' => $competencia->id,
            'categoria_id' => $categoria->id,
            'ronda_id' => $ronda?->id,
            'tipo_reporte' => $tipo,
            'estado' => 'generado',
            'archivo_generado_path' => $ruta,
            'generado_por' => $admin->id,
            'generado_at' => now(),
            'snapshot_json' => $snapshot,
            'observaciones' => $data['observaciones'] ?? null,
        ]);
    }

    public function serializar(ActaReporte $acta): array
    {
        return [
            'id' => (int) $acta->id,
            'competencia_id' => (int) $acta->competencia_id,
            'categoria_id' => (int) $acta->categoria_id,
            'ronda_id' => $acta->ronda_id ? (int) $acta->ronda_id : null,
            'competencia_nombre' => (string) ($acta->competencia?->nombre ?? ''),
            'categoria_nombre' => (string) ($acta->categoria?->nombre ?? ''),
            'ronda_nombre' => $acta->ronda?->nombre,
            'tipo_reporte' => (string) $acta->tipo_reporte,
            'tipo_reporte_label' => $this->tiposReporte()[$acta->tipo_reporte] ?? $acta->tipo_reporte,
            'estado' => (string) $acta->estado,
            'generado_por_nombre' => $this->nombreUsuario($acta->generadoPor),
            'archivo_firmado_subido_por_nombre' => $this->nombreUsuario($acta->archivoFirmadoSubidoPor),
            'generado_at' => optional($acta->generado_at)?->toIso8601String(),
            'archivo_firmado_subido_at' => optional($acta->archivo_firmado_subido_at)?->toIso8601String(),
            'observaciones' => $acta->observaciones,
            'download_generado_url' => route('admin.reportes.download.generado', $acta),
            'download_firmado_url' => $acta->archivo_firmado_path
                ? route('admin.reportes.download.firmado', $acta)
                : null,
        ];
    }

    private function construirSnapshot(string $tipo, Competencia $competencia, Categoria $categoria, ?Ronda $ronda): array
    {
        $base = [
            'tipo_reporte' => $tipo,
            'tipo_reporte_label' => $this->tiposReporte()[$tipo] ?? $tipo,
            'generado_at' => now()->format('d/m/Y'),
            'competencia' => [
                'id' => (int) $competencia->id,
                'nombre' => (string) $competencia->nombre,
                'fecha_inicio' => optional($competencia->fecha_inicio)?->format('d/m/Y'),
                'fecha_fin' => optional($competencia->fecha_fin)?->format('d/m/Y'),
                'logo_path' => (string) ($competencia->logo_url ?: $competencia->imagen_url ?: ''),
            ],
            'categoria' => [
                'id' => (int) $categoria->id,
                'nombre' => (string) $categoria->nombre,
            ],
            'ronda' => $ronda ? [
                'id' => (int) $ronda->id,
                'nombre' => (string) $ronda->nombre,
                'orden' => (int) ($ronda->orden ?? 1),
                'es_final' => (bool) $ronda->es_final,
            ] : null,
            'jueces' => $this->juecesAsignados($categoria),
        ];

        return match ($tipo) {
            'inscritos' => $base + $this->datosInscritos($competencia, $categoria),
            'tabla_resultados' => $base + $this->datosTablaResultados($competencia, $categoria, $ronda),
            'acta_final' => $base + $this->datosActaFinal($competencia, $categoria, $ronda),
            default => throw ValidationException::withMessages([
                'tipo_reporte' => 'Selecciona un tipo de reporte válido.',
            ]),
        };
    }

    private function datosInscritos(Competencia $competencia, Categoria $categoria): array
    {
        $inscripciones = Inscripcion::query()
            ->with(['equipo:id,nombre,institucion', 'integrantes:id,inscripcion_id,nombre_completo,es_capitan'])
            ->where('competencia_id', $competencia->id)
            ->where('categoria_id', $categoria->id)
            ->aprobadas()
            ->orderBy('id')
            ->get();

        return [
            'resumen' => [
                'total_aprobados' => $inscripciones->count(),
                'total_integrantes' => (int) ($categoria->max_integrantes ?? 0),
            ],
            'filas' => $inscripciones
                ->map(fn (Inscripcion $inscripcion, int $index) => [
                    'numero' => $index + 1,
                    'codigo' => (string) ($inscripcion->codigo ?? ''),
                    'equipo' => (string) ($inscripcion->equipo?->nombre ?? 'Sin equipo'),
                    'prototipo' => (string) ($inscripcion->nombre_prototipo ?? 'Sin prototipo'),
                    'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
                    'estado' => (string) $inscripcion->estado,
                    'comprobante' => (string) ($inscripcion->estado_comprobante ?? 'pendiente'),
                    'integrantes' => $inscripcion->integrantes
                        ->sortByDesc('es_capitan')
                        ->pluck('nombre_completo')
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function datosTablaResultados(Competencia $competencia, Categoria $categoria, ?Ronda $ronda): array
    {
        $panel = $this->clasificacionService->obtenerPanelEnVivo(
            (int) $competencia->id,
            (int) $categoria->id,
            $ronda ? (int) $ronda->id : null,
            [
                'audience' => 'report',
                'estados_publicacion' => ['visible', 'cerrado'],
            ]
        );
        $scopes = collect($panel['scopes'] ?? [])
            ->filter(function (array $scope) use ($categoria, $ronda) {
                if ((int) ($scope['categoria_id'] ?? 0) !== (int) $categoria->id) {
                    return false;
                }

                if ($ronda && (int) ($scope['ronda_id'] ?? 0) !== (int) $ronda->id) {
                    return false;
                }

                return ! empty($scope['rows']);
            })
            ->values()
            ->all();

        if (empty($scopes)) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No existen resultados publicados para esta categoría.',
            ]);
        }

        return [
            'resumen' => [
                'total_resultados' => collect($scopes)->sum(fn (array $scope) => count($scope['rows'] ?? [])),
                'total_rondas' => count($scopes),
                'estado_publicacion' => (string) ($scopes[0]['estado_publicacion'] ?? 'borrador'),
            ],
            'scopes' => $scopes,
            'filas' => collect($scopes)->flatMap(fn (array $scope) => $scope['rows'] ?? [])->values()->all(),
        ];
    }

    private function datosActaFinal(Competencia $competencia, Categoria $categoria, ?Ronda $ronda): array
    {
        $vista = $this->clasificacionService->obtenerVistaPublica(
            (int) $competencia->id,
            (int) $categoria->id,
            $ronda ? (int) $ronda->id : null
        );
        $rows = collect($vista['rows'] ?? [])->take(3)->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No existen resultados finales publicados para esta categoría.',
            ]);
        }

        return [
            'resumen' => [
                'total_resultados' => $rows->count(),
                'estado_publicacion' => (string) ($vista['scope']['estado_publicacion'] ?? 'borrador'),
                'updated_at' => $vista['summary']['updated_at'] ?? null,
            ],
            'vista_publica' => $vista,
            'filas' => $rows->all(),
            'podio' => $rows->all(),
        ];
    }

    private function resolverRondaFinal(Categoria $categoria, Competencia $competencia): ?Ronda
    {
        $final = Ronda::query()
            ->where('categoria_id', $categoria->id)
            ->where('es_final', true)
            ->orderByDesc('orden')
            ->first();

        if ($final) {
            return $final;
        }

        $rondaId = Clasificacion::query()
            ->join('catalogo.rondas as r', 'r.id', '=', 'resultados.clasificaciones.ronda_id')
            ->where('resultados.clasificaciones.competencia_id', $competencia->id)
            ->where('resultados.clasificaciones.categoria_id', $categoria->id)
            ->whereNotNull('resultados.clasificaciones.ronda_id')
            ->orderByDesc('r.orden')
            ->value('resultados.clasificaciones.ronda_id');

        return $rondaId ? Ronda::query()->find($rondaId) : null;
    }

    private function resolverRondaConResultados(Categoria $categoria, Competencia $competencia): ?Ronda
    {
        $rondaId = Clasificacion::query()
            ->join('catalogo.rondas as r', 'r.id', '=', 'resultados.clasificaciones.ronda_id')
            ->where('resultados.clasificaciones.competencia_id', $competencia->id)
            ->where('resultados.clasificaciones.categoria_id', $categoria->id)
            ->whereNotNull('resultados.clasificaciones.ronda_id')
            ->orderByDesc('r.es_final')
            ->orderByDesc('r.orden')
            ->orderByDesc('resultados.clasificaciones.ronda_id')
            ->value('resultados.clasificaciones.ronda_id');

        return $rondaId ? Ronda::query()->find($rondaId) : null;
    }

    private function clasificaciones(Competencia $competencia, Categoria $categoria, ?Ronda $ronda)
    {
        return Clasificacion::query()
            ->with(['equipo:id,nombre,institucion'])
            ->where('competencia_id', $competencia->id)
            ->where('categoria_id', $categoria->id)
            ->when($ronda, fn ($query) => $query->where('ronda_id', $ronda->id))
            ->orderBy('posicion')
            ->orderByDesc('puntaje_total')
            ->orderBy('tiempo_total')
            ->get();
    }

    private function serializarClasificacion(Clasificacion $clasificacion): array
    {
        $inscripcion = Inscripcion::query()
            ->where('competencia_id', $clasificacion->competencia_id)
            ->where('categoria_id', $clasificacion->categoria_id)
            ->where('equipo_id', $clasificacion->equipo_id)
            ->first(['id', 'nombre_prototipo']);

        return [
            'posicion' => (int) $clasificacion->posicion,
            'equipo' => (string) ($clasificacion->equipo?->nombre ?? 'Sin equipo'),
            'prototipo' => (string) ($inscripcion?->nombre_prototipo ?? 'Sin prototipo'),
            'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
            'puntaje_total' => $clasificacion->puntaje_total !== null ? number_format((float) $clasificacion->puntaje_total, 2) : '-',
            'tiempo_total' => $clasificacion->tiempo_total !== null ? $this->formatearTiempo((float) $clasificacion->tiempo_total) : '-',
            'penal_total' => $clasificacion->penal_total !== null ? number_format((float) $clasificacion->penal_total, 2) : '-',
            'estado_publicacion' => (string) $clasificacion->estado_publicacion,
        ];
    }

    private function juecesAsignados(Categoria $categoria): array
    {
        return AsignacionJuezCategoria::query()
            ->with('juez:id,name,last_name,email')
            ->where('categoria_id', $categoria->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AsignacionJuezCategoria $asignacion, int $index) => [
                'numero' => $index + 1,
                'nombre' => $this->nombreUsuario($asignacion->juez),
                'nombres' => trim((string) ($asignacion->juez?->name ?? '')),
                'apellidos' => trim((string) ($asignacion->juez?->last_name ?? '')),
                'email' => (string) ($asignacion->juez?->email ?? ''),
                'rol' => (string) ($asignacion->rol ?? 'Juez'),
                'rol_label' => $this->rolJuezLabel((string) ($asignacion->rol ?? 'Juez')),
            ])
            ->values()
            ->all();
    }

    private function construirPdf(array $snapshot): string
    {
        if ($snapshot['tipo_reporte'] === 'inscritos') {
            return $this->construirPdfInscritos($snapshot);
        }

        if ($snapshot['tipo_reporte'] === 'tabla_resultados') {
            return $this->construirPdfTablaResultados($snapshot);
        }

        if ($snapshot['tipo_reporte'] === 'acta_final') {
            return $this->construirPdfActaFinal($snapshot);
        }

        $pages = [[]];
        $pageIndex = 0;
        $y = self::PAGE_HEIGHT - self::MARGIN_TOP;

        $addLine = function (string $text, float $size = 10.0, bool $bold = false, float $indent = 0.0) use (&$pages, &$pageIndex, &$y): void {
            $maxWidth = self::PAGE_WIDTH - (self::MARGIN_X * 2) - $indent;
            foreach ($this->wrap($text, $size, $maxWidth) as $line) {
                if ($y < self::MARGIN_BOTTOM) {
                    $pages[] = [];
                    $pageIndex++;
                    $y = self::PAGE_HEIGHT - self::MARGIN_TOP;
                }

                $pages[$pageIndex][] = [
                    'type' => 'text',
                    'x' => self::MARGIN_X + $indent,
                    'y' => $y,
                    'size' => $size,
                    'bold' => $bold,
                    'text' => $line,
                ];
                $y -= $size + 5;
            }
        };

        $addSpace = function (float $height = 10.0) use (&$y): void {
            $y -= $height;
        };

        $addLine('CLUB DE ROBÓTICA ESPOCH', 15, true);
        $addLine(strtoupper((string) $snapshot['tipo_reporte_label']), 14, true);
        $addSpace(6);
        $addLine('Competencia: ' . $snapshot['competencia']['nombre'], 10, true);
        $addLine('Categoría: ' . $snapshot['categoria']['nombre'], 10, true);
        if (! empty($snapshot['ronda'])) {
            $addLine('Ronda: ' . $snapshot['ronda']['nombre'], 10, true);
        }
        $addLine('Fecha de generación: ' . $snapshot['generado_at'], 9);
        $addSpace(8);

        $this->agregarContenidoReporte($snapshot, $addLine, $addSpace);

        $addSpace(14);
        $addLine('Jueces asignados', 12, true);
        $jueces = $snapshot['jueces'] ?: [[
            'numero' => 1,
            'nombre' => 'Juez asignado',
            'nombres' => 'Juez',
            'apellidos' => 'asignado',
            'rol' => 'Juez',
            'rol_label' => 'Juez',
        ]];

        foreach ($jueces as $juez) {
            if ($y < 120) {
                $pages[] = [];
                $pageIndex++;
                $y = self::PAGE_HEIGHT - self::MARGIN_TOP;
            }

            $pages[$pageIndex][] = [
                'type' => 'line',
                'x1' => self::MARGIN_X,
                'y1' => $y - 8,
                'x2' => self::MARGIN_X + 220,
                'y2' => $y - 8,
            ];
            $lineCenter = self::MARGIN_X + 110;
            $pages[$pageIndex][] = [
                'type' => 'text',
                'x' => $lineCenter,
                'y' => $y - 22,
                'size' => 9,
                'bold' => false,
                'align' => 'center',
                'text' => (string) ($juez['rol_label'] ?? $this->rolJuezLabel((string) ($juez['rol'] ?? 'Juez'))),
            ];
            $pages[$pageIndex][] = [
                'type' => 'text',
                'x' => $lineCenter,
                'y' => $y - 39,
                'size' => 9,
                'bold' => false,
                'align' => 'center',
                'text' => (string) (($juez['nombres'] ?? '') ?: $juez['nombre']),
            ];
            if (! empty($juez['apellidos'])) {
                $pages[$pageIndex][] = [
                    'type' => 'text',
                    'x' => $lineCenter,
                    'y' => $y - 56,
                    'size' => 9,
                    'bold' => false,
                    'align' => 'center',
                    'text' => (string) $juez['apellidos'],
                ];
            }
            $y -= 76;
        }

        return $this->renderPdf($pages);
    }

    private function construirPdfTablaResultados(array $snapshot): string
    {
        $pages = [[]];
        $pageIndex = 0;
        $y = self::PAGE_HEIGHT - 50;

        $addPage = function () use (&$pages, &$pageIndex, &$y): void {
            $pages[] = [];
            $pageIndex++;
            $y = self::PAGE_HEIGHT - self::MARGIN_TOP;
        };

        $addText = function (
            string $text,
            float $x,
            float $textY,
            float $size = 9.0,
            bool $bold = false,
            string $align = 'left'
        ) use (&$pages, &$pageIndex): void {
            $pages[$pageIndex][] = [
                'type' => 'text',
                'x' => $x,
                'y' => $textY,
                'size' => $size,
                'bold' => $bold,
                'align' => $align,
                'text' => $text,
            ];
        };

        $addLine = function (float $x1, float $y1, float $x2, float $y2) use (&$pages, &$pageIndex): void {
            $pages[$pageIndex][] = compact('x1', 'y1', 'x2', 'y2') + ['type' => 'line'];
        };

        $logoPath = $this->resolverRutaLogo((string) ($snapshot['competencia']['logo_path'] ?? ''));
        if ($logoPath) {
            $pages[$pageIndex][] = [
                'type' => 'image',
                'path' => $logoPath,
                'x' => self::MARGIN_X,
                'y' => self::PAGE_HEIGHT - 96,
                'width' => 54,
                'height' => 54,
            ];
        }

        $addText('CLUB DE ROBÓTICA ESPOCH', self::PAGE_WIDTH / 2, $y, 14, true, 'center');
        $y -= 16;
        $addText('TABLA DE RESULTADOS', self::PAGE_WIDTH / 2, $y, 13, true, 'center');
        $y -= 38;

        $leftX = 82.0;
        $rightX = 356.0;
        $addText('Competencia: ' . $snapshot['competencia']['nombre'], $leftX, $y, 8, true);
        $addText('Rondas con resultados: ' . ($snapshot['resumen']['total_rondas'] ?? 0), $rightX, $y, 8, true);
        $y -= 13;
        $addText('Categoría: ' . $snapshot['categoria']['nombre'], $leftX, $y, 8, true);
        $addText('Total de resultados: ' . ($snapshot['resumen']['total_resultados'] ?? 0), $rightX, $y, 8);
        $y -= 13;
        $addText('Fecha de generación: ' . $snapshot['generado_at'], $leftX, $y, 8);
        $y -= 26;

        foreach ($snapshot['scopes'] ?? [] as $scope) {
            if ($y < 170) {
                $addPage();
            }

            $title = (string) ($scope['categoria_nombre'] ?? $snapshot['categoria']['nombre']);
            $subtitle = trim((string) ($scope['ronda_nombre'] ?? '') . ' · ' . (string) ($scope['mecanismo_nombre'] ?? ''), ' ·');
            $status = trim((string) ($scope['estado_publicacion'] ?? ''));
            $updatedAt = $this->formatearFechaHoraReporte($scope['updated_at'] ?? null);
            $statusLine = trim($status . ($updatedAt ? ' · ' . $updatedAt : ''), ' ·');
            $addText($title, self::MARGIN_X, $y, 10, true);
            $addText($statusLine, self::PAGE_WIDTH - self::MARGIN_X, $y, 8, false, 'right');
            $y -= 13;
            if ($subtitle !== '') {
                $addText($subtitle, self::MARGIN_X, $y, 8);
                $y -= 14;
            }

            if (! empty($scope['usa_enfrentamiento'])) {
                $columns = [
                    ['label' => 'Encuentro', 'x' => self::MARGIN_X, 'w' => 52],
                    ['label' => 'Equipo A', 'x' => self::MARGIN_X + 52, 'w' => 154],
                    ['label' => 'Resultado', 'x' => self::MARGIN_X + 206, 'w' => 100],
                    ['label' => 'Equipo B', 'x' => self::MARGIN_X + 306, 'w' => 205],
                ];
                $rows = collect($scope['rows'] ?? [])
                    ->map(fn (array $row) => [
                        (string) ($row['encuentro'] ?? ''),
                        trim((string) ($row['equipo_a'] ?? '-') . "\n" . (string) ($row['institucion_a'] ?? '')),
                        (string) ($row['resultado_label'] ?? '-'),
                        trim((string) ($row['equipo_b'] ?? '-') . "\n" . (string) ($row['institucion_b'] ?? '')),
                    ])
                    ->all();
            } elseif (! empty($scope['usa_intentos'])) {
                $attempts = collect($scope['intentos_headers'] ?? [])->values();
                $attemptWidth = min(68.0, (511.0 - 48.0 - 154.0 - 86.0) / max(1, $attempts->count()));
                $attemptWidth = max(18.0, $attemptWidth);
                $x = self::MARGIN_X;
                $columns = [['label' => 'Orden', 'x' => $x, 'w' => 48]];
                $x += 48;
                foreach ($attempts as $attempt) {
                    $columns[] = ['label' => (string) ($attempt['label'] ?? 'Intento'), 'x' => $x, 'w' => $attemptWidth];
                    $x += $attemptWidth;
                }
                $columns[] = ['label' => 'Participante', 'x' => $x, 'w' => 154];
                $x += 154;
                $columns[] = ['label' => 'Institución', 'x' => $x, 'w' => max(72.0, self::MARGIN_X + 511.0 - $x)];

                $rows = collect($scope['rows'] ?? [])
                    ->map(function (array $row) use ($attempts) {
                        $intentos = collect($row['intentos'] ?? [])->keyBy(fn (array $attempt) => (int) ($attempt['numero'] ?? 0));
                        $cells = [(string) ($row['posicion'] ?? '')];
                        foreach ($attempts as $attempt) {
                            $cells[] = (string) ($intentos->get((int) ($attempt['numero'] ?? 0))['resultado_label'] ?? 'Pendiente');
                        }
                        $cells[] = trim((string) ($row['equipo_nombre'] ?? '-') . "\nMejor intento: " . (string) ($row['resultado_label'] ?? '-'));
                        $cells[] = (string) (($row['institucion'] ?? '') ?: 'Sin institución');

                        return $cells;
                    })
                    ->all();
            } else {
                $columns = [
                    ['label' => 'Posición', 'x' => self::MARGIN_X, 'w' => 58],
                    ['label' => 'Equipo', 'x' => self::MARGIN_X + 58, 'w' => 170],
                    ['label' => 'Institución', 'x' => self::MARGIN_X + 228, 'w' => 125],
                    ['label' => 'Resultado', 'x' => self::MARGIN_X + 353, 'w' => 158],
                ];
                $rows = collect($scope['rows'] ?? [])
                    ->map(fn (array $row) => [
                        (string) ($row['posicion'] ?? ''),
                        (string) ($row['equipo_nombre'] ?? '-'),
                        (string) (($row['institucion'] ?? '') ?: 'Sin institución'),
                        (string) ($row['resultado_label'] ?? '-'),
                    ])
                    ->all();
            }

            $this->dibujarTablaPdf($columns, $rows, $pages, $pageIndex, $y, $addPage, $addLine, $addText);
            $y -= 20;
        }

        $this->agregarFirmasEnGrilla($pages, $pageIndex, $y, $snapshot['jueces']);

        return $this->renderPdf($pages);
    }

    private function construirPdfActaFinal(array $snapshot): string
    {
        $pages = [[]];
        $pageIndex = 0;
        $y = self::PAGE_HEIGHT - 50;

        $addPage = function () use (&$pages, &$pageIndex, &$y): void {
            $pages[] = [];
            $pageIndex++;
            $y = self::PAGE_HEIGHT - self::MARGIN_TOP;
        };

        $addText = function (
            string $text,
            float $x,
            float $textY,
            float $size = 9.0,
            bool $bold = false,
            string $align = 'left'
        ) use (&$pages, &$pageIndex): void {
            $pages[$pageIndex][] = [
                'type' => 'text',
                'x' => $x,
                'y' => $textY,
                'size' => $size,
                'bold' => $bold,
                'align' => $align,
                'text' => $text,
            ];
        };

        $addLine = function (float $x1, float $y1, float $x2, float $y2) use (&$pages, &$pageIndex): void {
            $pages[$pageIndex][] = compact('x1', 'y1', 'x2', 'y2') + ['type' => 'line'];
        };

        $logoPath = $this->resolverRutaLogo((string) ($snapshot['competencia']['logo_path'] ?? ''));
        if ($logoPath) {
            $pages[$pageIndex][] = [
                'type' => 'image',
                'path' => $logoPath,
                'x' => self::MARGIN_X,
                'y' => self::PAGE_HEIGHT - 96,
                'width' => 54,
                'height' => 54,
            ];
        }

        $vista = $snapshot['vista_publica'] ?? [];
        $scope = $vista['scope'] ?? [];

        $addText('CLUB DE ROBÓTICA ESPOCH', self::PAGE_WIDTH / 2, $y, 14, true, 'center');
        $y -= 16;
        $addText('ACTA FINAL', self::PAGE_WIDTH / 2, $y, 13, true, 'center');
        $y -= 38;

        $leftX = 82.0;
        $rightX = 356.0;
        $addText('Competencia: ' . $snapshot['competencia']['nombre'], $leftX, $y, 8, true);
        $addText('Última publicación: ' . ($this->formatearFechaHoraReporte($snapshot['resumen']['updated_at'] ?? null) ?: '-'), $rightX, $y, 8, true);
        $y -= 13;
        $addText('Categoría: ' . $snapshot['categoria']['nombre'], $leftX, $y, 8, true);
        $addText('Estado: ' . (string) ($snapshot['resumen']['estado_publicacion'] ?? '-'), $rightX, $y, 8);
        $y -= 13;
        $addText('Podio oficial - ' . (string) ($scope['mecanismo_nombre'] ?? 'Publicada'), $leftX, $y, 8);
        $addText('Fecha de generación: ' . $snapshot['generado_at'], $rightX, $y, 8);
        $y -= 28;

        $columns = [
            ['label' => 'Posición', 'x' => self::MARGIN_X, 'w' => 62],
            ['label' => 'Equipo', 'x' => self::MARGIN_X + 62, 'w' => 178],
            ['label' => 'Institución', 'x' => self::MARGIN_X + 240, 'w' => 126],
            ['label' => 'Resultado', 'x' => self::MARGIN_X + 366, 'w' => 145],
        ];
        $rows = collect($snapshot['podio'] ?? [])
            ->map(fn (array $row) => [
                (string) ($row['posicion'] ?? ''),
                trim((string) ($row['equipo_nombre'] ?? '-') . (! empty($row['nota']) ? "\n" . (string) $row['nota'] : '')),
                (string) (($row['institucion'] ?? '') ?: 'Sin institución'),
                $this->resultadoActaFinalLabel($row, $scope),
            ])
            ->all();

        $this->dibujarTablaPdf($columns, $rows, $pages, $pageIndex, $y, $addPage, $addLine, $addText);
        $y -= 20;

        $this->agregarFirmasEnGrilla($pages, $pageIndex, $y, $snapshot['jueces']);

        return $this->renderPdf($pages);
    }

    private function dibujarTablaPdf(
        array $columns,
        array $rows,
        array &$pages,
        int &$pageIndex,
        float &$y,
        callable $addPage,
        callable $addLine,
        callable $addText
    ): void {
        $tableWidth = collect($columns)->sum(fn (array $column) => (float) $column['w']);
        $drawHeader = function () use (&$y, $columns, $tableWidth, $addLine, $addText): void {
            $top = $y;
            $bottom = $y - 20;
            $addLine(self::MARGIN_X, $top, self::MARGIN_X + $tableWidth, $top);
            $addLine(self::MARGIN_X, $bottom, self::MARGIN_X + $tableWidth, $bottom);
            foreach ($columns as $column) {
                $addLine((float) $column['x'], $top, (float) $column['x'], $bottom);
                $addText((string) $column['label'], (float) $column['x'] + ((float) $column['w'] / 2), $y - 13, 7.5, true, 'center');
            }
            $addLine(self::MARGIN_X + $tableWidth, $top, self::MARGIN_X + $tableWidth, $bottom);
            $y = $bottom;
        };

        $drawHeader();

        foreach ($rows as $row) {
            $wrapped = [];
            $maxLines = 1;
            foreach ($columns as $index => $column) {
                $lines = [];
                foreach (explode("\n", (string) ($row[$index] ?? '')) as $part) {
                    array_push($lines, ...$this->wrap($part, 7.5, (float) $column['w'] - 8));
                }
                $wrapped[$index] = $lines ?: [''];
                $maxLines = max($maxLines, count($wrapped[$index]));
            }

            $rowHeight = max(24.0, 10.0 + ($maxLines * 10.0));
            if ($y - $rowHeight < 120) {
                $addPage();
                $drawHeader();
            }

            $top = $y;
            $bottom = $y - $rowHeight;
            $addLine(self::MARGIN_X, $bottom, self::MARGIN_X + $tableWidth, $bottom);
            foreach ($columns as $column) {
                $addLine((float) $column['x'], $top, (float) $column['x'], $bottom);
            }
            $addLine(self::MARGIN_X + $tableWidth, $top, self::MARGIN_X + $tableWidth, $bottom);

            foreach ($wrapped as $index => $lines) {
                $textY = $top - 12;
                foreach ($lines as $line) {
                    $align = $index === 0 ? 'center' : 'left';
                    $x = $align === 'center'
                        ? (float) $columns[$index]['x'] + ((float) $columns[$index]['w'] / 2)
                        : (float) $columns[$index]['x'] + 4;
                    $addText($line, $x, $textY, 7.5, false, $align);
                    $textY -= 10;
                }
            }

            $y = $bottom;
        }
    }

    private function construirPdfInscritos(array $snapshot): string
    {
        $pages = [[]];
        $pageIndex = 0;
        $y = self::PAGE_HEIGHT - 50;

        $addPage = function () use (&$pages, &$pageIndex, &$y): void {
            $pages[] = [];
            $pageIndex++;
            $y = self::PAGE_HEIGHT - self::MARGIN_TOP;
        };

        $addText = function (
            string $text,
            float $x,
            float $textY,
            float $size = 9.0,
            bool $bold = false,
            string $align = 'left'
        ) use (&$pages, &$pageIndex): void {
            $pages[$pageIndex][] = [
                'type' => 'text',
                'x' => $x,
                'y' => $textY,
                'size' => $size,
                'bold' => $bold,
                'align' => $align,
                'text' => $text,
            ];
        };

        $addLine = function (float $x1, float $y1, float $x2, float $y2) use (&$pages, &$pageIndex): void {
            $pages[$pageIndex][] = compact('x1', 'y1', 'x2', 'y2') + ['type' => 'line'];
        };

        $logoPath = $this->resolverRutaLogo((string) ($snapshot['competencia']['logo_path'] ?? ''));
        if ($logoPath) {
            $pages[$pageIndex][] = [
                'type' => 'image',
                'path' => $logoPath,
                'x' => self::MARGIN_X,
                'y' => self::PAGE_HEIGHT - 96,
                'width' => 54,
                'height' => 54,
            ];
        }

        $addText('CLUB DE ROBÓTICA ESPOCH', self::PAGE_WIDTH / 2, $y, 14, true, 'center');
        $y -= 16;
        $addText('NÚMERO DE INSCRITOS', self::PAGE_WIDTH / 2, $y, 13, true, 'center');
        $y -= 48;

        $leftX = 82.0;
        $rightX = 356.0;
        $addText('Competencia: ' . $snapshot['competencia']['nombre'], $leftX, $y, 8, true);
        $addText('Resumen', $rightX, $y, 9, true);
        $y -= 13;
        $addText('Categoría: ' . $snapshot['categoria']['nombre'], $leftX, $y, 8, true);
        $addText('Total de inscripciones aprobadas: ' . $snapshot['resumen']['total_aprobados'], $rightX, $y, 8);
        $y -= 13;
        $addText('Integrantes permitidos por equipo: ' . $snapshot['resumen']['total_integrantes'], $leftX, $y, 8);
        $addText('Fecha de generación: ' . $snapshot['generado_at'], $rightX, $y, 8);
        $y -= 30;

        $addText('Detalle de inscripciones aprobadas', self::MARGIN_X, $y, 11, true);
        $y -= 16;

        $columns = [
            ['label' => 'N.', 'x' => self::MARGIN_X, 'w' => 28],
            ['label' => 'Equipo', 'x' => self::MARGIN_X + 28, 'w' => 118],
            ['label' => 'Prototipo', 'x' => self::MARGIN_X + 146, 'w' => 92],
            ['label' => 'Institución', 'x' => self::MARGIN_X + 238, 'w' => 86],
            ['label' => 'Integrantes', 'x' => self::MARGIN_X + 324, 'w' => 187],
        ];
        $tableWidth = 511.0;

        $drawHeader = function () use (&$y, $columns, $tableWidth, $addLine, $addText): void {
            $top = $y;
            $bottom = $y - 20;
            $addLine(self::MARGIN_X, $top, self::MARGIN_X + $tableWidth, $top);
            $addLine(self::MARGIN_X, $bottom, self::MARGIN_X + $tableWidth, $bottom);
            foreach ($columns as $column) {
                $addLine($column['x'], $top, $column['x'], $bottom);
                $addText($column['label'], $column['x'] + ($column['w'] / 2), $y - 13, 8, true, 'center');
            }
            $addLine(self::MARGIN_X + $tableWidth, $top, self::MARGIN_X + $tableWidth, $bottom);
            $y = $bottom;
        };

        $drawHeader();

        foreach ($snapshot['filas'] as $fila) {
            $integrantes = $fila['integrantes'] ? implode("\n", $fila['integrantes']) : 'Sin integrantes registrados';
            $cells = [
                (string) $fila['numero'],
                $fila['equipo'],
                $fila['prototipo'],
                $fila['institucion'] ?: 'Sin institución',
                $integrantes,
            ];
            $wrapped = [];
            $maxLines = 1;

            foreach ($cells as $index => $cell) {
                $lines = [];
                foreach (explode("\n", (string) $cell) as $part) {
                    array_push($lines, ...$this->wrap($part, 8, $columns[$index]['w'] - 10));
                }
                $wrapped[$index] = $lines ?: [''];
                $maxLines = max($maxLines, count($wrapped[$index]));
            }

            $rowHeight = max(24.0, 12.0 + ($maxLines * 11.0));
            if ($y - $rowHeight < 188) {
                $addPage();
                $drawHeader();
            }

            $top = $y;
            $bottom = $y - $rowHeight;
            $addLine(self::MARGIN_X, $bottom, self::MARGIN_X + $tableWidth, $bottom);
            foreach ($columns as $column) {
                $addLine($column['x'], $top, $column['x'], $bottom);
            }
            $addLine(self::MARGIN_X + $tableWidth, $top, self::MARGIN_X + $tableWidth, $bottom);

            foreach ($wrapped as $index => $lines) {
                $textY = $top - 13;
                foreach ($lines as $line) {
                    $x = $index === 0
                        ? $columns[$index]['x'] + ($columns[$index]['w'] / 2)
                        : $columns[$index]['x'] + 5;
                    $addText($line, $x, $textY, 8, false, $index === 0 ? 'center' : 'left');
                    $textY -= 11;
                }
            }

            $y = $bottom;
        }

        $this->agregarFirmasEnGrilla($pages, $pageIndex, $y, $snapshot['jueces']);

        return $this->renderPdf($pages);
    }

    private function agregarFirmasEnGrilla(array &$pages, int &$pageIndex, float &$y, array $jueces): void
    {
        $jueces = $jueces ?: [[
            'numero' => 1,
            'nombre' => 'Juez asignado',
            'nombres' => 'Juez',
            'apellidos' => 'asignado',
            'rol' => 'Juez',
            'rol_label' => 'Juez',
        ]];
        $rowCount = (int) ceil(count($jueces) / 3);
        $footerTop = 154.0;
        $contentGap = 8.0;
        $titleToLineGap = 42.0;
        $rowGap = 44.0;
        $bottomTextDepth = 30.0;
        $bottomPadding = 8.0;
        $signatureTop = $y > ($footerTop + $contentGap)
            ? $footerTop
            : $y - $contentGap;
        $signatureBottom = $signatureTop
            - $titleToLineGap
            - (max(0, $rowCount - 1) * $rowGap)
            - $bottomTextDepth
            - $bottomPadding;

        if ($signatureBottom < self::MARGIN_BOTTOM) {
            $pages[] = [];
            $pageIndex++;
            $y = self::PAGE_HEIGHT - self::MARGIN_TOP;
            $signatureTop = self::PAGE_HEIGHT - 118.0;
        }

        $firstLineY = $signatureTop - $titleToLineGap;

        $pages[$pageIndex][] = [
            'type' => 'text',
            'x' => self::MARGIN_X,
            'y' => $signatureTop,
            'size' => 11,
            'bold' => true,
            'text' => 'Jueces asignados',
        ];

        $columnWidth = 511.0 / 3.0;
        $lineWidth = 128.0;
        $columnXs = array_map(
            fn (int $index) => self::MARGIN_X + ($columnWidth * $index) + (($columnWidth - $lineWidth) / 2),
            [0, 1, 2]
        );
        $lineY = $firstLineY;

        foreach (array_chunk($jueces, 3) as $row) {
            foreach ($row as $index => $juez) {
                $x = $columnXs[$index];
                $pages[$pageIndex][] = [
                    'type' => 'line',
                    'x1' => $x,
                    'y1' => $lineY,
                    'x2' => $x + $lineWidth,
                    'y2' => $lineY,
                ];
                $pages[$pageIndex][] = [
                    'type' => 'text',
                    'x' => $x + ($lineWidth / 2),
                    'y' => $lineY - 12,
                    'size' => 8,
                    'bold' => false,
                    'align' => 'center',
                    'text' => (string) ($juez['rol_label'] ?? $this->rolJuezLabel((string) ($juez['rol'] ?? 'Juez'))),
                ];
                $pages[$pageIndex][] = [
                    'type' => 'text',
                    'x' => $x + ($lineWidth / 2),
                    'y' => $lineY - 22,
                    'size' => 8,
                    'bold' => false,
                    'align' => 'center',
                    'text' => (string) (($juez['nombres'] ?? '') ?: $juez['nombre']),
                ];
                if (! empty($juez['apellidos'])) {
                    $pages[$pageIndex][] = [
                        'type' => 'text',
                        'x' => $x + ($lineWidth / 2),
                        'y' => $lineY - 32,
                        'size' => 8,
                        'bold' => false,
                        'align' => 'center',
                        'text' => (string) $juez['apellidos'],
                    ];
                }
            }
            $lineY -= $rowGap;
        }

        $y = $lineY - $bottomTextDepth - $bottomPadding;
    }

    private function agregarContenidoReporte(array $snapshot, callable $addLine, callable $addSpace): void
    {
        if ($snapshot['tipo_reporte'] === 'inscritos') {
            $resumen = $snapshot['resumen'];
            $addLine('Resumen', 12, true);
            $addLine('Total de inscripciones: ' . $resumen['total_inscripciones']);
            $addLine('Inscripciones confirmadas: ' . $resumen['confirmadas']);
            $addLine('Comprobantes aprobados: ' . $resumen['comprobante_aprobado']);
            $addLine('Integrantes permitidos por equipo: ' . $resumen['total_integrantes']);
            $addSpace(8);
            $addLine('Detalle de inscritos', 12, true);

            foreach ($snapshot['filas'] as $fila) {
                $addLine($fila['numero'] . '. ' . $fila['equipo'] . ' | Prototipo: ' . $fila['prototipo'], 9, true);
                $addLine('Institución: ' . ($fila['institucion'] ?: 'Sin institución') . ' | Estado: ' . $fila['estado'] . ' | Comprobante: ' . $fila['comprobante'], 8, false, 10);
                $addLine('Integrantes: ' . (implode(', ', $fila['integrantes']) ?: 'Sin integrantes registrados'), 8, false, 10);
                $addSpace(4);
            }

            return;
        }

        if ($snapshot['tipo_reporte'] === 'acta_final') {
            $addLine('Podio oficial', 12, true);
            foreach ($snapshot['podio'] as $fila) {
                $addLine('Puesto ' . $fila['posicion'] . ': ' . $fila['equipo'] . ' | Prototipo: ' . $fila['prototipo'], 10, true);
                $addLine('Puntaje: ' . $fila['puntaje_total'] . ' | Tiempo: ' . $fila['tiempo_total'] . ' | Institución: ' . ($fila['institucion'] ?: 'Sin institución'), 8, false, 10);
            }
            $addSpace(8);
        }

        $addLine('Tabla de resultados', 12, true);
        foreach ($snapshot['filas'] as $fila) {
            $addLine($fila['posicion'] . '. ' . $fila['equipo'] . ' | Prototipo: ' . $fila['prototipo'], 9, true);
            $addLine('Puntaje: ' . $fila['puntaje_total'] . ' | Tiempo: ' . $fila['tiempo_total'] . ' | Penalizaciones: ' . $fila['penal_total'] . ' | Estado: ' . $fila['estado_publicacion'], 8, false, 10);
            $addSpace(3);
        }
    }

    private function resolverRutaLogo(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        $storagePrefix = '/storage/';
        if (str_starts_with($path, $storagePrefix)) {
            $relative = substr($path, strlen($storagePrefix));
            if (Storage::disk('public')->exists($relative)) {
                return Storage::disk('public')->path($relative);
            }
        }

        $publicPath = public_path(ltrim($path, '/'));
        if (is_file($publicPath)) {
            return $publicPath;
        }

        return is_file($path) ? $path : null;
    }

    private function prepararImagenPdf(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }

        if (($info[2] ?? null) === IMAGETYPE_JPEG) {
            $data = @file_get_contents($path);

            return $data === false ? null : [
                'width' => (int) $info[0],
                'height' => (int) $info[1],
                'data' => $data,
            ];
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $source = match ($info[2] ?? null) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $data = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return $data === false ? null : [
            'width' => $width,
            'height' => $height,
            'data' => $data,
        ];
    }

    private function renderPdf(array $pages): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        $images = [];
        foreach ($pages as &$commands) {
            foreach ($commands as &$command) {
                if (($command['type'] ?? '') !== 'image') {
                    continue;
                }

                $image = $this->prepararImagenPdf((string) ($command['path'] ?? ''));
                if (! $image) {
                    $command['type'] = 'noop';
                    continue;
                }

                $key = md5((string) $command['path']);
                if (! isset($images[$key])) {
                    $images[$key] = $image + ['name' => 'Im' . (count($images) + 1)];
                }
                $command['name'] = $images[$key]['name'];
            }
        }
        unset($commands, $command);

        $pageObjectIds = [];
        $contentObjectIds = [];
        $contents = [];
        $nextId = 3;

        foreach ($pages as $commands) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageObjectIds[] = $pageId;
            $contentObjectIds[] = $contentId;
            $contents[$contentId] = $this->renderPageContent($commands);
        }

        $fontRegularId = $nextId++;
        $fontBoldId = $nextId++;
        $imageObjectIds = [];

        foreach ($images as $key => $image) {
            $imageObjectIds[$key] = $nextId++;
        }

        $xObjectResources = '';
        if ($images) {
            $entries = [];
            foreach ($images as $key => $image) {
                $entries[] = '/' . $image['name'] . ' ' . $imageObjectIds[$key] . ' 0 R';
            }
            $xObjectResources = ' /XObject << ' . implode(' ', $entries) . ' >>';
        }

        foreach ($pageObjectIds as $index => $pageId) {
            $contentId = $contentObjectIds[$index];
            $content = $contents[$contentId];
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >>{$xObjectResources} >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>';
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($images as $key => $image) {
            $objects[$imageObjectIds[$key]] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $image['width'],
                $image['height'],
                strlen($image['data']),
                $image['data']
            );
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= max(array_keys($objects)); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function renderPageContent(array $commands): string
    {
        $content = '';

        foreach ($commands as $command) {
            if ($command['type'] === 'noop') {
                continue;
            }

            if ($command['type'] === 'line') {
                $content .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $command['x1'], $command['y1'], $command['x2'], $command['y2']);
                continue;
            }

            if ($command['type'] === 'image') {
                $content .= sprintf(
                    "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
                    $command['width'],
                    $command['height'],
                    $command['x'],
                    $command['y'],
                    $command['name']
                );
                continue;
            }

            $font = $command['bold'] ? 'F2' : 'F1';
            $x = (float) $command['x'];
            if (($command['align'] ?? 'left') === 'center') {
                $x -= $this->textWidth((string) $command['text'], (float) $command['size']) / 2;
            } elseif (($command['align'] ?? 'left') === 'right') {
                $x -= $this->textWidth((string) $command['text'], (float) $command['size']);
            }

            $content .= sprintf(
                "BT /%s %.2F Tf 0 0 0 rg %.2F %.2F Td <%s> Tj ET\n",
                $font,
                $command['size'],
                $x,
                $command['y'],
                bin2hex($this->winAnsi($command['text']))
            );
        }

        return $content;
    }

    private function wrap(string $text, float $size, float $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current . ' ' . $word);
            if ($current !== '' && $this->textWidth($candidate, $size) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    private function textWidth(string $text, float $size): float
    {
        return strlen($this->winAnsi($text)) * $size * 0.48;
    }

    private function winAnsi(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
    }

    private function formatearTiempo(float $seconds): string
    {
        $totalSeconds = max(0, (int) floor($seconds));
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $remainingSeconds = $totalSeconds % 60;

        return sprintf('%02dh %02dm %02ds', $hours, $minutes, $remainingSeconds);
    }

    private function formatearFechaHoraReporte(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->timezone(config('app.timezone', 'America/Guayaquil'))
                ->format('d/m/Y, h:i a');
        } catch (\Throwable) {
            return '';
        }
    }

    private function resultadoActaFinalLabel(array $row, array $scope): string
    {
        if (empty($scope['promediar_jueces'])) {
            return (string) ($row['resultado_label'] ?? '-');
        }

        $rawValue = $row['resultado_valor'] ?? null;
        if ($rawValue === null && preg_match('/-?\d+(?:\.\d+)?/', (string) ($row['resultado_label'] ?? ''), $matches)) {
            $rawValue = $matches[0];
        }

        return 'Promedio ' . number_format((float) ($rawValue ?? 0), 2);
    }

    private function rolJuezLabel(string $rol): string
    {
        $rol = trim($rol);
        if ($rol === '') {
            return 'Juez';
        }

        return Str::of($rol)
            ->replace(['_', '-'], ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    }

    private function nombreUsuario(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim((string) $user->name . ' ' . (string) $user->last_name) ?: (string) $user->email;
    }
}
