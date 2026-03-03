# Guidelines V2 Consolidated - SaaS Multi-Tenant Base

Date: 2026-03-02  
Sources: `docs/GUIDELINES_V1.md` + `docs/GUIDELINES_INPUTS_CHECKLIST.md`  
Statut: V2 (consolidée, prête pour exécution avec points ouverts listés)

## 1) Objectif

FR: Ce projet sert de base standardisée pour créer des SaaS multi-tenant rapidement, avec forte maintenabilité et scalabilité.  
EN: This project is a standardized foundation to build multi-tenant SaaS products quickly, with high maintainability and scalability.

## 2) Stack et architecture cible

1. PHP `8.5+`
2. Symfony `7.4.*`
3. PostgreSQL LTS
4. Docker + Docker Compose
5. FrankenPHP + Caddy
6. Pattern multi-tenant: `database-per-tenant` strict
7. Nommage base tenant: `db_{tenantId}`
8. Isolation attendue: services isolés (app/DB/réseau)

## 3) Priorité MVP

Ordre validé:
1. Backend
2. Landing page
3. Demo flow
4. Provisioning
5. Admin
6. Monitoring

Contraintes MVP:
1. Hors MVP: rien d’explicitement exclu
2. Deadline cible: 1 semaine

## 4) Modèle app mère / app fille

1. App mère:
   - landing + CTA,
   - collecte contacts,
   - onboarding,
   - provisioning tenant,
   - administration globale EasyAdmin.
2. App fille:
   - logique métier du tenant.
3. Contrat d’intégration mère -> fille:
   - protocole: API REST,
   - auth: JWT,
   - états provisioning: `requested`, `created`, `failed`, `cancelled`,
   - retry: 3 tentatives,
   - rollback: obligatoire après échec final + log explicite.

## 5) Modèle data V1

Règles globales:
1. Tous les IDs en UUID.
2. Entités horodatées (`created_at`, `updated_at`, etc.).
3. Soft delete pour suppression (RGPD).
4. Données personnelles: conformité RGPD droit français à formaliser.

Main DB V1:
1. `tenant` (users)
2. `contacts`

Tenant DB V1:
1. `user` app fille (copie user tenant)
2. Le reste est défini côté app fille.

Contraintes:
1. Unicité tenant via email.
2. Index “si pertinent” (à préciser par table au moment des migrations).

## 6) Environnements et infra OVH

Environnements:
1. `dev`
2. `beta`
3. `prod`

Infra:
1. OS: Ubuntu Server LTS
2. Beta VPS: 8 vCPU / 24 GB RAM / 200 GB SSD
3. Prod VPS: même base pour l’instant (à ajuster selon app fille)
4. Nommage serveurs: `app-fille-tenant-{tenantId}-{env}`
5. Domaine: `app-fille-{tenantId}.dsn-dev.com`
6. DNS provider: OVH
7. TLS: géré par Caddy

## 7) Exigences serveur et opérations

Baseline obligatoire:
1. Hardening SSH
2. Fail2ban
3. Pare-feu
4. MAJ auto apt
5. Logrotate
6. Auto-restart stack après reboot
7. Reboot planifié
8. Monitoring
9. Backup DB vers support externe
10. Rollback documenté

Standards scripts:
1. `bash` strict mode `set -Eeuo pipefail`
2. Logs explicites avec timestamp ISO-8601
3. Commentaires orientés “why”
4. Gestion erreur explicite + exit codes fiables
5. Idempotence obligatoire
6. Secret management sans hardcode

## 8) Sécurité

1. Chiffrement requis: AES-256
2. SSH policy:
   - clé only, fail count 3,
   - root login activé en phase 1 (risque accepté),
   - hardening pré-prod: port SSH custom (validé), revue root login lors du hardening final.
3. MFA: optionnel (authenticator app)
4. VPN/allowlist IP: non requis
5. Matrice réseau V1 (par défaut):
   - `22/tcp` SSH: ouvert, clé only, port custom prévu avant mise en prod,
   - `80/tcp` HTTP: ouvert, redirection vers HTTPS,
   - `443/tcp` HTTPS: ouvert (Caddy/FrankenPHP),
   - `5432/tcp` PostgreSQL: interdit en public, accès réseau privé uniquement,
   - `2019/tcp` Caddy admin: localhost uniquement.
6. Monitoring endpoints:
   - `19999/tcp` Netdata: derrière reverse proxy + authentification,
   - `3001/tcp` Uptime Kuma: derrière reverse proxy + authentification.

## 9) Secrets management

1. CI/CD secrets: GitHub Secrets
2. App secrets runtime (décision V1):
   - défaut: Symfony Secrets,
   - bascule: Vault si secrets externes/API critiques ou exigences réglementaires.
3. Rotation secrets: 30 jours (à appliquer selon la solution active)
4. Convention env vars:
   - `MAIN_` pour global
   - `APP_` pour app fille

## 10) Backup, restore, continuité

1. Backup OVH: quotidien
2. Backup DB: horaire
3. Rétention:
   - snapshots OVH: 7 jours
   - backups DB: 10 jours
4. Chiffrement backup: oui (AES-256)
5. Cibles:
   - RPO: 3h
   - RTO: 3h
6. Test restauration: hebdomadaire
7. Stockage externe: rclone via volume monté

## 11) Reboot policy

1. Reboot serveur: 1 fois par semaine
2. Fenêtre maintenance: 1 fois par semaine
3. Ordre relance stack: DB -> app -> services
4. Validation post-reboot:
   - healthcheck Docker,
   - test accès app mère et app fille (route de test).
5. Évolution prévue: ajout d’un reboot conditionnel si nécessaire.

## 12) Logging et observabilité

