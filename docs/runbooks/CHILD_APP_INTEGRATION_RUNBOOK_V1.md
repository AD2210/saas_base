# Runbook Branchement App Fille V1

Date: 2026-03-04  
Scope: procédure exécutable pour brancher une app fille sur la base mère (`tenant-admin-provisioning:v1`)

## 1) Objectif

FR: connecter l'app mère à une app fille pour synchroniser/provisionner l'admin tenant au moment du set-password onboarding.  
EN: connect mother app to child app to sync/provision tenant admin during onboarding password setup.

## 2) Références

- Contrat technique V1: `docs/CHILD_APP_CONTRACT_V1.md`
- Compatibilité mère/fille: `docs/GUIDELINES_V2_CONSOLIDATED.md`
- Checklist globale pré-hardening: `docs/runbooks/VALIDATION_CHECKLIST_PRE_HARDENING.md`

## 3) Pré-requis

1. App mère:
   - QA vert (`qa:cs-fixer`, `qa:phpcs`, `qa:phpstan`, `phpunit`).
   - Worker Messenger actif.
2. App fille:
   - endpoint `POST /internal/provisioning/tenant-admin` implémenté.
   - auth Bearer activée.
   - idempotence implémentée (pas de duplication si même `tenant_uuid` + `user_uuid`).
3. Secrets:
   - token inter-app défini dans un secret manager ou variable sécurisée.

## 4) Paramétrage requis

### Côté app mère

Variables à définir:

```dotenv
MAIN_CHILD_APP_API_URL=https://child-app.example
MAIN_CHILD_APP_LOGIN_URL=https://child-app.example/login
MAIN_CHILD_APP_API_TOKEN=replace-with-strong-token
```

Fichier local recommandé:
- `dev`: `.env.local` (non commité)
- `beta/prod`: secrets Symfony / variables infra (jamais dans Git)

### Côté app fille

Configurer la validation du Bearer token côté endpoint interne de provisioning.

## 5) Smoke test contrat (manuel)

Exécuter depuis un shell disposant de `curl`:

```bash
export CHILD_URL="https://child-app.example"
export CHILD_TOKEN="replace-with-strong-token"

curl -i -X POST "${CHILD_URL}/internal/provisioning/tenant-admin" \
  -H "Authorization: Bearer ${CHILD_TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "contract":"tenant-admin-provisioning:v1",
    "tenant_uuid":"11111111-2222-7333-8444-555555555555",
    "user_uuid":"aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee",
    "email":"admin@example.com",
    "first_name":"Ada",
    "last_name":"Lovelace",
    "status":"active",
    "created_at":"2026-03-04T10:00:00+00:00",
    "updated_at":"2026-03-04T10:00:00+00:00",
    "password":"StrongPassw0rd!"
  }'
```

Résultat attendu:
1. HTTP `2xx`.
2. User admin créé/mis à jour côté app fille.
3. Aucun log contenant le mot de passe en clair.

## 6) Validation E2E depuis app mère

1. Démarrer stack locale:
   ```bash
   make up
   ```
2. Vérifier worker:
   ```bash
   docker compose ps
   ```
3. Soumettre une demande démo (landing):
   - route `/api/demo-requests` (ou formulaire `/`).
4. Récupérer le lien onboarding (mail async en dev via Messenger).
5. Ouvrir `/onboarding/set-password?token=...` et définir un mot de passe fort.
6. Contrôler résultat:
   - côté app mère:
     - état `accepted` si sync app fille OK,
     - état `sync_failed` si app fille indisponible / erreur contrat.
   - côté app fille:
     - user admin tenant présent et à jour.

## 7) Test d'idempotence (obligatoire)

1. Rejouer exactement le même payload provisioning 2 fois.
2. Attendu:
   - réponses `2xx` sur chaque appel,
   - aucun doublon utilisateur,
   - update déterministe de l'enregistrement existant.

## 8) Matrice d'erreurs à valider

| Cas | Entrée | Résultat attendu |
|---|---|---|
| URL absente en dev | `MAIN_CHILD_APP_API_URL=` | skip non bloquant + log `child.app.admin.sync.skipped` |
| URL absente en beta/prod | `MAIN_CHILD_APP_API_URL=` | erreur explicite (pas de skip silencieux) |
| Token absent | `MAIN_CHILD_APP_API_TOKEN=` | erreur explicite |
| Token invalide | Bearer incorrect | HTTP 401/403 côté app fille + `sync_failed` côté app mère |
| Endpoint indisponible | app fille down / timeout | `sync_failed` côté app mère |
| Retour 5xx app fille | erreur interne | `sync_failed` côté app mère |

## 9) Points de vigilance

1. Ne jamais logger `password` (mère et fille).
2. Maintenir le contrat `tenant-admin-provisioning:v1` strict.
3. Fixer des timeouts explicites et gérer les erreurs réseau.
4. Garder l'endpoint interne non public (proxy ACL, firewall, auth stricte).
5. Sur beta/prod, monitorer les erreurs de sync avec alerting.

## 10) Critères de Go

1. Smoke test contrat: OK.
2. E2E onboarding complet: OK.
3. Idempotence: OK.
4. Journaux conformes (sans secret): OK.
5. QA mère verte après branchement: OK.
