<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Añadir trabajador - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.workers', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Trabajadores
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('stores.workers.store', $store) }}" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="{{ __('Nombre') }}" />
                        <x-text-input id="name"
                                      name="name"
                                      type="text"
                                      class="block mt-1 w-full"
                                      :value="old('name')"
                                      required
                                      autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="email" value="{{ __('Email') }}" />
                        <x-text-input id="email"
                                      name="email"
                                      type="email"
                                      class="block mt-1 w-full"
                                      :value="old('email')"
                                      placeholder="correo@ejemplo.com"
                                      required />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Puedes añadir trabajadores aunque aún no tengan cuenta. Si el correo ya está registrado, quedará vinculado automáticamente. Cuando se registre con este email, se vinculará solo.</p>
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="phone" value="{{ __('Teléfono') }}" />
                        <x-text-input id="phone"
                                      name="phone"
                                      type="text"
                                      class="block mt-1 w-full"
                                      :value="old('phone')" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="document_number" value="{{ __('Documento (opcional)') }}" />
                        <x-text-input id="document_number"
                                      name="document_number"
                                      type="text"
                                      class="block mt-1 w-full"
                                      :value="old('document_number')" />
                        <x-input-error :messages="$errors->get('document_number')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="role_id" value="{{ __('Rol') }}" />
                        <select id="role_id"
                                name="role_id"
                                required
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($rolesList as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <a href="{{ route('stores.workers', $store) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md font-medium text-sm hover:bg-gray-300 dark:hover:bg-gray-600">
                            Cancelar
                        </a>
                        <x-primary-button type="submit">
                            Añadir trabajador
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
