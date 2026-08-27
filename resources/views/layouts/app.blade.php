<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Pokemon')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <!-- Dark mode: apply before render to prevent flash -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Header -->
    @include('components.header')

    <!-- Main content -->
    <main class="lg:ml-64 pt-16 min-h-full transition-all duration-300">
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Flash messages -->
            @if(session('success'))
                <x-alert type="success" :dismissible="true">{{ session('success') }}</x-alert>
            @endif
            @if(session('error'))
                <x-alert type="error" :dismissible="true">{{ session('error') }}</x-alert>
            @endif

            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
