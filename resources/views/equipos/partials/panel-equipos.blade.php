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
                        <div
                            class="bg-surface rounded-card shadow-sm border-2 overflow-hidden transition-all"
                            :class="selectedTeamId === team.id
                                ? 'border-primary'
                                : 'border-line'"
                        >
                            <!-- Team header -->
                            <div class="flex items-center justify-between p-3 border-b border-line bg-surface-sunken">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button
                                        @click="startRename(team)"
                                        class="text-sm font-medium text-ink hover:text-primary-ink transition-colors"
                                        x-text="team.name"
                                    ></button>
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
                                    <template x-if="composicionBadge(team)">
                                        <span
                                            class="px-1.5 py-0.5 text-2xs font-bold rounded uppercase"
                                            :class="composicionBadge(team).clase"
                                            x-text="composicionBadge(team).texto"
                                        ></span>
                                    </template>
                                </div>
                                <button
                                    @click="confirmDeleteTeam(team)"
                                    class="w-6 h-6 flex items-center justify-center rounded text-ink-subtle hover:text-danger-ink hover:bg-danger-soft transition-colors"
                                    :aria-label="'Eliminar equipo ' + team.name"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Team members -->
                            <div class="p-3">
                                <div class="grid grid-cols-3 gap-1">
                                    <template x-for="slot in [1,2,3]" :key="'slot-'+team.id+'-'+slot">
                                        <div class="text-center min-w-0">
                                            <template x-if="getMember(team, slot)">
                                                <div class="relative">
                                                    <x-sprite
                                                        size="128"
                                                        wrapper-class="mx-auto cursor-pointer"
                                                        x-bind:src="iconoDe(getMember(team, slot))"
                                                        x-on:error="onSpriteError($event, getMember(team, slot).pokemon_id, getMember(team, slot).es_shiny)"
                                                        x-bind:alt="nombreFormateado(getMember(team, slot))"
                                                        x-bind:title="'Ver detalle de ' + nombreFormateado(getMember(team, slot))"
                                                        @click="openMemberDetail(team, slot)"
                                                        role="button"
                                                        tabindex="0"
                                                        @keydown.enter="openMemberDetail(team, slot)"
                                                    />
                                                    <button
                                                        @click="!enExploracion(getMember(team, slot).id) && removeMember(team, getMember(team, slot))"
                                                        :disabled="enExploracion(getMember(team, slot).id)"
                                                        :title="enExploracion(getMember(team, slot).id) ? 'El Pokémon está en exploración' : 'Quitar miembro del equipo'"
                                                        class="absolute -top-1 -right-1 w-4 h-4 bg-danger text-on-primary rounded-full text-2xs flex items-center justify-center hover:bg-danger/90 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                        aria-label="Quitar miembro"
                                                    >
                                                        ✕
                                                    </button>
                                                    <!-- Nombre del Pokémon -->
                                                    <p
                                                        class="text-2xs leading-tight text-ink-muted mt-0.5 cursor-pointer hover:text-primary-ink"
                                                        @click="openMemberDetail(team, slot)"
                                                        :title="'Ver detalle de ' + nombreFormateado(getMember(team, slot))"
                                                        x-text="nombreFormateado(getMember(team, slot))"
                                                    ></p>
                                                    <!-- Formación: iconos seleccionables -->
                                                     <div class="flex flex-row items-center justify-center gap-1">
                                                        <button
                                                            @click="setFormacionSlot(team, slot, 'vanguardia')"
                                                            :class="formacionDe(team, slot) === 'vanguardia' ? 'bg-primary-soft border-primary text-primary-ink' : 'border-transparent text-ink-subtle hover:text-ink opacity-70'"
                                                            class="rounded px-1.5 py-0.5 border transition-colors"
                                                            :aria-label="'Posición vanguardia de ' + nombreFormateado(getMember(team, slot))"
                                                            :aria-pressed="formacionDe(team, slot) === 'vanguardia'"
                                                            :title="'Posición vanguardia de ' + nombreFormateado(getMember(team, slot))"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 01-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 011-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 011.52 0C14.51 3.81 17 5 19 5a1 1 0 011 1z"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            @click="setFormacionSlot(team, slot, 'retaguardia')"
                                                            :class="formacionDe(team, slot) === 'retaguardia' ? 'bg-danger-soft border-danger text-danger-ink' : 'border-transparent text-ink-subtle hover:text-ink opacity-70'"
                                                            class="rounded px-1.5 py-0.5 border transition-colors"
                                                            :aria-label="'Posición retaguardia de ' + nombreFormateado(getMember(team, slot))"
                                                            :aria-pressed="formacionDe(team, slot) === 'retaguardia'"
                                                            :title="'Posición retaguardia de ' + nombreFormateado(getMember(team, slot))"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path d="M14.5 17.5L3 6V3h3l11.5 11.5M13 19l6-6M16 16l4 4M19 21l2-2"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <!-- Postura de ronda -->
                                                    <select
                                                        :value="posturaDe(team, slot)"
                                                        @change="setPosturaSlot(team, slot, $event.target.value)"
                                                        class="mt-1 w-full rounded border border-line-strong bg-surface px-1 py-0.5 text-2xs text-ink border-line-strong bg-surface text-ink"
                                                        :aria-label="'Postura de ' + nombreFormateado(getMember(team, slot))"
                                                        :title="'Postura de ronda de ' + nombreFormateado(getMember(team, slot))"
                                                    >
                                                        <option value="atacar">Atacar</option>
                                                        <option value="guardia">Guardia</option>
                                                        <option value="cubrir">Cubrir</option>
                                                    </select>
                                                    <select
                                                        :value="itemDe(team, slot)"
                                                        @change="guardarItemSlot(team, slot, $event.target.value)"
                                                        :disabled="itemSavingTeamId === team.id"
                                                        class="mt-1 w-full rounded border border-line-strong bg-surface px-1 py-0.5 text-2xs text-ink disabled:opacity-40 disabled:cursor-not-allowed border-line-strong bg-surface text-ink"
                                                        :aria-label="'Objeto de ' + nombreFormateado(getMember(team, slot))"
                                                        :title="'Objeto equipado de ' + nombreFormateado(getMember(team, slot))"
                                                    >
                                                        <option value="">Sin objeto</option>
                                                        @foreach($objetos as $clave => $nombre)
                                                            <option value="{{ $clave }}">{{ $nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </template>
                                            <template x-if="!getMember(team, slot)">
                                                <div>
                                                    <div class="w-full h-16 mx-auto rounded border-2 border-dashed border-line-strong flex items-center justify-center">
                                                        <span class="text-ink-subtle text-lg">+</span>
                                                    </div>
                                                    <p class="text-2xs text-ink-subtle mt-0.5">Slot <span x-text="slot"></span></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <!-- Guardar formación -->
                            <div class="px-3 pb-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="guardarFormacion(team)"
                                        :disabled="formacionSavingTeamId === team.id"
                                        class="flex-1 px-3 py-1.5 bg-ink text-ink-inverse rounded-control text-2xs font-bold uppercase tracking-wide hover:opacity-90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="formacionSavingTeamId === team.id ? 'Guardando...' : 'Guardar formación'"
                                    ></button>
                                </div>
                                <div x-show="formacionSaveError" x-cloak role="alert" class="mt-1 text-2xs text-danger-ink" x-text="formacionSaveError"></div>
                                <div x-show="formacionSaveSuccess" x-cloak role="status" class="mt-1 text-2xs text-success-ink" x-text="formacionSaveSuccess"></div>
                                <div class="flex items-center gap-2 mt-2">
                                    <button
                                        @click="guardarPosturas(team)"
                                        :disabled="posturaSavingTeamId === team.id"
                                        class="flex-1 px-3 py-1.5 bg-primary text-on-primary rounded-control text-2xs font-bold uppercase tracking-wide hover:bg-primary-hover transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                        x-text="posturaSavingTeamId === team.id ? 'Guardando...' : 'Guardar posturas'"
                                    ></button>
                                </div>
                                <div x-show="posturaSaveError" x-cloak role="alert" class="mt-1 text-2xs text-danger-ink" x-text="posturaSaveError"></div>
                                <div x-show="posturaSaveSuccess" x-cloak role="status" class="mt-1 text-2xs text-success-ink" x-text="posturaSaveSuccess"></div>
                                <div x-show="itemSaveError" x-cloak role="alert" class="mt-1 text-2xs text-danger-ink" x-text="itemSaveError"></div>
                                <div x-show="itemSaveSuccess" x-cloak role="status" class="mt-1 text-2xs text-success-ink" x-text="itemSaveSuccess"></div>
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
