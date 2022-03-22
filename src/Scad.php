<?php

namespace Scad;

class Scad 
{
	const URI_TOKEN = '/serviceOnline/getToken';

	const URI_CLIENT = '/serviceOnline/search';

	const URI_CONTRACT = '/serviceOnline/contractDetails';

	private $scad = null;

	private $token = null;

	private $timeout = 3.0;

	private $expiryTime = null;

	public function __construct(string $host, int $port) 
	{
		$this->scad = new \GuzzleHttp\Client([
			'base_uri' => sprintf("http://%s:%d", $host, $port),
			'timeout' => $self->timeout,
		]);

		return $this;
	}


	public function getContract ( int $cid ) 
	{
		$result = null;

		try 
		{
			$response = $this->scad->request('POST', self::URI_CONTRACT, [
				'json' => [
					'token' 	=> $this->token,
					'contractId' => intval($cid)
				]
			]);

			$body = (string) $response->getBody();

			$data = json_decode($body, true);

			$allowed = [
				'id', 'months', 'sum', 'skidka', 'vznos', 'consultant',
				'date', 'schedule', 'pays', 'goods'
			];		

			if (empty($data))	
				throw new \Exception($body);
			
			$result = array_intersect_key( $data , array_flip( $allowed ));
		} 
		catch (\GuzzleHttp\Exception\BadResponseException $e)
		{
			throw new ScadException( 'Contract not found');		

		}
		catch (\Exception $e)
		{
			throw new ScadException( 'Contract error: ' . $e->getMessage() );		
		}

		return $result;
	}


	public function findClient ( $query ) 
	{
		$result = null;

		$allowed = [
			'passport', 'phone', 'otchestvo',
			'imya', 'inn', 'familiya'
		];

		$query = array_intersect_key( $query , array_flip( $allowed ));

		if ( empty($query) )
			throw new ScadException( 'Invalid search params' );		

		$query = array_map('trim', $query);

		try {

			$query['token'] = $this->token;

			$response = $this->scad->request('POST', self::URI_CLIENT, [
				'json' => $query
			]);

			$body = (string) $response->getBody();

			$data = json_decode($body, true);

			$allowed = [
				'latePayment', 'inn', 'debt', 'imya', 'phone', 'otchestvo',
				'remain', 'contract', 'familiya', 'passport', 'suboffice'				
			];		
		
			if (empty($data))
				throw new \Exception($body);
			
			$result = array_intersect_key( $data , array_flip( $allowed ));	

		} 
		catch (\GuzzleHttp\Exception\BadResponseException $e)
		{
			throw new ScadException( 'Client not found');		
		}
		catch (\Exception $e)
		{
			throw new ScadException( 'Search error: ' . $e->getMessage());		
		}

		return $result;
	}


	public function getToken( string $login, string $password ) 
	{
		try 
		{
			$response = $this->scad->request('POST', self::URI_TOKEN, [
				'json' => [
					'login' 	=> $login,
    				'password' 	=> md5($password)
    			]    			
			]);

			$body = $response->getBody();

			$data = json_decode($body, true);

			if ( empty($data['token']) )	
			{
				throw new ScadException( 'Empty token' );		
			}

			$this->token = $data['token'];
		} 
		catch (\GuzzleHttp\Exception\ClientException $e)
		{
			$body = (string) $e->getResponse()->getBody();
			$data = json_decode($body, true);
			throw new ScadException( $data['message'] );
		}
		catch (\Exception $e) 
		{
			throw new ScadException( 'Token error' );
		}
		
		return $this;
	}

	public function setTimeout( float $timeout ) {
		$this->timeout = $timeout;
		return $this;
	}
}