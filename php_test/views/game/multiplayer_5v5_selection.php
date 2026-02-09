<?php
/** VUE: Multiplayer 5v5 Selection - Sélection équipe + Queue 5v5 */
?>

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION D'UNE ÉQUIPE -->
    <div id="selectionScreen">
        <div class="selection-header">
            <h2>SÉLECTIONNEZ VOTRE ÉQUIPE 5v5</h2>
            <p>Choisissez l'une de vos équipes pré-créées pour le combat</p>
            <a href="<?php echo View::url('account?tab=teams'); ?>" class="btn-manage-teams">Gérer mes équipes</a>
        </div>

        <?php if (empty($userTeams)): ?>
            <div class="no-teams-message">
                <div class="message-icon"><img src="<?php echo View::asset('media/website/players.png'); ?>" alt="Équipe"></div>
                <h3>Aucune équipe disponible</h3>
                <p>Vous devez d'abord créer une équipe de 5 héros pour jouer en mode 5v5.</p>
                <a href="<?php echo View::url('account?tab=teams'); ?>" class="btn-create-team">Créer une équipe</a>
            </div>
        <?php else: ?>
            <div class="teams-list">
                <?php foreach ($userTeams as $teamData): 
                    $team = $teamData['team'];
                    $members = $teamData['members'];
                ?>
                    <div class="team-card" data-team-id="<?php echo $team->id; ?>">
                        <div class="team-header">
                            <h3><?php echo htmlspecialchars($team->team_name); ?></h3>
                            <?php if ($team->description): ?>
                                <p class="team-description"><?php echo htmlspecialchars($team->description); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="team-members-preview">
                            <?php foreach ($members as $member): 
                                $heroImg = $member->image_p1 ?? 'media/heroes/default.png';
                                $blessingId = $member->blessing_id ?? null;
                                $blessingData = $blessingId && isset($blessingsById[$blessingId]) ? $blessingsById[$blessingId] : null;
                            ?>
                                <div class="member-preview">
                                    <div class="member-img-container">
                                        <img src="<?php echo htmlspecialchars(View::asset($heroImg)); ?>" 
                                             alt="<?php echo htmlspecialchars($member->name); ?>"
                                             title="<?php echo htmlspecialchars($member->name . ' - ' . $member->type); ?>">
                                        <?php if ($blessingData): ?>
                                            <div class="member-blessing" title="<?php echo htmlspecialchars($blessingData['name']); ?>">
                                                <img src="<?php echo View::asset('media/blessings/' . $blessingData['img']); ?>" 
                                                     alt="<?php echo htmlspecialchars($blessingData['name']); ?>">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="member-name"><?php echo htmlspecialchars($member->name); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php 
                        // Encoder les données en JSON puis échapper pour HTML
                        $teamNameJson = json_encode($team->team_name);
                        $membersJson = json_encode($members);
                        ?>
                        <button class="btn-select-team" onclick="selectTeam(<?php echo $team->id; ?>, <?php echo htmlspecialchars($teamNameJson, ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($membersJson, ENT_QUOTES, 'UTF-8'); ?>)">
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
<script src="<?php echo View::asset('js/multiplayer-5v5-selection.js'); ?>"></script>
<script>
// Initialiser avec les paramètres PHP
init5v5Selection(
    '<?php echo View::asset(""); ?>',
    '<?php echo htmlspecialchars($username ?? 'Joueur'); ?>',
    '<?php echo View::url("api"); ?>'
);
</script>
