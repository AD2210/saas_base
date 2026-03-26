<?php

declare(strict_types=1);

namespace App\ChildApp;

final readonly class ChildAppProfile
{
    /**
     * @param list<string>         $landingFacts
     * @param array<string, mixed> $theme
     */
    public function __construct(
        private string $key,
        private string $name,
        private string $apiUrl,
        private string $loginUrl,
        private string $apiToken,
        private string $landingEyebrow,
        private string $landingTitle,
        private string $landingLead,
        private array $landingFacts,
        private string $formTitle,
        private string $formSubtitle,
        private string $submitLabel,
        private string $onboardingEyebrow,
        private string $onboardingTitle,
        private string $onboardingLead,
        private array $theme,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApiUrl(): string
    {
        return trim($this->apiUrl);
    }

    public function getLoginUrl(): string
    {
        return trim($this->loginUrl);
    }

    public function getApiToken(): string
    {
        return trim($this->apiToken);
    }

    public function getLandingEyebrow(): string
    {
        return $this->landingEyebrow;
    }

    public function getLandingTitle(): string
    {
        return $this->landingTitle;
    }

    public function getLandingLead(): string
    {
        return $this->landingLead;
    }

    /**
     * @return list<string>
     */
    public function getLandingFacts(): array
    {
        return $this->landingFacts;
    }

    public function getFormTitle(): string
    {
        return $this->formTitle;
    }

    public function getFormSubtitle(): string
    {
        return $this->formSubtitle;
    }

    public function getSubmitLabel(): string
    {
        return $this->submitLabel;
    }

    public function getOnboardingEyebrow(): string
    {
        return $this->onboardingEyebrow;
    }

    public function getOnboardingTitle(): string
    {
        return $this->onboardingTitle;
    }

    public function getOnboardingLead(): string
    {
        return $this->onboardingLead;
    }

    public function getThemeStyle(): string
    {
        $declarations = [];
        foreach ($this->theme as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $cssName = str_replace('_', '-', strtolower($name));
            $declarations[] = sprintf('--%s: %s', $cssName, trim((string) $value));
        }

        return implode('; ', $declarations);
    }
}
