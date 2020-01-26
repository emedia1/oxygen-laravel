<?php

namespace EMedia\Oxygen\Commands;

use EMedia\Generators\Commands\BaseGeneratorCommand;
use EMedia\PHPHelpers\Files\DirManager;
use EMedia\PHPHelpers\Files\FileEditor;
use EMedia\PHPHelpers\Files\FileManager;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

class OxygenSetupCommand extends BaseGeneratorCommand
{

	protected $signature   = 'setup:oxygen-project
								{--confirm : Confirm with the user if there are potential issues}
								{--name= : Name of the project}
								{--devurl= : Development URL alias of the local machine}
								{--email= : Default email for system emails and seeding}';

	protected $description = 'Generate common files for the Oxygen project';

	protected $dontAsk = true;

	protected $projectConfig = [];

	protected $progressLog = [
		'info' 		=> [],
		'errors' 	=> [],
		'comments'	=> [],
		'instructions' => [],
		'files' => [],
	];

	protected $configFile;

	public function __construct(Filesystem $files)
	{
		parent::__construct($files);

		$this->projectConfig = $this->defaultConfig();
		$this->configFile = base_path('oxygen.json');
	}

	public function handle()
	{
		$this->dontAsk = !$this->option('confirm');

		// $this->readSetupConfig();

		// move public folder to public_html
		$this->movePublicFolder();

		// get developer input
		$this->getDeveloperInput();

		// generate the migrations
		$this->generateMigrations();

		// generate the files from stubs
		$this->compileStubs($this->getStubMap());

		// update routes
		$this->addWebRoutes();
		$this->addAPIRoutes();

		// update middleware
		$this->updateMiddleware();

		// create oxygen class files
		$this->createOxygenClassFiles();

		// publish assets and other files
		$this->publishFiles();

		// add child packages
		$this->setupChildPackages();

		// add environment variables
		$this->addEnvironmentVariables();

		// we don't ask for confirmation on this
		$this->replaceKnownStrings();

		// add user display messages
		$this->progressLog['instructions'][] = ['composer dump-autoload', 'Generate the classmap, so the new files are recognized'];
		$this->progressLog['instructions'][] = ['php artisan db:refresh', 'Migrate and seed the database'];
		$this->progressLog['instructions'][] = ['npm install', 'Install NPM packages. Check if Node.js is installed with `npm -v`'];
		$this->progressLog['instructions'][] = ['npm run dev', 'Compile and build. If you get first time error, run it again.'];
		$this->progressLog['instructions'][] = ['npm run watch', 'Run and watch the application on browser (Does NOT work with Homestead)'];
		$this->progressLog['instructions'][] = ['php artisan serve', 'Run the local test server'];

		// Setup Completed! Show any info to the user.
		$this->showProgressLog();

		$this->updateReadMeFile();

		// $this->saveSetupConfig();
	}

	public function setupChildPackages()
	{
		$this->call('setup:package:app-settings');
		$this->call('setup:package:devices');
	}


	/**
	 *
	 * Move public folder to public_html
	 *
	 */
	protected function movePublicFolder()
	{
		$this->call('setup:move-public', [
			'--y' => true,		// don't ask for confirmation
		]);
	}


	/**
	 *	Get user input to customise setup
	 *
	 */
	protected function getDeveloperInput()
	{
		$userInput = [];

		$userInput['projectName'] 	 = ($this->option('name')) ?? $this->ask('What is the project name?', 'ADMIN PANEL');
		$userInput['fromEmail']   	 = ($this->option('email')) ?? $this->ask('What is the `from` email address for system emails? (Press ENTER key for default)', 'apps@elegantmedia.com.au');
		$userInput['seedAdminEmail'] = ($this->option('email')) ?? $this->anticipate('What is your email to seed the database? (Press ENTER key for default)', [], $userInput['fromEmail']);

		$defaultDomain = 'localhost.dev';
		if (!empty($userInput['projectName'])) {
			$defaultDomain = str_slug($userInput['projectName']) . '.devv';
		}
		$userInput['devMachineUrl'] = ($this->option('devurl')) ?? $this->anticipate('What is the local development URL? (Press ENTER key for default)', [], $defaultDomain);
		// $userInput['dashboardType']  = $this->choice('What should be the type of the dashboard?', ['HTML/CSS (Default)', 'Angular'], 0);

		// if ($this->confirm('Should the project have Multi-Tenant support?', false))
		$userInput['multiTenant'] = false;

		$this->projectConfig = array_merge($this->projectConfig, $userInput);
	}

