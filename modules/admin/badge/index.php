<?php
require_once '../../../includes/auth.php';
require_once '../../../config/database.php';

// Ajouter l'autoloader de Composer
require_once '../../../vendor/autoload.php';

use TCPDF as TCPDF;

redirectIfNotLoggedIn();
if (!isSuperAdmin()) {
    header('Location: ../planificateur/');
    exit();
}

// Définir le fuseau horaire d'Abidjan
date_default_timezone_set('Africa/Abidjan');

// Récupérer les paramètres de filtrage
$filter_user = isset($_GET['filter_user']) ? (int)$_GET['filter_user'] : 0;
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$report_period = isset($_GET['report_period']) ? $_GET['report_period'] : 'month';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t');

// Récupérer les paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = getDB();

// Récupérer la liste des utilisateurs pour le filtre
$stmt_users = $db->query("SELECT id, nom, prenom FROM users ORDER BY nom, prenom");
$users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Construction des conditions de filtre
$params = [];
$where_clauses = ["b.type_badgeage = 'arrival'"];

if ($filter_user > 0) {
    $where_clauses[] = "b.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_date)) {
    $where_clauses[] = "b.date_badgeage = ?";
    $params[] = $filter_date;
}

$where_sql = implode(" AND ", $where_clauses);

// Compter le total d'activités basé sur les arrivées
$count_query = "SELECT COUNT(*) as total FROM badgeages_collab b WHERE $where_sql";
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$totalActivities = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = $totalActivities > 0 ? ceil($totalActivities / $limit) : 1;

// REQUÊTE PRINCIPALE CORRIGÉE - On part des arrivées et on cherche les départs et pauses correspondants
$query = "
    SELECT 
        b.id,
        b.user_id,
        u.nom as user_nom,
        u.prenom as user_prenom,
        b.date_badgeage as date_activite,
        b.heure_badgeage as heure_arrivee,
        b.observations as observations_arrivee,
        b.created_at,
        b.overtime_hours as overtime_arrivee,
        b.ip_address,
        bd.id as depart_id,
        bd.heure_badgeage as heure_depart,
        bd.observations as observations_depart,
        bd.overtime_hours as overtime_depart,
        bd.created_at as depart_created_at,
        -- Récupérer les pauses de la journée
        (SELECT COUNT(*) 
         FROM badgeages_collab p 
         WHERE p.user_id = b.user_id 
         AND p.date_badgeage = b.date_badgeage 
         AND p.type_badgeage = 'pause_start') as nb_pauses,
        (SELECT GROUP_CONCAT(CONCAT(
            'Début: ', TIME(p1.recorded_datetime), 
            ' - Fin: ', IFNULL(TIME(p2.recorded_datetime), 'En cours'),
            IF(p2.recorded_datetime IS NOT NULL, 
               CONCAT(' (', TIMEDIFF(p2.recorded_datetime, p1.recorded_datetime), ')'), 
               '')
        ) SEPARATOR ' | ')
         FROM badgeages_collab p1
         LEFT JOIN badgeages_collab p2 ON p1.pause_pair_id = p2.id
         WHERE p1.user_id = b.user_id 
         AND DATE(p1.recorded_datetime) = b.date_badgeage 
         AND p1.type_badgeage = 'pause_start') as pauses_details
    FROM badgeages_collab b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN badgeages_collab bd ON bd.user_id = b.user_id 
        AND bd.date_badgeage = b.date_badgeage 
        AND bd.type_badgeage = 'departure'
    WHERE $where_sql
    ORDER BY b.date_badgeage DESC, b.heure_badgeage DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($query);

// Lier les paramètres correctement avec leurs types
$param_index = 1;
foreach ($params as $param) {
    $stmt->bindValue($param_index, $param, PDO::PARAM_STR);
    $param_index++;
}

// Lier LIMIT et OFFSET comme entiers
$stmt->bindValue($param_index, $limit, PDO::PARAM_INT);
$param_index++;
$stmt->bindValue($param_index, $offset, PDO::PARAM_INT);

