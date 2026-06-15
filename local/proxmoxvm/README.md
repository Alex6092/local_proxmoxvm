# local_proxmoxvm — VM Proxmox par utilisateur Moodle

Plugin Moodle (`local`) qui provisionne une machine virtuelle Proxmox par étudiant
et lui en donne le contrôle. Développé pour **Moodle 5.2+** et **Proxmox VE 6.4+**.

> État : **M1 + M2 + M3 complets** (v0.4.0). Provisionnement par cohorte (clone lié,
> nœud le moins chargé, async), espace utilisateur (icône navbar, statut/IP/SSH/mot de
> passe, allumage/arrêt/redémarrage, snapshots, réinitialisation à l'état initial),
> console noVNC intégrée, et mot de passe unique chiffré par VM.

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

### 1. Templates cloud-init (un par nœud)

Chaque nœud pouvant héberger des VM a besoin de **son propre** template (les clones liés ne
changent pas de nœud), idéalement **cloud-init** (pour l'IP en DHCP et le mot de passe unique
par VM). Recette à partir d'une image cloud officielle :

```bash
# Sur le nœud : récupérer une image cloud (cloud-init déjà inclus)
cd /var/lib/vz/template
wget https://cloud.debian.org/images/cloud/bookworm/latest/debian-12-genericcloud-amd64.qcow2

# (recommandé) installer le guest agent + autoriser le SSH par mot de passe DANS l'image
apt install -y libguestfs-tools
virt-customize -a debian-12-genericcloud-amd64.qcow2 \
  --install qemu-guest-agent \
  --run-command 'sed -i "s/^#\?PasswordAuthentication.*/PasswordAuthentication yes/" /etc/ssh/sshd_config'

# Assembler la VM (VMID libre, ex. 9000)
qm create 9000 --name debian12-ci --memory 1024 --cores 1 \
  --net0 virtio,bridge=vmbr0 --ostype l26 --agent enabled=1 --scsihw virtio-scsi-pci
qm importdisk 9000 debian-12-genericcloud-amd64.qcow2 local-lvm
qm set 9000 --scsi0 local-lvm:vm-9000-disk-0
qm set 9000 --boot order=scsi0       # commande séparée : le disque doit exister AVANT le boot order
qm set 9000 --ide2 local-lvm:cloudinit
qm set 9000 --ciuser debian          # PAS de --cipassword : le plugin le pose par VM
qm set 9000 --ipconfig0 ip=dhcp      # IP en DHCP, remontée ensuite par le guest agent
```

Puis **deux cas selon la taille de disque voulue** :

**A. La taille native de l'image suffit (~3 Go) — le plus simple** : convertir directement,
sans démarrer.
```bash
qm template 9000
```

**B. Tu veux un disque plus grand (ex. 10 Go)** : redimensionner **PUIS démarrer une fois**
pour que cloud-init agrandisse la partition et répare la table GPT, puis réinitialiser
cloud-init, puis convertir.
```bash
qm resize 9000 scsi0 10G
qm start 9000
sleep 60                                       # laisser cloud-init agrandir/réparer le disque
qm guest exec 9000 -- cloud-init clean --logs  # remet cloud-init à zéro → les clones le rejoueront
qm shutdown 9000 --timeout 60
qm template 9000
```

> ⚠️ **Ne JAMAIS faire `qm resize` puis `qm template` sans démarrer entre les deux.** Le disque
> se retrouve agrandi mais avec une **table GPT incohérente** (header de secours resté à
> l'ancienne taille — `GPT: Alternate GPT header not at the end of the disk`). Les clones
> héritent de ce GPT cassé et **paniquent au boot de façon intermittente**
> (`Attempted to kill init!`). Le démarrage + `cloud-init clean` produit un disque propre.

**À retenir :**
- `qemu-guest-agent` installé + `--agent enabled=1` → remontée de l'IP et arrêts propres.
- Stockage **LVM-Thin** (ou autre compatible **clones liés**).
- `--ciuser` doit correspondre au réglage *Nom d'utilisateur SSH* du plugin (ex. `debian`).
- **Vérifier un template avant de s'en servir** : clone-le une fois en VM normale, démarre-la
  3-4 fois → aucun kernel panic.
- Mets le VMID du template dans le mapping du plugin pour ce nœud (`proxmox5|9000|local-lvm`).
- Pour **réparer un template existant** sans repartir de zéro : reconstruis-en un avec un
  **nouveau VMID** (la recette ci-dessus) et pointe le mapping dessus — inutile de toucher au
  template cassé (Proxmox refuse de le supprimer tant qu'il a des clones liés).

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

## Console intégrée (M3)

**Architecture** — le navigateur ne parle **qu'à Moodle** ; Proxmox n'est jamais exposé.
Le navigateur ouvre un websocket vers un chemin **même origine** de Moodle (par défaut
`/pvews`), que ton serveur web **relaie vers le `vncwebsocket` de Proxmox en injectant le
token API**. Le ticket VNC est généré par Moodle **uniquement pour la VM de l'étudiant**
(vérification d'appartenance), et le proxy est **restreint au seul chemin `vncwebsocket`**.

### 1. Déposer noVNC dans le plugin

```bash
cd .../public/local/proxmoxvm/thirdparty
git clone --depth 1 --branch v1.5.0 https://github.com/novnc/noVNC.git novnc
# => .../thirdparty/novnc/core/rfb.js doit exister (conserver core/ ET vendor/)
```

### 2. Reverse proxy sur le serveur web de Moodle

Remplace `TON_SECRET` (secret du token) et `172.29.255.1` (ton nœud principal).
⚠️ **Garde la regex restreinte** au chemin `vncwebsocket` — sinon le token injecté
deviendrait exploitable pour toute l'API. Protège la conf (lisible par root uniquement).

**nginx** — dans le bloc `server { }` HTTPS de Moodle :

```nginx
location ~ ^/pvews/(nodes/[^/]+/qemu/[0-9]+/vncwebsocket)$ {
    proxy_pass https://172.29.255.1:8006/api2/json/$1$is_args$args;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host 172.29.255.1:8006;
    proxy_set_header Authorization "PVEAPIToken=moodle@pve!moodleAPI=TON_SECRET";
    proxy_ssl_verify off;
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
}
```

**Apache** — `a2enmod proxy proxy_http proxy_wstunnel headers ssl` :

```apache
SSLProxyEngine on
SSLProxyVerify none
SSLProxyCheckPeerName off
<LocationMatch "^/pvews/nodes/[^/]+/qemu/[0-9]+/vncwebsocket$">
    RequestHeader set Authorization "PVEAPIToken=moodle@pve!moodleAPI=TON_SECRET"
</LocationMatch>
ProxyPassMatch "^/pvews/(nodes/[^/]+/qemu/[0-9]+/vncwebsocket)$" "wss://172.29.255.1:8006/api2/json/$1"
```

### 3. Côté plugin

- Le token doit avoir le privilège **VM.Console** (déjà dans le rôle plus haut).
- Réglages : **Activer la console intégrée** coché, **Chemin websocket** = `/pvews`
  (doit correspondre au proxy).
- Un bouton **Ouvrir la console** apparaît sur la VM (quand elle est allumée).

> Pas de mot de passe PVE ni d'exposition de Proxmox : l'auth se fait via le token injecté
> par le proxy, et le ticket VNC est limité à la VM de l'étudiant. La console fonctionne
> partout où l'étudiant joint Moodle (Moodle relaie en interne vers Proxmox).

## Mot de passe unique par VM (cloud-init)

Réglage **Définir un mot de passe unique par VM** (désactivé par défaut). Activé, chaque VM
reçoit à sa création un mot de passe aléatoire (14 caractères, sans caractères ambigus),
appliqué via **cloud-init `cipassword` avant le premier démarrage** — il est donc capturé
dans le snapshot `initial`, et une **réinitialisation conserve le même mot de passe**. Il est
stocké **chiffré** (API `\core\encryption` de Moodle, clé dans `moodledata/secret/`) et
affiché à l'étudiant sur sa page (sous « Afficher le mot de passe »).

Prérequis côté template :
- template **cloud-init** avec un utilisateur par défaut (`ciuser`) — fais-le correspondre au
  réglage *Nom d'utilisateur SSH* pour que l'indication `ssh user@ip` soit cohérente ;
- pour un login SSH par mot de passe : **`PasswordAuthentication yes`** dans le sshd de la VM.

> Les VM créées avant l'activation de l'option n'ont pas de mot de passe (rien n'est affiché) ;
> seules les nouvelles VM en reçoivent un.
