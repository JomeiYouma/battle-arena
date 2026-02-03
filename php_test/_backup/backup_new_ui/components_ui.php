<?php
/**
 * COMPOSANTS UI AMÉLIORÉS
 * Affichage détaillé des capacités avec descriptions complètes
 */

/**
 * Affiche une capacité détaillée avec tous les détails
 */
function renderActionDetailedButton(Personnage $hero, string $actionKey, array $action, bool $canUse = true, bool $isGameOver = false): string {
    $ppText = '';
    if (method_exists($hero, 'getPPText')) {
        $ppText = $hero->getPPText($actionKey);
    }

    $hasPP = isset($action['pp']);
    $disabled = !$canUse || $isGameOver;
    
    // Extraction d'informations détaillées
    $emoji = $action['emoji'] ?? '⚔️';
    $label = $action['label'] ?? 'Capacité';
    $description = $action['description'] ?? 'Pas de description';
    
    // Obtenir la description améliorée (sans abréviations)
    $enhancedDescription = getEnhancedDescription($actionKey, $description);
    
    // Déterminer la classe de rareté/type basée sur le nom de la clé
    $rarity = getActionRarity($actionKey);
    $type = getActionType($actionKey);
    
    $disabledClass = $disabled ? 'disabled' : '';
    $rarityClass = "rarity-{$rarity}";
    $typeClass = "type-{$type}";
    
    $html = '<button type="submit" ';
    $html .= "name=\"action\" ";
    $html .= "value=\"{$actionKey}\" ";
    $html .= "class=\"action-btn-detailed {$actionKey} {$disabledClass} {$rarityClass} {$typeClass}\" ";
    $html .= "title=\"{$description}\" ";
    if ($disabled) $html .= "disabled ";
    $html .= ">";
    
    // Structure du bouton détaillé
    $html .= '<div class="action-detailed-content">';
    
    // Ligne 1: Emoji + Label + Badge Rareté
    $html .= '<div class="action-header">';
    $html .= "<span class=\"action-emoji-large\">$emoji</span>";
    $html .= "<span class=\"action-label-large\">$label</span>";
    if ($hasPP) {
        $html .= "<span class=\"action-pp-badge\">$ppText</span>";
    }
    $html .= '</div>';
    
    // Ligne 2: Description détaillée (sans abréviations)
    $html .= '<div class="action-description-detailed">';
    $html .= escapeDescription($enhancedDescription);
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</button>';
    
    return $html;
}

/**
 * Affiche le panneau complet des capacités
 */