	protected function defaultConfig()
	{
		return [
			'description'	=> 'Setup options for Oxygen assets',
			'multiTenant'	=> false,
		];
	}

	protected function readSetupConfig()
	{
		if ( ! file_exists($this->configFile)) return;

		$configContents = file_get_contents($this->configFile);
		$mergedConfig   = array_merge(json_decode($configContents, true), $this->projectConfig);
		$this->projectConfig = $mergedConfig;
	}

	protected function saveSetupConfig()
	{
		file_put_contents($this->configFile, json_encode($this->projectConfig, JSON_PRETTY_PRINT));
	}

	protected function generateMigrations()
	{
		// Migration Order
		// tenants
		// bouncer
		// bouncer updates
		// invitations
		// files

		$fileData = [];

		if ($this->projectConfig['multiTenant'])
		{
			// publish tenants
			$fileData[] = [
				'stub' => __DIR__ . '/../../Stubs/Migrations/001_create_tenants_tables.php',
				'destination_path' => database_path("migrations"),
				'destination_filename' => "{$this->getTimestamp()}_create_tenants_tables.php",
				'unique_file_id' => 'create_tenants_tables.php',
			];
		}

		$fileData[] = [
			'stub' => __DIR__ . '/../../Stubs/Migrations/002_create_role_permission_tables.php',
			'destination_path' => database_path("migrations"),
			'destination_filename' => "{$this->getTimestamp()}_create_role_permission_tables.php",
			'unique_file_id' => 'create_role_permission_tables.php',
		];

		$fileData[] = [
			'stub' => __DIR__ . '/../../Stubs/Migrations/003_alter_users_table.php',
			'destination_path' => database_path("migrations"),
			'destination_filename' => "{$this->getTimestamp()}_alter_users_table.php",
			'unique_file_id' => 'alter_users_table.php',
		];

		$fileData[] = [
			'stub' => __DIR__ . '/../../Stubs/Migrations/004_create_invitations_table.php',
			'destination_path' => database_path("migrations"),
			'destination_filename' => "{$this->getTimestamp()}_create_invitations_table.php",
			'unique_file_id' => 'create_invitations_table.php',
		];

		$fileData[] = [
			'stub' => __DIR__ . '/../../Stubs/Migrations/006_create_files_table.php',
			'destination_path' => database_path("migrations"),
			'destination_filename' => "{$this->getTimestamp()}_create_files_table.php",
			'unique_file_id' => 'create_files_table.php',
		];

		if ($this->projectConfig['multiTenant'])
		{
			$fileData[] = [
				'stub' => __DIR__ . '/../../Stubs/Migrations/005_update_auth_tables_multi_tenant.php',
				'destination_path' => database_path("migrations"),
				'destination_filename' => "{$this->getTimestamp()}_update_auth_tables_multi_tenant.php",
				'unique_file_id' => 'update_auth_tables_multi_tenant.php',
			];
//			$this->compileStubs([[
//				'stub' => __DIR__ . '/../../Stubs/Migrations/005_update_auth_tables_multi_tenant.php',
//				'path' => database_path('migrations/' . $this->getTimestamp() . '_update_auth_tables_multi_tenant.php'),
//				'name' => 'Update bouncer tables to support multi-tenancy'
//			]]);
		}

		$this->createFilesFromStubs($fileData);
	}

