# Horus Battle Arena


Vous jouez le personnage de gauche, l'action est réalisée selon la vitesse de votre héros et de celui affronté. En solo, l'action de l'adversaire est déterminée au hasard.

Un jeu de combat tour par tour multijoueur en temps réel (PHP/JS).

## � Structure du Projet

```
php_test/
├── index.php                    # Point d'entrée principal
├── api.php                      # API pour les actions de jeu
│
├── includes/                    # Fichiers utilitaires partagés
│   ├── autoload.php             # Autoloader centralisé + constantes de chemins
│   ├── header.php               # Template header HTML
│   ├── footer.php               # Template footer HTML
│   └── auth_helper.php          # Authentification des pages sensibles
│
├── core/                        # Classes PHP (métier)
│   ├── Database.php
│   ├── User.php
│   ├── Combat.php
│   ├── MultiCombat.php
│   ├── TeamCombat.php
│   ├── Personnage.php
│   ├── StatusEffect.php
│   ├── Blessing.php
│   ├── MatchQueue.php
│   ├── heroes/                  # Classes de héros
│   ├── effects/                 # Classes d'effets de statut
│   ├── blessings/               # Classes de bénédictions
│   ├── Models/                  # Modèles (Hero, etc.)
│   └── Services/                # Services (HeroManager, etc.)
│
├── pages/                       # Pages du site
│   ├── account.php              # Page Mon Compte
│   ├── auth/                    # Authentification
│   │   ├── login.php
│   │   └── register.php
│   ├── game/                    # Pages de jeu
│   │   ├── single_player.php
│   │   ├── multiplayer.php
│   │   ├── multiplayer-selection.php
│   │   ├── multiplayer-combat.php
│   │   ├── multiplayer_5v5_setup.php
│   │   ├── multiplayer_5v5_selection.php
│   │   ├── simulation.php
│   │   └── combat.php
│   ├── admin/                   # Administration
│   │   ├── admin_helper.php
│   │   ├── admin_heroes.php
│   │   └── admin_execute_sql.php
│   └── debug/                   # Outils de debug
│       ├── debug.php
│       ├── debug_json.php
│       ├── debug_teams.php
│       └── debug_onclick.php
│
├── components/                  # Composants réutilisables PHP
│   ├── selection-screen.php
│   ├── selection-utils.php
│   ├── team-hero-selector.php
│   ├── team-hero-selector-loader.php
│   └── team-manager.php
│
├── public/                      # Assets statiques (web-accessible)
│   ├── css/
│   │   ├── style.css
│   │   ├── account.css
│   │   ├── auth.css
│   │   ├── combat.css
│   │   ├── multiplayer.css
│   │   └── ...
│   ├── js/
│   │   ├── combat-animations.js
│   │   ├── selection-tooltip.js
│   │   └── ...
│   └── media/
│       ├── heroes/
│       ├── blessings/
│       └── website/
│
├── data/                        # Données runtime (fichiers JSON)
│   ├── queue.json
│   ├── queue_5v5.json
│   ├── schema.sql
│   ├── schema_teams.sql
│   └── matches/
│
└── config/                      # Configuration
```

## 🔧 Constantes de Chemins (autoload.php)

Le fichier `includes/autoload.php` définit les constantes suivantes :

```php
BASE_PATH        // Racine du projet (php_test/)
CORE_PATH        // Classes PHP (core/)
PUBLIC_PATH      // Assets statiques (public/)
PAGES_PATH       // Pages (pages/)
DATA_PATH        // Données runtime (data/)
INCLUDES_PATH    // Fichiers includes (includes/)
COMPONENTS_PATH  // Composants PHP (components/)
```

## 🚀 Déploiement sur O2Switch (ou tout hébergeur PHP/cPanel)

Ce projet utilise MySQL pour les utilisateurs et héros, et des fichiers JSON pour les matchs.

### 1. Préparer les fichiers
Assurez-vous d'avoir tous les fichiers du projet prêts.

### 2. Envoyer sur le serveur
Connectez-vous à votre hébergement via **FTP (FileZilla)** ou le **Gestionnaire de Fichiers cPanel**.
Envoyez tous les fichiers dans le dossier public de votre choix (ex: `public_html/arene`).

### 3. Vérifier les dossiers de données
Le système a besoin d'écrire dans le dossier `data`. Assurez-vous que la structure suivante existe :
```
data/
├── matches/
├── queue.json (créé automatiquement si absent)
└── queue_5v5.json (créé automatiquement si absent)
```

### 4. Permissions (Important)
Si vous rencontrez des erreurs, vérifiez les permissions (CHMOD) des dossiers.
Le serveur doit pouvoir écrire dans `data/` et `data/matches/`.
- Définir la valeur numérique à **755** (standard) ou **777** (si 755 ne suffit pas).

### 5. Base de données
Exécutez les scripts SQL dans `data/schema.sql` et `data/schema_teams.sql` pour créer les tables.

## 🛠️ Fonctionnalités

- **Single Player** : Combattez une IA.
- **Multiplayer 1v1** : Combattez un autre joueur en temps réel.
- **Multiplayer 5v5** : Combat d'équipes avec système de switch.
- **Bénédictions** : Bonus spéciaux pour les combats.
- **Statistiques** : Suivi des performances par héros.

## 🧹 Maintenance

Si le jeu semble bloqué, accédez à `/pages/debug/debug.php` pour :
- Voir l'état des sessions et matchs.
- **Reset Session** : Pour vous débloquer.
- **Clear All** : Pour supprimer tous les matchs et vider la queue.
