<?php
require_once '../../../includes/auth.php';
require_once '../../../config/database.php';

// Vérifier l'authentification et les permissions
redirectIfNotLoggedIn();
if (!isSuperAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès refusé');
}

// Définir le fuseau horaire d'Abidjan
date_default_timezone_set('Africa/Abidjan');

// Vérifier si TCPDF est disponible
if (!class_exists('TCPDF')) {
    // Essayer d'inclure TCPDF manuellement
    $tcpdf_path = '../../../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    } else {
        // Télécharger TCPDF automatiquement si possible, sinon utiliser une solution simple
        generateSimplePDF();
        exit;
    }
}

// Récupérer les paramètres de filtrage
$filter_user = isset($_GET['filter_user']) ? (int)$_GET['filter_user'] : 0;
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

$db = getDB();

// Construction des conditions de filtre
$params = [];
$where_clauses = ["b.type_badgeage = 'departure'"];

if ($filter_user > 0) {
    $where_clauses[] = "b.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_date)) {
    $where_clauses[] = "b.date_badgeage = ?";
    $params[] = $filter_date;
}

$where_sql = implode(" AND ", $where_clauses);

// REQUÊTE PRINCIPALE
$query = "
    SELECT 
        b.id,
        b.user_id,
        u.nom as user_nom,
        u.prenom as user_prenom,
        b.date_badgeage as date_activite,
        b.heure_badgeage as heure_arrivee,
        b.observations as observations_badge,
        b.created_at,
        b.overtime_hours,
        b.type_badgeage,
        (SELECT heure_badgeage FROM badgeages_collab b2 
         WHERE b2.user_id = b.user_id 
         AND b2.date_badgeage = b.date_badgeage 
         AND b2.type_badgeage = 'arrival' 
         LIMIT 1) as heure_arrivee_correcte,
        (SELECT overtime_hours FROM badgeages_collab b3 
         WHERE b3.user_id = b.user_id 
         AND b3.date_badgeage = b.date_badgeage 
         AND b3.type_badgeage = 'departure' 
         LIMIT 1) as overtime_depart
    FROM badgeages_collab b
    JOIN users u ON b.user_id = u.id
    WHERE $where_sql
    ORDER BY b.date_badgeage DESC, b.heure_badgeage DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nom de l'utilisateur pour le titre
$user_name = "Tous les collaborateurs";
if ($filter_user > 0) {
    $stmt_user = $db->prepare("SELECT nom, prenom FROM users WHERE id = ?");
    $stmt_user->execute([$filter_user]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_name = $user['prenom'] . ' ' . $user['nom'];
    }
}

