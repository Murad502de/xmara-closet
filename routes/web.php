<?php

// Auth
$router->get( '/api/auth', 'Services\amoAuthController@auth' );
$router->get( '/api/deauth', 'Services\amoAuthController@deauth' );

// Webhooks
$router->post( '/api/changestage', 'LeadController@changeStage' );

// Crons