	protected function getStubMap()
	{
		$stubMap = [];

		$stub = [
			'path'	=> base_path('bower.json'),
			'name'	=> 'bower.json',
		];
		$stub['stub'] = __DIR__ . '/../../Stubs/ProjectConfig/bower.json';
		$stubMap[] = $stub;

		$stub = [
			'path'	=> base_path('.bowerrc'),
			'name'	=> 'Bower config (.bowerrc)',
		];
		$stub['stub'] = __DIR__ . '/../../Stubs/ProjectConfig/bowerrc.stub';
		$stubMap[] = $stub;

		$stub = [
			'path'	=> base_path('webpack.mix.js'),
			'name'	=> 'webpack.mix.js',
			'stub'  =>  __DIR__ . '/../../Stubs/ProjectConfig/webpack.mix.js',
			'default' => __DIR__ . '/../../LaravelDefaultFiles/webpack.mix.js',
		];
		$stubMap[] = $stub;

		$stub = [
			'path'	=> base_path('readme.md'),
			'name'	=> 'readme.md',
			'stub'  =>  __DIR__ . '/../../Stubs/ProjectConfig/readme.md',
			'default' => __DIR__ . '/../../LaravelDefaultFiles/readme.md',
		];
		$stubMap[] = $stub;

		$stub = [
			'path'	=> base_path('resources/lang/en/auth.php'),
			'name'	=> 'English (auth) Language File',
			'stub'  =>  __DIR__ . '/../../resources/lang/en/auth.php',
			'default' => __DIR__ . '/../../LaravelDefaultFiles/resources/lang/en/auth.php',
		];
		$stubMap[] = $stub;

		$stub = [
			'path'	=> base_path('apidoc.json'),
			'name'	=> 'APIDoc Configuration (apidoc.json)',
		];
		$stub['stub'] = __DIR__ . '/../../Stubs/ProjectConfig/apidoc.json';
		$stubMap[] = $stub;

//		$stubMap[] = [
//			'stub'	=> __DIR__ . '/../../Stubs/config/oxygen.php',
//			'path'  => config_path('oxygen.php'),
//			'name'	=> 'Oxygen Configuration'
//		];

		if ($this->projectConfig['multiTenant']) {
			$stubMap[] = [
				'stub'	=> __DIR__ . '/../../Stubs/Common/User.MultiTenant.php',
				'path'  => app_path('User.php'),
				'name'	=> 'User.php',
				'default' => __DIR__ . '/../../LaravelDefaultFiles/app/User.php',
			];
			/*$stubMap[] = [
				'stub'	=> __DIR__ . '/../../Stubs/config/acl-multiTenant.php',
				'path'  => config_path('acl.php'),
				'name'	=> 'ACL for multi-tenant configuration'
			];*/
		} else {
			$stubMap[] = [
				'stub'	=> __DIR__ . '/../../Stubs/Common/User.SingleTenant.php',
				'path'  => app_path('User.php'),
				'name'	=> 'User.php',
				'default' => __DIR__ . '/../../LaravelDefaultFiles/app/User.php',
			];
			/*
			$stubMap[] = [
				'stub'	=> __DIR__ . '/../../Stubs/config/acl-singleTenant.php',
				'path'  => config_path('acl.php'),
				'name'	=> 'ACL for single-tenant configuration'
			];*/
		}

		$this->progressLog['instructions'][] = ['bower install', 'Install bower dependencies. Check if Bower is installed with `bower -v`'];

		return $stubMap;
	}



