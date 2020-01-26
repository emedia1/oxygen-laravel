<?php

namespace EMedia\Oxygen\Http\Controllers\Auth;

use App\User;
use EMedia\MultiTenant\Facades\TenantManager;
use EMedia\Oxygen\Exceptions\RegistrationsDisabledException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{
	/*
	|--------------------------------------------------------------------------
	| Register Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles the registration of new users as well as their
	| validation and creation. By default this controller uses a trait to
	| provide this functionality without requiring any additional code.
	|
	*/

	use RegistersUsers;

	/**
	 * Where to redirect users after login / registration.
	 *
	 * @var string
	 */
	protected $redirectTo = '/dashboard';

	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->middleware('guest');
	}

	public function showRegistrationForm()
	{
		if (!config('oxygen.registrationsEnabled', true)) {
			throw new RegistrationsDisabledException("Registrations are not allowed");
		}

		if (view()->exists('auth.register')) {
			return view('auth.register');
		}

		return view('oxygen::auth.register');
	}

	/**
	 * Get a validator for an incoming registration request.
	 *
	 * @param  array  $data
	 * @return \Illuminate\Contracts\Validation\Validator
	 */
	protected function validator(array $data)
	{
		return Validator::make($data, [
			'name' => 'required|max:255',
			'email' => 'required|email|max:255|unique:users',
			'password' => 'required|min:6|confirmed',
		]);
	}

	/**
	 * Create a new user instance after a valid registration.
	 *
	 * @param  array  $data
	 * @return User
	 */
	protected function create(array $data)
	{
		return User::create([
			'name' => $data['name'],
			'email' => $data['email'],
			'password' => bcrypt($data['password']),
		]);
	}


	/**
	 * Handle a registration request for the application.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function register(Request $request)
	{
		if (!config('oxygen.registrationsEnabled', true)) {
			throw new RegistrationsDisabledException("Registrations are not allowed");
		}

		$this->validator($request->all())->validate();
		$invitation_code = null;

		// if we have an incoming code, let the user join that team
		$invitationsRepo = app(config('oxygen.invitationRepository'));
		$tenantRepo		 = app(config('auth.tenantRepository'));
		$roleRepo		 = app(config('oxygen.roleRepository'));

		if ( ! empty($invitation_code = Session::get('invitation_code')) ) {
			$invite = $invitationsRepo->getValidInvitationByCode($invitation_code, true);
			if (!$invite)
				return redirect()
					->back()
					->withInput($request->except('password', 'confirm_password'))
					->with('error', 'The invitation is already used or expired. Please login or register for a new account.');
			if (TenantManager::multiTenancyIsActive()) $tenant = $tenantRepo->find($invite->tenant_id);
		} else {
			// create a tenant
			if (TenantManager::multiTenancyIsActive()) $tenant = $tenantRepo->create($request->all());
		}

		if (TenantManager::multiTenancyIsActive()) {
			TenantManager::setTenant($tenant);

			// create a user and attach to tenant
			$user = $this->create($request->all());
			$tenant->users()->attach($user->id);
		} else {
			$user = $this->create($request->all());
		}

		// assign this user as the admin of the tenant
		if ( ! empty($invite->role_id) ) {
			$user->roles()->attach($invite->role_id);
			// since the tenant is set now, we can retrieve the correct invitation as Eloquent
			$invite = $invitationsRepo->getValidInvitationByCode($invitation_code);
			$invitationsRepo->claim($invite);
			Session::flash('success', 'Your account has been created and you\'ve accepted the invitation');
		} else {
			// add the default Roles
			$defaultRoles = $roleRepo->getAssignByDefaultRoles();
			foreach ($defaultRoles as $role) {
				// create the default roles if they don't exist
				// this can be Seeded for single-tenants, but required in multi-tenancy
				/*$role = $roleRepo->findByName($defaultRole['name']);
				if (!$role) {
					$role = $roleRepo->newModel();
					$role->fill($defaultRole);
					$role->name = $defaultRole['name'];
					$role->save();
				}*/

				// add this role when the user registers
				$user->roles()->attach($role->id);
			}
			Session::flash('success', 'Your account has been created and you\'re now logged in.');
		}

		event(new Registered($user));
		$this->guard()->login($user);

		return $this->registered($request, $user)
			?: redirect($this->redirectPath());
	}
}
