<script setup lang="ts">
import { useId } from 'vue';

/**
 * Logo de Silvestre: un gato "vaquita", blanco con manchas negras.
 *
 * Los colores son fijos (no heredan currentColor) porque el gato ES blanco y
 * negro en cualquier tema. Por eso, por defecto, se dibuja sobre su propia
 * insignia verde menta: sobre el verde oscuro de la marca las manchas negras
 * se perderían. Con `bare` se dibuja solo el gato.
 */
withDefaults(
    defineProps<{
        bare?: boolean;
    }>(),
    { bare: false },
);

// Cada instancia necesita su propio id de recorte: puede haber varios logos
// en la misma página.
const clipId = `silvestre-cat-${useId()}`;

const head =
    'M5.2 15.6C5.2 12.4 5.9 8.6 7.2 3.6L13.2 8.4C14.8 7.9 17.2 7.9 18.8 8.4L24.8 3.6C26.1 8.6 26.8 12.4 26.8 15.6C26.8 23.2 22 28 16 28C10 28 5.2 23.2 5.2 15.6Z';
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        aria-hidden="true"
        focusable="false"
    >
        <defs>
            <clipPath :id="clipId">
                <path :d="head" />
            </clipPath>
        </defs>

        <rect v-if="!bare" width="40" height="40" rx="11" fill="#CFE7D8" />

        <g
            :transform="
                bare
                    ? 'translate(20 20.4) scale(1.42) translate(-16 -15.8)'
                    : 'translate(20 21) scale(1.18) translate(-16 -16)'
            "
        >
            <!-- Cara blanca -->
            <path :d="head" fill="#FFFFFF" />

            <!-- Manchas de vaquita, recortadas a la silueta -->
            <g :clip-path="`url(#${clipId})`" fill="#17231E">
                <!-- Oreja y ojo izquierdos -->
                <path
                    d="M1.5 0.5L15 6.2C16 10.5 15 16.6 11.6 19.2C8.4 21.6 3.4 19.8 2 16Z"
                />
                <!-- Mancha en la frente -->
                <path
                    d="M17.6 10.6C18.9 10 20.6 10.8 20.4 12.2C20.2 13.3 18.6 13.6 17.9 12.9C17.3 12.3 17.1 11 17.6 10.6Z"
                />
                <!-- Mancha en el cachete -->
                <path
                    d="M22.5 21.8C24 21.2 25.6 22.4 25 23.8C24.5 25 22.6 25 22.1 24C21.7 23.2 21.8 22.1 22.5 21.8Z"
                />
            </g>

            <!-- Oreja derecha, rosada por dentro -->
            <path
                d="M23.8 6.2L19.9 9.2C21.6 9.6 23.1 10.4 24.3 11.4C24.4 9.6 24.2 7.9 23.8 6.2Z"
                fill="#F4A9C2"
            />

            <!-- Contorno -->
            <path
                :d="head"
                fill="none"
                stroke="#17231E"
                stroke-width="2"
                stroke-linejoin="round"
            />

            <!-- Ojos contentos: blanco sobre la mancha, negro sobre lo blanco -->
            <path
                d="M9.7 15.4C10.5 17 12.5 17 13.3 15.4"
                fill="none"
                stroke="#FFFFFF"
                stroke-width="1.7"
                stroke-linecap="round"
            />
            <path
                d="M18.7 15.4C19.5 17 21.5 17 22.3 15.4"
                fill="none"
                stroke="#17231E"
                stroke-width="1.7"
                stroke-linecap="round"
            />

            <!-- Nariz y boca -->
            <path
                d="M14.7 18.7H17.3L16 20.2Z"
                fill="#EF7FA6"
                stroke="#EF7FA6"
                stroke-width="0.9"
                stroke-linejoin="round"
            />
            <path
                d="M16 20.2C16 21.5 14.6 22 13.9 21.1M16 20.2C16 21.5 17.4 22 18.1 21.1"
                fill="none"
                stroke="#17231E"
                stroke-width="1.3"
                stroke-linecap="round"
            />
        </g>
    </svg>
</template>
