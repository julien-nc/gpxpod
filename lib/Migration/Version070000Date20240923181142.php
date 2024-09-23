<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version070000Date20240923181142 extends SimpleMigrationStep {

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
		$schemaChanged = false;

		$table = $schema->getTable('gpxpod_directories');
		if (!$table->hasColumn('sort_ascending')) {
			$table->addColumn('sort_ascending', Types::SMALLINT, [
				'notnull' => true,
				'default' => 1,
			]);
			$schemaChanged = true;
		}
		if (!$table->hasColumn('display_recursive')) {
			$table->addColumn('display_recursive', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$schemaChanged = true;
		}
		if ($table->hasColumn('sort_asc')) {
			$table->dropColumn('sort_asc');
			$schemaChanged = true;
		}
		if ($table->hasColumn('recursive')) {
			$table->dropColumn('recursive');
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