$stmt->execute();
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour générer le rapport PDF
function generateOvertimeReportPDF($db, $report_type, $report_period, $filter_user = 0, $date_debut = '', $date_fin = '') {
    // Déterminer la période
    $start_date = '';
    $end_date = '';
    $title_period = '';
    
    if (!empty($date_debut) && !empty($date_fin)) {
        // Utiliser les dates personnalisées
        $start_date = $date_debut;
        $end_date = $date_fin;
        $title_period = 'Période personnalisée du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
    } else {
        // Utiliser les périodes prédéfinies
        switch($report_period) {
            case 'today':
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');
                $title_period = 'Aujourd\'hui - ' . date('d/m/Y');
                break;
            case 'yesterday':
                $start_date = date('Y-m-d', strtotime('-1 day'));
                $end_date = date('Y-m-d', strtotime('-1 day'));
                $title_period = 'Hier - ' . date('d/m/Y', strtotime('-1 day'));
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d', strtotime('sunday this week'));
                $title_period = 'Semaine du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
                break;
            case 'last_week':
                $start_date = date('Y-m-d', strtotime('monday last week'));
                $end_date = date('Y-m-d', strtotime('sunday last week'));
                $title_period = 'Semaine dernière du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
                break;
            case 'month':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                $title_period = 'Mois de ' . date('F Y');
                break;
            case 'last_month':
                $start_date = date('Y-m-01', strtotime('last month'));
                $end_date = date('Y-m-t', strtotime('last month'));
                $title_period = 'Mois dernier - ' . date('F Y', strtotime('last month'));
                break;
            case 'year':
                $start_date = date('Y-01-01');
                $end_date = date('Y-12-31');
                $title_period = 'Année ' . date('Y');
                break;
            default:
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                $title_period = 'Mois de ' . date('F Y');
        }
    }
    
    // Construire la requête pour les heures supplémentaires avec les pauses
    $query_params = [];
    $where_conditions = ["b.type_badgeage = 'arrival'", "b.date_badgeage BETWEEN ? AND ?"];
    $query_params[] = $start_date;
    $query_params[] = $end_date;
    
    if ($filter_user > 0) {
        $where_conditions[] = "b.user_id = ?";
        $query_params[] = $filter_user;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    $query = "
        SELECT 
            u.id as user_id,
            u.nom,
            u.prenom,
            b.date_badgeage,
            b.heure_badgeage as heure_arrivee,
            bd.heure_badgeage as heure_depart,
            b.overtime_hours as overtime_arrivee,
            bd.overtime_hours as overtime_depart,
            COALESCE(bd.overtime_hours, b.overtime_hours, 0) as overtime_total,
            -- Calcul du temps total des pauses
            (SELECT IFNULL(SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(p2.recorded_datetime, p1.recorded_datetime)))), '00:00:00')
             FROM badgeages_collab p1
             JOIN badgeages_collab p2 ON p1.pause_pair_id = p2.id
             WHERE p1.user_id = b.user_id 
             AND DATE(p1.recorded_datetime) = b.date_badgeage 
             AND p1.type_badgeage = 'pause_start') as total_pauses,
            -- Nombre de pauses
            (SELECT COUNT(*)
             FROM badgeages_collab p 
             WHERE p.user_id = b.user_id 
             AND DATE(p.recorded_datetime) = b.date_badgeage 
             AND p.type_badgeage = 'pause_start') as nb_pauses
        FROM badgeages_collab b
        JOIN users u ON b.user_id = u.id
        LEFT JOIN badgeages_collab bd ON bd.user_id = b.user_id 
            AND bd.date_badgeage = b.date_badgeage 
            AND bd.type_badgeage = 'departure'
        WHERE $where_sql
        ORDER BY u.nom, u.prenom, b.date_badgeage
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($query_params);
    $overtime_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grouper les données par utilisateur
    $user_overtime = [];
    foreach ($overtime_data as $row) {
        $user_id = $row['user_id'];
        if (!isset($user_overtime[$user_id])) {
            $user_overtime[$user_id] = [
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'total_overtime' => 0,
                'total_pauses' => '00:00:00',
                'total_nb_pauses' => 0,
                'days' => []
            ];
        }
        
        $overtime = (float)$row['overtime_total'];
        $user_overtime[$user_id]['total_overtime'] += $overtime;
        
        // Ajouter le temps de pause
        if ($row['total_pauses'] && $row['total_pauses'] != '00:00:00') {
            $user_overtime[$user_id]['total_nb_pauses'] += (int)$row['nb_pauses'];
        }
        
        $user_overtime[$user_id]['days'][] = [
            'date' => $row['date_badgeage'],
            'overtime' => $overtime,
            'arrivee' => $row['heure_arrivee'],
            'depart' => $row['heure_depart'],
            'pauses' => $row['total_pauses'],
            'nb_pauses' => $row['nb_pauses']
        ];
    }
    
    // Créer le PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Système de Badgeage');
    $pdf->SetTitle('Rapport des Activités');
    $pdf->SetSubject('Rapport ' . $title_period);
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Contenu du PDF
    $html = '
    <style>
        h1 { color: #8B5A2B; text-align: center; font-size: 20px; }
        h2 { color: #333333; font-size: 16px; }
        h3 { color: #8B5A2B; font-size: 14px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background-color: #F5F1EB; color: #8B5A2B; padding: 8px; border: 1px solid #C89B66; font-weight: bold; }
        td { padding: 8px; border: 1px solid #C89B66; }
        .total { background-color: #8A9A5B; color: white; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .period { color: #666; font-size: 14px; }
        .user-section { margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border-radius: 5px; }
        .day-details { font-size: 12px; color: #666; }
        .summary { background-color: #e8f4fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
    
    <div class="header">
        <h1>RAPPORT DES ACTIVITÉS ET HEURES SUPPLÉMENTAIRES</h1>
        <div class="period">' . $title_period . '</div>
        <div class="period">Généré le ' . date('d/m/Y à H:i') . '</div>
    </div>
    ';
    
    if (empty($user_overtime)) {
        $html .= '
        <div class="summary">
            <h3>Aucune donnée trouvée</h3>
            <p>Aucune activité n\'a été enregistrée pour la période sélectionnée.</p>
        </div>';
    } else {
        // Résumé général
        $total_general = array_sum(array_column($user_overtime, 'total_overtime'));
        $total_pauses = array_sum(array_column($user_overtime, 'total_nb_pauses'));
        $total_collaborateurs = count($user_overtime);
        
        $html .= '
        <div class="summary">
            <h3>SYNTHÈSE GÉNÉRALE</h3>
            <table>
                <tr>
                    <td><strong>Nombre de collaborateurs:</strong></td>
                    <td>' . $total_collaborateurs . '</td>
                </tr>
                <tr>
                    <td><strong>Total des pauses:</strong></td>
                    <td>' . $total_pauses . ' pauses</td>
                </tr>
                <tr>
                    <td><strong>Total des heures supplémentaires:</strong></td>
                    <td>' . number_format($total_general, 1) . ' heures</td>
                </tr>
            </table>
        </div>';
        
        foreach ($user_overtime as $user_id => $user_data) {
            $html .= '
            <div class="user-section">
                <h2>' . htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']) . '</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Arrivée</th>
                            <th>Départ</th>
                            <th>Pauses</th>
                            <th>Nb Pauses</th>
                            <th>Heures Supp</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($user_data['days'] as $day) {
                $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($day['date'])) . '</td>
                    <td>' . ($day['arrivee'] ? date('H:i', strtotime($day['arrivee'])) : '-') . '</td>
                    <td>' . ($day['depart'] ? date('H:i', strtotime($day['depart'])) : '-') . '</td>
                    <td>' . ($day['pauses'] && $day['pauses'] != '00:00:00' ? $day['pauses'] : '-') . '</td>
                    <td>' . ($day['nb_pauses'] > 0 ? $day['nb_pauses'] : '-') . '</td>
                    <td>' . ($day['overtime'] > 0 ? '+' . number_format($day['overtime'], 1) . 'h' : '-') . '</td>
                </tr>';
            }
            
            $html .= '
                    </tbody>
                    <tfoot>
                        <tr class="total">
                            <td colspan="3"><strong>TOTAUX ' . htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']) . '</strong></td>
                            <td><strong>' . $user_data['total_nb_pauses'] . ' pauses</strong></td>
                            <td colspan="2"><strong>' . number_format($user_data['total_overtime'], 1) . ' heures supp</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>';
        }
        
        // Total général
        $html .= '
        <div style="margin-top: 20px; padding: 15px; background-color: #8A9A5B; color: white; border-radius: 5px;">
            <h3 style="color: white; margin: 0; text-align: center;">
                TOTAL GÉNÉRAL - ' . number_format($total_general, 1) . ' heures supplémentaires - ' . $total_pauses . ' pauses
            </h3>
        </div>';
    }
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output du PDF
    $filename = 'rapport_activites_' . date('Y-m-d_H-i') . '.pdf';
    $pdf->Output($filename, 'D'); // 'D' pour téléchargement
    exit;
}

// Vérifier si on doit générer un PDF
if (isset($_GET['generate_pdf']) && $_GET['generate_pdf'] == '1') {
    generateOvertimeReportPDF($db, $report_type, $report_period, $filter_user, $date_debut, $date_fin);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activités des Collaborateurs - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
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

        .btn-success {
            background: linear-gradient(135deg, var(--vert-sage), #6b8e23);
            color: var(--blanc);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 154, 91, 0.3);
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: var(--blanc);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
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

        .pauses-badge {
            background: #f0e6ff;
            color: #6f42c1;
            border: 1px solid #d9c8ff;
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

        .report-section {
            background: var(--ivoire);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--vert-sage);
        }

        .report-section h2 {
            color: var(--gris-anthracite);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .date-range-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
        }

        .date-range-group .filter-group {
            grid-column: span 1;
        }

        .date-range-group.full-width {
            grid-column: 1 / -1;
        }

        .period-presets {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .period-preset-btn {
            background: var(--blanc);
            border: 1px solid var(--doré-clair);
            color: var(--doré-foncé);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .period-preset-btn:hover {
            background: var(--doré-clair);
            color: var(--blanc);
        }

        .period-preset-btn.active {
            background: var(--doré-foncé);
            color: var(--blanc);
            border-color: var(--doré-foncé);
        }

        .calendar-input {
            position: relative;
        }

        .calendar-input input {
            padding-right: 2.5rem !important;
        }

        .calendar-icon {
            position: absolute;
            left: 12rem;
            top: 70%;
            transform: translateY(-50%);
            color: var(--doré-clair);
            pointer-events: none;
        }

        .pauses-info {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #6f42c1;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .pauses-info i {
            font-size: 0.7rem;
        }

        /* Style personnalisé pour Flatpickr */
        .flatpickr-calendar {
            border-radius: 10px;
            border: 1px solid var(--doré-clair);
            box-shadow: 0 10px 25px rgba(139, 90, 43, 0.15);
        }

        .flatpickr-day.selected {
            background: var(--doré-foncé);
            border-color: var(--doré-foncé);
        }

        .flatpickr-day.today {
            border-color: var(--doré-clair);
        }

        .flatpickr-day.today:hover {
            background: var(--doré-clair);
            border-color: var(--doré-clair);
        }

        .flatpickr-months .flatpickr-month {
            background: var(--doré-clair);
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: var(--doré-clair);
        }

        .flatpickr-weekdays {
            background: var(--ivoire);
        }

        .flatpickr-weekday {
            color: var(--doré-foncé);
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
            max-width: 800px;
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

        .modal-pauses-container {
            background: #f0e6ff;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #d9c8ff;
            margin-bottom: 1rem;
        }

        .modal-pauses-label {
            font-size: 1rem;
            font-weight: 600;
            color: #6f42c1;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-pauses-content {
            color: var(--gris-anthracite);
            line-height: 1.7;
            font-size: 1rem;
            padding: 1rem;
            background: var(--blanc);
            border-radius: 8px;
            border: 1px solid rgba(111, 66, 193, 0.1);
            white-space: pre-wrap;
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

            .filters-grid,
            .report-grid {
                grid-template-columns: 1fr;
            }

            .date-range-group {
                grid-template-columns: 1fr;
            }

            .period-presets {
                flex-direction: column;
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
</head>
<body>
    <?php include '../../../includes/header.php'; ?>

    <div class="container">
        <div class="header">
            <a href="../" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <h1><i class="fas fa-users"></i> Activités des Collaborateurs</h1>
            <p>Suivi des badgeages, pauses et activités quotidiennes</p>
        </div>

        <div class="content">
            <!-- Section Rapports -->
            <div class="report-section">
                <h2><i class="fas fa-chart-line"></i> Rapports des Activités</h2>
                <form method="GET" action="" id="report-form">
                    <input type="hidden" name="generate_pdf" value="1">
                    
                    <div class="period-presets">
                        <button type="button" class="period-preset-btn" data-period="today">Aujourd'hui</button>
                        <button type="button" class="period-preset-btn" data-period="yesterday">Hier</button>
                        <button type="button" class="period-preset-btn" data-period="week">Cette semaine</button>
                        <button type="button" class="period-preset-btn" data-period="last_week">Semaine dernière</button>
                        <button type="button" class="period-preset-btn" data-period="month">Ce mois</button>
                        <button type="button" class="period-preset-btn" data-period="last_month">Mois dernier</button>
                        <button type="button" class="period-preset-btn" data-period="year">Cette année</button>
                        <button type="button" class="period-preset-btn active" data-period="custom">Période personnalisée</button>
                    </div>
                    
                    <div class="report-grid">
                        <div class="filter-group">
                            <label for="report_type">
                                <i class="fas fa-file-alt"></i> Type de rapport
                            </label>
                            <select name="report_type" id="report_type" required>
                                <option value="overtime" selected>Activités complètes</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="report_period">
                                <i class="fas fa-calendar-week"></i> Période
                            </label>
                            <select name="report_period" id="report_period">
                                <option value="today">Aujourd'hui</option>
                                <option value="yesterday">Hier</option>
                                <option value="week">Semaine en cours</option>
                                <option value="last_week">Semaine dernière</option>
                                <option value="month" selected>Mois en cours</option>
                                <option value="last_month">Mois dernier</option>
                                <option value="year">Année en cours</option>
                                <option value="custom">Période personnalisée</option>
                            </select>
                        </div>
                        
                        <div class="date-range-group full-width" id="custom-date-range">
                            <div class="filter-group calendar-input">
                                <label for="date_debut">
                                    <i class="fas fa-calendar-plus"></i> Date de début
                                </label>
                                <input type="text" name="date_debut" id="date_debut" 
                                       value="<?= htmlspecialchars($date_debut) ?>" 
                                       placeholder="Sélectionner une date"
                                       readonly
                                       style="background: white; cursor: pointer;">
                                <i class="fas fa-calendar-alt calendar-icon"></i>
                            </div>
                            <div class="filter-group calendar-input">
                                <label for="date_fin">
                                    <i class="fas fa-calendar-minus"></i> Date de fin
                                </label>
                                <input type="text" name="date_fin" id="date_fin" 
                                       value="<?= htmlspecialchars($date_fin) ?>" 
                                       placeholder="Sélectionner une date"
                                       readonly
                                       style="background: white; cursor: pointer;">
                                <i class="fas fa-calendar-alt calendar-icon"></i>
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label for="report_user">
                                <i class="fas fa-user"></i> Collaborateur
                            </label>
                            <select name="filter_user" id="report_user">
                                <option value="0">Tous les collaborateurs</option>
                                <?php foreach ($users_list as $user): ?>
                                    <option value="<?= $user['id'] ?>" 
                                        <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-file-pdf"></i> Générer PDF
                                </button>
                                <button type="button" class="btn btn-info" id="preview-report">
                                    <i class="fas fa-eye"></i> Aperçu
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Section Filtres existante -->
            <div class="filters-section">
                <h2 style="color: var(--gris-anthracite); margin-bottom: 1rem;">
                    <i class="fas fa-filter"></i> Filtres de Recherche
                </h2>
                
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="filter_user">
                                <i class="fas fa-user"></i> Sélectionner un collaborateur
                            </label>
                            <select name="filter_user" id="filter_user">
                                <option value="0">Tous les collaborateurs</option>
                                <?php foreach ($users_list as $user): ?>
                                    <option value="<?= $user['id'] ?>" 
                                        <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group calendar-input">
                            <label for="filter_date">
                                <i class="fas fa-calendar"></i> Filtrer par date
                            </label>
                            <input type="text" name="filter_date" id="filter_date" 
                                   value="<?= htmlspecialchars($filter_date) ?>" 
                                   placeholder="Sélectionner une date"
                                   readonly
                                   style="background: white; cursor: pointer;">
                            <i class="fas fa-calendar-alt calendar-icon"></i>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Appliquer
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Réinitialiser
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="stats-bar">
                <div class="stats-info">
                    <i class="fas fa-chart-bar"></i>
                    Page <?= $page ?> sur <?= $totalPages ?> - 
                    <?= $totalActivities ?> activité(s) trouvée(s)
                </div>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Collaborateur</th>
                            <th>Date</th>
                            <th>Horaires</th>
                            <th>Pauses</th>
                            <th>Heures Supp</th>
                            <th>Statut</th>
                            <th>Observations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activites)): ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Aucune activité trouvée</h3>
                                    <p>
                                        <?php if ($totalActivities === 0): ?>
                                            Aucune donnée d'activité n'a été enregistrée pour le moment.
                                            <br><small>Les collaborateurs doivent d'abord badger leur arrivée.</small>
                                        <?php else: ?>
                                            Aucune activité ne correspond à vos critères de filtrage.
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activites as $activite): ?>
                                <?php
                                    // Déterminer le statut
                                    $statut = $activite['heure_depart'] ? 'complet' : 'en_cours';
                                    
                                    // Calculer la durée si départ existe
                                    $duree = null;
                                    if ($activite['heure_depart']) {
                                        try {
                                            $arrivee_time = new DateTime($activite['date_activite'] . ' ' . $activite['heure_arrivee']);
                                            $depart_time = new DateTime($activite['date_activite'] . ' ' . $activite['heure_depart']);
                                            $interval = $arrivee_time->diff($depart_time);
                                            $duree = $interval->format('%Hh %Im');
                                        } catch (Exception $e) {
                                            $duree = 'Erreur';
                                        }
                                    }
                                    
                                    // Combiner les observations
                                    $observations_list = [];
                                    if (!empty($activite['observations_arrivee'])) {
                                        $observations_list[] = "Arrivée: " . $activite['observations_arrivee'];
                                    }
                                    if (!empty($activite['observations_depart'])) {
                                        $observations_list[] = "Départ: " . $activite['observations_depart'];
                                    }
                                    $observations = implode("\n\n", $observations_list);
                                    
                                    // Utiliser les heures supplémentaires du départ si disponibles, sinon celles de l'arrivée
                                    $overtime = $activite['overtime_depart'] ?: $activite['overtime_arrivee'];
                                    
                                    // Informations sur les pauses
                                    $nb_pauses = $activite['nb_pauses'] ?? 0;
                                    $pauses_details = $activite['pauses_details'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($activite['user_prenom'] . ' ' . $activite['user_nom']) ?></strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar-day" style="color: var(--doré-clair);"></i>
                                        <?= date('d/m/Y', strtotime($activite['date_activite'])) ?>
                                    </td>
                                    <td>
                                        <div class="time-display">
                                            <div class="time-item">
                                                <i class="fas fa-sign-in-alt"></i>
                                                <span><?= date('H:i', strtotime($activite['heure_arrivee'])) ?></span>
                                            </div>
                                            <?php if ($activite['heure_depart']): ?>
                                                <div class="time-item">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                    <span><?= date('H:i', strtotime($activite['heure_depart'])) ?></span>
                                                </div>
                                                <?php if ($duree): ?>
                                                    <span class="duration-badge">
                                                        <i class="fas fa-clock"></i> <?= $duree ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #856404;">
                                                    <i class="fas fa-hourglass-half"></i> En cours
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($nb_pauses > 0): ?>
                                            <span class="pauses-badge">
                                                <i class="fas fa-coffee"></i> <?= $nb_pauses ?> pause(s)
                                            </span>
                                            <?php if ($pauses_details): ?>
                                                <div class="pauses-info">
                                                    <i class="fas fa-info-circle"></i>
                                                    <span title="<?= htmlspecialchars($pauses_details) ?>">
                                                        Détails disponibles
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--gris-anthracite); opacity: 0.6;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($overtime) && $overtime > 0): ?>
                                            <span class="overtime-badge">
                                                <i class="fas fa-clock"></i> +<?= number_format($overtime, 1) ?>h
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--gris-anthracite); opacity: 0.6;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($statut === 'complet'): ?>
                                            <span class="badge badge-complet">
                                                <i class="fas fa-check-circle"></i>
                                                Complet
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-en-cours">
                                                <i class="fas fa-spinner"></i>
                                                En cours
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="observations-cell">
                                        <?php if (!empty($observations)): ?>
                                            <?= htmlspecialchars(substr($observations, 0, 50)) ?>...
                                        <?php else: ?>
                                            <span style="color: var(--gris-anthracite); opacity: 0.6;">Aucune observation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary" 
                                                onclick='showObservations(<?= json_encode($observations) ?>, <?= json_encode($activite['user_prenom'] . ' ' . $activite['user_nom']) ?>, <?= json_encode(date('d/m/Y', strtotime($activite['date_activite']))) ?>, <?= json_encode($activite['heure_arrivee']) ?>, <?= json_encode($activite['heure_depart']) ?>, <?= json_encode($duree) ?>, <?= json_encode($overtime) ?>, <?= json_encode($nb_pauses) ?>, <?= json_encode($pauses_details) ?>)'>
                                            <i class="fas fa-eye"></i>
                                            Détails
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = array_filter([
                        'filter_user' => $filter_user,
                        'filter_date' => $filter_date
                    ]);
                    $query_string = http_build_query($query_params);
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=1&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $page - 1 ?>&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?= $i ?>&<?= $query_string ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $totalPages ?>&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour afficher les détails -->
    <div class="modal-overlay" id="observations-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-clipboard-list"></i>
                    Détails de l'Activité
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-info">
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-user"></i> Collaborateur
                        </span>
                        <span class="modal-info-value" id="modal-user"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-calendar-day"></i> Date
                        </span>
                        <span class="modal-info-value" id="modal-date"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-sign-in-alt"></i> Heure d'arrivée
                        </span>
                        <span class="modal-info-value" id="modal-arrivee"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-sign-out-alt"></i> Heure de départ
                        </span>
                        <span class="modal-info-value" id="modal-depart"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-clock"></i> Durée totale
                        </span>
                        <span class="modal-info-value" id="modal-duree"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-business-time"></i> Heures supplémentaires
                        </span>
                        <span class="modal-info-value" id="modal-overtime"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-coffee"></i> Nombre de pauses
                        </span>
                        <span class="modal-info-value" id="modal-pauses-count"></span>
                    </div>
                </div>
                
                <div class="modal-pauses-container" id="pauses-container">
                    <div class="modal-pauses-label">
                        <i class="fas fa-coffee"></i>
                        Détails des Pauses
                    </div>
                    <div class="modal-pauses-content" id="modal-pauses"></div>
                </div>
                
                <div class="modal-observations-container" id="observations-container">
                    <div class="modal-observations-label">
                        <i class="fas fa-sticky-note"></i>
                        Observations
                    </div>
                    <div class="modal-observations-content" id="modal-observations"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script>
        // Initialisation des calendriers Flatpickr
        document.addEventListener('DOMContentLoaded', function() {
            // Configuration de base pour Flatpickr
            const flatpickrConfig = {
                locale: 'fr',
                dateFormat: 'Y-m-d',
                altFormat: 'd/m/Y',
                altInput: false,
                static: true,
                monthSelectorType: 'static',
                prevArrow: '<i class="fas fa-chevron-left"></i>',
                nextArrow: '<i class="fas fa-chevron-right"></i>',
                theme: 'light'
            };

            // Calendrier pour la date de début
            const dateDebutPicker = flatpickr('#date_debut', {
                ...flatpickrConfig,
                defaultDate: '<?= $date_debut ?>',
                onChange: function(selectedDates, dateStr) {
                    // Mettre à jour la date de fin minimum
                    if (selectedDates[0]) {
                        dateFinPicker.set('minDate', selectedDates[0]);
                    }
                }
            });

            // Calendrier pour la date de fin
            const dateFinPicker = flatpickr('#date_fin', {
                ...flatpickrConfig,
                defaultDate: '<?= $date_fin ?>',
                minDate: '<?= $date_debut ?>'
            });

            // Calendrier pour le filtre de date unique
            const filterDatePicker = flatpickr('#filter_date', {
                ...flatpickrConfig,
                defaultDate: '<?= $filter_date ?>'
            });

            // Gestion des périodes prédéfinies et personnalisées
            const reportPeriod = document.getElementById('report_period');
            const customDateRange = document.getElementById('custom-date-range');
            const periodPresetBtns = document.querySelectorAll('.period-preset-btn');
            const previewBtn = document.getElementById('preview-report');
            
            function toggleCustomDateRange() {
                if (reportPeriod.value === 'custom') {
                    customDateRange.style.display = 'grid';
                } else {
                    customDateRange.style.display = 'none';
                }
            }
            
            function setPeriod(period) {
                const today = new Date();
                let startDate, endDate;
                
                switch(period) {
                    case 'today':
                        startDate = today;
                        endDate = today;
                        break;
                    case 'yesterday':
                        const yesterday = new Date(today);
                        yesterday.setDate(today.getDate() - 1);
                        startDate = yesterday;
                        endDate = yesterday;
                        break;
                    case 'week':
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1));
                        const endOfWeek = new Date(startOfWeek);
                        endOfWeek.setDate(startOfWeek.getDate() + 6);
                        startDate = startOfWeek;
                        endDate = endOfWeek;
                        break;
                    case 'last_week':
                        const startOfLastWeek = new Date(today);
                        startOfLastWeek.setDate(today.getDate() - today.getDay() - 6);
                        const endOfLastWeek = new Date(startOfLastWeek);
                        endOfLastWeek.setDate(startOfLastWeek.getDate() + 6);
                        startDate = startOfLastWeek;
                        endDate = endOfLastWeek;
                        break;
                    case 'month':
                        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                        break;
                    case 'last_month':
                        startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                    case 'year':
                        startDate = new Date(today.getFullYear(), 0, 1);
                        endDate = new Date(today.getFullYear(), 11, 31);
                        break;
                    case 'custom':
                        // Ne pas changer les dates pour la période personnalisée
                        return;
                }
                
                dateDebutPicker.setDate(startDate);
                dateFinPicker.setDate(endDate);
                reportPeriod.value = period;
                toggleCustomDateRange();
            }
            
            // Gestion des boutons de période prédéfinie
            periodPresetBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const period = this.dataset.period;
                    
                    // Mettre à jour l'état actif des boutons
                    periodPresetBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    setPeriod(period);
                });
            });
            
            // Gestion du changement dans le select
            reportPeriod.addEventListener('change', function() {
                toggleCustomDateRange();
                if (this.value !== 'custom') {
                    setPeriod(this.value);
                }
                
                // Mettre à jour l'état actif des boutons
                periodPresetBtns.forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.period === this.value);
                });
            });
            
            // Aperçu du rapport
            previewBtn.addEventListener('click', function() {
                const form = document.getElementById('report-form');
                const originalAction = form.action;
                const originalTarget = form.target;
                
                // Ouvrir dans un nouvel onglet
                form.target = '_blank';
                form.action = window.location.href.replace(/(\?|&)generate_pdf=1/, '') + '&generate_pdf=1';
                form.submit();
                
                // Restaurer les valeurs originales
                setTimeout(() => {
                    form.action = originalAction;
                    form.target = originalTarget;
                }, 100);
            });
            
            // Initialiser l'état
            toggleCustomDateRange();
            
            // Animation des lignes du tableau
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.4s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });

        function showObservations(observations, user, date, arrivee, depart, duree, overtime, pausesCount, pausesDetails) {
            document.getElementById('modal-user').textContent = user;
            document.getElementById('modal-date').textContent = date;
            document.getElementById('modal-arrivee').textContent = arrivee ? new Date('2000-01-01 ' + arrivee).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) : '-';
            document.getElementById('modal-depart').textContent = depart ? new Date('2000-01-01 ' + depart).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) : 'En cours';
            document.getElementById('modal-duree').textContent = duree || 'En cours';
            document.getElementById('modal-overtime').textContent = overtime && overtime > 0 ? '+' + overtime + 'h' : 'Aucune';
            document.getElementById('modal-pauses-count').textContent = pausesCount > 0 ? pausesCount + ' pause(s)' : 'Aucune';
            
            const pausesContainer = document.getElementById('pauses-container');
            const pausesContent = document.getElementById('modal-pauses');
            const observationsContainer = document.getElementById('observations-container');
            const observationsContent = document.getElementById('modal-observations');
            
            // Gestion des pauses
            if (pausesDetails && pausesDetails.trim() !== '') {
                pausesContainer.style.display = 'block';
                pausesContent.textContent = pausesDetails.replace(/\|/g, '\n\n');
            } else {
                pausesContainer.style.display = 'none';
            }
            
            // Gestion des observations
            if (observations && observations.trim() !== '') {
                observationsContainer.style.display = 'block';
                observationsContent.textContent = observations;
            } else {
                observationsContainer.style.display = 'none';
            }
            
            const modal = document.getElementById('observations-modal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('observations-modal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('observations-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>