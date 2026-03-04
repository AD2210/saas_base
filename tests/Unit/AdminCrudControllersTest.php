<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\Admin\AuditEventCrudController;
use App\Controller\Admin\ContactCrudController;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\DemoRequestCrudController;
use App\Controller\Admin\TenantCrudController;
use App\Controller\Admin\TenantMigrationVersionCrudController;
use App\Entity\AuditEvent;
use App\Entity\Contact;
use App\Entity\DemoRequest;
use App\Entity\Tenant;
use App\Entity\TenantMigrationVersion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\TestCase;

final class AdminCrudControllersTest extends TestCase
{
    public function testTenantCrudConfigurationIsConsistent(): void
    {
        $controller = new TenantCrudController();

        self::assertSame(Tenant::class, $controller::getEntityFqcn());
        $this->assertCrudMetadata(
            $controller->configureCrud(Crud::new()),
            'Tenant',
            'Tenants',
            ['slug', 'name', 'adminEmail', 'status'],
            ['createdAt' => 'DESC']
        );
        $this->assertIndexDetailAndDisabledMutations($controller->configureActions(Actions::new()));
        $this->assertFieldProperties(
            $controller->configureFields(Crud::PAGE_INDEX),
            ['id', 'slug', 'name', 'adminEmail', 'adminFirstName', 'adminLastName', 'plan', 'status', 'isActive', 'dbHost', 'dbName', 'createdAt', 'updatedAt', 'deletedAt']
        );
    }

    public function testContactCrudConfigurationIsConsistent(): void
    {
        $controller = new ContactCrudController();

        self::assertSame(Contact::class, $controller::getEntityFqcn());
        $this->assertCrudMetadata(
            $controller->configureCrud(Crud::new()),
            'Contact',
            'Contacts',
            ['email', 'firstName', 'lastName', 'phone'],
            ['createdAt' => 'DESC']
        );
        $this->assertIndexDetailAndDisabledMutations($controller->configureActions(Actions::new()));
        $this->assertFieldProperties(
            $controller->configureFields(Crud::PAGE_INDEX),
            ['id', 'email', 'firstName', 'lastName', 'phone', 'address', 'birthDate', 'createdAt', 'updatedAt', 'deletedAt']
        );
    }

    public function testDemoRequestCrudConfigurationIsConsistent(): void
    {
        $controller = new DemoRequestCrudController();

        self::assertSame(DemoRequest::class, $controller::getEntityFqcn());
        $this->assertCrudMetadata(
            $controller->configureCrud(Crud::new()),
            'Demo Request',
            'Demo Requests',
            ['status', 'onboardingTokenHash'],
            ['requestedAt' => 'DESC']
        );
        $this->assertIndexDetailAndDisabledMutations($controller->configureActions(Actions::new()));
        $this->assertFieldProperties(
            $controller->configureFields(Crud::PAGE_INDEX),
            ['id', 'contact', 'tenant', 'status', 'requestedAt', 'expiresAt', 'acceptedAt', 'onboardingTokenHash', 'createdAt', 'updatedAt', 'deletedAt']
        );
    }

    public function testAuditEventCrudConfigurationIsConsistent(): void
    {
        $controller = new AuditEventCrudController();

        self::assertSame(AuditEvent::class, $controller::getEntityFqcn());
        $this->assertCrudMetadata(
            $controller->configureCrud(Crud::new()),
            'Audit Event',
            'Audit Events',
            ['action', 'resource', 'status', 'correlationId'],
            ['occurredAt' => 'DESC']
        );
        $this->assertIndexDetailAndDisabledMutations($controller->configureActions(Actions::new()));
        $this->assertFieldProperties(
            $controller->configureFields(Crud::PAGE_INDEX),
            ['id', 'occurredAt', 'tenant', 'action', 'resource', 'status', 'correlationId', 'ipAddress', 'userId', 'userAgent', 'beforeData', 'afterData']
        );
    }

    public function testTenantMigrationCrudConfigurationIsConsistent(): void
    {
        $controller = new TenantMigrationVersionCrudController();

        self::assertSame(TenantMigrationVersion::class, $controller::getEntityFqcn());
        $this->assertCrudMetadata(
            $controller->configureCrud(Crud::new()),
            'Tenant Migration',
            'Tenant Migrations',
            ['version'],
            ['appliedAt' => 'DESC']
        );
        $this->assertIndexDetailAndDisabledMutations($controller->configureActions(Actions::new()));
        $this->assertFieldProperties($controller->configureFields(Crud::PAGE_INDEX), ['id', 'tenant', 'version', 'appliedAt']);
    }

    public function testDashboardConfigurationSetsExpectedTitle(): void
    {
        $controller = new DashboardController('https://netdata.local', 'https://kuma.local');

        self::assertSame('SaaS Base Admin', $controller->configureDashboard()->getAsDto()->getTitle());
    }

    /**
     * @param list<string>                $expectedSearchFields
     * @param array<string, 'ASC'|'DESC'> $expectedDefaultSort
     */
    private function assertCrudMetadata(
        Crud $crud,
        string $singularLabel,
        string $pluralLabel,
        array $expectedSearchFields,
        array $expectedDefaultSort,
    ): void {
        $dto = $crud->getAsDto();

        self::assertSame($singularLabel, $dto->getEntityLabelInSingular());
        self::assertSame($pluralLabel, $dto->getEntityLabelInPlural());
        self::assertSame($expectedDefaultSort, $dto->getDefaultSort());
        foreach ($expectedSearchFields as $searchField) {
            self::assertContains($searchField, $dto->getSearchFields() ?? []);
        }
    }

    private function assertIndexDetailAndDisabledMutations(Actions $actions): void
    {
        $dto = $actions->getAsDto(Crud::PAGE_INDEX);

        self::assertNotNull($dto->getAction(Crud::PAGE_INDEX, Action::DETAIL));
        self::assertContains(Action::NEW, $dto->getDisabledActions());
        self::assertContains(Action::EDIT, $dto->getDisabledActions());
        self::assertContains(Action::DELETE, $dto->getDisabledActions());
    }

    /**
     * @param iterable<FieldInterface> $fields
     * @param list<string>             $expectedProperties
     */
    private function assertFieldProperties(iterable $fields, array $expectedProperties): void
    {
        $actualProperties = [];
        foreach ($fields as $field) {
            $actualProperties[] = $field->getAsDto()->getProperty();
        }

        self::assertSame($expectedProperties, $actualProperties);
    }
}
