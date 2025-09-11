<?php
namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProvisioningTest extends KernelTestCase
{
    public function test_placeholder(): void
    {
        self::bootKernel();
        $this->assertTrue(true);
    }
}
