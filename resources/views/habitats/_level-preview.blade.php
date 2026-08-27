<!-- Level Preview Partial - Shows how levels will look after changes -->
<div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700" x-data="levelPreview()" x-init="init()">
    <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        Vista previa de niveles
    </h3>

    <div class="grid grid-cols-3 gap-4" role="region" aria-label="Vista previa de los 3 niveles del hábitat">
        <template x-for="level in [1, 2, 3]" :key="level">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700 min-h-[120px] flex flex-col">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nivel @{{ level }}</h4>
                    <span class="text-xs text-gray-400" x-text="getCount(level) + ' pokémon'"></span>
                </div>
                <div class="flex flex-wrap gap-1.5 flex-1 min-h-[80px]" role="list" :aria-label="'Pokémon en nivel ' + level">
                    <template x-for="pokemon in getPokemons(level)" :key="pokemon.id">
                        <div class="relative w-14 h-14 flex-shrink-0 group" role="listitem">
                            <img
                                :src="'/images/iconos_webp/' + pokemon.id + '.webp'"
                                loading="lazy"
                                decoding="async"
                                :alt="pokemon.name"
                                :title="pokemon.name"
                                class="w-full h-full object-contain rounded border border-gray-200 dark:border-gray-700"
                                onerror="this.style.display='none'"
                            >
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity rounded flex items-center justify-center">
                                <span class="text-xs text-white px-1 truncate max-w-full" x-text="pokemon.name"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="getPokemons(level).length === 0">
                        <span class="text-xs text-gray-400 italic w-full text-center py-4">Vacío</span>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function levelPreview() {
    return {
        previewData: { 1: [], 2: [], 3: [] },

        init() {
            // Listen for family updates from the modal
            window.addEventListener('families-updated', () => {
                this.refreshPreview();
            });

            // Initial load
            this.refreshPreview();
        },

        async refreshPreview() {
            try {
                const response = await fetch(`/api/habitats/{{ $habitat['id'] }}/families`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                const families = await response.json();

                this.buildPreview(families);
            } catch (e) {
                console.error('Error loading preview:', e);
            }
        },

        buildPreview(families) {
            const preview = { 1: [], 2: [], 3: [] };

            families.forEach(family => {
                const base = family.base;
                const evolutions = family.evolutions || [];

                // Base pokemon at its level
                const baseLevel = base.level || 2;
                if (preview[baseLevel]) {
                    preview[baseLevel].push({
                        id: base.id,
                        name: base.name,
                    });
                }

                // Evolutions at their levels
                evolutions.forEach(evo => {
                    const level = evo.level || 2;
                    if (preview[level]) {
                        preview[level].push({
                            id: evo.id,
                            name: evo.name,
                        });
                    }
                });
            });

            this.previewData = preview;
        },

        getPokemons(level) {
            return this.previewData[level] || [];
        },

        getCount(level) {
            return (this.previewData[level] || []).length;
        }
    }
}
</script>