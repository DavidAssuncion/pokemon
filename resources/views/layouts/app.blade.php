<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Aplicación')</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <header class="app-header">
        <nav class="app-nav" aria-label="Navegación principal">
            <a class="nav-link" href="/pokedex">Pokedex</a>
            <a class="nav-link" href="/habitats">Hábitats</a>
            <a class="nav-link" href="/reclutados">Reclutados</a>
            <a class="nav-link" href="/reclutamiento">Reclutamiento</a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
