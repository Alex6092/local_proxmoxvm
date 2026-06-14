<?php
// This file is part of the local_proxmoxvm plugin for Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * French strings for local_proxmoxvm.
 *
 * @package    local_proxmoxvm
 * @copyright  2026 Alexandre Gremont
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apihost'] = 'Hôte Proxmox';
$string['apihost_desc'] = 'Nom d\'hôte ou IP du nœud Proxmox qui répond à l\'API (ex. le nœud principal 172.29.255.1).';
$string['apiport'] = 'Port de l\'API';
$string['autostart'] = 'Démarrer la VM après création';
$string['autostart_desc'] = 'Allumer automatiquement la VM une fois clonée et configurée.';
$string['clusterfull_body'] = 'Une machine virtuelle n\'a pas pu être créée car aucun nœud Proxmox ne dispose actuellement d\'assez de mémoire libre. La demande a été mise en file d\'attente et sera réessayée automatiquement. Libérez des ressources ou ajoutez de la capacité.';
$string['clusterfull_subject'] = 'Cluster Proxmox plein : création de VM en attente';
$string['cohorts'] = 'Cohortes de provisionnement';
$string['cohorts_desc'] = 'Les utilisateurs ajoutés à l\'une de ces cohortes reçoivent automatiquement une VM ; les retirer de toutes ces cohortes supprime leur VM automatique.';
$string['cores'] = 'Cœurs vCPU';
$string['cores_desc'] = 'Nombre de cœurs CPU virtuels attribués à chaque VM.';
$string['enableconsole'] = 'Activer la console intégrée';
$string['enableconsole_desc'] = 'Permettre aux utilisateurs d\'ouvrir la console noVNC de leur VM depuis Moodle (nécessite que le navigateur puisse joindre l\'hôte Proxmox).';
$string['errorapi'] = 'Erreur de l\'API Proxmox : {$a}';
$string['errorconnection'] = 'Impossible de se connecter à l\'API Proxmox : {$a}';
$string['errornocapacity'] = 'Aucun nœud Proxmox ne dispose actuellement d\'assez de mémoire libre pour héberger une nouvelle VM.';
$string['errornotconfigured'] = 'Le plugin Proxmox VM n\'est pas configuré (hôte, identifiant de jeton et secret sont requis).';
$string['errorsnapquota'] = 'Limite de snapshots atteinte ({$a} maximum). Supprimez d\'abord un snapshot existant.';
$string['errortask'] = 'La tâche Proxmox a échoué : {$a}';
$string['errortasktimeout'] = 'Délai dépassé en attendant la fin d\'une tâche Proxmox.';
$string['memory'] = 'RAM (Mo)';
$string['memory_desc'] = 'Quantité de RAM en mégaoctets attribuée à chaque VM.';
$string['messageprovider:clusterfull'] = 'Notifications de cluster Proxmox plein';
$string['nodemap'] = 'Nœuds et templates';
$string['nodemap_desc'] = 'Une ligne par nœud disponible pour le provisionnement, au format <code>nœud|vmidtemplate|stockage</code> (un quatrième champ optionnel <code>|maxvms</code> est réservé). Les lignes commençant par # sont ignorées. Exemple :<br><code>pve-node1|9000|local-lvm</code><br><code>pve-node2|9001|local-lvm</code>';
$string['pluginname'] = 'Proxmox VM';
$string['privacy:metadata:local_proxmoxvm'] = 'Informations sur la VM Proxmox provisionnée pour chaque utilisateur.';
$string['privacy:metadata:local_proxmoxvm:ipaddress'] = 'La dernière adresse IP connue de la VM de l\'utilisateur.';
$string['privacy:metadata:local_proxmoxvm:node'] = 'Le nœud Proxmox hébergeant la VM de l\'utilisateur.';
$string['privacy:metadata:local_proxmoxvm:timecreated'] = 'La date de création de l\'enregistrement de la VM.';
$string['privacy:metadata:local_proxmoxvm:userid'] = 'L\'identifiant de l\'utilisateur propriétaire de la VM.';
$string['privacy:metadata:local_proxmoxvm:vmid'] = 'Le VMID Proxmox de la VM de l\'utilisateur.';
$string['privacy:metadata:proxmox'] = 'Pour créer et gérer la VM, le nom d\'utilisateur sert à nommer la VM sur le cluster Proxmox.';
$string['privacy:metadata:proxmox:username'] = 'Le nom d\'utilisateur Moodle, utilisé pour construire un nom de VM traçable.';
$string['proxmoxvm:console'] = 'Accéder à la console intégrée de sa propre VM';
$string['proxmoxvm:control'] = 'Allumer/éteindre et gérer les snapshots de sa propre VM';
$string['proxmoxvm:manage'] = 'Gérer les VM des autres utilisateurs et créer des VM supplémentaires';
$string['proxmoxvm:view'] = 'Voir les informations de sa propre VM';
$string['settings_connection'] = 'Connexion Proxmox';
$string['settings_connection_desc'] = 'Identifiants utilisés par Moodle pour dialoguer avec l\'API Proxmox. Utilisez un jeton d\'API dédié avec un rôle au moindre privilège.';
$string['settings_console'] = 'Console';
$string['settings_provisioning'] = 'Provisionnement';
$string['snapshotquota'] = 'Quota de snapshots';
$string['snapshotquota_desc'] = 'Nombre maximum de snapshots qu\'un utilisateur peut conserver par VM (0 = illimité).';
$string['task_deprovision'] = 'Supprimer une VM Proxmox';
$string['task_provision'] = 'Créer une VM Proxmox';
$string['task_reconcile'] = 'Rafraîchir l\'état des VM Proxmox';
$string['tokenid'] = 'Identifiant du jeton d\'API';
$string['tokenid_desc'] = 'Identifiant complet du jeton au format <code>utilisateur@realm!nomjeton</code> (ex. <code>moodle@pve!moodle</code>).';
$string['tokensecret'] = 'Secret du jeton d\'API';
$string['tokensecret_desc'] = 'Le secret (UUID) affiché une seule fois lors de la création du jeton d\'API.';
$string['verifytls'] = 'Vérifier le certificat TLS';
$string['verifytls_desc'] = 'Vérifier le certificat TLS de Proxmox. À désactiver uniquement si le cluster utilise un certificat auto-signé que Moodle ne peut pas valider.';

