<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version050011Date20230417224807 extends SimpleMigrationStep {

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

		$table = $schema->getTable('gpxpod_tile_servers');
		if ($table->hasColumn('user')) {
			$column = $table->getColumn('user');
			$column->setNotnull(false);
			$column->setDefault(null);
		}

		if (!$schema->hasTable('gpxpod_tileservers')) {
			$table = $schema->createTable('gpxpod_tileservers');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('type', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('url', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('min_zoom', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('max_zoom', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('attribution', Types::TEXT, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
