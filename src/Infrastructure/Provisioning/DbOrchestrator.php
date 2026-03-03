<?php

declare(strict_types=1);

namespace App\Infrastructure\Provisioning;

interface DbOrchestrator
{
    /** @return array{host:string,dbname:string,user:string,password:string} */
    public function createDatabase(string $tenantId): array;

    public function rollbackDatabase(string $tenantId): void;
}
