<?php


namespace App\Http\Controllers\API\V1;

use App\Entities\Files\FilesRepository;
use EMedia\Api\Docs\APICall;
use EMedia\Api\Docs\Param;
use EMedia\AppSettings\Facades\Setting;

class GuestController extends APIBaseController
{

	/**
	 *
	 * Guest settings
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index()
	{
		document(function () {
			return (new APICall())
				->setDescription('Guest settings and parameters')
				->noDefaultHeaders()->setHeaders([
					(new Param('Accept', 'String', '`application/json`'))->setDefaultValue('application/json'),
					(new Param('x-api-key', 'String', 'API Key'))->setDefaultValue('123123123123'),
				])
				->setSuccessExample('{
					"payload": {
						"Add your response here"
					},
					"message": "",
					"result": true
				}');
		});

		$data = [];

		// get files
		/** @var FilesRepository $filesRepo */
		$filesRepo = app(FilesRepository::class);

		$fileKeys = [
			'privacy-policy',
			'terms-conditions',
			'about-us',
		];

		foreach ($fileKeys as $fileKey) {
			$file = $filesRepo->findByKey($fileKey);
			$variableName = str_replace('-', '_', $fileKey);
			$data[$variableName . '_url'] = ($file)? $file->permalink: null;
		}

		// get settings
		$settingKeys = [
			// 'last_app_update'
		];

		foreach ($settingKeys as $settingKey) {
			$value = Setting::get($settingKey);
			$data[$settingKey] = $value;
		}

		return response()->apiSuccess($data);
	}


}