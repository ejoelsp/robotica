<?php

namespace App\Services;

use App\Models\CertificadoGenerado;
use App\Models\Clasificacion;
use App\Models\Inscripcion;
use App\Models\InscripcionIntegrante;
use App\Models\PlantillaCertificado;
use App\Models\RondaParticipante;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CertificadoService
{
    private const PAGE_WIDTH = 842.0;
    private const PAGE_HEIGHT = 595.0;

    public function tiposCertificados(): array
    {
        return [
            'participacion' => 'Participación',
            'primer_lugar' => '1er lugar',
            'segundo_lugar' => '2do lugar',
            'tercer_lugar' => '3er lugar',
        ];
    }

    public function configuracionDefault(): array
    {
        return [
            'participante' => ['x' => 50, 'y' => 39, 'size' => 24, 'align' => 'center', 'bold' => true, 'visible' => true],
            'competencia' => ['x' => 50, 'y' => 53, 'size' => 18, 'align' => 'center', 'bold' => true, 'visible' => true],
            'categoria' => ['x' => 34, 'y' => 65, 'size' => 11, 'align' => 'left', 'bold' => true, 'visible' => true],
            'equipo' => ['x' => 64, 'y' => 65, 'size' => 11, 'align' => 'left', 'bold' => true, 'visible' => true],
            'prototipo' => ['x' => 34, 'y' => 72, 'size' => 11, 'align' => 'left', 'bold' => true, 'visible' => true],
            'institucion' => ['x' => 70, 'y' => 72, 'size' => 11, 'align' => 'left', 'bold' => true, 'visible' => true],
            'fecha' => ['x' => 50, 'y' => 79, 'size' => 11, 'align' => 'center', 'bold' => true, 'visible' => true],
        ];
    }

    public function obtenerCertificadosCompetidor(int $userId): array
    {
        $inscripciones = Inscripcion::query()
            ->with([
                'competencia:id,nombre,fecha_inicio',
                'categoria:id,nombre',
                'equipo:id,nombre,institucion',
                'integrantes:id,inscripcion_id,nombre_completo,user_id,es_capitan',
            ])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereHas('integrantes', fn ($q) => $q->where('user_id', $userId));
            })
            ->aprobadas()
            ->orderByDesc('id')
            ->get();

        $items = $inscripciones
            ->flatMap(fn (Inscripcion $inscripcion) => $this->serializarCertificadosInscripcion($inscripcion))
            ->values();

        return [
            'summary' => [
                'total' => $items->count(),
                'disponibles' => $items->where('disponible', true)->count(),
            ],
            'items' => $items->all(),
        ];
    }

    public function generarParaIntegrante(int $integranteId, int $userId): CertificadoGenerado
    {
        return $this->generarParaIntegrantePorTipo($integranteId, $userId, null);
    }

    public function generarParaIntegrantePorTipo(int $integranteId, int $userId, ?string $tipoSolicitado = null): CertificadoGenerado
    {
        $integrante = InscripcionIntegrante::query()
            ->with([
                'inscripcion.competencia:id,nombre,fecha_inicio',
                'inscripcion.categoria:id,nombre',
                'inscripcion.equipo:id,nombre,institucion',
                'inscripcion.integrantes:id,inscripcion_id,nombre_completo,user_id,es_capitan',
            ])
            ->findOrFail($integranteId);

        $inscripcion = $integrante->inscripcion;

        abort_unless(
            $inscripcion
            && ((int) $inscripcion->user_id === $userId || (int) $integrante->user_id === $userId),
            403
        );

        $clasificacion = $this->clasificacionPublicada($inscripcion);
        $excluido = $this->inscripcionExcluida($inscripcion);
        $tiposDisponibles = $this->tiposDisponiblesParaInscripcion($clasificacion, $excluido);

        if ($tiposDisponibles === []) {
            throw ValidationException::withMessages([
                'certificado' => 'El certificado estará disponible cuando los resultados se publiquen.',
            ]);
        }

        $tipo = $tipoSolicitado ?: 'participacion';

        if (! in_array($tipo, $tiposDisponibles, true)) {
            throw ValidationException::withMessages([
                'certificado' => 'El certificado solicitado no está disponible para esta inscripción.',
            ]);
        }

        $plantilla = $this->plantillaActiva($inscripcion, $tipo);

        if (! $plantilla) {
            throw ValidationException::withMessages([
                'plantilla' => 'No existe una plantilla activa para este tipo de certificado.',
            ]);
        }

        $datos = $this->datosCertificado($inscripcion, $integrante, $tipo);
        $pdf = $this->construirPdf(
            Storage::disk('public')->path($plantilla->archivo_plantilla),
            $datos,
            $plantilla->configuracion_textos ?: $this->configuracionDefault()
        );

        $rutaPdf = sprintf(
            'certificados/generados/%s/%s/%s.pdf',
            $inscripcion->competencia_id,
            $inscripcion->categoria_id,
            $this->nombreArchivo($integrante->nombre_completo . '-' . $tipo . '-' . $plantilla->id)
        );

        Storage::disk('public')->put($rutaPdf, $pdf);

        return CertificadoGenerado::query()->updateOrCreate(
            [
                'inscripcion_integrante_id' => $integrante->id,
                'plantilla_certificado_id' => $plantilla->id,
            ],
            [
                'competencia_id' => $inscripcion->competencia_id,
                'categoria_id' => $inscripcion->categoria_id,
                'equipo_id' => $inscripcion->equipo_id,
                'inscripcion_id' => $inscripcion->id,
                'tipo_certificado' => $tipo,
                'archivo_pdf' => $rutaPdf,
                'datos_json' => $datos,
                'fecha_generacion' => now(),
            ]
        );
    }

    public function construirPdfDesdePlantilla(PlantillaCertificado $plantilla): string
    {
        $datos = [
            'participante' => 'NOMBRE DEL PARTICIPANTE',
            'competencia' => $plantilla->competencia?->nombre ?? 'COMPETENCIA O EVENTO',
            'categoria' => 'CATEGORÍA',
            'equipo' => 'EQUIPO',
            'prototipo' => 'PROTOTIPO',
            'institucion' => 'INSTITUCIÓN/CLUB',
            'fecha' => now()->format('d/m/Y'),
        ];

        return $this->construirPdf(
            Storage::disk('public')->path($plantilla->archivo_plantilla),
            $datos,
            $plantilla->configuracion_textos ?: $this->configuracionDefault()
        );
    }

    private function serializarCertificadosInscripcion(Inscripcion $inscripcion)
    {
        $clasificacion = $this->clasificacionPublicada($inscripcion);
        $excluido = $this->inscripcionExcluida($inscripcion);
        $tiposDisponibles = $this->tiposDisponiblesParaInscripcion($clasificacion, $excluido);

        return $inscripcion->integrantes
            ->sortByDesc(fn (InscripcionIntegrante $integrante) => (bool) $integrante->es_capitan)
            ->flatMap(function (InscripcionIntegrante $integrante) use ($inscripcion, $clasificacion, $tiposDisponibles) {
                return collect($tiposDisponibles)->map(function (string $tipo) use ($integrante, $inscripcion, $clasificacion) {
                    $plantilla = $this->plantillaActiva($inscripcion, $tipo);

                    return [
                        'id' => sprintf('%d-%s', $integrante->id, $tipo),
                        'integrante_id' => (int) $integrante->id,
                        'participante' => (string) $integrante->nombre_completo,
                        'competencia' => (string) ($inscripcion->competencia?->nombre ?? 'Competencia'),
                        'categoria' => (string) ($inscripcion->categoria?->nombre ?? 'Categoría'),
                        'equipo' => (string) ($inscripcion->equipo?->nombre ?? 'Sin equipo'),
                        'prototipo' => (string) ($inscripcion->nombre_prototipo ?? 'Sin prototipo'),
                        'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
                        'posicion' => $tipo === 'participacion' || ! $clasificacion
                            ? null
                            : $this->posicionPodioCertificado($clasificacion, $this->esRondaDePodio($clasificacion)),
                        'tipo_certificado' => $tipo,
                        'tipo_label' => $this->tiposCertificados()[$tipo] ?? $tipo,
                        'disponible' => $plantilla !== null,
                        'mensaje' => $plantilla ? null : 'Falta plantilla activa para este certificado.',
                    ];
                });
            });
    }

    private function clasificacionPublicada(Inscripcion $inscripcion): ?Clasificacion
    {
        if (! $inscripcion->id) {
            return null;
        }

        $clasificaciones = Clasificacion::query()
            ->with('ronda:id,nombre,tipo,orden,es_final')
            ->where('competencia_id', $inscripcion->competencia_id)
            ->where('categoria_id', $inscripcion->categoria_id)
            ->where(function ($query) use ($inscripcion) {
                $query->where('inscripcion_id', $inscripcion->id)
                    ->orWhereRaw("detalle_json #>> '{evaluaciones,0,inscripcion_id}' = ?", [(string) $inscripcion->id]);
            })
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->get()
            ->values();

        $tieneRondasDePodio = $clasificaciones->contains(fn (Clasificacion $clasificacion) => $this->esRondaDePodio($clasificacion));

        return $clasificaciones
            ->filter(fn (Clasificacion $clasificacion) => $this->posicionPodioCertificado($clasificacion, $tieneRondasDePodio) !== null)
            ->sortByDesc(fn (Clasificacion $clasificacion) => [
                $this->prioridadRondaPodio($clasificacion),
                (int) ($clasificacion->ronda?->orden ?? 0),
                (int) $clasificacion->id,
            ])
            ->first();
    }

    private function tipoPorPosicion(int $posicion): string
    {
        return match ($posicion) {
            1 => 'primer_lugar',
            2 => 'segundo_lugar',
            3 => 'tercer_lugar',
            default => 'participacion',
        };
    }

    private function tiposDisponiblesParaInscripcion(?Clasificacion $clasificacion, bool $excluido): array
    {
        $tipos = ['participacion'];

        if ($clasificacion) {
            $posicionPodio = $this->posicionPodioCertificado($clasificacion, $this->esRondaDePodio($clasificacion));
            $tipoPodio = $posicionPodio ? $this->tipoPodioPorPosicion($posicionPodio) : null;
            if ($tipoPodio) {
                $tipos[] = $tipoPodio;
            }
        }

        if ($excluido) {
            return array_values(array_unique($tipos));
        }

        return array_values(array_unique($tipos));
    }

    private function tipoPodioPorPosicion(int $posicion): ?string
    {
        return match ($posicion) {
            1 => 'primer_lugar',
            2 => 'segundo_lugar',
            3 => 'tercer_lugar',
            default => null,
        };
    }

    private function posicionPodioCertificado(Clasificacion $clasificacion, bool $tieneRondasDePodio): ?int
    {
        $tipoRonda = (string) ($clasificacion->ronda?->tipo ?? '');
        $posicion = (int) $clasificacion->posicion;

        if ((bool) ($clasificacion->ronda?->es_final ?? false) || $tipoRonda === 'final') {
            return in_array($posicion, [1, 2], true) ? $posicion : null;
        }

        if ($tipoRonda === 'tercer_lugar') {
            return $posicion === 1 ? 3 : null;
        }

        if ($tieneRondasDePodio) {
            return null;
        }

        return in_array($posicion, [1, 2, 3], true) ? $posicion : null;
    }

    private function esRondaDePodio(Clasificacion $clasificacion): bool
    {
        $tipoRonda = (string) ($clasificacion->ronda?->tipo ?? '');

        return (bool) ($clasificacion->ronda?->es_final ?? false)
            || in_array($tipoRonda, ['final', 'tercer_lugar'], true);
    }

    private function prioridadRondaPodio(Clasificacion $clasificacion): int
    {
        $tipoRonda = (string) ($clasificacion->ronda?->tipo ?? '');

        if ((bool) ($clasificacion->ronda?->es_final ?? false) || $tipoRonda === 'final') {
            return 3;
        }

        if ($tipoRonda === 'tercer_lugar') {
            return 2;
        }

        return 1;
    }

    private function inscripcionExcluida(Inscripcion $inscripcion): bool
    {
        return RondaParticipante::query()
            ->where('inscripcion_id', $inscripcion->id)
            ->where('estado', 'excluido')
            ->exists();
    }

    private function plantillaActiva(Inscripcion $inscripcion, string $tipo): ?PlantillaCertificado
    {
        $anio = $inscripcion->competencia?->fecha_inicio?->year;

        return PlantillaCertificado::query()
            ->where('competencia_id', $inscripcion->competencia_id)
            ->where('tipo_certificado', $tipo)
            ->where('activo', true)
            ->when($anio, fn ($query) => $query->where(function ($q) use ($anio) {
                $q->where('anio', $anio)->orWhereNull('anio');
            }))
            ->orderByRaw('CASE WHEN anio IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->first();
    }

    private function datosCertificado(Inscripcion $inscripcion, InscripcionIntegrante $integrante, string $tipo): array
    {
        return [
            'participante' => (string) $integrante->nombre_completo,
            'competencia' => (string) ($inscripcion->competencia?->nombre ?? 'Competencia'),
            'categoria' => (string) ($inscripcion->categoria?->nombre ?? 'Categoría'),
            'equipo' => (string) ($inscripcion->equipo?->nombre ?? 'Sin equipo'),
            'prototipo' => (string) ($inscripcion->nombre_prototipo ?? 'Sin prototipo'),
            'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
            'fecha' => now()->format('d/m/Y'),
            'tipo_certificado' => $tipo,
        ];
    }

    private function construirPdf(string $templatePath, array $datos, array $configuracion): string
    {
        if (! is_file($templatePath)) {
            throw ValidationException::withMessages([
                'plantilla' => 'No se encontró el archivo de plantilla.',
            ]);
        }

        [$imageBytes, $width, $height] = $this->jpegDesdeImagen($templatePath);
        $content = "q\n" . self::PAGE_WIDTH . " 0 0 " . self::PAGE_HEIGHT . " 0 0 cm\n/Im1 Do\nQ\n";

        foreach ($datos as $key => $value) {
            if (! isset($configuracion[$key])) {
                continue;
            }

            if (($configuracion[$key]['visible'] ?? true) === false) {
                continue;
            }

            $content .= $this->comandoTexto((string) $value, $configuracion[$key]);
        }

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> /XObject << /Im1 4 0 R >> >> /Contents 7 0 R >>",
            4 => "<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($imageBytes) . " >>\nstream\n{$imageBytes}\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            6 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
            7 => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function comandoTexto(string $text, array $config): string
    {
        $size = (float) ($config['size'] ?? 12);
        $x = ((float) ($config['x'] ?? 50) / 100) * self::PAGE_WIDTH;
        $y = self::PAGE_HEIGHT - (((float) ($config['y'] ?? 50) / 100) * self::PAGE_HEIGHT);
        $align = (string) ($config['align'] ?? 'left');
        $font = ! empty($config['bold']) ? 'F2' : 'F1';
        $width = strlen($this->winAnsi($text)) * $size * 0.48;

        if ($align === 'center') {
            $x -= $width / 2;
        } elseif ($align === 'right') {
            $x -= $width;
        }

        return sprintf(
            "BT /%s %.2F Tf 0 0 0 rg %.2F %.2F Td <%s> Tj ET\n",
            $font,
            $size,
            max(0, $x),
            max(0, $y),
            bin2hex($this->winAnsi($text))
        );
    }

    private function winAnsi(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
    }

    private function jpegDesdeImagen(string $path): array
    {
        $info = getimagesize($path);

        if (! $info) {
            throw ValidationException::withMessages([
                'plantilla' => 'La plantilla debe ser una imagen JPG o PNG válida.',
            ]);
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            return [file_get_contents($path), (int) $info[0], (int) $info[1]];
        }

        if ($info[2] !== IMAGETYPE_PNG || ! function_exists('imagecreatefrompng')) {
            throw ValidationException::withMessages([
                'plantilla' => 'Para plantillas PNG se requiere la extensión GD de PHP.',
            ]);
        }

        $source = imagecreatefrompng($path);
        $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

        ob_start();
        imagejpeg($canvas, null, 92);
        $bytes = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return [$bytes, (int) $info[0], (int) $info[1]];
    }

    private function nombreArchivo(string $name): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $normalized);

        return trim((string) $normalized, '-') ?: 'certificado';
    }
}
