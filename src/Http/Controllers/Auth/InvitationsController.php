<?php

namespace EMedia\Oxygen\Http\Controllers\Auth;

use EMedia\MultiTenant\Facades\TenantManager;
use EMedia\Oxygen\Entities\Invitations\Invitation;
use EMedia\Oxygen\Entities\Invitations\InvitationRepository;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class InvitationsController extends Controller
{

    private $invitationsRepo;
    private $roleRepository;

    public function __construct(InvitationRepository $invitationsRepo)
    {
        $this->invitationsRepo = $invitationsRepo;
        $this->roleRepository  = app(config('oxygen.roleRepository'));

		$this->middleware('auth.acl:permissions[invite-group-users]', ['except' => [
			'showJoin', 'acceptInvite'
		]]);

    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $invitations = Invitation::orderBy('sent_at', 'desc')
        						 ->orderBy('claimed_at', 'desc')
        						 ->paginate(config('oxygen.dashboard.perPage', 50));
        $roles       = $this->roleRepository->allExcept(['owner']);
        return view('oxygen::account.invitations.invitations-all', compact('invitations', 'roles'));
    }

	public function create()
	{
		// reverse the order, so the default role to invite is (most likely) not the sys-admin.
		$roles = $this->roleRepository->allExcept(['owner'], true);

		return view('oxygen::account.invitations.invitations-create', compact('roles'));
	}

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function send(Request $request)
    {
        $this->validate($request, [
            'role_id'           => 'required|numeric',
            'invitation_emails' => 'required'
        ], [
            'role_id.required'              => 'Select a User Group for these invitations',
            'invitation_emails.required'    => 'Please add at least one valid email address'
        ]);

        // TODO: validate if the role is valid
        $role = $this->roleRepository->find($request->get('role_id'));

        // extract and validate emails
        $emails = $request->get('invitation_emails');
        $delimiters = [PHP_EOL, ';', ','];
        $emails = str_replace($delimiters, $delimiters[0], $emails);
        $emails = array_map('trim', explode(PHP_EOL, $emails));

        $validEmails   = [];
        $invalidEmails = [];
        foreach ($emails as $email) {
            if (!empty($email)) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validEmails[] = $email;
                } else {
                    $invalidEmails[] = $email;
                }
            }
        }

        // generate and send emails
        $user = Auth::user();
        $this->sendInvitations($user, $role, $validEmails);

        if (count($invalidEmails) > 0)
            return redirect()->back()
                ->withInput([
                    'invitation_emails' => implode("\r", $invalidEmails),
                    'success_emails'    => implode(", ", $validEmails)
                ])
                ->with('alert', 'Some emails are invalid. Please check them again.');

        return redirect()->back()->with('success', 'Hooray! The invitations will be sent shortly.');
    }

    protected function sendInvitations($user, $role, $emails)
    {
        // send invitations
        foreach ($emails as $email) {
            $invite = $this->invitationsRepo->createNewInvitation($email, $user, $role);

            if ($invite) $this->sendInvite($invite, $user);
        }
    }

    private function sendInvite($invite, $user)
    {
        // personalise subject line
        $subject = "You've got a Personal Invitation from a Friend";
        if ($user->hasFirstName())
        {
            $subject = $user->name . ' has Sent You a Personal Invitation';
        }

        $data = [
            'invite' => $invite->toArray(),
            'user'   => $user->toArray(),
            'email'  => $invite->email,
            'invitationLink' => $invite->invitation_code_permalink,
            'subject'=> $subject
        ];

        Mail::send('oxygen::emails.invitations.invitation_group', $data, function($message) use ($invite, $subject)
        {
            $message->to($invite->email)
                ->subject($subject);
        });

        $this->invitationsRepo->touchSentTime($invite);

        return true;
    }

    public function showJoin($code)
    {
        $invite = $this->invitationsRepo->getValidInvitationByCode($code);
        $plausibleUser = null;

        if ($invite)
        {
            // logout from any existing sessions
            // TODO: Confirm before logging out?
            // Auth::logout();
            // $data['invite'] = $invite;

            $prefilledEmail = $invite->email;

            // if user is logged-in, prompt to join team
            if ($user = Auth::user()) {
                if ($invite->email == $user->email) {
                    // join user to the team
                    if ($result = $this->acceptInvite($invite, $user))
                        return view('oxygen::account.invitations.invitations-join', compact('invite'));
                } else {
                    // this is an invite for someone else, logout and try again
                    Auth::logout();
                    $this->showJoin($code);
                }
            } else {
                Session::put('invitation_code', $invite->invitation_code);
                // if user doesn't have an account, prompt to register
                $userModel = app(config('auth.model'));
                $plausibleUser = $userModel::where('email', $invite->email)->first();
                if ($plausibleUser) {
                    // if user has an account, prompt to login
                    return view('oxygen::account.invitations.invitations-registerOrSignUp', compact('invite', 'plausibleUser', 'prefilledEmail'));
                }
            }

            // this is a new user, let her register
            // consider everything else as a new user (you should not get here)
            return view('oxygen::account.invitations.invitations-registerOrSignUp', compact('invite', 'plausibleUser', 'prefilledEmail'));
        }
        else
        {
            return redirect('/login')->with('error', 'The invitation is already used or expired. Please login, or register for a new account.');
        }
    }

    public function acceptInvite($invite, $user)
    {
        if (TenantManager::multiTenancyIsActive()) {
            // get the current tenant
            $currentTenant = TenantManager::getTenant();

            // set the tenant to new one, so we get the right Role
            TenantManager::setTenantById($invite->tenant_id);
        }

        $role = $this->roleRepository->find($invite->role_id);

        if (!$role) {
            // reset the old tenant
            if (TenantManager::multiTenancyIsActive()) TenantManager::setTenant($currentTenant);
            return false;
        }

        $user->roles()->attach($role->id);

        $this->invitationsRepo->claim($invite);
        Session::forget('invitation_code');

        return true;
    }

    public function destroy($id)
    {
        return ['result' => $this->invitationsRepo->delete($id)];
    }

}
