<?php


namespace EMedia\Oxygen\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\V1\APIBaseController;
use EMedia\Api\Docs\APICall;
use EMedia\Api\Docs\Param;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;

class ForgotPasswordController extends APIBaseController
{

	use SendsPasswordResetEmails;

	public function checkRequest(Request $request)
	{
		document(function () {
			return (new APICall())->setName('Reset Password')
				->setParams([
					(new Param('email')),
				])
				->noDefaultHeaders()
				->setHeaders([
					(new Param('Accept', 'String', '`application/json`'))->setDefaultValue('application/json'),
					(new Param('x-api-key', 'String', 'API Key'))->setDefaultValue('123-123-123-123'),
				])
				->setSuccessExample('{
					"payload": "",
					"message": "A password reset email will be sent to you in a moment.",
					"result": true
				}')->setErrorExample('{
					"message": "Failed to send password reset email. Ensure your email is correct and try again.",
					"payload": null,
					"result": false
				}', 422);
		});

		return $this->sendResetLinkEmail($request);
	}

	/**
	 * Get the response for a successful password reset link.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  string  $response
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
	 */
	protected function sendResetLinkResponse(Request $request, $response)
	{
		return response()->apiSuccess('', "A password reset email will be sent to you in a moment.");
	}

	/**
	 * Get the response for a failed password reset link.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  string  $response
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
	 */
	protected function sendResetLinkFailedResponse(Request $request, $response)
	{
		return response()->apiError("Failed to send password reset email. Ensure your email is correct and try again.");
	}

}