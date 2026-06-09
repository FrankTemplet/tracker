<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Power BI Azure AD Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used for authentication with Azure AD to obtain
    | access tokens for the Power BI REST API using the service principal
    | (client credentials flow).
    |
    */

    'tenant_id' => env('POWERBI_TENANT_ID'),

    'client_id' => env('POWERBI_CLIENT_ID'),

    'client_secret' => env('POWERBI_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Power BI Workspace & Dataset
    |--------------------------------------------------------------------------
    |
    | The workspace (group) ID and dataset ID where your email campaign
    | data resides.
    |
    */

    'workspace_id' => env('POWERBI_WORKSPACE_ID'),

    'dataset_id' => env('POWERBI_DATASET_ID'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Scope
    |--------------------------------------------------------------------------
    |
    | The OAuth scope required for Power BI API access.
    |
    */

    'scope' => env('POWERBI_SCOPE', 'https://analysis.windows.net/powerbi/api/.default'),

    /*
    |--------------------------------------------------------------------------
    | Query Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds to cache Power BI query results in the database cache.
    | Set to 0 to disable caching. Default: 30 minutes.
    | Override per environment: POWERBI_CACHE_TTL=3600
    |
    */

    'cache_ttl' => env('POWERBI_CACHE_TTL', 30 * 60),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Base URLs for Azure AD and Power BI REST API.
    |
    */

    'token_url' => 'https://login.microsoftonline.com/'.env('POWERBI_TENANT_ID').'/oauth2/token',

    'api_base_url' => 'https://api.powerbi.com/v1.0/myorg',

];
