<?php

namespace EMedia\Oxygen\Http\Controllers\Auth\Groups;

use App\User;
use EMedia\MultiTenant\Facades\TenantManager;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class GroupsController extends Controller
{

	protected $roleRepository;
	protected $tenantRepository;

	/**
	 * @param Guard $auth
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
		$this->roleRepository = app(config('oxygen.roleRepository'));
		if (TenantManager::multiTenancyIsActive()) $this->tenantRepository = app(config('auth.tenantRepository'));

		$this->middleware('auth.acl:permissions[view-groups]', ['only' => [
			'index'
		]]);

		$this->middleware('auth.acl:permissions[view-group-users]', ['only' => [
			'showUsers'
		]]);

		$this->middleware('auth.acl:permissions[edit-group-users]', ['only' => [
			'storeUsers'
		]]);

		$this->middleware('auth.acl:permissions[add-groups]', ['only' => [
			'create', 'store'
		]]);

		$this->middleware('auth.acl:permissions[edit-groups]', ['only' => [
			'edit', 'update'
		]]);

		$this->middleware('auth.acl:permissions[delete-groups]', ['only' => [
			'destroy', 'destroyUser'
		]]);
	}


	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$roles  = $this->roleRepository->all();
		if (TenantManager::multiTenancyIsActive()) {
			$tenant = TenantManager::getTenant();
			$users  = $tenant->users;
		} else {
			$users = User::all();
		}

		$rolesData = [];
		$availableRoles = [];
		foreach ($roles as $role)
		{
			$roleData = $role->toArray();
			$roleData['description'] = Str::words($role->description, 50);
			// $roleData['user_count']  = $role->users()->count;	// TODO: fix query
			$rolesData[] = $roleData;
			if ($role->name != 'owner') {
				$availableRoles[] = $roleData;
			}
		}

		$pageTitle = 'Manage Groups';

		return view('oxygen::groups.groups-all', compact('rolesData', 'availableRoles', 'users', 'pageTitle'));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		$role = $this->roleRepository->newModel();
		return view('oxygen::groups.groups-new', ['mode' => 'new', 'role' => $role]);
	}



	public function validationCriteria()
	{
		$data['rules'] = [
			'title' => 'required'
		];

		$data['messages'] = [
			'title.required' => 'An User Group Name is required'
		];
		return $data;
	}

	public function redirectWithError($message)
	{
		return redirect()->back()->with('error', $message);
	}

	public function redirectWithSuccess($message)
	{
		return redirect()->back()->with('success', $message);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		$validationCriteria = $this->validationCriteria();
		$this->validate($request, $validationCriteria['rules'], $validationCriteria['messages']);

		// TODO: this must have a unique slug

		$roleName = $this->roleRepository->getNextSlug($request->get('title'));

		$role   = $this->roleRepository->newModel();
		$role->fill($request->all());
		$result = $role->save();

		return redirect('/account/groups')->with('success', 'The group ' . $role->title . ' has been created.');
	}

	/**
	 *
	 * Store Users for given Roles and return a JSON response.
	 *
	 * @param Request $request
	 *
	 * @return array
	 */
	public function storeUsers(Request $request)
	{
		$roleIds = $request->get('selectRoles');
		$userIds = $request->get('selectUsers');

		foreach ($roleIds as $roleId) {
			$role = $this->roleRepository->find($roleId);
			if ($role) {
				foreach ($userIds as $userId) {
					// the user should already be in some team for this tenant
					if (TenantManager::multiTenancyIsActive()) {
						$tenant  = TenantManager::getTenant();
						$savedUser = $this->tenantRepository->getUserByTenant($userId, $tenant->id);
					} else {
						$savedUser = User::find($userId);
					}

					if ($savedUser) {
						// if already in group, ignore the request
						if ($savedUser->isAn($role->name)) continue;
					}

					// add the user to role
					$role->users()->attach($userId);
				}
			}
		}

		// for testing: return an error to the user
		// return response(['message' => 'something'], 404);

		return [
			'result'	=> 'success'
		];
	}

	public function showUsers($groupId)
	{
		$role = $this->roleRepository->usersInRole($groupId);

		if (!$role) return redirect()->route('account')->with('error', 'Invalid group request.');

		$availableRoles = $this->roleRepository->allExcept(['owner'])->toArray();

		if (TenantManager::multiTenancyIsActive()) {
			$tenant = TenantManager::getTenant();
			$users = $tenant->users;
		} else {
			$users = User::all();
		}

		$pageTitle = "Users in '{$role->title}' Group";

		return view('oxygen::groups.group-users-all', compact('role', 'users', 'availableRoles', 'pageTitle'));
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit($id)
	{
		$role = $this->roleRepository->find($id);
		$mode = 'edit';

		return view('oxygen::groups.groups-edit', compact('role', 'mode'));
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id)
	{
		$validationCriteria = $this->validationCriteria();
		$this->validate($request, $validationCriteria['rules'], $validationCriteria['messages']);

		$role = $this->roleRepository->find($id);
		if (!$role) return $this->redirectWithError('Invalid user group.');

		$role->fill($request->all());
		$result = $role->save();

		return redirect('/account/groups')->with('success', 'The group has been updated.');
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id)
	{
		$role = $this->roleRepository->find($id);

		// user can't delete default roles
		if (!$role->allow_to_be_deleted) {
			return $this->redirectWithError($role->title . ' is a default role, and cannot be deleted.');
		}

		if ($role) {
			$role->delete();
			return $this->redirectWithSuccess('User Group deleted.');
		}

		return $this->redirectWithError('Invalid user group.');
	}

	public function destroyUser($roleId, $userId)
	{
		$role = $this->roleRepository->find($roleId);

		if (!$role) return $this->redirectWithError('Invalid user group.');

		// don't delete the last super-admin or admin - because we'll lose account admin access
		// last account owner can't leave the role
		if (in_array($role->name, ['super-admin', 'admin'])) {
			$users = $role->users;
			if (count($users) <= 1)
				return $this->redirectWithError('The last member of the group ' . $role->name . ' cannot leave the role.');
		}

		$result = $this->roleRepository->removeUser($role, $userId);

		if ($result) {
			return $this->redirectWithSuccess('User removed from group.');
		}

		return $this->redirectWithError('Failed to remove user. Please try again.');
	}

}
