<?php

namespace EMedia\Oxygen\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

class ResetPasswordController extends Controller
{
	/*
	|--------------------------------------------------------------------------
	| Password Reset Controller
	|--------------------------------------------------------------------------
	|
	| This controller is responsible for handling password reset requests
	| and uses a simple trait to include this behavior. You're free to
	| explore this trait and override any methods you wish to tweak.
	|
	*/

	use ResetsPasswords;
	
	protected $redirectTo = '/dashboard';

	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct(Guard $auth, PasswordBroker $passwords)
	{
		$this->auth = $auth;
		$this->passwords = $passwords;

		$this->middleware('guest', ['except' =>
			[
				'editPassword', 'updatePassword'
			]
		]);
	}

	/**
	 * Display the password reset view for the given token.
	 *
	 * If no token is present, display the link request form.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  string|null  $token
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function showResetForm(Request $request, $token = null)
	{
		return view('oxygen::auth.passwords.reset')->with(
			['token' => $token, 'email' => $request->email]
		);
	}


	public function editPassword()
	{
		return view('oxygen::account.password-edit', [
			'user' => Auth::user(),
			'actionUrl' => route('account.password'),
		]);
	}

	/**
	 * Update the current logged-in user's password
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function updatePassword(Request $request)
	{
		$user = $this->auth->user();

		$this->validate($request, [
			'password'	=> 'required|confirmed|min:6',
			'current_password' => 'required',
		], [
			'password.required' => 'New password field is required.'
		]);

		// validate current password
		$isPasswordValid = $this->auth->attempt([
			'email'		=> $user->email,
			'password'	=> $request->get('current_password')
		]);

		if ( ! $isPasswordValid)
			return redirect()->back()->withErrors(['Current password is incorrect.']);

		// set the new password
		$user->password = bcrypt($request->get('password'));
		if ( ! $user->save())
			return redirect()->back()->withErrors(['Failed to save the new password. Try with another password.']);

		// TODO: inform the user their password has been changed

		return redirect()->back()->with('success', 'Password successfully updated.');
	}
}
