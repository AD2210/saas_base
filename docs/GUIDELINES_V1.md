# Guidelines V1 - SaaS Multi-Tenant Base

Date: 2026-03-02  
Statut: Draft V1

## 1) Objectif du projet / Project Goal

FR: Cette base sert de socle reproductible pour lancer rapidement des SaaS multi-tenant robustes, maintenables et scalables.  
EN: This repository is a reusable foundation to bootstrap robust, maintainable, and scalable multi-tenant SaaS products.

## 2) Stack technique obligatoire / Mandatory Stack

1. PHP: `8.5+`
2. Symfony: `7.4.*`
3. Database: PostgreSQL LTS
4. Runtime web: FrankenPHP + Caddy
5. Containerisation: Docker / Docker Compose
6. Observabilité et logs: logs applicatifs + logs système horodatés

## 3) Environnements et déploiement / Environments and Deployment

1. `dev`: local, itération rapide, routes debug autorisées.
2. `beta`: VPS OVH dédié, miroir proche prod, jeu de données de test réaliste.
3. `prod`: VPS OVH dédié, durci, accès strict, aucune route debug.

Règles:
1. Isolation stricte des secrets par environnement.
2. Variables d’environnement versionnées via `.env.example`, jamais de secret en Git.
3. Déploiements idempotents et rollbackables.

## 4) Architecture cible / Target Architecture

1. Une app mère Symfony gère:
   - landing page et CTA de demande de démo,
   - collecte des contacts,
   - orchestration de provisioning tenant,
   - administration globale (EasyAdmin),
   - observabilité et opérations.
2. Une app fille (ou plusieurs) porte le métier final de chaque tenant.
3. Modèle de données:
   - `main` database: contacts, demandes démo, utilisateurs globaux, audit global,
   - tenant databases: données métier isolées par tenant.

## 5) Exigences serveur / Server Baseline

Chaque serveur `beta` et `prod` doit inclure:
1. Configuration SSH durcie.
2. Pare-feu actif (policy deny by default).
3. Fail2ban configuré.
4. Mises à jour sécurité automatiques (`apt` unattended).
5. Rotation de logs (`logrotate`) explicite.
6. Redémarrage automatique de stack après reboot.
7. Redémarrage périodique du serveur via `systemd timer` ou cron (stratégie documentée).
8. Monitoring de santé système et applicatif.
9. Backup automatique chiffré de PostgreSQL vers stockage externe.
10. Procédure de rollback testée.

## 6) Standards scripts ops / Ops Script Standards

Pour tous les scripts shell/services:
1. Shebang strict et mode sécurisé: `#!/usr/bin/env bash` + `set -Eeuo pipefail`.
2. Messages explicites avec timestamp ISO-8601.
3. Commentaires orientés `why`, pas `how`.
4. Gestion d’erreur systématique avec codes de sortie non ambigus.
5. Logs stdout/stderr structurés et consultables rapidement.
6. Idempotence obligatoire.
7. Timeouts et retries documentés.
8. Aucun secret en clair dans les scripts.

Format log recommandé:
`2026-03-02T15:40:00+00:00 level=INFO service=backup action=start message="Starting pg_dump"`

## 7) Stratégie package Composer ops / Reusable Ops Package

Objectif: mutualiser les scripts récurrents entre projets SaaS.

Proposition V1:
1. Créer un package dédié `company/saas-platform-ops`.
2. Contenu:
   - commandes CLI,
   - templates `systemd`,
   - templates `fail2ban`,
   - templates `logrotate`,
   - scripts backup/restore/rollback.
3. Versioning sémantique (`semver`) + changelog.
4. Chaque projet consommateur garde uniquement la configuration locale.

## 8) Exigences applicatives / Application Requirements

1. Landing page élégante et orientée conversion.
2. CTA vers demande de démo.
3. Création d’un enregistrement `demo_request` horodaté.
4. Collecte des infos utiles pour futur CRM.
5. Envoi d’un email contenant un lien signé/chiffré avec expiration.
6. L’utilisateur fixe son mot de passe via ce lien.
7. L’app mère doit pouvoir provisionner et relier un tenant.

