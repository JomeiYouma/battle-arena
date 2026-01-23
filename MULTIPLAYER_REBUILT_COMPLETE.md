# 🎮 Multiplayer System - REBUILT

## ✅ Changements Effectués

### 1. **Nouvelle Page `combat.php`** (NOUVEAU)
- Page dédiée pour l'affichage du combat en multiplayer
- Miroir de `single_player.php` mais avec polling
- Reçoit `match_id` via URL
- Polls `/api.php?action=poll_status&match_id=XXX` toutes les 2 secondes
- Affiche état du combat en temps réel
- Support PvP et PvB (détection bot automatique)

**Fichier:** [combat.php](combat.php)

### 2. **Refactor `multi_player.php`** (REFACTORISÉ)
- **Avant:** Tout le HTML/JS du combat sur une seule page (280 lignes)
- **Après:** Juste la sélection + queue d'attente (120 lignes)
- **Deux écrans:**
  1. Sélection du héros (grid de cartes)
  2. Queue d'attente avec **compteur visible** 📊

**Nouvelle logique:**
- Joueur sélectionne un héros → POST `/api.php?action=join_queue`
- Affiche la queue screen avec compteur de joueurs
- Polls `/api.php?action=poll_queue` toutes les 1 seconde
- Dès qu'un match est trouvé ou timeout → redirige vers `combat.php?match_id=XXX`

**Fichier:** [multi_player.php](multi_player.php)

### 3. **API Complètement Reworkée** (`api.php`)
#### Anciens Endpoints (SUPPRIMÉS)
- ~~`leave_queue`~~
- ~~`poll_status` (sans match_id)~~

#### Nouveaux Endpoints

##### **POST `/api.php?action=join_queue`**
Paramètres:
- `hero_id` (POST)

Réponse:
```json
{
  "status": "matched" | "waiting" | "error",
  "matchId": "match_xxxxx",
  "message": "..."
}
```

Logique:
- Ajoute joueur à la queue
- Cherche un adversaire parmi les autres joueurs
- Si match trouvé → crée fichier match JSON + retourne `"matched"`
- Sinon → retourne `"waiting"`

---

##### **GET `/api.php?action=poll_queue`**
Aucun paramètre POST/GET requis

Réponse:
```json
{
  "status": "matched" | "waiting" | "timeout",
  "matchId": "match_xxxxx",
  "queue_count": 5
}
```

Logique:
- Vérifie si un match a été créé pour ce joueur (scan des fichiers match)
- Vérifie si 30s timeout atteint
  - Si oui → crée un match BOT automatiquement
  - Retourne `"timeout"` + `matchId`
- Sinon retourne `"waiting"` + nombre de joueurs en queue

**Cette endpoint fournit le COMPTEUR qui s'affiche dans la queue screen**

---

##### **GET `/api.php?action=poll_status&match_id=match_xxxxx`**
Paramètres:
- `match_id` (GET)

Réponse:
```json
{
  "status": "active",
  "turn": 5,
  "isOver": false,
  "me": {
    "name": "Guerrier",
    "type": "Guerrier",
    "pv": 45,
    "max_pv": 100,
    "img": "..."
  },
  "opponent": {
    "name": "Aquatique",
    "type": "Aquatique",
    "pv": 30,
    "max_pv": 80,
    "img": "..."
  },
  "logs": ["--- Tour 1 ---", "Guerrier attaque...", ...],
  "waiting_for_me": true,
  "waiting_for_opponent": false
}
```

Logique:
- Charge l'état du combat (fichier `.state`)
- Construit la réponse formatée pour `combat.php`
- Détermine qui doit jouer ensuite

---

##### **POST `/api.php?action=submit_move`**
Paramètres:
- `match_id` (POST)
- `move` (POST): `"attack"` | `"heal"` | ...

Réponse:
```json
{ "status": "ok" | "error", "message": "..." }
```

Logique:
- Enregistre l'action du joueur
- **Si c'est un combat BOT:** génère automatiquement l'action du bot
- Si les deux ont joué (ou bot match) → résout le tour
- Passe au tour suivant
- Vérifie si combat est terminé

---

### 4. **Améliorations `MatchQueue.php`**

#### Nouvelle Méthode: `getQueueCount()`
```php
public function getQueueCount() {
    // Retourne le nombre de joueurs en queue
    // Nettoie les entrées expirées automatiquement
}
```

#### Améliorations à `checkMatchStatus()`
- Retourne maintenant `queue_count` dans la réponse
- Gère le timeout et la création automatique du bot
- Crée le fichier match bot avec `"mode": "bot"`

---

### 5. **Logique BOT Améliorée**

Quand 30 secondes écoulées:
1. Crée un match bot automatiquement
2. Sélectionne un héros ennemi aléatoire (différent du joueur)
3. Marque le match avec `"mode": "bot"`
4. Dans `submit_move`: génère automatiquement les actions bot

**IA Bot:** 
- Si PV < 30% → `heal`
- Sinon → random entre `attack` et `heal`

---

## 🎯 Architecture Globale

