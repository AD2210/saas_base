<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Provisioning\TenantProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:tenant:provision', description: 'Create a tenant + DB and activate it')]
final class TenantProvisionCommand extends Command
{
    public function __construct(private readonly TenantProvisioner $provisioner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('company', InputArgument::REQUIRED)
             ->addArgument('email', InputArgument::REQUIRED)
             ->addArgument('slug', InputArgument::OPTIONAL)
             ->addArgument('first-name', InputArgument::OPTIONAL, '', 'Tenant')
             ->addArgument('last-name', InputArgument::OPTIONAL, '', 'Admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $tenant = $this->provisioner->createTenantAccount(
                (string) $input->getArgument('email'),
                (string) $input->getArgument('company'),
                (string) $input->getArgument('first-name'),
                (string) $input->getArgument('last-name'),
                $input->getArgument('slug') ? (string) $input->getArgument('slug') : null,
            );
            $this->provisioner->provisionDatabase($tenant);
            $output->writeln(sprintf('<info>Tenant %s provisioned and activated.</info>', $tenant->getIdString()));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>Provisioning failed: %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }
    }
}
