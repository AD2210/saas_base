# Child App Contract V1 (tenant-admin-provisioning)

Date: 2026-03-03  
Scope: contrat minimal obligatoire app mère -> app fille

## 1) Objectif

Synchroniser/créer l'utilisateur admin tenant côté app fille au moment de l'onboarding mot de passe.

## 2) Endpoint attendu côté app fille

- Method: `POST`
- Path: `/internal/provisioning/tenant-admin`
- Auth: `Authorization: Bearer <token>`
- Token source côté app mère: `MAIN_CHILD_APP_API_TOKEN`

## 3) Configuration app mère

- `MAIN_CHILD_APP_API_URL`: base URL de l'app fille (ex: `https://child-app.example`)
- `MAIN_CHILD_APP_API_TOKEN`: token Bearer de provisioning

Si `MAIN_CHILD_APP_API_URL` est vide, la synchronisation est ignorée (mode local/dev).

## 4) Payload JSON V1

```json
{
  "contract": "tenant-admin-provisioning:v1",
  "tenant_uuid": "uuid",
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
- `status_code`
- timestamp ISO-8601

Ne jamais logger le mot de passe en clair.
