<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();
if (!isAgent()) {
    header('Location: ../planificateur/');
    exit();
}

$db = getDB();
$message = '';

// Traitement des actions sur les chantiers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $rdv_id = intval($_POST['rdv_id']);
        
        try {
            switch ($_POST['action']) {
                case 'entretien':
                    $date_entretien = date('Y-m-d H:i:s');
                    // Vérifier si le chantier existe déjà
                    $stmt = $db->prepare("SELECT id FROM chantiers WHERE rdv_id = ?");
                    $stmt->execute([$rdv_id]);
                    
                    if ($stmt->fetch()) {
                        $stmt = $db->prepare("UPDATE chantiers SET date_entretien = ?, statut_travaux = 'en_attente' WHERE rdv_id = ?");
                        $stmt->execute([$date_entretien, $rdv_id]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO chantiers (rdv_id, date_entretien, statut_travaux) VALUES (?, ?, 'en_attente')");
                        $stmt->execute([$rdv_id, $date_entretien]);
                    }
                    
                    // Log de l'activité
                    $stmt = $db->prepare("SELECT CONCAT(c.prenom, ' ', c.nom) as client FROM rendezvous r JOIN clients c ON r.client_id = c.id WHERE r.id = ?");
                    $stmt->execute([$rdv_id]);
                    $client = $stmt->fetchColumn();
                    
                    logActivity($_SESSION['user_id'], 'entretien', "Entretien réalisé avec $client");
                    
                    $message = '<div class="alert success">Entretien enregistré avec succès.</div>';
                    break;
                    
                case 'devis':
                    $type_devis = filter_input(INPUT_POST, 'type_devis', FILTER_SANITIZE_STRING);
                    $date_devis = date('Y-m-d H:i:s');
                    
                    $stmt = $db->prepare("UPDATE chantiers SET date_devis_envoye = ?, type_devis = ?, statut_devis = 'envoye' WHERE rdv_id = ?");
                    $stmt->execute([$date_devis, $type_devis, $rdv_id]);
                    
                    $message = '<div class="alert success">Devis envoyé avec succès.</div>';
                    break;
                    
                case 'accepter_devis':
                    $stmt = $db->prepare("UPDATE chantiers SET statut_devis = 'accepte' WHERE rdv_id = ?");
                    $stmt->execute([$rdv_id]);
                    
                    $message = '<div class="alert success">Devis accepté avec succès.</div>';
                    break;
                    
                case 'commencer_travaux':
                    $duree_estimee = intval($_POST['duree_estimee']);
                    $date_debut = date('Y-m-d H:i:s');
                    $date_fin_estimee = date('Y-m-d H:i:s', strtotime("+$duree_estimee days"));
                    
                    $stmt = $db->prepare("UPDATE chantiers SET date_debut_travaux = ?, duree_estimee = ?, date_fin_estimee = ?, statut_travaux = 'en_cours' WHERE rdv_id = ?");
                    $stmt->execute([$date_debut, $duree_estimee, $date_fin_estimee, $rdv_id]);
                    
                    $message = '<div class="alert success">Travaux démarrés avec succès.</div>';
                    break;
                    
                case 'terminer_travaux':
                    $date_fin = date('Y-m-d H:i:s');
                    
                    // Calculer si la livraison est à temps, en avance ou en retard
                    $stmt = $db->prepare("SELECT date_fin_estimee FROM chantiers WHERE rdv_id = ?");
                    $stmt->execute([$rdv_id]);
                    $date_fin_estimee = $stmt->fetchColumn();
                    
                    $livraison = 'a_temps';
                    $now = time();
                    $estimee = strtotime($date_fin_estimee);
                    
                    if ($now < $estimee - (24 * 3600)) { // 24h avant
                        $livraison = 'en_avance';
                    } elseif ($now > $estimee) {
                        $livraison = 'en_retard';
                    }
                    
                    $stmt = $db->prepare("UPDATE chantiers SET date_fin_reelle = ?, statut_travaux = 'termine', livraison = ? WHERE rdv_id = ?");
                    $stmt->execute([$date_fin, $livraison, $rdv_id]);
                    
                    $message = '<div class="alert success">Travaux terminés avec succès.</div>';
                    break;
                    
                case 'ajouter_notes':
                    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
                    
                    $stmt = $db->prepare("UPDATE chantiers SET notes = ? WHERE rdv_id = ?");
                    $stmt->execute([$notes, $rdv_id]);
                    
                    $message = '<div class="alert success">Notes mises à jour avec succès.</div>';
                    break;
            }
        } catch (Exception $e) {
            $message = '<div class="alert error">Erreur: ' . $e->getMessage() . '</div>';
        }
    }
}

