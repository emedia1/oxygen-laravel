<?php


namespace EMedia\Oxygen\Entities\Invitations;

use Carbon\Carbon;
use EMedia\MultiTenant\Facades\TenantManager;
use EMedia\QuickData\Entities\Repositories\BaseRepository;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class InvitationRepository extends BaseRepository
{

	protected $model;

	public function __construct(Invitation $model)
	{
		parent::__construct($model);
		$this->model = $model;
	}

	public function getValidInvitationByCode($invitation_code, $allTenants = false)
	{
		return Invitation::where('invitation_code', $invitation_code)
				->whereNull('claimed_at')
				->first();
	}

	public function getValidInvitationByEmail($email)
	{
		return Invitation::where('email', $email)
			->whereNull('claimed_at')
			->first();
	}

	public function deleteInvitation($id, $team)
	{
		return Invitation::where('team_id', $team->id)
			->where('id', $id)
			->delete();
	}

	public function requestNewInvitation($inviteEmail)
	{
		return Invitation::create(['email' => $inviteEmail]);
	}

	public function createNewInvitation($inviteEmail = null, $user = null, $role, $senderName = null, $team = null)
	{
		$inviteEmail = trim($inviteEmail);
		$tenant 	 = null;
		if (empty($inviteEmail)) return false;

		if (TenantManager::multiTenancyIsActive()) {
			$tenant = TenantManager::getTenant();
		}

		// if there's already an invite for this user, return that
		$query = Invitation::where('email', trim($inviteEmail));
		// if ($team) $query->where('team_id', $team->id);
		// if ($user) $query->where('referrer_id', $user->id);
		if ($tenant) $query->where('tenant_id', $tenant->id);
		$invite = $query->first();

		if ( (!$invite) || empty($invite->invitation_code))
		{
			$invitationCode = $this->generateUniqueInvitationCode();
			if ( ! empty($invitationCode))
			{
				$invite = new Invitation();
				$invite->email 	= $inviteEmail;
				$invite->invitation_code 	= $invitationCode;
				$invite->role_id = $role->id;
				// if ($senderName) $invite->first_name = $senderName;
				if ($tenant) $invite->tenant_id 	= $tenant->id;
				if ($user)   $invite->referrer_id 	= $user->id;

				// use Laravel method instead of REMOTE_ADDR, useful if we need a load balancer in the future
				$invite->referrer_ip = Request::getClientIp();
				$invite->save();
			}
			else
			{
				// unable to generate a unique invitation invitation_code
				return false;
			}
		}

		return $invite;
	}


	public function generateNewInvitationCode()
	{
		return date('Ymd') . Str::random(40);
	}

	public function checkCode($invitation_code)
	{
		$invite = Invitation::where('invitation_code', $invitation_code)->first();
		if ($invite)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function generateUniqueInvitationCode()
	{
		$invitationCode = self::generateNewInvitationCode();

		// check for duplicate invitation codes and avoid collisions
		$codeExists = false;
		for ($i = 0; $i < 100; $i++)
		{
			$codeExists = self::checkCode($invitationCode);
			if ($codeExists)
			{
				$invitationCode = self::generateNewInvitationCode();
			}
			else
			{
				break;
			}
		}

		// if the code exists, return false, otherwise the code
		return ($codeExists)? false : $invitationCode;
	}

	public function touchSentTime(Invitation $invite)
	{
		$invite->sent_at = Carbon::now();
		$invite->save();
	}

	public function claim(Invitation $invite)
	{
		$invite->claimed_at = Carbon::now();
		$invite->save();
	}

}