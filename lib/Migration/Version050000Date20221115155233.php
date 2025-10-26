<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version050000Date20221115155233 extends SimpleMigrationStep {

	/**
	 * @var IDBConnection
	 */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('gpxpod_directories');
		if ($table->hasColumn('open')) {
			$table->dropColumn('open');
		}
		if (!$table->hasColumn('is_open')) {
			$table->addColumn('is_open', Types::INTEGER, [
				'notnull' => true,
				'length' => 1,
				'default' => '0',
			]);
		}
		if (!$table->hasColumn('sort_order')) {
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'length' => 1,
				'default' => '0',
			]);
		}

		$table = $schema->getTable('gpxpod_tracks');
		if (!$table->hasColumn('is_enabled')) {
			$table->addColumn('is_enabled', Types::INTEGER, [
				'notnull' => true,
				'length' => 1,
				'default' => '0',
			]);
		}
		if (!$table->hasColumn('color')) {
			$table->addColumn('color', Types::STRING, [
				'notnull' => false,
				'length' => 10,
			]);
		}
		if (!$table->hasColumn('color_criteria')) {
			$table->addColumn('color_criteria', Types::INTEGER, [
				'notnull' => true,
				'length' => 1,
				'default' => '0',
			]);
		}
		if (!$table->hasColumn('directory_id')) {
			$table->addColumn('directory_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => '0',
			]);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// get all dirs
		$dirByUserByPath = [];
		$qb = $this->connection->getQueryBuilder();
		$qb->select('id', 'user', 'path')
			->from('gpxpod_directories');
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$userId = $row['user'];
			$path = $row['path'];
			if (!isset($dirByUserByPath[$userId])) {
				$dirByUserByPath[$userId] = [];
			}
			$dirByUserByPath[$userId][$path] = $row;
		}
		$req->closeCursor();

		// indexed by track id => dir id
		$trackDirIds = [];
		// get tracks by user (with 0 as directory_id)
		foreach ($dirByUserByPath as $userId => $dirByPath) {
			$qb = $this->connection->getQueryBuilder();
			$qb->select('id', 'user', 'trackpath')
				->from('gpxpod_tracks')
				->where(
					$qb->expr()->eq('directory_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				);
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$trackPath = $row['trackpath'];
				$trackUser = $row['user'];
				$trackDirPath = dirname($trackPath);
				$trackDirIds[$row['id']] = $dirByUserByPath[$trackUser][$trackDirPath]['id'] ?? 0;
			}
			$req->closeCursor();
		}

		foreach ($trackDirIds as $trackId => $dirId) {
			$qb = $this->connection->getQueryBuilder();
			$qb->update('gpxpod_tracks');
			$qb->set('directory_id', $qb->createNamedParameter($dirId, IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter((int)$trackId, IQueryBuilder::PARAM_INT))
			);
			$qb->executeStatement();
		}
	}
}