function generatePDFWithTCPDF($activites, $user_name, $filter_date) {
    // Créer un nouveau PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Informations du document
    $pdf->SetCreator('Système de Badgeage');
    $pdf->SetAuthor('Administration');
    $pdf->SetTitle('Rapport des Activités des Collaborateurs');

    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Supprimer le header/footer par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Ajouter une page
    $pdf->AddPage();

    // Couleurs
    $header_color = array(139, 90, 43); // Doré foncé
    $light_color = array(200, 155, 102); // Doré clair

    // En-tête
    $pdf->SetFillColorArray($header_color);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'RAPPORT DES ACTIVITÉS DES COLLABORATEURS', 0, 1, 'C', true);
    $pdf->Ln(5);

    // Informations du rapport
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Généré le: ' . date('d/m/Y à H:i'), 0, 1);
    $pdf->Cell(0, 6, 'Collaborateur: ' . $user_name, 0, 1);
    if (!empty($filter_date)) {
        $pdf->Cell(0, 6, 'Date spécifique: ' . date('d/m/Y', strtotime($filter_date)), 0, 1);
    }
    $pdf->Cell(0, 6, 'Total des activités: ' . count($activites), 0, 1);
    $pdf->Ln(10);

    // Tableau des activités
    if (!empty($activites)) {
        // En-tête du tableau
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 8);
        
        // Largeurs des colonnes
        $w = array(40, 20, 15, 35, 20, 15, 45);
        
        $pdf->Cell($w[0], 8, 'Collaborateur', 1, 0, 'C', true);
        $pdf->Cell($w[1], 8, 'Date', 1, 0, 'C', true);
        $pdf->Cell($w[2], 8, 'Type', 1, 0, 'C', true);
        $pdf->Cell($w[3], 8, 'Horaires', 1, 0, 'C', true);
        $pdf->Cell($w[4], 8, 'H. Supp', 1, 0, 'C', true);
        $pdf->Cell($w[5], 8, 'Statut', 1, 0, 'C', true);
        $pdf->Cell($w[6], 8, 'Observations', 1, 1, 'C', true);
        
        // Données
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);
        
        foreach ($activites as $activite) {
            $statut = $activite['type_badgeage'] === 'departure' ? 'Complet' : 'En cours';
            $heure_arrivee = $activite['heure_arrivee_correcte'] ?: $activite['heure_arrivee'];
            
            $horaires = '';
            if ($heure_arrivee) {
                $horaires .= 'Arr: ' . date('H:i', strtotime($heure_arrivee));
            }
            if ($activite['type_badgeage'] === 'departure') {
                $horaires .= $horaires ? "\nDép: " : 'Dép: ';
                $horaires .= date('H:i', strtotime($activite['heure_arrivee']));
            }
            
            $overtime = $activite['overtime_depart'] ?: $activite['overtime_hours'];
            $heures_supp = (!empty($overtime) && $overtime > 0) ? '+' . $overtime . 'h' : '-';
            
            $observations = !empty($activite['observations_badge']) ? 
                substr($activite['observations_badge'], 0, 40) . (strlen($activite['observations_badge']) > 40 ? '...' : '') : 
                'Aucune';
            
            $type_badgeage = $activite['type_badgeage'] === 'arrival' ? 'Arrivée' : 'Départ';
            
            // Calcul de la hauteur pour les cellules multi-lignes
            $line_height = 4;
            $nb_lines = max(
                ceil($pdf->GetStringWidth($activite['user_prenom'] . ' ' . $activite['user_nom']) / $w[0]),
                ceil($pdf->GetStringWidth($horaires) / $w[3]),
                ceil($pdf->GetStringWidth($observations) / $w[6])
            );
            $cell_height = max(8, $nb_lines * $line_height);
            
            $pdf->MultiCell($w[0], $line_height, $activite['user_prenom'] . ' ' . $activite['user_nom'], 1, 'L', true, 0);
            $pdf->MultiCell($w[1], $line_height, date('d/m/Y', strtotime($activite['date_activite'])), 1, 'C', true, 0);
            $pdf->MultiCell($w[2], $line_height, $type_badgeage, 1, 'C', true, 0);
            $pdf->MultiCell($w[3], $line_height, $horaires, 1, 'C', true, 0);
            $pdf->MultiCell($w[4], $line_height, $heures_supp, 1, 'C', true, 0);
            $pdf->MultiCell($w[5], $line_height, $statut, 1, 'C', true, 0);
            $pdf->MultiCell($w[6], $line_height, $observations, 1, 'L', true, 1);
        }
    } else {
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->Cell(0, 20, 'Aucune activité trouvée pour les critères sélectionnés', 0, 1, 'C');
    }

    // Pied de page
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 10, 'Page ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');

    // Output du PDF
    $filename = 'rapport_activites_' . date('Y-m-d_H-i') . '.pdf';
    $pdf->Output($filename, 'D');
}