// Récupérer les rendez-vous effectués de l'agent
$stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone, 
                     ch.id as chantier_id, ch.date_entretien, ch.date_devis_envoye, ch.type_devis, 
                     ch.statut_devis, ch.date_debut_travaux, ch.duree_estimee, ch.date_fin_estimee,
                     ch.date_fin_reelle, ch.statut_travaux, ch.livraison, ch.notes as notes_chantier
                     FROM rendezvous r
                     JOIN clients c ON r.client_id = c.id
                     LEFT JOIN chantiers ch ON r.id = ch.rdv_id
                     WHERE r.agent_id = ? AND r.statut_rdv = 'Effectué'
                     ORDER BY r.date_rdv DESC");
$stmt->execute([$_SESSION['user_id']]);
$rendezvous_effectues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si un ID spécifique est demandé, récupérer ce rendez-vous effectué
$current_rdv = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $rdv_id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.genre, c.commune, c.telephone,
                         ch.id as chantier_id, ch.date_entretien, ch.date_devis_envoye, ch.type_devis, 
                         ch.statut_devis, ch.date_debut_travaux, ch.duree_estimee, ch.date_fin_estimee,
                         ch.date_fin_reelle, ch.statut_travaux, ch.livraison, ch.notes as notes_chantier
                         FROM rendezvous r
                         JOIN clients c ON r.client_id = c.id
                         LEFT JOIN chantiers ch ON r.id = ch.rdv_id
                         WHERE r.id = ? AND r.agent_id = ? AND r.statut_rdv = 'Effectué'");
    $stmt->execute([$rdv_id, $_SESSION['user_id']]);
    $current_rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_rdv) {
        $message = '<div class="alert error">Rendez-vous non trouvé ou non effectué.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous Effectués - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .timeline {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #ddd;
            z-index: 1;
        }
        
        .etape {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .cercle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #ddd;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .cercle.active {
            background-color: var(--doré-foncé);
        }
        
        .cercle.completed {
            background-color: var(--vert-sage);
        }
        
        .etape-label {
            font-size: 0.9rem;
            color: var(--gris-anthracite);
        }
        
        .etape-date {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        
        .chantier-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-contents {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .status-en-cours { background-color: #fff3cd; color: #856404; }
        .status-termine { background-color: #d4edda; color: #155724; }
        .status-en-attente { background-color: #d1ecf1; color: #0c5460; }
        
        .livraison-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .livraison-a-temps { background-color: #d4edda; color: #155724; }
        .livraison-en-avance { background-color: #c3e6cb; color: #0c5460; }
        .livraison-en-retard { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mes Rendez-vous Effectués</h1>
            <a href="rendezvous.php" class="btn btn-secondary" style="text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Retour aux rendez-vous en attente
            </a>
        </div>
        
        <?php echo $message; ?>
        
        <?php if ($current_rdv): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Détails du rendez-vous effectué</h2>
                <div>
                    <?php if ($current_rdv['chantier_id']): ?>
                        <?php if ($current_rdv['statut_travaux'] == 'en_attente'): ?>
                            <span class="status-badge status-en-attente">En attente</span>
                        <?php elseif ($current_rdv['statut_travaux'] == 'en_cours'): ?>
                            <span class="status-badge status-en-cours">Travaux en cours</span>
                        <?php elseif ($current_rdv['statut_travaux'] == 'termine'): ?>
                            <span class="status-badge status-termine">Terminé</span>
                        <?php endif; ?>
                        
                        <?php if ($current_rdv['livraison']): ?>
                            <?php if ($current_rdv['livraison'] == 'a_temps'): ?>
                                <span class="livraison-badge livraison-a-temps">Livraison à temps</span>
                            <?php elseif ($current_rdv['livraison'] == 'en_avance'): ?>
                                <span class="livraison-badge livraison-en-avance">Livraison en avance</span>
                            <?php elseif ($current_rdv['livraison'] == 'en_retard'): ?>
                                <span class="livraison-badge livraison-en-retard">Livraison en retard</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="rendezvousEffectuer.php" class="btn btn-secondary" style="text-decoration:none;">Retour à la liste</a>
                </div>
            </div>
            
            <!-- Timeline du chantier -->
            <?php if ($current_rdv['chantier_id']): ?>
            <div class="timeline">
                <div class="etape">
                    <div class="cercle <?= $current_rdv['date_entretien'] ? 'completed' : ($current_rdv['statut_travaux'] ? 'active' : '') ?>">
                        1
                    </div>
                    <div class="etape-label">Entretien</div>
                    <?php if ($current_rdv['date_entretien']): ?>
                        <div class="etape-date"><?= date('d/m/Y', strtotime($current_rdv['date_entretien'])) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="etape">
                    <div class="cercle <?= $current_rdv['date_devis_envoye'] ? 'completed' : ($current_rdv['date_entretien'] && !$current_rdv['date_debut_travaux'] ? 'active' : '') ?>">
                        2
                    </div>
                    <div class="etape-label">Devis</div>
                    <?php if ($current_rdv['date_devis_envoye']): ?>
                        <div class="etape-date"><?= date('d/m/Y', strtotime($current_rdv['date_devis_envoye'])) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="etape">
                    <div class="cercle <?= $current_rdv['date_debut_travaux'] ? 'completed' : ($current_rdv['statut_devis'] == 'accepte' ? 'active' : '') ?>">
                        3
                    </div>
                    <div class="etape-label">Travaux</div>
                    <?php if ($current_rdv['date_debut_travaux']): ?>
                        <div class="etape-date"><?= date('d/m/Y', strtotime($current_rdv['date_debut_travaux'])) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="etape">
                    <div class="cercle <?= $current_rdv['date_fin_reelle'] ? 'completed' : ($current_rdv['date_debut_travaux'] && !$current_rdv['date_fin_reelle'] ? 'active' : '') ?>">
                        4
                    </div>
                    <div class="etape-label">Livraison</div>
                    <?php if ($current_rdv['date_fin_reelle']): ?>
                        <div class="etape-date"><?= date('d/m/Y', strtotime($current_rdv['date_fin_reelle'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions du chantier -->
            <div class="chantier-actions">
                <?php if (!$current_rdv['date_entretien']): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="rdv_id" value="<?= $current_rdv['id'] ?>">
                        <input type="hidden" name="action" value="entretien">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-handshake"></i> Enregistrer l'entretien
                        </button>
                    </form>
                <?php elseif (!$current_rdv['date_devis_envoye']): ?>
                    <button onclick="openModal('devisModal')" class="btn btn-primary">
                        <i class="fas fa-file-invoice-dollar"></i> Envoyer un devis
                    </button>
                <?php elseif ($current_rdv['statut_devis'] == 'envoye'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="rdv_id" value="<?= $current_rdv['id'] ?>">
                        <input type="hidden" name="action" value="accepter_devis">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Devis accepté
                        </button>
                    </form>
                <?php elseif ($current_rdv['statut_devis'] == 'accepte' && !$current_rdv['date_debut_travaux']): ?>
                    <button onclick="openModal('travauxModal')" class="btn btn-primary">
                        <i class="fas fa-tools"></i> Démarrer les travaux
                    </button>
                <?php elseif ($current_rdv['statut_travaux'] == 'en_cours'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="rdv_id" value="<?= $current_rdv['id'] ?>">
                        <input type="hidden" name="action" value="terminer_travaux">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-flag-checkered"></i> Terminer les travaux
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($current_rdv['chantier_id']): ?>
                    <button onclick="openModal('notesModal')" class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Ajouter des notes
                    </button>
                <?php endif; ?>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label>Client</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= htmlspecialchars($current_rdv['client_prenom'] . ' ' . $current_rdv['client_nom']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Genre</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= $current_rdv['genre'] == 'M' ? 'Masculin' : ($current_rdv['genre'] == 'F' ? 'Féminin' : 'Autre') ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Téléphone</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= htmlspecialchars($current_rdv['telephone']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Commune</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= htmlspecialchars($current_rdv['commune']) ?>
                    </div>
                </div>
                
                <?php if ($current_rdv['chantier_id']): ?>
                    <div class="form-group">
                        <label>Type de devis</label>
                        <div class="form-control" style="background-color: #f9f9f9;">
                            <?= $current_rdv['type_devis'] == 'avec_3d' ? 'Avec 3D' : ($current_rdv['type_devis'] == 'sans_3d' ? 'Sans 3D' : 'Non envoyé') ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Statut du devis</label>
                        <div class="form-control" style="background-color: #f9f9f9;">
                            <?= $current_rdv['statut_devis'] == 'envoye' ? 'Envoyé' : ($current_rdv['statut_devis'] == 'accepte' ? 'Accepté' : ($current_rdv['statut_devis'] == 'refuse' ? 'Refusé' : 'Non envoyé')) ?>
                        </div>
                    </div>
                    
                    <?php if ($current_rdv['date_debut_travaux']): ?>
                        <div class="form-group">
                            <label>Durée estimée</label>
                            <div class="form-control" style="background-color: #f9f9f9;">
                                <?= $current_rdv['duree_estimee'] ?> jours
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Date de fin estimée</label>
                            <div class="form-control" style="background-color: #f9f9f9;">
                                <?= $current_rdv['date_fin_estimee'] ? date('d/m/Y', strtotime($current_rdv['date_fin_estimee'])) : 'Non définie' ?>
                            </div>
                        </div>
                        
                        <?php if ($current_rdv['date_fin_reelle']): ?>
                            <div class="form-group">
                                <label>Date de fin réelle</label>
                                <div class="form-control" style="background-color: #f9f9f9;">
                                    <?= date('d/m/Y', strtotime($current_rdv['date_fin_reelle'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Date du rendez-vous</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= date('d/m/Y H:i', strtotime($current_rdv['date_rdv'])) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Date de contact</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= date('d/m/Y H:i', strtotime($current_rdv['date_contact'])) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Statut paiement</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= $current_rdv['statut_paiement'] ?>
                    </div>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label>Motif</label>
                    <div class="form-control" style="background-color: #f9f9f9; min-height: 80px;">
                        <?= htmlspecialchars($current_rdv['motif']) ?>
                    </div>
                </div>
                
                <?php if ($current_rdv['notes_agent']): ?>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Notes de l'agent</label>
                    <div class="form-control" style="background-color: #f9f9f9; min-height: 80px;">
                        <?= htmlspecialchars($current_rdv['notes_agent']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($current_rdv['notes_chantier']): ?>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Notes du chantier</label>
                    <div class="form-control" style="background-color: #f9f9f9; min-height: 80px;">
                        <?= htmlspecialchars($current_rdv['notes_chantier']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste de mes rendez-vous effectués</h2>
                <span class="badge"><?= count($rendezvous_effectues) ?> rendez-vous</span>
            </div>
            
            <?php if (empty($rendezvous_effectues)): ?>
                <div class="alert info">Vous n'avez encore effectué aucun rendez-vous.</div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Date RDV</th>
                        <th>Commune</th>
                        <th>État du chantier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendezvous_effectues as $rdv): ?>
                    <tr>
                        <td><?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></td>
                        <td><?= htmlspecialchars($rdv['commune']) ?></td>
                        <td>
                            <?php if ($rdv['chantier_id']): ?>
                                <?php if ($rdv['statut_travaux'] == 'en_attente'): ?>
                                    <span class="badge badge-warning">Entretien fait</span>
                                <?php elseif ($rdv['statut_travaux'] == 'en_cours'): ?>
                                    <span class="badge badge-info">Travaux en cours</span>
                                <?php elseif ($rdv['statut_travaux'] == 'termine'): ?>
                                    <span class="badge badge-success">Terminé</span>
                                <?php endif; ?>
                                
                                <?php if ($rdv['livraison'] == 'en_retard'): ?>
                                    <span class="badge badge-danger">Retard</span>
                                <?php elseif ($rdv['livraison'] == 'en_avance'): ?>
                                    <span class="badge badge-success">En avance</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge">À suivre</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="rendezvousEffectuer.php?id=<?= $rdv['id'] ?>" class="btn btn-primary btn-sm" style="text-decoration:none;">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal pour le devis -->
    <div id="devisModal" class="modal">
        <div class="modal-contents">
            <h3>Envoyer un devis</h3>
            <form method="POST" id="devisForm">
                <input type="hidden" name="rdv_id" value="<?= $current_rdv ? $current_rdv['id'] : '' ?>">
                <input type="hidden" name="action" value="devis">
                
                <div class="form-group">
                    <label>Type de devis *</label>
                    <select name="type_devis" class="form-control" required>
                        <option value="">Sélectionner</option>
                        <option value="avec_3d">Avec 3D</option>
                        <option value="sans_3d">Sans 3D</option>
                    </select>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Envoyer le devis</button>
                    <button type="button" onclick="closeModal('devisModal')" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal pour démarrer les travaux -->
    <div id="travauxModal" class="modal">
        <div class="modal-contents">
            <h3>Démarrer les travaux</h3>
            <form method="POST" id="travauxForm">
                <input type="hidden" name="rdv_id" value="<?= $current_rdv ? $current_rdv['id'] : '' ?>">
                <input type="hidden" name="action" value="commencer_travaux">
                
                <div class="form-group">
                    <label>Durée estimée (en jours) *</label>
                    <input type="number" name="duree_estimee" class="form-control" min="1" max="365" required>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Démarrer les travaux</button>
                    <button type="button" onclick="closeModal('travauxModal')" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal pour les notes -->
    <div id="notesModal" class="modal">
        <div class="modal-contents">
            <h3>Ajouter des notes</h3>
            <form method="POST" id="notesForm">
                <input type="hidden" name="rdv_id" value="<?= $current_rdv ? $current_rdv['id'] : '' ?>">
                <input type="hidden" name="action" value="ajouter_notes">
                
                <div class="form-group">
                    <label>Notes du chantier</label>
                    <textarea name="notes" class="form-control" rows="6"><?= $current_rdv ? htmlspecialchars($current_rdv['notes_chantier']) : '' ?></textarea>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Enregistrer les notes</button>
                    <button type="button" onclick="closeModal('notesModal')" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <script src="../../js/script.js"></script>
</body>
</html>