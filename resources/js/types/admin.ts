export type PlanLimitKey = 'post_quota_weekly' | 'story_quota_daily' | 'photo_limit' | 'product_limit' | 'service_limit';

export type PlanCapabilityKey = 'can_checkout' | 'can_reserve_stock' | 'can_use_team' | 'can_invoice' | 'can_promote';

export type AdminPlanPrice = {
    id: number | null;
    interval_months: number;
    price: number;
    is_active: boolean;
    label?: string;
};

export type AdminPlan = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    position: number;
    is_active: boolean;
    protected: boolean;
    activeSubscriptions: number | null;
    prices: AdminPlanPrice[];
} & Record<PlanLimitKey, number | null> &
    Record<PlanCapabilityKey, boolean>;

/** Cómo se llama cada tope y cada función para una persona, no para la base de datos. */
export const planLimits: { key: PlanLimitKey; label: string; unit: string }[] = [
    { key: 'post_quota_weekly', label: 'Publicaciones', unit: 'por semana' },
    { key: 'story_quota_daily', label: 'Historias', unit: 'por día' },
    { key: 'photo_limit', label: 'Fotos o videos', unit: 'por publicación' },
    { key: 'product_limit', label: 'Productos', unit: 'en catálogo' },
    { key: 'service_limit', label: 'Servicios', unit: 'en catálogo' },
];

export const planCapabilities: { key: PlanCapabilityKey; label: string; hint: string }[] = [
    { key: 'can_promote', label: 'Promocionado', hint: 'Sale como publicidad en el Inicio de quienes aún no lo siguen.' },
    { key: 'can_checkout', label: 'Vender en la app', hint: 'Carrito y pedidos desde su perfil.' },
    { key: 'can_reserve_stock', label: 'Control de inventario', hint: 'Aparta existencias al recibir un pedido.' },
    { key: 'can_use_team', label: 'Equipo', hint: 'Invitar a otras personas a administrar el negocio.' },
    { key: 'can_invoice', label: 'Factura electrónica', hint: 'Emitir FEL desde Silvestre.' },
];

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
