# Guidelines Inputs Checklist (Autonomie Codex)

Date: 2026-03-02  
Usage: compléter ce document pour me permettre d’exécuter en autonomie.

## 1) MVP Priorité / Prioritization

- [ ] Ordre exact des livrables MVP (ex: `Landing -> Demo flow -> Provisioning -> Admin -> Monitoring`)
- R : Backend -> landing page -> demo flow -> provisioning -> admin -> monitoring
- [ ] Ce qui est explicitement hors MVP
- R : rien
- [ ] Deadline cible MVP
- R : 1 semaine

## 2) Multi-Tenant Model

- [ ] Modèle confirmé: `database-per-tenant` (oui/non)
- R : oui, nommage DB tenant : db_tenantId
- [ ] Exceptions autorisées (si oui, lesquelles)
- R : Non
- [ ] Niveau d’isolation attendu (app, DB, réseau)
- R : oui chaque service est isolé

## 3) Contrat App Mère <-> App Fille

- [ ] Mode d’intégration: API REST / events / autre
- R : API REST
- [ ] Auth entre apps (JWT, mTLS, token signé, etc.)
- R : JWT
- [ ] États de provisioning (`requested`, `created`, `failed`, etc.)
- R : requested, created, failed, cancelled
- [ ] Stratégie retry/rollback en cas d’échec provisioning
- R : retry 3 fois, rollback si échec + log

## 4) Entités V1 Obligatoires

- [ ] Main DB: liste des entités minimales + champs obligatoires
- R : tenant (users), contacts
- [ ] Tenant DB: liste des entités minimales + champs obligatoires
- R : user app fille (avec copie du user tenant) le reste sera géré coté app fille
- [ ] Contraintes critiques (unicité, index, FK, soft delete, etc.)
- R : unicité des tenant via email, index si pertient, soft delete en cas de demande de suppression (RGPD), Politiques RGPD à prévoir conforme droit francais

## 5) Providers Imposés

- [ ] Email provider
- R : ovh
- [ ] Stockage backup externe
- R : Rclone via serveur sur volume monté
- [ ] Monitoring / alerting stack
- R : pas de préférence, nous ferons une analyse de l'existant ici pour choisir une solution
- [ ] Logging stack (centralisé ou local)
- R : local

## 6) Topologie OVH

- [ ] OS cible (version)
- R : Ubuntu server LTS
- [ ] Specs VPS `beta`
- R : 8 cores, 24go RAM, 200go SSD
- [ ] Specs VPS `prod`
- R : à définir en fct de l'app fille mais partons sur idem beta pour le moment
- [ ] Nommage serveurs
- R : app-fille-tenant-{tenantId}-{env}
- [ ] Domaines / sous-domaines
- R : domaine app-fille-{tenantId}.dsn-dev.com
- [ ] DNS provider
- R : OVH
- [ ] Politique TLS/certificats
- R : Caddy pour l'app

## 7) Secrets Management

- [ ] Méthode de stockage secrets
- R : GH pour les secrets CI/CD, a voir pour des solutions coté app
- [ ] Politique rotation secrets
- R : 30 jours, à voir avec la solution retenu
- [ ] Qui peut accéder à quoi
- R : Super Admin (moi) => full accès, les autres sont des users simple => accès à leur infos personnelles et app fille en tant qu'admin
- [ ] Convention de nommage des variables d’environnement
- R : MAIN_ pour les variables globales, APP_ pour l'app fille

## 8) Politique Sécurité Non Négociable

- [ ] Ports autorisés par environnement
- R : Paramétrable dans les scripts, on reste classique ici pour les ports
- [ ] Politique SSH (clé only, root login, fail count, etc.)
- R : clé only, root login activé, fail count à 3, MFA optionnel avec authenticator app
- [ ] MFA / VPN / allowlist IP
- R : MFA optionnel avec authenticator app, pas de VPN ou allowlist IP
- [ ] Exigences de chiffrement obligatoires
- R : AES-256

## 9) Backup / Restore Targets

- [ ] Fréquence backup
- R : backup OVH tous les jours, Backup DB tous les heures
- [ ] Rétention
- R : Ovh conserve 7 jours les snapshots, DB 10 jours
- [ ] Chiffrement backup
- R : Oui (AES-256)
- [ ] RPO cible
- R : 3 heures
- [ ] RTO cible
- R : 3 heures
- [ ] Fréquence test de restauration
- R : 1 fois par semaine

## 10) Politique Reboot

- [ ] Fréquence reboot serveur
- R : 1 fois par jours
- [ ] Fenêtre de maintenance
- R : 1 fois par semaine
- [ ] Comportement stack au reboot (ordre de relance)
- R : DB, app, services
- [ ] Procédure de validation post-reboot
- R : HealthCheck docker, test d'accès app main et app fille avec route de test

## 11) Politique Logs

- [ ] Format log final
- R : JSON
- [ ] Niveau minimal par environnement
- R : Main (dev et beta) => critical, warning, info, debug; Main (prod) => critical, warning, info; App fille => défini dans app fille
- [ ] Rétention
- R : 1 mois
- [ ] Anonymisation / masquage PII
- R : Oui anonymise dans les logs, on conservera uniquement l'UUID de l'utilisateur
- [ ] Centralisation (oui/non)
- R :non

