<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitrina Demo - Nombre del Negocio</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gray-100">
    <div
        class="min-h-screen flex flex-col"
        style="background-image: url('{{ asset('vitrina-demo/fondo-pagina.jpg') }}'); background-size: cover; background-position: center;"
    >
        <!-- Capa de color suave encima del fondo general -->
        <div class="flex-1 bg-white/80">
            <!-- Portada -->
            <div
                class="relative h-64 w-full"
                style="background-image: url('{{ asset('vitrina-demo/fondo-portada.jpg') }}'); background-size: cover; background-position: center;"
            >
                <div class="absolute inset-0 bg-black/30"></div>

                <!-- Logo centrado, se superpone con la siguiente sección -->
                <div class="absolute inset-x-0 -bottom-16 flex justify-center">
                    <div class="w-32 h-32 rounded-full border-4 border-white shadow-xl overflow-hidden bg-white">
                        <img
                            src="{{ asset('vitrina-demo/logo-negocio.png') }}"
                            alt="Logo del negocio"
                            class="w-full h-full object-cover"
                        >
                    </div>
                </div>
            </div>

            <!-- Contenido principal -->
            <main class="pt-24 pb-16 px-4">
                <!-- Información del negocio -->
                <section class="max-w-xl mx-auto bg-white/90 backdrop-blur rounded-xl shadow-lg p-6 text-center">
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Nombre del Negocio
                    </h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Aquí va una breve descripción del negocio. Por ejemplo: panadería artesana, café especial y repostería hecha en casa.
                    </p>
                    <div class="mt-4 text-sm text-gray-700 space-y-1">
                        <p><span class="font-medium">Ubicación:</span> Ciudad - Barrio o dirección de ejemplo</p>
                        <p><span class="font-medium">Horario:</span> Lun a Sáb · 8:00 am – 7:00 pm</p>
                        <p class="text-emerald-700 font-medium">
                            Haz tu pedido por WhatsApp o revisa nuestro catálogo.
                        </p>
                    </div>
                </section>

                <!-- Botones de acción -->
                <section class="mt-8 max-w-3xl mx-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Ver catálogo -->
                        <a
                            href="#catalogo"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-medium shadow hover:bg-emerald-700 transition"
                        >
                            Ver catálogo
                        </a>

                        <!-- Ver ubicación -->
                        <a
                            href="#ubicacion"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-white/90 text-emerald-700 text-sm font-medium shadow border border-emerald-100 hover:bg-emerald-50 transition"
                        >
                            Ver ubicación
                        </a>

                        <!-- Llamar -->
                        <a
                            href="tel:+573001234567"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-white/90 text-emerald-700 text-sm font-medium shadow border border-emerald-100 hover:bg-emerald-50 transition"
                        >
                            Llamar
                        </a>

                        <!-- WhatsApp -->
                        <a
                            href="https://wa.me/573001234567?text=Hola,%20quiero%20hacer%20un%20pedido"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-green-500 text-white text-sm font-medium shadow hover:bg-green-600 transition"
                        >
                            WhatsApp
                        </a>
                    </div>
                </section>

                <!-- Zona inferior / catálogo -->
                <section id="catalogo" class="mt-12 max-w-5xl mx-auto">
                    <div class="bg-white/80 rounded-xl shadow-md p-8 text-center">
                        <h2 class="text-xl font-semibold text-gray-900">
                            Aquí se mostrará el catálogo de productos
                        </h2>
                        <p class="mt-2 text-sm text-gray-600">
                            En esta sección, más adelante, listaremos todos los productos que marques como
                            <span class="font-medium">disponibles en la vitrina virtual</span>.
                        </p>
                    </div>
                </section>

                <!-- Placeholder ubicación -->
                <section id="ubicacion" class="mt-10 max-w-3xl mx-auto">
                    <div class="bg-white/80 rounded-xl shadow p-6 text-center text-sm text-gray-600">
                        Aquí podrás mostrar un mapa o enlace a Google Maps con la ubicación de tu negocio.
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>

