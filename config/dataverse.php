<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dataverse Azure AD Credentials
    |--------------------------------------------------------------------------
    |
    | Dataverse uses the same service principal as Power BI by default, so the
    | DATAVERSE_* variables only need to be set when a dedicated app
    | registration is used. Note that the service principal must also exist as
    | an Application User inside the Dataverse environment, with a security
    | role that can read cr21a_emailengagementlogs.
    |
    */

    'tenant_id' => env('DATAVERSE_TENANT_ID', env('POWERBI_TENANT_ID')),

    'client_id' => env('DATAVERSE_CLIENT_ID', env('POWERBI_CLIENT_ID')),

    'client_secret' => env('DATAVERSE_CLIENT_SECRET', env('POWERBI_CLIENT_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | Environment URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the Dataverse environment, without a trailing slash. The Web
    | API lives under /api/data/v9.2 of this host.
    |
    */

    'url' => rtrim((string) env('DATAVERSE_URL', 'https://org9e047986.api.crm.dynamics.com'), '/'),

    'api_version' => env('DATAVERSE_API_VERSION', 'v9.2'),

    /*
    |--------------------------------------------------------------------------
    | OAuth
    |--------------------------------------------------------------------------
    |
    | Unlike the Power BI API, Dataverse requires a v2.0 token whose scope is
    | the environment URL itself. Leave the scope empty to derive it from the
    | environment URL.
    |
    */

    'token_url' => 'https://login.microsoftonline.com/'
        .env('DATAVERSE_TENANT_ID', env('POWERBI_TENANT_ID'))
        .'/oauth2/v2.0/token',

    'scope' => env('DATAVERSE_SCOPE'),

    /*
    |--------------------------------------------------------------------------
    | Query Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds to cache Dataverse query results. Set to 0 to disable caching.
    | Default: 30 minutes.
    |
    */

    'cache_ttl' => env('DATAVERSE_CACHE_TTL', 30 * 60),

    /*
    |--------------------------------------------------------------------------
    | Page Size
    |--------------------------------------------------------------------------
    |
    | Rows per page requested from the Web API. Dataverse caps this at 5000.
    |
    */

    'page_size' => (int) env('DATAVERSE_PAGE_SIZE', 100),

];
