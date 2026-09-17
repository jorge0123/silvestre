import type { OnboardingStep } from '@/types/onboarding';

/**
 * El texto de la guía de cada paso: qué es, por qué se pide y consejos.
 * Vive aparte de los formularios para poder ajustarlo sin tocar la lógica.
 */
export type StepGuide = {
    short: string;
    title: string;
    lead: string;
    what: string;
    why: string;
    tips: string[];
};

export const onboardingGuide: Record<OnboardingStep, StepGuide> = {
    negocio: {
        short: 'Negocio',
        title: '¿Cómo se llama tu negocio?',
        lead: 'Así te van a encontrar tus clientes.',
        what: 'Tu nombre público y tu usuario. El usuario es tu dirección dentro de Silvestre, la que compartes por WhatsApp.',
        why: 'El usuario no se puede repetir: es lo que hace que tu link sea solo tuyo.',
        tips: [
            'Usa el nombre que ya conocen tus clientes.',
            'Si tienes NIT y razón social, elige Empresa: después podrás sumar equipo y facturar.',
        ],
    },
    oferta: {
        short: 'Oferta',
        title: '¿Qué ofreces?',
        lead: 'Esto decide cómo va a funcionar tu negocio en la app.',
        what: 'Los productos llevan catálogo, inventario y carrito. Los servicios llevan agenda y citas.',
        why: 'No es lo mismo vender galletas que cortar el pelo: cada uno tiene su propio flujo, y la app se adapta al tuyo.',
        tips: [
            'Elige "ambos" si, por ejemplo, vendes productos de belleza y también das tratamientos.',
            'La categoría ayuda a que te encuentren en Descubrir.',
        ],
    },
    ubicacion: {
        short: 'Ubicación',
        title: '¿Dónde estás y cuándo atiendes?',
        lead: 'Tu zona, tu WhatsApp y tu horario.',
        what: 'La zona donde está tu negocio, el número donde te escriben y los días que atiendes.',
        why: 'La gente busca por zona ("¿quién entrega en la zona 10?") y ve si estás abierto ahora.',
        tips: [
            'Tu dirección exacta solo se muestra si recogen pedidos o atiendes en tu local.',
            'Puedes cambiar tu horario cuando quieras.',
        ],
    },
    entrega: {
        short: 'Entrega',
        title: '¿Cómo llega lo que vendes?',
        lead: 'Formas de entrega y lugares donde atiendes.',
        what: 'Cómo entregas tus productos y dónde das tus servicios, con sus costos.',
        why: 'El cliente ve el costo de envío antes de pedir. Sin sorpresas, hay menos pedidos cancelados.',
        tips: [
            'Agrega solo las zonas a las que de verdad llegas.',
            'Si el envío es gratis, escribe 0.',
        ],
    },
    cobros: {
        short: 'Cobros',
        title: '¿Cómo te pagan tus clientes?',
        lead: 'El dinero va directo de tu cliente a ti.',
        what: 'Las formas de pago que aceptas y los datos que el cliente necesita para pagarte.',
        why: 'Silvestre no toca tu dinero ni cobra comisión por venta. Solo le mostramos al cliente cómo pagarte.',
        tips: [
            'Con transferencia, el cliente sube su comprobante y tú confirmas que llegó antes de preparar el pedido.',
            'Ofrecer más de una forma de pago ayuda a vender más.',
        ],
    },
    presentacion: {
        short: 'Presentación',
        title: 'Preséntate',
        lead: 'Lo primero que lee quien llega a tu perfil.',
        what: 'Una frase corta que diga qué haces y, si quieres, tu historia.',
        why: 'La gente le compra a quien entiende en tres segundos.',
        tips: [
            'Di qué vendes, para quién y qué te hace distinto.',
            'Evita promesas como "cura" o "garantizado": esas publicaciones pasan a revisión.',
        ],
    },
    reglas: {
        short: 'Reglas',
        title: 'Las reglas del barrio',
        lead: 'Lo último antes de abrir tu negocio.',
        what: 'Lo que se puede y no se puede vender, y cómo funcionan las reseñas.',
        why: 'Gracias a estas reglas, las estrellas y los negocios que ve la gente son de confianza.',
        tips: [
            'Las reseñas no se pueden borrar, pero sí puedes responderlas.',
            'Lo prohibido se bloquea en cuanto intentas publicarlo.',
        ],
    },
};
