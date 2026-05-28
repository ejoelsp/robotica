<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\ClasificacionConsolidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveResultadosController extends Controller
{
    private const VIEWER_TTL_SECONDS = 90;
    private const VIEWER_KEY_PREFIX = 'live_viewers';

    public function __construct(
        private readonly ClasificacionConsolidacionService $service
    ) {
    }

    public function snapshot(Request $request): JsonResponse
    {
        $request->validate([
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $competenciaId = $this->resolveCompetenciaId($request);

        return response()->json(
            $this->buildSnapshotPayload(
                $competenciaId,
                $request->integer('categoria_id') ?: null,
                $request->integer('ronda_id') ?: null,
                ['visible', 'cerrado'],
                'public'
            )
        );
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'viewer_id' => ['required', 'string', 'max:120'],
        ]);

        $competenciaId = $this->resolveCompetenciaId($request);
        $viewerId = trim((string) $request->string('viewer_id'));

        Cache::put(
            $this->viewerCacheKey($competenciaId, $viewerId),
            now()->toIso8601String(),
            now()->addSeconds(self::VIEWER_TTL_SECONDS)
        );

        return response()->json([
            'ok' => true,
            'competencia_id' => $competenciaId,
            'viewers_count' => $this->countActiveViewers($competenciaId),
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $competenciaId = $this->resolveCompetenciaId($request);
        $categoriaId = $request->integer('categoria_id') ?: null;
        $rondaId = $request->integer('ronda_id') ?: null;

        return $this->buildStreamResponse(
            $competenciaId,
            $categoriaId,
            $rondaId,
            ['visible', 'cerrado'],
            'public'
        );
    }

    public function snapshotAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $competenciaId = $this->resolveCompetenciaId($request);

        return response()->json(
            $this->buildSnapshotPayload(
                $competenciaId,
                $request->integer('categoria_id') ?: null,
                $request->integer('ronda_id') ?: null,
                ['borrador', 'visible', 'cerrado'],
                'admin'
            )
        );
    }

    public function streamAdmin(Request $request): StreamedResponse
    {
        $request->validate([
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $competenciaId = $this->resolveCompetenciaId($request);

        return $this->buildStreamResponse(
            $competenciaId,
            $request->integer('categoria_id') ?: null,
            $request->integer('ronda_id') ?: null,
            ['borrador', 'visible', 'cerrado'],
            'admin'
        );
    }

    private function resolveCompetenciaId(Request $request): int
    {
        return (int) (
            $request->integer('competencia_id')
            ?: DB::table('catalogo.competencias')->where('estado', true)->orderByDesc('id')->value('id')
            ?: DB::table('catalogo.competencias')->orderByDesc('id')->value('id')
        );
    }

    private function buildSnapshotPayload(
        int $competenciaId,
        ?int $categoriaId,
        ?int $rondaId,
        array $estadosPublicacion,
        string $audience
    ): array {
        $payload = $this->service->obtenerPanelEnVivo(
            $competenciaId,
            $categoriaId,
            $rondaId,
            [
                'audience' => $audience,
                'estados_publicacion' => $estadosPublicacion,
            ]
        );

        return $this->appendStreamMeta($payload);
    }

    private function buildStreamResponse(
        int $competenciaId,
        ?int $categoriaId,
        ?int $rondaId,
        array $estadosPublicacion,
        string $audience
    ): StreamedResponse {
        $retryMs = (int) config('resultados_live.sse.retry_ms', 5000);
        $heartbeatMs = (int) config('resultados_live.sse.heartbeat_ms', 3000);
        $iterations = (int) config('resultados_live.sse.iterations', 20);

        return response()->stream(function () use (
            $competenciaId,
            $categoriaId,
            $rondaId,
            $estadosPublicacion,
            $audience,
            $retryMs,
            $heartbeatMs,
            $iterations
        ) {
            ignore_user_abort(true);
            @set_time_limit(0);

            $lastFingerprint = null;
            echo "retry: {$retryMs}\n\n";

            for ($index = 0; $index < $iterations; $index++) {
                if (connection_aborted()) {
                    break;
                }

                $payload = $this->buildSnapshotPayload(
                    $competenciaId,
                    $categoriaId,
                    $rondaId,
                    $estadosPublicacion,
                    $audience
                );

                $fingerprint = md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                if ($index === 0 || $fingerprint !== $lastFingerprint) {
                    echo "event: live-results\n";
                    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                    $lastFingerprint = $fingerprint;
                }

                echo "event: heartbeat\n";
                echo 'data: ' . json_encode([
                    'generated_at' => now()->toIso8601String(),
                    'selected_key' => $payload['meta']['selected_key'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();

                if ($index < $iterations - 1 && $heartbeatMs > 0) {
                    usleep($heartbeatMs * 1000);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function appendStreamMeta(array $payload): array
    {
        $competenciaId = (int) ($payload['competition_id'] ?? 0);

        $payload['stream'] = [
            'mode' => 'short_sse',
            'retry_ms' => (int) config('resultados_live.sse.retry_ms', 5000),
            'heartbeat_ms' => (int) config('resultados_live.sse.heartbeat_ms', 3000),
            'iterations' => (int) config('resultados_live.sse.iterations', 20),
            'viewers_count' => $competenciaId > 0 ? $this->countActiveViewers($competenciaId) : 0,
        ];

        return $payload;
    }

    private function viewerCacheKey(int $competenciaId, string $viewerId): string
    {
        return self::VIEWER_KEY_PREFIX . ':' . $competenciaId . ':' . sha1($viewerId);
    }

    private function countActiveViewers(int $competenciaId): int
    {
        if ($competenciaId <= 0) {
            return 0;
        }

        if (config('cache.default') !== 'database') {
            return 0;
        }

        $table = (string) config('cache.stores.database.table', 'cache');
        $prefix = (string) config('cache.prefix', '');
        $likePattern = $prefix . self::VIEWER_KEY_PREFIX . ':' . $competenciaId . ':%';

        return (int) DB::table($table)
            ->where('key', 'like', $likePattern)
            ->where('expiration', '>=', now()->timestamp)
            ->count();
    }
}
