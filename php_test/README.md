# Horus Battle Arena

Un jeu de combat tour par tour multijoueur en temps réel (PHP/JS).

## 🚀 Déploiement sur O2Switch (ou tout hébergeur PHP/cPanel)

Ce projet utilise un stockage de données basé sur des fichiers JSON (`/data`), il ne nécessite **aucune base de données MySQL**.

### 1. Préparer les fichiers
Assurez-vous d'avoir tous les fichiers du projet prêts.
*(Les fichiers `.gitignore` et ce `README.md` n'ont pas besoin d'être uploadés, mais ce n'est pas grave s'ils le sont).*

### 2. Envoyer sur le serveur
Connectez-vous à votre hébergement via **FTP (FileZilla)** ou le **Gestionnaire de Fichiers cPanel**.
Envoyez tous les fichiers dans le dossier public de votre choix (ex: `public_html/arene`).

### 3. Vérifier les dossiers de données
Le système a besoin d'écrire dans le dossier `data`. Assurez-vous que la structure suivante existe :
```
/ (racine du projet)
└── data/
    ├── matches/
    └── queue.json (créé automatiquement si absent)
```

### 4. Permissions (Important)
Si vous rencontrez des erreurs, vérifiez les permissions (CHMOD) des dossiers.
Le serveur doit pouvoir écrire dans `data/` et `data/matches/`.
- Clic droit sur le dossier `data` > Droits d'accès au fichier...
- Définir la valeur numérique à **755** (standard) ou **777** (si 755 ne suffit pas).
- Cochez "Récursion dans les sous-dossiers".

### 5. C'est tout !
Accédez à `https://votre-site.com/arene/index.php` et jouez.

## 🛠️ Fonctionnalités

- **Single Player** : Combattez une IA.
- **Multiplayer** : Combattez un autre joueur en temps réel (Queue -> Match).
- **Temps Réel** : Système de polling (toutes les secondes) pour synchroniser l'état du jeu.
- **Sécurité** : Protection XSS sur les pseudos.

## 🧹 Maintenance

Si le jeu semble bloqué ou buggé, vous pouvez accéder à `/debug.php` pour :
- Voir l'état des sessions et matchs.
- **Reset Session** : Pour vous débloquer.
- **Clear All** : Pour supprimer tous les matchs et vider la queue (admin).
