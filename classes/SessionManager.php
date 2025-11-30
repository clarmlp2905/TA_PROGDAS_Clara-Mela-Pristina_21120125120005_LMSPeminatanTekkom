<?php
class SessionManager {
    public static function initDefaults(): void {
        if(!isset($_SESSION['progress'])) $_SESSION['progress'] = ['assessments'=>[], 'materials'=>[]];
        if(!isset($_SESSION['locked'])) $_SESSION['locked'] = ['assessment'=>false, 'challenge_track'=>null];
    }

    public static function getUser(): ?User {
        return User::fromSession();
    }

    public static function setUser(User $u): void {
        $_SESSION['user'] = $u;
    }

    public static function logout() {
        unset($_SESSION['user']);
    }

}
