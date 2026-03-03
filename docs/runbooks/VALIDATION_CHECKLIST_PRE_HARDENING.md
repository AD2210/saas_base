# Validation Checklist (Pre-Hardening)

Date: 2026-03-03  
Scope: passe complète de validation avant hardening pré-prod

## 1) Préparation de la passe

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Branche cible validée | `git branch --show-current` | Branche attendue (`beta` ou branche de release) | `[ ]` |
| Arbre Git propre ou maîtrisé | `git status --short` | Aucun changement non voulu | `[ ]` |
| Variables d'env présentes | Vérifier `.env`, `.env.test`, secrets CI | Pas de variable manquante bloquante | `[ ]` |
| Dépendances installées | `composer install --no-interaction` | Install sans erreur | `[ ]` |

## 2) Stack applicative locale (Docker)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Build + up | `make up` | Containers `app`, `db`, `worker` up | `[ ]` |
| État containers | `docker compose ps` | Tous services critiques `Up` | `[ ]` |
| Healthz | `curl -fsS http://127.0.0.1:8088/healthz` | `OK` | `[ ]` |
| Ready | `curl -fsS http://127.0.0.1:8088/ready` | `READY` | `[ ]` |
| Worker Messenger | `make worker-logs` | Consommateur actif, pas d'erreurs en boucle | `[ ]` |

## 3) Base de données main + migrations

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Connexion DB | `php bin/console doctrine:query:sql "SELECT 1"` | Retour SQL valide | `[ ]` |
| Migrations à jour | `php bin/console doctrine:migrations:status` | Aucune migration pendante | `[ ]` |
| Table messenger | Vérifier `messenger_messages` | Table présente | `[ ]` |
| Contraintes clés | Vérifier uniques/FK sur `tenant`, `contact`, `demo_request` | Contraintes conformes au modèle V1 | `[ ]` |

## 4) Flux Landing -> Demo Request -> Onboarding

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Landing accessible | Ouvrir `/` | Page chargée sans erreur front | `[ ]` |
| Soumission formulaire démo | Tester formulaire landing | Réponse `201` + `status=requested` | `[ ]` |
| Création DB | Vérifier en DB `contact`, `tenant`, `demo_request` | Lignes créées et horodatées | `[ ]` |
| Mail async | Contrôler logs worker/mailer | Message traité via Messenger | `[ ]` |
| Lien onboarding | Ouvrir `/onboarding/set-password?token=...` | Formulaire affiché si token valide | `[ ]` |
| Password policy | Tester mot de passe faible/fort | Règles 12+ / uppercase / digit / special appliquées | `[ ]` |
| Token one-shot | Réutiliser token après acceptation | Token invalide (consommé) | `[ ]` |
| Token expiré | Tester token expiré | Statut expiré + message explicite | `[ ]` |

## 5) Contrat app mère -> app fille (tenant-admin-provisioning:v1)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Variables contrat | `MAIN_CHILD_APP_API_URL`, `MAIN_CHILD_APP_API_TOKEN` | Config cohérente par env | `[ ]` |
| Comportement dev sans URL | URL vide en dev | Skip journalisé non bloquant | `[ ]` |
| Comportement prod/beta sans URL | URL vide en prod/beta | Échec explicite (pas de skip silencieux) | `[ ]` |
| Payload V1 | Vérifier payload envoyé | Champs requis `tenant_uuid`, `user_uuid`, `email`, etc. | `[ ]` |
| Idempotence côté app fille | Rejouer provisioning admin | Pas de duplication, réponse 2xx si état déjà atteint | `[ ]` |

## 6) Back-office Admin (EasyAdmin)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Auth requise | Ouvrir `/admin` sans auth | `401` | `[ ]` |
| Auth super_admin | Ouvrir `/admin` avec credentials | Accès OK | `[ ]` |
| CRUD V1 | Naviguer CRUD `tenant`, `contact`, `demo_request`, `audit_event`, `tenant_migration_version` | Pages accessibles sans erreur | `[ ]` |
| Ops page | Ouvrir `/admin/ops` | Monitoring links + log excerpt visibles | `[ ]` |

