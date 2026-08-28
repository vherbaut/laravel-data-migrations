# Laravel Data Migrations

> **[Read in English](README.md)**

[![Dernière version sur Packagist](https://img.shields.io/packagist/v/vherbaut/laravel-data-migrations.svg?style=flat-square)](https://packagist.org/packages/vherbaut/laravel-data-migrations)
[![Tests](https://img.shields.io/github/actions/workflow/status/vherbaut/laravel-data-migrations/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/vherbaut/laravel-data-migrations/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Téléchargements](https://img.shields.io/packagist/dt/vherbaut/laravel-data-migrations.svg?style=flat-square)](https://packagist.org/packages/vherbaut/laravel-data-migrations)
[![Licence](https://img.shields.io/packagist/l/vherbaut/laravel-data-migrations.svg?style=flat-square)](https://packagist.org/packages/vherbaut/laravel-data-migrations)

**Migrations de données versionnées pour Laravel.** Transformez, remplissez et migrez vos données avec la même élégance que les migrations de schéma.

---

## Table des matières

- [Pourquoi les migrations de données ?](#pourquoi-les-migrations-de-données-)
- [Migrations de données vs Seeders](#migrations-de-données-vs-seeders)
- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Démarrage rapide](#démarrage-rapide)
- [Commandes console](#commandes-console)
- [Écrire des migrations de données](#écrire-des-migrations-de-données)
- [Configuration](#configuration)
- [Fonctionnalités de sécurité](#fonctionnalités-de-sécurité)
- [Événements](#événements)
- [Exemples concrets](#exemples-concrets)
- [Architecture](#architecture)
- [Tests](#tests)
- [Bonnes pratiques](#bonnes-pratiques)
- [Contribution](#contribution)
- [Licence](#licence)

---

## Pourquoi les migrations de données ?

Les migrations de schéma de Laravel gèrent les changements de structure de base de données de manière élégante, mais qu'en est-il des transformations de données ? Actuellement, les développeurs recourent à :

- **Mettre la logique de données dans les migrations de schéma** — Mélange des responsabilités, difficile à annuler
- **Des commandes artisan ponctuelles** — Non versionnées, oubliées, impossibles à rejouer
- **Du SQL manuel en production** — Dangereux et non documenté

Les **migrations de données** résolvent ce problème en fournissant une approche structurée et versionnée des transformations de données.

---

## Migrations de données vs Seeders

Une question fréquente : *"Pourquoi ne pas simplement utiliser les Seeders Laravel ?"*

Les Seeders et les Migrations de données servent des **objectifs fondamentalement différents** :

| Aspect | Seeders | Migrations de données |
|--------|---------|----------------------|
| **Objectif** | Peupler des données de dev/test | Transformer des données de production |
| **Environnement** | Développement, test | Production, staging |
| **Suivi** | Aucun - peut s'exécuter plusieurs fois | Versionné - s'exécute une fois par environnement |
| **Rollback** | Non supporté | Support complet du rollback |
| **Historique** | Aucun enregistrement d'exécution | Piste d'audit complète (quand, lignes affectées, durée) |
| **Sync équipe** | Coordination manuelle | Automatique - comme les migrations de schéma |
| **Progression** | Aucun retour | Barres de progression, comptage des lignes |
| **Sécurité** | Aucune protection | Dry-run, confirmations, sauvegardes* |

_*La fonctionnalité de sauvegarde nécessite [spatie/laravel-backup](https://github.com/spatie/laravel-backup)_

### Quand utiliser les Seeders

```php
// Seeders : Peupler des données de test pour le développement
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->count(100)->create();  // Crée des utilisateurs fictifs
    }
}
```

Utilisez les seeders quand vous devez :
- Générer des données fictives pour le développement local
- Réinitialiser votre base de données à un état connu
- Créer des fixtures de test

### Quand utiliser les Migrations de données

```php
// Migrations de données : Transformer des vraies données de production
return new class extends DataMigration implements Reversible
{
    protected string $description = 'Migrer les valeurs de statut legacy vers le nouvel enum';

    public function up(): void
    {
        // Transforme les données de production existantes
        DB::table('orders')
            ->where('status', 'pending_payment')
            ->update(['status' => 'awaiting_payment']);

        $this->affected(DB::table('orders')->where('status', 'awaiting_payment')->count());
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('status', 'awaiting_payment')
            ->update(['status' => 'pending_payment']);
    }
};
```

Utilisez les migrations de données quand vous devez :
- Transformer des données de production existantes
- Remplir de nouvelles colonnes avec des valeurs calculées
- Normaliser ou nettoyer des données legacy
- Migrer des données entre des changements de schéma
- Garantir que tous les membres de l'équipe/environnements appliquent les mêmes changements

### Le problème avec l'utilisation des Seeders pour les transformations de données

```php
// NE FAITES PAS ÇA - Utiliser des seeders pour des changements de données en production
class FixUserEmailsSeeder extends Seeder
{
    public function run(): void
    {
        // Problèmes :
        // 1. Pas de suivi - peut s'exécuter deux fois et corrompre les données
        // 2. Pas de rollback si quelque chose se passe mal
        // 3. Pas de piste d'audit - quand a-t-il été exécuté ? Par qui ?
        // 4. Les membres de l'équipe ne savent pas s'ils doivent l'exécuter
        // 5. Pas de retour de progression sur les grands ensembles de données
        DB::table('users')->update([
            'email' => DB::raw('LOWER(email)')
        ]);
    }
}
```

**Les Migrations de données résolvent tous ces problèmes** en traitant les changements de données avec la même rigueur que les changements de schéma.

---

## Fonctionnalités

| Fonctionnalité | Description |
|----------------|-------------|
| **Migrations versionnées** | Suivez les changements de données comme les migrations de schéma |
| **Séparé du schéma** | Gardez la logique de données indépendante des changements de structure |
| **Support du rollback** | Annulez les changements de données si nécessaire |
| **Mode simulation** | Prévisualisez ce qui va se passer avant l'exécution |
| **Suivi de progression** | Barres de progression visuelles pour les opérations longues |
| **Traitement par lots** | Traitez des millions de lignes sans problèmes de mémoire |
| **Sécurité production** | Confirmations intégrées et flags de forçage |
| **Support des transactions** | Encapsulation automatique avec modes configurables |
| **Sauvegarde auto** | Sauvegarde automatique optionnelle (nécessite [spatie/laravel-backup](https://github.com/spatie/laravel-backup)) |
| **Événements** | `DataMigrationStarted`, `DataMigrationEnded`, `DataMigrationFailed` et `NoPendingDataMigrations` pour les notifications et l'audit |
| **Verrou de concurrence** | Option `--isolated` et verrou de cache partagé entre `data:migrate`, `data:rollback` et `data:fresh` |
| **Alertes de seuil** | Demandes de confirmation pour les opérations volumineuses |
| **PHPStan Niveau 5** | Entièrement typé, conformité stricte à l'analyse statique |
| **Helpers de test** | Trait `InteractsWithDataMigrations` et `DataMigrations::fake()` pour votre suite de tests |
| **Enchaînement** | Option `run_after_migrate` pour lancer `data:migrate` après `php artisan migrate` |

---

## Prérequis

- PHP 8.2 ou supérieur
- Laravel 12.x ou 13.x
- Une base de données supportée (MySQL, PostgreSQL, SQLite, SQL Server)

---

## Installation

Installez le package via Composer :

```bash
composer require vherbaut/laravel-data-migrations
```

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=data-migrations-config
```

Exécutez les migrations pour créer la table de suivi :

```bash
php artisan migrate
```

### Optionnel : Publier la migration de suivi

Le package charge automatiquement la migration de sa table `data_migrations`. Publiez-la uniquement si vous devez la personnaliser :

```bash
php artisan vendor:publish --tag=data-migrations-migrations
```

Dès qu'une copie publiée existe dans `database/migrations` (même renommée avec un nouveau timestamp), le package cesse de charger sa propre copie.

### Optionnel : Publier les stubs

Personnalisez les templates de migration :

```bash
php artisan vendor:publish --tag=data-migrations-stubs
```

---

## Démarrage rapide

### 1. Créer une migration de données

```bash
php artisan make:data-migration split_user_names
```

Cela crée `database/data-migrations/2024_01_15_123456_split_user_names.php` :

```php
<?php

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Contracts\Reversible;
use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration implements Reversible
{
    protected string $description = 'Séparer full_name en first_name et last_name';

    protected array $affectedTables = ['users'];

    public function up(): void
    {
        DB::table('users')
            ->whereNull('first_name')
            ->cursor()
            ->each(function ($user) {
                $parts = explode(' ', $user->full_name, 2);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $parts[0],
                        'last_name' => $parts[1] ?? '',
                    ]);

                $this->affected();
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('first_name')
            ->update([
                'full_name' => DB::raw("CONCAT(first_name, ' ', last_name)"),
                'first_name' => null,
                'last_name' => null,
            ]);
    }
};
```

### 2. Exécuter les migrations

```bash
# Exécuter les migrations de données en attente
php artisan data:migrate

# Prévisualiser les changements sans exécuter (simulation)
php artisan data:migrate --dry-run

# Forcer l'exécution en production
php artisan data:migrate --force
```

### 3. Vérifier le statut

```bash
php artisan data:status
```

```
+--------------------------------------+-------+-----------+--------+----------+---------------------+
| Migration                            | Batch | Statut    | Lignes | Durée    | Exécuté le          |
+--------------------------------------+-------+-----------+--------+----------+---------------------+
| 2024_01_15_123456_split_user_names   | 1     | Terminé   | 50 000 | 4523ms   | 2024-01-15 12:35:00 |
| 2024_01_16_091500_normalize_phones   | -     | En attente| -      | -        | -                   |
+--------------------------------------+-------+-----------+--------+----------+---------------------+

Total : 2 | En attente : 1 | Terminées : 1 | Échouées : 0
```

---

## Commandes console

| Commande | Description |
|----------|-------------|
| `make:data-migration {name}` | Créer un nouveau fichier de migration de données |
| `data:migrate` | Exécuter toutes les migrations de données en attente |
| `data:rollback` | Annuler le dernier lot de migrations |
| `data:status` | Afficher le statut de toutes les migrations |
| `data:fresh` | Réinitialiser et ré-exécuter toutes les migrations |
| `data:prune` | Supprimer les enregistrements de suivi dont le fichier de migration n'existe plus |

### make:data-migration

Créer un nouveau fichier de migration de données.

```bash
php artisan make:data-migration {name} [options]
```

| Option | Description |
|--------|-------------|
| `--table=` | Spécifier la table concernée |
| `--chunked` | Utiliser le template de migration par lots |
| `--idempotent` | Marquer la migration comme idempotente |
| `--path=` | Répertoire de création du fichier, relatif au chemin de base sauf avec `--realpath` |
| `--realpath` | Considérer `--path` comme un chemin absolu |

**Exemples :**

```bash
# Migration basique
php artisan make:data-migration update_user_statuses

# Migration par lots pour grands ensembles de données
php artisan make:data-migration process_orders --table=orders --chunked

# Migration idempotente (sûre à ré-exécuter)
php artisan make:data-migration normalize_emails --idempotent
```

### data:migrate

Exécuter les migrations de données en attente.

```bash
php artisan data:migrate [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Prévisualiser les migrations sans exécuter |
| `--force` | Forcer l'exécution en environnement de production |
| `--step` | Attribuer un numéro de lot distinct à chaque migration afin de pouvoir les annuler une par une |
| `--no-confirm` | Ignorer les demandes de confirmation du nombre de lignes |
| `--isolated[=CODE]` | Ne rien exécuter si une autre commande de migration de données détient le verrou partagé, avec `CODE` comme code de sortie optionnel (voir [Verrou de concurrence](#verrou-de-concurrence)) |
| `--path=*` | N'utiliser que les migrations de données trouvées dans ces répertoires (répétable, relatif au chemin de base sauf avec `--realpath`) |
| `--realpath` | Considérer les valeurs de `--path` comme des chemins absolus |

#### Rejouer les migrations échouées ou annulées

Les migrations enregistrées comme `failed` ou `rolled_back` sont considérées comme en attente : le prochain `data:migrate` les ré-exécute et remplace l'enregistrement précédent. `data:rollback` ne cible que les migrations `completed` (ou encore `running`).

### data:rollback

Annuler les migrations de données.

```bash
php artisan data:rollback [options]
```

| Option | Description |
|--------|-------------|
| `--step=N` | Annuler les N dernières migrations |
| `--batch=N` | Annuler un numéro de lot spécifique |
| `--force` | Forcer l'exécution en environnement de production |
| `--isolated[=CODE]` | Ne rien exécuter si une autre commande de migration de données détient le verrou partagé, avec `CODE` comme code de sortie optionnel (voir [Verrou de concurrence](#verrou-de-concurrence)) |
| `--path=*` | N'utiliser que les migrations de données trouvées dans ces répertoires (répétable, relatif au chemin de base sauf avec `--realpath`) |
| `--realpath` | Considérer les valeurs de `--path` comme des chemins absolus |

**Exemples :**

```bash
# Annuler le dernier lot
php artisan data:rollback

# Annuler les 3 dernières migrations
php artisan data:rollback --step=3

# Annuler le lot numéro 2
php artisan data:rollback --batch=2
```

### data:status

Afficher le statut de toutes les migrations de données.

```bash
php artisan data:status [options]
```

| Option | Description |
|--------|-------------|
| `--pending` | Afficher uniquement les migrations en attente |
| `--ran` | Afficher uniquement les migrations terminées |
| `--json` | Afficher la liste en tableau JSON (un objet par migration avec `name`, `status`, `batch`, `rows_affected`, `duration_ms`, `ran_at`, `orphaned`) |

Les enregistrements dont le fichier de migration a été supprimé sont listés aussi, marqués `(orphaned)` dans la table et comptés dans la ligne de résumé. Supprimez-les avec `data:prune`.

### data:fresh

Réinitialiser et ré-exécuter toutes les migrations de données.

```bash
php artisan data:fresh [options]
```

| Option | Description |
|--------|-------------|
| `--force` | Forcer l'exécution en environnement de production |
| `--isolated[=CODE]` | Ne rien exécuter si une autre commande de migration de données détient le verrou partagé, avec `CODE` comme code de sortie optionnel (voir [Verrou de concurrence](#verrou-de-concurrence)) |

> **Attention :** Cette commande supprimera tous les enregistrements de migration et ré-exécutera chaque migration. À utiliser avec précaution.

### data:prune

Supprimer les enregistrements de suivi dont le fichier de migration n'existe plus (ceux que `data:status` marque comme orphelins).

```bash
php artisan data:prune [options]
```

| Option | Description |
|--------|-------------|
| `--force` | Forcer l'exécution en environnement de production |

La commande refuse de s'exécuter quand le répertoire des migrations ne contient aucun fichier alors que des enregistrements existent, afin qu'un `path` mal configuré ne puisse pas vider la table.

---

## Écrire des migrations de données

### Propriétés des migrations

| Propriété | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `$description` | `string` | `''` | Description lisible de ce que fait cette migration |
| `$affectedTables` | `array` | `[]` | Liste des tables modifiées par cette migration (documentation/sauvegarde) |
| `$withinTransaction` | `bool` | `true` | Encapsuler la migration dans une transaction |
| `$chunkSize` | `int` | `1000` | Taille de lot par défaut pour les opérations par lots |
| `$chunkColumn` | `string` | `'id'` | Colonne clé utilisée par `chunk()`, `chunkLazy()` et `chunkUpdate()` pour paginer |
| `$idempotent` | `bool` | `false` | Cette migration peut-elle être exécutée plusieurs fois sans danger |
| `$connection` | `?string` | `null` | Connexion de base de données à utiliser (null = défaut) |

### Migration basique

```php
return new class extends DataMigration
{
    protected string $description = 'Désactiver les utilisateurs inactifs depuis un an';

    protected array $affectedTables = ['users'];

    public function up(): void
    {
        $affected = DB::table('users')
            ->where('status', 'active')
            ->where('last_login_at', '<', now()->subYear())
            ->update(['status' => 'inactive']);

        $this->affected($affected);
    }
};
```

### Migration par lots (grands ensembles de données)

Pour les grands ensembles de données, utilisez le traitement par lots pour éviter les problèmes de mémoire et les transactions longues :

```php
return new class extends DataMigration
{
    protected string $description = 'Recalculer les totaux des commandes';

    protected array $affectedTables = ['orders'];

    protected int $chunkSize = 500;

    protected bool $withinTransaction = false; // Important pour les grands ensembles

    public function up(): void
    {
        $total = $this->getEstimatedRows();
        $this->startProgress($total, "Traitement de {$total} commandes...");

        $this->chunk('orders', function ($order) {
            $newTotal = DB::table('order_items')
                ->where('order_id', $order->id)
                ->sum('price');

            DB::table('orders')
                ->where('id', $order->id)
                ->update(['total' => $newTotal]);
        });

        $this->finishProgress();
    }

    public function getEstimatedRows(): ?int
    {
        return DB::table('orders')->count();
    }
};
```

### Migration idempotente

Migrations sûres à exécuter plusieurs fois :

```php
return new class extends DataMigration
{
    protected string $description = 'Normaliser les adresses email en minuscules';

    protected bool $idempotent = true;

    public function up(): void
    {
        // Ne traite que les enregistrements non normalisés
        DB::table('users')
            ->whereRaw('email != LOWER(email)')
            ->cursor()
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['email' => strtolower($user->email)]);

                $this->affected();
            });
    }
};
```

### Migration réversible

Implémentez l'interface `Reversible` et sa méthode `down()` pour permettre le rollback :

```php
return new class extends DataMigration implements Reversible
{
    protected string $description = 'Appliquer une augmentation de prix de 10%';

    protected array $affectedTables = ['products'];

    public function up(): void
    {
        $affected = DB::table('products')
            ->update(['price' => DB::raw('price * 1.1')]);

        $this->affected($affected);
    }

    public function down(): void
    {
        DB::table('products')
            ->update(['price' => DB::raw('price / 1.1')]);
    }
};
```

> **Note :** Les migrations générées n'implémentent pas `Reversible`. Une migration qui ne l'implémente pas est ignorée par `data:rollback`, même si elle déclare une méthode `down()`. N'ajoutez pas de `down()` vide : la migration serait marquée comme annulée sans qu'aucune donnée ne soit restaurée.

### Utiliser une connexion de base de données spécifique

```php
return new class extends DataMigration
{
    protected ?string $connection = 'tenant';

    public function up(): void
    {
        $this->db()->table('settings')->update(['migrated' => true]);
    }
};
```

---

## Méthodes disponibles

### Accès à la base de données

```php
// Obtenir la connexion de base de données configurée
$this->db()->table('users')->get();
```

### Suivi de progression

```php
// Démarrer une barre de progression
$this->startProgress(1000, 'Traitement des enregistrements...');

// Incrémenter de 1
$this->incrementProgress();

// Incrémenter de N
$this->addProgress(10);

// Définir la progression absolue
$this->setProgress(500);

// Terminer et effacer la barre de progression
$this->finishProgress();

// Obtenir le pourcentage actuel
$percentage = $this->getProgressPercentage();
```

### Traitement par lots

```php
// Traiter les enregistrements un par un, paginés par clé (chunkById en interne)
$processed = $this->chunk('table_name', function ($record) {
    // Traiter chaque enregistrement
    // La progression et les lignes affectées sont comptées automatiquement
});

// Itération lazy économe en mémoire (lazyById en interne)
$processed = $this->chunkLazy('table_name', function ($record) {
    // Traiter chaque enregistrement
});

// Mise à jour massive par lots (pour les requêtes UPDATE)
$affected = $this->chunkUpdate(
    'table_name',
    ['status' => 'processed'],
    function ($query) {
        $query->where('status', 'pending');
    }
);

// Taille de lot et colonne clé optionnelles (défauts : $chunkSize et $chunkColumn)
$processed = $this->chunk('legacy_table', fn ($record) => $this->process($record), 500, 'legacy_id');
```

Les trois helpers ajoutent les lignes qu'ils traitent au compteur de lignes affectées : n'appelez pas `affected()` une seconde fois avec leur valeur de retour. Les trois helpers parcourent la table par clé : la colonne clé doit être unique et le callback ne doit pas la modifier. `chunkUpdate()` sélectionne les clés des prochaines lignes correspondantes puis met à jour cette plage de clés : chaque ligne est visitée une fois et la boucle se termine même si la mise à jour laisse les lignes dans le prédicat. Enveloppez les conditions `OR` dans une closure à l'intérieur du callback, comme avec `chunkById()`.

### Comptage des lignes

```php
// Incrémenter les lignes affectées de 1
$this->affected();

// Incrémenter d'un montant spécifique
$this->affected(100);

// Obtenir le total des lignes affectées (utilisé dans les logs)
$count = $this->getRowsAffected();
```

### Sortie console

```php
// Message d'information (console uniquement)
$this->info('Traitement terminé !');

// Message d'avertissement
$this->warn('Certains enregistrements ont été ignorés.');

// Message d'erreur
$this->error('Échec du traitement de l\'enregistrement.');

// Message de log (vers le canal de log configuré + console)
$this->log('Migration terminée avec succès.');
$this->log('Une erreur s\'est produite.', 'error');
```

### Informations de simulation

Surchargez `dryRun()` pour fournir des informations détaillées pendant `--dry-run` :

```php
public function dryRun(): array
{
    return [
        'description' => $this->getDescription(),
        'affected_tables' => $this->affectedTables,
        'estimated_rows' => $this->getEstimatedRows(),
        'reversible' => $this instanceof Reversible,
        'idempotent' => $this->idempotent,
        'uses_transaction' => $this->withinTransaction,
    ];
}
```

---

## Configuration

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=data-migrations-config
```

### Référence complète de configuration

```php
<?php
// config/data-migrations.php

return [
    /*
    |--------------------------------------------------------------------------
    | Chemin des migrations
    |--------------------------------------------------------------------------
    |
    | Le répertoire où les fichiers de migration de données sont stockés.
    |
    */
    'path' => database_path('data-migrations'),

    /*
    |--------------------------------------------------------------------------
    | Table des migrations
    |--------------------------------------------------------------------------
    |
    | La table de base de données utilisée pour suivre les migrations exécutées.
    |
    */
    'table' => 'data_migrations',

    /*
    |--------------------------------------------------------------------------
    | Mode de transaction
    |--------------------------------------------------------------------------
    |
    | Comment gérer les transactions de base de données :
    | - 'auto' : Utilise la propriété $withinTransaction de la migration
    | - 'always' : Toujours encapsuler dans une transaction (ignore le paramètre migration)
    | - 'never' : Ne jamais utiliser de transactions (ignore le paramètre migration)
    |
    */
    'transaction' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Configuration des logs
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('DATA_MIGRATIONS_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration de sécurité
    |--------------------------------------------------------------------------
    */
    'safety' => [
        /*
        | Exiger le flag --force lors de l'exécution en production
        */
        'require_force_in_production' => true,

        /*
        | Demander confirmation si les lignes estimées dépassent ce seuil.
        | Définir à 0 pour désactiver.
        */
        'confirm_threshold' => 10000,

        /*
        | Créer automatiquement une sauvegarde de la base de données avant les migrations.
        | Nécessite le package spatie/laravel-backup.
        */
        'auto_backup' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verrou de concurrence
    |--------------------------------------------------------------------------
    */
    'lock' => [
        /*
        | Prendre le verrou partagé même sans l'option --isolated.
        */
        'enabled' => false,

        /*
        | Store de cache qui porte le verrou (null = store par défaut).
        */
        'store' => null,

        /*
        | Secondes après lesquelles un verrou laissé par un processus tué expire.
        */
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enchaînement après les migrations de schéma
    |--------------------------------------------------------------------------
    */
    'run_after_migrate' => false,
];
```

### Enchaîner les migrations de données après les migrations de schéma

Mettez `run_after_migrate` à `true` pour que `data:migrate --force` s'exécute automatiquement dès que `php artisan migrate`, `migrate:fresh` ou `migrate:refresh` se termine avec succès, afin qu'un déploiement ne nécessite qu'une seule commande :

```php
// config/data-migrations.php
'run_after_migrate' => true,
```

Le hook écoute l'événement console `CommandFinished` de Laravel. Il est ignoré après `--pretend`, après une commande en échec, et dans l'environnement `testing`, où Laravel n'émet pas les événements console (une suite de tests avec `RefreshDatabase` n'exécutera pas vos migrations de données).

---

## Fonctionnalités de sécurité

### Protection de production

Par défaut, l'exécution des migrations en production nécessite le flag `--force` :

```bash
# Ceci demandera une confirmation en production
php artisan data:migrate

# Ceci s'exécutera sans demander
php artisan data:migrate --force
```

### Confirmation du nombre de lignes

Lorsqu'une migration estime qu'elle affectera plus de lignes que `confirm_threshold`, vous serez invité à confirmer :

```
Nombre estimé de lignes à affecter : 150 000
Cela dépasse le seuil de confirmation de 10 000 lignes.
Voulez-vous continuer ? (oui/non) [non] :
```

Ignorez avec `--no-confirm` ou `--force` :

```bash
php artisan data:migrate --no-confirm
```

### Sauvegarde automatique

Activez la sauvegarde automatique de la base de données avant les migrations (nécessite [spatie/laravel-backup](https://github.com/spatie/laravel-backup)) :

```bash
composer require spatie/laravel-backup
```

```php
// config/data-migrations.php
'safety' => [
    'auto_backup' => true,
],
```

### Verrou de concurrence

Deux processus `data:migrate` lancés en même temps tenteraient tous deux d'insérer le même enregistrement de suivi. Passez `--isolated` à `data:migrate`, `data:rollback` ou `data:fresh` pour prendre d'abord un verrou de cache, exactement comme `php artisan migrate --isolated` :

```bash
# Ignore l'exécution (code de sortie 0) quand une autre commande de migration de données détient le verrou
php artisan data:migrate --isolated

# Idem, mais avec le code de sortie 12 au lieu de 0 quand le verrou est détenu
php artisan data:migrate --isolated=12
```

Les trois commandes partagent un seul verrou nommé `data-migrations` : `data:rollback --isolated` attend donc aussi la fin d'un `data:migrate --isolated` en cours. `data:fresh --isolated` conserve le verrou pendant qu'il exécute `data:migrate` en interne. Le verrou vit dans le store de cache indiqué par `lock.store` (le store par défaut si `null`) et expire après `lock.ttl` secondes si le processus est tué. Mettez `lock.enabled` à `true` pour prendre le verrou sans passer l'option :

```php
// config/data-migrations.php
'lock' => [
    'enabled' => true,
    'store' => 'redis',
    'ttl' => 7200,
],
```

---

## Événements

Le migrateur émet des événements Laravel que vous pouvez écouter pour des notifications, des métriques ou un journal d'audit. Chaque événement porte l'instance de migration, son nom et la méthode exécutée (`$method`, `up` ou `down`), à l'image des événements `MigrationStarted` et `MigrationEnded` de Laravel :

| Événement | Propriétés | Quand |
|-----------|------------|-------|
| `DataMigrationStarted` | `migration`, `name`, `method` | Juste avant l'exécution de `up()` ou `down()` |
| `DataMigrationEnded` | `migration`, `name`, `method`, `rowsAffected`, `durationMs` | Après la mise à jour de l'enregistrement de suivi |
| `DataMigrationFailed` | `migration`, `name`, `method`, `exception` | Avant que l'exception ne soit relancée |
| `NoPendingDataMigrations` | `method` | Quand une exécution ou un rollback ne trouve rien à faire |

```php
use Vherbaut\DataMigrations\Events\DataMigrationEnded;
use Vherbaut\DataMigrations\Events\DataMigrationFailed;

Event::listen(DataMigrationEnded::class, function (DataMigrationEnded $event) {
    Log::info("La migration de données {$event->name} ({$event->method}) a touché {$event->rowsAffected} lignes en {$event->durationMs} ms");
});

Event::listen(DataMigrationFailed::class, function (DataMigrationFailed $event) {
    Notification::route('slack', config('services.slack.ops'))
        ->notify(new DataMigrationFailedNotification($event->name, $event->exception));
});
```

Aucun événement n'est émis en mode simulation. Le migrateur reçoit le dispatcher lors de sa première résolution : dans les tests, appelez `Event::fake()` avant le premier appel Artisan, ou enregistrez un vrai listener avec `Event::listen()`.

---

## Exemples concrets

### Normaliser les numéros de téléphone

```php
return new class extends DataMigration
{
    protected string $description = 'Normaliser les numéros de téléphone au format E.164';

    protected array $affectedTables = ['users'];

    protected bool $idempotent = true;

    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', 'NOT LIKE', '+%')
            ->cursor()
            ->each(function ($user) {
                $normalized = $this->normalizePhone($user->phone);

                if ($normalized) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['phone' => $normalized]);

                    $this->affected();
                }
            });
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 10 ? '+33' . substr($digits, 1) : null;
    }
};
```

### Remplir des champs calculés

```php
return new class extends DataMigration
{
    protected string $description = 'Remplir order_count sur les clients';

    protected array $affectedTables = ['customers'];

    protected bool $withinTransaction = false;

    public function up(): void
    {
        $total = DB::table('customers')->whereNull('order_count')->count();
        $this->startProgress($total);

        $this->chunkUpdate(
            'customers',
            ['order_count' => DB::raw('(SELECT COUNT(*) FROM orders WHERE orders.customer_id = customers.id)')],
            fn ($query) => $query->whereNull('order_count')
        );

        $this->finishProgress();
    }
};
```

### Anonymisation des données RGPD

```php
return new class extends DataMigration
{
    protected string $description = 'Anonymiser les utilisateurs supprimés depuis plus de 2 ans (RGPD)';

    protected array $affectedTables = ['users'];

    protected bool $idempotent = true;

    public function up(): void
    {
        DB::table('users')
            ->where('deleted_at', '<', now()->subYears(2))
            ->whereNull('anonymized_at')
            ->cursor()
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => "anonymized_{$user->id}@supprime.local",
                        'name' => 'Utilisateur supprimé',
                        'phone' => null,
                        'address' => null,
                        'anonymized_at' => now(),
                    ]);

                $this->affected();
            });
    }
};
```

### Chiffrer des données sensibles

```php
return new class extends DataMigration
{
    protected string $description = 'Chiffrer le champ numéro de sécurité sociale';

    protected array $affectedTables = ['employees'];

    protected bool $withinTransaction = false;

    public function up(): void
    {
        $total = DB::table('employees')
            ->whereNotNull('ssn')
            ->whereNull('ssn_encrypted')
            ->count();

        $this->startProgress($total, 'Chiffrement des données NSS...');

        DB::table('employees')
            ->whereNotNull('ssn')
            ->whereNull('ssn_encrypted')
            ->cursor()
            ->each(function ($employee) {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'ssn_encrypted' => encrypt($employee->ssn),
                        'ssn' => null,
                    ]);

                $this->incrementProgress();
                $this->affected();
            });

        $this->finishProgress();
    }
};
```

### Migrer vers une nouvelle structure de schéma

```php
return new class extends DataMigration implements Reversible
{
    protected string $description = 'Migrer les adresses de users vers la table addresses';

    protected array $affectedTables = ['users', 'addresses'];

    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('address_line1')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('addresses')
                    ->whereRaw('addresses.user_id = users.id');
            })
            ->cursor()
            ->each(function ($user) {
                DB::table('addresses')->insert([
                    'user_id' => $user->id,
                    'line1' => $user->address_line1,
                    'line2' => $user->address_line2,
                    'city' => $user->city,
                    'state' => $user->state,
                    'zip' => $user->zip,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->affected();
            });
    }

    public function down(): void
    {
        // Recopier les données vers la table users
        DB::table('addresses')
            ->join('users', 'users.id', '=', 'addresses.user_id')
            ->cursor()
            ->each(function ($address) {
                DB::table('users')
                    ->where('id', $address->user_id)
                    ->update([
                        'address_line1' => $address->line1,
                        'address_line2' => $address->line2,
                        'city' => $address->city,
                        'state' => $address->state,
                        'zip' => $address->zip,
                    ]);
            });

        DB::table('addresses')->truncate();
    }
};
```

---

## Architecture

Ce package suit les principes SOLID et utilise une architecture propre :

### Interfaces principales

| Interface | Description |
|-----------|-------------|
| `MigrationInterface` | Contrat pour les migrations de données |
| `Reversible` | Contrat des migrations que `data:rollback` peut annuler via `down()` |
| `MigratorInterface` | Contrat pour le runner de migration |
| `MigrationRepositoryInterface` | Contrat pour la persistance de l'état des migrations |
| `MigrationFileResolverInterface` | Contrat pour localiser et résoudre les fichiers de migration |
| `BackupServiceInterface` | Contrat pour les services de sauvegarde |

### Composants clés

```
src/
├── Commands/                    # Commandes Artisan
│   ├── Concerns/
│   │   └── IsolatesDataMigrations.php
│   ├── DataMigrateCommand.php
│   ├── DataMigrateFreshCommand.php
│   ├── DataMigratePruneCommand.php
│   ├── DataMigrateRollbackCommand.php
│   ├── DataMigrateStatusCommand.php
│   └── MakeDataMigrationCommand.php
├── Concerns/
│   └── TracksProgress.php       # Trait barre de progression
├── Contracts/                   # Interfaces
├── DTO/
│   ├── MigrationRecord.php      # Objet de transfert de données typé
│   └── MigrationStatus.php      # Ligne de data:status, forme JSON
├── Exceptions/
│   ├── InvalidMigrationException.php
│   ├── MigrationException.php
│   └── MigrationNotFoundException.php
├── Events/
│   ├── DataMigrationEnded.php
│   ├── DataMigrationFailed.php
│   ├── DataMigrationStarted.php
│   └── NoPendingDataMigrations.php
├── Facades/
│   └── DataMigrations.php
├── Listeners/
│   └── RunDataMigrationsAfterMigrate.php
├── Locking/
│   └── DataMigrationsCommandMutex.php
├── Migration/
│   ├── DataMigration.php        # Classe de migration de base
│   ├── MigrationFileResolver.php
│   ├── MigrationRepository.php
│   ├── Migrator.php
│   └── RollbackTargetSelector.php
├── Services/
│   ├── NullBackupService.php
│   └── SpatieBackupService.php
├── Testing/
│   ├── InteractsWithDataMigrations.php
│   └── MigratorFake.php
└── DataMigrationsServiceProvider.php
```

### Utilisation de la Facade

```php
use Vherbaut\DataMigrations\Facades\DataMigrations;

// Obtenir les migrations en attente
$pending = DataMigrations::getPendingMigrations();

// Exécuter les migrations programmatiquement
$ran = DataMigrations::run(['dry-run' => false]);

// Rollback
$rolledBack = DataMigrations::rollback(['step' => 1]);

// Obtenir le repository
$repo = DataMigrations::getRepository();

// Enregistrements dont le fichier de migration n'existe plus
$orphaned = DataMigrations::getOrphanedMigrations();

// En test : enregistrer ce qui s'exécuterait sans rien exécuter (voir Tests)
$fake = DataMigrations::fake();
```

---

## Tests

Exécuter la suite de tests :

```bash
composer test
```

Exécuter l'analyse statique :

```bash
composer phpstan
```

### Tester vos migrations

Le trait `InteractsWithDataMigrations` exécute réellement une migration de données, dans un lot dédié, et vérifie son statut de suivi. Il a besoin de la table de suivi, utilisez-le donc avec `RefreshDatabase` :

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vherbaut\DataMigrations\Testing\InteractsWithDataMigrations;

class SplitUserNamesTest extends TestCase
{
    use InteractsWithDataMigrations;
    use RefreshDatabase;

    public function test_it_splits_user_names(): void
    {
        DB::table('users')->insert(['full_name' => 'Jean Dupont']);

        $this->runDataMigration('2024_06_01_120000_split_user_names');

        $this->assertDataMigrationRan('2024_06_01_120000_split_user_names');
        $this->assertDatabaseHas('users', ['first_name' => 'Jean', 'last_name' => 'Dupont']);

        $this->rollbackDataMigration('2024_06_01_120000_split_user_names');

        $this->assertDataMigrationRolledBack('2024_06_01_120000_split_user_names');
    }
}
```

| Helper | Comportement |
|--------|--------------|
| `runDataMigration($name)` | Exécute la migration dans un lot dédié. Lève `MigrationNotFoundException` si le fichier manque et `LogicException` si elle a déjà été exécutée |
| `runDataMigrations()` | Exécute toutes les migrations en attente, comme `data:migrate` |
| `rollbackDataMigration($name)` | Annule la migration. Lève `LogicException` si elle partage son lot avec d'autres migrations |
| `assertDataMigrationRan($name)`, `assertDataMigrationNotRan($name)`, `assertDataMigrationFailed($name)`, `assertDataMigrationRolledBack($name)` | Vérifient le statut de suivi |

### Simuler le migrateur

Pour tester du code qui déclenche des migrations de données (une commande de déploiement, un listener) sans les exécuter, remplacez le migrateur par un fake, comme `Bus::fake()`. Les exécutions et rollbacks sont enregistrés, les lectures passent toujours par le vrai repository :

```php
use Vherbaut\DataMigrations\Facades\DataMigrations;

public function test_deploy_command_runs_data_migrations(): void
{
    $fake = DataMigrations::fake();

    $this->artisan('app:deploy');

    $fake->assertRan('2024_06_01_120000_split_user_names');
    $fake->assertNothingRolledBack();
}
```

Assertions disponibles : `assertRan($name)`, `assertNotRan($name)`, `assertNothingRan()`, `assertRolledBack($name)`, `assertNothingRolledBack()`, plus `ran()` et `rolledBack()` pour inspecter les noms enregistrés. `fake()` réinitialise aussi l'application console Artisan, afin que les commandes résolues avant l'appel utilisent le fake.

---

## Bonnes pratiques

### Recommandations générales

1. **Toujours tester en staging d'abord** — Utilisez `--dry-run` pour prévisualiser les changements avant l'exécution
2. **Gardez les migrations focalisées** — Un changement logique par migration
3. **Documentez avec `$description`** — Votre futur vous vous remerciera
4. **Définissez `$affectedTables`** — Permet la sauvegarde automatique et la documentation

### Pour les grands ensembles de données

1. **Désactivez les transactions** — Définissez `$withinTransaction = false` pour éviter les timeouts de verrou
2. **Utilisez les lots** — Traitez les enregistrements par lots pour éviter l'épuisement de la mémoire
3. **Implémentez `getEstimatedRows()`** — Permet le suivi de progression et les demandes de confirmation
4. **Utilisez `chunkLazy()`** — Plus économe en mémoire que `chunk()` pour les très grands ensembles

### Pour la sécurité

1. **Rendez les migrations idempotentes** — Sûres à ré-exécuter si interrompues
2. **Implémentez `down()` quand possible** — Permet le rollback
3. **Utilisez le comptage de lignes** — Appelez `$this->affected()` pour un logging précis
4. **Activez la sauvegarde automatique** — Pour les transformations de données critiques

### Pour le débogage

1. **Utilisez `$this->log()`** — Log vers le fichier et la console
2. **Exécutez avec le flag `-v`** — Voir les traces d'erreur
3. **Vérifiez `data:status`** — Voir l'historique des migrations et les échecs

---

## Contribution

Les contributions sont les bienvenues ! Veuillez consulter [CONTRIBUTING.md](CONTRIBUTING.md) pour les détails.

1. Forkez le repository
2. Créez votre branche de fonctionnalité (`git checkout -b feature/fonctionnalite-geniale`)
3. Écrivez des tests pour vos changements
4. Assurez-vous que les tests passent (`composer test`)
5. Assurez-vous que PHPStan passe (`composer phpstan`)
6. Committez vos changements (`git commit -m 'Ajouter une fonctionnalité géniale'`)
7. Pushez vers la branche (`git push origin feature/fonctionnalite-geniale`)
8. Ouvrez une Pull Request

---

## Changelog

Veuillez consulter [CHANGELOG.md](CHANGELOG.md) pour les changements récents.

---

## Sécurité

Si vous découvrez une vulnérabilité de sécurité, veuillez envoyer un email à vincenth.lzh@gmail.com au lieu d'utiliser le tracker d'issues.

---

## Crédits

- [Vincent Herbaut](https://github.com/vherbaut)
- [Tous les contributeurs](../../contributors)

---

## Licence

La licence MIT (MIT). Veuillez consulter [LICENSE](LICENSE) pour plus d'informations.