// M2 - tableau de bord & contrôles utilisateur.
$string['action_reboot'] = 'Redémarrer';
$string['action_start'] = 'Allumer';
$string['action_stop'] = 'Éteindre';
$string['actionqueued'] = 'Commande envoyée à la VM.';
$string['allvms'] = 'Toutes les machines virtuelles';
$string['col_actions'] = 'Actions';
$string['col_node'] = 'Nœud';
$string['col_origin'] = 'Origine';
$string['col_state'] = 'État';
$string['col_user'] = 'Utilisateur';
$string['confirmdeletevm'] = 'Supprimer définitivement la VM de {$a} ? Cela détruit la machine virtuelle dans Proxmox.';
$string['confirmrollback'] = 'Restaurer la VM au snapshot « {$a} » ? La VM sera arrêtée puis redémarrée, et toute modification effectuée depuis ce snapshot sera perdue.';
$string['confirmsnapshotdelete'] = 'Supprimer le snapshot « {$a} » ?';
$string['createextravm'] = 'Créer une VM supplémentaire';
$string['createsnapshot'] = 'Créer un snapshot';
$string['deletesnapshot'] = 'Supprimer';
$string['deletevm'] = 'Supprimer la VM';
$string['field_description'] = 'Description';
$string['field_ip'] = 'Adresse IP';
$string['field_ssh'] = 'SSH';
$string['field_vmid'] = 'ID de la VM';
$string['ipunknown'] = 'Pas encore disponible';
$string['managevms'] = 'Gérer les VM';
$string['mymachine'] = 'Ma machine virtuelle';
$string['noeligibleusers'] = 'Aucun utilisateur trouvé dans les cohortes de provisionnement.';
$string['nosnapshots'] = 'Aucun snapshot.';
$string['novm'] = 'Vous n\'avez pas encore de machine virtuelle.';
$string['novmsyet'] = 'Aucune machine virtuelle n\'a encore été créée.';
$string['origin_auto'] = 'Automatique (cohorte)';
$string['origin_manual'] = 'Manuelle (enseignant)';
$string['quotareached'] = 'Quota de snapshots atteint. Supprimez-en un pour en créer un nouveau.';
$string['recordnotfound'] = 'Machine virtuelle introuvable, ou elle ne vous appartient pas.';
$string['rollback'] = 'Restaurer';
$string['rollbackqueued'] = 'Restauration lancée en arrière-plan. La VM va redémarrer.';
$string['snapshotcreated'] = 'Snapshot créé.';
$string['snapshotdeleted'] = 'Snapshot supprimé.';
$string['snapshotname'] = 'Nom du snapshot';
$string['snapshots'] = 'Snapshots';
$string['sshuser'] = 'Nom d\'utilisateur SSH';
$string['sshuser_desc'] = 'Nom d\'utilisateur affiché dans l\'indication « ssh user@ip » sur la page utilisateur (laisser vide pour masquer).';
$string['state_deprovisioning'] = 'Suppression';
$string['state_error'] = 'Erreur';
$string['state_pending'] = 'En attente';
$string['state_provisioning'] = 'Création';
$string['state_ready'] = 'Prête';
$string['status_running'] = 'Allumée';
$string['status_stopped'] = 'Éteinte';
$string['status_unknown'] = 'Inconnu';
$string['task_snapshotrollback'] = 'Restaurer un snapshot de VM';
$string['vmcreatequeued'] = 'Création de la VM demandée.';
$string['vmdeletequeued'] = 'Suppression de la VM demandée.';
$string['vmerror'] = 'Un problème est survenu lors de la création de votre machine virtuelle. Un administrateur a été notifié.';
$string['vmpending'] = 'Votre machine virtuelle est en cours de création. Revenez dans quelques minutes.';

// Snapshot initial / réinitialisation.
$string['confirmreset'] = 'Réinitialiser la VM à son état initial ? Tout votre travail sur cette VM sera définitivement perdu.';
$string['errorcannotdeleteinitial'] = 'Le snapshot initial est protégé et ne peut pas être supprimé.';
$string['errorreservedname'] = 'Ce nom de snapshot est réservé. Veuillez en choisir un autre.';
$string['resetqueued'] = 'Réinitialisation lancée en arrière-plan. La VM va être restaurée puis redémarrée.';
$string['resetvm'] = 'Réinitialiser la VM';
$string['resetvm_help'] = 'Restaure la VM à son état initial (utile si elle ne démarre plus). Vos modifications seront perdues.';
