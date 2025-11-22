<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  {{-- 👇 Igual que los inputs del plugin, sin /frontend --}}
  @vite(['main.js','styles/app.css'])
  @inertiaHead
</head>
<body class="antialiased bg-white text-gray-900">
  @inertia
</body>
</html>
