<?php

declare(strict_types=1);

namespace OCA\GpxPod\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version040200Date20200317174315 extends SimpleMigrationStep {

    /** @var IDBConnection */
    private $connection;

    /**
     * @param IDBConnection $connection
     */
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

        // for those who come from before migration : dateTaken => date_taken
        // because it hurts postgresql when created with migrations
        $table = $schema->getTable('gpxpod_pictures');
        if (!$table->hasColumn('date_taken')) {
            $table->addColumn('date_taken', 'bigint', [
                'notnull' => false,
                'length' => 10
            ]);
        }
        if ($table->hasColumn('dateTaken')) {
            $table->dropColumn('dateTaken');
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
        $qb->delete('gpxpod_tracks');
        $qb->execute();
        $qb = $qb->resetQueryParts();
        $qb->delete('gpxpod_pictures');
        $qb->execute();
    }
}