```
User Flow:

multi_player.php (sélection + queue)
         ↓
   [Sélectionne héros]
         ↓
   POST /api/join_queue
         ↓
   ┌─────────────────┬──────────────────┐
   ↓                 ↓                  ↓
Matched             Waiting           Error
   ↓                 ↓
 combat.php   (polling queue)
   ↓                 ↓
   └─────────────────┘
         ↓
(30s timeout ou match trouvé)
         ↓
   combat.php?match_id=xxx
         ↓
GET /api/poll_status (chaque 2s)
         ↓
    [COMBAT]
         ↓
POST /api/submit_move (quand joueur clique)
         ↓
Bot génère action ou attend autre joueur
         ↓
(Résolution du tour)
         ↓
GET /api/poll_status (update)
         ↓
... (repeat) ...
         ↓
   [Fin de match]
         ↓
Menu principal
```

---

## 📁 Fichiers Affectés

| Fichier | Type | Changement |
|---------|------|-----------|
| [combat.php](combat.php) | NOUVEAU | Page de combat en multiplayer |
| [multi_player.php](multi_player.php) | REFACTORISÉ | Juste sélection + queue |
| [api.php](api.php) | REWORKÉ | Nouveaux endpoints |
| [classes/MatchQueue.php](classes/MatchQueue.php) | AMÉLIORÉ | Compteur queue + meilleur timeout |
| [classes/MultiCombat.php](classes/MultiCombat.php) | INCHANGÉ | Réutilisé tel quel |
| [style.css](style.css) | INCHANGÉ | Réutilisé tel quel |

---

## 🧪 Tests Recommandés

### Test 1: Sélection Hero
1. Ouvrir http://localhost/nodeTest2/mood-checker/php_test/multi_player.php
2. Cliquer sur une carte de héros
3. ✅ Doit afficher l'écran de queue avec compteur

### Test 2: Compteur Queue (2 joueurs)
1. Ouvrir 2 onglets du navigateur (multi_player.php)
2. Joueur 1: Sélectionne un héros
3. Joueur 2: Sélectionne un héros
4. ✅ Les deux voient le compteur à "2"
5. ✅ Dès que joueur 2 rejoint → match créé
6. ✅ Les deux redirigent vers combat.php

### Test 3: Timeout Bot (1 joueur)
1. Ouvrir multi_player.php
2. Sélectionner un héros
3. Attendre 30 secondes
4. ✅ Compteur compte jusqu'à 30
5. ✅ Redirect automatique vers combat.php
6. ✅ Voir "Le bot arrive en renfort !" dans les logs

### Test 4: Combat PvP (2 joueurs)
1. 2 joueurs dans la queue
2. Match créé
3. Tous les deux redirigent vers combat.php
4. ✅ Interface affiche les deux personnages
5. ✅ Un joueur clique sur bouton → son action s'enregistre
6. ✅ L'autre joueur voit "En attente de l'adversaire..."
7. ✅ Quand les deux ont joué → tour se résout
8. ✅ Combat progresse normalement

### Test 5: Combat PvB (1 joueur + Bot)
1. Joueur dans queue → 30s timeout → bot créé
2. Combat.php se charge
3. ✅ Affiche "Le bot arrive en renfort !"
4. ✅ Joueur clique sur action
5. ✅ Bot génère action AUTOMATIQUEMENT
6. ✅ Combat se résout immédiatement
7. ✅ Combat progresse rapidement (pas d'attente)

---

## ⚠️ Points Critiques à Vérifier

1. **Queue.json permissions:** Doit être writable par PHP
2. **Match files:** Créés dans `data/matches/`
3. **Sessions:** Chaque tab a une session différente (très important pour les tests!)
4. **Polling timing:** 2s pour combat, 1s pour queue
5. **Timeout:** Exécuté au bout de 30 secondes exactement

---

## 🔧 Debugging

### Si queue ne marche pas:
1. Vérifier permissions: `data/queue.json`
2. Vérifier sessions différentes (Private Browsing)
3. Voir logs de `/api/poll_queue`

### Si combat ne charge pas:
1. Vérifier match file existe: `data/matches/match_xxxxx.json`
2. Vérifier MultiCombat.state existe
3. Vérifier `/api/poll_status` retourne les données

### Si bot ne joue pas:
1. Vérifier match a `"mode": "bot"`
2. Vérifier `generateBotMove()` dans api.php
3. Voir si player2 est marqué `"is_bot": true`

---

## 📊 Changements de Code

### Multi_player.php (avant → après)
- **Avant:** 279 lignes (sélection + queue + combat)
- **Après:** 120 lignes (sélection + queue uniquement)
- **Combat déplacé vers:** combat.php (250 lignes)

### API (avant → après)
- **Avant:** Endpoints mixtes et incomplets
- **Après:** 4 endpoints clairs et séparés

### MatchQueue (avant → après)
- **Avant:** Compteur n'existait pas
- **Après:** `getQueueCount()` + retur queue_count dans réponses

---

## ✨ Points Forts de cette Architecture

✅ **Séparation des concerns**
- Selection page: multi_player.php
- Combat page: combat.php
- Logic: api.php

✅ **Compteur visible**
- Joueurs voient combien d'autres attendent
- Crée une meilleure UX

✅ **Bot fallback automatique**
- Après 30s → bot créé
- Joueur ne doit rien faire

✅ **PvP et PvB supportés**
- Même code pour les deux
- Déterminé par champ `mode`

✅ **Interface cohérente**
- Combat.php = single_player.php adapté
- Même design, même gameplay

---

**Status:** ✅ REBUILT ET PRÊT POUR LES TESTS
