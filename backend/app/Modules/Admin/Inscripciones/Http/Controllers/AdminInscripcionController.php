<?php

namespace App\Modules\Admin\Inscripciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\ConfiguracionPago;
use App\Models\Inscripcion;
use App\Modules\Admin\Inscripciones\Requests\RejectComprobanteRequest;
use App\Modules\Admin\Notificaciones\Services\NotificacionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AdminInscripcionController extends Controller
{
    public function index()
    {
        $inscripcionModels = Inscripcion::query()
            ->with([
                'equipo:id,nombre,institucion',
                'categoria:id,nombre,costo_inscripcion',
                'competencia:id,nombre',
                'usuarioRegistro:id,name,email',
            ])
            ->orderByDesc('id')
            ->get();

        $inscriptions = $inscripcionModels
            ->map(function ($inscripcion) {
                $estado = strtolower((string) $inscripcion->estado);
                $estadoComprobante = strtolower((string) ($inscripcion->estado_comprobante ?? 'no_subido'));

                $status = match (true) {
                    $estadoComprobante === 'rechazado' => 'Rechazada',
                    $estado === 'confirmado' => 'Aprobada',
                    default => 'Pendiente',
                };

                $paymentStatus = match ($estadoComprobante) {
                    'aprobado' => 'Pagado',
                    'rechazado' => 'Rechazado',
                    'revision' => 'En revision',
                    'no_subido' => 'No subido',
                    default => 'No subido',
                };

                return [
                    'id' => $inscripcion->id,
                    'team' => $inscripcion->equipo?->nombre ?? 'Sin equipo',
                    'leader' => $inscripcion->usuarioRegistro?->name ?? 'Sin líder',
                    'email' => $inscripcion->usuarioRegistro?->email ?? '',
                    'phone' => $inscripcion->telefono_contacto ?? '',
                    'institution' => $inscripcion->equipo?->institucion ?? '-',
                    'categoryId' => $inscripcion->categoria_id ? (int) $inscripcion->categoria_id : null,
                    'category' => $inscripcion->categoria?->nombre ?? 'Sin categoría',
                    'prototype' => $inscripcion->nombre_prototipo ?? 'Sin prototipo',
                    'categoryPrice' => (float) ($inscripcion->categoria?->costo_inscripcion ?? 0),
                    'status' => $status,
                    'paymentStatus' => $paymentStatus,
                    'paymentObservation' => $inscripcion->observacion_rechazo ?: $inscripcion->motivo_rechazo,
                    'paymentReason' => $inscripcion->motivo_rechazo,
                    'paymentComment' => $inscripcion->observacion_rechazo,
                    'date' => optional($inscripcion->created_at)?->format('Y-m-d'),
                    'comprobante_pago' => $inscripcion->comprobante_pago,
                    'comprobante_url' => $inscripcion->comprobante_pago
                        ? asset('storage/' . $inscripcion->comprobante_pago)
                        : null,
                    'estado_db' => $inscripcion->estado,
                    'estado_comprobante_db' => $inscripcion->estado_comprobante,
                    'puede_validar' => ($inscripcion->estado_comprobante === 'revision') && !empty($inscripcion->comprobante_pago),
                ];
            })
            ->values();

        $competitionIds = $inscripcionModels
            ->pluck('competencia_id')
            ->filter()
            ->unique()
            ->values();

        $inscriptionCountsByCategory = $inscripcionModels
            ->groupBy('categoria_id')
            ->map(fn ($items) => $items->count());

        $categories = Categoria::query()
            ->select('id', 'nombre')
            ->when($competitionIds->isNotEmpty(), fn ($query) => $query->whereIn('competencia_id', $competitionIds))
            ->orderBy('nombre')
            ->get()
            ->map(fn (Categoria $categoria) => [
                'id' => (int) $categoria->id,
                'name' => (string) $categoria->nombre,
                'count' => (int) ($inscriptionCountsByCategory[$categoria->id] ?? 0),
            ])
            ->sortBy('name')
            ->values();

        $stats = [
            [
                'label' => 'Total Inscripciones',
                'value' => $inscriptions->count(),
                'chip' => 'bg-blue-600',
            ],
            [
                'label' => 'Aprobadas',
                'value' => $inscriptions->where('status', 'Aprobada')->count(),
                'chip' => 'bg-emerald-600',
            ],
            [
                'label' => 'Pendientes',
                'value' => $inscriptions->filter(function ($item) {
                    return in_array($item['paymentStatus'], ['No subido', 'En revision'], true);
                })->count(),
                'chip' => 'bg-amber-500',
            ],
            [
                'label' => 'Rechazadas',
                'value' => $inscriptions->where('paymentStatus', 'Rechazado')->count(),
                'chip' => 'bg-rose-600',
            ],
        ];

        $pendingPayments = $inscripcionModels
            ->filter(fn ($inscripcion) => $inscripcion->estado_comprobante === 'revision' && !empty($inscripcion->comprobante_pago))
            ->groupBy(fn ($inscripcion) => $this->paymentBatchKey($inscripcion))
            ->map(function ($group) {
                $principal = $group->sortBy('id')->first();
                $items = $group
                    ->sortBy('id')
                    ->map(function ($inscripcion) {
                        return [
                            'id' => $inscripcion->id,
                            'category' => $inscripcion->categoria?->nombre ?? 'Sin categoría',
                            'prototype' => $inscripcion->nombre_prototipo ?? 'Sin prototipo',
                            'amount' => (float) ($inscripcion->categoria?->costo_inscripcion ?? 0),
                        ];
                    })
                    ->values();

                return [
                    'id' => $principal->id,
                    'leader' => $principal->usuarioRegistro?->name ?? 'Sin líder',
                    'email' => $principal->usuarioRegistro?->email ?? '',
                    'institution' => $principal->equipo?->institucion ?? '-',
                    'competition' => $principal->competencia?->nombre ?? 'Sin competencia',
                    'comprobante_url' => $principal->comprobante_pago
                        ? asset('storage/' . $principal->comprobante_pago)
                        : null,
                    'puede_validar' => true,
                    'items' => $items,
                    'totalAmount' => $items->sum('amount'),
                ];
            })
            ->sortByDesc('id')
            ->values();

        return Inertia::render('Admin/Inscripciones', [
            'inscriptions' => $inscriptions,
            'categories' => $categories,
            'stats' => $stats,
            'pendingPayments' => $pendingPayments,
            'configuracionPago' => $this->configuracionPagoActiva(),
        ]);
    }

    public function guardarConfiguracionPago(Request $request)
    {
        $data = $request->validate([
            'informacion_pago' => ['required', 'string', 'max:5000'],
        ], [
            'informacion_pago.required' => 'Ingresa los datos de pago que verá el competidor.',
            'informacion_pago.max' => 'Los datos de pago no pueden superar los 5000 caracteres.',
        ]);

        ConfiguracionPago::query()->updateOrCreate(
            ['activo' => true],
            ['informacion_pago' => trim($data['informacion_pago'])]
        );

        return back()->with('success', 'Datos de pago actualizados correctamente.');
    }

    public function approve(int $id)
    {
        $inscripcion = Inscripcion::query()->findOrFail($id);

        if (($inscripcion->estado_comprobante ?? null) !== 'revision' || empty($inscripcion->comprobante_pago)) {
            return back()->with('error', 'No se puede aprobar: no hay comprobante en revision.');
        }

        $aprobadas = $this->paymentBatchQuery($inscripcion)->get();
        $aprobadasIds = $aprobadas->pluck('id')->all();

        DB::transaction(function () use ($aprobadas) {
            $aprobadas->each(function ($item) {
                $item->update([
                    'estado' => 'confirmado',
                    'estado_comprobante' => 'aprobado',
                    'motivo_rechazo' => null,
                    'observacion_rechazo' => null,
                    'fecha_revision_comprobante' => now(),
                    'revisado_por' => auth()->id(),
                ]);
            });
        });

        $notificacionService = app(NotificacionService::class);
        $actor = auth()->user();

        Inscripcion::query()
            ->with(['usuarioRegistro:id,name,last_name,email', 'competencia:id,nombre', 'categoria:id,nombre', 'equipo:id,nombre'])
            ->whereIn('id', $aprobadasIds)
            ->get()
            ->each(fn (Inscripcion $item) => $notificacionService->notificarInscripcionAprobada($item, $actor));

        return back()->with('success', 'Comprobante aprobado correctamente.');
    }

    public function reject(RejectComprobanteRequest $request, int $id)
    {
        $inscripcion = Inscripcion::query()->findOrFail($id);

        if (($inscripcion->estado_comprobante ?? null) !== 'revision' || empty($inscripcion->comprobante_pago)) {
            return back()->with('error', 'No se puede rechazar: no hay comprobante en revision.');
        }

        $rechazadas = $this->paymentBatchQuery($inscripcion)->get();
        $rechazadasIds = $rechazadas->pluck('id')->all();

        DB::transaction(function () use ($rechazadas, $request) {
            $rechazadas->each(function ($item) use ($request) {
                $item->update([
                    'estado' => 'pendiente_pago',
                    'estado_comprobante' => 'rechazado',
                    'motivo_rechazo' => $request->motivo,
                    'observacion_rechazo' => $request->observacion,
                    'fecha_revision_comprobante' => now(),
                    'revisado_por' => auth()->id(),
                ]);
            });
        });

        $notificacionService = app(NotificacionService::class);
        $actor = auth()->user();

        Inscripcion::query()
            ->with(['usuarioRegistro:id,name,last_name,email', 'competencia:id,nombre', 'categoria:id,nombre', 'equipo:id,nombre'])
            ->whereIn('id', $rechazadasIds)
            ->get()
            ->each(fn (Inscripcion $item) => $notificacionService->notificarInscripcionRechazada($item, $actor));

        return back()->with('success', 'Comprobante rechazado correctamente.');
    }

    public function corregirDecision(int $id)
    {
        $inscripcion = Inscripcion::query()->findOrFail($id);

        if (!in_array($inscripcion->estado_comprobante, ['aprobado', 'rechazado'], true)) {
            return back()->with('error', 'Solo puedes corregir una decision ya tomada.');
        }

        DB::transaction(function () use ($inscripcion) {
            $this->paymentBatchQuery($inscripcion)->get()->each(function ($item) {
                $item->update([
                    'estado' => 'revision',
                    'estado_comprobante' => 'revision',
                    'motivo_rechazo' => null,
                    'observacion_rechazo' => null,
                    'fecha_revision_comprobante' => now(),
                    'revisado_por' => auth()->id(),
                ]);
            });
        });

        return back()->with('success', 'Se envio a validacion para corregir la decision.');
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $filter = (string) $request->query('filter', 'all');
        $categoryId = (int) $request->query('category_id', 0);
        $q = trim((string) $request->query('q', ''));

        $query = Inscripcion::query()
            ->with([
                'equipo:id,nombre,institucion',
                'categoria:id,nombre,costo_inscripcion',
                'competencia:id,nombre',
                'usuarioRegistro:id,name,email',
            ])
            ->orderByDesc('id');

        if ($filter === 'Aprobada') {
            $query->where('estado_comprobante', 'aprobado');
        } elseif ($filter === 'Rechazada') {
            $query->where('estado_comprobante', 'rechazado');
        } elseif ($filter === 'Pendiente') {
            $query->whereIn('estado_comprobante', ['no_subido', 'revision']);
        }

        if ($categoryId > 0) {
            $query->where('categoria_id', $categoryId);
        }

        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                $where->whereHas('equipo', function ($equipo) use ($q) {
                    $equipo->where('nombre', 'ILIKE', "%{$q}%")
                        ->orWhere('institucion', 'ILIKE', "%{$q}%");
                })
                    ->orWhereHas('usuarioRegistro', function ($usuario) use ($q) {
                        $usuario->where('name', 'ILIKE', "%{$q}%")
                            ->orWhere('email', 'ILIKE', "%{$q}%");
                    })
                    ->orWhereHas('competencia', function ($competencia) use ($q) {
                        $competencia->where('nombre', 'ILIKE', "%{$q}%");
                    })
                    ->orWhereHas('categoria', function ($categoria) use ($q) {
                        $categoria->where('nombre', 'ILIKE', "%{$q}%");
                    });
            });
        }

        $rows = $query->get()->map(function ($inscripcion) {
            $estado = strtolower((string) $inscripcion->estado);
            $estadoComprobante = strtolower((string) ($inscripcion->estado_comprobante ?? 'no_subido'));

            $status = match (true) {
                $estadoComprobante === 'rechazado' => 'Rechazada',
                $estado === 'confirmado' => 'Aprobada',
                default => 'Pendiente',
            };

            $pago = match ($estadoComprobante) {
                'aprobado' => 'Pagado',
                'rechazado' => 'Rechazado',
                'revision' => 'En revision',
                'no_subido' => 'No subido',
                default => 'No subido',
            };

            return [
                'Equipo' => $inscripcion->equipo?->nombre ?? '',
                'Líder' => $inscripcion->usuarioRegistro?->name ?? '',
                'Email' => $inscripcion->usuarioRegistro?->email ?? '',
                'Institución' => $inscripcion->equipo?->institucion ?? '',
                'Competencia' => $inscripcion->competencia?->nombre ?? '',
                'Categoría' => $inscripcion->categoria?->nombre ?? '',
                'Estado' => $status,
                'Pago' => $pago,
                'Observación' => $inscripcion->observacion_rechazo ?: ($inscripcion->motivo_rechazo ?? ''),
                'Fecha' => optional($inscripcion->created_at)?->format('Y-m-d H:i:s') ?? '',
            ];
        })->values()->all();

        $filenameBase = 'inscripciones_' . date('Ymd_His') . '_categoria_' . ($categoryId > 0 ? $categoryId : 'todas');

        if ($format === 'xlsx') {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                return back()->with('error', 'Falta instalar PhpSpreadsheet.');
            }

            $headers = array_keys($rows[0] ?? [
                'Equipo' => '', 'Líder' => '', 'Email' => '', 'Institución' => '', 'Competencia' => '',
                'Categoría' => '', 'Estado' => '', 'Pago' => '', 'Observación' => '', 'Fecha' => '',
            ]);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $column = 1;
            foreach ($headers as $header) {
                $cell = Coordinate::stringFromColumnIndex($column) . '1';
                $sheet->setCellValue($cell, $header);
                $column++;
            }

            $rowNumber = 2;
            foreach ($rows as $row) {
                $column = 1;
                foreach ($headers as $header) {
                    $cell = Coordinate::stringFromColumnIndex($column) . $rowNumber;
                    $sheet->setCellValue($cell, (string) ($row[$header] ?? ''));
                    $column++;
                }
                $rowNumber++;
            }

            for ($i = 1; $i <= count($headers); $i++) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

            $tmpPath = storage_path("app/{$filenameBase}.xlsx");
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmpPath);

            return response()->download($tmpPath, "{$filenameBase}.xlsx")->deleteFileAfterSend(true);
        }

        $headers = array_keys($rows[0] ?? [
            'Equipo' => '', 'Líder' => '', 'Email' => '', 'Institución' => '', 'Competencia' => '',
            'Categoría' => '', 'Estado' => '', 'Pago' => '', 'Observación' => '', 'Fecha' => '',
        ]);

        $filename = "{$filenameBase}.csv";

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = (string) ($row[$header] ?? '');
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function paymentBatchKey(Inscripcion $inscripcion): string
    {
        $timestamp = optional($inscripcion->fecha_subida_comprobante)?->format('Y-m-d H:i:s') ?? 'sin-fecha';

        return implode('|', [
            $inscripcion->user_id,
            $timestamp,
        ]);
    }

    protected function paymentBatchQuery(Inscripcion $inscripcion): Builder
    {
        $timestamp = optional($inscripcion->fecha_subida_comprobante)?->format('Y-m-d H:i:s');

        return Inscripcion::query()
            ->where('user_id', $inscripcion->user_id)
            ->where('estado_comprobante', $inscripcion->estado_comprobante)
            ->whereNotNull('comprobante_pago')
            ->when($timestamp, function (Builder $query) use ($timestamp) {
                $query->whereRaw("to_char(fecha_subida_comprobante, 'YYYY-MM-DD HH24:MI:SS') = ?", [$timestamp]);
            });
    }

    protected function configuracionPagoActiva(): ?array
    {
        $configuracion = ConfiguracionPago::query()
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        return $configuracion ? [
            'id' => $configuracion->id,
            'informacion_pago' => $configuracion->informacion_pago,
        ] : null;
    }
}
