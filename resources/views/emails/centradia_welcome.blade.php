<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a CENTRADIA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #020617;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #e2e8f0;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #020617;
            padding: 24px 0;
        }
        .main {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            background-color: #020617;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            overflow: hidden;
        }
        .header {
            padding: 20px 28px 10px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-title {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #38bdf8;
        }
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(56, 189, 248, 0.35);
            font-size: 11px;
            color: #e2e8f0;
            margin-top: 10px;
        }
        .content {
            padding: 24px 28px 26px;
        }
        h1 {
            font-size: 22px;
            line-height: 1.3;
            margin: 0 0 10px;
            color: #f9fafb;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            margin: 8px 0;
            color: #e2e8f0;
        }
        .muted {
            color: #9ca3af;
            font-size: 13px;
        }
        .cta-btn {
            display: inline-block;
            margin-top: 18px;
            padding: 10px 22px;
            border-radius: 999px;
            background: #0f172a;
            color: #e0f2fe;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.75);
        }
        .cta-secondary {
            display: inline-block;
            margin-top: 10px;
            margin-left: 4px;
            padding: 9px 20px;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.65);
            background: rgba(56, 189, 248, 0.08);
            color: #e5e7eb;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
        }
        .highlight-box {
            margin-top: 20px;
            padding: 14px 14px 12px;
            border-radius: 18px;
            background: radial-gradient(circle at top left, rgba(56, 189, 248, 0.12), transparent 60%),
                        radial-gradient(circle at bottom right, rgba(52, 211, 153, 0.08), transparent 55%),
                        rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        .highlight-title {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #38bdf8;
            margin-bottom: 8px;
        }
        .highlight-text {
            font-size: 13px;
            color: #e5e7eb;
        }
        .list {
            margin: 14px 0 6px;
            padding-left: 18px;
        }
        .list li {
            font-size: 13px;
            color: #e5e7eb;
            margin-bottom: 4px;
        }
        .footer {
            padding: 12px 28px 16px;
            border-top: 1px solid rgba(148, 163, 184, 0.25);
            text-align: center;
        }
        .footer p {
            font-size: 11px;
            color: #9ca3af;
            margin: 4px 0;
        }
        .footer span {
            display: block;
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }
        @media (max-width: 600px) {
            .main {
                border-radius: 0;
            }
            .header, .content, .footer {
                padding-left: 18px;
                padding-right: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table class="main" role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="header">
                    <div class="logo">
                        <img src="{{ asset('centradialogo.png') }}" alt="CENTRADIA" width="40" style="border-radius: 999px; display:block;">
                        <div>
                            <div class="logo-title">CENTRADIA</div>
                            <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">
                                El núcleo de la operación de tu empresa
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="pill">Bienvenido a tu nueva central operativa</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="content">
                    <p class="muted" style="margin-bottom: 6px;">Hola @isset($name){{ $name }}@elseempresario@enderror,</p>
                    <h1>Toma el control total de tu empresa, hoy mismo.</h1>
                    <p>
                        Gracias por dar el paso y acercarte a <strong>CENTRADIA</strong>.
                        Desde ahora cuentas con una central operativa que conecta tus procesos internos con tus ventas
                        para que puedas tomar decisiones basadas en la realidad, no en suposiciones.
                    </p>
                    <p>
                        Nuestro objetivo es que tu empresa deje de depender de hojas de cálculo dispersas y chats eternos,
                        y empiece a operar con la estructura que merecen las compañías que quieren crecer en serio.
                    </p>

                    @isset($actionUrl)
                        <a href="{{ $actionUrl }}" class="cta-btn">
                            Entra a tu panel de CENTRADIA
                        </a>
                        <a href="{{ $actionUrl }}" class="cta-secondary">
                            Prueba el control total ahora
                        </a>
                    @endisset

                    <div class="highlight-box">
                        <div class="highlight-title">¿Qué ganarás con CENTRADIA?</div>
                        <div class="highlight-text">
                            <ul class="list">
                                <li><strong>Centralización total:</strong> inventarios, ventas, caja y equipo en un solo lugar.</li>
                                <li><strong>Información al día:</strong> indicadores claros para decidir cada mañana qué hacer.</li>
                                <li><strong>Impulso de ventas:</strong> una operación interna que respalda la experiencia de tus clientes.</li>
                            </ul>
                            <p style="margin-top: 8px;">
                                Diseñamos CENTRADIA para que las empresas pequeñas operen como las grandes.
                                No importa el tamaño de tu negocio hoy, te damos la estructura para el tamaño que quieres tener mañana.
                            </p>
                        </div>
                    </div>

                    <p class="muted" style="margin-top: 18px;">
                        Si quieres que te acompañemos paso a paso en la implementación, responde a este correo y
                        uno de nuestros asesores revisará contigo el mejor camino para tu empresa.
                    </p>
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <p>© {{ date('Y') }} CENTRADIA. Todos los derechos reservados.</p>
                    <span>CENTRADIA: El núcleo donde orbitan tus sueños y se ejecutan tus resultados.</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

