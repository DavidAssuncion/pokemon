@extends('layouts.app')

@section('title', 'Combate')

@push('styles')
    @vite(['resources/css/combate.css', 'resources/js/combate.js'])
@endpush

@section('content')
@livewire('combate')
@endsection
