export type OnboardingStep =
    | 'negocio'
    | 'oferta'
    | 'ubicacion'
    | 'entrega'
    | 'cobros'
    | 'presentacion'
    | 'reglas';

export type Option = {
    value: string;
    label: string;
    description: string;
};

export type PaymentOption = Option & {
    details: string[];
    requiresProof: boolean;
};

export type CategoryOption = {
    id: number;
    name: string;
    offering: 'productos' | 'servicios' | 'ambos';
    requires_verification: boolean;
    required_document: string | null;
};

export type OnboardingOptions = {
    kinds: Option[];
    offerings: Option[];
    fulfillmentMethods: Option[];
    serviceModes: Option[];
    paymentMethods: PaymentOption[];
    categories: CategoryOption[];
    zones: { id: number; name: string }[];
    weekdays: { value: number; label: string }[];
    banks: string[];
    accountTypes: string[];
    wallets: string[];
    currency: string;
    phonePrefix: string;
    city: string;
    trialDays: number;
    prohibited: string[];
};

export type HourRow = { weekday: number; opens_at: string; closes_at: string };

export type ZoneFee = {
    zone_id: number | null;
    fee: number | string;
    min_order: number | string;
};

export type PaymentRow = {
    method: string;
    details: Record<string, string>;
};

export type OnboardingBusiness = {
    name: string;
    slug: string;
    kind: string;
    legal_name: string | null;
    tax_id: string | null;
    offering: 'productos' | 'servicios' | 'ambos';
    category_id: number | null;
    category: {
        name: string;
        requires_verification: boolean;
        required_document: string | null;
    } | null;
    zone_id: number | null;
    address: string | null;
    address_notes: string | null;
    whatsapp: string | null;
    hours: HourRow[];
    fulfillment: string[];
    shipping_rate: number | null;
    free_shipping_from: number | null;
    service_modes: string[];
    travel_fee: number | null;
    delivery_zones: ZoneFee[];
    payment_methods: PaymentRow[];
    intro: string | null;
    about: string | null;
};

/** Lo que recibe cada paso del onboarding. */
export type StepProps = {
    business: OnboardingBusiness | null;
    options: OnboardingOptions;
    backHref: string | null;
};
