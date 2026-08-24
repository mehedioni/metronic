export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
}

/** Props shared with every page via HandleInertiaRequests::share(). */
export interface SharedData {
    app: {
        name: string;
    };
    auth: {
        user: AuthUser | null;
        roles: string[];
        permissions: string[];
    };
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
