<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCA\GpxPod\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version080100Date20250106183919 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
		private IUserConfig $userConfig,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// app config
		foreach (['maptiler_api_key', 'proxy_osm', 'use_gpsbabel'] as $key) {
			$value = $this->appConfig->getValueString(Application::APP_ID, $key);
			if ($value !== '') {
				$this->appConfig->updateLazy(Application::APP_ID, $key, true);
			}
		}

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('userid', 'configvalue', 'configkey')
			->from('preferences')
			->where(
				$qbSelect->expr()->eq('appid', $qbSelect->createNamedParameter(Application::APP_ID, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qbSelect->expr()->eq('lazy', $qbSelect->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		$req = $qbSelect->executeQuery();
		while ($row = $req->fetch()) {
			$userId = $row['userid'];
			$key = $row['configkey'];
			$value = $row['configvalue'];

			if ($value === null) {
				$this->userConfig->deleteUserConfig($userId, Application::APP_ID, $key);
			} else {
				$this->userConfig->setValueString($userId, Application::APP_ID, $key, $value, lazy: true);
			}
		}
	}
}
