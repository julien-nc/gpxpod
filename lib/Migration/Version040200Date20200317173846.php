<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version040200Date20200317173846 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('gpxpod_directories')) {
			$table = $schema->createTable('gpxpod_directories');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('path', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('gpxpod_tracks')) {
			$table = $schema->createTable('gpxpod_tracks');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('trackpath', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('contenthash', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('marker', 'text', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('gpxpod_pictures')) {
			$table = $schema->createTable('gpxpod_pictures');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('path', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('contenthash', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('lon', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('date_taken', 'bigint', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('gpxpod_tile_servers')) {
			$table = $schema->createTable('gpxpod_tile_servers');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 20,
				'default' => 'tile',
			]);
			$table->addColumn('servername', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('url', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => 'no-token',
			]);
			$table->addColumn('format', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => 'image/jpeg',
			]);
			$table->addColumn('layers', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('version', 'string', [
				'notnull' => true,
				'length' => 30,
				'default' => '1.1.1',
			]);
			$table->addColumn('opacity', 'string', [
				'notnull' => true,
				'length' => 10,
				'default' => '0.4',
			]);
			$table->addColumn('transparent', 'string', [
				'notnull' => true,
				'length' => 10,
				'default' => 'true',
			]);
			$table->addColumn('minzoom', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 1,
			]);
			$table->addColumn('maxzoom', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 18,
			]);
			$table->addColumn('attribution', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '???',
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
