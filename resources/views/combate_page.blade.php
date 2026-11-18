@extends('layouts.app')

@section('title', 'Combate')

@push('styles')
    @vite(['resources/css/combate.css'])
@endpush

@push('scripts')
    @include('livewire.partials.combate-scripts')
@endpush

@section('content')
@livewire('combate')
@endsection
