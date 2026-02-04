<?php
/**
 * MENTIONS LÉGALES & CRÉDITS
 * Conforme RGPD et législation française
 */

// Autoloader centralisé
require_once __DIR__ . '/../includes/autoload.php';

// Configuration du header
$pageTitle = 'Mentions Légales - Horus Battle Arena';
$extraCss = [];
$showUserBadge = false;
$showMainTitle = true;
require_once INCLUDES_PATH . '/header.php';
?>

<div class="legal-container">

    <h2>Mentions Légales</h2>
    
    <section>
        <h3>1. Éditeur du site</h3>
        <p>
            <strong>Nom / Raison sociale :</strong> MADORÉ Raphaël<br>
            <strong>Adresse :</strong> 50.431544, 10.673926<br>
            <strong>Téléphone :</strong> +33 6 32 91 22 90<br>
            <strong>Email :</strong>contact.battle-arena@gmail.com<br>
            <strong>Directeur de la publication :</strong> MADORÉ Raphaël
        </p>
    </section>

    <section>
        <h3>2. Hébergeur</h3>
        <p>
            <strong>Nom :</strong> O2Switch<br>
            <strong>Adresse :</strong> Chemin des Pardiaux
63000 Clermont-Ferrand<br>
            <strong>Téléphone :</strong> 02 036 950 543
        </p>
    </section>

    <section>
        <h3>3. Propriété intellectuelle</h3>
        <p>
            Le présent site et l'ensemble de son contenu (textes, images, graphismes, logo, code source, etc.) 
            sont protégés par le droit d'auteur et le droit des marques.
        </p>
        <p>
            Toute reproduction, représentation, modification, publication, adaptation de tout ou partie des éléments 
            du site, quel que soit le moyen ou le procédé utilisé, est interdite, sauf autorisation écrite préalable.
        </p>
        <p>
            <strong>Horus Battle Arena</strong> est un projet personnel/étudiant à but non lucratif.
        </p>
    </section>

    <h2 class="section-title">Politique de Confidentialité (RGPD)</h2>

    <section>
        <h3>1. Responsable du traitement</h3>
        <p>
            Le responsable du traitement des données personnelles est :<br>
            <strong>MADORÉ Raphaël</strong>
        </p>
    </section>

    <section>
        <h3>2. Données collectées</h3>
        <p>Nous collectons les données suivantes :</p>
        <ul>
            <li><strong>Données de compte :</strong> Pseudo, adresse email, mot de passe (crypté)</li>
            <li><strong>Données de jeu :</strong> Statistiques de parties, équipes créées, historique des combats</li>
            <li><strong>Données techniques :</strong> Identifiant de session, adresse IP (logs serveur)</li>
        </ul>
    </section>

    <section>
        <h3>3. Finalités du traitement</h3>
        <p>Les données sont collectées pour :</p>
        <ul>
            <li>Permettre la création et la gestion de votre compte utilisateur</li>
            <li>Assurer le fonctionnement du jeu (matchmaking, statistiques, classements)</li>
            <li>Améliorer l'expérience utilisateur et l'équilibrage du jeu</li>
        </ul>
    </section>

    <section>
        <h3>4. Base légale</h3>
        <p>
            Le traitement des données est fondé sur :<br>
            - <strong>L'exécution du contrat</strong> (création de compte et utilisation du service)<br>
            - <strong>L'intérêt légitime</strong> (amélioration du service, sécurité)
        </p>
    </section>

    <section>
        <h3>5. Destinataires des données</h3>
        <p>
            Vos données personnelles ne sont pas transmises à des tiers, sauf obligation légale.
            Elles sont stockées sur les serveurs de l'hébergeur mentionné ci-dessus.
        </p>
    </section>

    <section>
        <h3>6. Durée de conservation</h3>
        <p>
            - <strong>Données de compte :</strong> Conservées tant que le compte est actif, puis 3 ans après la dernière connexion<br>
            - <strong>Données de jeu :</strong> Conservées pendant la durée de vie du compte<br>
            - <strong>Logs techniques :</strong> 12 mois maximum
        </p>
    </section>

    <section>
        <h3>7. Vos droits (Articles 15 à 22 du RGPD)</h3>
        <p>Conformément au Règlement Général sur la Protection des Données, vous disposez des droits suivants :</p>
        <ul>
            <li><strong>Droit d'accès :</strong> Obtenir une copie de vos données personnelles</li>
            <li><strong>Droit de rectification :</strong> Corriger des données inexactes</li>
            <li><strong>Droit à l'effacement :</strong> Demander la suppression de vos données</li>
            <li><strong>Droit à la portabilité :</strong> Récupérer vos données dans un format structuré</li>
            <li><strong>Droit d'opposition :</strong> Vous opposer au traitement de vos données</li>
            <li><strong>Droit à la limitation :</strong> Limiter le traitement de vos données</li>
        </ul>
        <p class="contact-info">
            Pour exercer ces droits, contactez-nous à : <strong>[EMAIL À COMPLÉTER]</strong>
        </p>
        <p>
            En cas de litige, vous pouvez saisir la <strong>CNIL</strong> (Commission Nationale de l'Informatique et des Libertés) :<br>
            <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>
        </p>
    </section>

    <section>
        <h3>8. Cookies</h3>
        <p>
            Ce site utilise uniquement des <strong>cookies techniques essentiels</strong> au fonctionnement du service 
            (session utilisateur, authentification). Aucun cookie publicitaire ou de traçage n'est utilisé.
        </p>
    </section>

    <h2 class="section-title">Crédits & Sources</h2>

    <section>
        <h3>Icônes et ressources graphiques</h3>
        <ul class="credits-list">
            <li>
                 <a href="https://www.flaticon.com/free-icons/two-players" target="_blank" rel="noopener" title="two players icons">
                    Icons created by AbtoCreative - Flaticon
                </a>
            </li>
            <li>
                <a href="https://thenounproject.com/browse/icons/term/the-magician/" target="_blank" rel="noopener" title="The Magician Icons">
                    The Magician by Saneseed - Noun Project
                </a> (CC BY 3.0)
            </li>
        </ul>
    </section>

    <section>
        <h3>APIs et services tiers</h3>
        <ul class="credits-list">
            <li>
                <a href="https://developer.clashroyale.com/" target="_blank" rel="noopener">
                    Clash Royale API
                </a> - Supercell (Images png)
            </li>
        </ul>
    </section>

    <section>
        <h3>Technologies utilisées</h3>
        <ul>
            <li>PHP <?php echo phpversion(); ?></li>
            <li>MySQL</li>
            <li>HTML5, CSS3, JavaScript</li>
        </ul>
    </section>

    <p class="footer-note">
        Dernière mise à jour : <?php echo date('d/m/Y'); ?>
    </p>

</div>

<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
