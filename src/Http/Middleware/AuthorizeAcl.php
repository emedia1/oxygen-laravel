<?php

namespace EMedia\Oxygen\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;

class AuthorizeAcl
{
	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Handle an incoming request.
	 * Pass the roles in this format
	 *
	 * eg.
	 * auth.acl:roles[owner|admin],permissions[do-something]
	 * auth.acl:roles[owner|admin]
	 * auth.acl:permissions[do-something]
	 * auth.acl:permissions[do-something|do-another-thing]	// requires both permissions
	 * auth.acl:permissions[do-something OR do-something-else] // requires at least one permission
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 *
	 * @return mixed
	 */
	public function handle($request, Closure $next, $aclRules = null, $aclPermissions = null)
	{
		$aclRoles = null;
		$aclPermissionsWhitelist = null;

		$user = $this->auth->user();
		if (!$user) return $this->unauthorizedResponse($request);

		// the user can pass 2, 3 or 4 args
		// 2 args - No check -> pass through
		// 3 args - Check either role or permission
		// 4 args - Check both role and permission
		$argCount = func_num_args();

		if ($argCount === 4) {
			// check both role and permission

			// user must be in at least one role
			$aclRoles = $this->splitRules('roles', $aclRules);

			// user must have at least ONE of the given permissions
			$aclPermissionsWhitelist = $this->splitRulesWhitelist('permissions', $aclPermissions);

			// user must have ALL the given permissions to access the resource
			$aclPermissions = $this->splitRules('permissions', $aclPermissions);

		} else if ($argCount === 3) {
			// check either roles or permissions

			if (strpos($aclRules, 'roles') === 0) {
				// this is a roles check
				$aclRoles = $this->splitRules('roles', $aclRules);
			} else if (strpos($aclRules, 'permissions') === 0) {
				// user must have at least ONE of the given permissions
				$aclPermissionsWhitelist = $this->splitRulesWhitelist('permissions', $aclRules);
				// this is a permission check
				$aclPermissions = $this->splitRules('permissions', $aclRules);
			} else {
				throw new \InvalidArgumentException("Unable to parse ACL rule {$aclRules}");
			}
		}

		// authorize roles
		if ($aclRoles) {
			if (!$user->isA($aclRoles)) return $this->unauthorizedResponse($request);
		}


		// authorize permissions
		// if we have a whitelist, if at least 1 rule matches, let the user pass
		$isUserWhitelisted = false;
		if ($aclPermissionsWhitelist && count($aclPermissionsWhitelist) > 0) {
			foreach ($aclPermissionsWhitelist as $whitelistedPermission) {
				if ($user->can($whitelistedPermission)) $isUserWhitelisted = true;
			}
		}

		// check all permissions, if the user is not whitelisted
		if (!$isUserWhitelisted && $aclPermissions) {
			foreach ($aclPermissions as $permission) {
				if ($user->cannot($permission)) {
					return $this->unauthorizedResponse($request);
				}
			}
		}

		return $next($request);
	}

	/**
	 *
	 * Give a rule and extract the separate rules
	 * eg.
	 * $rule = permissions[view_dashboard|edit_dashboard]
	 *
	 * returns ['view_dashboard', 'edit_dashboard'] or null
	 *
	 * @param $group
	 * @param $rule
	 *
	 * @return array
	 */
	private function splitRules($group, $rule)
	{
		$rules = null;

		if (strpos($rule, ',') !== false)
			throw new \InvalidArgumentException("Invalid ',' character in ACL rule.");

		$matchCount = preg_match_all('/^' . $group . '\[(.*)\]$/i', $rule, $matches);
		if ($matchCount && count($matches[1])) {
			$rules = explode('|', $matches[1][0]);
		}

		return $rules;
	}

	private function splitRulesWhitelist($group, $rule)
	{
		$rules = null;

		if (strpos($rule, ',') !== false)
			throw new \InvalidArgumentException("Invalid ',' character in ACL rule.");

		$matchCount = preg_match_all('/^' . $group . '\[(.*)\]$/i', $rule, $matches);
		if ($matchCount && count($matches[1])) {
			$rules = explode(' OR ', $matches[1][0]);
		}

		return $rules;
	}

	private function unauthorizedResponse($request)
	{
		if ($request->ajax()) {
			return response('Unauthorized.', 401);
		} else {
			return redirect('/')->with('error', trans('auth.invalid-permissions')) ;
		}
	}
}
