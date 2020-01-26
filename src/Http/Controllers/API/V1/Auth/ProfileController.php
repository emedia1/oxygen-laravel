<?php

namespace EMedia\Oxygen\Http\Controllers\API\V1\Auth;

use Storage;
use App\Entities\Auth\UsersRepository;
use App\Http\Controllers\API\V1\APIBaseController;
use EMedia\Api\Docs\APICall;
use EMedia\Api\Docs\Param;
use EMedia\Devices\Auth\DeviceAuthenticator;
use Illuminate\Http\Request;


class ProfileController extends APIBaseController
{

	/**
	 * @var UsersRepository
	 */
	protected $usersRepo;

	public function __construct(UsersRepository $usersRepo)
	{
		$this->usersRepo = $usersRepo;
	}

	/**
	 *
	 * Get currently logged-in user's profile
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index()
	{
		document(function () {
			return (new APICall)->setName('My Profile')
				->setDescription('Get currently logged in user\'s profile')
				->setSuccessExample('{
				"payload": {
					"id": 3,
					"uuid": "1ed343b7-73c7-4b03-af53-c13bd47246b0",
					"first_name": "Ms. Alf",
					"last_name": null,
					"email": "swift.theresa@gmail.com.au",
					"avatar_url": null,
					"first_name": "Ms. Alf",
					"full_name": "Ms. Alf"
				},
				"message": "",
				"result": true
			}');
		});

		$user = DeviceAuthenticator::getUserByAccessToken();

		return response()->apiSuccess($user);
    }

	/**
	 *
	 * Update user's profile
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function update(Request $request)
	{
		document(function () {
			return (new APICall)
			->setName('Update My Profile')
			->setParams([
				(new Param('first_name')),
				(new Param('last_name'))->optional(),
				(new Param('email')),
				(new Param('phone'))->optional(),
				// (new Param('_method'))->description("Must be set to `PUT`")->setDefaultValue('put'),
			])
			->setSuccessExample('{
				"payload": {
					"id": 3,
					"uuid": "11756f8a-9e4f-4c17-b1e6-2b860dd05f02",
					"first_name": "{{name}}",
					"last_name": "Johnson",
					"email": "fosinski@roodb.com.au",
					"phone": "06512345678",
					"avatar_url": "https://www.example.com/users/3/avatar.png",
					"first_name": "{{name}}",
					"full_name": "{{name}} Johnson"
				},
				"message": "",
				"result": true
			}');
		});

		$user = DeviceAuthenticator::getUserByAccessToken();

		$this->validate($request, [
			'first_name' => 'required',
			'email' => 'required|email|unique:users,email,' . $user->id,
		]);

		$user = $this->usersRepo->update($user, $request->only('first_name', 'last_name', 'email', 'phone'));

		return response()->apiSuccess($user);
    }

	/**
	 *
	 * Update user's profile picture (avatar)
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateAvatar(Request $request)
	{
		document(function () {
			return (new APICall)
			->setName('Update My Avatar')
			->setConsumes([APICall::CONSUME_MULTIPART_FORM])
			->setParams([
				(new Param('image'))->dataType('File'),
			])
			->setSuccessExample('{
				"payload": {
					"id": 3,
					"uuid": "11756f8a-9e4f-4c17-b1e6-2b860dd05f02",
					"first_name": "{{name}}",
					"last_name": "Johnson",
					"email": "fosinski@roodb.com.au",
					"phone": "06512345678",
					"avatar_url": "https://www.example.com/users/3/avatar.png",
					"first_name": "{{name}}",
					"full_name": "{{name}} Johnson"
				},
				"message": "",
				"result": true
			}');
		});

		$user = DeviceAuthenticator::getUserByAccessToken();

		$this->validate($request, [
			'image' => 'file|image|mimes:jpeg,png,gif',
		]);

		// save the file
        if($request->hasFile('image')) {
        	$diskName = 'public';
            $disk = Storage::disk($diskName);

            $path = $request->image->store('avatars/' . $user->id, $diskName);
            $url = $disk->url($path);

            $user->update([
            	'avatar_path' => $path,
            	'avatar_url' => $url,
            	'avatar_disk' => $diskName,
            ]);

            return response()->apiSuccess($user->fresh());
        }

		return response()->apiError('Avatar could not be saved.');
    }

}
