# Importer des paiements

[English](payment-imports.md) · [Deutsch](payment-imports.de.md) · **Français** · [Español](payment-imports.es.md)

Activez **Paramètres → Importation et API → Import de paiements** pour utiliser les deux méthodes. Cette option est désactivée par défaut. La désactiver suspend les imports depuis un dossier et conserve leurs paramètres et leur progression. Les imports actifs reprennent lorsque vous réactivez l’option.

Pour un import ponctuel, ouvrez **Import en masse**. Pour des imports réguliers, configurez un dossier. Les deux méthodes utilisent le même format JSON. Vous êtes le payeur pour les dépenses et le bénéficiaire pour les revenus. Dans un espace partagé, CoBudget applique la répartition habituelle de l’espace.

## Format du fichier

Dans la liste `payments`, créez un objet par paiement. Seuls les champs `date` et `amount` sont obligatoires. Un fichier peut contenir jusqu’à 5 000 paiements et peser jusqu’à 5 Mio (5 242 880 octets). Les montants sont exprimés dans la devise de votre compte. Pour un import automatique, la devise du compte lors de la validation du premier lot s’applique à tout le fichier, même si vous la modifiez avant la fin de l’import. Pour importer des revenus ou des paiements à une date future, activez l’option correspondante dans vos paramètres.

Cet exemple contient une dépense de courses et un versement de salaire. Adaptez les dates et les noms à vos données. Dans l’application, **Format d’importation** permet de copier ou de télécharger un exemple daté du jour selon votre heure locale.

```json
{
  "payments": [
    {
      "date": "2026-11-19",
      "amount": -42.75,
      "category": "Groceries",
      "payment_partner": "Corner Market",
      "description": "Weekly shopping #household",
      "review": 1
    },
    {
      "date": "2026-11-19",
      "amount": 1850.00,
      "category": "Salary",
      "payment_partner": "Employer",
      "description": "November salary"
    }
  ]
}
```

| Champ | Signification |
| --- | --- |
| `date` | Date du paiement au format `YYYY-MM-DD`. |
| `amount` | Montant non nul avec deux décimales au maximum, séparées par un point. Négatif pour une dépense, positif pour un revenu. |
| `category` | Nom de la catégorie pour ce type de paiement, jusqu’à 128 caractères. |
| `payment_partner` | Nom du bénéficiaire ou du payeur pour ce type de paiement, jusqu’à 128 caractères. |
| `description` | Texte de 512 caractères maximum, pouvant inclure des hashtags comme `#household`. |
| `important` | Paiement important. |
| `review` | Paiement à vérifier. |
| `fixed_cost` | Frais fixes (dépenses uniquement). |
| `children` | Paiement lié aux enfants. |
| `subscription` | Abonnement (dépenses uniquement). |
| `tax_relevant` | Paiement à prendre en compte pour les impôts. |
| `insurance` | Paiement d’assurance. |

Chaque étiquette de `important` à `insurance` accepte `1` (activée) ou `0` (désactivée). Une étiquette omise est désactivée. CoBudget applique les étiquettes activées dans vos paramètres.

## Import manuel en masse

1. Ouvrez **Import en masse**. Choisissez **Sans espace** pour vos paiements personnels ou sélectionnez un espace partagé.
2. Sélectionnez le fichier JSON ou collez son contenu, puis choisissez **Afficher l’aperçu**.
3. Vérifiez les montants, les dates et les éventuelles catégories ou partenaires de paiement à créer. Confirmez l’import pour enregistrer les paiements.

Pour vos paiements personnels ou dans un espace dont vous êtes propriétaire, CoBudget peut créer les catégories et partenaires de paiement manquants. Les autres membres doivent utiliser des noms existants pour le type de paiement concerné ou omettre ces champs. Si la vérification détecte un nom inconnu, le fichier entier est rejeté. **Format d’importation** indique les noms disponibles pour la destination choisie.

Après une interruption, affichez à nouveau l’aperçu des mêmes données pour la même destination. Il indique les paiements qu’il reste à enregistrer.

## Import automatique depuis un dossier