1. Format log: JSON
2. Log stack: locale (non centralisée)
3. Rétention logs: 1 mois
4. PII dans logs: anonymisée, conserver UUID utilisateur
5. Niveaux:
   - main `dev`/`beta`: critical, warning, info, debug
   - main `prod`: critical, warning, info
   - app fille: défini côté app fille
6. Monitoring stack V1:
   - Netdata pour métriques serveur et santé runtime,
   - Uptime Kuma pour checks d'uptime (HTTP/TCP/SSL),
   - alertes email activées via SMTP OVH.

## 13) Fonctionnel app

1. Landing page + CTA conversion.
2. Démo:
   - collecte: email, nom, prénom, adresse, date de naissance, téléphone,
   - expiration démo: 30 jours (paramétrable).
3. Onboarding:
   - email avec lien d’inscription sécurisé,
   - validité lien: 24h,
   - template email requis: inscription + confirmation,
   - création du mot de passe au moment de l’onboarding (pas à la collecte démo).
4. Password policy:
   - min 12 caractères,
   - 1 majuscule,
   - 1 chiffre,
   - 1 caractère spécial.

## 14) Admin (EasyAdmin)

1. CRUD obligatoires: toutes les entités V1
2. RBAC V1: Super Admin uniquement
3. Fonctions sensibles additionnelles: non définies pour l’instant

## 15) Debug policy

1. Préfixe debug: `/debug/*`
2. `beta`: autorisé, protégé par authentification globale + règles par route/action
3. `prod`: non déployé
4. Production artifact policy:
   - ne pas copier les dossiers debug/fixtures/tests dans l’artefact prod,
   - conserver les tests sur la branche `main` pour QA continue.
5. Durée de vie debug: pendant la vie de l’app en env non-prod

## 16) Qualité, standards, tests

1. Standards: PSR-1 / PSR-12 (PSR-2 couvert par PSR-12)
2. PHPStan:
   - cible: niveau 10 avec tolérance 10 warnings
   - alternative stricte: niveau 8 avec 0 tolérance
3. Coverage: 80% minimum
4. SonarQube:
   - phase 1: non utilisé,
   - phase 2: ajout si besoin (qualité/gouvernance) avec gate à définir.
5. Outils:
   - PHPStan
   - PHP Code Sniffer
   - PHPUnit
   - CS Fixer (+ règles projet PHP/JS/Twig/Symfony/Vue)

## 17) Git, branches, CI/CD, release

Branching annoncé:
1. `main`
2. `develop`
3. `release`
4. `beta`
5. `production`
6. `hotfix`
7. `feature/*` (1 US = 1 feature)

Règles:
1. Commits courts, peu de fichiers.
2. PR: 2 reviews minimum.
3. Checks PR obligatoires: PHPStan, PHP Code Sniffer, PHPUnit.
4. CI commune dev/beta/prod + contrôle au commit sur `main` et `feature/*`.
5. CD:
   - une CD beta,
   - une CD prod,
   - déploiement prod nocturne.
6. Versioning:
   - tag `vX.Y.Z`,
   - changenote à chaque release.
7. Rollback deployment: manuel, documenté dans README.

## 18) Compatibilité projets fils

Principe validé:
1. Le seul contrat strict entre app mère et app fille est le `tenant admin user`.
2. Tout le reste est sous responsabilité de l’app fille.

## 19) Stratégie de configuration Symfony (YAML overrides)

Règles de lisibilité et maintenance:
1. Une clé de configuration ne doit être définie qu’à un seul endroit fonctionnel.
2. Les options d’un composant doivent rester dans son fichier dédié (`web_profiler.yaml`, `mailer.yaml`, `messenger.yaml`, etc.).
3. Les overrides d’environnement (`when@dev`, `when@test`, `when@prod`) doivent rester dans ce même fichier dédié.
4. Éviter de répartir une même section (ex: `framework.profiler`) sur plusieurs fichiers non liés.
5. En cas d’exception, documenter la raison dans le fichier concerné (commentaire “why”, FR + EN).

Contrat minimal obligatoire app mère -> app fille (V1):
1. Provisionner (ou synchroniser) un utilisateur admin tenant dans l’app fille.
2. Données minimales requises:
   - `tenant_uuid`,
   - `user_uuid`,
   - `email`,
   - `first_name`,
   - `last_name`,
   - `status` (actif/inactif),
   - `created_at` / `updated_at`.
3. L’opération doit être idempotente (rejeu sans duplication).

Points adaptables:
1. Schéma métier spécifique app fille.
2. Rôles supplémentaires au-delà de l’admin tenant.
3. Entités et flux non liés au provisioning de l’admin tenant.

Checklist onboarding projet fils (minimum):
1. Endpoint/API de provisioning de l’admin tenant disponible.
2. Auth JWT inter-app validée.
3. Mapping des champs minimaux validé.
4. Test d’idempotence validé.
5. Test de connexion avec le tenant admin validé.

Matrice de compatibilité (V1):
1. Contrat `tenant-admin-provisioning:v1` requis côté app fille.
2. Toute app fille implémentant `v1` est compatible avec la base mère V1.

## 20) Règles d’autonomie Codex

1. Autorisé sans validation:
   - code applicatif,
   - scripts infra hors prod.
2. Non autorisé sans validation:
   - actions touchant prod,
   - actions hors projet local,
   - modifications config poste.
3. Infra: utiliser une VM de test.
4. Reporting attendu: détaillé.
5. Langue:
   - livrables: FR
   - RFC: bilingue
   - commits/PR: FR
6. Mode livraison: lots complets par milestone.

## 21) Open points à finaliser

1. Aucun point ouvert bloquant en phase 1.