	protected function updateMiddleware()
	{
		if (!$this->dontAsk) {
			if (!$this->confirm('Update Http/Kernel.php with new middleware?', true)) return false;
		}

		$editor = new FileEditor();
		$inputFile = app_path('Http/Kernel.php');

		$defaultFile = __DIR__ . '/../../LaravelDefaultFiles/app/Http/Kernel.php';

		// ask the user if the existing middleware file is not identical to Laravel's default
		if (!FileManager::areFilesSimilar($inputFile, $defaultFile)) {
			if (!$this->confirm('Update Http/Kernel.php with new middleware?', true)) return false;
		}

		$fields = [
			[
				'name'	=> 'routeMiddleware',
				'value' => "'auth.acl' => \EMedia\Oxygen\Http\Middleware\AuthorizeAcl::class"
			],
			[
				'name'	=> 'routeMiddleware',
				'value' => "'auth.api' => \EMedia\Oxygen\Http\Middleware\ApiAuthenticate::class"
			],
			[
				'name'	=> 'routeMiddleware',
				'value' => "'auth.api.logged-in' => \EMedia\Oxygen\Http\Middleware\ApiUserAccessTokenVerification::class"
			],
		];

		// only update the file, if the values are not already in the files
		$fieldsToAdd = array_filter($fields, function ($item) use ($inputFile) {
			return !FileManager::isTextInFile($inputFile, $item['value'], true);
		});

		if ($editor->addPropertyValuesToFile($inputFile, $fieldsToAdd))
		{
			$this->info('Http/Kernel.php updated.');
			$this->progressLog['files'][] = ['Http\Kernel.php', 'Check for duplicate entries.'];
		}

		$fields = [
			[
				'name'	=> 'web',
				'value' => "\EMedia\Oxygen\Http\Middleware\LoadViewSettings::class"
			],
			[
				'name'	=> 'api',
				'value' => "\EMedia\Oxygen\Http\Middleware\ParseNonPostFormData::class"
			],
		];

		$fieldsToAdd = array_filter($fields, function ($item) use ($inputFile) {
			return !FileManager::isTextInFile($inputFile, $item['value'], true);
		});

		$editor->addArrayValuesToFile($inputFile, $fieldsToAdd);

		$this->replaceIn($inputFile,
			"'auth' => \App\Http\Middleware\Authenticate::class",
			"'auth' => \EMedia\Oxygen\Http\Middleware\Authenticate::class");
	}


	protected function addWebRoutes()
	{
		$routesFilePath = base_path('routes/web.php');
		$stubPath = __DIR__ . '/../../Stubs/routes/web.stub';
		$bytes = false;

		try {
			$bytes = FileEditor::appendStubIfSectionNotFound($routesFilePath, $stubPath, null, null, true);
		} catch (\EMedia\PHPHelpers\Exceptions\FileSystem\SectionAlreadyExistsException $ex) {
			if (!$this->confirm("Oxygen routes are already in routes file. Add again?", false)) {
				return false;
			}
		}

		// ask the user and update the routes file if required
		if (!$this->dontAsk) {
			if (!$this->confirm("Update routes file with routes for auth, invitations, roles?", true)) {
				return false;
			}
		}

		if ($bytes === false) {
			$bytes = FileEditor::appendStub($routesFilePath, $stubPath);
		}

		if ($bytes !== false)
		{
			$this->info('routes\web.php file updated.');
			$this->progressLog['files'][] = ['routes\web.php', 'Check for duplicate entries.'];
		}
	}

	protected function addAPIRoutes()
	{
		$routesFilePath = base_path('routes/api.php');
		$stubPath = __DIR__ . '/../../Stubs/routes/api.stub';
		$bytes = false;

		try {
			$bytes = FileEditor::appendStubIfSectionNotFound($routesFilePath, $stubPath, null, null, true);
		} catch (\EMedia\PHPHelpers\Exceptions\FileSystem\SectionAlreadyExistsException $ex) {
			if (!$this->confirm("Oxygen API routes are already in routes file. Add again?", false)) {
				return false;
			}
		}

		// ask the user and update the routes file if required
		if (!$this->dontAsk) {
			if (!$this->confirm("Update routes file with routes for registration and login routes?", true)) {
				return false;
			}
		}

		if ($bytes === false) {
			$bytes = FileEditor::appendStub($routesFilePath, $stubPath);
		}

		if ($bytes !== false)
		{
			$this->info('routes\api.php file updated.');
			$this->progressLog['files'][] = ['routes\api.php', 'Check for duplicate entries.'];
		}
	}


