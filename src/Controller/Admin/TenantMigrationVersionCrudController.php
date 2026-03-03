<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TenantMigrationVersion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<TenantMigrationVersion>
 */
final class TenantMigrationVersionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TenantMigrationVersion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tenant Migration')
            ->setEntityLabelInPlural('Tenant Migrations')
            ->setDefaultSort(['appliedAt' => 'DESC'])
            ->setSearchFields(['version']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('tenant');
        yield TextField::new('version');
        yield DateTimeField::new('appliedAt');
    }
}
