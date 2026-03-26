# Validation Checklist (Pre-Hardening)

Date: 2026-03-03  
Scope: passe complète de validation avant hardening pré-prod

## 1) Préparation de la passe

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Branche cible validée | `git branch --show-current` | Branche attendue (`beta` ou branche de release) | `[X]` Branche `main` validée pour passe locale |
| Arbre Git propre ou maîtrisé | `git status --short` | Aucun changement non voulu | `[X]` Arbre maîtrisé (changements volontaires) |
| Variables d'env présentes | Vérifier `.env`, `.env.test`, secrets CI | Pas de variable manquante bloquante | `[X]` Variables critiques présentes (URLs monitoring volontairement vides en local) |
| Dépendances installées | `composer install --no-interaction` | Install sans erreur | `[X]` |

## 2) Stack applicative locale (Docker)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Build + up | `make up` | Containers `app`, `db`, `worker` up | `[X]` |
| État containers | `docker compose ps` | Tous services critiques `Up` | `[X]` (`app` healthy, `worker` up, `db` up) |
| Healthz | `curl -fsS http://127.0.0.1:8088/healthz` | `OK` | `[X]` |
| Ready | `curl -fsS http://127.0.0.1:8088/ready` | `READY` | `[X]` |
| Worker Messenger | `make worker-logs` | Consommateur actif, pas d'erreurs en boucle | `[X]` Message consumer actif et stable |

## 3) Base de données main + migrations

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Connexion DB | `php bin/console dbal:run-sql "SELECT 1"` | Retour SQL valide | `[X]` |
| Migrations à jour | `php bin/console doctrine:migrations:status` | Aucune migration pendante | `[X]` (`Executed Unavailable = 0`, `New = 0`) |
| Table messenger | Vérifier `messenger_messages` | Table présente | `[X]` |
| Contraintes clés | Vérifier uniques/FK sur `tenant`, `contact`, `demo_request` | Contraintes conformes au modèle V1 | `[X]` |

## 4) Flux Landing -> Demo Request -> Onboarding

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Landing accessible | Ouvrir `/` | Page chargée sans erreur front | `[X]` |
| Soumission formulaire démo | Tester formulaire landing | Réponse `201` + `status=requested` | `[X]` |
| Création DB | Vérifier en DB `contact`, `tenant`, `demo_request` | Lignes créées et horodatées | `[X]` |
| Mail async | Contrôler logs worker/mailer | Message traité via Messenger | `[X]` (`SendEmailMessage` routé `async`, ack worker) |
| Lien onboarding | Ouvrir `/onboarding/set-password?token=...` | Formulaire affiché si token valide | `[X]` |
| Password policy | Tester mot de passe faible/fort | Règles 12+ / uppercase / digit / special appliquées | `[X]` |
| Token one-shot | Réutiliser token après acceptation | Token invalide (consommé) | `[X]` |
| Token expiré | Tester token expiré | Statut expiré + message explicite | `[X]` |

## 5) Contrat app mère -> app fille (tenant-admin-provisioning:v1)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Variables contrat | `DEFAULT_CHILD_APP_KEY` + catalogue `config/packages/child_apps.yaml` | Config cohérente par env | `[X]` |
| Comportement dev sans URL | URL vide en dev | Skip journalisé non bloquant | `[X]` (tests unitaires) |
| Comportement prod/beta sans URL | URL vide en prod/beta | Échec explicite (pas de skip silencieux) | `[X]` (tests unitaires) |
| Payload V1 | Vérifier payload envoyé | Champs requis `tenant_uuid`, `user_uuid`, `email`, etc. + contexte `child_app_key` | `[X]` conforme implémentation/doc V1 |
| Idempotence côté app fille | Rejouer provisioning admin | Pas de duplication, réponse 2xx si état déjà atteint | `[ ]` À valider avec vraie app fille (non disponible localement) |

## 6) Back-office Admin (EasyAdmin)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Auth requise | Ouvrir `/admin` sans auth | `401` | `[X]` |
| Auth super_admin | Ouvrir `/admin` avec credentials | Accès OK | `[X]` |
| CRUD V1 | Naviguer CRUD `tenant`, `contact`, `demo_request`, `audit_event`, `tenant_migration_version` | Pages accessibles sans erreur | `[X]` |
| Ops page | Ouvrir `/admin/ops` | Monitoring links + log excerpt visibles | `[X]` |