## 12) Scope EasyAdmin V1

- [ ] CRUD obligatoires
- R : l'ensemble des entités V1
- [ ] Rôles/permissions (RBAC)
- R : Super admin uniquement
- [ ] Fonctions admin sensibles
- R : pas pour l'instant
- [ ] Éléments explicitement exclus du V1
- R : pas pour l'instant

## 13) Flow Démo / Onboarding

- [ ] Données exactes collectées au signup démo
- R : email, nom, prenom, adresse, date de naissance, téléphone, mot de passe
- [ ] Durée de validité lien onboarding
- R : 24h
- [ ] Règles d’expiration de démo
- R : 30 jours, paramétrable
- [ ] Templates email requis
- R : Oui pour lien d'inscription et confirmation
- [ ] Règles mot de passe (policy)
- R : 12 caractère minimum, 1 majuscule, 1 chiffre, 1 caractère spécial

## 14) Debug en Beta

- [ ] Routes debug autorisées en `beta` (oui/non)
- R : oui
- [ ] Contrôle d’accès (IP, auth, VPN)
- R : par auth, de manière globale puis par route ou action
- [ ] Durée de conservation des endpoints debug
- R : Pendant toutes la durée de vie de l'application, elle ne seront pas copié lors du deploiement prod
- [ ] Politique de purge avant prod
- R : on purge tous les endpoints debug, les fixtures et les tests

## 15) Qualité / QA Gates

- [ ] PHPStan level cible
- R : 10 avec tolérance sur 10 warning, lvl 8 avec 0 tolérance
- [ ] Seuil couverture de code (%)
- R : 80% minimum
- [ ] Quality gate Sonar (bloquant ou informatif)
- R : a voir lors du déploiement
- [ ] Règles CS Fixer spécifiques
- R : Règles spécifiques pour les projets PHP et JS, incluant Twig, Symfony, VueJS, etc.

## 16) Git + CI/CD

- [ ] Stratégie branches
- R : Git flow utilisé pour les branches, chaque partie, découpé en US = une feature, prévoir des commits courts avec peu de fichiers modifiés.
- R : 1 branche release, 1 branche main, 1 branche develop, une branche beta, une branche de production, une branche hotfix
- [ ] Règles PR (review count, checks obligatoires)
- R : 2 reviews minimum, checks obligatoires : PHPStan, PHP Code Sniffer, PHPUnit, SonarQube
- [ ] Pipeline dev -> beta -> prod
- R : oui, Ci commune pour les 3 environnements, controle au commit sur les branches main, feature/*. 1 CD pour chaque beta et 1 pour prod.
- Pour la CD prod, prévoir un timer de déploiement la nuit
- [ ] Stratégie release (tagging/versioning)
- R : 1 tag par release, avec le format `vX.Y.Z` (X version majeur, Y version mineur, Z version patch), prévoir une changenote à chaque release.
- [ ] Rollback deployment (auto/manuelle)
- R : Manuelle, documenté dans le README.

## 17) Compatibilité Projets Fils

- [ ] Contrat minimal obligatoire pour un projet fils
- R : User uniquement, il faut cette partie reste fléxible quelques soit l'app fille
- [ ] Points adaptables
- R : Qu'attentu ici ?
- [ ] Checklist d’onboarding projet fils
- R : Qu'attentu ici ?
- [ ] Matrice de compatibilité versions
- R : Qu'attentu ici ?

## 18) Garde-Fous d’Autonomie Codex

- [ ] Actions autorisées sans validation (code/app)
- R : tous code et app
- [ ] Actions autorisées sans validation (infra/scripts)
- R : oui, mais uniquement pour les scripts qui ne touchent pas à la prod, pour l'infra prévoir une VM de test
- [ ] Actions nécessitant validation explicite
- R : oui, pour les actions qui touchent à la prod, toutes les actions modifiant ou supprimant des fichiers sur mon postes hors projet, tous ce qui concerne la config de mon poste
- [ ] Limites (budget temps, niveau de risque, impacts prod)
- R : pas de limites

## 19) Format de réponse attendu (optionnel mais utile)

- [ ] Tu préfères que je fournisse:
  - [ ] PR petites et fréquentes
  - [x] lots complets par milestone
  - [ ] RFC avant implémentation
- [ ] Langue principale des livrables (`FR`, `EN`, `bilingue`)
- R : FR Pour les livrables, bilingue pour les RFC, FR pour les commits et PR 
- [ ] Niveau de détail de reporting (`court`, `moyen`, `détaillé`)
- R : détaillé

---

## Bloc Réponse Rapide (copier/coller)

Tu peux répondre rapidement en reprenant ce format:

```text
1) MVP:
2) Multi-tenant:
3) Mother/Child contract:
4) Entities:
5) Providers:
6) OVH topology:
7) Secrets:
8) Security:
9) Backup/Restore:
10) Reboot:
11) Logs:
12) EasyAdmin:
13) Demo flow:
14) Debug beta:
15) QA gates:
16) Git/CI-CD:
17) Child projects:
18) Codex autonomy guardrails:
19) Reporting preferences:
```

