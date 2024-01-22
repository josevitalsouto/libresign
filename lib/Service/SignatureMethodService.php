<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\AbstractIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\ClickToSign;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCA\Libresign\Service\IdentifyMethod\Password;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Psr\Container\ContainerInterface;

class SignatureMethodService {
	public const TOKEN_LENGTH = 6;
	private const SIGN_PASSWORD = 'password';
	private const SIGN_SIGNAL = 'signal';
	private const SIGN_TELEGRAM = 'telegram';
	private const SIGN_SMS = 'sms';
	private const SIGN_EMAIL = 'email';
	private const DEFAULT_SIGN_METHOD = 'password';
	/**
	 * @var AbstractIdentifyMethod[]
	 */
	private array $allowedMethods;

	public function __construct(
		private IdentifyMethodService $identifyMethodService,
		private IAccountManager $accountManager,
		private IAppManager $appManager,
		private IConfig $config,
		private IL10N $l10n,
		private ISecureRandom $secureRandom,
		private IHasher $hasher,
		private ContainerInterface $serverContainer,
		private MailService $mail,
		private Password $password,
		private ClickToSign $clickToSign,
		private Email $email,
	) {
		$this->allowedMethods = [
			$this->password::ID => $this->password,
			$this->clickToSign::ID => $this->clickToSign,
			$this->email::ID => $this->email,
		];
	}

	public function getCurrent(): array {
		$signatureMethod = $this->config->getAppValue(Application::APP_ID, 'signature_method');
		$signatureMethod = json_decode($signatureMethod, true);
		if (!is_array($signatureMethod)) {
			$signatureMethods = $this->getAllowedMethods();
			foreach ($signatureMethods as $signatureMethod) {
				if ($signatureMethod['id'] === self::DEFAULT_SIGN_METHOD) {
					return $signatureMethod;
				}
			}
		}
		return $signatureMethod;
	}

	public function getAllowedMethods(): array {
		return array_map(function (AbstractIdentifyMethod $method) {
			return [
				'id' => $method->id,
				'label' => $method->friendlyName,
			];
		}, array_values($this->allowedMethods));
	}

	public function requestCode(SignRequest $signRequest, string $methodId, string $identify = ''): string {
		if (!array_key_exists($methodId, $this->allowedMethods)) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}

		$identifyMethods = $this->identifyMethodService->getIdentifyMethodsFromSignRequestId($signRequest->getId());
		if (!empty($identifyMethods[$methodId])) {
			$method = array_filter($identifyMethods[$methodId], function (IdentifyMethod $identifyMethod) use ($methodId) {
				if ($identifyMethod->getIdentifierKey() === $methodId) {
					true;
				}
				return false;
			});
			$method = current($method);
		}
		if (empty($method)) {
			$method = $this->identifyMethodService->getInstanceOfIdentifyMethod($methodId, $identify);
		} else {
			if (!empty($identify) && $identify !== $method->getEntity()->getIdentifierKey()) {
				throw new LibresignException($this->l10n->t('Invalid data to sign file'), 1);
			}
			$identify = $method->getEntity()->getIdentifierKey();
		}

		$token = $this->secureRandom->generate(self::TOKEN_LENGTH, ISecureRandom::CHAR_DIGITS);
		$this->sendCode($signRequest, $methodId, $token, $identify);

		$entity = $method->getEntity();
		$entity->setCode($this->hasher->hash($token));
		$entity->setMandatory(0);
		$this->identifyMethodService->save($signRequest, false);

		return $token;
	}

	private function sendCode(SignRequest $signRequest, string $methodId, string $code, string $identify = ''): void {
		switch ($methodId) {
			case SignatureMethodService::SIGN_SMS:
			case SignatureMethodService::SIGN_TELEGRAM:
			case SignatureMethodService::SIGN_SIGNAL:
				$this->sendCodeByGateway($code, gatewayName: $methodId);
				break;
			case SignatureMethodService::SIGN_EMAIL:
				$this->sendCodeByEmail($code, $identify, $signRequest->getDisplayName());
				break;
			case SignatureMethodService::SIGN_PASSWORD:
				throw new LibresignException($this->l10n->t('Sending authorization code not enabled.'));
		}
	}

	private function sendCodeByGateway(string $code, string $gatewayName): void {
		$gateway = $this->getGateway($user, $gatewayName);

		$userAccount = $this->accountManager->getAccount($user);
		$identifier = $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
		$gateway->send($user, $identifier, $this->l10n->t('%s is your LibreSign verification code.', $code));
	}

	/**
	 * @throws OCSForbiddenException
	 */
	private function getGateway(IUser $user, string $gatewayName): \OCA\TwoFactorGateway\Service\Gateway\IGateway {
		if (!$this->appManager->isEnabledForUser('twofactor_gateway', $user)) {
			throw new OCSForbiddenException($this->l10n->t('Authorize signing using %s token is disabled because Nextcloud Two-Factor Gateway is not enabled.', $gatewayName));
		}
		$factory = $this->serverContainer->get('\OCA\TwoFactorGateway\Service\Gateway\Factory');
		$gateway = $factory->getGateway($gatewayName);
		if (!$gateway->getConfig()->isComplete()) {
			throw new OCSForbiddenException($this->l10n->t('Gateway %s not configured on Two-Factor Gateway.', $gatewayName));
		}
		return $gateway;
	}

	private function sendCodeByEmail(string $code, string $email, string $displayName): void {
		$this->mail->sendCodeToSign(
			email: $email,
			name: $displayName,
			code: $code
		);
	}
}
