<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 * @package core
 */
class Migration1562306893MakeCustomerFirstLoginDateTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562306893;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer`
            MODIFY COLUMN `first_login` DATETIME(3) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