	protected function addEnvironmentVariables()
	{
		$filePaths = [
			base_path('.env'),
			base_path('.env.example'),
		];
		$stubPath = $this->files->get(__DIR__ . '/../../Stubs/ProjectConfig/env.stub');

		foreach ($filePaths as $filePath) {

			try {
				// check if the routes file mentions anything about the 'oxygen routes'
				// if so, it might already be there. Ask the user to confirm.
				if (FileManager::isTextInFile($filePath, 'Oxygen Settings', false)) {
					if (!$this->confirm("Oxygen ENV values are already in {$filePath}. Add again?", false)) {
						return false;
					}
				}
			} catch (FileNotFoundException $ex) {
				$this->error("Environment file not found at `{$filePath}`. Skipping...");
			}

			// ask the user and update the routes file if required
			if (!$this->dontAsk) {
				if (!$this->confirm("Update environment variable files?", true)) {
					return false;
				}
			}

			$result = $this->files->append($filePath, $stubPath);
			if ($result) {
				$this->info("{$filePath} file updated.");
				$this->progressLog['files'][] = [$filePath, 'Check for duplicate entries.'];
			}
		}
	}

	protected function replaceKnownStrings()
	{
		$fromEmail   = $this->projectConfig['fromEmail'];
		$projectName = $this->projectConfig['projectName'];
		$devMachineUrl = $this->projectConfig['devMachineUrl'];

		$stringsToReplace = [
			[
				'path'		=> app_path('Http/Middleware/RedirectIfAuthenticated.php'),
				'search'	=> "return redirect('/home');",
				'replace'	=> "return redirect('/dashboard');"
			],

			// .env file
			[
				'path'		=> base_path('.env'),
				'search'	=> "APP_NAME=Laravel",
				'replace'	=> "APP_NAME=\"{$projectName}\"",
			],
			[
				'path'		=> base_path('.env'),
				'search'	=> "MAIL_FROM_NAME=ExampleSender",
				'replace'	=> "MAIL_FROM_NAME=\"{$projectName} (DEV)\"",
			],

			// .env.example file
			[
				'path'		=> base_path('.env.example'),
				'search'	=> "APP_NAME=Laravel",
				'replace'	=> "APP_NAME=\"{$projectName}\"",
			],
			[
				'path'		=> base_path('.env.example'),
				'search'	=> "MAIL_FROM_NAME=ExampleSender",
				'replace'	=> "MAIL_FROM_NAME=\"{$projectName} (DEV)\"",
			],

			[
				'path'		=> base_path('.env'),
				'search'	=> "APP_URL=http://localhost",
				'replace'	=> "APP_URL=http://{$devMachineUrl}",
			],
			[
				'path'		=> database_path('seeds/Auth/UsersTableSeeder.php'),
				'search'	=> "apps@elegantmedia.com.au",
				'replace'	=> $this->projectConfig['seedAdminEmail']
			],
			[
				'path'		=> base_path('webpack.mix.js'),
				'search'	=> "localhost.dev",
				'replace'	=> $devMachineUrl,
			],

			// Change project name in readme.md
			[
				'path'		=> base_path('readme.md'),
				'search'	=> "OxygenProject",
				'replace'	=> $projectName,
			],

			// database settings
			[
				'path'		=> config_path('database.php'),
				'search'	=> "'engine' => null,",
				'replace'	=> "'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',",
			],
		];

		if ($this->projectConfig['multiTenant']) {
			$stringsToReplace[] = [
				'path'		=> config_path('oxygen.php'),
				'search'	=> "'multiTenantActive' => false,",
				'replace'	=> "'multiTenantActive' => true,"
			];
			$stringsToReplace[] = [
				'path'		=> app_path('Entities/Auth/Ability.php'),
				'search'	=> "use EMedia\Oxygen\Entities\Auth\SingleTenant\Ability as AbilityBase;",
				'replace'	=> "use EMedia\Oxygen\Entities\Auth\MultiTenant\Ability as AbilityBase;"
			];
			$stringsToReplace[] = [
				'path'		=> app_path('Entities/Auth/Role.php'),
				'search'	=> "use EMedia\Oxygen\Entities\Auth\SingleTenant\Role as BaseRole;",
				'replace'	=> "use EMedia\Oxygen\Entities\Auth\MultiTenant\Role as BaseRole;"
			];
		}

		foreach ($stringsToReplace as $stringData)
		{
			$this->replaceIn($stringData['path'], $stringData['search'], $stringData['replace']);
		}
	}

