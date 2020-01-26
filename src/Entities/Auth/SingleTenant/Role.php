<?php

namespace EMedia\Oxygen\Entities\Auth\SingleTenant;

use Cviebrock\EloquentSluggable\Sluggable;
use Silber\Bouncer\Database\Role as BouncerRole;

class Role extends BouncerRole
{

	use Sluggable;

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