1. Ouvrez **Paramètres → Importation et API → Importations automatiques**. Sur la ligne de la destination souhaitée, choisissez un dossier dans Fichiers Nextcloud. À l’ouverture des paramètres, CoBudget prépare le dossier `CoBudget/Import`. Utilisez un dossier distinct par destination. Le dossier reste réservé lorsque l’import est en pause.
2. Choisissez l’intervalle (toutes les 5 ou 15 minutes, toutes les heures ou tous les jours) et les notifications (aucune, uniquement en cas d’erreur ou après chaque import), puis activez **Actif**. Dès qu’un import est actif, **Paramètres → Rappels et notifications** permet de choisir Nextcloud, l’e-mail ou votre conversation Talk personnelle, selon les services disponibles sur le serveur.
3. Déposez les fichiers `.json` complets directement dans le dossier choisi. Les tâches d’arrière-plan de Nextcloud vérifient le dossier à l’intervalle configuré. Pour le vérifier immédiatement, utilisez le bouton de lecture à côté de **Actif**. Un import volumineux peut nécessiter plusieurs passages.
4. Pour consulter les détails sous **Exécuté le**, survolez l’horodatage, sélectionnez-le au clavier ou touchez-le. Une icône rouge signale une erreur ou un fichier en attente. Dans le dossier d’import, les fichiers traités sont déplacés vers `Verarbeitet` et les fichiers rejetés vers `Fehler`. Sélectionnez le nom du fichier pour l’ouvrir dans un nouvel onglet.

Les imports de dossier nécessitent le verrouillage des fichiers de Nextcloud. Un fichier qui ne peut être lu ou déplacé reste dans le dossier. Si une source change pendant l’enregistrement des paiements et qu’un verrou ou des droits de lecture insuffisants empêchent CoBudget de vérifier sa nouvelle version, les nouveaux imports de ce dossier attendent cette vérification. Si seul le déplacement est bloqué, CoBudget peut rejeter les copies de la version modifiée et importer les autres fichiers.

L’archivage d’un espace masque son import automatique et suspend le traitement. La restauration de l’espace rétablit les réglages précédents. Une règle active reprend à la prochaine vérification.

CoBudget examine les fichiers ajoutés ou modifiés pendant une vérification lors d’un passage ultérieur. Vous pouvez continuer à déposer des fichiers pendant un import. Si la synchronisation modifie les paiements pendant le déplacement d’un fichier déjà comptabilisé vers `Verarbeitet`, CoBudget déplace le fichier modifié vers `Fehler` et rejette les copies suivantes. Après une interruption, CoBudget continue de rejeter les copies de ces paiements modifiés, même si vous avez déplacé le fichier archivé dans un autre dossier.

Si une archive contient déjà un fichier du même nom, les deux fichiers sont conservés. Si vous corrigez un fichier rejeté avant son déplacement vers `Fehler`, CoBudget vérifie le fichier corrigé lors d’un passage ultérieur. Si un autre fichier occupe l’emplacement prévu dans les archives après une interruption, il conserve son nom et peut toujours être importé. Cela vaut aussi si la synchronisation écrase le fichier archivé et que Nextcloud conserve son identifiant.

### Noms de fichiers, doublons et corrections

Choisissez un nom inédit **avant de déposer chaque nouveau lot**, par exemple `payments-2026-11-20.json`. Si vous modifiez la date d’un paiement dans un fichier déjà importé et le déposez sous le même nom, CoBudget rejette le fichier modifié. Le nombre indiqué dans le message correspond aux paiements enregistrés à partir du fichier d’origine. La copie rejetée reste bloquée même si vous la renommez ensuite. Pour corriger un paiement déjà enregistré, modifiez-le dans CoBudget. Si vous renommez un fichier dans le dossier pendant l’enregistrement des paiements, le nom d’origine et le nouveau nom restent réservés.

Si la validation rejette un brouillon avant tout enregistrement de paiement, vous pouvez le corriger et réutiliser son nom de fichier. Si une version est ensuite rejetée pour conflit de nom, les copies de cette version restent bloquées.

Si vous déposez à nouveau un lot identique avec le même compte, dans le même espace de travail et pour la même destination, les paiements déjà enregistrés ne sont pas dupliqués. Cela vaut aussi si le nom du fichier ou l’ordre des champs d’un paiement change. Pour réessayer, conservez l’ordre initial des paiements. Si deux exports se recoupent, retirez les paiements déjà fournis avant de déposer le nouveau lot.

Si une catégorie ou un partenaire de paiement disparaît en cours d’import, **Exécuté le** indique le fichier en attente, le nom manquant et le nombre de paiements enregistrés. Les autres fichiers continuent d’être traités. Demandez au propriétaire de l’espace de rétablir le nom d’origine. CoBudget reprend alors le fichier en attente et enregistre les paiements restants avec leurs quotes-parts.

Si le dossier a été supprimé puis recréé au même emplacement, sélectionnez-le à nouveau dans **Paramètres → Importation et API → Importations automatiques**. Une fois l’import actif, CoBudget peut terminer l’enregistrement des paiements sauvegardés même si le nouveau dossier est vide.
