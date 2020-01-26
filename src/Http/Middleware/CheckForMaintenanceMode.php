<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Exceptions\MaintenanceModeException;

class CheckForMaintenanceMode
{
	/**
	 * The application implementation.
	 *
	 * @var \Illuminate\Contracts\Foundation\Application
	 */
	protected $app;

	/**
	 * Create a new middleware instance.
	 *
	 * @param  \Illuminate\Contracts\Foundation\Application  $app
	 * @return void
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\HttpException
	 */
	public function handle($request, Closure $next)
	{
		if ($this->app->isDownForMaintenance() && !in_array($request->getClientIp(), $this->getIpExclusions()) ) {
			$data = json_decode(file_get_contents($this->app->storagePath().'/framework/down'), true);

			throw new MaintenanceModeException($data['time'], $data['retry'], $data['message']);
		}

		return $next($request);
	}

	/**
	 *
	 * Set a list of IP addresses to be excluded from going down for maintenance.
	 * Useful for deployment troubleshooting in production
	 *
	 * @return array
	 */
	protected function getIpExclusions()
	{
		return ['220.101.87.98'];
	}
}
