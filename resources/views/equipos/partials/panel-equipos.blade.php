            <div class="space-y-4">
                <!-- New Team Button -->
                <button
                    @click="showNewTeamForm = !showNewTeamForm"
                    class="w-full px-4 py-3 bg-primary text-on-primary rounded-card text-sm font-medium hover:bg-primary-hover transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nuevo Equipo
                </button>

                <!-- New Team Form -->
                <template x-if="showNewTeamForm">
                    <div class="bg-surface rounded-card shadow-sm border border-line p-4">
                        <input
                            type="text"
                            x-model="newTeamName"
                            placeholder="Nombre del equipo"
                            class="w-full px-3 py-2 bg-surface-sunken border border-line rounded-control text-sm text-ink placeholder:text-ink-subtle focus-ring mb-3"
                            @keydown.enter="createTeam()"
                        >
                        <div class="flex gap-2">
                            <button
                                @click="createTeam()"
                                :disabled="!newTeamName.trim()"
                                class="flex-1 px-3 py-2 bg-success text-on-primary rounded-control text-sm font-medium hover:bg-success/90 disabled:bg-surface-sunken disabled:cursor-not-allowed transition-colors"
                            >
                                Crear
                            </button>
                            <button
                                @click="showNewTeamForm = false; newTeamName = ''"
                                class="px-3 py-2 bg-surface-sunken text-ink rounded-control text-sm font-medium hover:bg-line transition-colors"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Teams List -->
                <div class="space-y-3">
                    <template x-for="team in teams" :key="'team-'+team.id">
                        <div class="bg-surface rounded-card shadow-sm border-2 border-line overflow-hidden transition-all">
                            <!-- Team header -->
                            <div class="flex items-center justify-between gap-2 p-3 border-b border-line bg-surface-sunken">
                                <button
                                    type="button"
                                    @click="toggleEquipo(team)"
                                    :aria-expanded="equipoAbierto(team) ? 'true' : 'false'"
                                    :aria-controls="'team-panel-'+team.id"
                                    class="flex min-w-0 flex-1 items-center gap-2 text-left focus-ring rounded-control"
                                >
                                    <!-- Miniaturas de los 3 slots -->
                                    <span class="flex shrink-0 items-center gap-0.5" aria-hidden="true">
                                        <template x-for="slot in [1,2,3]" :key="'mini-'+team.id+'-'+slot">
                                            <span class="inline-flex items-center">
                                                <template x-if="getMember(team, slot)">
                                                    <x-sprite
                                                        size="inline"
                                                        decorative
                                                        x-bind:src="iconoDe(getMember(team, slot))"
                                                        x-on:error="onSpriteError($event, getMember(team, slot).pokemon_id, getMember(team, slot).es_shiny)"
                                                    />
                                                </template>
                                                <template x-if="!getMember(team, slot)">
                                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded border border-dashed border-line-strong text-2xs text-ink-subtle">?</span>
                                                </template>
                                            </span>
                                        </template>
                                    </span>

                                    <span class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                                        <span class="truncate text-sm font-medium text-ink" x-text="team.name"></span>
                                        <span
                                            x-show="equipoGuardadoId === team.id"
                                            x-cloak
                                            role="status"
                                            class="text-2xs font-bold text-success-ink"
                                            title="Cambios guardados"
                                        >✓</span>
                                        <template x-if="team.members.length < 3">
                                            <span class="px-1.5 py-0.5 bg-surface-sunken text-ink-muted text-2xs font-bold rounded">
                                                INVÁLIDO
                                            </span>
                                        </template>
                                        <template x-if="teamEnExploracion(team) > 0">
                                            <span
                                                class="px-1.5 py-0.5 bg-warning-soft text-warning-ink text-2xs font-bold rounded uppercase"
                                                :title="teamEnExploracion(team) + ' miembro(s) del equipo en exploración'"
                                                x-text="teamEnExploracion(team) + ' en exploración'"
                                            ></span>
                                        </template>
                                    </span>

                                    <svg class="h-4 w-4 shrink-0 text-ink-muted transition-transform" :class="equipoAbierto(team) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <!-- Acciones del equipo (fuera del botón de despliegue) -->
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        @click="startRename(team)"
                                        class="flex h-7 w-7 items-center justify-center rounded text-ink-subtle transition-colors hover:bg-surface hover:text-primary-ink focus-ring"
                                        :aria-label="'Renombrar equipo ' + team.name"
                                        :title="'Renombrar equipo ' + team.name"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="abrirEdicionEquipo(team)"
                                        class="flex h-7 w-7 items-center justify-center rounded text-ink-subtle transition-colors hover:bg-surface hover:text-primary-ink focus-ring"
                                        :aria-label="'Editar equipo ' + team.name"
                                        :title="'Editar equipo ' + team.name"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmDeleteTeam(team)"
                                        class="flex h-7 w-7 items-center justify-center rounded text-ink-subtle transition-colors hover:bg-danger-soft hover:text-danger-ink focus-ring"
                                        :aria-label="'Eliminar equipo ' + team.name"
                                        :title="'Eliminar equipo ' + team.name"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Team members (colapsable) -->
                            <div
                                :id="'team-panel-'+team.id"
                                x-show="equipoAbierto(team)"
                                x-cloak
                                x-transition.opacity.duration.150ms
                                class="p-3"
                            >
                                <div class="grid grid-cols-2 gap-2">
                                    {{-- Retaguardia --}}
                                    <div class="min-w-0 space-y-2">
                                        <div class="flex items-center justify-between gap-2 rounded-control bg-danger-soft px-2 py-1 text-2xs font-bold text-danger-ink">
                                            <span>Retaguardia</span>
                                            <span x-text="miembrosDeFormacion(team, 'retaguardia').length"></span>
                                        </div>
                                        <template x-for="miembro in miembrosDeFormacion(team, 'retaguardia')" :key="'r-'+team.id+'-'+miembro.slot">
                                            <div class="relative min-w-0 rounded-control border border-line bg-surface p-2 text-center">
                                                <x-sprite
                                                    size="96"
                                                    wrapper-class="mx-auto cursor-pointer"
                                                    x-bind:src="iconoDe(miembro.reclutado)"
                                                    x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)"
                                                    x-bind:alt="nombreFormateado(miembro.reclutado)"
                                                    x-bind:title="'Ver detalle de ' + nombreFormateado(miembro.reclutado)"
                                                    @click="openMemberDetail(team, miembro.slot)"
                                                    role="button"
                                                    tabindex="0"
                                                    @keydown.enter="openMemberDetail(team, miembro.slot)"
                                                />
                                                <button
                                                    @click="!enExploracion(miembro.reclutado.id) && removeMember(team, miembro.reclutado)"
                                                    :disabled="enExploracion(miembro.reclutado.id)"
                                                    :title="enExploracion(miembro.reclutado.id) ? 'El Pokémon está en exploración' : 'Quitar miembro del equipo'"
                                                    class="absolute -top-1 -right-1 w-4 h-4 bg-danger text-on-primary rounded-full text-2xs flex items-center justify-center hover:bg-danger/90 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                    aria-label="Quitar miembro"
                                                >
                                                    ✕
                                                </button>
                                                <p
                                                    class="mt-0.5 cursor-pointer text-2xs leading-tight text-ink-muted hover:text-primary-ink"
                                                    @click="openMemberDetail(team, miembro.slot)"
                                                    :title="'Ver detalle de ' + nombreFormateado(miembro.reclutado)"
                                                    x-text="nombreFormateado(miembro.reclutado)"
                                                ></p>
                                                <p class="text-2xs leading-tight text-ink-muted">
                                                    <span x-text="'Slot ' + miembro.slot"></span>
                                                    · <span x-text="posturaEtiqueta(posturaDe(team, miembro.slot))"></span>
                                                </p>
                                                <p class="truncate text-2xs leading-tight text-ink-subtle" x-text="nombreObjeto(itemDe(team, miembro.slot))"></p>
                                            </div>
                                        </template>
                                        <p x-show="miembrosDeFormacion(team, 'retaguardia').length === 0" class="rounded-control border border-dashed border-line-strong p-2 text-center text-2xs text-ink-subtle">
                                            Sin Pokémon
                                        </p>
                                    </div>

                                    {{-- Vanguardia --}}
                                    <div class="min-w-0 space-y-2">
                                        <div class="flex items-center justify-between gap-2 rounded-control bg-primary-soft px-2 py-1 text-2xs font-bold text-primary-ink">
                                            <span>Vanguardia</span>
                                            <span x-text="miembrosDeFormacion(team, 'vanguardia').length"></span>
                                        </div>
                                        <template x-for="miembro in miembrosDeFormacion(team, 'vanguardia')" :key="'v-'+team.id+'-'+miembro.slot">
                                            <div class="relative min-w-0 rounded-control border border-line bg-surface p-2 text-center">
                                                <x-sprite
                                                    size="96"
                                                    wrapper-class="mx-auto cursor-pointer"
                                                    x-bind:src="iconoDe(miembro.reclutado)"
                                                    x-on:error="onSpriteError($event, miembro.reclutado.pokemon_id, miembro.reclutado.es_shiny)"
                                                    x-bind:alt="nombreFormateado(miembro.reclutado)"
                                                    x-bind:title="'Ver detalle de ' + nombreFormateado(miembro.reclutado)"
                                                    @click="openMemberDetail(team, miembro.slot)"
                                                    role="button"
                                                    tabindex="0"
                                                    @keydown.enter="openMemberDetail(team, miembro.slot)"
                                                />
                                                <button
                                                    @click="!enExploracion(miembro.reclutado.id) && removeMember(team, miembro.reclutado)"
                                                    :disabled="enExploracion(miembro.reclutado.id)"
                                                    :title="enExploracion(miembro.reclutado.id) ? 'El Pokémon está en exploración' : 'Quitar miembro del equipo'"
                                                    class="absolute -top-1 -right-1 w-4 h-4 bg-danger text-on-primary rounded-full text-2xs flex items-center justify-center hover:bg-danger/90 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                    aria-label="Quitar miembro"
                                                >
                                                    ✕
                                                </button>
                                                <p
                                                    class="mt-0.5 cursor-pointer text-2xs leading-tight text-ink-muted hover:text-primary-ink"
                                                    @click="openMemberDetail(team, miembro.slot)"
                                                    :title="'Ver detalle de ' + nombreFormateado(miembro.reclutado)"
                                                    x-text="nombreFormateado(miembro.reclutado)"
                                                ></p>
                                                <p class="text-2xs leading-tight text-ink-muted">
                                                    <span x-text="'Slot ' + miembro.slot"></span>
                                                    · <span x-text="posturaEtiqueta(posturaDe(team, miembro.slot))"></span>
                                                </p>
                                                <p class="truncate text-2xs leading-tight text-ink-subtle" x-text="nombreObjeto(itemDe(team, miembro.slot))"></p>
                                            </div>
                                        </template>
                                        <p x-show="miembrosDeFormacion(team, 'vanguardia').length === 0" class="rounded-control border border-dashed border-line-strong p-2 text-center text-2xs text-ink-subtle">
                                            Sin Pokémon
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty teams -->
                    <template x-if="teams.length === 0">
                        <div class="text-center py-8 bg-surface rounded-card shadow-sm border border-line">
                            <svg class="w-12 h-12 mx-auto text-ink-subtle mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-sm text-ink-muted">No hay equipos creados</p>
                        </div>
                    </template>
                </div>
            </div>
