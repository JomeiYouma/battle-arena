# 🎮 Mood Checker - Multiplayer Rebuild Complete

## ✨ What's New

Your multiplayer system has been completely rebuilt with:

### ✅ Hero Selection → Queue with 30s Countdown → Auto Bot Fallback

**User Journey:**
1. **Select Hero** - Pick from your character roster with stats preview
2. **Join Queue** - See live 30-second countdown ⏳
3. **Wait for Player** - If someone joins within 30s → 1v1 Battle
4. **Or Fight Bot** - After 30s timeout → Auto battle against random bot
5. **Combat** - Identical arena layout for both player vs player and player vs bot
6. **Result** - Victory or defeat, no stats tracking

---

## 🚀 GitHub Auto-Deploy Setup

**To enable automatic deployment when you push to GitHub:**

1. Go to your GitHub repository **Settings**
2. Add 4 **Actions Secrets**:
   - `DEPLOY_HOST` → your server IP/domain
   - `DEPLOY_USER` → SSH username
   - `DEPLOY_KEY` → SSH private key (generate with `ssh-keygen`)
   - `DEPLOY_PATH` → `/var/www/mood-checker` (or your path)

3. **Push to main branch** → Automatic deployment! 🎯

See [DEPLOY_SETUP.md](DEPLOY_SETUP.md) for detailed instructions.

---

## 🎨 Design

- **Dark dungeon theme** - consistent with single-player
- **Dark red & gold accents** - same color palette
- **Responsive layout** - works on desktop/tablet/mobile
- **Smooth animations** - fade-ins, transitions, glow effects

### Queue Screen
```
       🔴 RECHERCHE D'ADVERSAIRE 🔴
       
              ⏳ Loader
              
       Timeout dans 30s
       
       ❌ Annuler la Recherche
```

### Combat Arena
```
       ⚔️ TOUR 3 ⚔️
       
    [Player Stats]    [Opponent Stats]
        PV Bar ▯▯▯       PV Bar ▯▯▯
        
        🧙‍♂️ VS 🗡️
        
    📜 Battle Log (scrollable)
    
    [Attack] [Heal] [Special]
```

---

## 🤖 Bot AI

The bot opponent uses **simple, random AI**:
- Chooses a random action from available moves
- **Heals when HP < 30%** (basic survival logic)
- No wins tracking - pure gameplay
- Works with all hero types

---

## 📋 File Changes

### New Files
- `.github/workflows/deploy.yml` - GitHub Actions workflow
- `DEPLOY_SETUP.md` - Deployment guide
- `MULTIPLAYER_REBUILD.md` - Technical documentation

### Modified Files
- `php_test/multi_player.php` - UI + JavaScript
- `php_test/api.php` - Bot move generation
- `php_test/classes/MatchQueue.php` - Bot creation on timeout
- `php_test/classes/MultiCombat.php` - Type field in response
- `php_test/style.css` - Multiplayer styling

---

## 🧪 Quick Test

1. Open browser: `http://localhost/nodeTest2/mood-checker/php_test/index.php`
2. Click "Mode Multijoueur"
3. **Test A: Quick Match**
   - Open in 2 browser tabs, select heroes in both
   - Should match immediately ✅

4. **Test B: Bot Fallback**
   - Open in 1 tab, select hero
   - Watch 30-second countdown
   - Should auto-create bot opponent ✅

5. **Test C: Combat**
   - Play 1-2 turns
   - Verify HP bars update
   - Bot responds automatically ✅

---

## 🔧 Configuration

All timing is in seconds. To modify:

**Queue timeout (30s):**
- File: `php_test/classes/MatchQueue.php`
- Line: `private $timeoutSeconds = 30;`

**Polling interval (2s):**
- File: `php_test/multi_player.php`
- Search: `setInterval(updateCombatState, 2000);`

---

## 📞 Support

If you encounter issues:

1. **Check PHP syntax**: `php -l api.php`
2. **Verify file permissions**: `/data/` folder must be writable
3. **Check logs**: Browser console (F12) for JavaScript errors
4. **Review**: `/data/matches/match_*.json` files for match state

---

## 📝 Notes

- ✅ All PHP files pass syntax validation
- ✅ Responsive design (mobile, tablet, desktop)
- ✅ No database needed (file-based queue)
- ✅ Compatible with existing hero system
- ✅ Ready for production deployment

---

**Status:** 🟢 **READY TO DEPLOY**

Push to GitHub main branch to auto-deploy! 🚀
