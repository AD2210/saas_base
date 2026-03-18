# Child App Contract V1 (tenant-admin-provisioning)

Date: 2026-03-03  
Scope: contrat minimal obligatoire app mère -> app fille

## 1) Objectif

Synchroniser/créer l'utilisateur admin tenant côté app fille au moment de l'onboarding mot de passe.

## 2) Endpoint attendu côté app fille

- Method: `POST`
- Path: `/internal/provisioning/tenant-admin`
- Auth: `Authorization: Bearer <token>`
- Token source côté app mère: profil `api_token` du catalogue `config/packages/child_apps.yaml`

## 3) Configuration app mère

- `DEFAULT_CHILD_APP_KEY`: clé par défaut pour `/`
- `config/packages/child_apps.yaml`: catalogue des apps filles
- pour chaque profil:
  - `api_url`: base URL de provisioning interne (ex: `https://child-app.example`)
  - `login_url`: URL publique de login (ex: `https://child-app.example/login`)
  - `api_token`: token Bearer de provisioning
  - branding/landing/onboarding: copy + thème visuel utilisés par `/demo/{childAppKey}` et `/onboarding/set-password`

Si `api_url` est vide pour le profil ciblé, la synchronisation est ignorée en `dev` et échoue explicitement en `beta/prod`.

## 4) Payload JSON V1

```json
{
  "contract": "tenant-admin-provisioning:v1",
  "child_app_key": "vault",
  "child_app_name": "Client Secrets Vault",
  "tenant_uuid": "uuid",
  "tenant_slug": "acme-demo",
  "tenant_name": "Acme Demo",
  "user_uuid": "uuid",
  "email": "admin@example.com",
  "first_name": "Ada",
  "last_name": "Lovelace",
  "status": "active",
  "created_at": "2026-03-03T09:00:00+00:00",
  "updated_at": "2026-03-03T09:00:00+00:00",
  "password": "StrongPassw0rd!"
}
```

## 5) Contrat de réponse

- `2xx`: succès, l'onboarding peut être finalisé côté app mère.
- `4xx/5xx`: échec, l'onboarding reste non finalisé côté app mère (retry manuel utilisateur).

## 6) Idempotence (obligatoire)

L'endpoint app fille doit être idempotent:
- même `tenant_uuid` + `user_uuid` rejoué -> mise à jour, pas de duplication.
- réponse `2xx` si l'état final est déjà atteint.

## 7) Journalisation minimale côté app fille

- `tenant_uuid`
- `user_uuid`
- `contract`
- `child_app_key`
- `status_code`
- timestamp ISO-8601

Ne jamais logger le mot de passe en clair.
