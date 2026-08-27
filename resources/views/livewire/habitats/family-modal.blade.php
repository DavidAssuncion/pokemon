<div x-data="familyModal()" x-init="init()">
    <!-- Toast -->
    <div x-show="toastMessage" x-transition.opacity.duration.300ms class="fixed top-4 right-4 z-50" role="alert" aria-live="polite">
        <div :class="['px-4 py-3 rounded-lg shadow-lg text-white', toastType === 'success' ? 'bg-green-600' : 'bg-red-600']" class="flex items-center gap-3">
            <span x-text="toastMessage"></span>
            <button @click="toastMessage = ''" class="ml-2 text-white hover:text-gray-200" aria-label="Cerrar notificación">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    <!-- Modal Backdrop -->
    <div x-show="showModal" x-transition.opacity.duration.200ms class="fixed inset-0 bg-black/50 z-40" @click="closeModal()" aria-hidden="true"></div>

    <!-- Modal -->
    <div x-show="showModal" x-transition.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title" @keydown.escape="closeModal()">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col" @click.outside="closeModal()">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 id="modal-title" class="text-xl font-semibold text-gray-900 dark:text-white">Editar Familias Evolutivas</h2>
                <button @click="closeModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400" aria-label="Cerrar modal">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex" role="tablist" aria-label="Acciones de familia">
                    <button
                        role="tab"
                        :aria-selected="activeTab === 'add'"
                        :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', activeTab === 'add' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
                        @click="setActiveTab('add')"
                        tabindex="0"
                    >
                        Añadir familia
                    </button>
                    <button
                        role="tab"
                        :aria-selected="activeTab === 'remove'"
                        :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', activeTab === 'remove' ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
                        @click="setActiveTab('remove')"
                        tabindex="0"
                    >
                        Eliminar familia
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Loading State -->
                <template x-if="loading">
                    <div class="flex items-center justify-center h-64">
                        <div class="animate-spin rounded-full h-10 w-10 border-3 border-blue-500 border-t-transparent"></div>
                    </div>
                </template>

                <!-- Add Family Tab -->
                <template x-if="!loading && activeTab === 'add'">
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Selecciona una familia evolutiva para añadirla al hábitat. Se colocarán automáticamente en los niveles correspondientes según sus etapas.
                        </p>

                        <template x-if="availableFamilies.length > 0">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Familias en otros hábitats</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3" role="list" aria-label="Familias disponibles para añadir">
                                <template x-for="family in availableFamilies" :key="family.evolution_chain_id">
                                    <div class="family-card" :class="{'opacity-50 pointer-events-none': loading}" @click="assign(family.evolution_chain_id)" role="listitem" tabindex="0" @keydown.enter.prevent="assign(family.evolution_chain_id)" @keydown.space.prevent="assign(family.evolution_chain_id)">
                                        <img
                                            :src="'/images/iconos/' + family.base.id + '.png'"
                                            :alt="family.base.name"
                                            class="w-full h-20 object-contain mx-auto mb-2"
                                            onerror="this.style.display='none'"
                                        >
                                        <div class="family-name" x-text="family.base.name"></div>
                                        <span class="family-stages" x-text="family.total_stages + ' evoluciones'"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="unassignedFamilies.length > 0">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white mt-6">Familias sin hábitat asignado</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3" role="list" aria-label="Familias sin hábitat">
                                <template x-for="family in unassignedFamilies" :key="family.evolution_chain_id">
                                    <div class="family-card" :class="{'opacity-50 pointer-events-none': loading}" @click="assign(family.evolution_chain_id)" role="listitem" tabindex="0" @keydown.enter.prevent="assign(family.evolution_chain_id)" @keydown.space.prevent="assign(family.evolution_chain_id)">
                                        <img
                                            :src="'/images/iconos/' + family.base.id + '.png'"
                                            :alt="family.base.name"
                                            class="w-full h-20 object-contain mx-auto mb-2"
                                            onerror="this.style.display='none'"
                                        >
                                        <div class="family-name" x-text="family.base.name"></div>
                                        <span class="family-stages" :data-stages="1 + (family.evolutions?.length || 0)" x-text="(1 + (family.evolutions?.length || 0)) + ' evoluciones'"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="availableFamilies.length === 0 && unassignedFamilies.length === 0">
                            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="mt-2">No hay familias disponibles para añadir</p>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Remove Family Tab -->
                <template x-if="!loading && activeTab === 'remove'">
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Haz clic en una familia asignada para eliminarla del hábitat. Se removerán todos sus pokémon de los niveles correspondientes.
                        </p>

                        <template x-if="assignedFamilies.length > 0">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3" role="list" aria-label="Familias asignadas para eliminar">
                                <template x-for="family in assignedFamilies" :key="family.evolution_chain_id">
                                    <div class="family-card border-red-200 dark:border-red-800 hover:border-red-400 dark:hover:border-red-500" :class="{'opacity-50 pointer-events-none': loading}" @click="remove(family.evolution_chain_id)" role="listitem" tabindex="0" @keydown.enter.prevent="remove(family.evolution_chain_id)" @keydown.space.prevent="remove(family.evolution_chain_id)">
                                        <img
                                            :src="'/images/iconos/' + family.base.id + '.png'"
                                            :alt="family.base.name"
                                            class="w-full h-20 object-contain mx-auto mb-2"
                                            onerror="this.style.display='none'"
                                        >
                                        <div class="family-name" x-text="family.base.name"></div>
                                        <span class="family-stages bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300" x-text="family.total_stages + ' evoluciones'"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="assignedFamilies.length === 0">
                            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                <p class="mt-2">No hay familias asignadas a este hábitat</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Level Preview -->
            <div class="border-t border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Vista previa de niveles</h3>
                <div class="grid grid-cols-3 gap-4">
                    <template x-for="level in [1, 2, 3]" :key="level">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700 min-h-[100px]">
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Nivel @{{ level }}</h4>
                            <div class="flex flex-wrap gap-1.5 min-h-[60px]" role="list" :aria-label="'Pokémon en nivel ' + level">
                                <template x-for="pokemon in (levelPreview[level] || [])" :key="pokemon.id">
                                    <div class="relative w-14 h-14 flex-shrink-0" role="listitem">
                                        <img
                                            :src="'/images/iconos/' + pokemon.id + '.png'"
                                            :alt="pokemon.name"
                                            :title="pokemon.name"
                                            class="w-full h-full object-contain rounded"
                                            onerror="this.style.display='none'"
                                        >
                                        <span class="absolute bottom-0 right-0 bg-black/60 text-white text-xs px-1 rounded-tr rounded-bl" x-text="pokemon.name"></span>
                                    </div>
                                </template>
                                <template x-if="(levelPreview[level] || []).length === 0">
                                    <span class="text-xs text-gray-400 italic w-full text-center py-4">Vacío</span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function familyModal() {
    return {
        showModal: @entangle('showModal').defer,
        activeTab: @entangle('activeTab').defer,
        loading: @entangle('loading').defer,
        assignedFamilies: @entangle('assignedFamilies').defer,
        availableFamilies: @entangle('availableFamilies').defer,
        unassignedFamilies: @entangle('unassignedFamilies').defer,
        levelPreview: @entangle('levelPreview').defer ?? { 1: [], 2: [], 3: [] },
        toastMessage: @entangle('toastMessage').defer,
        toastType: @entangle('toastType').defer,
        habitatId: @entangle('habitatId'),

        init() {
            // Listen for toast events from Livewire
            window.addEventListener('show-toast', (e) => {
                this.toastMessage = e.detail.message;
                this.toastType = e.detail.type;
                setTimeout(() => this.toastMessage = '', 4000);
            });

            // Focus trap for modal
            this.$watch('showModal', (open) => {
                if (open) {
                    setTimeout(() => {
                        const modal = this.$refs.modal;
                        if (modal) {
                            const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"]), [role="listitem"]');
                            if (focusable?.length) focusable[0].focus();
                        }
                    }, 100);
                }
            });
        },

        openModal() {
            this.showModal = true;
            this.$wire.openModal();
        },

        closeModal() {
            this.showModal = false;
            this.$wire.closeModal();
        },

        setActiveTab(tab) {
            this.activeTab = tab;
            this.$wire.setActiveTab(tab);
        },

        assign(chainId) {
            if (this.loading) return;
            this.$wire.assign(chainId);
        },

        remove(chainId) {
            if (this.loading) return;
            if (confirm('¿Eliminar esta familia y todos sus pokémon del hábitat?')) {
                this.$wire.remove(chainId);
            }
        }
    }
}

document.addEventListener('alpine:init', () => {
    Alpine.data('familyModal', familyModal);
});
</script>

<style>
/* Focus visible for accessibility */
[x-data="familyModal()"] button:focus-visible,
[x-data="familyModal()"] [role="tab"]:focus-visible,
[x-data="familyModal()"] [role="listitem"]:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Family card styles */
.family-card {
    @apply bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 cursor-pointer transition-all hover:border-blue-400 dark:hover:border-blue-500 hover:shadow-md;
}

.family-card:disabled,
.family-card[disabled],
.family-card.opacity-50 {
    @apply opacity-50 cursor-not-allowed hover:border-gray-200 dark:hover:border-gray-700 hover:shadow-none;
}

.family-card img {
    @apply w-full h-20 object-contain mx-auto mb-2;
}

.family-card .family-name {
    @apply text-sm font-medium text-gray-900 dark:text-white text-center truncate;
}

.family-card .family-stages {
    @apply inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 mt-1 mx-auto block;
}
</style>