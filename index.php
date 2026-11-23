<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestpro - Soluciones VeriFactu 2026</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap');
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            overflow-x: hidden;
        }

        body { font-family: 'Inter', sans-serif; }

        input,
        select,
        textarea {
            font-size: 16px;
        }

        a,
        button {
            touch-action: manipulation;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">

    <nav class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl bg-slate-50 shadow-sm ring-1 ring-slate-200">
                    <img src="assets/branding/logo-gestpro.jpeg" alt="Logo de GestPro" class="h-8 w-8 object-contain">
                </span>
                <span class="text-2xl font-bold tracking-tight text-slate-900">
                    Gest<span class="text-blue-600">Pro</span>
                </span>
            </a>

            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center sm:justify-end sm:gap-4">
                <a href="iniciarsesion.php" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-blue-200 hover:text-blue-600">
                    Iniciar sesion
                </a>
                <a href="registro.php" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                    Registrarse
                </a>
            </div>
        </div>
    </nav>

    <header class="border-b border-slate-100 bg-white">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 md:py-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)] lg:items-center lg:px-8">
            <div class="text-center lg:text-left">
                <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.22em] text-blue-700">
                    Preparado para 2026
                </span>
                <h1 class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-5xl md:text-6xl">
                    Tu negocio, siempre al dia con
                    <span class="mt-2 block text-blue-600">VeriFactu</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-500 lg:mx-0 lg:text-xl">
                    Gestpro te ayuda a emitir facturas verificables, organizar clientes y mantener un flujo de trabajo claro desde cualquier pantalla.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <a href="iniciarsesion.php" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                        Entrar al panel
                    </a>
                    <a href="registro.php" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Crear cuenta
                    </a>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-slate-900 p-6 text-white shadow-2xl shadow-slate-200 sm:p-8">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Estado del sistema</p>
                        <h2 class="mt-2 text-2xl font-bold">Panel activo</h2>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-300">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                        Online
                    </span>
                </div>

                <div class="mt-6 space-y-3">
                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <span class="text-sm text-slate-300">Registros encadenados</span>
                        <span class="text-xs font-extrabold uppercase tracking-wide text-white">Activo</span>
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <span class="text-sm text-slate-300">Envio de datos</span>
                        <span class="text-xs font-extrabold uppercase tracking-wide text-white">Listo</span>
                    </div>
                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <span class="text-sm text-slate-300">Facturas PDF</span>
                        <span class="text-xs font-extrabold uppercase tracking-wide text-white">Disponible</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-14 sm:px-6 sm:py-16 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <section class="grid gap-6 md:grid-cols-3">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-sm font-extrabold text-blue-700">01</div>
                    <h3 class="mt-5 text-xl font-bold text-slate-900">Normativa clara</h3>
                    <p class="mt-3 leading-relaxed text-slate-600">
                        Emite facturas siguiendo el nuevo marco VeriFactu con una interfaz limpia y facil de revisar desde movil.
                    </p>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-sm font-extrabold text-blue-700">02</div>
                    <h3 class="mt-5 text-xl font-bold text-slate-900">Trazabilidad total</h3>
                    <p class="mt-3 leading-relaxed text-slate-600">
                        Cada factura mantiene su detalle, estado y descarga accesible sin depender de pantallas grandes.
                    </p>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-sm font-extrabold text-blue-700">03</div>
                    <h3 class="mt-5 text-xl font-bold text-slate-900">Automatizacion util</h3>
                    <p class="mt-3 leading-relaxed text-slate-600">
                        Consulta clientes, crea facturas y revisa resultados con botones y tablas adaptados a dedo.
                    </p>
                </article>
            </section>

            <section class="relative mt-12 overflow-hidden rounded-[2rem] bg-blue-600 p-6 text-white shadow-2xl shadow-blue-200 sm:p-10 lg:p-14">
                <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-12 -left-10 h-36 w-36 rounded-full bg-blue-400/30"></div>

                <div class="relative grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.85fr)] lg:items-center">
                    <div>
                        <h3 class="text-2xl font-bold sm:text-3xl">Cumplimiento 2026 sin complicarte</h3>
                        <ul class="mt-6 space-y-4 text-blue-100">
                            <li class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-blue-200"></span>
                                <span>Archivos y registros listos para una operativa trazable.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-blue-200"></span>
                                <span>Descarga de PDF y detalle de factura accesibles desde cualquier dispositivo.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-blue-200"></span>
                                <span>Panel administrativo y de cliente con navegacion adaptada a movil.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-3xl border border-white/20 bg-white/10 p-6 backdrop-blur">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-100">Vista rapida</p>
                        <div class="mt-5 space-y-3">
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs uppercase tracking-wide text-blue-100">Facturas emitidas</p>
                                <p class="mt-2 text-3xl font-black text-white">24</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wide text-blue-100">Estado AEAT</p>
                                    <p class="mt-2 text-sm font-bold text-white">Conectado</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wide text-blue-100">Acceso movil</p>
                                    <p class="mt-2 text-sm font-bold text-white">Optimizado</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="bg-slate-900 px-4 py-10 text-slate-400 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div class="text-center md:text-left">
                <div class="flex items-center justify-center gap-3 md:justify-start">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm">
                        <img src="assets/branding/logo-gestpro.jpeg" alt="Logo de GestPro" class="h-8 w-8 object-contain">
                    </span>
                    <span class="text-xl font-bold text-white">GestPro</span>
                </div>
                <p class="mt-2 text-sm">Software de facturacion homologado.</p>
            </div>

            <p class="text-center text-xs md:text-right">&copy; 2026 Gestpro Technologies S.L.</p>
        </div>
    </footer>

    <script src="assets/js/mobile-tap.js?v=2" defer></script>

</body>
</html>
