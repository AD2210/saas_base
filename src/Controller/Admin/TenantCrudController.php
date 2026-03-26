<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tenant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Tenant>
 */
final class TenantCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tenant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tenant')
            ->setEntityLabelInPlural('Tenants')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['slug', 'name', 'childAppKey', 'adminEmail', 'status']);
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
        yield TextField::new('slug');
        yield TextField::new('name');
        yield TextField::new('childAppKey', 'Child App');
        yield TextField::new('adminEmail', 'Admin Email');
        yield TextField::new('adminFirstName', 'Admin First Name');
        yield TextField::new('adminLastName', 'Admin Last Name');
        yield TextField::new('plan');
        yield TextField::new('status');
        yield BooleanField::new('isActive');
        yield TextField::new('dbHost')->hideOnIndex();
        yield TextField::new('dbName')->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
        yield DateTimeField::new('deletedAt')->hideOnForm();
    }
}
