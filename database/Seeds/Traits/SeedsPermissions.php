<?php


namespace EMedia\Oxygen\Database\Seeds\Traits;


trait SeedsPermissions
{

	/**
	 *
	 * Assign given permissions (abilities) to a role and save
	 * Pass the roleNames as a string or an array.
	 *
	 * @param array|string $roleNames
	 * @param array $includeAbilityCategories
	 * @param array $blacklistedAbilityNames
	 * @param array $whitelistedAbilityNames
	 * @param bool  $deleteOldEntries
	 */
	private function assignPermissionsToRole($roleNames,
		$includeAbilityCategories = [],
		$blacklistedAbilityNames = [],
		$whitelistedAbilityNames = [],
		$deleteOldEntries = false,
		$slugFieldName = 'slug')
	{

		$roles = new \Illuminate\Database\Eloquent\Collection();
		if (is_array($roleNames)) {
			$roles = $this->getRolesByName($roleNames);
		} else {
			$roles->push($this->getRoleByName($roleNames));
		}

		$abilities = $this->getAbilities($includeAbilityCategories, $blacklistedAbilityNames, $whitelistedAbilityNames, $slugFieldName);

		foreach ($roles as $role) {
			$role->abilities()->sync($abilities, $deleteOldEntries);
		}
	}

	/**
	 *
	 * Get a role by a given name
	 *
	 * @param $roleName
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	private function getRoleByName($roleName, $fieldName = 'name')
	{
		$role = $this->roleModel->where($fieldName, $roleName)->first();

		if (empty($role))
			throw new \Exception("Role not found with the name {$roleName}");

		return $role;
	}

	/**
	 * @param        $roleNames
	 * @param string $fieldName
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	private function getRolesByName($roleNames, $fieldName = 'name')
	{
		$roles = $this->roleModel->whereIn($fieldName, $roleNames)->get();

		if ($roles->isEmpty())
			throw new \Exception("Roles not found for the given names " . implode(', ', $roleNames));

		return $roles;
	}

	/**
	 *
	 * Get abilities by a category, exclude some abilities, and whitelist some
	 *
	 * @param array $includeCategoryNames
	 * @param array $blacklistedAbilityNames
	 * @param array $whitelistedAbilityNames
	 *
	 * @return mixed
	 */
	private function getAbilities($includeCategoryNames = [],
		$blacklistedAbilityNames = [],
		$whitelistedAbilityNames = [], $slugFieldName = 'slug')
	{
		$query = $this->abilityModel->select();

		if (count($includeCategoryNames) === 0) {
			$includeCategoryNames = ['PLACEHOLDER_CATEGORY_NAME_FOR_EMPTY_RESULTS'];
		}
		$query->whereHas('category', function ($q) use ($includeCategoryNames, $slugFieldName) {
			$q->whereIn($slugFieldName, $includeCategoryNames);
		});

		// blacklist
		if (count($blacklistedAbilityNames) > 0) {
			$query->whereNotIn($slugFieldName, $blacklistedAbilityNames);
		}
		$includeAbilities = $query->get();

		$whitelistedAbilities = new \Illuminate\Database\Eloquent\Collection();
		if (count($whitelistedAbilityNames) > 0) {
			$whitelistedAbilities = $this->abilityModel->whereIn($slugFieldName, $whitelistedAbilityNames)->get();
		}

		return $includeAbilities->merge($whitelistedAbilities);
	}


}
