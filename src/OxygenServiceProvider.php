<?php

namespace EMedia\Oxygen;

use EMedia\Oxygen\Commands\CreateNewUserCommand;
use EMedia\Oxygen\Commands\Scaffolding\ScaffoldViewsCommand;
use EMedia\Oxygen\Commands\OxygenSetupCommand;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Silber\Bouncer\BouncerFacade;
use Illuminate\Database\Schema\Blueprint;

class OxygenServiceProvider extends ServiceProvider
{

	public function boot()
	{
		// load default views
		$this->loadViewsFrom(__DIR__.'/../resources/views', 'oxygen');

		// allow user to publish views
		$this->publishes([
			__DIR__ . '/../resources/views' => base_path('resources/views/vendor/oxygen'),
		], 'views');

		// SASS files
		$this->publishes([
			__DIR__ . '/../resources/sass' => base_path('resources/sass'),
		], 'source-sass');

		// JS source
		$this->publishes([
			__DIR__ . '/../resources/js' => base_path('resources/js'),
		], 'source-js');

		// publish common entities
		$this->publishes([
			__DIR__ . '/../Stubs/Entities' => app_path('Entities'),
		], 'entities');

		// publish Auth controllers
		$this->publishes([
			__DIR__ . '/../Stubs/Http/Controllers/Auth' => app_path('Http/Controllers/Auth'),
		], 'auth-controllers');

		$this->publishes([
			__DIR__ . '/../Stubs/Http/Controllers/API' => app_path('Http/Controllers/API'),
		], 'api-controllers');

		$this->publishes([
			__DIR__ . '/../LaravelDefaultFiles/app/Http/Controllers/API' => app_path('Http/Controllers/API'),
			__DIR__ . '/../LaravelDefaultFiles/app/Http/Controllers/Manage' => app_path('Http/Controllers/Manage'),
		], 'default-controllers');

		$this->publishes([
			__DIR__ . '/../Stubs/Seeds' => database_path('seeds'),
		], 'database-seeds');

		// publish config
		$this->publishes([
			__DIR__.'/../Stubs/config/oxygen.php' => config_path('oxygen.php'),
			__DIR__.'/../Stubs/config/features.php' => config_path('features.php')
		], 'oxygen-config');

		// set custom models for abilities and roles
		$abilityModel = config('oxygen.abilityModel');
		if ($abilityModel) BouncerFacade::useAbilityModel($abilityModel);
		$roleModel = config('oxygen.roleModel');
		if ($roleModel) BouncerFacade::useRoleModel($roleModel);

		$this->registerCustomValidators();

	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->mergeConfigFrom( __DIR__ . '/../config/auth.php', 'auth');

		$this->registerDependentServiceProviders();
		$this->registerAliases();

		if ($this->app->environment('local'))
		{
			$this->app->singleton("emedia.oxygen.setup", function () {
				return app(OxygenSetupCommand::class);
			});
			$this->commands("emedia.oxygen.setup");

			$this->commands(ScaffoldViewsCommand::class);
		}

		$this->commands(CreateNewUserCommand::class);

		$this->registereDatabaseMacros();
	}

	/**
	 *
	 * Register dependant service providers for the package
	 *
	 */
	private function registerDependentServiceProviders()
	{
		$this->app->register(\EMedia\MultiTenant\MultiTenantServiceProvider::class);
	}

	/**
	 *
	 * Register aliases for the package
	 *
	 */
	private function registerAliases()
	{
		$loader = \Illuminate\Foundation\AliasLoader::getInstance();

		$loader->alias('TenantManager', \EMedia\MultiTenant\Facades\TenantManager::class);
	}

	private function registerCustomValidators()
	{
		// custom validation rules

		// match array count is equal
		// eg: match_count_with:permission::name
		// this will match the array count between both fields
		Validator::extend('match_count_with', function ($attribute, $value, $parameters, $validator) {
			// dd(count($value));
			$otherFieldCount = request()->get($parameters[0]);
			return (count($value) === count($otherFieldCount));
		});

		// custom message
		Validator::replacer('match_count_with', function ($message, $attribute, $rule, $parameters) {
			return "The values given in two array fields don't match.";
		});
	}

	protected function registereDatabaseMacros()
	{
		Blueprint::macro('location', function () {
			// location
			/** @var Blueprint $this */
			$this->string('venue')->nullable();
			$this->string('address')->nullable();
			$this->string('street')->nullable();
			$this->string('street_2')->nullable();
			$this->string('city')->nullable();
			$this->string('state')->nullable();
			$this->string('zip')->nullable();
			$this->string('country')->nullable();
			$this->float('latitude', 10, 6)->nullable()->index();
			$this->float('longitude', 10, 6)->nullable()->index();
		});

		Blueprint::macro('dropLocation', function () {
			// drop location
			$this->dropColumn('venue');
			$this->dropColumn('address');
			$this->dropColumn('street');
			$this->dropColumn('street_2');
			$this->dropColumn('city');
			$this->dropColumn('state');
			$this->dropColumn('zip');
			$this->dropColumn('country');
			$this->dropColumn('latitude');
			$this->dropColumn('longitude');
		});
	}

}
