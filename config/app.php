<?php

return [
    'amoCRM' => [
        'client_secret' => env( 'AMOCRM_CLIENT_SECRET', null ),
        'redirect_uri'  => env( 'AMOCRM_REDIRECT_URI', null ),
        'subdomain'     => env( 'AMOCRM_SUBDOMAIN', null ),
    ]
];
