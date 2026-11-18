            <!-- Left 1/5: Back, Image (auto size) with title overlay, stacked construction buttons -->
            <div class="lg:col-span-1 space-y-4">
                <!-- Back card (visual) -->
                <a href="/habitats" aria-label="Volver a hábitats" title="Volver a hábitats"
                   class="card-surface p-3 flex items-center gap-3 cursor-pointer transition-all hover:shadow-md hover:border-primary group">
                    <span class="w-8 shrink-0 flex justify-center">
                        <svg class="w-5 h-5 text-ink-muted group-hover:text-primary-ink transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </span>
                    <span class="flex-1 text-sm font-medium text-ink group-hover:text-primary-ink transition-colors">Volver a hábitats</span>
                </a>

                <!-- Image card with title overlay -->
                <div class="card-surface relative p-3 overflow-hidden w-fit min-h-[6rem] flex items-center justify-center">
                    @if(!empty($habitat['image']))
                    <img
                        src="{{ $habitat['image'] }}"
                        alt="{{ $habitat['name'] }}"
                        class="w-auto h-auto max-w-full rounded-control"
                        onerror="this.style.display='none'"
                    >
                    @else
                    <div class="w-48 h-32 rounded-control bg-surface-sunken flex items-center justify-center">
                        <span class="text-xs text-ink-muted">Sin imagen</span>
                    </div>
                    @endif
                    <h1 class="absolute bottom-0 inset-x-0 px-3 py-2 text-on-scrim text-sm font-bold truncate rounded-b-lg"
                        style="background: linear-gradient(to top, var(--color-scrim), transparent)"
                        title="{{ $habitat['name'] }}">{{ $habitat['name'] }}</h1>
                </div>

                <!-- Construction buttons (stacked, full width) -->
                @php
                    $bloqueadoConstruccion = $exploracionesActivas && $exploracionesActivas->count() > 0;
                @endphp
                <div class="space-y-3">
                    <!-- Combate de Ruta (modo por defecto) -->
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : 'setModoRuta()' }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="modo === 'ruta' ? 'ring-2 ring-primary border-primary bg-primary-soft' : 'hover:bg-surface-sunken'"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6 text-danger-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Combate de ruta</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : 'openGranjaModal()' }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface-sunken' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/farm.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Granjas">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Granjas</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "toggleEntrenadores()" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="modo === 'entrenadores' ? 'ring-2 ring-primary border-primary bg-primary-soft' : 'hover:bg-surface-sunken'"
                    >
                        <span class="w-10 shrink-0 flex justify-center">
                            <img
                                :src="spriteUrl(sprites.habitats?.[habitatId]?.clase)"
                                alt=""
                                aria-hidden="true"
                                width="40"
                                height="40"
                                loading="lazy"
                                decoding="async"
                                style="image-rendering: pixelated"
                                class="w-10 h-10 object-contain"
                                onerror="this.onerror=null;if(this.src.indexOf('/images/misc/trainer.webp')===-1){this.src='/images/misc/trainer.webp';}"
                            >
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Entrenadores</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : "toggleExploraciones()" }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : '' }}"
                        :class="modo === 'pokemon' ? 'ring-2 ring-primary border-primary bg-primary-soft' : 'hover:bg-surface-sunken'"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6 text-warning-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Exploraciones</span>
                    </button>
                    <button
                        @click="{{ $bloqueadoConstruccion ? '' : 'openMazmorraModal()' }}"
                        {{ $bloqueadoConstruccion ? 'disabled' : '' }}
                        @if($bloqueadoConstruccion) title="No disponible durante exploraciones activas" @endif
                        class="w-full px-4 py-3 bg-surface border border-line rounded-card text-sm font-medium text-ink transition-colors flex items-center gap-3 {{ $bloqueadoConstruccion ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface-sunken' }}"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <img src="/images/misc/raid.webp" loading="lazy" decoding="async" class="w-8 h-8 object-contain" alt="Mazmorras">
                        </span>
                        <span class="flex-1 text-sm text-left font-medium text-ink">Mazmorras</span>
                    </button>
                    <button
                        @click="openGestionModal()"
                        class="w-full px-4 py-3 bg-primary text-on-primary rounded-card text-sm font-bold transition-colors hover:bg-primary-hover flex items-center gap-3 uppercase tracking-wide"
                    >
                        <span class="w-8 shrink-0 flex justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-sm text-center font-bold uppercase tracking-wide">Admin - Gestion</span>
                    </button>
                </div>
                @if($bloqueadoConstruccion)
                <p class="text-xs text-center text-warning-ink">
                    No disponible durante exploraciones activas
                </p>
                @endif
            </div>
