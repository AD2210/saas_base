<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DemoRequest;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<DemoRequest>
 */
final class DemoRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DemoRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demo Request')
            ->setEntityLabelInPlural('Demo Requests')
            ->setDefaultSort(['requestedAt' => 'DESC'])
            ->setSearchFields(['status', 'onboardingTokenHash']);
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
        yield AssociationField::new('contact');
        yield AssociationField::new('tenant');
        yield TextField::new('status');
        yield DateTimeField::new('requestedAt');
        yield DateTimeField::new('expiresAt');
        yield DateTimeField::new('acceptedAt');
        yield TextField::new('onboardingTokenHash')->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
        yield DateTimeField::new('deletedAt')->hideOnForm();
    }
}
