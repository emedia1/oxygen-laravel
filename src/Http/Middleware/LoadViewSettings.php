<?php

namespace EMedia\Oxygen\Http\Middleware;

use Closure;
use EMedia\MultiTenant\Facades\TenantManager;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\View;

class LoadViewSettings
{
	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$appName 	= config('app.name');
		$pageTitle	= 'My Account';

		View::share('appName', $appName);
		View::share('pageTitle', $pageTitle);

		if ($user = $this->auth->user()) {
			View::share('user', $user);

			// Tenants
			// $tenants = TenantManager::allTenants();
			if (TenantManager::multiTenancyIsActive()) View::share('tenants', $user->tenants);
		}

		return $next($request);
	}
}
