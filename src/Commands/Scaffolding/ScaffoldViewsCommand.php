<?php


namespace EMedia\Oxygen\Commands\Scaffolding;


use EMedia\PHPHelpers\Exceptions\FileSystem\FileNotFoundException;
use EMedia\PHPHelpers\Files\DirManager;
use EMedia\PHPHelpers\Files\FileManager;
use Illuminate\Console\Command;
use Illuminate\View\ViewFinderInterface;

class ScaffoldViewsCommand extends Command
{

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'scaffold:views
								{--path= : View resource path}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Scaffold the default views for a resource';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$this->createViews();
	}

	protected function createViews()
	{
		// get resource path eg: manage.users, oxygen::manage.users

		$path = $this->option('path');

		if (empty($path)) {
			$path = $this->ask("What is the view resource path, excluding final view name? (Example: manage.users)");
		}

		if (empty($path)) {
			$this->error("Need a resource path to continue");
			return;
		}

		$delimiter = ViewFinderInterface::HINT_PATH_DELIMITER;

		$vendor = explode($delimiter, $path);
		if (count($vendor) > 1) {
			$vendorName = $vendor[0];
			$path = $vendor[1];
			$resourcePath = resource_path('views' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $vendorName);
		} else {
			$resourcePath = resource_path('views');
		}

		$directories = explode('.', $path);
		foreach ($directories as $directory) {
			$resourcePath .= DIRECTORY_SEPARATOR . $directory;
		}
		DirManager::makeDirectoryIfNotExists($resourcePath);

		// create the files
		foreach ($this->getStubs() as $key => $stub) {
			if (!file_exists($stub)) {
				throw new FileNotFoundException("Required file {$stub} was not found.");
			}

			$destinationFilePath = $resourcePath . DIRECTORY_SEPARATOR . "$key.blade.php";
			if (file_exists($destinationFilePath)) {
				if (!$this->confirm("View {$destinationFilePath} already exists. Overwrite?", false)) {
					$this->error('View already exists. Skipping...');
					continue;
				}
			}

			if (copy($stub, $destinationFilePath)) {
				$this->info("View created - {$destinationFilePath}");
			} else {
				$this->error("Failed to create - {$destinationFilePath}");
			}
		}
	}

	protected function getStubs()
	{
		return [
			'index' => __DIR__ . '/../../../resources/views/defaults/allItems-index.blade.php',
			'form'  => __DIR__ . '/../../../resources/views/defaults/formation-form.blade.php',
		];
	}

}