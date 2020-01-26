<?php

namespace EMedia\Oxygen\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use EMedia\MultiTenant\Facades\TenantManager;

class LoginController extends Controller
{
	/*
	|--------------------------------------------------------------------------
	| Login Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles authenticating users for the application and
	| redirecting them to your home screen. The controller uses a trait
	| to conveniently provide its functionality to your applications.
	|
	*/

	use AuthenticatesUsers;

	/**
	 * Where to redirect users after login.
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
		$this->middleware('guest', ['except' => 'logout']);
	}

	public function showLoginForm()
	{
		if (view()->exists('auth.login')) {
			return view('auth.login');
		}

		return view('oxygen::auth.login');
	}

	protected function authenticated(Request $request, $user)
	{
		// see if this login is accepting any invitation tokens
		// if we have an incoming code, let the user join that team
		$invitationsRepo = app(config('oxygen.invitationRepository'));
		$tenantRepo		 = app(config('auth.tenantRepository'));
		$roleRepo		 = app(config('oxygen.roleRepository'));

		if ( ! empty($invitation_code = Session::get('invitation_code')) ) {
			$invite = $invitationsRepo->getValidInvitationByCode($invitation_code, true);
			if (!$invite)
				return redirect()
					->intended($this->redirectPath())
					->with('error', 'The invitation is already used or expired.');

			// see if you can get a valid tenant
			// if (($tenant = $tenantRepo->find($invite->tenant_id)) && !empty($invite->role_id)) {
			if (!empty($invite->role_id)) {
				// the RoleID should already be attached with the tenant

				if (TenantManager::multiTenancyIsActive()) {
					$tenant = $tenantRepo->find($invite->tenant_id);
					TenantManager::setTenant($tenant);
					$tenant->users()->attach($user->id);
				}

				$role = $roleRepo->find($invite->role_id);

				// attach tenant and the role
				$user->roles()->attach($role->id);

				return redirect()
					->intended($this->redirectPath())
					->with('success', 'You\'ve accepted the invitation and joined the team.');
			};
		}

		// if there are no invitations, proceed as usual
		return redirect()->intended($this->redirectPath());
	}
}
