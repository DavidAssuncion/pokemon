<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Aplicación')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tipos-tokens')
    <style>[x-cloak] { display: none !important; }</style>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-canvas text-ink min-h-screen flex items-center justify-center px-4 py-10">
    <main class="w-full max-w-md">
        @if(session('status'))
            <x-ui.alert variant="success" class="mb-4">
                {{ session('status') }}
            </x-ui.alert>
        @endif
        @if(session('success'))
            <x-ui.alert variant="success" class="mb-4">
                {{ session('success') }}
            </x-ui.alert>
        @endif
        @if(session('error'))
            <x-ui.alert variant="danger" class="mb-4">
                {{ session('error') }}
            </x-ui.alert>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
