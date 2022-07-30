<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version050000Date20220730004531 extends SimpleMigrationStep {

	public function __construct() {
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
		if (!$table->hasColumn('open')) {
			$table->addColumn('open', Types::INTEGER, [
				'notnull' => true,
				'length' => 1,
				'default' => '0'
			]);
		}

		$table = $schema->getTable('gpxpod_tracks');
		if (!$table->hasColumn('enabled')) {
			$table->addColumn('enabled', Types::INTEGER, [
				'notnull' => true,
				'length' => 1,
				'default' => '0'
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
				'default' => '0'
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
	}
}
