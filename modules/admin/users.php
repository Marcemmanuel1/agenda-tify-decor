<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

if (!isSuperAdmin()) {
    header('Location: ../planificateur/');
    exit();
}

$db = getDB();
$message = '';

// Ajouter un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    
    if ($nom && $prenom && $email && $password && $role) {
        // Vérifier si l'email existe déjà
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert error">Cet email est déjà utilisé.</div>';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (nom, prenom, email, password, role) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$nom, $prenom, $email, $hashedPassword, $role])) {
                $message = '<div class="alert success">Utilisateur ajouté avec succès.</div>';
            } else {
                $message = '<div class="alert error">Erreur lors de l\'ajout de l\'utilisateur.</div>';
            }
        }
    } else {
        $message = '<div class="alert error">Veuillez remplir tous les champs correctement.</div>';
    }
}

// Modifier un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $active = isset($_POST['active']) ? 1 : 0;
    
    if ($id && $nom && $prenom && $email && $role) {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert error">Cet email est déjà utilisé par un autre utilisateur.</div>';
        } else {
            $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, role = ?, active = ? WHERE id = ?");
            
            if ($stmt->execute([$nom, $prenom, $email, $role, $active, $id])) {
                $message = '<div class="alert success">Utilisateur modifié avec succès.</div>';
            } else {
                $message = '<div class="alert error">Erreur lors de la modification de l\'utilisateur.</div>';
            }
        }
    } else {
        $message = '<div class="alert error">Veuillez remplir tous les champs correctement.</div>';
    }
}

// Récupérer tous les utilisateurs
$stmt = $db->query("SELECT id, nom, prenom, email, role, active, created_at FROM users ORDER BY nom, prenom");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="sidebar">
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php" style="text-decoration: none; color: inherit;"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li class="nav-item active"><i class="fas fa-users"></i> Gestion utilisateurs</li>
            <li class="nav-item"><a href="calendrier.php" style="text-decoration: none; color: inherit;"><i class="fas fa-calendar-alt"></i> Calendrier</a></li>
            <li class="nav-item"><i class="fas fa-cog"></i> Paramètres</li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Gestion des Utilisateurs</h1>
        </div>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Ajouter un utilisateur</h2>
            </div>
            
            <form method="POST" class="user-form">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Rôle</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="planificateur">Planificateur</option>
                            <option value="agent">Agent</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="add_user" class="btn btn-primary">Ajouter l'utilisateur</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste des utilisateurs</h2>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Date création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></td>
                        <td>
                            <span class="badge <?= $user['active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $user['active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-primary edit-user" data-id="<?= $user['id'] ?>" 
                                data-nom="<?= htmlspecialchars($user['nom']) ?>" 
                                data-prenom="<?= htmlspecialchars($user['prenom']) ?>" 
                                data-email="<?= htmlspecialchars($user['email']) ?>" 
                                data-role="<?= $user['role'] ?>" 
                                data-active="<?= $user['active'] ?>">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal de modification -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background-color: white; width: 500px; margin: 100px auto; padding: 20px; border-radius: 8px;">
            <h2>Modifier l'utilisateur</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_nom">Nom</label>
                    <input type="text" id="edit_nom" name="nom" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_prenom">Prénom</label>
                    <input type="text" id="edit_prenom" name="prenom" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Rôle</label>
                    <select id="edit_role" name="role" class="form-control" required>
                        <option value="super_admin">Super Admin</option>
                        <option value="planificateur">Planificateur</option>
                        <option value="agent">Agent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_active" name="active" value="1"> Actif
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').style.display = 'none'">Annuler</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
    <script>
        // Gestion de la modal d'édition
        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const nom = this.getAttribute('data-nom');
                const prenom = this.getAttribute('data-prenom');
                const email = this.getAttribute('data-email');
                const role = this.getAttribute('data-role');
                const active = this.getAttribute('data-active');
                
                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_nom').value = nom;
                document.getElementById('edit_prenom').value = prenom;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_role').value = role;
                document.getElementById('edit_active').checked = active === '1';
                
                document.getElementById('editModal').style.display = 'block';
            });
        });
    </script>
</body>
</html>