<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version070002Date20241020160425 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$qbDelete = $this->connection->getQueryBuilder();
		$qbDelete->delete('gpxpod_directories')
			->where(
				$qbDelete->expr()->eq('id', $qbDelete->createParameter('idToDelete'))
			);

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('id', 'user', 'path', $qbSelect->createFunction('COUNT(*)'))
			->from('gpxpod_directories')
			->having('COUNT(*) > 1')
			->groupBy('user', 'path');

		do {
			$hasDuplicates = false;
			$selectResult = $qbSelect->executeQuery();
			while ($row = $selectResult->fetch()) {
				$hasDuplicates = true;
				$id = $row['id'];
				$qbDelete->setParameter('idToDelete', $id, IQueryBuilder::PARAM_INT);
				$qbDelete->executeStatement();
			}
			$selectResult->closeCursor();
		} while ($hasDuplicates);
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
		$schemaChanged = false;

		$table = $schema->getTable('gpxpod_directories');
		if ($table->hasColumn('user') && $table->hasColumn('path') && !$table->hasIndex('gpxpod_dir_user_path_uniq')) {
			$table->addUniqueIndex(['user', 'path'], 'gpxpod_dir_user_path_uniq');
			$schemaChanged = true;
		}

		return $schemaChanged ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
