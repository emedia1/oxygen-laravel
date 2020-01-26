<?php


namespace EMedia\Oxygen\Entities\Auth;

use EMedia\QuickData\Entities\Repositories\BaseRepository;

class RoleRepository extends BaseRepository
{

	public function __construct()
	{
		$model = app(config('oxygen.roleModel'));
		parent::__construct($model);
	}

	public function allExcept(array $exceptRoles, $reverse = false)
	{
		$query = $this->model->select();

		foreach ($exceptRoles as $role) {
			$query->where('name', '<>', $role);
		}

		if ($reverse) {
			$query->orderBy('id', 'desc');
		}

		return $query->get();
	}

	public function findByName($roleName)
	{
		return $this->model->where('name', $roleName)->first();
	}

	public function getAssignByDefaultRoles()
	{
		return $this->model->where('assign_by_default', true)->get();
	}

	public function exists($roleName)
	{
		$role = $this->model->where('name', $roleName)->first();
		return ($role)? true: false;
	}

	public function getNextSlug($entityName)
	{
		$roleName = str_slug($entityName);

		if ($this->exists($roleName)) {
			// already in DB, create a new one
			$nextRoleName = str_slug_next($roleName);

			if ($this->exists($nextRoleName)) {
				// TODO: remove the loop and optimise this logic
				for ($i = 0; $i < 250; $i++)
				{
					$nextRoleName = str_slug_next($nextRoleName);
					if (!$this->exists($nextRoleName)) return $nextRoleName;
				}
			}
		} else {
			return $roleName;
		}

		return false;
	}

	public function usersInRole($groupId, $onlyFirstResult = true)
	{
		$query = $this->model->select()->where('id', $groupId)->with('users');

		// if (count($except)) $query->whereNotIn('name', $except);
		if ($onlyFirstResult) return $query->first();

		return $query->get();
	}

	public function removeUser($role, $userId)
	{
		return $role->users()->detach($userId);
	}

}