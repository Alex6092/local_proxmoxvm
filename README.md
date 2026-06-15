# local_proxmoxvm — une VM Proxmox par utilisateur Moodle

Plugin Moodle (type `local`) qui **provisionne automatiquement une machine virtuelle Proxmox
par étudiant** et lui en donne le contrôle depuis Moodle : statut, adresse IP, mot de passe,
allumage / arrêt / redémarrage, snapshots, réinitialisation, et **console noVNC intégrée**.

Développé pour **Moodle 5.2+** (arborescence `public/`) et **Proxmox VE 6.4+**.

> État : **fonctionnel** (v0.4.0) — provisionnement, espace utilisateur, espace enseignant,
> console et mot de passe par VM sont en place.

## Fonctionnalités

- **Provisionnement par cohorte** : ajouter un étudiant à une cohorte crée une VM en
  **clone lié** d'un template, sur le **nœud le moins chargé** ; le retirer la supprime.
  Entièrement **asynchrone** (tâches adhoc), donc jamais bloquant pour Moodle.
- **Espace utilisateur** (icône dans la barre du haut) : statut temps réel, IP, indication
  SSH, **mot de passe unique chiffré** par VM, boutons Allumer / Éteindre / Redémarrer,
  **snapshots** (créer sans RAM / restaurer / supprimer) et **réinitialisation** à un
  snapshot initial protégé.
- **Console noVNC intégrée** sans exposer Proxmox : le navigateur ne parle qu'à Moodle, qui
  relaie le websocket VNC en interne, avec **isolation stricte par VM**.
- **Espace enseignant** : liste de toutes les VM, suppression, création de VM supplémentaires.
- **Cluster plein** → mise en file d'attente + notification Moodle aux enseignants.

## Prérequis

- **Moodle 5.2+** (PHP 8.2+).
- **Proxmox VE 6.4+**, stockage compatible **clones liés** (LVM-Thin, ZFS, qcow2…).
- Templates **cloud-init** avec **qemu-guest-agent**.
- Un **jeton d'API Proxmox** au moindre privilège.
- Pour la console : un **reverse proxy** (nginx ou Apache) devant Moodle.

## Installation rapide

```bash
git clone https://github.com/Alex6092/local_proxmoxvm.git
# copier le plugin dans le webroot de Moodle :
cp -r local_proxmoxvm/local/proxmoxvm /chemin/vers/moodle/public/local/
```

Puis *Administration du site → Notifications* pour lancer l'installation, et configurer le
plugin dans *Administration du site → Plugins → Plugins locaux → Proxmox VM*.

👉 **Guide de déploiement complet** (création du rôle/jeton Proxmox, templates cloud-init,
réglages, reverse proxy pour la console, mot de passe par VM, dépannage) :
**[`local/proxmoxvm/README.md`](local/proxmoxvm/README.md)**.

## Architecture (en bref)

| Couche | Rôle |
|---|---|
| Observateurs d'événements | cohorte ↔ VM → mettent en file des tâches adhoc |
| Tâches adhoc / cron | clone lié, configuration, suppression, réconciliation statut/IP |
| Client REST | dialogue avec l'API Proxmox (authentification par jeton) |
| Pages Moodle | tableau de bord étudiant, page enseignant, console, diagnostic |
| Reverse proxy | relaie le websocket VNC (jeton injecté, Proxmox jamais exposé) |

## Sécurité

- Le jeton Proxmox (privilégié) reste **côté serveur**, jamais transmis au navigateur.
- Vérification d'**appartenance** à chaque action : un étudiant ne pilote que **sa** VM.
- Mot de passe de VM **chiffré** en base (API `\core\encryption` de Moodle).
- Console : ticket VNC **par VM** + reverse proxy **restreint** au seul endpoint
  `vncwebsocket`, donc le jeton injecté n'est pas exploitable pour le reste de l'API.

## Structure du dépôt

```
local/proxmoxvm/          # le plugin Moodle
├── classes/              # client Proxmox, logique métier, tâches, observateurs, RGPD, diagnostic
├── db/                   # schéma, événements, tâches, capabilities, messages, upgrade
├── lang/{en,fr}/         # chaînes anglaises + françaises
├── templates/            # tableau de bord (Mustache)
├── thirdparty/novnc/     # client noVNC (à déposer — voir le README du plugin)
├── cli/diagnose.php      # diagnostic en ligne de commande
├── index.php manage.php console.php diagnose.php   # pages
├── settings.php version.php lib.php                # plomberie Moodle
└── README.md             # guide de déploiement détaillé
```

## Licence

Plugin sous **GNU GPL v3 ou ultérieure** (comme Moodle). Le client **noVNC** embarqué est
distribué sous licence **MPL-2.0**.
