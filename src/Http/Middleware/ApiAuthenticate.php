<?php namespace EMedia\Oxygen\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

class ApiAuthenticate {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// check if the X-API-KEY is not found in the header or if the token is invalid
		$apiToken 		= request()->header('x-api-key');
		$validApiToken 	= config('oxygen.apiKey');

		$validApiTokens = explode(',', $validApiToken);

		if (empty($apiToken)) {
			$response = [
				'result'	=> false,
				'message'	=> 'An API Key is required',
				'type'		=> 'INVALID_PARAMETER_API_KEY'
			];
			return response($response, 401);
		}

		if (!in_array($apiToken, $validApiTokens)) {
			$response = [
				'result'	=> false,
				'message'	=> 'A valid API Key is required',
				'type'		=> 'INVALID_PARAMETER_API_KEY'
			];
			return response($response, 401);
		}

		return $next($request);
	}

}
