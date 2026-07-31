@php
    $tercero = $tercero ?? null;
    $editing = (bool) $tercero;
    $activeRoles = old('roles', $editing ? $tercero->roles->where('activo', true)->pluck('rol')->all() : []);
    $cliente = $editing ? $tercero->perfilCliente : null;
    $gym = $cliente?->gym;
    $proveedor = $editing ? $tercero->perfilProveedor : null;
    $trabajador = $editing ? $tercero->perfilTrabajador : null;
    $oldProductIds = old('productos');
    $selectedProductModels = $editing ? $tercero->productos : collect();
    $selectedProducts = collect($oldProductIds ?? $selectedProductModels->pluck('id')->all())
        ->map(function ($id) use ($selectedProductModels) {
            $product = $selectedProductModels->firstWhere('id', (int) $id);

            return [
                'id' => (int) $id,
                'name' => $product?->name ?? 'Producto #'.$id,
                'sku' => $product?->sku,
                'barcode' => $product?->barcode,
            ];
        })
        ->values()
        ->all();
    $newContacts = old($editing ? 'contactos_nuevos' : 'contactos', []);
    $inputClass = 'w-full rounded-xl border-white/10 bg-white/5 text-gray-100 placeholder:text-gray-600 focus:border-brand focus:ring-brand';
    $cardClass = 'rounded-xl border border-white/5 bg-dark-card p-5 sm:p-6';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $editing ? 'Editar tercero' : 'Crear tercero' }}</h2>
                <p class="text-sm text-gray-400">{{ $store->name }}</p>
            </div>
            <a href="{{ route('stores.terceros', $store) }}" class="text-sm text-gray-400 hover:text-brand">← Volver a terceros</a>
        </div>
    </x-slot>

    <div
        class="py-12"
        x-data="{
            roles: @js(array_values($activeRoles)),
            tipoIdentificacion: @js(old('tipo_identificacion', $tercero?->tipo_identificacion)),
            numeroIdentificacion: @js(old('numero_identificacion', $tercero?->numero_identificacion)),
            dv: @js(old('digito_verificacion', $tercero?->digito_verificacion)),
            contactos: @js(array_values($newContacts)),
            selectedProducts: @js($selectedProducts),
            productQuery: '',
            productResults: [],
            searchingProducts: false,
            productError: '',
            searchTimer: null,
            init() {
                this.contactos = this.contactos.map(contacto => ({
                    nombre: '',
                    telefono: '',
                    email: '',
                    parentesco: '',
                    tipo_contacto: 'principal',
                    ...contacto
                }));
                this.calcularDv();
                this.$watch('tipoIdentificacion', () => this.calcularDv());
                this.$watch('numeroIdentificacion', () => this.calcularDv());
            },
            calcularDv() {
                if (this.tipoIdentificacion !== 'NIT') {
                    this.dv = '';
                    return;
                }
                const digits = String(this.numeroIdentificacion ?? '').replace(/\D/g, '');
                if (!digits || digits.length > 15) {
                    this.dv = '';
                    return;
                }
                const weights = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
                const padded = digits.padStart(weights.length, '0');
                const sum = weights.reduce((total, weight, index) => total + Number(padded[index]) * weight, 0);
                const remainder = sum % 11;
                this.dv = String(remainder <= 1 ? remainder : 11 - remainder);
            },
            addContact() {
                this.contactos.push({ nombre: '', telefono: '', email: '', parentesco: '', tipo_contacto: 'principal' });
            },
            async searchProducts() {
                const query = this.productQuery.trim();
                if (!query) {
                    this.productResults = [];
                    return;
                }
                this.searchingProducts = true;
                this.productError = '';
                const params = new URLSearchParams({ q: query });
                this.selectedProducts.forEach(product => params.append('exclude[]', product.id));
                try {
                    const response = await fetch(`{{ route('stores.terceros.productos.buscar', $store) }}?${params.toString()}`, {
                        headers: { Accept: 'application/json' }
                    });
                    if (!response.ok) throw new Error('No fue posible buscar productos.');
                    this.productResults = await response.json();
                } catch (error) {
                    this.productResults = [];
                    this.productError = error.message;
                } finally {
                    this.searchingProducts = false;
                }
            },
            debounceProductSearch() {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.searchProducts(), 350);
            },
            selectProduct(product) {
                if (!this.selectedProducts.some(selected => selected.id === product.id)) {
                    this.selectedProducts.push(product);
                }
                this.productResults = this.productResults.filter(result => result.id !== product.id);
            },
            removeProduct(id) {
                this.selectedProducts = this.selectedProducts.filter(product => product.id !== id);
            }
        }"
    >
        <div class="mx-auto max-w-6xl space-y-5 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/40 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-xl border border-red-500/30 bg-red-950/40 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-500/30 bg-red-950/40 px-4 py-3 text-red-200">
                    <ul class="list-inside list-disc text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ $editing ? route('stores.terceros.update', [$store, $tercero]) : route('stores.terceros.store', $store) }}" class="space-y-5">
                @csrf
                @if($editing) @method('PUT') @endif

                <section class="{{ $cardClass }}">
                    <div class="mb-5">
                        <h3 class="text-lg font-semibold text-gray-100">Datos básicos</h3>
                        <p class="text-sm text-gray-500">Identificación, roles y datos principales del tercero.</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Tipo de persona</label>
                            <select name="tipo_persona" class="{{ $inputClass }}">
                                <option value="natural" @selected(old('tipo_persona', $tercero?->tipo_persona ?? 'natural') === 'natural')>Natural</option>
                                <option value="juridica" @selected(old('tipo_persona', $tercero?->tipo_persona) === 'juridica')>Jurídica</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Tipo de identificación</label>
                            <select name="tipo_identificacion" x-model="tipoIdentificacion" class="{{ $inputClass }}">
                                <option value="">Sin especificar</option>
                                @foreach(\App\Models\Tercero::TIPOS_IDENTIFICACION as $tipo)
                                    <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-300">Roles *</label>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach(\App\Models\Tercero::ROLES as $rol)
                                    <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 p-4 text-gray-200">
                                        <input type="checkbox" name="roles[]" value="{{ $rol }}" x-model="roles" class="rounded border-white/20 bg-white/5 text-brand">
                                        {{ ucfirst($rol) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Número de identificación</label>
                            <input name="numero_identificacion" x-model="numeroIdentificacion" class="{{ $inputClass }}">
                        </div>
                        <div x-show="tipoIdentificacion === 'NIT'" x-cloak>
                            <label class="mb-1 block text-sm text-gray-400">Dígito de verificación</label>
                            <input name="digito_verificacion" x-model="dv" readonly class="{{ $inputClass }} cursor-not-allowed opacity-75">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Nombre o razón social *</label>
                            <input name="nombre" required value="{{ old('nombre', $tercero?->nombre) }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Nombre comercial</label>
                            <input name="nombre_comercial" value="{{ old('nombre_comercial', $tercero?->nombre_comercial) }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Email</label>
                            <input type="email" name="email" value="{{ old('email', $tercero?->email) }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Teléfono</label>
                            <input name="telefono" value="{{ old('telefono', $tercero?->telefono) }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Teléfono secundario</label>
                            <input name="telefono_secundario" value="{{ old('telefono_secundario', $tercero?->telefono_secundario) }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Dirección principal</label>
                            <input name="direccion" value="{{ old('direccion', $tercero?->direccion) }}" class="{{ $inputClass }}">
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" value="1" @checked(old('activo', $tercero?->activo ?? true)) class="rounded border-white/20 bg-white/5 text-brand">
                            Tercero activo
                        </label>
                    </div>
                </section>

                <section x-show="roles.includes('cliente')" x-cloak class="{{ $cardClass }}">
                    <h3 class="mb-5 text-lg font-semibold text-gray-100">Cliente</h3>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="cliente[credito_habilitado]" value="0">
                            <input type="checkbox" name="cliente[credito_habilitado]" value="1" @checked(old('cliente.credito_habilitado', $cliente?->credito_habilitado)) class="rounded bg-white/5 text-brand"> Crédito habilitado
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="cliente[bloqueado_ventas]" value="0">
                            <input type="checkbox" name="cliente[bloqueado_ventas]" value="1" @checked(old('cliente.bloqueado_ventas', $cliente?->bloqueado_ventas)) class="rounded bg-white/5 text-brand"> Bloqueado para ventas
                        </label>
                        <div><label class="mb-1 block text-sm text-gray-400">Cupo de crédito</label><input type="number" step="0.01" min="0" name="cliente[cupo_credito]" value="{{ old('cliente.cupo_credito', $cliente?->cupo_credito) }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Días de plazo</label><input type="number" min="0" name="cliente[dias_plazo]" value="{{ old('cliente.dias_plazo', $cliente?->dias_plazo) }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Motivo de bloqueo</label><input name="cliente[motivo_bloqueo]" value="{{ old('cliente.motivo_bloqueo', $cliente?->motivo_bloqueo) }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Observaciones</label><input name="cliente[observaciones]" value="{{ old('cliente.observaciones', $cliente?->observaciones) }}" class="{{ $inputClass }}"></div>
                        <div class="border-t border-white/5 pt-4 md:col-span-2"><h4 class="font-semibold text-gray-200">Datos personales</h4></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Género</label><select name="gym[gender]" class="{{ $inputClass }}"><option value="">—</option>@foreach(['M','F','NN'] as $value)<option value="{{ $value }}" @selected(old('gym.gender', $gym?->gender) === $value)>{{ $value }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Tipo de sangre</label><input name="gym[blood_type]" value="{{ old('gym.blood_type', $gym?->blood_type) }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-1 block text-sm text-gray-400">EPS</label><input name="gym[eps]" value="{{ old('gym.eps', $gym?->eps) }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Fecha de nacimiento</label><input type="date" name="gym[birth_date]" value="{{ old('gym.birth_date', $gym?->birth_date?->format('Y-m-d')) }}" class="{{ $inputClass }}"></div>
                    </div>
                </section>

                <section x-show="roles.includes('proveedor')" x-cloak class="{{ $cardClass }}">
                    <h3 class="mb-5 text-lg font-semibold text-gray-100">Proveedor</h3>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label class="mb-1 block text-sm text-gray-400">Plazo de pago (días)</label><input type="number" min="0" name="proveedor[plazo_pago_dias]" value="{{ old('proveedor.plazo_pago_dias', $proveedor?->plazo_pago_dias) }}" class="{{ $inputClass }}"></div>
                        <label class="flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="proveedor[preferido]" value="0">
                            <input type="checkbox" name="proveedor[preferido]" value="1" @checked(old('proveedor.preferido', $proveedor?->preferido)) class="rounded bg-white/5 text-brand"> Proveedor preferido
                        </label>
                        <div class="md:col-span-2"><label class="mb-1 block text-sm text-gray-400">Observaciones</label><textarea name="proveedor[observaciones]" rows="3" class="{{ $inputClass }}">{{ old('proveedor.observaciones', $proveedor?->observaciones) }}</textarea></div>

                        <div class="space-y-3 md:col-span-2">
                            <label class="block text-sm font-medium text-gray-300">Productos suministrados</label>
                            <input type="hidden" name="productos_seleccionados_presentes" value="1">
                            <div class="flex gap-2">
                                <input x-model="productQuery" @input="debounceProductSearch()" @keydown.enter.prevent="searchProducts()" placeholder="Nombre, SKU o código de barras" class="{{ $inputClass }}">
                                <button type="button" @click="searchProducts()" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/15">Buscar</button>
                            </div>
                            <p x-show="searchingProducts" class="text-sm text-gray-500">Buscando productos…</p>
                            <p x-show="productError" x-text="productError" class="text-sm text-red-400"></p>
                            <div x-show="productResults.length" class="max-h-64 space-y-2 overflow-y-auto rounded-xl border border-white/10 p-2">
                                <template x-for="product in productResults" :key="product.id">
                                    <button type="button" @click="selectProduct(product)" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-white/5">
                                        <span>
                                            <span class="block text-sm text-gray-100" x-text="product.name"></span>
                                            <span class="text-xs text-gray-500" x-text="[product.sku, product.barcode].filter(Boolean).join(' · ')"></span>
                                        </span>
                                        <span class="text-sm text-brand">Agregar</span>
                                    </button>
                                </template>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="product in selectedProducts" :key="product.id">
                                    <span class="inline-flex items-center gap-2 rounded-xl border border-brand/30 bg-brand/10 px-3 py-2 text-sm text-gray-100">
                                        <input type="hidden" name="productos[]" :value="product.id">
                                        <span x-text="product.name"></span>
                                        <span x-show="product.sku" class="text-xs text-gray-500" x-text="product.sku"></span>
                                        <button type="button" @click="removeProduct(product.id)" class="text-red-300 hover:text-red-200" aria-label="Quitar producto">×</button>
                                    </span>
                                </template>
                                <span x-show="!selectedProducts.length" class="text-sm text-gray-500">No hay productos seleccionados.</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section x-show="roles.includes('trabajador')" x-cloak class="{{ $cardClass }}">
                    <h3 class="mb-5 text-lg font-semibold text-gray-100">Trabajador</h3>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Rol de permisos *</label>
                            <select name="trabajador[role_id]" class="{{ $inputClass }}">
                                <option value="">Selecciona…</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected((string) old('trabajador.role_id', $trabajador?->role_id) === (string) $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="mb-1 block text-sm text-gray-400">Cargo</label><input name="trabajador[cargo]" value="{{ old('trabajador.cargo', $trabajador?->cargo) }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-1 block text-sm text-gray-400">Fecha de ingreso</label><input type="date" name="trabajador[fecha_ingreso]" value="{{ old('trabajador.fecha_ingreso', $trabajador?->fecha_ingreso?->format('Y-m-d')) }}" class="{{ $inputClass }}"></div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Estado laboral</label>
                            <select name="trabajador[estado_laboral]" class="{{ $inputClass }}">
                                @foreach(['activo' => 'Activo', 'suspendido' => 'Suspendido', 'retirado' => 'Retirado'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('trabajador.estado_laboral', $trabajador?->estado_laboral ?? 'activo') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="{{ $cardClass }}">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100">Contactos</h3>
                            <p class="text-sm text-gray-500">{{ $editing ? 'Agrega contactos nuevos; los existentes se conservan.' : 'Puedes registrarlos antes de crear el tercero.' }}</p>
                        </div>
                        <button type="button" @click="addContact()" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/15">+ Agregar contacto</button>
                    </div>

                    @if($editing)
                        <div class="mb-5 space-y-2">
                            @forelse($tercero->contactos as $contacto)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 p-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-100">{{ $contacto->nombre }}</div>
                                        <div class="text-xs text-gray-500">{{ ucfirst($contacto->tipo_contacto ?? 'contacto') }}{{ $contacto->parentesco ? ' · '.$contacto->parentesco : '' }} · {{ $contacto->email ?: ($contacto->telefono ?: $contacto->celular) }}</div>
                                    </div>
                                    <button type="submit" form="delete-contacto-{{ $contacto->id }}" class="text-xs text-red-400 hover:text-red-300">Eliminar</button>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Sin contactos existentes.</p>
                            @endforelse
                        </div>
                    @endif

                    <div class="space-y-3">
                        <template x-for="(contacto, index) in contactos" :key="index">
                            <div class="grid gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4 sm:grid-cols-2 lg:grid-cols-6">
                                <input :name="`{{ $editing ? 'contactos_nuevos' : 'contactos' }}[${index}][nombre]`" x-model="contacto.nombre" required placeholder="Nombre *" class="{{ $inputClass }} lg:col-span-2">
                                <input :name="`{{ $editing ? 'contactos_nuevos' : 'contactos' }}[${index}][telefono]`" x-model="contacto.telefono" placeholder="Teléfono" class="{{ $inputClass }}">
                                <input type="email" :name="`{{ $editing ? 'contactos_nuevos' : 'contactos' }}[${index}][email]`" x-model="contacto.email" placeholder="Email" class="{{ $inputClass }} lg:col-span-2">
                                <button type="button" @click="contactos.splice(index, 1)" class="rounded-xl border border-red-500/20 px-3 py-2 text-sm text-red-400">Eliminar</button>
                                <input :name="`{{ $editing ? 'contactos_nuevos' : 'contactos' }}[${index}][parentesco]`" x-model="contacto.parentesco" placeholder="Parentesco o cargo" class="{{ $inputClass }} lg:col-span-3">
                                <select :name="`{{ $editing ? 'contactos_nuevos' : 'contactos' }}[${index}][tipo_contacto]`" x-model="contacto.tipo_contacto" class="{{ $inputClass }} lg:col-span-3">
                                    @foreach(['principal' => 'Principal', 'facturacion' => 'Facturación', 'cartera' => 'Cartera', 'compras' => 'Compras', 'emergencia' => 'Emergencia', 'otro' => 'Otro'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </template>
                        <p x-show="!contactos.length" class="text-sm text-gray-500">No has agregado contactos nuevos.</p>
                    </div>
                </section>

                @if($editing)
                    <section class="{{ $cardClass }}">
                        <h3 class="mb-4 text-lg font-semibold text-gray-100">Direcciones registradas</h3>
                        <div class="space-y-2">
                            @forelse($tercero->direcciones as $direccion)
                                <div class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 p-3">
                                    <div><div class="text-sm font-medium text-gray-100">{{ ucfirst($direccion->tipo) }} — {{ $direccion->linea }}</div><div class="text-xs text-gray-500">{{ $direccion->ciudad }} {{ $direccion->departamento }}</div></div>
                                    <button type="submit" form="delete-direccion-{{ $direccion->id }}" class="text-xs text-red-400">Eliminar</button>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Sin direcciones adicionales.</p>
                            @endforelse
                        </div>
                    </section>
                @endif

                <div class="flex justify-end gap-3 rounded-xl border border-white/5 bg-dark-card p-5">
                    <a href="{{ route('stores.terceros', $store) }}" class="rounded-xl bg-white/5 px-4 py-2 text-gray-300">Cancelar</a>
                    @if(!$editing)
                        @storeCan($store, 'terceros.create')<button class="rounded-xl bg-brand px-5 py-2 font-semibold text-white">Crear tercero</button>@endstoreCan
                    @else
                        @storeCan($store, 'terceros.edit')<button class="rounded-xl bg-brand px-5 py-2 font-semibold text-white">Guardar cambios</button>@endstoreCan
                    @endif
                </div>
            </form>

            @if($editing)
                @storeCan($store, 'terceros.edit')
                    <form method="POST" action="{{ route('stores.terceros.direcciones.store', [$store, $tercero]) }}" class="{{ $cardClass }}">
                        @csrf
                        <h3 class="mb-4 font-semibold text-gray-100">Agregar dirección adicional</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select name="tipo" class="{{ $inputClass }}">@foreach(['fiscal','facturacion','entrega','correspondencia'] as $tipo)<option value="{{ $tipo }}">{{ ucfirst($tipo) }}</option>@endforeach</select>
                            <input name="linea" required placeholder="Dirección *" class="{{ $inputClass }}">
                            <input name="ciudad" placeholder="Ciudad" class="{{ $inputClass }}">
                            <input name="departamento" placeholder="Departamento" class="{{ $inputClass }}">
                            <input name="pais" value="Colombia" placeholder="País" class="{{ $inputClass }}">
                            <label class="flex items-center gap-2 text-sm text-gray-300"><input type="checkbox" name="es_principal" value="1" class="rounded bg-white/5 text-brand"> Principal</label>
                        </div>
                        <button class="mt-4 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white">Agregar dirección</button>
                    </form>
                @endstoreCan

                @foreach($tercero->contactos as $contacto)
                    <form id="delete-contacto-{{ $contacto->id }}" method="POST" action="{{ route('stores.terceros.contactos.destroy', [$store, $tercero, $contacto]) }}" onsubmit="return confirm('¿Eliminar este contacto?')">@csrf @method('DELETE')</form>
                @endforeach
                @foreach($tercero->direcciones as $direccion)
                    <form id="delete-direccion-{{ $direccion->id }}" method="POST" action="{{ route('stores.terceros.direcciones.destroy', [$store, $tercero, $direccion]) }}" onsubmit="return confirm('¿Eliminar esta dirección?')">@csrf @method('DELETE')</form>
                @endforeach

                @storeCan($store, 'terceros.destroy')
                    <form method="POST" action="{{ route('stores.terceros.destroy', [$store, $tercero]) }}" onsubmit="return confirm('¿Eliminar este tercero? Esta acción no se puede deshacer.')" class="flex justify-end">
                        @csrf @method('DELETE')
                        <button class="rounded-xl border border-red-500/30 px-4 py-2 text-sm text-red-400">Eliminar tercero</button>
                    </form>
                @endstoreCan
            @endif
        </div>
    </div>
</x-app-layout>
