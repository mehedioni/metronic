export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

/** Props shared with every page via HandleInertiaRequests::share(). */
export interface SharedData {
    auth: {
        user: AuthUser | null;
    };
    [key: string]: unknown;
}
