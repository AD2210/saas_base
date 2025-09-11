<?php
namespace App\Infrastructure\Provisioning;
use Doctrine\DBAL\Connection;
final class PostgresDbOrchestrator implements DbOrchestrator {
    public function __construct(private readonly Connection $main) {}
    public function createDatabase(string $slug): array {
        $db = sprintf('tenant_%s', preg_replace('/[^a-z0-9_]/', '_', strtolower($slug)));
        $user = $db . '_u';
        $pass = bin2hex(random_bytes(10));
        $this->main->executeStatement("CREATE USER \"$user\" WITH PASSWORD '$pass'");
        $this->main->executeStatement("CREATE DATABASE \"$db\" OWNER \"$user\" ENCODING 'UTF8'");
        return ['host'=>'db','dbname'=>$db,'user'=>$user,'password'=>$pass];
    }
}