<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version050011Date20230417224808 extends SimpleMigrationStep {

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

		$table = $schema->getTable('gpxpod_pictures');
		if (!$table->hasColumn('directory_id')) {
			$table->addColumn('directory_id', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
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
				'length' => 500,
			]);
			$table->addColumn('min_zoom', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('max_zoom', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('attribution', Types::STRING, [
				'notnull' => false,
				'length' => 300,
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
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('gpxpod_pictures');
		$qb->execute();
	}
}
