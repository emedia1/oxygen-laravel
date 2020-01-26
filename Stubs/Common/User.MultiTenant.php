<?php

namespace App;

use EMedia\MultiTenant\Auth\MultiTenantUserTrait;
use EMedia\Oxygen\Entities\Traits\OxygenUserTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{

	use MultiTenantUserTrait, OxygenUserTrait {
		OxygenUserTrait::roles insteadof MultiTenantUserTrait;
	}
	use Notifiable;

	protected $fillable = ['name', 'email', 'password'];
	protected $hidden   = ['password', 'remember_token'];
	protected $appends  = ['full_name'];

}
