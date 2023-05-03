<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use Closure;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends OCSController {
	private IConfig $config;
	private LoggerInterface $logger;
	private string $userId;

	use ErrorHelper;

	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		LoggerInterface $logger,
		string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->logger = $logger;
		$this->userId = $userId;
	}

	/**
	 * @param Closure $callback
	 *
	 * @return DataResponse
	 */
	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @param string $setting
	 */
	private function validateGetUserSetting(string $setting): void {
		if ($setting === 'user_folder') {
			return;
		}

		throw new \InvalidArgumentException('Unsupported setting ' . $setting);
	}

	/**
	 * @param string      $setting
	 * @param string|null $value
	 *
	 * @throws \InvalidArgumentException
	 */
	private function validateSetUserSetting(string $setting, ?string $value): void {
		if ($value === null) {
			throw new \InvalidArgumentException('Empty value for setting ' . $setting);
		}

		if ($setting === 'user_folder') {
			// No root folder, has to start with `/`, no subfolder
			if ($value === '/'
				|| !(strpos($value, '/') === 0)
				|| strpos($value, '/', 1) !== false) {
				throw new \InvalidArgumentException('Invalid collectives folder path');
			}

			return;
		}

		throw new \InvalidArgumentException('Unsupported setting ' . $setting);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $key
	 *
	 * @return DataResponse
	 */
	public function getUserSetting(string $key): DataResponse {
		return $this->prepareResponse(function () use ($key): string {
			$this->validateGetUserSetting($key);
			return $this->config->getUserValue($this->userId, 'collectives', $key, '');
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string      $key
	 * @param string|null $value
	 *
	 * @return DataResponse
	 */
	public function setUserSetting(string $key, ?string $value): DataResponse {
		return $this->prepareResponse(function () use ($key, $value): ?string {
			$this->validateSetUserSetting($key, $value);
			$this->config->setUserValue($this->userId, 'collectives', $key, $value);
			return $value;
		});
	}
}
