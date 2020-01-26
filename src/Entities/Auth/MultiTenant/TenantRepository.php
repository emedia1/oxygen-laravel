<?php

namespace EMedia\Oxygen\Entities\Auth\MultiTenant;

use App\Entities\BaseRepository;

class TenantRepository extends BaseRepository
{

	protected $model;

	public function __construct()
	{
		$model = app(config('auth.tenantModel'));
		parent::__construct($model);
		$this->model = $model;
	}

	public function getUserByTenant($userId, $tenantId)
	{
		$userModel = app(config('auth.model'));
		$query = $userModel::where('id', $userId)
			->whereHas('roles', function ($q) use ($tenantId) {
				$q->where('tenant_id', $tenantId);
			});

		return $query->first();
	}


	/**
	 * Check if a given UserID belongs to a TenantID
	 *
	 * @param $userId
	 * @param $tenantId
	 * @return Tenant, null
	 */
	public function getTenantByUser($userId, $tenantId)
	{
		$query = $this->model->where('id', $tenantId)
			->whereHas('users', function ($q) use ($userId) {
				$q->where('user_id', $userId);
			});
		return $query->first();
	}

}