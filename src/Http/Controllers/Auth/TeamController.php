<?php


namespace EMedia\Oxygen\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use EMedia\MultiTenant\Facades\TenantManager;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{

	protected $tenantRepository;

	/**
	 * Switch the Team (Tenant) to another one by Id
	 *
	 * @param $teamId
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function switchTeam($teamId)
	{
		if ( ! TenantManager::multiTenancyIsActive())
			return redirect()->to('dashboard')->with('error', 'Invalid request.');

		$this->tenantRepository = app(config('auth.tenantRepository'));
		$user = Auth::user();

		$tenant = $this->tenantRepository->getTenantByUser($user->id, $teamId);

		if (!empty($tenant)) {
			TenantManager::setTenant($tenant);
			// TODO: what if the user is not authenticated to view 'back' page's content?
			return redirect()->back();
		}

		return redirect()->back()->with('error', 'Invalid Team ID');
	}

}