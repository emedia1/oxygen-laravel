<?php

namespace EMedia\Oxygen\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait UpdatesUsers
{

	public function getProfile()
	{
		$user = Auth::user();

		if ($user) return view('oxygen::auth.profile', compact('user'));

		return Redirect::to('dashboard');
	}

	public function updateProfile(Request $request)
	{
		$validator = $this->updateValidator($request->all());

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$user = Auth::user();
		$user->fill($request->only($user->getProfileUpdatableFields()));
		$result = $user->save();

		if ($result) return redirect()->back()->with('success', 'Your profile has been updated.');

		return redirect()->back()->withErrors();
	}

	protected function updateValidator(array $data)
	{
		return Validator::make($data, [
			'name' => 'required|max:255'
		]);
	}

	public function getEmail()
	{
		$user = Auth::user();

		if ($user) return view('oxygen::auth.email', compact('user'));

		return Redirect::to('dashboard');
	}

	public function updateEmail(Request $request)
	{
		$user = Auth::user();

		$validator = $this->emailUpdateValidator($request->all(), $user);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		// validate the password
		if (!password_verify($request->input('password'), $user->password)) {
			return back()->with('error', "The current password is invalid.");
		}

		$user->fill($request->only([
			'email'
		]));
		$result = $user->save();

		// TODO: notify the user, their password has been updated

		if ($result) return redirect()->back()->with('success', 'Your email has been updated.');

		return redirect()->back()->withErrors();
	}

	protected function emailUpdateValidator(array $data, $user)
	{
		return Validator::make($data, [
			'email' => 'required|email|max:255|unique:users,email,' . $user->id,
			'password' => 'required'
		]);
	}

}