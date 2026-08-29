@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Iniciar sesión</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-6">Accede a tu aventura Pokémon</p>

    @if(isset($errors) && $errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 border border-red-200 dark:border-red-800 text-sm" role="alert">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ url('/login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="login-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
            <input
                id="login-name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="username"
                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
            @if(isset($errors) && $errors->has('name'))
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $errors->first('name') }}</p>
            @endif
        </div>

        <div>
            <label for="login-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña</label>
            <input
                id="login-password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
            @if(isset($errors) && $errors->has('password'))
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $errors->first('password') }}</p>
            @endif
        </div>

        <button
            type="submit"
            class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors"
        >
            Entrar
        </button>
    </form>

    <p class="mt-6 text-sm text-center text-gray-500 dark:text-gray-400">
        ¿No tienes cuenta?
        <a href="{{ url('/register') }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">Regístrate</a>
    </p>
</div>
@endsection
