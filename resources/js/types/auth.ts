export type User = {
    id: number;
    username: string;
    email: string;
    email_verified_at: string | null;
    display_name: string;
    bio: string | null;
    avatar_color: string;
    country_code: string | null;
    locale: 'ru' | 'uz' | 'en';
    role: 'user' | 'moderator' | 'author' | 'admin';
    streak_count: number;
    streak_freeze_available: number;
    last_solve_date: string | null;
    xp_total: number;
    avatar?: string;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
