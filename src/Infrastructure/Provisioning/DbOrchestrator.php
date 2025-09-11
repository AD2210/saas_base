<?php
namespace App\Infrastructure\Provisioning;
interface DbOrchestrator {
    /** @return array{host:string,dbname:string,user:string,password:string} */
    public function createDatabase(string $slug): array;
}