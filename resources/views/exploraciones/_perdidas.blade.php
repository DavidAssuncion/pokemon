@php
    $perdidas = $perdidas ?? [];
    $motivo = $motivo ?? null;
    $span = $span ?? '';
@endphp

@if($perdidas !== [])
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 {{ $span }}">
        <p class="text-xs font-semibold text-red-700 dark:text-red-300 uppercase tracking-wide mb-2 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Recompensas perdidas
        </p>

        @if(is_string($motivo) && trim($motivo) !== '')
            <p class="mb-2 text-xs text-red-700 dark:text-red-300">{{ $motivo }}</p>
        @endif

        <div class="flex flex-wrap gap-3">
            @foreach($perdidas as $perdida)
                <div class="text-center" title="{{ $perdida['tipo_label'] ?? 'Objeto' }} #{{ $perdida['id'] ?? '' }}">
                    <div class="relative inline-block">
                        @if(!empty($perdida['imagen']))
                            <img
                                src="{{ $perdida['imagen'] }}"
                                loading="lazy"
                                decoding="async"
                                alt="{{ $perdida['label'] ?? $perdida['tipo_label'] ?? 'Objeto perdido' }}"
                                class="w-12 h-12 object-contain grayscale opacity-50"
                                onerror="this.style.display='none'"
                            >
                        @else
                            <span class="block w-12 h-12" aria-hidden="true"></span>
                        @endif
                        <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full">
                            ×{{ $perdida['cantidad_perdida'] ?? 0 }}
                        </span>
                    </div>
                    @if(!empty($perdida['label']) || !empty($perdida['tipo_label']))
                        <p class="text-[10px] text-red-700 dark:text-red-300 truncate w-12">
                            {{ $perdida['label'] ?? $perdida['tipo_label'] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