	protected function replaceIn($path, $search, $replace)
	{
		if ( ! $this->files->exists($path) ) {
			$this->error($path . ' not found.');
			$this->progressLog['errors'][] = $path . ' not found to update.';
			return false;
		}
		parent::replaceIn($path, $search, $replace);
	}

	/**
	 *
	 * Create the class files
	 *
	 */
	protected function createOxygenClassFiles()
	{
		// 'stub' must be a path ending with a file
		// 'destination_path' must not end with a slash
		$fileData = [
			[
				'stub' => __DIR__ . '/../../Stubs/Http/Controllers/Common/DashboardController.php',
				'class' => 'App\Http\Controllers\DashboardController',
				'destination_path' => app_path('Http/Controllers'),
			],
			[
				'stub' => __DIR__ . '/../../Stubs/Http/Controllers/Common/PagesController.php',
				'class' => 'App\Http\Controllers\PagesController',
				'destination_path' => app_path('Http/Controllers'),
			],
		];

		$this->createFilesFromStubs($fileData);
	}

	protected function publishFiles()
	{
		$assetInfo = [
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['views'],
					'--force'		=> true,
				],
				'desc'			=> 'default views (say `no` if you do not intent do modify default views)',
				'default'		=> false
			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['source-sass'],
					'--force'		=> false,
				],
				'desc' 			=> 'Uncompiled SASS files'
			],
//			[
//				'command'		=> 'vendor:publish',
//				'arguments'		=> [
//					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
//					'--tag'			=> ['public-assets'],
//					'--force'		=> true,
//				],
//				'desc'			=> 'JS, CSS and other assets in public folder'
//			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['source-js'],
					'--force'		=> true,
				],
				'desc'			=> 'JS Source Files'
			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['database-seeds'],
					'--force'		=> true,
				]
			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['entities'],
					'--force'		=> true,
				]
			],
