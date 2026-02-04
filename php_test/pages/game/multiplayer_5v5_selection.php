<?php
/** MULTIPLAYER_5V5_SELECTION - Sélection équipe + Queue 5v5 */

require_once __DIR__ . '/../../includes/autoload.php';

unset($_SESSION['matchId']);
unset($_SESSION['queue5v5Status']);
unset($_SESSION['queue5v5Team']);
unset($_SESSION['queue5v5DisplayName']);
unset($_SESSION['queue5v5BlessingId']);

if (!User::isLoggedIn()) {
    header('Location: ../auth/login.php?redirect=multiplayer_5v5_selection.php');
    exit;
}

$userId = User::getCurrentUserId();
$pdo = Database::getInstance();

// Récupérer les équipes de l'utilisateur (5 héros obligatoires)
$stmt = $pdo->prepare("
    SELECT t.*, 
           COUNT(tm.id) as hero_count
    FROM teams t
    LEFT JOIN team_members tm ON t.id = tm.team_id
    WHERE t.user_id = ? AND t.is_active = 1
    GROUP BY t.id
    HAVING hero_count = 5
    ORDER BY t.updated_at DESC
");
$stmt->execute([$userId]);
$userTeams = $stmt->fetchAll();

// Charger les bénédictions pour l'affichage
require_once COMPONENTS_PATH . '/selection-utils.php';
$blessingsList = getBlessingsList();
$blessingsById = [];
foreach ($blessingsList as $b) {
    $blessingsById[$b['id']] = $b;
}

$pageTitle = 'Multijoueur 5v5 - Horus Battle Arena';
$extraCss = ['shared-selection', 'multiplayer', 'multiplayer-5v5-selection'];
$showUserBadge = true;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION D'UNE ÉQUIPE -->
    <div id="selectionScreen">
        <div class="selection-header">
            <a href="multiplayer.php" class="back-link">← Retour aux modes</a>
            <h2>SÉLECTIONNEZ VOTRE ÉQUIPE 5v5</h2>
            <p>Choisissez l'une de vos équipes pré-créées pour le combat</p>
            <a href="../account.php?tab=teams" class="btn-manage-teams">Gérer mes équipes</a>
        </div>

        <?php if (empty($userTeams)): ?>
            <div class="no-teams-message">
                <div class="message-icon"><img src="<?php echo $basePath; ?>public/media/website/players.png" alt="Équipe"></div>
                <h3>Aucune équipe disponible</h3>
                <p>Vous devez d'abord créer une équipe de 5 héros pour jouer en mode 5v5.</p>
                <a href="../account.php?tab=teams" class="btn-create-team">Créer une équipe</a>
            </div>
        <?php else: ?>
            <div class="teams-list">
                <?php foreach ($userTeams as $team): 
                    // Récupérer les membres de l'équipe
                    $stmtMembers = $pdo->prepare("
                        SELECT tm.position, tm.hero_id, tm.blessing_id, h.*
                        FROM team_members tm
                        LEFT JOIN heroes h ON tm.hero_id = h.hero_id
                        WHERE tm.team_id = ?
                        ORDER BY tm.position ASC
                    ");
                    $stmtMembers->execute([$team['id']]);
                    $members = $stmtMembers->fetchAll();
                ?>
                    <div class="team-card" data-team-id="<?php echo $team['id']; ?>">
                        <div class="team-header">
                            <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                            <?php if ($team['description']): ?>
                                <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="team-members-preview">
                            <?php foreach ($members as $member): 
                                $heroImg = $member['image_p1'] ?? 'media/heroes/default.png';
                                $blessingId = $member['blessing_id'] ?? null;
                                $blessingData = $blessingId && isset($blessingsById[$blessingId]) ? $blessingsById[$blessingId] : null;
                            ?>
                                <div class="member-preview">
                                    <div class="member-img-container">
                                        <img src="<?php echo htmlspecialchars(asset_url($heroImg)); ?>" 
                                             alt="<?php echo htmlspecialchars($member['name']); ?>"
                                             title="<?php echo htmlspecialchars($member['name'] . ' - ' . $member['type']); ?>">
                                        <?php if ($blessingData): ?>
                                            <div class="member-blessing" title="<?php echo htmlspecialchars($blessingData['name']); ?>">
                                                <img src="<?php echo asset_url('media/blessings/' . $blessingData['img']); ?>" 
                                                     alt="<?php echo htmlspecialchars($blessingData['name']); ?>">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php 
                        // Encoder les données en JSON puis échapper pour HTML
                        $teamNameJson = json_encode($team['team_name']);
                        $membersJson = json_encode($members);
                        ?>
                        <button class="btn-select-team" onclick="selectTeam(<?php echo $team['id']; ?>, <?php echo htmlspecialchars($teamNameJson, ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($membersJson, ENT_QUOTES, 'UTF-8'); ?>)">
                            Combattre avec cette équipe
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ÉCRAN 2: QUEUE / MATCHMAKING -->
    <div id="queueScreen" style="display:none;">
        <div class="queue-box">
            <h2>RECHERCHE D'ADVERSAIRE...</h2>
            <div class="queue-spinner"></div>
            
            <div class="queue-team-preview" id="queueTeamPreview">
                <!-- Prévisualisé par JS -->
            </div>
            
            <div class="queue-timer">
                <span id="queueSeconds">0</span>s / 30s
            </div>
            <div class="queue-progress">
                <div class="queue-progress-bar" id="queueProgressBar"></div>
            </div>
            
            <p class="queue-hint">Match contre un joueur réel, ou un bot si aucun adversaire trouvé après 30s</p>
            
            <button id="cancelQueue" class="btn-cancel">Annuler la recherche</button>
        </div>
    </div>
</div>

<!-- JavaScript externe pour la sélection 5v5 -->
<script src="<?php echo asset_url('js/multiplayer-5v5-selection.js'); ?>"></script>
<script>
// Initialiser avec les paramètres PHP
init5v5Selection(
    '<?php echo asset_url(""); ?>',
    '<?php echo htmlspecialchars($_SESSION['username'] ?? 'Joueur'); ?>'
);
</script>

<?php 
$showBackLink = false; // Le back-link est déjà dans la page
require_once INCLUDES_PATH . '/footer.php'; 
?>
