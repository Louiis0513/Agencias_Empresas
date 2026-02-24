import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    darkMode: 'class',

    theme: {
        extend: {
            colors: {
                // TU NEÓN AZUL
                brand: {
                    DEFAULT: '#2272FF',
                    glow: 'rgba(34, 114, 255, 0.35)', // Para efectos de resplandor
                },
                // TU NEGRO BASE Y TONOS PARA TARJETAS
                dark: {
                    DEFAULT: '#1D1D1D', // El que me pasaste
                    card: '#242424',    // Un poquito más claro para que las cajas se vean sobre el fondo
                    border: '#333333',  // Para bordes muy finos
                }
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
