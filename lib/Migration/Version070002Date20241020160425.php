<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version070002Date20241020160425 extends SimpleMigrationStep {

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
		if ($table->hasColumn('user') && $table->hasColumn('path')) {
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
