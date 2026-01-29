-- =====================================================
-- Insertion des héros dans la table heroes
-- Exécuter après avoir créé la table via schema.sql
-- =====================================================

INSERT INTO heroes (hero_id, name, type, pv, atk, def, speed, description, image_p1, image_p2, is_active) VALUES
('brutor', 'Brutor le Brûlant', 'Pyromane', 100, 22, 5, 12, 'Maître des flammes, sacrifie sa vie pour des dégâts dévastateurs.', 'media/heroes/fire_skol.png', 'media/heroes/fire_skol_2.png', 1),
('ulroc', 'Ulroc le Mastoc', 'Guerrier', 120, 20, 10, 8, 'Tank puissant, alterne rage offensive et défense solide.', 'media/heroes/combat_skol.png', 'media/heroes/combat_skol_2.png', 1),
('siras', 'Siras le Sage', 'Guerisseur', 150, 14, 3, 13, 'Énergie divine pour soigner et infliger des dégâts qui ignorent les défenses.', 'media/heroes/priest_skol.png', 'media/heroes/priest_skol_2.png', 1),
('poissard', 'Poissard le Poisson', 'Aquatique', 85, 20, 6, 15, 'Insaisissable et fluide, esquive et contre-attaque avec des tsunamis.', 'media/heroes/fish_skol.png', 'media/heroes/fish_skol_2.png', 1),
('pedro', 'Pedro', 'Barbare', 110, 20, 0, 10, 'Berserk sanguinaire, plus dangereux quand blessé. Risque et récompense.', 'media/heroes/kouto_skol.png', 'media/heroes/kouto_skol_2.png', 1),
('krimus', 'Krimus l''Astucieux', 'Guerisseur', 110, 12, 9, 9, 'Guérisseur rusé, combine soins puissants et attaques sacrées.', 'media/heroes/sat_skol.png', 'media/heroes/sat_skol_2.png', 1),
('pikratchu', 'Pikratchu', 'Electrique', 90, 18, 3, 11, 'Vitesse extrême et dégâts électriques. Paralysie et charge dévastatrice.', 'media/heroes/pikachu_skol.png', 'media/heroes/pikachu_skol_2.png', 1),
('norgol', 'Norgol Roi des Ombres', 'Necromancien', 145, 17, 4, 5, 'Manipulateur d''âmes, copie les attaques et maudit ses ennemis.', 'media/heroes/necro_skol.png', 'media/heroes/necro_skol_2.png', 1),
('gorath', 'Gorath le Goliathe', 'Brute', 150, 16, 9, 3, 'Géant lent mais dévastateur. Bombe finale si mal en point.', 'media/heroes/giant_skol.png', 'media/heroes/giant_skol_2.png', 1);
