# 🎮 Multiplayer Rebuild - Visual Architecture

## System Flow

```
┌────────────────────────────────────────────────────────────────────┐
│                    MOOD CHECKER - MULTIPLAYER MODE                 │
└────────────────────────────────────────────────────────────────────┘

                            ┌─────────────────┐
                            │  START GAME     │
                            └────────┬────────┘
                                     │
                    ┌────────────────▼────────────────┐
                    │   HERO SELECTION SCREEN         │
                    │  (Grid layout, clickable cards) │
                    └────────────────┬────────────────┘
                                     │
                                     ▼
                    ┌────────────────────────────────┐
                    │   JOIN QUEUE                   │
                    │   (Player added to queue)      │
                    └────────────────┬───────────────┘
                                     │
                    ┌────────────────▼───────────────┐
                    │   QUEUE SCREEN                 │
                    │   ⏳ Countdown: 30s → 0s       │
                    │   [Annuler la Recherche]       │
                    └────────────────┬───────────────┘
                                     │
                ┌────────────────────┴──────────────────────┐
                │                                            │
                ▼                                            ▼
    ┌──────────────────────┐               ┌──────────────────────┐
    │ SCENARIO A:          │               │ SCENARIO B:          │
    │ PLAYER JOINS (< 30s) │               │ TIMEOUT (= 30s)      │
    │ ✅ Matched           │               │ ✅ Bot Created       │
    └──────┬───────────────┘               └──────┬───────────────┘
           │                                       │
           │    (poll_status returns 'matched')    │
           │    (poll_status returns 'timeout')    │
           │                                       │
           └─────────────────┬─────────────────────┘
                             │
                   ┌─────────▼─────────┐
                   │  START COMBAT     │
                   │  (Initialize      │
                   │   match state)    │
                   └─────────┬─────────┘
                             │
                   ┌─────────▼────────────────┐
                   │  COMBAT ARENA            │
                   │  ┌─────────────────────┐ │
                   │  │ Turn 1, 2, 3, ...   │ │
                   │  │ [Stats] VS [Stats]  │ │
                   │  │ 🧙‍♂️  VS  🗡️ │
                   │  │ Battle Log          │ │
                   │  │ [Act1] [Act2]...    │ │
                   │  └─────────────────────┘ │
                   └─────────┬────────────────┘
                             │
            ┌────────────────┴────────────────┐
            │ Player Submits Action           │
            │ Bot auto-generates if PvB       │
            │ Both actions → Turn Resolution  │
            │ Update HP/Stats/Log             │
            └────────────────┬────────────────┘
                             │
                    ┌────────▼─────────┐
                    │  Repeat until    │
                    │  Victory/Defeat  │
                    └────────┬─────────┘
                             │
                   ┌─────────▼──────────┐
                   │  GAME OVER         │
                   │  "VICTOIRE!" or    │
                   │  "DÉFAITE..."      │
                   │  [Menu Principal]  │
                   └────────────────────┘
```

---

## API Communication

```
CLIENT (JavaScript)              SERVER (PHP/API)
────────────────────────────────────────────────────

selectHero('id')
    ├─ POST api.php?action=join_queue
    │                  ├─ Add to queue
    │                  ├─ Check for match
    │                  └─ return {status: 'matched'} or 'waiting'
    │
    ├─ startPollingQueue()
    │    (every 2 seconds)
    │
    ├─ GET api.php?action=poll_status
    │                  ├─ Check queue.json
    │                  ├─ Check matches/*.json
    │                  ├─ If timeout: create bot, return {status: 'timeout'}
    │                  ├─ If matched: return {status: 'matched', matchId: 'X'}
    │                  └─ Otherwise: return {status: 'waiting'}
    │
    ├─ startCombat()
    │
    ├─ updateCombatState()
    │    (every 2 seconds)
    │
    ├─ GET api.php?action=poll_status&match_id=X
    │                  ├─ Load match state
    │                  ├─ Load combat state
    │                  └─ return {turn, me, opponent, logs, waiting_for_me, etc}
    │
    ├─ USER CLICKS ACTION
    │
    ├─ POST api.php?action=submit_move
    │                  ├─ Record player action
    │                  ├─ If bot match: generate bot action
    │                  ├─ If both actions: resolve turn
    │                  ├─ Save match state
    │                  └─ return {status: 'ok'}
    │
    └─ [Repeat until game over]
```

---

## File Structure

