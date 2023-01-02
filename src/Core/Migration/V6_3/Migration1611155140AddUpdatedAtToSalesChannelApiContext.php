<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 * @package core
 */
class Migration1611155140AddUpdatedAtToSalesChannelApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1611155140;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement(
                'ALTER TABLE `sales_channel_api_context`
                ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            );
        } catch (\Throwable $e) {
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
