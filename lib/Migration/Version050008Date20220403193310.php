<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version050008Date20220403193310 extends SimpleMigrationStep {

	private IDBConnection $connection;

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

		$table = $schema->getTable('gpxpod_pictures');
		if (!$table->hasColumn('direction')) {
			$table->addColumn('direction', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
			]);
		}

		$table = $schema->getTable('gpxpod_directories');
		if (!$table->hasColumn('sort_asc')) {
			$table->addColumn('sort_asc', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 1,
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
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('gpxpod_pictures');
		$qb->execute();
		$qb = $qb->resetQueryParts();
		$qb->delete('gpxpod_tracks');
		$qb->execute();
	}
}
