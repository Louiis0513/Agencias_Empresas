@php
    /** @var \App\Services\WorkerScheduleService $scheduleService */
    $tzLabel = $store->timezone ?? 'America/Bogota';
    $defaultEntrada = old('fecha_hora_entrada', $editing ? $scheduleService->toStoreLocalForInput($store, $editing->fecha_hora_entrada) : \Carbon\Carbon::now($tzLabel)->format('Y-m-d\TH:i'));
    $defaultSalida = old('fecha_hora_salida', $editing && $editing->fecha_hora_salida ? $scheduleService->toStoreLocalForInput($store, $editing->fecha_hora_salida) : '');
    $historialApplied = $historialApplied ?? false;
    $historialAttempted = $historialAttempted ?? false;
    $historialFrom = $historialFrom ?? null;
    $historialTo = $historialTo ?? null;
    $historialWorkerId = $historialWorkerId ?? null;
    $historialQuery = array_filter([
        'from' => $historialFrom,
        'to' => $historialTo,
        'worker_id' => $historialWorkerId,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">
                    Registro de horarios
                </h2>
                <p class="mt-1 text-sm text-gray-400">{{ $store->name }} · {{ $tzLabel }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if(session('success'))
                <div class="rounded-xl border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</div>
            @endif

            @if($editing)
                <div class="flex items-center justify-between rounded-xl border border-brand/30 bg-brand/10 px-4 py-3 text-sm text-brand-100">
                    <span>Editando registro #{{ $editing->id }}</span>
                    <a href="{{ route('stores.workers.time-attendance', array_merge(['store' => $store], $historialQuery)) }}" wire:navigate class="text-brand underline hover:no-underline">Cancelar edición</a>
                </div>
            @endif

            @storeCan($store, 'workers.schedules.create')
            <div class="rounded-2xl border border-white/10 bg-dark-card p-6 sm:p-8 shadow-sm">
                <h3 class="text-lg font-semibold text-white">{{ $editing ? 'Actualizar horario' : 'Registrar horario' }}</h3>
                <p class="mt-1 text-sm text-gray-500">Fecha y hora en la zona de la tienda. Mismas reglas que en liquidación de nómina (festivo entrada/salida, compensación).</p>

                <form
                    method="post"
                    action="{{ $editing ? route('stores.workers.schedules.update', [$store, $editing]) : route('stores.workers.schedules.store', $store) }}"
                    class="mt-6 space-y-5"
                    x-data="{
                        entrada: @js($defaultEntrada),
                        salida: @js($defaultSalida),
                        get showFestivoSalida() {
                            const e = String(this.entrada || '');
                            const s = String(this.salida || '').trim();
                            if (e.length < 10 || s.length < 10) return false;
                            return e.slice(0, 10) !== s.slice(0, 10);
                        }
                    }"
                >
                    @csrf
                    @if($editing)
                        @method('PUT')
                    @endif
                    @if($historialApplied)
                        <input type="hidden" name="redirect_from" value="{{ $historialFrom }}" />
                        <input type="hidden" name="redirect_to" value="{{ $historialTo }}" />
                        @if($historialWorkerId !== null && $historialWorkerId !== '')
                            <input type="hidden" name="redirect_worker_id" value="{{ $historialWorkerId }}" />
                        @endif
                    @endif

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Trabajador <span class="text-red-400">*</span></label>
                            <select name="worker_id" required class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-gray-100 focus:border-brand focus:ring-brand">
                                <option value="">Seleccionar…</option>
                                @foreach($workers as $w)
                                    <option value="{{ $w->id }}" @selected((int) old('worker_id', $editing?->worker_id) === $w->id)>{{ $w->name }} @if($w->role) — {{ $w->role->name }} @endif</option>
                                @endforeach
                            </select>
                            @error('worker_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1.5">Entrada <span class="text-red-400">*</span></label>
                                <input type="datetime-local" name="fecha_hora_entrada" x-model="entrada" required class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-gray-100" />
                                @error('fecha_hora_entrada')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-400 mb-1.5">Salida</label>
                                <input type="datetime-local" name="fecha_hora_salida" x-model="salida" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-gray-100" />
                                <p class="mt-1 text-xs text-gray-500">Opcional al crear; puedes cerrar después editando.</p>
                                <p class="mt-1 text-xs text-gray-600" x-show="salida && salida.length >= 10 && entrada && entrada.length >= 10 && !showFestivoSalida" x-cloak>Mismo día civil: no aplica festivo en día de salida.</p>
                                @error('fecha_hora_salida')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                <input type="checkbox" name="es_festivo" value="1" class="mt-1 rounded border-white/20 bg-white/10 text-brand focus:ring-brand" @checked(old('es_festivo', $editing?->es_festivo)) />
                                <span class="text-sm text-gray-300">Es día festivo <span class="text-gray-500">(día de entrada)</span></span>
                            </label>
                            @error('es_festivo')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <template x-if="showFestivoSalida">
                            <div>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                    <input type="checkbox" name="es_festivo2" value="1" class="mt-1 rounded border-white/20 bg-white/10 text-brand focus:ring-brand" @checked(old('es_festivo2', $editing?->es_festivo2)) />
                                    <span class="text-sm text-gray-300">Es día festivo <span class="text-gray-500">(día de salida)</span><span class="mt-1 block text-xs text-gray-500">La salida cae en otro día civil que la entrada.</span></span>
                                </label>
                            </div>
                        </template>
                        <div>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                <input type="checkbox" name="no_compensa_semana_siguiente" value="1" class="mt-1 rounded border-white/20 bg-white/10 text-amber-400 focus:ring-amber-500" @checked(old('no_compensa_semana_siguiente', $editing?->no_compensa_semana_siguiente)) />
                                <span class="text-sm text-gray-300">No compensa en la semana siguiente <span class="mt-1 block text-xs text-gray-500">Solo domingo o festivo en día de entrada.</span></span>
                            </label>
                            @error('no_compensa_semana_siguiente')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @error('es_festivo2')
                        <p class="text-xs text-red-400">{{ $message }}</p>
                    @enderror

                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Observaciones</label>
                        <textarea name="observaciones" rows="2" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-gray-100" placeholder="Opcional">{{ old('observaciones', $editing?->observaciones) }}</textarea>
                        @error('observaciones')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand/25 hover:bg-brand/90">
                            {{ $editing ? 'Guardar cambios' : 'Registrar horario' }}
                        </button>
                    </div>
                </form>
            </div>
            @endstoreCan

            @if($liquidacion)
                <div class="rounded-2xl border border-white/10 bg-dark-card p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Vista previa liquidación (rango seleccionado, turnos cerrados)</h3>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($liquidacion['totalHorasPorTipo'] as $tipo => $horas)
                            @if($horas > 0)
                                <div class="rounded-xl border border-white/5 bg-white/5 px-4 py-3">
                                    <dt class="text-xs text-gray-500">{{ str_replace('Horas', '', $tipo) }}</dt>
                                    <dd class="mt-1 text-lg font-semibold tabular-nums text-white">{{ number_format($horas, 2) }} h</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">Total trabajado: <span class="text-gray-300 font-medium">{{ number_format($liquidacion['totalHorasTrabajadas'], 2) }} h</span></p>
                </div>
            @endif

            <div class="rounded-2xl border border-white/10 bg-dark-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-4 space-y-4">
                    <div>
                        <h3 class="font-semibold text-white">Historial</h3>
                        <p class="text-sm text-gray-500">Elige un rango de fechas (máx. 60 días) y pulsa Aplicar. Opcionalmente filtra por trabajador.</p>
                    </div>
                    <form method="get" action="{{ route('stores.workers.time-attendance', $store) }}" class="flex flex-wrap items-end gap-3">
                        @if($editing)
                            <input type="hidden" name="edit" value="{{ $editing->id }}" />
                        @endif
                        <div>
                            <label for="hist_from" class="block text-xs font-medium text-gray-400 mb-1">Desde <span class="text-red-400">*</span></label>
                            <input type="date" id="hist_from" name="from" value="{{ old('from', $historialFrom) }}" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-100" />
                        </div>
                        <div>
                            <label for="hist_to" class="block text-xs font-medium text-gray-400 mb-1">Hasta <span class="text-red-400">*</span></label>
                            <input type="date" id="hist_to" name="to" value="{{ old('to', $historialTo) }}" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-100" />
                        </div>
                        <div class="min-w-[12rem]">
                            <label for="hist_worker" class="block text-xs font-medium text-gray-400 mb-1">Trabajador</label>
                            <select id="hist_worker" name="worker_id" class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-100">
                                <option value="">Todos</option>
                                @foreach($workers as $w)
                                    <option value="{{ $w->id }}" @selected((string) old('worker_id', $historialWorkerId) === (string) $w->id)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/15">Aplicar</button>
                        @if($historialApplied)
                            <a href="{{ route('stores.workers.time-attendance.classification-excel', array_merge(['store' => $store], $historialQuery, $editing ? ['edit' => $editing->id] : [])) }}" class="inline-flex items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-500/15 px-4 py-2 text-sm font-medium text-emerald-200 hover:bg-emerald-500/25">Descargar Excel (clasificación)</a>
                        @endif
                    </form>
                    @error('from')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    @error('to')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    @error('worker_id')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="overflow-x-auto">
                    @if(!$historialApplied)
                        <p class="px-6 py-10 text-center text-sm text-gray-500">
                            @unless($historialAttempted)
                                Indica <strong class="text-gray-400">desde</strong> y <strong class="text-gray-400">hasta</strong> y pulsa <strong class="text-gray-400">Aplicar</strong> para cargar el historial. Sin rango válido no se listan registros.
                            @else
                                Corrige el rango o los filtros e inténtalo de nuevo.
                            @endunless
                        </p>
                    @elseif($schedules->isEmpty())
                        <p class="px-6 py-10 text-center text-sm text-gray-500">No hay registros en este período.</p>
                    @else
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th class="px-6 py-3">Trabajador</th>
                                    <th class="px-6 py-3">Entrada</th>
                                    <th class="px-6 py-3">Salida</th>
                                    <th class="px-6 py-3">Notas</th>
                                    <th class="px-6 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                @foreach($schedules as $row)
                                    @php
                                        $entLocal = $row->fecha_hora_entrada->copy()->timezone($tzLabel);
                                        $salLocal = $row->fecha_hora_salida?->copy()->timezone($tzLabel);
                                    @endphp
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-100">{{ $row->worker->name ?? '—' }}</div>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                @if($row->es_domingo)<span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-violet-500/20 text-violet-200">Domingo</span>@endif
                                                @if($row->es_festivo)<span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-200">Festivo in</span>@endif
                                                @if($row->es_festivo2)<span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-200">Festivo out</span>@endif
                                                @if($row->no_compensa_semana_siguiente)<span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-orange-500/20 text-orange-200">No compensa</span>@endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 tabular-nums text-gray-300">{{ $entLocal->format('d/m/Y H:i') }}</td>
                                        <td class="px-6 py-4 tabular-nums text-gray-300">
                                            @if($salLocal)
                                                {{ $salLocal->format('d/m/Y H:i') }}
                                            @else
                                                <span class="text-amber-200/90">Pendiente</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 max-w-xs truncate">{{ $row->observaciones ?: '—' }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                @storeCan($store, 'workers.schedules.edit')
                                                <a href="{{ route('stores.workers.time-attendance', array_merge(['store' => $store, 'edit' => $row->id], $historialQuery)) }}" wire:navigate class="text-sm text-brand hover:underline">Editar</a>
                                                @endstoreCan
                                                @storeCan($store, 'workers.schedules.destroy')
                                                <form method="post" action="{{ route('stores.workers.schedules.destroy', [$store, $row]) }}" class="inline" onsubmit="return confirm('¿Eliminar este registro?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if($historialApplied)
                                                        <input type="hidden" name="redirect_from" value="{{ $historialFrom }}" />
                                                        <input type="hidden" name="redirect_to" value="{{ $historialTo }}" />
                                                        @if($historialWorkerId !== null && $historialWorkerId !== '')
                                                            <input type="hidden" name="redirect_worker_id" value="{{ $historialWorkerId }}" />
                                                        @endif
                                                    @endif
                                                    <button type="submit" class="text-sm text-red-400 hover:underline">Eliminar</button>
                                                </form>
                                                @endstoreCan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
