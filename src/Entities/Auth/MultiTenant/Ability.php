<?php


namespace EMedia\Oxygen\Entities\Auth\MultiTenant;

use EMedia\MultiTenant\Scoping\Traits\TenantScopedModelTrait;
use Silber\Bouncer\Database\Ability as BouncerAbility;

class Ability extends BouncerAbility
{

	use TenantScopedModelTrait;

}