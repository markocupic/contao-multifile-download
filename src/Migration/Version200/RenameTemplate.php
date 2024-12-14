<?php

declare(strict_types=1);

/*
 * This file is part of Contao Multifile Download.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-multifile-download
 */

namespace Markocupic\ContaoMultifileDownload\Migration\Version200;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class RenameTemplate extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $doMigration = false;

        $schemaManager = $this->connection->createSchemaManager();

        // If the database table itself does not exist we should do nothing
        if ($schemaManager->tablesExist(['tl_content'])) {
            $columns = $schemaManager->listTableColumns('tl_content');

            if (isset($columns['id'], $columns['type'], $columns['customtpl'])) {
                // #1 Rename template
                $id = $this->connection->fetchOne(
                    'SELECT id FROM tl_content WHERE type = ? AND customTpl LIKE "ce_downloads_multifile%"',
                    ['downloads'],
                );

                if (false !== $id) {
                    $doMigration = true;
                }
            }
        }

        return $doMigration;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $arrMessage = [];

        // #1 Rename template
        $id = $this->connection->fetchOne(
            'SELECT id FROM tl_content WHERE type = ? AND customTpl LIKE "ce_downloads_multifile%"',
            ['downloads'],
        );

        if (false !== $id) {
            $this->connection->executeStatement('UPDATE tl_content SET customTpl = "content_element/downloads/multifile" WHERE customTpl LIKE "ce_downloads_multifile%"');
            $arrMessage[] = 'Renamed the custom legacy templates of the downloads elements from "ce_downloads_multifile*" to "content_element/downloads/multifile".';
        }

        return new MigrationResult(
            true,
            implode(' ', $arrMessage)
        );
    }
}
