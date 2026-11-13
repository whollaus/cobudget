# CoBudget

[English](../README.md) · [Deutsch](readme.de.md) · **Français** · [Español](readme.es.md)

**Comprendre ses dépenses. Partager les frais équitablement. Garder une vue d’ensemble dans Nextcloud.**

CoBudget aide les particuliers, les couples, les familles et les colocataires à
suivre leur budget au quotidien. Enregistrez vos revenus et dépenses, anticipez les
paiements à venir et retrouvez votre part des frais communs. Les espaces partagés
conviennent au foyer, aux voyages ou aux petits groupes. Justificatifs et rappels
restent associés aux paiements concernés.

> [!WARNING]
> **Bêta — version de développement actuelle : 0.8.8.** Faites des sauvegardes
> régulières et ne conservez pas vos données financières importantes uniquement
> dans CoBudget. Le développement est assisté par l’IA. Les tests automatisés ne
> remplacent pas un audit de sécurité indépendant, qui n’a pas encore été réalisé.
> Voir la [politique de sécurité](../SECURITY.md).

## Au quotidien, seul ou à plusieurs

- **Suivre ses finances :** revenus et dépenses, catégories, partenaires de paiement,
  libellés et tags.
- **Anticiper :** paiements récurrents, objectifs budgétaires, rapports, comparaisons
  et prévisions.
- **Partager équitablement :** indiquez qui a payé, choisissez la répartition et
  soldez les comptes. Votre vue personnelle compte votre part ; l’espace partagé
  affiche le montant total.
- **Garder le contexte :** justificatifs dans Nextcloud Files et rappels autonomes
  ou liés à un paiement, avec suivi de leur réalisation et archivage.
- **Choisir ses notifications :** Nextcloud, conversation privée CoBudget dans Talk
  et e-mail facultatif, activables séparément selon l’événement. Les rappels peuvent
  aussi être synchronisés avec un calendrier ; les modifications d’un calendrier
  privé sont répercutées dans CoBudget.
- **Réutiliser ses données :** impression de rapports, export CSV, copie vers un
  tableur, exports personnels et sauvegardes administrateur.
- **Adapter l’interface :** jeux de données séparés grâce aux espaces de travail,
  affichage mobile ou bureau et thème clair, sombre ou système.

L’interface est disponible en anglais, allemand, français et espagnol.
La documentation technique complémentaire est en anglais :
[fonctionnalités](../FEATURES.md), [calcul des rapports](reports.md) et
[concept de CoBudget](concept.md).

## Comment CoBudget est organisé

CoBudget s’organise autour des paiements, des parts personnelles et des espaces
partagés. Votre budget personnel présente vos propres frais ; l’espace partagé
conserve le total, la personne qui a payé et les sommes dues entre membres.
CoBudget ne se connecte pas aux comptes bancaires et ne synchronise pas les
opérations bancaires. Les rapports reposent sur les paiements enregistrés et leur
répartition. Leur solde est celui de votre budget, pas un solde bancaire rapproché.

## Prérequis et version 1.0

La bêta actuelle prend en charge **Nextcloud 33 à 35**. **CoBudget 1.0 nécessitera
Nextcloud 34 au minimum. La prise en charge de Nextcloud 33 s’arrête avec la série
0.x.** Les tests prévus pour la 1.0 couvrent Nextcloud 34 et 35 ; aucune date de
sortie n’est fixée.

Utilisez une version de PHP compatible avec votre Nextcloud (8.2 minimum pour
33/34, 8.3 pour 35), un navigateur récent et des tâches de fond fonctionnelles.
Talk, le calendrier et l’e-mail sont facultatifs. Les e-mails utilisent l’adresse
du compte Nextcloud ; l’envoi de courrier doit être configuré sur le serveur.
Voir le [bilan de préparation à la 1.0](release-readiness-1.0.md) et les
[tests](../tests/README.md).

## Installation et mises à jour

Installez CoBudget depuis la gestion des applications de Nextcloud via
l’[App Store](https://apps.nextcloud.com/apps/cobudget), ou utilisez
`occ app:install cobudget`. Pour une installation manuelle :

1. Téléchargez le fichier signé `cobudget.tar.gz` depuis les [versions GitHub](https://github.com/whollaus/cobudget/releases).
2. Extrayez-le dans `apps/` ou `custom_apps/`, sous le nom de dossier `cobudget`.
3. Activez l’application et terminez toute mise à jour en attente.

Les archives de code source générées automatiquement par GitHub ne sont pas des
paquets installables. Sauvegardez avant toute mise à jour. Exécutez `cron.php`
toutes les cinq minutes avec le compte du serveur web. Voir le
[guide d’exploitation](operations.md).

## Que contient une sauvegarde ?

Un **export personnel** conserve votre propre perspective financière. Il ne peut
être importé que dans un compte sans données CoBudget ; les parts communes
deviennent des paiements personnels indépendants. Une **sauvegarde complète
administrateur** contient les données financières de tous les utilisateurs de
CoBudget et leurs paramètres transférables. Sa restauration remplace les données
actuelles de l’application.

**Les fichiers justificatifs ne sont pas inclus : seuls leurs chemins sont
conservés.** Utilisez votre solution de sauvegarde habituelle pour Nextcloud Files
pour protéger ces fichiers. Cette responsabilité reste volontairement séparée.
Ces archives ne remplacent pas une sauvegarde complète de Nextcloud. CoBudget
bloque temporairement l’accès à l’application pendant la sauvegarde et la
restauration. Si une opération est en cours, réessayez après sa fin. Rechargez
les onglets CoBudget ouverts après une restauration. Voir la
[procédure de restauration](operations.md#backup-and-recovery).

## Retours et soutien

Les signalements de bugs et les résultats de tests sont particulièrement utiles
pendant la bêta. CoBudget est principalement maintenu par une personne ; discutez
au préalable des contributions de code.
[GitHub Issues](https://github.com/whollaus/cobudget/issues) · [Contribuer](../CONTRIBUTING.md) ·
[Historique](../CHANGELOG.md) · [Signaler une vulnérabilité](../SECURITY.md).

Soutenez le développement sur [GitHub Sponsors](https://github.com/sponsors/whollaus)
ou [Stripe](https://donate.stripe.com/aFaeVdfARbPNe897bu5J600).
Licence : [AGPL-3.0 ou ultérieure](../LICENSE).
