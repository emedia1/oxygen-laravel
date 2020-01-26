<?php

namespace EMedia\Oxygen\Entities\Traits;

use App\Entities\Auth\Ability;
use App\Entities\Auth\Role;

use EMedia\Formation\Entities\GeneratesFields;
use EMedia\QuickData\Entities\Search\SearchableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Silber\Bouncer\Database\Models;

trait OxygenUserTrait
{

	use SoftDeletes;
	use SearchableTrait;
	use GeneratesFields;

	use HasRolesAndAbilities {
		HasRolesAndAbilities::isA as bouncerIsA;
	}

	// the field which are allowed to be edited from the dashboard by admin
	// overwrite the field or the getEditableFields() method to change the values
	protected $editable = [
		[
			'name' => 'name',
			'display_name' => 'First Name',
		],
		'last_name',
		'email',
	];

	/**
	 *
	 * Return the names of fields to be stuffed with random characters or to be made null.
	 * Overwrite this function if you have added custom fields to the user object.
	 *
	 * @return array
	 */
	protected function getStripPIIFieldNames(): array
	{
		return [
			'make_random' => [
				'last_name',
			],
			'nullable' => [
				'avatar_url',
				'avatar_path',
			]
		];
	}

	public function getProfileUpdatableFields()
	{
		return [
			'name',
		];
	}

	/**
	 *
	 * Get the user's full name
	 *
	 * @return string
	 */
	public function getFullNameAttribute(): string
	{
		return implode_not_empty(' ', [$this->name, $this->last_name]);
	}

	/**
	 *
	 * Proxy the name field as the first name
	 *
	 * @param $value
	 */
	public function setFirstNameAttribute($value)
	{
		$this->attributes['name'] = $value;
	}

	/**
	 *
	 * Return the name field as the first name
	 *
	 * @return mixed
	 */
	public function getFirstNameAttribute()
	{
		return $this->name;
	}

	/**
	 *
	 * Check if the user is not disabled
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return ($this->disabled_at === null);
	}

	/**
	 *
	 * Check if the user is disabled
	 *
	 * @return bool
	 */
	public function isDisabled()
	{
		return ($this->disabled_at !== null);
	}

	/**
	 *
	 * Check if the current logged in user is the same as this user
	 * (Helpful for admin functions)
	 *
	 * @return bool
	 */
	public function isMySelf(): bool
	{
		if (auth()->user()) return ($this->id === auth()->id());

		return false;
	}

	/**
	 *
	 *  Enable (Reactivate) a user
	 *
	 */
	public function enable()
	{
		$this->disabled_at = null;
		$this->disabled_by_user_id = null;
		$this->save();
	}

	/**
	 *
	 *  Disable (Deactivate) a user
	 *
	 */
	public function disable($disabledByUserId = null)
	{
		if (!$disabledByUserId) {
			if (auth()->user()) $disabledByUserId = auth()->id();
		}

		if ($disabledByUserId) {
			$this->disabled_by_user_id = $disabledByUserId;
		}

		$this->disabled_at = now();
		$this->setRememberToken(null);

		$this->save();
	}

	/**
	 *
	 * Strip any PII data and delete the user
	 * This is only meaningful if the users are soft-deleted.
	 *
	 * @param null $deletedByUserId
	 *
	 * @return mixed
	 */
	public function stripPIIDAndDelete($deletedByUserId = null)
	{
		if (!$deletedByUserId) {
			if (auth()->user()) $deletedByUserId = auth()->id();
		}

		if ($deletedByUserId) {
			$this->deleted_by_user_id = $deletedByUserId;
		}

		$this->stripPIIData();

		return $this->delete();
	}

	/**
	 *
	 * Strip PII data and anomymize the object.
	 *
	 * @return mixed
	 */
	public function stripPIIData()
	{
		$url = config('app.url');
		$url = parse_url($url);

		if (empty($url['host'])) {
			throw new \InvalidArgumentException("Cannot remove PII data without a URL. Have you set the APP_URL in the environment?");
		}

		$this->attributes['email'] = time() . str_random(25) . '_deleted@' . $url['host'];
		$this->password = bcrypt(str_random(35));
		$this->name = 'DELETED_' . str_random(10);

		$piiFieldNames = $this->getStripPIIFieldNames();

		if (!empty($piiFieldNames['make_random'])) {
			foreach ($piiFieldNames['make_random'] as $fieldName) {
				$this->$fieldName = str_random(10);
			}
		}

		if (!empty($piiFieldNames['nullable'])) {
			foreach ($piiFieldNames['nullable'] as $fieldName) {
				$this->$fieldName = null;
			}
		}

		return $this->save();
	}


	// use Authorizable;

	/**
	 * Override the Bouncer trait's is function and allow passing in an array of roles
	 * So you can send $user->isA(['admin', 'owner']) as well as $user->isA('admin', 'owner')
	 *
	 * @param $roles
	 * @return bool|mixed
	 */
	public function isA($roles)
	{

		if (is_array($roles)) {
			return call_user_func_array([$this, 'bouncerIsA'], $roles);
		}

		return $this->bouncerIsA($roles);
	}

	public function hasFirstName()
	{
		return (empty($this->name))? false: true;
	}

	/**
	 *  Setup model event hooks
	 */
	public static function boot()
	{
		parent::boot();
		self::creating(function ($model) {
			$model->uuid = (string) \Webpatser\Uuid\Uuid::generate(4);
		});
	}

}