## 7) Politique Debug

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Route debug en test | `php bin/console debug:router app_debug_onboarding_validate --env=test` | Route présente | `[ ]` |
| Route debug absente en prod | `php bin/console debug:router app_debug_onboarding_validate --env=prod` | Route inexistante | `[ ]` |
| Protection debug | Appel `/debug/...` sans auth | `401` | `[ ]` |
| Protection debug admin | Appel `/debug/...` avec auth admin | Réponse fonctionnelle | `[ ]` |

## 8) Monitoring + logs

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Netdata up | `make monitoring-up` puis accès `:19999` | Dashboard accessible | `[ ]` |
| Uptime Kuma up | Accès `:3001` | Dashboard accessible | `[ ]` |
| Liens admin configurés | `NETDATA_PUBLIC_URL`, `UPTIME_KUMA_PUBLIC_URL` | Liens visibles et ouvrables | `[ ]` |
| Logs applicatifs | Vérifier `var/log/*.log` | Logs explicites, timestamps, pas de secrets en clair | `[ ]` |

## 9) Qualité code / non-régression

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| YAML lint | `php bin/console lint:yaml config --parse-tags` | OK | `[ ]` |
| Container lint | `php bin/console lint:container` | OK | `[ ]` |
| Static analysis | `composer qa:phpstan` | OK | `[ ]` |
| Style check | `composer qa:cs-fixer` + `composer qa:phpcs` | OK | `[ ]` |
| Tests | `composer qa:phpunit` | Tous tests passants | `[ ]` |
| Deprecations | `php bin/console debug:container --deprecations` | 0 dépréciation | `[ ]` |

## 10) CI/CD et artefacts

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| CI branches | Vérifier workflow CI (`feature/*`, `main`, `develop`, `beta`, `production`) | Triggers corrects | `[ ]` |
| CD beta | Vérifier `cd-beta.yml` | Déploiement avec `DEPLOY_PROFILE=beta` | `[ ]` |
| CD prod | Vérifier `cd-prod.yml` | Déploiement nocturne + `DEPLOY_PROFILE=prod` | `[ ]` |
| Exclusions artefact prod | Vérifier tar excludes + script deploy | `src/Debug`, `config/routes/debug.yaml`, `src/DataFixtures`, `public/test.php`, `tests` exclus | `[ ]` |
| Rollback script | Vérifier présence et exécution à blanc | Procédure opérationnelle | `[ ]` |

## 11) Scripts Ops (phase 1)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Bash lint scripts | `make ops-lint` | OK | `[ ]` |
| Idempotence logique | Relancer scripts sur VM test | Pas d'effets destructifs inattendus | `[ ]` |
| Logging scripts | Vérifier `/var/log/saas/*` | Logs timestampés et lisibles | `[ ]` |
| Backup job | Simuler run `backup_db.sh` | Dump chiffré + rotation + upload | `[ ]` |
| Restore drill | Test restauration hebdo | RTO/RPO conformes cibles | `[ ]` |

## 12) Documentation et gouvernance

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Guidelines cohérentes | Relecture `docs/GUIDELINES_V2_CONSOLIDATED.md` | Pas de conflit avec implémentation | `[ ]` |
| Runbook à jour | `docs/runbooks/DEPLOYMENT_RUNBOOK_V1.md` | Procédures alignées scripts/workflows | `[ ]` |
| Contrat app fille doc | `docs/CHILD_APP_CONTRACT_V1.md` | Payload/réponses/idempotence documentés | `[ ]` |
| README à jour | `README.md` | Quickstart + debug policy + flow onboarding cohérents | `[ ]` |

## 13) Go / No-Go avant hardening pré-prod

| Critère | Règle | Statut |
|---|---|---|
| Critiques bloquants | 0 blocant ouvert (sécurité, data, provisioning, rollback) | `[ ]` |
| QA | Pipeline QA vert | `[ ]` |
| Déploiement | CD beta/prod validés sur environnement test | `[ ]` |
| Exploitation | Monitoring + backup + rollback opérationnels | `[ ]` |
| Décision | `GO` pour hardening pré-prod | `[ ]` |

