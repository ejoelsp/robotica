<h2>Nuevo reclamo registrado: {{ $codigo }}</h2>

<p>Se ha registrado un reclamo formal en el sistema.</p>

<ul>
    <li><strong>Evento:</strong> {{ $reclamo['evento']['nombre'] ?? 'Evento' }}</li>
    <li><strong>Categoría:</strong> {{ $reclamo['categoria']['nombre'] ?? 'Categoría' }}</li>
    <li><strong>Equipo:</strong> {{ $reclamo['equipo']['nombre'] ?? 'Equipo' }}</li>
    <li><strong>Prototipo:</strong> {{ $reclamo['prototipo_nombre'] ?: 'No registrado' }}</li>
    <li><strong>Institución:</strong> {{ $reclamo['institucion'] ?: 'No registrada' }}</li>
</ul>

<p>
    <a href="{{ $url }}">Ver formato del reclamo</a>
</p>
