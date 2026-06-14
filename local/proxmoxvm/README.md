# local_proxmoxvm — VM Proxmox par utilisateur Moodle

Plugin Moodle (`local`) qui provisionne une machine virtuelle Proxmox par étudiant
et lui en donne le contrôle. Développé pour **Moodle 5.2+** et **Proxmox VE 6.4+**.

> État : **M1 — backend & cycle de vie**. Le provisionnement, la suppression et la
> logique de contrôle (allumage, snapshots…) existent côté serveur. L'**interface
> utilisateur** (page + icône navbar) arrive en **M2**, la **console intégrée** en **M3**.

## Ce que fait le M1

- Quand un utilisateur est **ajouté à une cohorte** déclencheuse → une VM est créée
  en **clone lié** d'un template, sur le **nœud le moins chargé** (par RAM libre).
- Quand il est **retiré de toutes** les cohortes déclencheuses, ou que le **compte est
  supprimé** → la VM est **arrêtée puis supprimée**.
- Création/suppression **asynchrones** (tâches adhoc) : l'ajout en cohorte n'est jamais
  bloqué par Proxmox.
- **Cluster plein** → la demande reste en file (réessais automatiques) et les
  enseignants reçoivent une **notification Moodle**.
- Tâche planifiée (toutes les 5 min) qui rafraîchit **statut + IP** (via guest agent).

## Prérequis côté Proxmox

### 1. Templates (un par nœud)

Sur chaque nœud pouvant héberger des VM, préparez un template :

- **`qemu-guest-agent` installé et activé** dans l'OS invité (indispensable pour
  remonter l'IP et faire les arrêts propres). Le plugin force `agent: 1` au clonage.
- Stocké sur du **LVM-Thin** (ou un autre stockage compatible **clones liés**).
- Converti en template : `qm template <vmid>`.
- Serveur **SSH** opérationnel + un moyen pour l'étudiant de s'authentifier
  (cloud-init avec utilisateur/clé, ou compte pré-installé).
- ⚠️ Les clones liés ne **changent pas de nœud** : chaque nœud a donc son propre template.

### 2. Utilisateur et jeton d'API (moindre privilège)

Sur le nœud principal :

```bash
# Rôle au moindre privilège
pveum role add MoodleVM -privs "VM.Allocate VM.Clone VM.Config.Disk VM.Config.CPU \
  VM.Config.Memory VM.Config.Network VM.Config.Options VM.Config.Cloudinit \
  VM.PowerMgmt VM.Snapshot VM.Audit VM.Console Datastore.AllocateSpace \
  Datastore.Audit Sys.Audit"

# Utilisateur dédié
pveum user add moodle@pve

# Attribution du rôle (sur / ; vous pouvez restreindre à un pool dédié)
pveum acl modify / -user moodle@pve -role MoodleVM

# Jeton SANS séparation de privilèges (hérite des droits de l'utilisateur)
pveum user token add moodle@pve moodle --privsep 0
```

La dernière commande affiche :
- **full-tokenid** : `moodle@pve!moodle` → à mettre dans *Identifiant du jeton d'API*.
- **value** : l'UUID secret → à mettre dans *Secret du jeton d'API* (affiché **une seule fois**).

> Pour cloisonner davantage : créez un pool (ex. `students`), placez-y templates et VM,
> et faites `pveum acl modify /pool/students -user moodle@pve -role MoodleVM`
> (gardez `Sys.Audit` sur `/nodes` pour la sélection de nœud).

## Installation du plugin

1. Copiez le dossier `local/proxmoxvm` dans le webroot de votre Moodle :
   `…/moodle/public/local/proxmoxvm` (Moodle 5.2 utilise le répertoire `public/`).
2. Connecté en admin, allez dans **Administration du site → Notifications** pour lancer
   l'installation (création de la table `local_proxmoxvm`).
3. Configurez : **Administration du site → Plugins → Plugins locaux → Proxmox VM**.

