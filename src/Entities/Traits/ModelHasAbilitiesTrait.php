<?php

namespace EMedia\Oxygen\Entities\Traits;

trait ModelHasAbilitiesTrait
{

	/**
	 * Get the Roles and Users who are allowed to access a given $permission for this model
	 *
	 * @param $permission
	 * @return array
	 */
	public function whoIsAllowedTo($permission)
	{
		$abilities = $this->abilities()->where('name', $permission)->get();
		$permissions = [];
		foreach ($abilities as $ability)
		{
			$permissions['roles'] = $ability->roles;
			$permissions['users'] = $ability->users;
		}
		return $permissions;
	}

	/**
	 * Query builder for abilities for this model (Similar to Permissions)
	 *
	 * @return mixed
	 */
	public function abilities()
	{
		$abilityModel = config('oxygen.abilityModel', '\App\Entities\Auth\Ability');

		return $this->morphMany($abilityModel, 'entity');
	}

}