    <!-- Edit Team Modal -->
    <x-ui.modal
        id="equipo-editar"
        titulo="Editar equipo"
        abierto="showTeamEditModal"
        cerrar="cerrarEdicionEquipo()"
        tamano="max-w-3xl"
    >
        <template x-if="equipoEnEdicion">
            <div class="space-y-4">
                <p class="text-sm text-ink-muted">
                    Equipo <strong class="text-ink" x-text="equipoEnEdicion.name"></strong>
                </p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <template x-for="slot in [1,2,3]" :key="'editar-slot-'+slot">
                        <div class="min-w-0 space-y-2 rounded-control border border-line bg-surface-sunken p-3">
                            <template x-if="getMember(equipoEnEdicion, slot)">
                                <div class="space-y-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <x-sprite
                                            size="64"
                                            x-bind:src="iconoDe(getMember(equipoEnEdicion, slot))"
                                            x-on:error="onSpriteError($event, getMember(equipoEnEdicion, slot).pokemon_id, getMember(equipoEnEdicion, slot).es_shiny)"
                                            x-bind:alt="nombreFormateado(getMember(equipoEnEdicion, slot))"
                                        />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-ink" x-text="nombreFormateado(getMember(equipoEnEdicion, slot))"></p>
                                            <p class="text-2xs text-ink-muted" x-text="'Posición ' + slot"></p>
                                        </div>
                                    </div>

                                    <!-- Formación -->
                                    <div>
                                        <p class="mb-1 text-2xs font-semibold uppercase tracking-wide text-ink-muted">Formación</p>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                @click="setFormacionSlot(equipoEnEdicion, slot, 'vanguardia')"
                                                :aria-pressed="formacionDe(equipoEnEdicion, slot) === 'vanguardia'"
                                                :aria-label="'Vanguardia para ' + nombreFormateado(getMember(equipoEnEdicion, slot))"
                                                :class="formacionDe(equipoEnEdicion, slot) === 'vanguardia'
                                                    ? 'border-primary bg-primary-soft text-primary-ink'
                                                    : 'border-line text-ink-muted hover:text-ink'"
                                                class="flex-1 rounded-control border px-2 py-1 text-2xs font-bold transition-colors focus-ring"
                                            >Vanguardia</button>
                                            <button
                                                type="button"
                                                @click="setFormacionSlot(equipoEnEdicion, slot, 'retaguardia')"
                                                :aria-pressed="formacionDe(equipoEnEdicion, slot) === 'retaguardia'"
                                                :aria-label="'Retaguardia para ' + nombreFormateado(getMember(equipoEnEdicion, slot))"
                                                :class="formacionDe(equipoEnEdicion, slot) === 'retaguardia'
                                                    ? 'border-danger bg-danger-soft text-danger-ink'
                                                    : 'border-line text-ink-muted hover:text-ink'"
                                                class="flex-1 rounded-control border px-2 py-1 text-2xs font-bold transition-colors focus-ring"
                                            >Retaguardia</button>
                                        </div>
                                    </div>

                                    <!-- Postura -->
                                    <div>
                                        <label
                                            :for="'postura-'+equipoEnEdicion.id+'-'+slot"
                                            class="mb-1 block text-2xs font-semibold uppercase tracking-wide text-ink-muted"
                                        >Postura</label>
                                        <select
                                            :id="'postura-'+equipoEnEdicion.id+'-'+slot"
                                            :value="posturaDe(equipoEnEdicion, slot)"
                                            @change="setPosturaSlot(equipoEnEdicion, slot, $event.target.value)"
                                            class="w-full rounded-control border border-line bg-surface px-2 py-1 text-xs text-ink focus-ring"
                                        >
                                            <option value="atacar">Atacar</option>
                                            <option value="guardia">Guardia</option>
                                            <option value="cubrir">Cubrir</option>
                                        </select>
                                    </div>

                                    <!-- Objeto -->
                                    <div>
                                        <label
                                            :for="'objeto-'+equipoEnEdicion.id+'-'+slot"
                                            class="mb-1 block text-2xs font-semibold uppercase tracking-wide text-ink-muted"
                                        >Objeto</label>
                                        <select
                                            :id="'objeto-'+equipoEnEdicion.id+'-'+slot"
                                            :value="itemDe(equipoEnEdicion, slot)"
                                            @change="setItemSlot(equipoEnEdicion, slot, $event.target.value)"
                                            class="w-full rounded-control border border-line bg-surface px-2 py-1 text-xs text-ink focus-ring"
                                        >
                                            <option value="">Sin objeto</option>
                                            @foreach($objetos as $clave => $nombre)
                                                <option value="{{ $clave }}">{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!getMember(equipoEnEdicion, slot)">
                                <div class="space-y-2">
                                    <div class="flex h-16 items-center justify-center rounded border-2 border-dashed border-line-strong text-lg text-ink-subtle">+</div>
                                    <p class="text-center text-2xs text-ink-subtle">Slot <span x-text="slot"></span> vacío</p>
                                    <div class="flex items-center gap-1">
                                        <button type="button" disabled class="flex-1 rounded-control border border-line px-2 py-1 text-2xs font-bold text-ink-subtle opacity-50">Vanguardia</button>
                                        <button type="button" disabled class="flex-1 rounded-control border border-line px-2 py-1 text-2xs font-bold text-ink-subtle opacity-50">Retaguardia</button>
                                    </div>
                                    <select
                                        disabled
                                        :aria-label="'Postura del slot ' + slot + ' (vacío)'"
                                        class="w-full rounded-control border border-line bg-surface px-2 py-1 text-xs text-ink opacity-50"
                                    >
                                        <option>Sin postura</option>
                                    </select>
                                    <select
                                        disabled
                                        :aria-label="'Objeto del slot ' + slot + ' (vacío)'"
                                        class="w-full rounded-control border border-line bg-surface px-2 py-1 text-xs text-ink opacity-50"
                                    >
                                        <option>Sin objeto</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div x-show="edicionError" x-cloak role="alert" class="rounded-control border border-danger bg-danger-soft px-3 py-2 text-sm text-danger-ink">
                    <span x-text="edicionError"></span>
                </div>
                <div class="flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        @click="cerrarEdicionEquipo()"
                        :disabled="edicionGuardando"
                        class="w-full rounded-control bg-surface-sunken px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-line focus-ring disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:px-6"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="guardarEdicionEquipo()"
                        :disabled="edicionGuardando || !equipoEnEdicion.members || equipoEnEdicion.members.length === 0"
                        class="w-full rounded-control bg-danger px-4 py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-danger/90 disabled:cursor-not-allowed disabled:opacity-50 focus-ring sm:w-auto sm:px-6"
                    >
                        <span x-show="!edicionGuardando">Guardar cambios</span>
                        <span x-show="edicionGuardando" x-cloak>Guardando...</span>
                    </button>
                </div>
            </div>
        </template>
    </x-ui.modal>
