@extends('layouts.auth')

@section('title', 'Registro')

@section('content')
<x-ui.card padding="none" class="p-8">
    <h1 class="text-2xl font-bold text-ink">Crear cuenta</h1>
    <p class="text-sm text-ink-muted mt-1 mb-6">Empieza tu aventura Pokémon</p>

    @if(isset($errors) && $errors->any())
        <x-ui.alert variant="danger" class="mb-4">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ url('/register') }}" class="space-y-4">
        @csrf

        <x-ui.input
            name="name"
            label="Nombre"
            type="text"
            :value="old('name')"
            :error="isset($errors) ? $errors->first('name') : null"
            required
            autofocus
            autocomplete="username"
        />

        <x-ui.input
            name="password"
            label="Contraseña"
            type="password"
            :error="isset($errors) ? $errors->first('password') : null"
            required
            autocomplete="new-password"
        />

        <x-ui.input
            name="password_confirmation"
            label="Confirmar contraseña"
            type="password"
            :error="isset($errors) ? $errors->first('password_confirmation') : null"
            required
            autocomplete="new-password"
        />

        <x-ui.button type="submit" class="w-full">Registrarse</x-ui.button>
    </form>

    <p class="mt-6 text-sm text-center text-ink-muted">
        ¿Ya tienes cuenta?
        <a href="{{ url('/login') }}" class="text-primary-ink hover:underline font-medium">Inicia sesión</a>
    </p>
</x-ui.card>
@endsection
