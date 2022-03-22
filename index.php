<?php

use App\Bitrix24\Bitrix24API;
use App\Bitrix24\Bitrix24APIException;
use App\HTTP\HTTP;
use Scad\Scad;

require __DIR__ . '/vendor/autoload.php';

// Configuration file
$parser = new \IniParser('config.ini');
$config = $parser->parse();

// HTTP routing
$app = new \Slim\Slim();

// App seetings
$app->config([

	// App
	'debug' 		=> $config->debug,
	'logfile'		=> $config->log,
	'loglvl'		=> intval( $config->level ),

	// Bitrix24
	'b24_lead_get' 	=> $config->b24->lead_get,
	'b24_lead_upd' 	=> $config->b24->lead_upd,
	'b24_user_get'	=> $config->b24->user_get,
	'b24_opt_thr'	=> $config->b24->throttle,
	'b24_opt_delay'	=> $config->b24->timeout,

	// Scad
	'scad_host' 	=> $config->scad->host,
	'scad_port' 	=> $config->scad->port,
	'scad_login'	=> $config->scad->login,
	'scad_password'	=> $config->scad->password
]);

// Logs
$app->hook('slim.before', function () use ($app) {

	$app->log->setEnabled(true);

	$app->log->setLevel($app->config('loglvl'));

	$handler = @fopen( $app->config('logfile'), 'a');

    $app->log->setWriter( new \Scad\Log($handler) );
});

// Disabled index page for security reason
$app->get('/', function () use ($app) {
    $app->halt(403, 'You shall not pass!');
});

/**
 * 
 * Lead data load 
 **/
