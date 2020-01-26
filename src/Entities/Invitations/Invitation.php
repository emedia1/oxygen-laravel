<?php

namespace EMedia\Oxygen\Entities\Invitations;

use App\Entities\Auth\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
	protected $fillable = ['email'];

	public function getDates()
	{
		return array_merge(parent::getDates(), ['sent_at', 'claimed_at']);
	}

	public function getInvitationCodePermalinkAttribute()
	{
		return route('invitations.join', ['code' => $this->invitation_code]);
	}

	public function useInvite($code)
	{
		if ($code == $this->code)
		{
			$this->claimed_at = Carbon::now();
			$this->save();
			return true;
		}
		return false;
	}

	public function role()
	{
		return $this->belongsTo(Role::class);
	}
}
