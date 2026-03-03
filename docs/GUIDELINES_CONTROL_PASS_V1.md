# Guidelines Control Pass V1

Date: 2026-03-02  
Scope: contrôle de cohérence de `GUIDELINES_V2_CONSOLIDATED.md`

## 1) Résultat global

1. Sections couvertes: 19/19
2. Statut:
   - `OK`: 19
   - `A clarifier`: 0
3. Bloquant immédiat pour implémentation MVP: non
4. Risques à trancher avant déploiement beta/prod: non (hors hardening pré-prod planifié)

## 2) Points OK

1. Stack et versions cibles.
2. Modèle multi-tenant strict (`database-per-tenant`).
3. Priorisation MVP et deadline.
4. Contrat mère/fille (REST + JWT + états + retry/rollback).
5. Baseline infra OVH.
6. Standards scripts ops.
7. Backup/restore avec objectifs RPO/RTO.
8. Politique debug beta/prod.
9. QA minimum (outils + couverture).
10. Règles d’autonomie Codex.
11. Stack monitoring V1 fixée (Netdata + Uptime Kuma + email SMTP OVH).
12. Stratégie secrets runtime fixée (Symfony Secrets, puis Vault si besoin externe/réglementaire).
13. SonarQube reporté en phase 2 (hors phase 1).
14. Compatibilité projets fils fixée (contrat strict limité au tenant admin user).
15. Matrice des ports validée (avec SSH custom pré-prod + proxy/auth Netdata/Uptime Kuma).

## 3) Points à clarifier (ouverts)

1. Aucun point ouvert.

## 4) Points de vigilance validés

1. SSH/root:
   - root login maintenu en phase 1 (risque accepté),
   - hardening pré-prod prévu: port SSH custom + revue finale de la policy root.
2. Reboot:
   - décision validée: reboot serveur hebdomadaire,
   - évolution possible: reboot conditionnel en phase suivante.
3. Onboarding password:
   - décision validée: mot de passe non collecté au signup démo,
   - mot de passe créé lors de l’onboarding via lien 24h.
4. Artefact prod:
   - décision validée: exclusion des dossiers debug/fixtures/tests de l’artefact prod,
   - tests conservés sur `main` pour exécution CI/QA.

## 5) Verdict exécution autonome

Niveau d’autonomie actuel: `élevé` pour phase build MVP local/beta.  
Conditions pour autonomie complète jusqu’à prod:
1. Toutes les conditions phase 1 sont validées.
