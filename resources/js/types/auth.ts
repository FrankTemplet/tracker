export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

export type EmailAnalyticsData = {
    bounces: number;
    bounce_rate: number;
    opens: number;
    open_rate: number;
    clicks: number;
    click_rate: number;
    total_delivered: number;
    unique_opens: number;
    unique_clicks: number;
    opt_out_rate: number;
};
