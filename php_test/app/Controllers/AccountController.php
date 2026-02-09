<?php
/**
 * ACCOUNTCONTROLLER - Gestion du compte utilisateur et statistiques
 */

class AccountController extends Controller {
    private User $userModel;
    private TeamManager $teamManager;
    private HeroManager $heroManager;
    
    public function __construct() {
        parent::__construct();
        $this->userModel = new User();
        $this->teamManager = new TeamManager();
        $this->heroManager = new HeroManager();
    }
    
    /**
     * Page principale du compte
     */
    public function index(): void {
        $this->requireAuth('/login');
        
        $userId = User::getCurrentUserId();
        $username = User::getCurrentUsername();
        
        // Récupérer toutes les données
        $data = $this->gatherAccountData($userId);
        $data['username'] = $username;
        $data['userId'] = $userId;
        
        // Configuration page
        $data['pageTitle'] = 'Mon Compte - Horus Battle Arena';
        $data['extraCss'] = ['account', 'shared-selection', 'multiplayer'];
        $data['showUserBadge'] = true;
        $data['showMainTitle'] = true;
        
        // Tab sélectionné après action
        $data['postTabToSelect'] = '';
        
        $this->render('account/index', $data);
    }
    
    /**
     * Créer une équipe
     */
    public function createTeam(): void {
        $this->requireAuth('/login');
        
        $userId = User::getCurrentUserId();
        $teamName = trim($this->post('team_name', ''));
        $description = $this->post('team_description', '');
        
        if ($teamName && strlen($teamName) > 0) {
            $newTeamId = $this->teamManager->createTeam($userId, $teamName, $description);
            if ($newTeamId) {
                $this->flash('success', 'Équipe créée avec succès!');
            } else {
                $this->flash('error', 'Erreur lors de la création de l\'équipe.');
            }
        }
        
        $this->redirect('/account');
    }
    
    /**
     * Supprimer une équipe
     */
    public function deleteTeam(): void {
        $this->requireAuth('/login');
        
        $userId = User::getCurrentUserId();
        $teamId = (int) $this->post('team_id', 0);
        
        if ($teamId && $this->teamManager->userOwnsTeam($userId, $teamId)) {
            if ($this->teamManager->deleteTeam($teamId)) {
                $this->flash('success', 'Équipe supprimée avec succès!');
            }
        }
        
        $this->redirect('/account');
    }
    
    /**
     * Ajouter un héros à une équipe
     */
    public function addHeroToTeam(): void {
        $this->requireAuth('/login');
        
        $userId = User::getCurrentUserId();
        $teamId = (int) $this->post('team_id', 0);
        $position = (int) $this->post('position', 0);
        $heroId = $this->post('hero_id', '');
        $blessingId = $this->post('blessing_id', null);
        
        if (!empty($heroId) && $position >= 1 && $position <= 5) {
            if ($this->teamManager->userOwnsTeam($userId, $teamId)) {
                if ($this->teamManager->addMemberToTeam($teamId, $position, $heroId, $blessingId)) {
                    $this->flash('success', 'Héros ajouté à l\'équipe!');
                } else {
                    $this->flash('error', 'Erreur lors de l\'ajout du héros.');
                }
            }
        }
        
        $this->redirect('/account');
    }
    
    /**
     * Retirer un héros d'une équipe
     */
    public function removeHeroFromTeam(): void {
        $this->requireAuth('/login');
        
        $userId = User::getCurrentUserId();
        $teamId = (int) $this->post('team_id', 0);
        $position = (int) $this->post('position', 0);
        
        if ($position >= 1 && $position <= 5) {
            if ($this->teamManager->userOwnsTeam($userId, $teamId)) {
                if ($this->teamManager->removeMemberFromTeam($teamId, $position)) {
                    $this->flash('success', 'Héros retiré de l\'équipe!');
                }
            }
        }
        
        $this->redirect('/account');
    }
    
    /**
     * Rassembler toutes les données du compte
     */
    private function gatherAccountData(int $userId): array {
        // Statistiques 1v1
        $globalStats = $this->userModel->get1v1GlobalStats($userId);
        $mostPlayed = $this->userModel->getMostPlayedHeroes($userId, 3, null, true);
        $heroStats = $this->userModel->getHeroStats($userId, null, true);
        $recentCombats = $this->userModel->getRecentCombats($userId, 10, null, true);
        
        // Statistiques 5v5
        $stats5v5 = $this->userModel->get5v5Stats($userId);
        $statsByMode = $this->userModel->getStatsByMode($userId);
        $bestHero5v5 = $this->userModel->getBestHeroByWinrate($userId, '5v5', 2);
        
        // Leaderboard
        $leaderboard = $this->userModel->getLeaderboard(20);
        
        // Noms des héros
        $heroesModels = $this->heroManager->getAll(true);
        $heroNames = [];
        foreach ($heroesModels as $hero) {
            $heroNames[$hero->getHeroId()] = $hero->getName();
        }
        
        // Équipes de l'utilisateur
        $userTeams = $this->teamManager->getTeamsByUser($userId);
        
        return [
            'globalStats' => $globalStats,
            'mostPlayed' => $mostPlayed,
            'heroStats' => $heroStats,
            'recentCombats' => $recentCombats,
            'stats5v5' => $stats5v5,
            'statsByMode' => $statsByMode,
            'bestHero5v5' => $bestHero5v5,
            'leaderboard' => $leaderboard,
            'heroNames' => $heroNames,
            'userTeams' => $userTeams
        ];
    }
}