function renderActionsPanel(Personnage $hero, bool $isGameOver = false): string {
    $html = '<div class="actions-panel-detailed">';
    $html .= '<h3 class="actions-title">Capacités de ' . htmlspecialchars($hero->getName()) . '</h3>';
    $html .= '<div class="actions-grid-detailed">';
    
    foreach ($hero->getAllActions() as $key => $action) {
        $canUse = $hero->canUseAction($key);
        $html .= renderActionDetailedButton($hero, $key, $action, $canUse, $isGameOver);
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Obtient le texte complètement déroulé sans abréviations
 */
function getFullStatsText(Personnage $hero): string {
    $stats = [
        'Santé Actuelle' => $hero->getPv(),
        'Santé Maximale' => $hero->getBasePv(),
        'Attaque' => $hero->getAtk(),
        'Défense' => $hero->getDef(),
        'Vitesse' => $hero->getSpeed()
    ];
    
    $text = '';
    foreach ($stats as $label => $value) {
        $text .= "$label: $value | ";
    }
    
    return trim($text, ' |');
}

/**
 * Affiche les stats complètes du personnage
 */
function renderCharacterStats(Personnage $hero): string {
    $html = '<div class="character-stats-full">';
    
    // Section Santé
    $html .= '<div class="stats-health">';
    $html .= '<div>';
    $html .= '<span class="stat-label">Héros: </span>';
    $html .= '<span class="stat-value">' . htmlspecialchars($hero->getName()) . '</span>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<span class="stat-label">Santé: </span>';
    $html .= '<span class="stat-value">' . htmlspecialchars($hero->getPv()) . ' / ' . htmlspecialchars($hero->getBasePv()) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Section Combat
    $html .= '<div class="stats-combat">';
    $html .= '<div>';
    $html .= '<span class="stat-label">Attaque: </span>';
    $html .= '<span class="stat-value">' . htmlspecialchars($hero->getAtk()) . '</span>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<span class="stat-label">Défense: </span>';
    $html .= '<span class="stat-value">' . htmlspecialchars($hero->getDef()) . '</span>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<span class="stat-label">Vitesse: </span>';
    $html .= '<span class="stat-value">' . htmlspecialchars($hero->getSpeed()) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Détermine la rareté d'une capacité
 */
function getActionRarity(string $actionKey): string {
    $basic = ['attack'];
    $common = ['heal', 'defend', 'shield', 'warcry', 'cri_de_guerre', 'coup_de_hache'];
    $rare = ['rage', 'levee_de_bouclier', 'charge', 'fury', 'fureur', 'decharge', 'jugement', 'danse_glaciale'];
    $epic = ['deathbomb', 'necro_special'];
    
    if (in_array($actionKey, $basic)) return 'basic';
    if (in_array($actionKey, $common)) return 'common';
    if (in_array($actionKey, $rare)) return 'rare';
    if (in_array($actionKey, $epic)) return 'epic';
    
    return 'common';
}

/**
 * Détermine le type de capacité
 */
function getActionType(string $actionKey): string {
    $offensive = ['attack', 'coup_de_hache', 'charge', 'fury', 'fureur', 'decharge', 'jugement', 'danse_glaciale', 'eclair'];
    $defensive = ['shield', 'levee_de_bouclier', 'defend', 'armure', 'protection'];
    $supportive = ['heal', 'devourer', 'bless', 'benediction', 'barriere'];
    $cc = ['curse', 'malefice', 'paralyse', 'gel'];
    
    if (in_array($actionKey, $offensive)) return 'offensive';
    if (in_array($actionKey, $defensive)) return 'defensive';
    if (in_array($actionKey, $supportive)) return 'supportive';
    if (in_array($actionKey, $cc)) return 'cc';
    
    return 'neutral';
}

/**
 * Récupère une description améliorée SANS abréviations et OBJECTIVE avec chiffres
 */
function getEnhancedDescription(string $actionKey, string $fallback): string {
    $descriptions = [
        // === ACTIONS DE BASE ===
        'attack' => 'Utilise la statistique d\'attaque pour infliger des dégâts à l\'ennemi. Les dégâts = Attaque - Défense adverse.',
        'defend' => 'Posture défensive. Augmente la défense de 15 points pendant 1 tour.',
        
        // === PYROMANE ===
        'overheat' => 'Surchauffe. Augmente l\'attaque de 20 points pendant 2 tours. Coûte 10 points de vie.',
        'flamearrow' => 'Flèche enflammée. Inflige 5-7 dégâts supplémentaires et applique brûlure pendant 3 tours.',
        'inferno' => 'Inferno. Inflige 2 fois l\'attaque moins la défense adverse en dégâts. Coûte 20 points de vie.',
        
        // === GUERRIER ===
        'charge' => 'Charge directe. Inflige 1.5 fois l\'attaque à l\'ennemi. Réduit votre défense de 10 points pendant 1 tour.',
        'warcry' => 'Cri de guerre. Augmente votre attaque de 15 points pendant 2 tours.',
        'cri_de_guerre' => 'Cri de bataille. Augmente votre attaque de 15 points pendant 2 tours.',
        'rage' => 'Rage. Augmente l\'attaque de 10 points pendant 2 tours.',
        'shield' => 'Levée de bouclier. Augmente la défense de 15 points pendant 2 tours.',
        
        // === GUÉRISSEUR ===
        'levee_de_bouclier' => 'Levée de bouclier. Augmente la défense de 20 points pendant 1 tour.',
        'devourer' => 'Vol de vie. Inflige dégâts normaux et restaure 50% des dégâts infligés en points de vie.',
        'heal' => 'Soigne. Restaure entre 12 et 28 points de vie selon le héros.',
        'bless' => 'Bénédiction. Augmente la défense de 5 points pendant 3 tours et restaure 10 points de vie.',
        'smite' => 'Châtiment. Inflige votre attaque plus 5 points sans tenir compte de la défense adverse.',
        'barrier' => 'Barrière. Augmente la défense de 25 points pendant 1 tour.',
        
        // === BRUTE ===
        'coup_de_hache' => 'Coup de hache. Attaque qui ignore 30% de la défense adverse. Bonus de 15 dégâts si santé inférieure à 30%.',
        'stomp' => 'Piétinement. Inflige dégâts normaux et réduit la vitesse de l\'ennemi de 10 points pendant 3 tours.',
        'bonearmor' => 'Armure d\'os. Augmente votre défense de 10 points pendant 2 tours.',
        'deathbomb' => 'Bombe finale (santé < 20%). Inflige 60% de votre santé maximale en dégâts différés (activé au prochain tour).',
        'fury' => 'Fureur. Inflige 2 fois l\'attaque à l\'ennemi. Coûte 15 points de vie.',
        
        // === AQUATIQUE ===
        'dodge' => 'Esquive. 50% de probabilité d\'esquiver la prochaine attaque entrante.',
        'tsunami' => 'Tsunami. Inflige 1.8 fois l\'attaque à l\'ennemi.',
        
        // === ÉLECTRIQUE ===
        'etincelle' => 'Étincelle. Attaque de base infligeant attaque plus 2-5 points de dégâts.',
        'acceleration' => 'Accélération. Augmente votre vitesse de 10 points pendant 4 tours.',
        'prise_foudre' => 'Prise foudre. Paralyse l\'ennemi pendant 2 tours et réduit sa vitesse de 5 points pendant 4 tours.',
        'decharge' => 'Décharge. Dégâts augmentés de 13 points par action réussie accumulée. Usage unique en combat.',
        
        // === NÉCROMANCIEN ===
        'ordre_necrotique' => 'Ordre nécrotique. Force l\'ennemi à utiliser une de ses actions contre lui-même ou pour vous.',
        'chaines_rituel' => 'Chaînes du rituel. Échange 25% de la santé restante entre vous et l\'ennemi.',
        'malediction' => 'Malédiction. Inflige 5% de la santé maximale de l\'ennemi chaque tour pendant 5 tours.',
        'manipulation_ame' => 'Manipulation de l\'âme. Échange l\'attaque et la défense de l\'ennemi pendant 2 tours.',
        
        // === ACTIONS DE BÉNÉDICTIONS ===
        'concoction' => 'Concoction maladroite. Inflige dégâts aléatoires entre 5 et 20 points.',
        'foudre_amour' => 'Foudre de l\'amour. Inflige dégâts et renvoie 25% des dégâts reçus en retour pendant 2 tours.',
        'grand_conseil' => 'Grand conseil. Augmente tous les stats de 10% pendant 2 tours.',
        'sentence' => 'Sentence. Inflige le double de dégâts aux ennemis dont la santé est inférieure à 50%.',
        'transe_guerriere' => 'Transe guerrière. Augmente l\'attaque de 50 points mais réduit la défense à 0 pendant 2 tours.',
        'fortifications' => 'Fortifications. Augmente la défense de 25 points pendant 3 tours.',
        'jour_nouveau' => 'Jour nouveau. Restaure 30% de la santé maximale et augmente la vitesse de 20% pendant 1 tour.',
        'noeud_destin' => 'Nœud de destin. L\'ennemi reçoit en retour 10% des dégâts qu\'il vous inflige.',
        
        // === AUTRES ACTIONS ===
        'paralyse' => 'Paralysie. L\'ennemi immobilisé pendant 1 tour et ne peut pas attaquer.',
        'gel' => 'Gel. L\'ennemi immobilisé pendant 1 tour et ne peut pas attaquer.',
        'jugement' => 'Jugement des maudits. Inflige 2 fois l\'attaque moins la défense adverse.',
        'danse_glaciale' => 'Danse glaciale. Inflige 1.8 fois l\'attaque et gèle l\'ennemi pendant 2 tours.',
        'curse' => 'Malédiction. Réduit l\'attaque de l\'ennemi de 20 points pendant 2 tours.',
        'malefice' => 'Malédiction. Réduit l\'attaque de l\'ennemi de 20 points pendant 2 tours.',
        'benediction' => 'Bénédiction divine. Augmente l\'attaque et la défense de 15 points pendant 1 tour.',
        'barriere' => 'Barrière magique. Réduit les dégâts reçus de 30% pendant 1 tour.',
        'protection' => 'Protection. Augmente la défense de 15 points pendant 1 tour.',
        'armure' => 'Armure renforcée. Augmente la défense de 20 points pendant 1 tour.',
        'eclair' => 'Éclair. Attaque électrique basée sur l\'attaque avec chance de paralyser l\'ennemi pendant 1 tour.',
    ];
    
    // Si la description personnalisée existe, la retourner
    if (isset($descriptions[$actionKey])) {
        return $descriptions[$actionKey];
    }
    
    // Sinon, utiliser le fallback (description originale)
    return $fallback;
}

/**
 * Échappe et formate la description
 */
function escapeDescription(string $description): string {
    return htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
}
