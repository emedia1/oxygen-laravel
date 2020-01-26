<?php

namespace App;

use EMedia\Oxygen\Entities\Traits\OxygenUserTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{

	use OxygenUserTrait;
	use Notifiable;

	protected $searchable = ['name', 'last_name', 'email'];

	protected $fillable = ['name', 'first_name', 'last_name', 'email', 'password'];
	protected $hidden   = ['name', 'password', 'remember_token', 'avatar_path', 'avatar_disk'];
	protected $visible  = ['id', 'uuid', 'first_name', 'last_name', 'full_name', 'email', 'avatar_url', 'avatar_path', 'avatar_disk'];
	protected $appends  = ['first_name', 'full_name'];


}
