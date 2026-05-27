<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reclamo {{ $reclamo['codigo'] ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f7;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.45;
        }
        .page {
            width: 920px;
            min-height: 1180px;
            margin: 28px auto;
            background: #ffffff;
            border: 1px solid #d9e2ec;
            padding: 42px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 22px;
        }
        .brand { display: flex; align-items: center; gap: 18px; }
        .logo {
            width: 86px;
            height: 86px;
            border: 1px solid #dbe3ef;
            object-fit: contain;
            padding: 8px;
        }
        .logo-fallback {
            display: grid;
            place-items: center;
            width: 86px;
            height: 86px;
            border: 1px solid #dbe3ef;
            color: #2563eb;
            font-size: 28px;
            font-weight: 700;
        }
        h1 { margin: 0; font-size: 24px; letter-spacing: 0; }
        h2 { margin: 0 0 14px; font-size: 16px; color: #1e3a8a; }
        .muted { color: #64748b; font-size: 13px; }
        .code {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 10px 14px;
            font-weight: 700;
            text-align: right;
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 26px; }
        .section {
            border: 1px solid #e2e8f0;
            padding: 18px;
            margin-top: 18px;
        }
        .field { margin-bottom: 12px; overflow-wrap: anywhere; }
        .label { display: block; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .value { margin-top: 3px; font-size: 15px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; font-size: 14px; overflow-wrap: anywhere; }
        th { color: #475569; font-size: 12px; text-transform: uppercase; }
        .claim-text {
            min-height: 150px;
            white-space: pre-wrap;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 16px;
            font-size: 15px;
            overflow-wrap: anywhere;
            word-break: break-word;
            max-width: 100%;
            overflow: hidden;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 14px;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }
        .actions { width: 920px; margin: 0 auto 24px; text-align: right; }
        .print {
            border: 0;
            background: #2563eb;
            color: #ffffff;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }
        @media print {
            body { background: #ffffff; }
            .page { margin: 0; width: 100%; border: 0; min-height: auto; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button class="print" onclick="window.print()">Imprimir o guardar como PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <div class="brand">
                @if(! empty($reclamo['evento']['logo_url']))
                    <img class="logo" src="{{ $reclamo['evento']['logo_url'] }}" alt="Logo del evento">
                @else
                    <div class="logo-fallback">CR</div>
                @endif
                <div>
                    <h1>Formato formal de reclamo</h1>
                    <div class="muted">{{ $reclamo['evento']['nombre'] ?? 'Evento' }}</div>
                </div>
            </div>
            <div class="code">
                {{ $reclamo['codigo'] ?? 'REC-BORRADOR' }}<br>
                <span class="muted">{{ $reclamo['fecha'] ?? '' }}</span>
            </div>
        </header>

        <section class="grid">
            <div class="section">
                <h2>Datos de la participación</h2>
                <div class="field">
                    <span class="label">Categoría</span>
                    <div class="value">{{ $reclamo['categoria']['nombre'] ?? 'Categoría' }}</div>
                </div>
                <div class="field">
                    <span class="label">Equipo</span>
                    <div class="value">{{ $reclamo['equipo']['nombre'] ?? 'Equipo' }}</div>
                </div>
                <div class="field">
                    <span class="label">Nombre del prototipo</span>
                    <div class="value">{{ $reclamo['prototipo_nombre'] ?: 'No registrado' }}</div>
                </div>
                <div class="field">
                    <span class="label">Institución</span>
                    <div class="value">{{ $reclamo['institucion'] ?: 'No registrada' }}</div>
                </div>
            </div>

            <div class="section">
                <h2>Responsables de la categoría</h2>
                @forelse($reclamo['jueces'] ?? [] as $juez)
                    <div class="field">
                        <span class="label">{{ $juez['rol'] ?? 'Juez' }}</span>
                        <div class="value">{{ $juez['nombre'] ?? 'Juez asignado' }}</div>
                    </div>
                @empty
                    <div class="muted">No existen jueces asignados para esta categoría.</div>
                @endforelse
            </div>
        </section>

        <section class="section">
            <h2>Integrantes del equipo</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Rol</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reclamo['integrantes'] ?? [] as $integrante)
                        <tr>
                            <td>{{ $integrante['nombre'] ?? 'Integrante' }}</td>
                            <td>{{ ! empty($integrante['es_capitan']) ? 'Capitán' : 'Integrante' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">No existen integrantes registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="section">
            <h2>Detalle del reclamo</h2>
            <div class="claim-text">{{ $reclamo['descripcion'] ?? '' }}</div>
        </section>

        <footer class="footer">
            Documento generado automáticamente por el sistema. 
        </footer>
    </main>
</body>
</html>
