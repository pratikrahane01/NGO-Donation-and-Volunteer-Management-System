<?php
/**
 * Session Management and Remember Me logic
 */
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/../models/User.php';

class Session {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_strict_mode', 1);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function login($user, $remember = false) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'] ?? 'User';
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];

        if ($remember) {
            $token = Security::generateRememberToken($user['id'], $user['password']);
            setcookie('remember_me', $token, time() + (86400 * 30), '/', '', isset($_SERVER['HTTPS']), true);
        }
    }

    public static function logout() {
        self::start();
        $_SESSION = [];
        session_destroy();
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }

    public static function checkLogin() {
        self::start();
        if (isset($_SESSION['user_id'])) {
            return true;
        }

        // Check remember me cookie
        if (isset($_COOKIE['remember_me'])) {
            $data = Security::verifyRememberToken($_COOKIE['remember_me']);
            if ($data) {
                $userModel = new User();
                $user = $userModel->findById($data['user_id']);
                if ($user && hash_equals($user['password'], $data['password_hash']) && ($user['status'] ?? '') === 'active') {
                    self::login($user, false); // Log them in, don't recreate cookie
                    return true;
                }
            }
            // Invalid token, clear it
            setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
        return false;
    }
}
