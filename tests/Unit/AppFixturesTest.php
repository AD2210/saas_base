<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DataFixtures\AppFixtures;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

final class AppFixturesTest extends TestCase
{
    public function testLoadFlushesManagerEvenWithoutSeedData(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->once())->method('flush');

        $fixtures = new AppFixtures();
        $fixtures->load($manager);
    }
}
