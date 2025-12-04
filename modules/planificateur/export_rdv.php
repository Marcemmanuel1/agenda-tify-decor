<?php
try {
    require_once '../../includes/auth.php';
    require_once '../../config/database.php';
    require_once '../../includes/functions.php';

    // Vérifier si l'utilisateur est connecté et a le bon statut
    redirectIfNotLoggedIn();

    // Vérification supplémentaire du statut utilisateur
    checkUserStatus();

    if (!isPlanificateur() && !isSuperAdmin()) {
        header('Location: ../agent/');
        exit();
    }

    // Enregistrer l'export dans l'historique avant de générer le PDF
    $filters = [
        'statut'      => filter_input(INPUT_GET, 'statut', FILTER_SANITIZE_SPECIAL_CHARS),
        'commune'     => filter_input(INPUT_GET, 'commune', FILTER_SANITIZE_SPECIAL_CHARS),
        'date_debut'  => filter_input(INPUT_GET, 'date_debut', FILTER_SANITIZE_SPECIAL_CHARS),
        'date_fin'    => filter_input(INPUT_GET, 'date_fin', FILTER_SANITIZE_SPECIAL_CHARS),
        'search'      => filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS),
    ];

    // Construire la description des filtres pour l'historique
    $filters_description = "Export PDF des rendez-vous avec filtres: ";
    $filters_array = [];
    if (!empty($filters['statut'])) $filters_array[] = "Statut: " . $filters['statut'];
    if (!empty($filters['commune'])) $filters_array[] = "Commune: " . $filters['commune'];
    if (!empty($filters['date_debut'])) $filters_array[] = "Du: " . date('d/m/Y', strtotime($filters['date_debut']));
    if (!empty($filters['date_fin'])) $filters_array[] = "Au: " . date('d/m/Y', strtotime($filters['date_fin']));
    if (!empty($filters['search'])) $filters_array[] = "Recherche: " . $filters['search'];
    $filters_description .= count($filters_array) ? implode(", ", $filters_array) : "Aucun filtre";

    // Enregistrer l'action dans l'historique
    logActivity(
        $_SESSION['user_id'],
        'export',
        $filters_description
    );

    require_once('../../vendor/tecnickcom/tcpdf/tcpdf.php');

    // --- 1. Input Filtering and Security ---
    // (Les filtres sont déjà récupérés plus haut)

    // --- 2. Refactored Database Logic ---
    function fetchRendezvousData($db, $userId, $filters) {
        $sql = "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone,
                      u.nom as agent_nom, u.prenom as agent_prenom
                FROM rendezvous r
                JOIN clients c ON r.client_id = c.id
                LEFT JOIN users u ON r.agent_id = u.id
                WHERE r.planificateur_id = ?";
        $params = [$userId];

        if (!empty($filters['statut'])) {
            $sql .= " AND r.statut_rdv = ?";
            $params[] = $filters['statut'];
        }
        if (!empty($filters['commune'])) {
            $sql .= " AND c.commune LIKE ?";
            $params[] = '%' . $filters['commune'] . '%';
        }
        if (!empty($filters['date_debut'])) {
            $sql .= " AND r.date_rdv >= ?";
            $params[] = date('Y-m-d', strtotime($filters['date_debut'])) . ' 00:00:00';
        }
        if (!empty($filters['date_fin'])) {
            $sql .= " AND r.date_rdv <= ?";
            $params[] = date('Y-m-d', strtotime($filters['date_fin'])) . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR r.motif LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        }
        $sql .= " ORDER BY r.date_rdv DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $db = getDB();
    $rendezvous = fetchRendezvousData($db, $_SESSION['user_id'], $filters);

    // --- 3. Robustness: Define Constants and Colors ---
    define('PDF_CREATOR_NAME', 'TIFY DECOR');
    define('PDF_AUTHOR_NAME', 'TIFY DECOR');
    define('PDF_TITLE', 'Liste des Rendez-vous');
    define('PDF_SUBJECT', 'Export des rendez-vous');

    define('COLOR_GOLD_LIGHT', [221, 186, 118]);
    define('COLOR_GOLD_DARK',  [139, 90, 43]);
    define('COLOR_WHITE',      [255, 255, 255]);
    define('COLOR_IVORY',      [250, 250, 245]);
    define('COLOR_GRAY_DARK',  [51, 51, 51]);
    define('COLOR_GRAY_LIGHT', [240, 240, 240]);

    // Badge colors
    $badge_colors = [
        'En attente' => [255, 193, 7],
        'Effectué'   => [40, 167, 69],
        'Annulé'     => [220, 53, 69],
        'Modifié'    => [23, 162, 184],
    ];

    // --- 4. Custom PDF Class with Centered Watermark ---
    class CustomPDF extends TCPDF {
        public function Header() {
            // Bandeau doré avec ombre
            $this->SetFillColorArray(COLOR_GOLD_LIGHT);
            $this->Rect(0, 0, 300, 30, 'F');
            
            // Bordure inférieure dorée
            $this->SetDrawColorArray(COLOR_GOLD_DARK);
            $this->Line(0, 30, 300, 30);

            // Logo / Nom entreprise centré
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColorArray(COLOR_GOLD_DARK);
            $this->Cell(0, 15, 'TIFY DECOR - AGENDA DES RENDEZ-VOUS', 0, 1, 'C');
            
            // Sous-titre
            $this->SetFont('helvetica', 'I', 10);
            $this->SetTextColorArray(COLOR_WHITE);
            $this->Cell(0, 0, 'Gestion professionnelle des rendez-vous clients', 0, 1, 'C');

            // Watermark (Filigrane)
            $this->SetAlpha(0.03);
            $this->SetFont('helvetica', 'B', 70);
            $pageWidth = $this->getPageWidth();
            $pageHeight = $this->getPageHeight();
            $text = 'TIFY DECOR';
            $textWidth = $this->GetStringWidth($text);
            $textHeight = 70 * 1.2; // A rough estimate for font height

            // Calculate coordinates for centering
            $x = ($pageWidth / 2) - ($textWidth / 2);
            $y = ($pageHeight / 2) - ($textHeight / 2);

            $this->Rotate(45, $x, $y);
            $this->Text($x, $y, $text);
            $this->Rotate(0);
            $this->SetAlpha(1);

            $this->Ln(10);
        }

        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 9);
            $this->SetTextColorArray(COLOR_GOLD_DARK);
            $this->Cell(0, 10, '© ' . date('Y') . ' TIFY DECOR | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 'T', false, 'C');
        }
    }

    // --- PDF Generation ---
    $pdf = new CustomPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR_NAME);
    $pdf->SetAuthor(PDF_AUTHOR_NAME);
    $pdf->SetTitle(PDF_TITLE);
    $pdf->SetSubject(PDF_SUBJECT);

    $pdf->SetMargins(15, 40, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->AddPage();

    // Main title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColorArray(COLOR_GOLD_DARK);
    $pdf->Cell(0, 15, 'LISTE DES RENDEZ-VOUS', 0, 1, 'C');
    $pdf->Ln(5);

    // --- Centered Filters Box ---
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_GRAY_DARK);
    $filters_text = "Filtres appliqués : ";
    $filters_array = [];
    if (!empty($filters['statut'])) $filters_array[] = "Statut: " . $filters['statut'];
    if (!empty($filters['commune'])) $filters_array[] = "Commune: " . $filters['commune'];
    if (!empty($filters['date_debut'])) $filters_array[] = "Du: " . date('d/m/Y', strtotime($filters['date_debut']));
    if (!empty($filters['date_fin'])) $filters_array[] = "Au: " . date('d/m/Y', strtotime($filters['date_fin']));
    if (!empty($filters['search'])) $filters_array[] = "Recherche: " . $filters['search'];
    $filters_text .= count($filters_array) ? implode(", ", $filters_array) : "Aucun filtre";

    $box_width = 260;
    $box_height = 10;
    $box_x = ($pdf->getPageWidth() - $box_width) / 2;
    $box_y = $pdf->GetY();

    $pdf->SetFillColorArray(COLOR_GRAY_LIGHT);
    $pdf->SetDrawColorArray(COLOR_GOLD_LIGHT);
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect($box_x, $box_y, $box_width, $box_height, 2, '1111', 'DF');
    $pdf->SetXY($box_x, $box_y);
    $pdf->Cell($box_width, $box_height, $filters_text, 0, 1, 'C', 0);
    $pdf->Ln(10);

    // Table header
    $header = ['Client', 'Téléphone', 'Commune', 'Date RDV', 'Agent', 'Statut', 'Paiement'];
    $column_widths = [45, 30, 35, 35, 45, 30, 30];
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColorArray(COLOR_GOLD_DARK);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColorArray(COLOR_GOLD_LIGHT);
    $pdf->SetLineWidth(0.3);

    $table_width = array_sum($column_widths);
    $start_x = ($pdf->getPageWidth() - $table_width) / 2;
    $pdf->SetX($start_x);
    foreach ($header as $i => $col) {
        $pdf->Cell($column_widths[$i], 9, $col, 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    foreach ($rendezvous as $rdv) {
        $pdf->SetX($start_x);
        $pdf->SetFillColor($fill ? COLOR_GRAY_LIGHT[0] : 255, $fill ? COLOR_GRAY_LIGHT[1] : 255, $fill ? COLOR_GRAY_LIGHT[2] : 255);
        $pdf->SetTextColorArray(COLOR_GRAY_DARK);
        
        $pdf->Cell($column_widths[0], 7, $rdv['client_prenom'] . ' ' . $rdv['client_nom'], 'LR', 0, 'L', $fill);
        $pdf->Cell($column_widths[1], 7, $rdv['telephone'], 'LR', 0, 'C', $fill);
        $pdf->Cell($column_widths[2], 7, $rdv['commune'], 'LR', 0, 'L', $fill);
        $pdf->Cell($column_widths[3], 7, date('d/m/Y H:i', strtotime($rdv['date_rdv'])), 'LR', 0, 'C', $fill);
        $agent = $rdv['agent_id'] ? $rdv['agent_prenom'] . ' ' . $rdv['agent_nom'] : 'Non assigné';
        $pdf->Cell($column_widths[4], 7, $agent, 'LR', 0, 'L', $fill);

        // Statut with badge color
        $color = $badge_colors[$rdv['statut_rdv']] ?? [108, 117, 125];
        $pdf->SetFillColorArray($color);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($column_widths[5], 7, $rdv['statut_rdv'], 'LR', 0, 'C', 1);

        // Paiement with badge color
        $color = $rdv['statut_paiement'] == 'Payé' ? [40, 167, 69] : [220, 53, 69];
        $pdf->SetFillColorArray($color);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($column_widths[6], 7, $rdv['statut_paiement'], 'LR', 0, 'C', 1);

        $pdf->Ln();
        $fill = !$fill;
    }

    // Closing line
    $pdf->SetX($start_x);
    $pdf->Cell(array_sum($column_widths), 0, '', 'T');
    $pdf->Ln(10);

    // Summary and legend
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->SetTextColorArray(COLOR_GRAY_DARK);
    $pdf->Cell(0, 10, 'Généré le ' . date('d/m/Y à H:i') . ' - ' . count($rendezvous) . ' rendez-vous trouvés', 0, 0, 'C');

    $pdf->Ln(15);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 0, 'LÉGENDE DES STATUTS :', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 8);
    $legend_x = ($pdf->getPageWidth() - 120) / 2;
    foreach ($badge_colors as $statut => $color) {
        $pdf->SetX($legend_x);
        $pdf->SetFillColorArray($color);
        $pdf->Cell(8, 5, '', 0, 0, 'C', 1);
        $pdf->Cell(25, 5, $statut, 0, 0, 'L');
        $pdf->Ln(6);
    }

    // Output
    $pdf->Output('liste_rendezvous_' . date('Y-m-d') . '.pdf', 'I');
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    echo "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
} catch (Exception $e) {
    http_response_code(500);
    echo "Une erreur inattendue est survenue. Veuillez réessayer plus tard.";
}
?>