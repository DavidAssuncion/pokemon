{{-- Modal compartido de selección de equipo + formación (gimnasios y hábitats temporales). --}}
<template x-if="showFormacionPopup">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="formacion-modal-title"
         @keydown.escape.window="closeFormacionPopup()">
        <div class="absolute inset-0 scrim" @click="closeFormacionPopup()"></div>
        <div class="relative flex max-h-[90vh] w-full max-w-6xl flex-col rounded-panel bg-surface-raised shadow-modal">
            <div class="shrink-0 px-6 pt-6">
                <h3 id="formacion-modal-title" class="mb-2 text-lg font-bold text-ink">Configurar Formación</h3>
                <p class="text-sm text-ink-muted">
                    Selecciona un equipo y ajusta la posición de cada pokémon antes del combate contra
                    <strong class="text-ink" x-text="formacionObjetivo"></strong>.
                </p>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4">
                {{-- Error del popup --}}
                <div x-show="popupError" x-cloak role="alert" class="text-sm text-danger-ink bg-danger-soft border border-danger rounded-control px-3 py-2 mb-4">
                    <span x-text="popupError"></span>
                </div>

                {{-- Selección de equipo --}}
                <div class="space-y-2 mb-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Equipo</p>
                    <template x-if="teams.length === 0">
                        <p class="py-2 text-center text-sm text-ink-subtle">No tienes equipos creados</p>
                    </template>
                    <template x-for="team in teams" :key="team.id">
                        <button type="button" @click="selectTeam(team.id, team.name)"
                                :class="selectedTeamId === team.id
                                    ? 'border-primary bg-primary-soft'
                                    : 'border-line hover:border-line-strong'"
                                class="flex w-full items-center justify-between rounded-control border-2 p-3 text-left transition-all focus-ring"
                                :aria-label="'Seleccionar equipo ' + team.name">
                            <span class="text-sm font-medium text-ink" x-text="team.name"></span>
                            <template x-if="selectedTeamId === team.id">
                                <svg class="h-5 w-5 text-primary-ink" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                        </button>
                    </template>
                </div>

                {{-- Formación --}}
                <div x-show="selectedTeamId" x-cloak class="space-y-3 mb-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Formación</p>
                    <template x-for="miembro in (selectedTeamMembers || [])" :key="miembro.id">
                        <div class="flex items-center justify-between rounded-control border border-line bg-surface-sunken p-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <x-sprite
                                    size="64"
                                    x-bind:src="iconoReclutado(miembro.reclutado)"
                                    x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)"
                                    x-bind:alt="miembro.reclutado.nombre"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink" x-text="miembro.reclutado.nombre"></p>
                                    <p class="text-xs text-ink-muted" x-text="'Posición ' + miembro.slot"></p>
                                </div>
                            </div>
                            <button type="button"
                                    @click="toggleFormacionSlot(miembro.slot)"
                                    :class="formacion[miembro.slot] === 'vanguardia'
                                        ? 'border-primary bg-primary text-on-primary'
                                        : 'border-line bg-surface-sunken text-ink'"
                                    class="shrink-0 rounded-control border px-3 py-1.5 text-xs font-bold transition-colors focus-ring"
                                    x-text="(formacion[miembro.slot] === 'vanguardia' ? 'Vanguardia' : 'Retaguardia')">
                            </button>
                        </div>
                    </template>
                    <template x-if="!selectedTeamMembers || selectedTeamMembers.length === 0">
                        <p class="py-4 text-center text-sm text-ink-subtle">Selecciona un equipo primero</p>
                    </template>
                </div>

                @include('partials.comparacion-pc')
                @include('partials.leyenda-ivs-evs', ['columnas' => 3, 'colapsable' => true])
            </div>

            {{-- Botones --}}
            <div class="flex shrink-0 flex-col gap-3 border-t border-line px-6 py-4 sm:flex-row sm:justify-end">
                <button @click="closeFormacionPopup()"
                        class="w-full rounded-control bg-surface-sunken px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-line focus-ring sm:w-auto sm:px-6">
                    Cancelar
                </button>
                <button @click="confirmarCombate()"
                        :disabled="!selectedTeamId || combating"
                        class="w-full rounded-control bg-danger px-4 py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-danger/90 disabled:cursor-not-allowed disabled:opacity-50 focus-ring sm:w-auto sm:px-6">
                    <span x-show="!combating">¡Combatir!</span>
                    <span x-show="combating" x-cloak class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Iniciando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
