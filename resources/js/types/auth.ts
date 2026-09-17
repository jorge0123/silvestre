export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    account_type?: 'personal' | 'business';
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    /** Equipo de Silvestre: ve el panel de administración. */
    is_admin?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** Modo en el que se está usando la app: comprar o administrar un negocio. */
export type AppMode = {
    type: 'personal' | 'business';
    business: { id: number; name: string; slug: string } | null;
};

export type OwnedBusiness = {
    id: number;
    name: string;
    slug: string;
    finished: boolean;
};

export type Auth = {
    user: User;
    mode: AppMode | null;
    businesses: OwnedBusiness[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
