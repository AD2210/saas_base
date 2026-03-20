<?php

declare(strict_types=1);

namespace App\Domain\Demo;

use App\ChildApp\ChildAppCatalog;
use App\Entity\Contact;
use App\Entity\DemoRequest;
use App\Infrastructure\Provisioning\OnboardingTokenManager;
use App\Infrastructure\Provisioning\TenantProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class DemoRequestManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantProvisioner $tenantProvisioner,
        private OnboardingTokenManager $tokenManager,
        private ChildAppCatalog $childAppCatalog,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(string:MAILER_FROM)%')]
        private string $mailerFrom,
    ) {
    }

    public function requestDemo(
        string $email,
        string $firstName,
        string $lastName,
        string $address,
        \DateTimeImmutable $birthDate,
        string $phone,
        string $company,
        string $baseUrl,
        ?string $childAppKey = null,
        ?string $slug = null,
    ): DemoRequest {
        $childApp = $this->childAppCatalog->resolve($childAppKey);
        $contact = $this->em->getRepository(Contact::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$contact instanceof Contact) {
            $contact = new Contact($email, $firstName, $lastName, $address, $birthDate, $phone);
            $this->em->persist($contact);
        }

        $tenant = $this->tenantProvisioner->createTenantAccount(
            email: $email,
            company: $company,
            firstName: $firstName,
            lastName: $lastName,
            childAppKey: $childApp->getKey(),
            slug: $slug,
        );
        $this->tenantProvisioner->provisionDatabase($tenant);

        $demoExpiresAt = new \DateTimeImmutable('+30 days');
        $demoRequest = new DemoRequest($contact, $tenant, $demoExpiresAt);
        $token = $this->tokenManager->generateToken($tenant, 86400);
        $demoRequest->setOnboardingTokenHash(hash('sha256', $token));
        $this->em->persist($demoRequest);
        $this->em->flush();

        $onboardingUrl = rtrim($baseUrl, '/').'/onboarding/set-password?token='.urlencode($token);
        $this->sendOnboardingMail($email, $firstName, $childApp->getName(), $onboardingUrl);

        $this->logger->info('demo.request.created', [
            'demo_request_uuid' => $demoRequest->getIdString(),
            'tenant_uuid' => $tenant->getIdString(),
            'tenant_slug' => $tenant->getSlug(),
            'child_app_key' => $childApp->getKey(),
            'contact_email' => $contact->getEmail(),
            'demo_expires_at' => $demoRequest->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]);

        return $demoRequest;
    }

    private function sendOnboardingMail(string $email, string $firstName, string $childAppName, string $onboardingUrl): void
    {
        $mail = (new Email())
            ->from($this->mailerFrom)
            ->to($email)
            ->subject(sprintf('Activation de votre demo %s', $childAppName))
            ->text(sprintf(
                "Bonjour %s,\n\nVotre demande de demo %s est enregistree.\n\nUtilisez ce lien pour creer votre mot de passe (valide 24h):\n%s\n",
                $firstName,
                $childAppName,
                $onboardingUrl
            ));

        $this->mailer->send($mail);
    }
}
