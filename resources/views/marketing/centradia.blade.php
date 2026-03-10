@extends('layouts.marketing')

@section('title', 'CENTRADIA | Toma el control total de tu empresa')

@section('content')
    {{-- Hero: Titular de impacto --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-32 -top-32 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl"></div>
            <div class="absolute -left-10 bottom-0 h-64 w-64 rounded-full bg-emerald-400/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pt-12 pb-20 lg:pt-20 lg:pb-28 grid gap-12 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] items-center">
            <div class="space-y-8">
                <p class="inline-flex items-center gap-2 rounded-full border border-sky-500/30 bg-slate-900/60 px-3 py-1 text-xs font-medium uppercase tracking-[0.2em] text-sky-300 shadow-sm shadow-sky-500/30">
                    Plataforma operativa para empresas que quieren crecer
                </p>

                <div class="space-y-4">
                    <h1 class="text-balance text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-slate-50">
                        Toma el control total de tu empresa, hoy mismo.
                    </h1>
                    <p class="text-balance text-sm sm:text-base text-slate-300 leading-relaxed max-w-xl">
                        <span class="font-semibold text-sky-300">CENTRADIA</span> es la central operativa que conecta tus procesos internos con tus ventas,
                        para llevar tu negocio al siguiente nivel.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <a
                        href="#cta"
                        class="inline-flex items-center justify-center rounded-full bg-sky-500 px-6 py-3 text-sm sm:text-base font-semibold text-slate-950 shadow-[0_18px_45px_rgba(56,189,248,0.45)] hover:bg-sky-400 transition"
                    >
                        ¡Empieza a crecer con CENTRADIA hoy!
                    </a>
                    <a
                        href="#cta"
                        class="inline-flex items-center justify-center rounded-full border border-slate-600/80 bg-slate-900/60 px-6 py-3 text-sm sm:text-base font-semibold text-slate-100 hover:border-sky-400 hover:text-sky-200 transition"
                    >
                        Prueba el control total ahora
                    </a>
                </div>

                <ul class="mt-3 flex flex-wrap gap-4 text-xs text-slate-400">
                    <li class="flex items-center gap-2">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        Sin instalaciones complicadas
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        Pensado para ti, para tu empresa y para tu crecimiento.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        Enfocado en levarte al siguiente nivel.
                    </li>
                </ul>
            </div>

            <div class="relative">
                <div class="pointer-events-none absolute -inset-8 rounded-3xl bg-gradient-to-br from-sky-500/25 via-purple-500/10 to-emerald-400/20 blur-2xl"></div>

                <div class="relative rounded-3xl bg-slate-900/80 ring-1 ring-white/10 shadow-2xl shadow-sky-900/60 backdrop-blur">
                    <div class="border-b border-white/5 px-5 py-3 flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-300">Panel de control CENTRADIA</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2 py-1 text-[10px] font-medium text-emerald-300">
                            • Datos al día
                        </span>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-2xl bg-slate-900/90 p-4 border border-white/5">
                                <p class="text-[11px] text-slate-400">Ingresos de hoy</p>
                                <p class="mt-1 text-lg font-semibold text-emerald-300">$ 4.850.000</p>
                                <p class="mt-1 text-[11px] text-emerald-400/80">+18% vs. ayer</p>
                            </div>
                            <div class="rounded-2xl bg-slate-900/90 p-4 border border-white/5">
                                <p class="text-[11px] text-slate-400">Pedidos activos</p>
                                <p class="mt-1 text-lg font-semibold text-sky-300">32</p>
                                <p class="mt-1 text-[11px] text-sky-400/80">Clientes atendidos en tiempo real</p>
                            </div>
                        </div>
                        <div class="rounded-2xl bg-slate-900/90 p-4 border border-white/5">
                            <p class="text-[11px] text-slate-400 mb-2">Flujo diario de caja</p>
                            <div class="h-20 w-full rounded-xl bg-gradient-to-r from-emerald-400/25 via-sky-400/25 to-purple-400/20 flex items-end gap-1 px-2 pb-1">
                                @for($i = 0; $i < 16; $i++)
                                    <div class="flex-1 rounded-full bg-emerald-300/80" style="height: {{ rand(20, 95) }}%"></div>
                                @endfor
                            </div>
                            <p class="mt-2 text-[11px] text-slate-400">Visualiza tus ingresos y gastos en tiempo real, cada día.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 3 Pilares de valor --}}
    <section id="beneficios" class="bg-slate-950 py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-2xl">
                <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">Los 3 pilares de valor de CENTRADIA</h2>
                <p class="mt-3 text-sm sm:text-base text-slate-300">
                    No es solo un sistema, es la forma en que tu empresa organiza su operación para crecer con orden.
                </p>
            </header>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <article class="group rounded-2xl border border-white/5 bg-gradient-to-b from-slate-900 to-slate-950 p-6 shadow-md shadow-black/40">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-500/15 text-sky-300">
                        <span class="text-lg">◎</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-slate-50">CENTRALIZACIÓN TOTAL</h3>
                    <p class="mt-3 text-sm text-slate-300">
                        Olvídate de tener la información regada en mil hojas de Excel. Desde inventarios hasta nómina, todo ocurre en un solo lugar.
                    </p>
                </article>

                <article class="group rounded-2xl border border-white/5 bg-gradient-to-b from-slate-900 to-slate-950 p-6 shadow-md shadow-black/40">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
                        <span class="text-lg">◆</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-slate-50">INFORMACIÓN AL DÍA</h3>
                    <p class="mt-3 text-sm text-slate-300">
                        Toma decisiones basadas en la realidad, no en suposiciones. Visualiza tus ingresos y gastos en tiempo real, cada día.
                    </p>
                </article>

                <article class="group rounded-2xl border border-white/5 bg-gradient-to-b from-slate-900 to-slate-950 p-6 shadow-md shadow-black/40">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-purple-500/15 text-purple-300">
                        <span class="text-lg">↗</span>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-slate-50">IMPULSO DE VENTAS</h3>
                    <p class="mt-3 text-sm text-slate-300">
                        No solo gestionamos lo que tienes, te ayudamos a vender más. Conecta tu operación interna con la atención a tus clientes.
                    </p>
                </article>
            </div>
        </div>
    </section>

    {{-- ¿Qué incluye CENTRADIA? --}}
    <section id="funcionalidades" class="bg-slate-950/95 border-y border-white/5 py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 grid gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] items-start">
            <div>
                <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">¿Qué incluye CENTRADIA?</h2>
                <p class="mt-3 text-sm sm:text-base text-slate-300 max-w-xl">
                    Todo lo que necesitas para que la operación interna y las ventas de tu negocio hablen el mismo idioma.
                </p>

                <ul class="mt-8 grid gap-3 sm:grid-cols-2">
                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-50">Módulo Contable Inteligente</p>
                            <p class="text-xs sm:text-[13px] text-slate-300 mt-1">
                                Automatiza tus registros y cumple con tus obligaciones sin estrés.
                            </p>
                        </div>
                    </li>

                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-50">Gestión de Procesos Internos</p>
                            <p class="text-xs sm:text-[13px] text-slate-300 mt-1">
                                Controla inventarios, flujos de trabajo y tareas de tu equipo desde un solo panel.
                            </p>
                        </div>
                    </li>

                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-50">Panel de Ventas y Clientes</p>
                            <p class="text-xs sm:text-[13px] text-slate-300 mt-1">
                                Registra cada transacción y conoce mejor a quien te compra.
                            </p>
                        </div>
                    </li>

                    <li class="flex items-start gap-3">
                        <span class="mt-1 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
                            ✓
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-50">Reportes de Crecimiento</p>
                            <p class="text-xs sm:text-[13px] text-slate-300 mt-1">
                                Gráficas claras que te muestran hacia dónde va tu dinero.
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <aside class="rounded-3xl bg-slate-900/80 border border-white/10 p-6 shadow-xl shadow-black/40">
                <p class="text-xs font-semibold tracking-[0.22em] uppercase text-sky-300">
                    Pequeño gran empresario
                </p>
                <p class="mt-4 text-sm sm:text-base text-slate-100 leading-relaxed">
                    Diseñamos <span class="font-semibold text-sky-300">CENTRADIA</span> para que las empresas pequeñas operen como las grandes.
                    No importa el tamaño de tu negocio hoy, te damos la estructura para el tamaño que quieres tener mañana.
                </p>
                <p class="mt-5 text-xs text-slate-400">
                    Pasa de sobrevivir al día a día a construir una empresa con base sólida y visión de crecimiento.
                </p>
            </aside>
        </div>
    </section>

    {{-- Mensaje emocional ampliado --}}
    <section id="empresario" class="bg-slate-950 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-xs font-semibold tracking-[0.26em] uppercase text-sky-300">
                Pensado para quienes hacen empresa todos los días
            </p>
            <h2 class="mt-4 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">
                Tu empresa merece una central operativa a la altura de tus sueños.
            </h2>
            <p class="mt-4 text-sm sm:text-base text-slate-300 leading-relaxed">
                Mientras tú te enfocas en vender, liderar y crear oportunidades, <span class="font-semibold text-sky-300">CENTRADIA</span>
                se encarga de que los números, procesos y equipos trabajen alineados detrás de escena.
            </p>
        </div>
    </section>

    {{-- Preguntas frecuentes --}}
    <section id="faq" class="bg-slate-950/98 border-y border-white/5 py-16 sm:py-20">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold tracking-[0.26em] uppercase text-sky-300">
                    Preguntas frecuentes
                </p>
                <h2 class="mt-3 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">
                    Resolvemos tus dudas antes de que tomes la decisión.
                </h2>
                <p class="mt-3 text-sm sm:text-base text-slate-300">
                    Si tienes una situación particular en tu empresa, conversemos y revisamos juntos cómo CENTRADIA puede ayudarte.
                </p>
            </div>

            <div class="mt-10 space-y-4">
                <details class="group rounded-2xl border border-white/5 bg-slate-900/70 px-5 py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-slate-50">
                                ¿Para qué tipo de empresas está pensada CENTRADIA?
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                Desde pequeños comercios hasta empresas en crecimiento con varios puntos de venta.
                            </p>
                        </div>
                        <span class="ml-2 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-slate-500 text-xs text-slate-300 group-open:rotate-180 transition">
                            ▾
                        </span>
                    </summary>
                    <div class="mt-3 text-sm text-slate-300">
                        Diseñamos CENTRADIA para el <span class="font-semibold">“pequeño gran empresario”</span>: negocios que ya facturan,
                        pero todavía sienten que todo se maneja a punta de chats, hojas de cálculo y memoria. Si tienes inventario,
                        clientes recurrentes o un equipo que atender, CENTRADIA te ayuda a organizar la operación para dar el siguiente salto.
                    </div>
                </details>

                <details class="group rounded-2xl border border-white/5 bg-slate-900/70 px-5 py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-slate-50">
                                ¿CENTRADIA reemplaza mi software contable actual?
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                Podemos convivir con tu contabilidad actual o ayudarte a migrar progresivamente.
                            </p>
                        </div>
                        <span class="ml-2 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-slate-500 text-xs text-slate-300 group-open:rotate-180 transition">
                            ▾
                        </span>
                    </summary>
                    <div class="mt-3 text-sm text-slate-300">
                        CENTRADIA integra un <span class="font-semibold">Módulo Contable Inteligente</span> que automatiza registros y prepara tu información
                        para el cumplimiento. Si ya trabajas con un contador o un software contable externo, podemos utilizar CENTRADIA
                        como la fuente operativa de la verdad y sincronizar o exportar la información que tu contador necesita.
                    </div>
                </details>

                <details class="group rounded-2xl border border-white/5 bg-slate-900/70 px-5 py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-slate-50">
                                ¿Qué tan segura está la información de mi empresa?
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                Cuidamos tus datos como si fueran parte de nuestro propio negocio.
                            </p>
                        </div>
                        <span class="ml-2 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-slate-500 text-xs text-slate-300 group-open:rotate-180 transition">
                            ▾
                        </span>
                    </summary>
                    <div class="mt-3 text-sm text-slate-300">
                        Tu información se almacena en infraestructura en la nube con <span class="font-semibold">cifrado, copias de seguridad</span>
                        y controles de acceso por usuario y rol. Solo las personas autorizadas dentro de tu empresa pueden ver cada módulo,
                        y puedes revocar accesos en segundos desde el panel.
                    </div>
                </details>

                <details class="group rounded-2xl border border-white/5 bg-slate-900/70 px-5 py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-slate-50">
                                ¿Cuánto tiempo me toma empezar a usar CENTRADIA?
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                Menos de lo que imaginas: comenzamos con lo esencial y luego vamos profundizando.
                            </p>
                        </div>
                        <span class="ml-2 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-slate-500 text-xs text-slate-300 group-open:rotate-180 transition">
                            ▾
                        </span>
                    </summary>
                    <div class="mt-3 text-sm text-slate-300">
                        Iniciamos con una <span class="font-semibold">implementación guiada</span> donde configuramos tus datos básicos (productos,
                        clientes, usuarios y permisos). En pocos días puedes tener tu operación centralizada y luego ir activando
                        módulos adicionales como facturación, reportes o asistencias según tu ritmo.
                    </div>
                </details>

                <details class="group rounded-2xl border border-white/5 bg-slate-900/70 px-5 py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm sm:text-base font-semibold text-slate-50">
                                ¿Qué tipo de acompañamiento recibo después de contratar?
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                No te dejamos solo: soporte cercano y enfoque en resultados.
                            </p>
                        </div>
                        <span class="ml-2 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-slate-500 text-xs text-slate-300 group-open:rotate-180 transition">
                            ▾
                        </span>
                    </summary>
                    <div class="mt-3 text-sm text-slate-300">
                        Tendrás acceso a <span class="font-semibold">soporte especializado</span>, sesiones de acompañamiento para tu equipo
                        y recursos de formación continua. Nuestro objetivo no es solo que uses un software,
                        sino que veas impacto real en el orden y crecimiento de tu empresa.
                    </div>
                </details>
            </div>
        </div>
    </section>

    {{-- CTA final --}}
    <section id="cta" class="bg-gradient-to-br from-sky-600 via-sky-500 to-emerald-400 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center text-slate-950">
            <p class="text-xs font-semibold tracking-[0.26em] uppercase text-slate-900/80">
                Da el siguiente paso
            </p>
            <h2 class="mt-4 text-2xl sm:text-3xl font-semibold tracking-tight">
                CENTRADIA: El núcleo donde orbitan tus sueños y se ejecutan tus resultados.
            </h2>
            <p class="mt-4 text-sm sm:text-base text-slate-900/80 leading-relaxed">
                Agenda una demostración o comienza una prueba guiada y descubre cómo se siente tener el control
                total de tu operación en una sola plataforma.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a
                    href="#"
                    class="inline-flex items-center justify-center rounded-full bg-slate-950 px-7 py-3 text-sm sm:text-base font-semibold text-sky-200 shadow-[0_18px_45px_rgba(15,23,42,0.55)] hover:bg-slate-900 transition"
                >
                    ¡Empieza a crecer con CENTRADIA hoy!
                </a>
                <a
                    href="#"
                    class="inline-flex items-center justify-center rounded-full border border-slate-900/60 bg-sky-500/10 px-7 py-3 text-sm sm:text-base font-semibold text-slate-950 hover:border-slate-950/80 transition"
                >
                    Prueba el control total ahora
                </a>
            </div>

            <p class="mt-5 text-[11px] text-slate-900/70">
                Sin compromiso inicial. Te acompañamos paso a paso en la implementación.
            </p>
        </div>
    </section>
@endsection

