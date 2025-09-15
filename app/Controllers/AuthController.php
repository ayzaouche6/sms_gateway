<?php
/**
 * Contrôleur d'authentification
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        // Rediriger si déjà connecté
        if (Auth::check()) {
            $this->redirect('/dashboard');
            return;
        }
        
        $this->view('auth/login', [
            'title' => 'Connexion'
        ]);
    }
    
    public function login()
    {
        if (!$this->validateCSRF()) {
            return;
        }
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validation des entrées
        $validation = Middleware::validateInput([
            'email' => 'required|email',
            'password' => 'required|min:1'
        ]);
        
        if ($validation !== true) {
            $this->view('auth/login', [
                'title' => 'Connexion',
                'errors' => $validation,
                'email' => $email
            ]);
            return;
        }
        
        // Tentative de connexion
        if (Auth::attempt($email, $password)) {
            $this->redirect('/dashboard');
        } else {
            $this->view('auth/login', [
                'title' => 'Connexion',
                'error' => 'Email ou mot de passe incorrect',
                'email' => $email
            ]);
        }
    }
    
    public function logout()
    {
        Auth::logout();
        $this->redirect('/login');
    }
}