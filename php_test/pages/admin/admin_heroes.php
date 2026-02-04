<?php
require_once 'admin_helper.php';
requireAdmin();

// HeroManager et Hero sont chargés par l'autoloader
$manager = new HeroManager();
$message = "";
$error = "";

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $hero = new Hero([
                    'hero_id' => $_POST['hero_id'],
                    'name' => $_POST['name'],
                    'type' => $_POST['type'],
                    'pv' => (int)$_POST['pv'],
                    'atk' => (int)$_POST['atk'],
                    'def' => (int)$_POST['def'],
                    'speed' => (int)$_POST['speed'],
                    'description' => $_POST['description'],
                    'image_p1' => $_POST['image_p1'],
                    'image_p2' => $_POST['image_p2'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ]);
                
                if ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                    $hero->setId((int)$_POST['id']);
                    $manager->update($hero);
                    $message = "Héros modifié avec succès";
                } else {
                    $manager->add($hero);
                    $message = "Héros ajouté avec succès";
                }
            }
            
            if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $manager->hardDelete((int)$_POST['id']);
                $message = "Héros supprimé définitivement";
            }
            
            if ($_POST['action'] === 'toggle' && isset($_POST['id'])) {
                $hero = $manager->get((int)$_POST['id']);
                if ($hero) {
                    $hero->setIsActive(!$hero->isActive());
                    $manager->update($hero);
                    $message = $hero->isActive() ? "Héros activé" : "Héros désactivé";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

$heroes = $manager->getAll(true); // Récupérer TOUS les héros (actifs et inactifs)
$heroToEdit = null;

if (isset($_GET['edit'])) {
    $heroToEdit = $manager->get((int)$_GET['edit']);
}

// Classes disponibles (depuis le dossier heroes/)
$availableClasses = [
    'Pyromane', 'Guerrier', 'Guerisseur', 'Aquatique', 
    'Barbare', 'Brute', 'Necromancien', 'Electrique'
];

// Configuration du header
$pageTitle = 'Administration des Héros - Horus Battle Arena';
$extraCss = ['account', 'admin'];
$showUserBadge = true;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>
    <div class="account-container">
        <div class="account-header">
            <h1>Gestion des Héros</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="alert success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="hero-form">
            <h2><?= $heroToEdit ? "Modifier un héros" : "Ajouter un héros" ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="<?= $heroToEdit ? 'edit' : 'add' ?>">
                <?php if ($heroToEdit): ?>
                    <input type="hidden" name="id" value="<?= $heroToEdit->getId() ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div>
                        <label>ID Héros *</label>
                        <input type="text" name="hero_id" required value="<?= $heroToEdit ? htmlspecialchars($heroToEdit->getHeroId()) : '' ?>" placeholder="ex: brutor">
                    </div>
                    <div>
                        <label>Nom *</label>
                        <input type="text" name="name" required value="<?= $heroToEdit ? htmlspecialchars($heroToEdit->getName()) : '' ?>" placeholder="ex: Brutor le Brûlant">
                    </div>
                    <div>
                        <label>Classe *</label>
                        <select name="type" required>
                            <?php foreach ($availableClasses as $class): ?>
                                <option value="<?= $class ?>" <?= $heroToEdit && $heroToEdit->getType() === $class ? 'selected' : '' ?>>
                                    <?= $class ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>PV *</label>
                        <input type="number" name="pv" required value="<?= $heroToEdit ? $heroToEdit->getPv() : 100 ?>" min="1">
                    </div>
                    <div>
                        <label>ATK *</label>
                        <input type="number" name="atk" required value="<?= $heroToEdit ? $heroToEdit->getAtk() : 20 ?>" min="1">
                    </div>
                    <div>
                        <label>DEF *</label>
                        <input type="number" name="def" required value="<?= $heroToEdit ? $heroToEdit->getDef() : 5 ?>" min="0">
                    </div>
                    <div>
                        <label>SPE *</label>
                        <input type="number" name="speed" required value="<?= $heroToEdit ? $heroToEdit->getSpeed() : 10 ?>" min="1">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div>
                        <label>Image P1</label>
                        <input type="text" name="image_p1" value="<?= $heroToEdit ? htmlspecialchars($heroToEdit->getImageP1()) : '' ?>" placeholder="media/heroes/fire_skol.png">
                    </div>
                    <div>
                        <label>Image P2</label>
                        <input type="text" name="image_p2" value="<?= $heroToEdit ? htmlspecialchars($heroToEdit->getImageP2()) : '' ?>" placeholder="media/heroes/fire_skol_2.png">
                    </div>
                </div>
                
                <div>
                    <label>Description</label>
                    <textarea name="description" placeholder="Description du héros..."><?= $heroToEdit ? htmlspecialchars($heroToEdit->getDescription()) : '' ?></textarea>
                </div>
                
                <div class="checkbox-wrapper">
                    <input type="checkbox" name="is_active" id="is_active" <?= !$heroToEdit || $heroToEdit->isActive() ? 'checked' : '' ?>>
                    <label for="is_active" style="margin: 0;">Actif</label>
                </div>
                
                <div class="form-actions">
                    <button type="submit"><?= $heroToEdit ? 'Modifier' : 'Ajouter' ?></button>
                    <?php if ($heroToEdit): ?>
                        <a href="admin_heroes.php"><button type="button" class="btn-secondary">Annuler</button></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <hr style="margin: 30px 0; border: none; border-top: 2px solid #4a0000;">
        
        <h2>Liste des héros</h2>
        <table class="heroes-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Classe</th>
                    <th>Stats (PV/ATK/DEF/SPE)</th>
                    <th>Statut</th>
                    <th>Suppression</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($heroes): ?>
                    <?php foreach ($heroes as $hero): ?>
                        <tr class="<?= $hero->isActive() ? '' : 'inactive-row' ?>">
                            <td><?= htmlspecialchars($hero->getHeroId()) ?></td>
                            <td><strong><?= htmlspecialchars($hero->getName()) ?></strong></td>
                            <td><?= htmlspecialchars($hero->getType()) ?></td>
                            <td>
                                <span title="Points de Vie">PV:<?= $hero->getPv() ?></span> | 
                                <span title="Attaque">ATK:<?= $hero->getAtk() ?></span> | 
                                <span title="Défense">DEF:<?= $hero->getDef() ?></span> | 
                                <span title="Vitesse">SPE:<?= $hero->getSpeed() ?></span>
                            </td>
                            <td class="actions">
                                <a class="btn-edit" href="?edit=<?= $hero->getId() ?>">Modifier</a>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $hero->getId() ?>">
                                    <button type="submit" class="<?= $hero->isActive() ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $hero->isActive() ? 'Désactiver' : 'Activer' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="actions">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $hero->getId() ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Supprimer définitivement ce héros ?')">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 30px;">Aucun héros trouvé.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
