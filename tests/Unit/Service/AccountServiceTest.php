<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FolderService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AccountServiceTest extends TestCase {
	/** @var IL10N */
	private $l10n;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IUserManager */
	protected $userManager;
	/** @var FolderService */
	private $folder;
	/** @var IConfig */
	private $config;
	/** @var NewUserMailHelper */
	private $newUserMail;
	/** @var CfsslHandler */
	private $cfsslHandler;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->folder = $this->createMock(FolderService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->cfsslHandler = $this->createMock(CfsslHandler::class);
	}

	/**
	 * @dataProvider providerTestValidateCreateToSign
	 */
	public function testValidateCreateToSign($arguments, $expectedErrorMessage) {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManager,
			$this->folder,
			$this->config,
			$this->newUserMail,
			$this->cfsslHandler
		);
		$this->expectExceptionMessage($expectedErrorMessage);
		$this->service->validateCreateToSign($arguments);
	}

	public function providerTestValidateCreateToSign() {
		return [
			[
				[
					'uuid' => 'invalid uuid'
				],
				'Invalid UUID'
			],
			[
				function ($self) {
					$uuid = '12345678-1234-1234-1234-123456789012';
					$self->fileUserMapper = $self->createMock(FileUserMapper::class);
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnCallback(function () {
							throw new \Exception("Beep, beep, not found!", 1);
						}));
					return [
						'uuid' => $uuid
					];
				},
				'UUID not found'
			],
			[
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'email' => 'invalid'
				],
				'Invalid email'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'invalid@test.coop'
					];
				},
				'This is not your file'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					$self->userManager
						->method('userExists')
						->will($self->returnValue(true));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop'
					];
				},
				'User already exists'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop',
						'password' => '',
					];
				},
				'Password is mandatory'
			],
			[
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'email' => 'valid@test.coop',
						'password' => '123456789',
						'signPassword' => '',
					];
				},
				'Password to sign is mandatory'
			]
		];
	}

	public function testGenerateCertificateWithInvalidData() {
		$this->cfsslHandler
			->method('__call')
			->will($this->returnValue($this->cfsslHandler));
		$this->service = new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManager,
			$this->folder,
			$this->config,
			$this->newUserMail,
			$this->cfsslHandler
		);
		$this->expectErrorMessage('Failure on generate certificate');
		$this->service->generateCertificate('uid', 'password');
	}
}
