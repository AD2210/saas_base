<?php

declare(strict_types=1);

namespace App\ChildApp;

final class ChildAppCatalog
{
    /** @var array<string, ChildAppProfile> */
    private array $profiles = [];

    /**
     * @param array{
     *     default_key: string,
     *     apps: array<string, array<string, mixed>>
     * } $config
     */
    public function __construct(private readonly array $config)
    {
        foreach ($config['apps'] as $key => $profileConfig) {
            $this->profiles[$key] = $this->hydrateProfile((string) $key, $profileConfig);
        }
    }

    public function getDefault(): ChildAppProfile
    {
        $defaultKey = $this->config['default_key'];

        return $this->getByKey($defaultKey);
    }

    public function hasKey(string $key): bool
    {
        return isset($this->profiles[$key]);
    }

    public function getByKey(string $key): ChildAppProfile
    {
        if (!$this->hasKey($key)) {
            throw new \InvalidArgumentException(sprintf('Unknown child app key "%s".', $key));
        }

        return $this->profiles[$key];
    }

    public function resolve(?string $key): ChildAppProfile
    {
        $normalizedKey = null === $key ? '' : trim($key);
        if ('' === $normalizedKey) {
            return $this->getDefault();
        }

        return $this->getByKey($normalizedKey);
    }

    /**
     * @return list<ChildAppProfile>
     */
    public function all(): array
    {
        return array_values($this->profiles);
    }

    /**
     * @param array<string, mixed> $profileConfig
     */
    private function hydrateProfile(string $key, array $profileConfig): ChildAppProfile
    {
        $landingFacts = [];
        foreach ($profileConfig['landing_facts'] ?? [] as $fact) {
            if (is_scalar($fact)) {
                $landingFacts[] = trim((string) $fact);
            }
        }

        /** @var array<string, mixed> $theme */
        $theme = is_array($profileConfig['theme'] ?? null) ? $profileConfig['theme'] : [];

        return new ChildAppProfile(
            key: $key,
            name: trim((string) ($profileConfig['name'] ?? $key)),
            apiUrl: trim((string) ($profileConfig['api_url'] ?? '')),
            loginUrl: trim((string) ($profileConfig['login_url'] ?? '')),
            apiToken: trim((string) ($profileConfig['api_token'] ?? '')),
            landingEyebrow: trim((string) ($profileConfig['landing_eyebrow'] ?? 'Secure demo')),
            landingTitle: trim((string) ($profileConfig['landing_title'] ?? 'Launch your demo environment.')),
            landingLead: trim((string) ($profileConfig['landing_lead'] ?? 'Provisioning and onboarding are ready.')),
            landingFacts: $landingFacts,
            formTitle: trim((string) ($profileConfig['form_title'] ?? 'Request a demo')),
            formSubtitle: trim((string) ($profileConfig['form_subtitle'] ?? 'Receive your onboarding link instantly.')),
            submitLabel: trim((string) ($profileConfig['submit_label'] ?? 'Start my demo')),
            onboardingEyebrow: trim((string) ($profileConfig['onboarding_eyebrow'] ?? 'Secure onboarding')),
            onboardingTitle: trim((string) ($profileConfig['onboarding_title'] ?? 'Create your password')),
            onboardingLead: trim((string) ($profileConfig['onboarding_lead'] ?? 'Create your password to activate access.')),
            theme: $theme,
        );
    }
}