## 9) Administration app mère / Mother App Admin

EasyAdmin doit exposer:
1. CRUD `contacts`, `users`, `demo_requests` (et entités globales utiles).
2. Vue monitoring serveur (ou liens vers dashboards).
3. Accès aux logs applicatifs et techniques pertinents.
4. Outils d’analyse des tenants en lecture seule si possible.

## 10) Règles données / Data Rules

1. Tous les identifiants: UUID.
2. Toutes les entités persistées: timestamps (`created_at`, `updated_at`, etc.).
3. Conventions homogènes entre base main et bases tenant.
4. Traçabilité par corrélation (`correlation_id`) sur les flux critiques.

## 11) Debug policy

1. Les routes de debug commencent par `/debug/`.
2. Les routes debug sont activées uniquement en `dev` et éventuellement `beta` restreint IP.
3. Les routes debug sont interdites en production.
4. Chaque route debug est documentée: but, prérequis, output attendu.

## 12) Qualité de code / Code Quality

1. Respect PSR-1, PSR-12.  
2. PSR-2 est considéré couvert par PSR-12 pour les règles de style modernes.
3. Analyse statique obligatoire: PHPStan.
4. Formatage/lint obligatoire: PHP-CS-Fixer.
5. SonarQube recommandé pour la vision qualité globale.
6. Nommage du code en anglais.
7. Documentation et commentaires en français + anglais.

## 13) Politique de tests / Testing Policy

1. Tests unitaires, fonctionnels, intégration obligatoires.
2. Toute feature non-debug doit être couverte.
3. Les cas de succès, erreurs et edge-cases doivent être testés.
4. Les flows critiques:
   - demande de démo,
   - génération lien sécurisé,
   - activation compte,
   - provisioning tenant,
   - rollback.
5. Les tests doivent être reproductibles localement et en CI.

## 14) Definition of Done (DoD)

Une tâche est terminée si:
1. Code conforme aux standards ci-dessus.
2. Tests pertinents écrits et passants.
3. Lint/format/static analysis passants.
4. Documentation mise à jour (FR + EN sur les points clés).
5. Logs et erreurs explicites vérifiés.
6. Impact sécurité vérifié.
7. Si impact infra: procédure de rollback documentée.

## 15) Structure recommandée du repo (V1)

1. `src/`: code applicatif.
2. `tests/`: tests unitaires/fonctionnels/intégration.
3. `config/`: configuration Symfony.
4. `docker/`: images et conf runtime.
5. `docs/`: ADR, runbooks, guidelines, onboarding.
6. `ops/` ou package externe: scripts serveur réutilisables.

## 16) Intégration de projets fils / Child Project Integration

1. Définir un contrat d’intégration minimal:
   - identité tenant,
   - URL/base domain,
   - statut provisioning,
   - état abonnement.
2. Documenter un guide d’adaptation projet fils:
   - points de couplage,
   - prérequis techniques,
   - checklist migration vers modèle SaaS.
3. Prévoir des adapters pour limiter le couplage fort.

## 17) Sécurité applicative minimum

1. Tokens signés et expirables pour onboarding.
2. Chiffrement des données sensibles au repos et en transit.
3. Audit trail des actions admin sensibles.
4. Rotation des secrets planifiée.
5. Principe du moindre privilège sur DB et infra.

## 18) Observabilité et exploitation

1. Logs applicatifs structurés, horodatés, corrélables.
2. Métriques système: CPU, RAM, disk, load, DB health.
3. Alerting sur erreurs critiques et saturation.
4. Vérification périodique restauration backup.
5. Runbook incident documenté.

## 19) Backlog V2 (à compléter)

1. ADR multi-tenant détaillé (`database-per-tenant` vs alternatives).
2. Convention de migration DB entre main et tenants.
3. Contrat technique app mère <-> app fille.
4. Dashboard monitoring standard (Grafana/Prometheus ou alternative).
5. Politique de rétention des logs/backup.
6. Pipeline CI/CD standard dev -> beta -> prod.

