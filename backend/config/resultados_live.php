<?php

return [
    'sse' => [
        'retry_ms' => env('RESULTADOS_LIVE_SSE_RETRY_MS', 5000),
        'heartbeat_ms' => env('RESULTADOS_LIVE_SSE_HEARTBEAT_MS', 3000),
        'iterations' => env('RESULTADOS_LIVE_SSE_ITERATIONS', 20),
    ],
];