$app->post('/lead/:lid', function (int $lid) use ($app) 
{
	// @todo: auth required

	$app->log->info( sprintf( "Lead #%d requested", $lid ) );

	// Log
	$SyncLog = array();

	// B24 Lead data
	$Lead = null;

	// B24 contact
	$Contact = null;

	// Scad user
	$Customer = null;

	// Scad most recent contract
	$Contract = null;

	// Lead update fields values
	$B24Update = array();

	// Person search params
	$Query = [
		'phone'		=> null,		
		'inn'		=> null,
		'passport'	=> null,
		'familiya'	=> null,
		'imya'		=> null,
		'otchestvo' => null
	];

	// Fields mapping

	$B24FieldsMap = [
		'LAST_NAME'				=> 'familiya', 
		'NAME'					=> 'imya', 
		'SECOND_NAME'			=> 'otchestvo',
		'PHONE'					=> 'phone',
		'UF_CRM_1643199726691' 	=> 'inn',
		'UF_CRM_1643199747711'	=> 'passport',
	];

	$B24FieldPlural = [
		'UF_CRM_1643199588174',
		'UF_CRM_1643199655686',
		'UF_CRM_1643199596650',
		'UF_CRM_1643199726691',
		'UF_CRM_1643199682295',
		'UF_CRM_1643199747711',
		'UF_CRM_1643199672790',
		'UF_CRM_1643199630471',
		'UF_CRM_1643199759077',
		'UF_CRM_1643199771084',
		'UF_CRM_1643199813401',
		'UF_CRM_1643199784872'
	];

	$SCADFieldsMapCustomer = [
		'familiya' 		=> 'LAST_NAME',
		'imya'			=> 'NAME',
		'otchestvo' 	=> 'SECOND_NAME',
		'active' 		=> 'UF_CRM_1643199588174',
		'debt' 			=> 'UF_CRM_1643199655686', 
		'ended' 		=> 'UF_CRM_1643199596650',
		'inn' 			=> 'UF_CRM_1643199726691', 
		'latePayment' 	=> 'UF_CRM_1643199682295',
		'passport' 		=> 'UF_CRM_1643199747711',
		'remain' 		=> 'UF_CRM_1643199672790',
		'returned' 		=> 'UF_CRM_1643199630471',
		'ids' 			=> 'UF_CRM_1643199640266',
		'suboffice' 	=> 'UF_CRM_1644099572'
	];

	$SCADFieldsMapContract = [		
		'months' 		=> 'UF_CRM_1643199759077',
		'sum' 			=> 'UF_CRM_1643199771084',
		'vznos' 		=> 'UF_CRM_1643199813401',
		'consultant' 	=> 'UF_CRM_1643631680846',
		'skidka' 		=> 'UF_CRM_1643199784872',
		'date' 			=> 'UF_CRM_1643199830172',
	];

	// Loading B24 Lead
	try 
	{		
		$bx24 = new Bitrix24API( $app->config('b24_lead_get') );

		$bx24->http->throttle = $app->config('b24_opt_thr');

    	$bx24->http->curlTimeout = $app->config('b24_opt_delay');

    	$app->log->info(sprintf("Lead #%d, Loading B24 lead data ... ", $lid));

		$Lead = $bx24->getLead( $lid, [ Bitrix24API::$WITH_CONTACTS ] );

		$app->log->info(sprintf("Lead #%d, Loaded ", $lid));

		$app->log->debug(sprintf("Lead #%d: %s", $lid, serialize($Lead)));

		foreach ( array_keys($B24FieldsMap) as $field )
		{
			if ( !empty($Lead[$field]) )
			{
				if ( is_array( $Lead[ $field ] ) )
				{	
					$Query[$B24FieldsMap[$field]] = is_array( $Lead[ $field ][0] )
						? $Lead[ $field ][0]['VALUE']
						: $Lead[ $field ][0];
				}
				else
				{
					$Query[$B24FieldsMap[$field]] = trim( $Lead[$field] );
				}
			}
		}

		// Hight priority Contact's data
		if ( !empty($Lead['CONTACT_ID']))
		{
			$app->log->debug(sprintf("Lead #%d, has Contact ", $lid));

			$bx24 = new Bitrix24API( $app->config('b24_user_get') );

			$bx24->http->throttle = $app->config('b24_opt_thr');

    		$bx24->http->curlTimeout = $app->config('b24_opt_delay');

    		$app->log->debug(sprintf("Lead #%d, loading Contacts ... ", $lid));

    		$Contact = $bx24->getContact( $Lead['CONTACT_ID'] );

    		$app->log->debug(sprintf("Lead #%d, Contact: %s", $lid, serialize($Contact)));

    		if ( "Y" == $Contact['HAS_PHONE'])
    			$Query['phone'] = $Contact['PHONE'][0]['VALUE']; 
		}	
	} 
	catch ( Bitrix24APIException $e ) 
	{
		$msg = sprintf('Lead #%d, not found: %s', $lid, $e->getMessage());

		$app->log->critical( $msg );

		throw new Exception( $e );
	} 
	catch ( Exception $e ) 
	{
		$app->log->critical( $e );

		throw new Exception( $e );	
	}

	$app->log->info(sprintf("Lead #%d, Loading SCAD data ... ", $lid));

	// Loading SCAD data
	try
	{
		$Scad = new Scad(
			$app->config('scad_host'),
			$app->config('scad_port')
		);

		$app->log->debug(sprintf("Lead #%d, SCAD query: %s", $lid, serialize($Query)));

		$Customer = $Scad->getToken( 
				$app->config('scad_login'), 
				$app->config('scad_password')
			)
			->findClient($Query);

		$app->log->debug(sprintf("Lead #%d, SCAD Customer: %s", $lid, serialize($Customer)));

		$app->log->info(sprintf("Lead #%d, SCAD Customer loaded", $lid));

		if ( !empty($Customer['contract']) )
		{
			$Customer += $Customer['contract'];

			// Most recent contract ID
			$mostRecentId = null;

			// All contract IDs
			$contractIds = array();

			if ( !empty($Customer['contract']['ids']) )
			{
				$contractIds = array_map(function($i){ 
					return intval( $i['id'] ); 
				}, $Customer['contract']['ids']);

				$app->log->debug(sprintf("Lead #%d, SCAD contracts: %s", $lid, serialize($contractIds)));

				if ( !empty($contractIds) )
				{
					// @todo
				 	$Customer['ids'] = $contractIds;

					$mostRecentId = max($contractIds);
				}
			}

			if ($mostRecentId)
			{
				$app->log->debug(sprintf("Lead #%d, SCAD contract selected: %d", $lid, $mostRecentId));

				try 
				{
					$Contract = $Scad->getContract( $mostRecentId );

					$app->log->debug(sprintf("Lead #%d, SCAD Contract: %s", $lid, serialize($Contract)));
				}				
				catch (Exception $e)
				{
					$app->log->error(sprintf("Lead #%d: %s", $lid, $e));

					$SyncLog[] = sprintf("Error loading SCAD contact: %d", $mostRecentId);
				}	

				$app->log->info(sprintf("Lead #%d, SCAD Contract loaded", $lid));
			}
		}
		else
		{
			$app->log->info(sprintf("Lead #%d, No contract", $lid));

			// @todo: scad log
			$SyncLog[] = sprintf("Error loading contract: %d", $mostRecentId);
		}

	}
	catch (Exception $e)
	{
		$app->log->error(sprintf("Lead #%d - %s", $lid, $e));

		$SyncLog[] = sprintf("SCAD error: %s", $e);
	}

	if ( $Customer )
	{
		foreach( $SCADFieldsMapCustomer as $scf => $b24f )
		{
			$B24Update[ $b24f ] = in_array($b24f, $B24FieldPlural)
				? [ 'VALUE' => $Customer[ $scf ] ]
				: $Customer[ $scf ];
		}
	}
	else
	{
		$SyncLog[] = sprintf('SCAD no customer');
	}

	if ( $Contract )
	{
		foreach ( $SCADFieldsMapContract as $scf => $b24f )
		{
			$B24Update[ $b24f ] = in_array($b24f, $B24FieldPlural)
				? [ 'VALUE' => $Contract[ $scf ] ]
				: $Contract[ $scf ];
		}
	}
	else
	{
		$SyncLog[] = sprintf('SCAD no contract');
	}

	$app->log->info(sprintf("Lead #%d, updating ...", $lid));

	$app->log->debug(sprintf("Lead #%d, new data: %s", $lid, serialize($B24Update)));

	// Updating Lead
	try
	{
		$bx24 = new Bitrix24API( $app->config('b24_lead_upd') );	

		$B24Update['UF_CRM_1643628554625'] = array_pop($SyncLog);

		$res = $bx24->updateLead($lid, $B24Update );

		$app->log->info(sprintf("Lead #%d, updated", $lid));
	}
	catch (Bitrix24APIException $e) 
	{
		$app->log->critical(sprintf("Lead #%d, Bitrix24APIException: %s", $lid, $e));

		throw new Exception( $e );
	} 
	catch (Exception $e) 
	{
		$app->log->critical(sprintf("Lead #%d, Exception: %s", $lid, $e));

		throw new Exception( $e );
	}

})->conditions(array('lid' => '[0-9]+'));

$app->run();