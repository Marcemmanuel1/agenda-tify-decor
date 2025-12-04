<?php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function isSuperAdmin() {
    return getUserRole() === 'super_admin';
}

function isPlanificateur() {
    return getUserRole() === 'planificateur';
}

function isAgent() {
    return getUserRole() === 'agent';
}

function isAdminGeneral() {
    return getUserRole() === 'admingeneral';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: ../../../index.php');
        exit();
    }
}

// Nouvelle fonction pour vérifier le statut de l'utilisateur
function checkUserStatus() {
    if (isLoggedIn()) {
        require_once '../../config/database.php';
        $db = getDB();
        
        $stmt = $db->prepare("SELECT active FROM users WHERE id = :id");
        $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si l'utilisateur n'existe plus ou est désactivé, le déconnecter
        if (!$user || !$user['active']) {
            session_destroy();
            header('Location: ../../login.php?message=compte_desactive');
            exit();
        }
    }
}

function redirectBasedOnRole() {
    if (!isLoggedIn()) return;
    
    // Vérifier le statut de l'utilisateur avant la redirection
    checkUserStatus();
    
    $role = getUserRole();
    $currentPath = $_SERVER['PHP_SELF'];
    
    // Éviter les redirections infinies en vérifiant si nous sommes déjà dans le bon répertoire
    $isInAdminDir = strpos($currentPath, '/modules/admin/') !== false;
    $isInPlanificateurDir = strpos($currentPath, '/modules/planificateur/') !== false;
    $isInAgentDir = strpos($currentPath, '/modules/agent/') !== false;
    $isInAdminGeneralDir = strpos($currentPath, '/modules/admin_general/') !== false;
    
    if ($role === 'super_admin' && !$isInAdminDir) {
        header('Location: ../admin/');
        exit();
    } elseif ($role === 'planificateur' && !$isInPlanificateurDir) {
        header('Location: ../planificateur/');
        exit();
    } elseif ($role === 'agent' && !$isInAgentDir) {
        header('Location: ../agent/');
        exit();
    } elseif ($role === 'admingeneral' && !$isInAdminGeneralDir) {
        header('Location: ../admin_general/');
        exit();
    }
}

function check_auth(array $roles_allowed = []) {
    if (!isLoggedIn()) {
        header("Location: ../../index.php");
        exit();
    }

    // Vérifier le statut de l'utilisateur avant de vérifier les autorisations
    checkUserStatus();

    $role = getUserRole();

    // Si aucun rôle spécifique n'est demandé, on laisse passer
    if (empty($roles_allowed)) {
        return;
    }

    // Si super_admin est connecté, il a toujours accès
    if ($role === 'super_admin') {
        return;
    }

    // Vérifier si le rôle est dans la liste autorisée
    if (!in_array($role, $roles_allowed)) {
        // Pour les pages accessibles par plusieurs rôles, rediriger vers le dashboard approprié
        switch ($role) {
            case 'planificateur':
                header('Location: ../planificateur/');
                break;
            case 'agent':
                header('Location: ../agent/');
                break;
            case 'admingeneral':
                header('Location: ../admin_general/');
                break;
            default:
                header("HTTP/1.1 403 Forbidden");
                echo "<h1>Accès refusé</h1><p>Vous n'avez pas la permission pour accéder à cette page.</p>";
                break;
        }
        exit();
    }
}

// Fonction utilitaire pour debugger les redirections
function debugRedirect($message) {
    if (isset($_GET['debug'])) {
        echo "<!-- DEBUG: $message -->\n";
    }
}
?>