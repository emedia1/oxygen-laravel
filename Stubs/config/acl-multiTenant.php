<?php

return [

	'defaultRoles'	=> [
		[
			'name'				=> 'owner',		// role names - don't change this after creating a role
			'display_name' 		=> 'Owner',		// role label - change it to something meaningful
			'description'		=> 'Account Owner. Has all permissions.',
			'assignWhenRegister' => true,
		],
		[
			'name'				=> 'member',
			'display_name' 		=> 'Members',
			'description'		=> 'Account Members',
			'assignWhenRegister' => false,
		],
		[
			'name'				=> 'admin',
			'display_name' 		=> 'Admins',
			'description'		=> 'Account Admin. Can administer the account and Members.',
			'assignWhenRegister' => false,
		]
	],

	// site admin menus are only available for these roles
	'adminRoleNames'	=> ['admin', 'owner'],

	// these are the default roles assigned by the system. Users can't delete these roles
	'defaultRoleNames'	=> ['admin', 'owner', 'member'],

	'invitationRepo'	=> '\EMedia\Oxygen\Entities\Invitations\InvitationRepository',

];
