import { router } from '@inertiajs/vue3';

/**
 * Peticiones JSON para interacciones que no deben cambiar de página
 * (reaccionar, comentar, cargar más del Inicio). Así el scroll no se pierde.
 */
export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }

    /** El primer mensaje de validación, o el mensaje general. */
    firstError(): string {
        const first = Object.values(this.errors)[0]?.[0];

        return first ?? this.message;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function api<T>(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    url: string,
    body?: Record<string, unknown>,
): Promise<T> {
    let response: Response;

    // GET no lleva cuerpo: el navegador lo rechaza.
    const init: RequestInit = {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    };

    if (body && method !== 'GET') {
        init.body = JSON.stringify(body);
    }

    try {
        response = await fetch(url, init);
    } catch {
        throw new ApiError(
            'Sin conexión. Revisa tu internet e intenta otra vez.',
            0,
        );
    }

    const data = await response.json().catch(() => null);

    if (response.status === 401) {
        router.visit('/login');
        throw new ApiError('Inicia sesión para continuar.', 401);
    }

    if (response.status === 419) {
        throw new ApiError('Tu sesión expiró. Recarga la página.', 419);
    }

    if (response.status === 429) {
        throw new ApiError(
            'Vas muy rápido. Espera un momento e intenta de nuevo.',
            429,
        );
    }

    if (!response.ok) {
        throw new ApiError(
            data?.message ?? 'Algo salió mal. Intenta de nuevo.',
            response.status,
            data?.errors ?? {},
        );
    }

    return data as T;
}
