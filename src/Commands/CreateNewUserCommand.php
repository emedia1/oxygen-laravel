<?php

namespace EMedia\Oxygen\Commands;

use Illuminate\Console\Command;

class CreateNewUserCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'setup:create-user';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new user for Oxygen backend';

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
		$this->createNewUser();
	}

	private function createNewUser()
	{
		$email = $this->ask("What's the email?", 'info@elegantmedia.com.au');

		$model = app(config('auth.providers.users.model'));
		$user = $model->where('email', $email)->first();

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->error('The email is not valid');
			return false;
		}

		if (!$user) {
			$user = new $model();

			$firstName = $this->ask("What's the first name?");
			$password  = $this->ask("Enter a password for this user");
			$confirmPassword = $this->ask("Enter the password again to confirm");

			if ($password !== $confirmPassword) {
				$this->error("Passwords do not match");
				return false;
			}

			$user->email = $email;
			$user->name = $firstName;
			$user->password = bcrypt($password);
			$user->save();
		}

		if ($this->confirm("Add this user to a role?", false)) {
			$rolesRepo = app(config('oxygen.roleRepository'));

			$roles = $rolesRepo->allExcept([]);

			if ($roles->count()) {
				foreach ($roles as $role) {
					$this->info($role->name);
				}
				$roleSlug = $this->ask("What's the slug of the role to attach?");

				$role = $rolesRepo->findByName($roleSlug);

				if (!$role) {
					$this->error("Unable to find that role");
					return false;
				}

				if ($user->isAn($role->name)) {
					$this->info("The user is already in this role.");
				} else {
					$role->users()->attach($user->id);
					$this->info('Role attached.');
				}
			} else {
				$this->error("No roles found. Seed some roles first");
			}

		}
	}
}
