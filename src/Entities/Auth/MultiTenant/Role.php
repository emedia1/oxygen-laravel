<?php

namespace EMedia\Oxygen\Entities\Auth\MultiTenant;

use Cviebrock\EloquentSluggable\Sluggable;
use EMedia\MultiTenant\Scoping\Traits\TenantScopedModelTrait;
use Silber\Bouncer\Database\Role as BouncerRole;

class Role extends BouncerRole
{

	use Sluggable;
	use TenantScopedModelTrait;

	protected $fillable = ['title', 'description'];

	public function sluggable()
	{
		return [
			'name' => [
				'source' => 'title'
			]
		];
	}

}