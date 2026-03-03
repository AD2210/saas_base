<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<AuditEvent>
 */
final class AuditEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AuditEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Audit Event')
            ->setEntityLabelInPlural('Audit Events')
            ->setDefaultSort(['occurredAt' => 'DESC'])
            ->setSearchFields(['action', 'resource', 'status', 'correlationId']);
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
        yield DateTimeField::new('occurredAt');
        yield AssociationField::new('tenant');
        yield TextField::new('action');
        yield TextField::new('resource');
        yield TextField::new('status');
        yield TextField::new('correlationId');
        yield TextField::new('ipAddress');
        yield TextField::new('userId')->hideOnIndex();
        yield TextEditorField::new('userAgent')->hideOnIndex();
        yield ArrayField::new('beforeData')->hideOnIndex();
        yield ArrayField::new('afterData')->hideOnIndex();
    }
}
