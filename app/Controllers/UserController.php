<?php
/**
 * Contrôleur de gestion des utilisateurs
 */
class UserController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
            return;
        }
        
        $this->view('users/profile', [
            'title' => 'Mon profil',
            'user' => $user
        ]);
    }
    
    public function updateProfile()
    {
        if (!$this->validateCSRF()) {
            return;
        }
        
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
            return;
        }
        
        $validation = Middleware::validateInput([
            'username' => 'required|min:3|max:50',
            'email' => 'required|email'
        ]);
        
        if ($validation !== true) {
            $this->view('users/profile', [
                'title' => 'Mon profil',
                'user' => $user,
                'errors' => $validation
            ]);
            return;
        }
        
        try {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            
            // Vérifier l'unicité
            if (User::isUsernameTaken($username, $user['id'])) {
                throw new Exception('Ce nom d\'utilisateur est déjà utilisé');
            }
            
            if (User::isEmailTaken($email, $user['id'])) {
                throw new Exception('Cette adresse email est déjà utilisée');
            }
            
            $updateData = [
                'username' => $username,
                'email' => $email,
                'language' => $_POST['language'] ?? 'fr'
            ];
            
            // Mise à jour du mot de passe si fourni
            if (!empty($_POST['password'])) {
                $passwordValidation = SecurityService::validatePassword($_POST['password']);
                if ($passwordValidation !== true) {
                    $this->view('users/profile', [
                        'title' => 'Mon profil',
                        'user' => $user,
                        'errors' => ['password' => implode(', ', $passwordValidation)]
                    ]);
                    return;
                }
                
                if ($_POST['password'] !== $_POST['password_confirm']) {
                    throw new Exception('Les mots de passe ne correspondent pas');
                }
                
                $updateData['password'] = $_POST['password'];
            }
            
            User::update($user['id'], $updateData);
            
            // Mettre à jour la session
            $_SESSION['user_email'] = $email;
            
            Logger::info("User profile updated", ['user_id' => $user['id']]);
            
            $this->view('users/profile', [
                'title' => 'Mon profil',
                'user' => array_merge($user, $updateData),
                'success' => 'Profil mis à jour avec succès'
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error updating profile: ' . $e->getMessage());
            $this->view('users/profile', [
                'title' => 'Mon profil',
                'user' => $user,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function index()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        
        try {
            if ($search) {
                $users = User::search($search, $limit, $offset);
                $total = count(User::search($search, 1000, 0)); // Approximation
            } else {
                $users = User::getAll($limit, $offset);
                $total = count(User::getAll());
            }
            
            $this->view('users/index', [
                'title' => 'Gestion des utilisateurs',
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit),
                'search' => $search
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error loading users: ' . $e->getMessage());
            $this->view('users/index', [
                'title' => 'Gestion des utilisateurs',
                'error' => 'Erreur lors du chargement des utilisateurs'
            ]);
        }
    }
    
    public function create()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        $this->view('users/create', [
            'title' => 'Créer un utilisateur'
        ]);
    }
    
    public function store()
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        $validation = Middleware::validateInput([
            'username' => 'required|min:3|max:50',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role' => 'required'
        ]);
        
        if ($validation !== true) {
            $this->view('users/create', [
                'title' => 'Créer un utilisateur',
                'errors' => $validation,
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => $_POST['role'] ?? ''
            ]);
            return;
        }
        
        try {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            
            // Vérifications
            if (User::isUsernameTaken($username)) {
                throw new Exception('Ce nom d\'utilisateur est déjà utilisé');
            }
            
            if (User::isEmailTaken($email)) {
                throw new Exception('Cette adresse email est déjà utilisée');
            }
            
            if (!in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_OPERATOR])) {
                throw new Exception('Rôle invalide');
            }
            
            $passwordValidation = SecurityService::validatePassword($password);
            if ($passwordValidation !== true) {
                throw new Exception('Mot de passe invalide: ' . implode(', ', $passwordValidation));
            }
            
            if ($password !== $_POST['password_confirm']) {
                throw new Exception('Les mots de passe ne correspondent pas');
            }
            
            $userId = User::create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'language' => $_POST['language'] ?? 'fr'
            ]);
            
            Logger::info("User created", [
                'user_id' => $userId,
                'username' => $username,
                'role' => $role,
                'created_by' => Auth::id()
            ]);
            
            $this->redirect('/users?success=' . urlencode('Utilisateur créé avec succès'));
            
        } catch (Exception $e) {
            Logger::error('Error creating user: ' . $e->getMessage());
            $this->view('users/create', [
                'title' => 'Créer un utilisateur',
                'error' => $e->getMessage(),
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => $_POST['role'] ?? ''
            ]);
        }
    }
    
    public function edit($userId)
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        try {
            $user = User::find($userId);
            if (!$user) {
                $this->redirect('/users?error=' . urlencode('Utilisateur non trouvé'));
                return;
            }
            
            $this->view('users/edit', [
                'title' => 'Modifier l\'utilisateur',
                'user' => $user
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error loading user for edit: ' . $e->getMessage());
            $this->redirect('/users?error=' . urlencode('Erreur lors du chargement'));
        }
    }
    
    public function update($userId)
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            $user = User::find($userId);
            if (!$user) {
                $this->redirect('/users?error=' . urlencode('Utilisateur non trouvé'));
                return;
            }
            
            $validation = Middleware::validateInput([
                'username' => 'required|min:3|max:50',
                'email' => 'required|email',
                'role' => 'required'
            ]);
            
            if ($validation !== true) {
                $this->view('users/edit', [
                    'title' => 'Modifier l\'utilisateur',
                    'user' => $user,
                    'errors' => $validation
                ]);
                return;
            }
            
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            
            // Vérifications
            if (User::isUsernameTaken($username, $userId)) {
                throw new Exception('Ce nom d\'utilisateur est déjà utilisé');
            }
            
            if (User::isEmailTaken($email, $userId)) {
                throw new Exception('Cette adresse email est déjà utilisée');
            }
            
            if (!in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_OPERATOR])) {
                throw new Exception('Rôle invalide');
            }
            
            $updateData = [
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'language' => $_POST['language'] ?? 'fr'
            ];
            
            // Mise à jour du mot de passe si fourni
            if (!empty($_POST['password'])) {
                $passwordValidation = SecurityService::validatePassword($_POST['password']);
                if ($passwordValidation !== true) {
                    throw new Exception('Mot de passe invalide: ' . implode(', ', $passwordValidation));
                }
                
                if ($_POST['password'] !== $_POST['password_confirm']) {
                    throw new Exception('Les mots de passe ne correspondent pas');
                }
                
                $updateData['password'] = $_POST['password'];
            }
            
            User::update($userId, $updateData);
            
            Logger::info("User updated", [
                'user_id' => $userId,
                'updated_by' => Auth::id()
            ]);
            
            $this->redirect('/users?success=' . urlencode('Utilisateur mis à jour avec succès'));
            
        } catch (Exception $e) {
            Logger::error('Error updating user: ' . $e->getMessage());
            $this->view('users/edit', [
                'title' => 'Modifier l\'utilisateur',
                'user' => $user ?? User::find($userId),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function delete($userId)
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            $user = User::find($userId);
            if (!$user) {
                $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
                return;
            }
            
            // Empêcher la suppression de son propre compte
            if ($userId == Auth::id()) {
                $this->json(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte'], 400);
                return;
            }
            
            User::delete($userId);
            
            Logger::info("User deleted", [
                'user_id' => $userId,
                'username' => $user['username'],
                'deleted_by' => Auth::id()
            ]);
            
            $this->json(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
            
        } catch (Exception $e) {
            Logger::error('Error deleting user: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur lors de la suppression'], 500);
        }
    }
    
    public function toggleStatus($userId)
    {
        if (!$this->requireRole(ROLE_ADMIN)) {
            return;
        }
        
        if (!$this->validateCSRF()) {
            return;
        }
        
        try {
            $user = User::find($userId);
            if (!$user) {
                $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
                return;
            }
            
            // Empêcher la désactivation de son propre compte
            if ($userId == Auth::id()) {
                $this->json(['success' => false, 'message' => 'Vous ne pouvez pas désactiver votre propre compte'], 400);
                return;
            }
            
            $newStatus = $user['is_active'] ? 0 : 1;
            User::update($userId, ['is_active' => $newStatus]);
            
            Logger::info("User status toggled", [
                'user_id' => $userId,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'changed_by' => Auth::id()
            ]);
            
            $this->json([
                'success' => true, 
                'message' => 'Statut mis à jour avec succès',
                'new_status' => $newStatus
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error toggling user status: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour'], 500);
        }
    }
}