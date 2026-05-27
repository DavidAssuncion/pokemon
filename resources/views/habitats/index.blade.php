@extends('layouts.app')

@section('title', 'Hábitats')

@section('content')
<h1>Provincias</h1>
    <div id="provincias">
        <div class="tabs" role="tablist" aria-label="Provincias">
            @foreach($provincias as $i => $province)
                <button type="button" class="tab-button @if($i==0) active @endif" data-index="{{ $i }}">{{ $province['name'] }}</button>
            @endforeach
        </div>

        @foreach($provincias as $i => $province)
            <div class="province" data-index="{{ $i }}" style="display: @if($i==0) block @else none @endif;">
                <h2>{{ $province['name'] }}</h2>
                <div class="habitats-grid">
                    @foreach($province['habitats'] as $habitat)
                        <button type="button" class="habitat-button" data-id="{{ $habitat['id'] }}">
                            <img src="/habitats-img/{{ $habitat['id'] }}.webp" alt="{{ $habitat['name'] }}" class="habitat-thumb" onerror="this.style.display='none'">
                            <p class="habitat-name">{{ $habitat['name'] }}</p>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
        // Tabs behaviour
        const tabButtons = document.querySelectorAll('.tab-button');
        const provincePanels = document.querySelectorAll('.province');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = btn.dataset.index;
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                provincePanels.forEach(p => p.style.display = p.dataset.index === idx ? 'block' : 'none');
            });
        });

        // Habitat click -> detail
        document.querySelectorAll('.habitat-button').forEach(button => {
            button.addEventListener('click', () => {
                const habitatId = button.dataset.id;
                window.location.href = `/habitats/${habitatId}`;
            });
        });
    </script>
    @endpush
@endsection
