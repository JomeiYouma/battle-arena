<?php
/** TEAMMANAGER - Service de gestion des équipes 5v5 */

class TeamManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function createTeam(int $userId, string $teamName, string $description = ''): ?int {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO teams (user_id, team_name, description, is_active)
                VALUES (?, ?, ?, 1)
            ');
            
            $stmt->execute([$userId, $teamName, $description]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("TeamManager::createTeam error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer toutes les équipes d'un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @param bool $includeInactive Inclure les équipes inactives
     * @return array Liste des équipes avec nombre de membres
     */
    public function getTeamsByUser(int $userId, bool $includeInactive = false): array {
        try {
            $sql = '
                SELECT 
                    t.id,
                    t.team_name,
                    t.description,
                    t.is_active,
                    t.created_at,
                    COUNT(tm.id) as member_count
                FROM teams t
                LEFT JOIN team_members tm ON t.id = tm.team_id
                WHERE t.user_id = ?
            ';
            
            if (!$includeInactive) {
                $sql .= ' AND t.is_active = 1';
            }
            
            $sql .= ' GROUP BY t.id ORDER BY t.created_at DESC';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TeamManager::getTeamsByUser error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer une équipe par ID avec tous ses membres
     * 
     * @param int $teamId ID de l'équipe
     * @return array|null Données de l'équipe avec membres, ou null si non trouvée
     */
    public function getTeamById(int $teamId): ?array {
        try {
            $stmt = $this->pdo->prepare('
                SELECT id, user_id, team_name, description, is_active, created_at
                FROM teams
                WHERE id = ?
            ');
            
            $stmt->execute([$teamId]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$team) {
                return null;
            }

            // Ajouter les membres de l'équipe
            $team['members'] = $this->getTeamMembers($teamId);
            $team['member_count'] = count($team['members']);
            
            return $team;
        } catch (PDOException $e) {
            error_log("TeamManager::getTeamById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour les infos d'une équipe
     * 
     * @param int $teamId ID de l'équipe
     * @param array $updates Données à mettre à jour: ['team_name', 'description', 'is_active']
     * @return bool Succès de l'opération
     */
    public function updateTeam(int $teamId, array $updates): bool {
        try {
            $allowedFields = ['team_name', 'description', 'is_active'];
            $setClause = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($updates[$field])) {
                    $setClause[] = "$field = ?";
                    $values[] = $updates[$field];
                }
            }

            if (empty($setClause)) {
                return false;
            }

            $values[] = $teamId;
            $sql = 'UPDATE teams SET ' . implode(', ', $setClause) . ', updated_at = NOW() WHERE id = ?';
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("TeamManager::updateTeam error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer une équipe
     * 
     * @param int $teamId ID de l'équipe
     * @return bool Succès de l'opération
     */
    public function deleteTeam(int $teamId): bool {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM teams WHERE id = ?');
            return $stmt->execute([$teamId]);
        } catch (PDOException $e) {
            error_log("TeamManager::deleteTeam error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activer/Désactiver une équipe
     * 
     * @param int $teamId ID de l'équipe
     * @param bool $active État désiré
     * @return bool Succès de l'opération
     */
    public function setTeamActive(int $teamId, bool $active): bool {
        return $this->updateTeam($teamId, ['is_active' => $active ? 1 : 0]);
    }

    // ============================================
    // GESTION DES MEMBRES (HÉROS) DE L'ÉQUIPE
    // ============================================

    /**
     * Obtenir tous les membres d'une équipe (triés par position)
     * 
     * @param int $teamId ID de l'équipe
     * @return array Liste des héros avec positions
     */
    public function getTeamMembers(int $teamId): array {
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    tm.id,
                    tm.position,
                    tm.hero_id,
                    tm.blessing_id,
                    h.id as hero_db_id,
                    h.hero_id as hero_code,
                    h.name,
                    h.type,
                    h.pv,
                    h.atk,
                    h.def,
                    h.speed,
                    h.image_p1,
                    h.image_p2,
                    h.description
                FROM team_members tm
                JOIN heroes h ON tm.hero_id = h.hero_id
                WHERE tm.team_id = ?
                ORDER BY tm.position ASC
            ');
            
            $stmt->execute([$teamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TeamManager::getTeamMembers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ajouter un héros à une position dans l'équipe
     * 
     * @param int $teamId ID de l'équipe
     * @param int $position Position (1-5)
     * @param int $heroId ID du héros
     * @param string|null $blessingId ID de la bénédiction (optionnel)
     * @return bool Succès de l'opération
     */
    public function addMemberToTeam(int $teamId, int $position, string $heroId, ?string $blessingId = null): bool {
        // Valider position
        if ($position < 1 || $position > 5) {
            return false;
        }

        try {
            // Vérifier que le héros existe (par hero_id string)
            $heroCheck = $this->pdo->prepare('SELECT id FROM heroes WHERE hero_id = ?');
            $heroCheck->execute([$heroId]);
            
            if (!$heroCheck->fetch()) {
                error_log("TeamManager::addMemberToTeam - Hero not found: $heroId");
                return false;
            }

            // Insérer ou mettre à jour (stocker hero_id string)
            $stmt = $this->pdo->prepare('
                INSERT INTO team_members (team_id, position, hero_id, blessing_id)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    hero_id = VALUES(hero_id),
                    blessing_id = VALUES(blessing_id),
                    created_at = NOW()
            ');
            
            return $stmt->execute([$teamId, $position, $heroId, $blessingId]);
        } catch (PDOException $e) {
            error_log("TeamManager::addMemberToTeam error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retirer un héros d'une position
     * 
     * @param int $teamId ID de l'équipe
     * @param int $position Position à vider
     * @return bool Succès de l'opération
     */
    public function removeMemberFromTeam(int $teamId, int $position): bool {
        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM team_members
                WHERE team_id = ? AND position = ?
            ');
            
            return $stmt->execute([$teamId, $position]);
        } catch (PDOException $e) {
            error_log("TeamManager::removeMemberFromTeam error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour un membre de l'équipe (héros ou bénédiction)
     * 
     * @param int $teamId ID de l'équipe
     * @param int $position Position
     * @param int $heroId ID du nouveau héros
     * @param string|null $blessingId ID de la nouvelle bénédiction
     * @return bool Succès de l'opération
     */
    public function updateTeamMember(int $teamId, int $position, int $heroId, ?string $blessingId = null): bool {
        try {
            // Vérifier que le héros existe
            $heroCheck = $this->pdo->prepare('SELECT id FROM heroes WHERE id = ?');
            $heroCheck->execute([$heroId]);
            
            if (!$heroCheck->fetch()) {
                error_log("TeamManager::updateTeamMember - Hero not found: $heroId");
                return false;
            }

            $stmt = $this->pdo->prepare('
                UPDATE team_members
                SET hero_id = ?, blessing_id = ?
                WHERE team_id = ? AND position = ?
            ');
            
            return $stmt->execute([$heroId, $blessingId, $teamId, $position]);
        } catch (PDOException $e) {
            error_log("TeamManager::updateTeamMember error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si une équipe est complète (5 héros)
     * 
     * @param int $teamId ID de l'équipe
     * @return bool True si l'équipe a 5 héros
     */
    public function isTeamComplete(int $teamId): bool {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) as count FROM team_members WHERE team_id = ?
            ');
            
            $stmt->execute([$teamId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] == 5;
        } catch (PDOException $e) {
            error_log("TeamManager::isTeamComplete error: " . $e->getMessage());
            return false;
        }
    }

    // ============================================
    // GESTION DE L'ÉTAT DE COMBAT 5V5
    // ============================================

    /**
     * Sauvegarder l'état des équipes pendant un combat
     * 
     * @param string $matchId ID unique du match
     * @param array $player1TeamData État sérialisé équipe 1
     * @param array $player2TeamData État sérialisé équipe 2
     * @param int $player1CurrentIdx Index du héros courant P1
     * @param int $player2CurrentIdx Index du héros courant P2
     * @return bool Succès de l'opération
     */
    public function saveTeamCombatState(
        string $matchId,
        array $player1TeamData,
        array $player2TeamData,
        int $player1CurrentIdx = 0,
        int $player2CurrentIdx = 0
    ): bool {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO team_combat_state 
                (match_id, player1_team_data, player2_team_data, player1_current_idx, player2_current_idx, turn_number)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    player1_team_data = VALUES(player1_team_data),
                    player2_team_data = VALUES(player2_team_data),
                    player1_current_idx = VALUES(player1_current_idx),
                    player2_current_idx = VALUES(player2_current_idx),
                    updated_at = NOW()
            ');
            
            return $stmt->execute([
                $matchId,
                json_encode($player1TeamData),
                json_encode($player2TeamData),
                $player1CurrentIdx,
                $player2CurrentIdx
            ]);
        } catch (PDOException $e) {
            error_log("TeamManager::saveTeamCombatState error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer l'état d'un combat d'équipe
     * 
     * @param string $matchId ID unique du match
     * @return array|null État du combat avec toutes les données sérialisées
     */
    public function getTeamCombatState(string $matchId): ?array {
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    match_id,
                    JSON_UNQUOTE(player1_team_data) as player1_team_data,
                    JSON_UNQUOTE(player2_team_data) as player2_team_data,
                    player1_current_idx,
                    player2_current_idx,
                    turn_number,
                    created_at,
                    updated_at
                FROM team_combat_state
                WHERE match_id = ?
            ');
            
            $stmt->execute([$matchId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Décoder les JSON
                $result['player1_team_data'] = json_decode($result['player1_team_data'], true);
                $result['player2_team_data'] = json_decode($result['player2_team_data'], true);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("TeamManager::getTeamCombatState error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour l'index du héros courant pour un joueur
     * 
     * @param string $matchId ID du match
     * @param int $playerNum Numéro du joueur (1 ou 2)
     * @param int $heroIndex Index du nouveau héros (0-4)
     * @return bool Succès de l'opération
     */
    public function updateCurrentHeroIndex(string $matchId, int $playerNum, int $heroIndex): bool {
        if ($playerNum !== 1 && $playerNum !== 2 || $heroIndex < 0 || $heroIndex > 4) {
            return false;
        }

        try {
            $column = $playerNum === 1 ? 'player1_current_idx' : 'player2_current_idx';
            $sql = "UPDATE team_combat_state SET $column = ?, updated_at = NOW() WHERE match_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$heroIndex, $matchId]);
        } catch (PDOException $e) {
            error_log("TeamManager::updateCurrentHeroIndex error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Nettoyer l'état d'un combat terminé
     * 
     * @param string $matchId ID du match
     * @return bool Succès de l'opération
     */
    public function clearTeamCombatState(string $matchId): bool {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM team_combat_state WHERE match_id = ?');
            return $stmt->execute([$matchId]);
        } catch (PDOException $e) {
            error_log("TeamManager::clearTeamCombatState error: " . $e->getMessage());
            return false;
        }
    }

    // ============================================
    // UTILITAIRES
    // ============================================

    /**
     * Vérifier que l'utilisateur possède bien cette équipe
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $teamId ID de l'équipe
     * @return bool True si l'utilisateur possède l'équipe
     */
    public function userOwnsTeam(int $userId, int $teamId): bool {
        try {
            $stmt = $this->pdo->prepare('
                SELECT id FROM teams WHERE id = ? AND user_id = ?
            ');
            
            $stmt->execute([$teamId, $userId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("TeamManager::userOwnsTeam error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compter le nombre d'équipes de l'utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @return int Nombre d'équipes
     */
    public function countUserTeams(int $userId): int {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count FROM teams WHERE user_id = ?');
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['count'];
        } catch (PDOException $e) {
            error_log("TeamManager::countUserTeams error: " . $e->getMessage());
            return 0;
        }
    }
}
