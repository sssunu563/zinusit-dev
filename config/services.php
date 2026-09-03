<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'snipeit' => [
        'url'                  => env('SNIPEIT_URL'),
        'token'                => env('SNIPEIT_TOKEN'),
        'connect_timeout'      => env('SNIPEIT_CONNECT_TIMEOUT', 5),
        'timeout'              => env('SNIPEIT_TIMEOUT', 20),
        'fallback_supplier_id' => env('SNIPEIT_FALLBACK_SUPPLIER_ID', 1),
    ],

    'grafana' => [
        'api_key' => env('GRAFANA_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLDAP Connection Settings
    |--------------------------------------------------------------------------
    | Used by App\Services\LdapService for:
    |   – Credential verification on login (LDAP user bind)
    |   – User search / list
    |   – User create / update / password change (admin bind)
    |
    | Required .env keys:
    |   LDAP_HOST         – LLDAP hostname or IP
    |   LDAP_PORT         – typically 389 or 636
    |   LDAP_BASE_DN      – e.g. dc=example,dc=com
    |   LDAP_BIND_DN      – admin bind DN, e.g. uid=admin,ou=people,dc=example,dc=com
    |   LDAP_BIND_PW      – admin bind password
    |   LDAP_USERS_OU     – users OU, e.g. ou=people,dc=example,dc=com
    |   LDAP_TIMEOUT      – socket timeout in seconds (default 5)
    |   LDAP_SSL          – true if using ldaps:// (port 636)
    */

    'ldap' => [
        'host'        => env('LDAP_HOST', '127.0.0.1'),
        'port'        => (int) env('LDAP_PORT', 389),
        'base_dn'     => env('LDAP_BASE_DN', ''),
        'bind_dn'     => env('LDAP_BIND_DN', env('LDAP_USERNAME', '')),
        'bind_pw'     => env('LDAP_BIND_PW', env('LDAP_PASSWORD', '')),
        'users_ou'    => env('LDAP_USERS_OU', env('LDAP_BASE_DN', '')),
        'groups_ou'   => env('LDAP_GROUPS_OU', env('LDAP_BASE_DN', '')),
        'login_group' => env('LDAP_LOGIN_GROUP', ''),
        'timeout'     => (int) env('LDAP_TIMEOUT', 5),
        'ssl'         => (bool) env('LDAP_SSL', false),
    ],

    'teams' => [
        'webhook_url' => env('MS_TEAMS_WEBHOOK_URL'),
    ],

    'prtg' => [
        'url'             => env('PRTG_URL', ''),
        'api_token'       => env('PRTG_API_TOKEN', ''),
        'avg_minutes'     => (int) env('PRTG_AVG_MINUTES', 300),
        'timeout'         => (int) env('PRTG_TIMEOUT', 15),
        'connect_timeout' => (int) env('PRTG_CONNECT_TIMEOUT', 5),
    ],

    'zabbix' => [
        'timeout'         => (int) env('ZABBIX_TIMEOUT', 30),
        'connect_timeout' => (int) env('ZABBIX_CONNECT_TIMEOUT', 5),
        'instances' => [
            'f1' => ['url' => env('ZABBIX_F1_URL', ''), 'token' => env('ZABBIX_F1_TOKEN', '')],
            'f2' => ['url' => env('ZABBIX_F2_URL', ''), 'token' => env('ZABBIX_F2_TOKEN', '')],
            'f3' => ['url' => env('ZABBIX_F3_URL', ''), 'token' => env('ZABBIX_F3_TOKEN', '')],
        ],
    ],

    // CCTV Monitor — configurable group keywords from ENV
    // CCTV_ZABBIX_GROUPS  = comma-separated Zabbix group keywords → cctv type
    // FINGER_ZABBIX_GROUPS = comma-separated Zabbix group keywords → finger type
    // NVR_PRTG_GROUPS      = comma-separated PRTG group keywords → nvr type
    'cctv_monitor' => [
        'zabbix_cctv_groups'   => array_filter(array_map('trim', explode(',', env('CCTV_ZABBIX_GROUPS',   'CCTV')))),
        'zabbix_finger_groups' => array_filter(array_map('trim', explode(',', env('FINGER_ZABBIX_GROUPS', 'FINGERPRINT')))),
        'prtg_nvr_groups'      => array_filter(array_map('trim', explode(',', env('NVR_PRTG_GROUPS',      'NVR')))),
    ],
];
