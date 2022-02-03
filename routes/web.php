<?php

// Auth
$router->get( 'auth', 'Services\amoAuthController@auth' );
$router->get( 'deauth', 'Services\amoAuthController@deauth' );

$router->get( 'test', function () {
    return 'qwertyuiop';
} );

// Webhooks
$router->post( '/changestage', 'LeadController@changeStage' );

// Crons
$router->get(
    '/changestage',

    [
        'middleware'  =>  'amoAuth',
        'uses'        =>  'LeadController@cronChangeStage',
    ]
);

$router->get(
    '/closeleads',

    [
        'middleware'  =>  'amoAuth',
        'uses'        =>  'LeadController@cronCloseLeads',
    ]
);