## 7) Politique Debug

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Route debug en test | `php bin/console debug:router app_debug_onboarding_validate --env=test` | Route présente | `[X]` |
| Route debug absente en prod | `php bin/console debug:router app_debug_onboarding_validate --env=prod` | Route inexistante | `[X]` |
| Protection debug | Appel `/debug/...` sans auth | `401` | `[X]` |
| Protection debug admin | Appel `/debug/...` avec auth admin | Réponse fonctionnelle | `[X]` |

## 8) Monitoring + logs

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Netdata up | `make monitoring-up` puis accès `:19999` | Dashboard accessible | `[X]` |
| Uptime Kuma up | Accès `:3001` | Dashboard accessible | `[X]` |
| Liens admin configurés | `NETDATA_PUBLIC_URL`, `UPTIME_KUMA_PUBLIC_URL` | Liens visibles et ouvrables | `[ ]` Variables publiques non renseignées en local |
| Logs applicatifs | Vérifier logs runtime | Logs explicites, timestamps, pas de secrets en clair | `[X]` Après durcissement logs (suppression debug Caddy + canaux sensibles) |

## 9) Qualité code / non-régression

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| YAML lint | `php bin/console lint:yaml config --parse-tags` | OK | `[X]` |
| Container lint | `php bin/console lint:container` | OK | `[X]` |
| Static analysis | `composer qa:phpstan` | OK | `[X]` |
| Style check | `composer qa:cs-fixer` + `composer qa:phpcs` | OK | `[X]` |
| Tests | `composer qa:phpunit` | Tous tests passants | `[X]` (pass, avec notices PHPUnit non bloquantes) |
| Deprecations | `php bin/console debug:container --deprecations` | 0 dépréciation | `[ ]` Commande non exploitable telle quelle (`deprecation file does not exist`) |

## 10) CI/CD et artefacts

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| CI branches | Vérifier workflow CI (`feature/*`, `main`, `develop`, `beta`, `production`) | Triggers corrects | `[X]` |
| CD beta | Vérifier `cd-beta.yml` | Déploiement avec `DEPLOY_PROFILE=beta` | `[X]` |
| CD prod | Vérifier `cd-prod.yml` | Déploiement nocturne + `DEPLOY_PROFILE=prod` | `[X]` |
| Exclusions artefact prod | Vérifier tar excludes + script deploy | `src/Debug`, `config/routes/debug.yaml`, `src/DataFixtures`, `public/test.php`, `tests` exclus | `[X]` |
| Rollback script | Vérifier présence et exécution à blanc | Procédure opérationnelle | `[X]` Présent et cohérent (drill réel à faire sur VM) |

## 11) Scripts Ops (phase 1)

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Bash lint scripts | `make ops-lint` | OK | `[X]` |
| Idempotence logique | Relancer scripts sur VM test | Pas d'effets destructifs inattendus | `[ ]` À valider sur VPS de test |
| Logging scripts | Vérifier `/var/log/saas/*` | Logs timestampés et lisibles | `[ ]` À valider sur VPS (hors environnement local) |
| Backup job | Simuler run `backup_db.sh` | Dump chiffré + rotation + upload | `[ ]` À valider sur VPS (rclone cible réelle) |
| Restore drill | Test restauration hebdo | RTO/RPO conformes cibles | `[ ]` À planifier sur environnement beta |

## 12) Documentation et gouvernance

| Check | Commande / Action | Résultat attendu | Statut |
|---|---|---|---|
| Guidelines cohérentes | Relecture `docs/GUIDELINES_V2_CONSOLIDATED.md` | Pas de conflit avec implémentation | `[X]` |
| Runbook à jour | `docs/runbooks/DEPLOYMENT_RUNBOOK_V1.md` | Procédures alignées scripts/workflows | `[X]` |
| Contrat app fille doc | `docs/CHILD_APP_CONTRACT_V1.md` | Payload/réponses/idempotence documentés | `[X]` |
| README à jour | `README.md` | Quickstart + debug policy + flow onboarding cohérents | `[X]` |

## 13) Go / No-Go avant hardening pré-prod

| Critère | Règle | Statut |
|---|---|---|
| Critiques bloquants | 0 blocant ouvert (sécurité, data, provisioning, rollback) | `[X]` Aucun blocant applicatif local restant |
| QA | Pipeline QA vert | `[X]` `composer qa` vert |
| Déploiement | CD beta/prod validés sur environnement test | `[ ]` À confirmer par run réel sur VPS |
| Exploitation | Monitoring + backup + rollback opérationnels | `[ ]` Partiel local; validation finale à faire sur VPS |
| Décision | `GO` pour hardening pré-prod | `[ ]` `GO conditionnel` après validations VPS (ops/deploy/restore) |
