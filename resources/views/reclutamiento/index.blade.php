@extends('layouts.app')

@section('title', 'Reclutamiento')

@section('content')
<div x-data="reclutamientoApp()" x-init="init()">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reclutamiento</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Pokémons que sueñan con unirse a tus filas 
                </p>
            </div>
            <button
                x-show="items.length > 0"
                @click="confirmDiscardAll()"
                class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center gap-2 self-start"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Descartar todos
            </button>
        </div>

        <!-- Pokemon grid -->
        <template x-if="items.length > 0">
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9 gap-3">
                <template x-for="item in items" :key="item.id">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden text-center">
                        <!-- Image -->
                        <div class="w-32 h-32 mx-auto relative">
                            <img
                                :src="'/images/iconos/' + item.pokemon_id + '.png'"
                                loading="lazy"
                                decoding="async"
                                :alt="item.nombre"
                                class="w-full h-full object-contain"
                                onerror="this.style.display='none'"
                            >
                            <!-- Quantity badge -->
                            <template x-if="item.cantidad > 1">
                                <span class="absolute top-1 right-1 px-1.5 py-0.5 bg-blue-600 text-white text-[10px] font-bold rounded-full" x-text="item.cantidad"></span>
                            </template>
                        </div>
                        <!-- Info -->
                        <div class="px-2 pb-2">
                            <p class="text-xs font-medium text-gray-900 dark:text-white truncate" x-text="item.nombre"></p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500" x-text="'x' + item.cantidad"></p>
                            <button
                                @click="recruit(item)"
                                class="mt-1 w-full px-2 py-1 bg-blue-600 text-white rounded text-[10px] font-medium hover:bg-blue-700 transition-colors"
                            >
                                Reclutar
                            </button>
                            <button
                                @click="openDiscardModal(item)"
                                class="mt-1 w-full px-2 py-1 bg-red-600 text-white rounded text-[10px] font-medium hover:bg-red-700 transition-colors"
                            >
                                Descartar
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Empty state -->
        <template x-if="items.length === 0">
            <div class="text-center py-16">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-400 text-lg">No hay Pokémon reclutables</p>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Explora hábitats para capturar Pokémon</p>
            </div>
        </template>

    <!-- Confirm Discard All Modal -->
    <template x-if="showDiscardAllModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showDiscardAllModal = false">
            <div class="absolute inset-0 bg-black/60" @click="showDiscardAllModal = false"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
                <div class="text-center mb-4">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Descartar todos</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Se descartarán todos los Pokémon y generarán caramelos de experiencia. Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button
                        @click="showDiscardAllModal = false"
                        class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="discardAll()"
                        class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors"
                    >
                        Descartar
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Individual Discard Modal -->
    <template x-if="showDiscardModal && discardItem">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="showDiscardModal = false; discardItem = null"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/60" @click="showDiscardModal = false; discardItem = null"></div>
            <!-- Modal content -->
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                    Descartar <span x-text="discardItem.nombre"></span>
                </h3>

                <!-- Quantity input -->
                <div class="mb-4">
                    <label for="discard-quantity" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Cantidad
                    </label>
                    <input
                        id="discard-quantity"
                        type="number"
                        min="1"
                        :max="discardItem.cantidad"
                        x-model.number="discardQuantity"
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-colors"
                    >
                </div>

                <!-- Quick quantity buttons -->
                <div class="grid grid-cols-4 gap-2 mb-4">
                    <button
                        @click="setDiscardPercent(25)"
                        class="px-2 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        25%
                    </button>
                    <button
                        @click="setDiscardPercent(50)"
                        class="px-2 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        50%
                    </button>
                    <button
                        @click="setDiscardPercent(75)"
                        class="px-2 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        75%
                    </button>
                    <button
                        @click="setDiscardPercent(100)"
                        class="px-2 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Todos
                    </button>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Se convertirán en caramelos de la familia
                    <span class="font-medium text-gray-900 dark:text-white" x-text="discardItem.nombre"></span>
                </p>

                <div class="flex gap-3">
                    <button
                        @click="showDiscardModal = false; discardItem = null"
                        class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmDiscard()"
                        class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 transition-colors"
                    >
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function reclutamientoApp() {
    return {
        items: @json($reclutables ?? []),
        showDiscardAllModal: false,
        showDiscardModal: false,
        discardItem: null,
        discardQuantity: 1,

        init() {
            // Initialize from server data
        },

        recruit(item) {
            if (item.cantidad > 1) {
                item.cantidad--;
            } else {
                this.items = this.items.filter(i => i.id !== item.id);
            }

            // Optionally send to server
            fetch('/reclutamiento/recruit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reclutable_id: item.id }),
            }).catch(err => console.error('Error:', err));
        },

        confirmDiscardAll() {
            this.showDiscardAllModal = true;
        },

        discardAll() {
            this.items = [];
            this.showDiscardAllModal = false;

            fetch('/reclutamiento/discard-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
            }).catch(err => console.error('Error:', err));
        },

        openDiscardModal(item) {
            this.discardItem = item;
            this.discardQuantity = 1;
            this.showDiscardModal = true;
        },

        setDiscardPercent(pct) {
            const total = this.discardItem.cantidad;
            if (pct === 100) { this.discardQuantity = total; return; }
            this.discardQuantity = Math.max(1, Math.round(total * pct / 100));
        },

        async confirmDiscard() {
            const qty = Math.max(1, Math.min(this.discardQuantity || 1, this.discardItem.cantidad));
            try {
                const response = await fetch('/reclutamiento/discard', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ reclutable_id: this.discardItem.id, cantidad: qty }),
                });
                if (response.ok) {
                    // Update local state
                    if (qty >= this.discardItem.cantidad) {
                        this.items = this.items.filter(i => i.id !== this.discardItem.id);
                    } else {
                        this.discardItem.cantidad -= qty;
                    }
                    this.showDiscardModal = false;
                    this.discardItem = null;
                } else {
                    alert('Error al descartar');
                }
            } catch (err) {
                console.error('Error discarding:', err);
            }
        },
    };
}
</script>
@endpush
@endsection
