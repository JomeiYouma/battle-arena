<?php
/**
 * LEGALCONTROLLER - Pages légales
 */

class LegalController extends Controller {
    
    /**
     * Page mentions légales
     */
    public function index(): void {
        $data = [
            'pageTitle' => 'Mentions Légales - Horus Battle Arena',
            'extraCss' => [],
            'showUserBadge' => false,
            'showMainTitle' => true,
        ];
        
        $this->render('legal/index', $data);
    }
}