//			[
//				'command'		=> 'vendor:publish',
//				'arguments'		=> [
//					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
//					'--tag'			=> ['common-controllers'],
//					'--force'		=> true,
//				]
//			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['auth-controllers'],
					'--force'		=> true,
				]
			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['api-controllers'],
					'--force'		=> true,
				]
			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['default-controllers'],
					'--force'		=> true,
				]
			],
			[
				'command'		=> 'vendor:publish',
				'arguments'		=> [
					'--provider'	=> 'EMedia\Oxygen\OxygenServiceProvider',
					'--tag'			=> ['oxygen-config'],
					'--force'		=> true,
				]
			]
		];

		if (!$this->dontAsk) {
			if (!$this->confirm('Publish project assets?', true)) return false;
		}

		foreach ($assetInfo as $asset)
		{
			$default = (empty($asset['default']))? true: $asset['default'];
			$desc    = (empty($asset['desc']))? $asset['arguments']['--tag'][0]: $asset['desc'];

			if (!$this->dontAsk) {
				if (!$this->confirm("Publish $desc ?", $default)) continue;
			}

			$this->call($asset['command'], $asset['arguments']);
		}

		if (!$this->dontAsk) {
			if (!$this->confirm('Publish `public` folder assets?', true)) return false;
		}

		// publish the public folder
		// we need to check if this is `public_html` or something else
		$sourceDir = __DIR__ . '/../../public_html';

		$publicPath = public_path();
		$basename = basename($publicPath);
		if ($basename === 'public') {
			if (!file_exists($publicPath)) {
				$tempPublicPath = str_replace('public', 'public_html', $publicPath);
				if (file_exists($tempPublicPath)) {
					$publicPath = $tempPublicPath;
				} else {
					$this->error("The public path {$publicPath} does not exist. Cannot copy files. Skipping...");
					return false;
				}
			}
		}

		$result = $this->files->copyDirectory($sourceDir, $publicPath);
	}

	protected function showProgressLog()
	{
		$this->info('');
		$this->info('***** OXYGEN SETUP COMPLETED! *****');
		$this->info('');


		if (count($this->progressLog['files'])) {
			$this->info('Check these files for accuracy.');

			$headers = ['File', 'What you should check'];
			$this->table($headers, $this->progressLog['files']);
		}

		if (count($this->progressLog['instructions'])) {
			$this->info('Run these commands in order to complete the build process.');

			$headers = ['ID', 'CLI Command', 'What it does'];

			$rows = [];
			for ($i = 0, $iMax = count($this->progressLog['instructions']); $i < $iMax; $i++) {
				$rows[] = array_merge([$i + 1], $this->progressLog['instructions'][$i]);
			}

			$this->table($headers, $rows);
			$this->info('');
		}

		foreach ($this->progressLog['info'] as $message)
			$this->info($message);

		if (count($this->progressLog['errors'])) {
			$this->error('THESE ERRORS WERE DETECTED:');
			foreach ($this->progressLog['errors'] as $message)
				$this->error($message);
		}
	}

	/**
	 *
	 * Check if a given files and classes exist, otherwise copy the stubs
	 *
	 * @param $fileData
	 */
	protected function createFilesFromStubs($fileData)
	{
		foreach ($fileData as $file) {

			if (!empty($file['unique_file_id'])) {
				// match if a file already exists by the file name match
				$existingFiles = glob($file['destination_path'] . DIRECTORY_SEPARATOR . "*{$file['unique_file_id']}*");
				if (count($existingFiles)) {
					if (!$this->confirm("{$file['unique_file_id']} file already exists. Create another?", false)) {
						continue;
					}
				}
			} else {
				// if the class doesn't exist, we'll create it without confirming, otherwise ask the user
				if (class_exists($file['class'], false)) {
					if (!$this->confirm("{$file['class']} class already exists. Overwrite?", false)) {
						continue;
					}
				}
			}

			// if the destination path is a directory, copy the original file name
			if (empty($file['destination_filename'])) {
				$pathinfo = pathinfo($file['stub']);
				$fileName = $pathinfo['basename'];
			} else {
				$fileName = $file['destination_filename'];
			}

			DirManager::makeDirectoryIfNotExists($file['destination_path']);

			$filePath = $file['destination_path'] . DIRECTORY_SEPARATOR . $fileName;

			$this->files->copy($file['stub'], $filePath);
		}
	}

	protected function updateReadMeFile()
	{
		if (count($this->progressLog['instructions'])) {
			$title = '## Local Development Setup Instructions';
			$filePath = base_path('readme.md');

			try {
				if (FileManager::isTextInFile($filePath, $title)) return false;
			} catch (FileNotFoundException $ex) {
				$this->error('Readme.md file not found at ' . $filePath);
				return false;
			}

			$lines = [];
			$lines[] = "\r";
			$lines[] = $title;
			$lines[] = " ";

			for ($i = 0, $iMax = count($this->progressLog['instructions']); $i < $iMax; $i++) {
				$instruction = $this->progressLog['instructions'][$i];
				if (count($instruction) === 2) {
					$lines[] = "- `{$instruction[0]}` - {$instruction[1]}";
				} else {
					$lines[] = "- " . $instruction[0];
				}
			}

			$content = implode("\r\n", $lines);

			$result = $this->files->append($filePath, $content);

			$this->info("Readme.md file updated with build instructions.");
		}
	}

	protected function buildClass($name, $stubPath = null)
	{
		if (!$stubPath) $stubPath = $this->getStub();

		$stub = $this->files->get($stubPath);
		$this->replaceNamespace($stub, $name);

		return $stub;
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		return '';
	}

}
