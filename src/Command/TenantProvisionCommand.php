<?php
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
    public function __construct(private readonly TenantProvisioner $provisioner) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('company', InputArgument::REQUIRED)
             ->addArgument('email', InputArgument::REQUIRED)
             ->addArgument('slug', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $this->provisioner->createTenantAccount($input->getArgument('email'), $input->getArgument('company'));
        // Optionally override slug here
        // $tenant->setSlug($input->getArgument('slug'));
        $this->provisioner->provisionDatabase($tenant);
        $output->writeln('<info>Tenant provisioned and activated.</info>');
        return Command::SUCCESS;
    }
}