function generateSimplePDF() {
    // Solution de secours si TCPDF n'est pas disponible
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Rapport PDF Non Disponible</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .error { color: #d00; padding: 10px; background: #fee; border: 1px solid #d00; }
            .info { margin: 20px 0; padding: 15px; background: #eef; border: 1px solid #00d; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Génération PDF Temporairement Indisponible</h2>
            <p>Le système de génération de PDF est temporairement indisponible.</p>
            <p>Veuillez contacter l\'administrateur pour installer la bibliothèque TCPDF.</p>
        </div>
        <div class="info">
            <p><strong>Solution temporaire :</strong> Vous pouvez utiliser la fonction d\'impression de votre navigateur :</p>
            <ol>
                <li>Retournez à la page des activités</li>
                <li>Appuyez sur Ctrl+P (Windows) ou Cmd+P (Mac)</li>
                <li>Choisissez "Enregistrer au format PDF" comme imprimante</li>
            </ol>
        </div>
    </body>
    </html>';
}

// Générer le PDF
try {
    generatePDFWithTCPDF($activites, $user_name, $filter_date);
} catch (Exception $e) {
    // En cas d'erreur, générer un message d'erreur
    error_log("Erreur génération PDF: " . $e->getMessage());
    generateSimplePDF();
}
?>

<style>
        :root {
            --doré-foncé: #8B5A2B;
            --doré-clair: #C89B66;
            --ivoire: #F5F1EB;
            --blanc: #FFFFFF;
            --gris-anthracite: #333333;
            --vert-sage: #8A9A5B;
            --ombre: rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--ivoire) 0%, var(--blanc) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--blanc);
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--ombre);
            overflow: hidden;
            margin-top: 80px;
        }

        .header {
            background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
            color: var(--blanc);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .header .btn-secondary {
            position: absolute;
            left: 2rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
        }

        .filters-section {
            background: var(--ivoire);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--gris-anthracite);
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid var(--doré-clair);
            border-radius: 8px;
            background: var(--blanc);
            color: var(--gris-anthracite);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--doré-foncé);
            box-shadow: 0 0 0 3px rgba(139, 90, 43, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
            color: var(--blanc);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 90, 43, 0.3);
        }

        .btn-secondary {
            background: var(--blanc);
            color: var(--doré-foncé);
            border: 1px solid var(--doré-clair);
        }

        .btn-secondary:hover {
            background: var(--ivoire);
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stats-info {
            color: var(--gris-anthracite);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--ivoire);
            margin-bottom: 2rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .table th {
            background: var(--ivoire);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gris-anthracite);
            border-bottom: 2px solid var(--doré-clair);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--ivoire);
            color: var(--gris-anthracite);
        }

        .table tr:hover {
            background: var(--ivoire);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-complet {
            background: #d1f2eb;
            color: #0c5460;
            border: 1px solid #a3e4d7;
        }

        .badge-en-cours {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .time-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .time-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .time-item i {
            color: var(--doré-clair);
        }

        .duration-badge {
            background: var(--ivoire);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--doré-foncé);
        }

        .overtime-badge {
            background: #e8f4fd;
            color: #0d6efd;
            border: 1px solid #b6d4fe;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .observations-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--gris-anthracite);
        }

        .no-data i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
            display: block;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            background: var(--blanc);
            border: 1px solid var(--doré-clair);
            color: var(--doré-foncé);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .pagination-btn:hover {
            background: var(--doré-clair);
            color: var(--blanc);
        }

        .pagination-btn.active {
            background: var(--doré-foncé);
            color: var(--blanc);
            border-color: var(--doré-foncé);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal-container {
            background: var(--blanc);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow: hidden;
            animation: slideUp 0.4s ease;
            border: 2px solid var(--doré-clair);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
            color: var(--blanc);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-header h3 {
            font-size: 1.4rem;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: var(--blanc);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
            max-height: calc(85vh - 200px);
            overflow-y: auto;
        }

        .modal-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--ivoire);
            border-radius: 12px;
            border-left: 4px solid var(--doré-clair);
        }

        .modal-info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .modal-info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--doré-foncé);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-info-value {
            font-size: 1rem;
            color: var(--gris-anthracite);
            font-weight: 500;
        }

        .modal-observations-container {
            background: var(--ivoire);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--doré-clair);
            margin-bottom: 1rem;
        }

        .modal-observations-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--doré-foncé);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-observations-content {
            color: var(--gris-anthracite);
            line-height: 1.7;
            font-size: 1rem;
            padding: 1rem;
            background: var(--blanc);
            border-radius: 8px;
            border: 1px solid rgba(139, 90, 43, 0.1);
            white-space: pre-wrap;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                margin-top: 70px;
            }

            .header .btn-secondary {
                position: static;
                transform: none;
                margin-bottom: 1rem;
            }

            .content {
                padding: 1rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .modal-container {
                margin: 10px;
                max-height: 90vh;
            }

            .modal-header {
                padding: 1rem 1.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .modal-info {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
        }
    </style>