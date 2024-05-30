<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private CertificateEngineHandler $certificateEngineHandler
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Has root certificate
	 *
	 * Checks whether the root certificate has been configured by checking the Nextcloud configuration table to see if the root certificate settings have
	 *
	 * @return DataResponse<Http::STATUS_OK, array{hasRootCert: bool}, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	public function hasRootCert(): DataResponse {
		$checkData = [
			'hasRootCert' => $this->certificateEngineHandler->getEngine()->isSetupOk()
		];

		return new DataResponse($checkData);
	}
}
