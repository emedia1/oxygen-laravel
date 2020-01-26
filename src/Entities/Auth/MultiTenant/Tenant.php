<?php

namespace EMedia\Oxygen\Entities\Auth\MultiTenant;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{

	protected $fillable = ['company_name'];

	public function users()
	{
		return $this->belongsToMany(config('auth.model'));
	}
}
