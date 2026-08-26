export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
}

/** Props shared with every page via HandleInertiaRequests::share(). */
/** How a currency is written, from config/currencies.php. */
export interface CurrencyConfig {
    code: string;
    name: string;
    symbol: string;
    position: 'before' | 'after';
    decimals: number;
}

export interface SharedData {
    app: {
        name: string;
    };
    auth: {
        user: AuthUser | null;
        roles: string[];
        permissions: string[];
    };
    fileLimits: {
        mimes: string[];
        maxKilobytes: number;
        maxPerProduct: number;
    };
    /** Store-wide settings, from Settings → General. */
    settings: {
        companyName: string;
        logoUrl: string | null;
        currency: CurrencyConfig;
    };
    /** The currencies Settings offers, from config/currencies.php. */
    currencies: CurrencyConfig[];
    flash: {
        success: string | null;
        error: string | null;
    };
    [key: string]: unknown;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Shape of a Laravel length-aware paginator serialised to JSON. */
export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
