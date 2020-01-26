<?php


namespace EMedia\Oxygen\Http\Controllers\API\V1\Auth;


use App\Entities\Auth\UsersRepository;
use App\Http\Controllers\API\V1\APIBaseController;
use EMedia\API\Docs\APICall;
use EMedia\API\Docs\Param;
use EMedia\Devices\Auth\DeviceAuthenticator;
use EMedia\Devices\Entities\Devices\DevicesRepository;
use Illuminate\Http\Request;

class AuthController extends APIBaseController
{

	/**
	 * @var UsersRepository
	 */
	protected $usersRepository;
	/**
	 * @var DevicesRepository
	 */
	protected $devicesRepo;

	public function __construct(UsersRepository $usersRepository, DevicesRepository $devicesRepo)
	{
		$this->usersRepository = $usersRepository;
		$this->devicesRepo = $devicesRepo;
	}


	/**
	 *
	 * Sign up a user
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function register(Request $request)
	{
		document(function () {
			return (new APICall)->setName('Register')
				->setParams([
					(new Param('device_id', 'String', 'Unique ID of the device')),
					(new Param('device_type', 'String', 'Type of the device `APPLE` or `ANDROID`')),
					(new Param('device_push_token', 'String', 'Unique push token for the device'))->optional(),

					(new Param('first_name'))->setDefaultValue('Joe')->optional(),
					(new Param('last_name'))->setDefaultValue('Johnson')->optional(),
					(new Param('phone'))->optional(),
					(new Param('email')),

					(new Param('password', 'string',
						'Password. Must be at least 6 characters.'))->setDefaultValue('123456'),
					(new Param('password_confirmation'))->setDefaultValue('123456'),
				])
				->noDefaultHeaders()
				->setHeaders([
					(new Param('Accept', 'String', '`application/json`'))->setDefaultValue('application/json'),
					(new Param('x-api-key', 'String', 'API Key'))->setDefaultValue('123-123-123-123'),
				])
				->setErrorExample('{
					"message": "The email must be a valid email address.",
					"payload": {
						"errors": {
							"email": [
								"The email must be a valid email address."
							]
						}
					},
					"result": false
				}', 422);
		});

		$this->validate($request, [
			// 'first_name' => 'required',
			// 'last_name'  => 'required',
			'email'      => 'required|email|unique:users,email',
			'password'   => 'required|confirmed',

			'device_id'   => 'required',
			'device_type' => 'required',
		]);

		$data = $request->only(['first_name', 'last_name', 'email', 'password', 'phone']);
		$data['password'] = bcrypt($data['password']);
		$user = $this->usersRepository->create($data);

		$responseData = $user->toArray();
		$deviceData = $request->only(['device_id', 'device_type', 'device_push_token']);
		$device = $this->devicesRepo->createOrUpdateByIDAndType($deviceData, $user->id);
		$responseData['access_token'] = $device->access_token;

		return response()->apiSuccess($responseData);
	}


	/**
	 *
	 * Login to the API and get access token
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function login(Request $request)
	{
		document(function () {
			return (new APICall())->setName('Login')
				->setParams([
					(new Param('device_id', 'String', 'Unique ID of the device')),
					(new Param('device_type', 'String', 'Type of the device `APPLE` or `ANDROID`')),
					(new Param('device_push_token', 'String',
					  'Unique push token for the device'))->optional(),

					(new Param('email'))->setDefaultValue('test@example.com'),
					(new Param('password'))->setDefaultValue('123456'),
				])
				->noDefaultHeaders()
				->setHeaders([
					(new Param('Accept', 'String', '`application/json`'))->setDefaultValue('application/json'),
					(new Param('x-api-key', 'String', 'API Key'))->setDefaultValue('123-123-123-123'),
				])
				->setSuccessExample('{
					"payload": {
						"id": 31,
						"uuid": "6dfb4b23-df73-49d1-90ab-a3118cc170ed",
						"name": "null",
						"last_name": "null",
						"email": "test@example.com",
						"avatar_url": null,
						"first_name": "null",
						"full_name": "null null",
						"access_token": "1540054802BbiqclNMqujaIgfGzRMjsdds8a9M4HvBxPg"
					},
					"message": "",
					"result": true
				}');
		});

		$this->validate($request, [
			'device_id' => 'required',
			'device_type' => 'required',
			'email' => 'required|email',
			'password' => 'required',
		]);

		if (!auth()->attempt($request->only('email', 'password'), true)) {
			return response()->apiErrorUnauthorized('Invalid login credentials. Try again.');
		}

		$user = auth()->user();
		$response = $user->toArray();
		$device = $this->devicesRepo->findByDeviceForUser($user->id, $request->get('device_id'));

		// return an existing device
		if ($device) {
			// reset the push token and access tokens
			// because someone else could be logging in from the same device
			if ($request->device_push_token && ($device->device_push_token !== $request->device_push_token)) {
				$device->device_push_token = $request->device_push_token;
			}
			$device->refreshAccessToken();
		} else {
			// if this is a new device, create it
			$device = $this->devicesRepo->createOrUpdateByIDAndType($request->only('device_id', 'device_type', 'device_push_token'), $user->id);
		}

		$response['access_token'] = $device->access_token;

		return response()->apiSuccess($response);
	}

	/**
	 *
	 * Logout from the API
	 *
	 */
	public function logout()
	{
		document(function () {
			return (new APICall)->setName('Logout')
				->setDescription('Logout the user from current device');
		});

		$accessToken = request()->header('X-Access-Token');

		DeviceAuthenticator::clearAccessToken($accessToken);

		return response()->apiSuccess(null, 'Logged out from the account.');
	}

	/**
	 *
	 * Logout all devices from the API
	 *
	 */
	public function logoutAllDevices()
	{
		document(function () {
			return (new APICall)->setName('Logout All Devices')
				->setDescription('Logout the user from all devices');
		});

		$user = DeviceAuthenticator::getUserByAccessToken();

		DeviceAuthenticator::clearAllAccessTokensByUserId($user->id);

		return response()->apiSuccess(null, 'Logged out from all the devices.');
	}

}
