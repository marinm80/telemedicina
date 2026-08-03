<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telemedicina</title>
    @inertiaHead

    <!-- Integración Monorrepo Vite (Vite en /frontend, Laravel en /backend) -->
    @if (app()->environment('local') && !app()->runningUnitTests())
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/src/main.ts"></script>
    @else
        @vite(['src/main.ts'], 'build')
    @endif
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
