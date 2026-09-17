import { toast } from 'vue-sonner';

/**
 * Compartir un enlace: en el celular abre el menú nativo (WhatsApp, etc.);
 * en computadora lo copia y avisa.
 */
export async function shareLink(url: string, title: string): Promise<void> {
    const absolute = new URL(url, window.location.origin).toString();

    if (navigator.share) {
        try {
            await navigator.share({ title, url: absolute });

            return;
        } catch (error) {
            if ((error as DOMException)?.name === 'AbortError') {
                return; // la persona cerró el menú
            }
        }
    }

    try {
        await navigator.clipboard.writeText(absolute);
        toast.success('Enlace copiado. Pégalo en WhatsApp o donde quieras.');
    } catch {
        toast.error('No se pudo copiar el enlace.');
    }
}
