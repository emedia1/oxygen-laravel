<?php


namespace EMedia\Oxygen\Http\Controllers\Auth;


use App\Entities\Auth\UsersRepository;
use App\Http\Controllers\Controller;
use App\User;
use EMedia\Formation\Builder\Formation;
use Illuminate\Http\Request;

class UsersController extends Controller
{

	/**
	 * @var UsersRepository
	 */
	protected $usersRepo;

	protected $indexRoute;

	public function __construct(UsersRepository $usersRepo)
	{
		$this->usersRepo = $usersRepo;

		$this->indexRoute = route('manage.users.index');
	}

	/**
	 *
	 * Show all users
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		return view('oxygen::manage.users.index', [
			'pageTitle' => 'Manage Users',
			'allItems' => $this->usersRepo->search(),
		]);
	}

	/**
	 *
	 * Don't create users
	 *
	 */
	public function create()
	{
		// Don't Create Users from Here.
		// If users needs to be added, use the 'Invitations' feature, or let them register.
	}

	/**
	 *
	 * Don't store users
	 *
	 */
	public function store(Request $request)
	{
		// Don't Create Users from Here.
		// If users needs to be added, use the 'Invitations' feature, or let them register.
	}


	/**
	 *
	 * Edit a user
	 *
	 * @param  \App\User  $user
	 * @return \Illuminate\Http\Response
	 */
	public function edit(User $user)
	{
		return view('oxygen::manage.users.edit', [
			'pageTitle' => "Edit User - `{$user->full_name}`",
			'entity' => $user,
			'form' => new Formation($user),
		]);
	}


	/**
	 *
	 * Update a user
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \App\User  $user
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, User $user)
	{
		$this->validate($request, [
			'name' => 'required',
			'email' => 'required|email|unique:users,email,' . $user->id,
		]);

		$data = $request->only(['name', 'last_name', 'email']);

		$user->update($data);

		return redirect($this->indexRoute)->with('success', 'User updated.');
	}


	/**
	 *
	 * Show edit password form
	 *
	 * @param User $user
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function editPassword(User $user)
	{
		return view('oxygen::manage.users.edit-password', [
			'pageTitle' => "Edit Password for `{$user->full_name}`",
			'entity' => $user,
			'actionUrl' => route('manage.users.edit-password', $user->id)
		]);
	}


	/**
	 *
	 * Update the another user's password
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function updatePassword(User $user, Request $request)
	{
		$this->validate($request, [
			'password'	=> 'required|confirmed|min:6',
			'current_password' => 'required',
		], [
			'password.required' => 'New password field is required.'
		]);

		// validate current password
		$admin = auth()->user();
		$isPasswordValid = auth()->attempt([
			'email'		=> $admin->email,
			'password'	=> $request->get('current_password')
		]);

		if (!$isPasswordValid)
			return redirect()->back()->with('error', 'Your current password is incorrect.');

		// set the new password
		$user->password = bcrypt($request->get('password'));
		$user->setRememberToken(null);
		if (!$user->save()) {
			return redirect()->back()->with('error', 'Failed to save the new password. Try with another password.');
		}

		$this->resetDeviceAccessTokensByUser($user);

		return redirect()->back()->with('success', 'Password successfully updated.');
	}

	/**
	 *
	 * If there are devices attached to this user, reset their access tokens, so the user will be forced to login again.
	 *
	 * @param User $user
	 */
	protected function resetDeviceAccessTokensByUser(User $user): void
	{
		if (class_exists('\EMedia\Devices\Entities\Devices\DevicesRepository', false)) {
			/** @var \EMedia\Devices\Entities\Devices\DevicesRepository $devicesRepo */
			$devicesRepo = app('\EMedia\Devices\Entities\Devices\DevicesRepository');
			$devicesRepo->resetAccessTokensByUserId($user->id);
		}
	}

	/**
	 *
	 * Toggle the disabled/enabled status
	 *
	 * @param User    $user
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function updateDisabled(User $user, Request $request)
	{
		if ($request->action === 'enable') {
			return $this->enable($user);
		}

		return $this->disable($user);
	}

	/**
	 *
	 * Disable a user
	 *
	 * @param User $user
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	protected function disable(User $user)
	{
		if ($user->id === auth()->id()) {
			return back()->with('error', 'You cannot disable your own account.');
		}

		if ($user->isDisabled()) {
			return back()->with('error', 'Account is already disabled');
		}

		$user->disable();
		$this->resetDeviceAccessTokensByUser($user);

		return back()->with('success', 'Account disabled.');
	}

	/**
	 *
	 * Enable a user
	 *
	 * @param User $user
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	protected function enable(User $user)
	{
		if ($user->isEnabled()) {
			return back()->with('error', 'Account is already enabled.');
		}

		$user->enable();

		return back()->with('success', 'Account enabled.');
	}

	/**
	 *
	 * Delete a user
	 *
	 * @param  \App\User  $user
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(User $user)
	{
		if ($user->id === auth()->id()) {
			return back()->with('error', 'You cannot delete your own account.');
		}

		$this->resetDeviceAccessTokensByUser($user);

		if ($user->stripPIIDAndDelete()) {
			return redirect($this->indexRoute)->with('success', 'User successfully deleted.');
		}

		return redirect($this->indexRoute)->with('error', 'Something went wrong while deleting user.');
	}

}
