<?php


namespace EMedia\Oxygen\Http\Middleware;


use Closure;
use EMedia\Devices\Auth\DeviceAuthenticator;

class ApiUserAccessTokenVerification
{

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$accessToken = $request->header('x-access-token');

		if (empty($accessToken)) {
			return response()->apiErrorAccessDenied('x-access-token missing from request');
		}

		if (!DeviceAuthenticator::validateToken($accessToken)) {
			return response()->apiErrorUnauthorized('Invalid access token');
		}

		return $next($request);
	}

}