## Configuration

| Réglage | Exemple |
|---|---|
| Hôte Proxmox | `172.29.255.1` |
| Port de l'API | `8006` |
| Identifiant du jeton d'API | `moodle@pve!moodle` |
| Secret du jeton d'API | *(l'UUID)* |
| Vérifier le certificat TLS | décoché si certificat auto-signé |
| Nœuds et templates | une ligne par nœud (voir ci-dessous) |
| Cœurs vCPU / RAM (Mo) | `1` / `1024` |
| Démarrer la VM après création | coché |
| Cohortes de provisionnement | sélectionnez vos cohortes-classes |

**Format « Nœuds et templates »** — `nœud|vmidtemplate|stockage` :

```
pve-node1|9000|local-lvm
pve-node2|9001|local-lvm
```

## Tester le M1 (sans interface)

1. Créez une cohorte de test et cochez-la dans les réglages du plugin.
2. Ajoutez un utilisateur de test à cette cohorte.
3. Exécutez le cron (ou directement le runner de tâches adhoc) depuis la racine Moodle :
   ```bash
   php admin/cli/cron.php
   # ou, pour ne traiter que les tâches adhoc :
   php admin/cli/adhoc_task.php --execute
   ```
4. Vérifiez : la VM apparaît dans Proxmox ; en base, la ligne de `mdl_local_proxmoxvm`
   passe à `provisionstate = ready` avec `ipaddress` renseignée.
5. Retirez l'utilisateur de la cohorte, relancez le runner → la VM est supprimée et la
   ligne disparaît.

### Diagnostic

- Tâches adhoc en attente / en échec : **Administration du site → Serveur → Tâches →
  Tâches adhoc**. Un échec (ex. cluster plein) est réessayé avec back-off croissant.
- Logs d'erreur : activez le mode développeur pour voir les messages `debugging()` des
  observateurs.

## Dépannage

**Outil de diagnostic** — page web (admin) `/local/proxmoxvm/diagnose.php`, ou en CLI
`php public/local/proxmoxvm/cli/diagnose.php`. Elle teste la connexion, liste les nœuds vus
par le jeton avec leur RAM, et croise avec le mapping. Les tâches en échec sont visibles dans
*Administration du site → Serveur → Tâches → Tâches adhoc*.

**« Aucun nœud ne dispose d'assez de mémoire » alors qu'il reste de la RAM** — le jeton d'API
n'a pas le privilège **`Sys.Audit`**. Sans lui, Proxmox renvoie `mem`/`maxmem` à `0` pour les
nœuds et le sélecteur les croit pleins. Le diagnostic l'indique (« RAM à 0 sur tous les
nœuds »). Correctif : ajouter `Sys.Audit` au rôle et accorder le rôle **au jeton lui-même**
(cf. la section ACL plus haut — important si la séparation de privilèges du jeton est activée).

## Utilisation (M2 — espace utilisateur)

- **Icône dans la barre du haut** (à côté des notifications) → page **Ma machine virtuelle**
  (`/local/proxmoxvm/index.php`) : statut, IP, indication SSH, boutons Allumer / Éteindre /
  Redémarrer, et gestion des snapshots (créer sans RAM / restaurer / supprimer). Le statut
  s'actualise automatiquement (toutes les 20 s).
- **Page enseignant** *Gérer les VM* (`/local/proxmoxvm/manage.php`, capability
  `local/proxmoxvm:manage`) : liste de toutes les VM, suppression, et création d'une VM
  **supplémentaire** pour un étudiant des cohortes de provisionnement.
- Réglage **Nom d'utilisateur SSH** : affiche l'indication `ssh user@ip` sur la page.

> Les restaurations de snapshot s'exécutent en **tâche de fond** (arrêt → restauration →
> redémarrage) via le cron Moodle ; les autres actions sont immédiates.

## Limites connues

- Console intégrée (noVNC) → **M3**.