```
mood-checker/
├── .github/
│   └── workflows/
│       └── deploy.yml ⭐ NEW
│
├── php_test/
│   ├── index.php (unchanged)
│   ├── single_player.php (unchanged)
│   ├── multi_player.php ✏️ MODIFIED
│   ├── api.php ✏️ MODIFIED
│   ├── style.css ✏️ MODIFIED
│   │
│   ├── classes/
│   │   ├── MatchQueue.php ✏️ MODIFIED
│   │   ├── MultiCombat.php ✏️ MODIFIED
│   │   ├── AutoCombat.php (unchanged)
│   │   ├── Combat.php (unchanged)
│   │   ├── Personnage.php (unchanged)
│   │   ├── [Hero classes] (unchanged)
│   │   └── effects/ (unchanged)
│   │
│   ├── data/
│   │   ├── queue.json (runtime)
│   │   └── matches/
│   │       ├── match_*.json (runtime)
│   │       └── match_*.state (runtime)
│   │
│   ├── media/ (unchanged)
│   └── [other files] (unchanged)
│
├── .git/ (existing)
├── DEPLOY_SETUP.md ⭐ NEW
├── MULTIPLAYER_REBUILD.md ⭐ NEW
├── README_MULTIPLAYER.md ⭐ NEW
└── IMPLEMENTATION_CHECKLIST.md ⭐ NEW
```

---

## Screen Mockups

### HERO SELECTION
```
╔════════════════════════════════════╗
║  Choisissez votre Champion         ║
║                                    ║
║  ┌──────┐ ┌──────┐ ┌──────┐       ║
║  │ Hero1│ │ Hero2│ │ Hero3│ ...  ║
║  │ PV:50│ │ PV:40│ │ PV:60│       ║
║  │ ATK:8│ │ATK:10│ │ATK:6 │       ║
║  └──────┘ └──────┘ └──────┘       ║
║                                    ║
║  [More heroes below]               ║
╚════════════════════════════════════╝
```

### QUEUE WAITING
```
╔════════════════════════════════════╗
║                                    ║
║        ⏳  LOADER  ⏳               ║
║                                    ║
║   Recherche d'adversaire...        ║
║   En attente d'un digne opposant..║
║                                    ║
║  Timeout dans 25s 🔴              ║
║                                    ║
║  Un combat contre un bot débutera  ║
║  si personne ne se présente        ║
║                                    ║
║    [Annuler la Recherche]          ║
║                                    ║
╚════════════════════════════════════╝
```

### COMBAT ARENA
```
╔════════════════════════════════════╗
║         ⚔️  TOUR 1  ⚔️              ║
║                                    ║
║ ┌────────────┐      ┌────────────┐ ║
║ │ Moi        │      │ Adversaire │ ║
║ │ Guerrier   │      │ Magicien   │ ║
║ │ [██████▒▒] │      │ [████░░░░░]│ ║
║ │ 45/50      │      │ 30/60      │ ║
║ └────────────┘      └────────────┘ ║
║                                    ║
║        🧙‍♂️    VS    🗡️        ║
║                                    ║
║  ╔════ BATTLE LOG ════╗            ║
║  ║ Turn 1 commenced   ║            ║
║  ║ Moi used Attack!   ║            ║
║  ║ Deals 8 DMG!       ║            ║
║  ║ Adversaire Heals!  ║            ║
║  ╚════════════════════╝            ║
║                                    ║
║   [⚔️ Attack] [💖 Heal]            ║
║                                    ║
╚════════════════════════════════════╝
```

### VICTORY
```
╔════════════════════════════════════╗
║                                    ║
║          ✨ VICTOIRE ! ✨          ║
║                                    ║
║    Vous avez vaincu l'adversaire!  ║
║                                    ║
║      [Menu Principal]              ║
║                                    ║
╚════════════════════════════════════╝
```

---

## Key Improvements

| Before | After |
|--------|-------|
| Basic queue system | ✅ 30-second countdown display |
| No bot fallback | ✅ Auto bot creation on timeout |
| No opponent type info | ✅ Bot vs Player identification |
| Basic styling | ✅ Unified dark dungeon theme |
| Manual deployments | ✅ GitHub Actions auto-deploy |
| No design consistency | ✅ Matches single-player aesthetic |

---

## Technologies Used

- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 7.4+
- **Data Storage:** JSON (file-based, no database needed)
- **Deployment:** GitHub Actions + SSH
- **Version Control:** Git + GitHub

---

**Last Updated:** January 23, 2026  
**Version:** 1.0 - Production Ready  
**Status:** ✅ Complete
