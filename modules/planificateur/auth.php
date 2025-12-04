<?php
// Start the session if it's not already started
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

function isPlanner() {
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
        header('Location: ../../index.php');
        exit();
    }
}

// New function to check the user's status
function checkUserStatus() {
    if (isLoggedIn()) {
        require_once '../../config/database.php';
        $db = getDB();
        
        $stmt = $db->prepare("SELECT active FROM users WHERE id = :id");
        $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If the user no longer exists or is disabled, log them out
        if (!$user || !$user['active']) {
            session_destroy();
            header('Location: ../../login.php?message=compte_desactive');
            exit();
        }
    }
}

function redirectBasedOnRole() {
    if (!isLoggedIn()) return;
    
    // Check the user's status before redirection
    checkUserStatus();
    
    $role = getUserRole();
    $currentPath = $_SERVER['PHP_SELF'];
    
    // Avoid infinite redirects by checking if we are already in the correct directory
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

    // Check the user's status before checking permissions
    checkUserStatus();

    $role = getUserRole();

    // If no specific role is requested, allow access
    if (empty($roles_allowed)) {
        return;
    }

    // If super_admin is logged in, they always have access
    if ($role === 'super_admin') {
        return;
    }

    // Check if the role is in the allowed list
    if (!in_array($role, $roles_allowed)) {
        // For pages accessible by multiple roles, redirect to the appropriate dashboard
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
                echo "<h1>Access denied</h1><p>You do not have permission to access this page.</p>";
                break;
        }
        exit();
    }
}

// Utility function to debug redirects
function debugRedirect($message) {
    if (isset($_GET['debug'])) {
        echo "\n";
    }
}