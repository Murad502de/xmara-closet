<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\amoAPI\amoCRM;
use App\Models\Account;
use App\Models\Lead;
use App\Models\changeStage;

class LeadController extends Controller
{
    public const STAGE_LOSS                 = 143;
    public const STAGE_SUCCESS              = 142;
    public const LEADS_COUNT                = 20;
    public const LEADS_COUNT_ZUM_SCHLISSEN  = 50;
    public const HERSTELLERKUERZEL          = 352873;

	public function changeStage ( Request $request )
	{
		$inputData = $request->all();

		Log::info( __METHOD__, $inputData );

		$dataLead = $inputData[ 'leads' ][ 'status' ][ 0 ];

        changeStage::updateOrCreate(
            [ 'lead_id' => ( int ) $dataLead[ 'id' ], ],

            [ 'lead'    => json_encode( $dataLead ), ]
        );

		return response( [ 'OK' ], 200 );
	}

	public function cronChangeStage ()
	{
		$account    = new Account();
		$authData   = $account->getAuthData();

		$amo            = new amoCRM( $authData );
		$objLead        = new Lead();
        $objChangeStage = new changeStage();

		$leads = changeStage::take( self::LEADS_COUNT )->get();

		foreach ( $leads as $lead )
		{
			$leadData = json_decode( $lead->lead, true );

			$lead_id    = ( int ) $leadData[ 'id' ];
            $status_id  = ( int ) $leadData[ 'status_id' ];

			if ( $status_id === self::STAGE_SUCCESS )
			{
                // echo 'leadData aus der Datenbank<br>';
                // echo '<pre>';
                // print_r( $leadData );
                // echo '</pre>';

                $responsible_user_id    = ( int ) $leadData[ 'responsible_user_id' ];
                $pipeline_id            = ( int ) $leadData[ 'pipeline_id' ];

                $hauptLead = $amo->findLeadById( $lead_id );

                if ( $hauptLead[ 'code' ] === 404 || $hauptLead[ 'code' ] === 400 )
                {
                    // Leadsdaten aus der Datenbank entfernen (change_stage)
                    $objChangeStage->deleteLead( $lead_id );

                    continue;
                }

                // echo 'hauptLead<br>';
                // echo '<pre>';
                // print_r( $hauptLead );
                // echo '</pre>';

                $hauptLead_custom_fields    = $hauptLead[ 'body' ][ 'custom_fields_values' ];
                $hauptLeadHerstellerkuerzel = null;

                // echo 'hauptLead_custom_fields<br>';
                // echo '<pre>';
                // print_r( $hauptLead_custom_fields );
                // echo '</pre>';

                if ( !$hauptLead_custom_fields )
                {
                    // Leadsdaten aus der Datenbank entfernen (change_stage)
                    $objChangeStage->deleteLead( $lead_id );

                    continue;
                }

                for ( $cfIndex = 0; $cfIndex < count( $hauptLead_custom_fields ); $cfIndex++ )
                {
                    if ( ( int ) $hauptLead_custom_fields[ $cfIndex ][ 'field_id' ] === self::HERSTELLERKUERZEL )
                    {
                        $hauptLeadHerstellerkuerzel = $hauptLead_custom_fields[ $cfIndex ][ 'values' ][ 0 ][ 'value' ];

                        break;
                    }
                }

                if ( !$hauptLeadHerstellerkuerzel )
                {
                    echo 'hauptLeadHerstellerkuerzel ist leer<br>';

                    // Leadsdaten aus der Datenbank entfernen (change_stage)
                    $objChangeStage->deleteLead( $lead_id );

                    continue;
                }

                echo 'hauptLeadHerstellerkuerzel: ' . $hauptLeadHerstellerkuerzel . '<br>';

                $mainContactId  = null;
                $contacts       = $hauptLead[ 'body' ][ '_embedded' ][ 'contacts' ];

                for ( $contactIndex = 0; $contactIndex < count( $contacts ); $contactIndex++ )
                {
                    if ( $contacts[ $contactIndex ][ 'is_main' ] )
                    {
                        $mainContactId = ( int ) $contacts[ $contactIndex ][ 'id' ];

                        break;
                    }
                }

                if ( !$mainContactId ) // FIXME
                {
                    // Leadsdaten aus der Datenbank entfernen (change_stage)
                    $objChangeStage->deleteLead( $lead_id );

                    continue;
                }

                $contact = $amo->findContactById( $mainContactId );

                if ( $contact[ 'code' ] === 404 || $contact[ 'code' ] === 400 )
                {
                    // Leadsdaten aus der Datenbank entfernen (change_stage)
                    $objChangeStage->deleteLead( $lead_id );

                    continue;
                }

                $leads                      = $contact[ 'body' ][ '_embedded' ][ 'leads' ];
                $activeLeadsZumSchlissen    = [];

                for ( $leadIndex = 0; $leadIndex < count( $leads ); $leadIndex++ )
                {
                    $lead = $amo->findLeadById( $leads[ $leadIndex ][ 'id' ] );

                    if (
                        ( int ) $lead[ 'body' ][ 'status_id' ] !== self::STAGE_SUCCESS
                            &&
                        ( int ) $lead[ 'body' ][ 'status_id' ] !== self::STAGE_LOSS
                    )
                    {
                        // TODO Herstellerkuerzel pruefen
                        $lead_custom_fields         = $lead[ 'body' ][ 'custom_fields_values' ];
                        //$hauptLeadHerstellerkuerzel = null;

                        echo 'lead_custom_fields Herstellerkuerzel pruefen<br><pre>';
                        echo "id: " . $lead[ 'body' ][ 'id' ] . '<br>';
                        print_r( $lead_custom_fields );
                        echo '</pre>';

                        if ( !$lead_custom_fields )
                        {
                            // TODO delete lead
                            echo 'es gibt keine lead_custom_fields bei Herstellerkuerzel pruefen<br>';

                            continue;
                        }

                        for ( $cfIndex = 0; $cfIndex < count( $lead_custom_fields ); $cfIndex++ )
                        {
                            if ( ( int ) $lead_custom_fields[ $cfIndex ][ 'field_id' ] === self::HERSTELLERKUERZEL )
                            {
                                echo 'that<br>';
                                echo $lead_custom_fields[ $cfIndex ][ 'values' ][ 0 ][ 'value' ] . " : " . $hauptLeadHerstellerkuerzel . '<br>';

                                if ( $lead_custom_fields[ $cfIndex ][ 'values' ][ 0 ][ 'value' ] == $hauptLeadHerstellerkuerzel )
                                {
                                    $activeLeadsZumSchlissen[] = $lead[ 'body'];
                                }

                                break;
                            }
                            else
                            {
                                echo 'other<br>';
                                echo $lead_custom_fields[ $cfIndex ][ 'values' ][ 0 ][ 'value' ]  . " : " . $hauptLeadHerstellerkuerzel . '<br>';
                            }
                        }

                        if ( !$hauptLeadHerstellerkuerzel )
                        {
                            echo 'hauptLeadHerstellerkuerzel ist leer<br>';
                            continue;
                        }

                        echo 'hauptLeadHerstellerkuerzel: ' . $hauptLeadHerstellerkuerzel . '<br>';
                    }
                }

                echo 'activeLeadsZumSchlissen<br><pre>';
                print_r( $activeLeadsZumSchlissen );
                echo '</pre>';

                if ( count( $activeLeadsZumSchlissen ) )
                {
                    echo '
                        <br>
                            activeLeadsZumSchlissen m??ssen registriert werden
                        <br>
                    ';

                    for ( $actLeadIndex = 0; $actLeadIndex < count( $activeLeadsZumSchlissen ); $actLeadIndex++ )
                    {
                        echo 'activeLead: ' . $activeLeadsZumSchlissen[ $actLeadIndex ][ 'name' ] . " : " . $activeLeadsZumSchlissen[ $actLeadIndex ][ 'id' ] . '<br>';

                        $str = mb_strtolower( $activeLeadsZumSchlissen[ $actLeadIndex ][ 'name' ] );

                        echo '<pre>';

                        var_dump($str);

                        $isMatched = preg_match('/????????????????????:.*????????.*/i', $str, $matches);

                        var_dump($isMatched, $matches);

                        echo '</pre>';

                        if ( $isMatched )
                        {
                            Lead::updateOrCreate(
                                [
                                    'lead_id'  => $activeLeadsZumSchlissen[ $actLeadIndex ][ 'id' ],
                                ],
                            );
                        }
                    }
                }
			}

			// Leadsdaten aus der Datenbank entfernen (change_stage)
			$objChangeStage->deleteLead( $lead_id );
		}
	}

    public function cronCloseLeads ()
    {
        $account    = new Account();
		$authData   = $account->getAuthData();

		$amo            = new amoCRM( $authData );
		$objLead        = new Lead();

		$leads = Lead::take( self::LEADS_COUNT_ZUM_SCHLISSEN )->get();

        foreach ( $leads as $lead )
        {
            echo 'das Lead zum schlissen: ' . $lead->lead_id . '<br>';

            $amo->updateLead(
                [
                    [
                        "id"        => ( int ) $lead->lead_id,
                        "status_id" => self::STAGE_LOSS,
                    ]
                ]
            );

            $lead->delete();
        }
    }
}
