<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default LDAP Connection
    |--------------------------------------------------------------------------
    | The name of the default LDAP connection to use. Maps to a key
    | in the 'connections' array below.
    */

    'default' => env('LDAP_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Connections (LdapRecord-Laravel)
    |--------------------------------------------------------------------------
    | Configure your LLDAP server here. LLDAP uses standard LDAP protocol on
    | port 389. Users live under ou=people,<base_dn>.
    |
    | Required .env keys:
    |   LDAP_HOST          – LLDAP server hostname/IP
    |   LDAP_PORT          – 389 (LDAP) or 636 (LDAPS)
    |   LDAP_BASE_DN       – e.g. dc=example,dc=com
    |   LDAP_USERNAME      – Admin bind DN, e.g. uid=admin,ou=people,dc=example,dc=com
    |   LDAP_PASSWORD      – Admin bind password
    |   LDAP_USERS_OU      – ou=people,dc=example,dc=com  (resolved in LdapService)
    */

    'connections' => [
        'default' => [
            'hosts'    => [env('LDAP_HOST', '127.0.0.1')],
            'username' => env('LDAP_USERNAME', ''),
            'password' => env('LDAP_PASSWORD', ''),
            'port'     => (int) env('LDAP_PORT', 389),
            'base_dn'  => env('LDAP_BASE_DN', ''),
            'timeout'  => (int) env('LDAP_TIMEOUT', 5),
            'use_ssl'  => (bool) env('LDAP_SSL', false),
            'use_tls'  => (bool) env('LDAP_TLS', false),
            'use_sasl' => false,
            'sasl_options' => [
                'mech'     => null,
                'realm'    => null,
                'authc_id' => null,
                'authz_id' => null,
                'props'    => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('LDAP_CACHE', false),
        'driver'  => env('LDAP_CACHE_DRIVER', 'file'),
    ],

];
