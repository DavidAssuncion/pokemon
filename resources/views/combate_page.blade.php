@extends('layouts.app')

@section('title', 'Combate')

@push('styles')
    @vite(['resources/css/combate.css'])
@endpush

@push('scripts')
    @vite(['resources/js/combate.js'])
@endpush

@section('content')
@livewire('combate')
@endsection
