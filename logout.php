<?php
session_start();

// LOGOUT HANDLER UTILITY FUNCTION
class session_manager {
    // DESTROY USER SESSION
    public static function destroy_user_session() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $cookie_params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $cookie_params["path"], 
                $cookie_params["domain"],
                $cookie_params["secure"], 
                $cookie_params["httponly"]
            );
        }
        
        session_destroy();
    }
}

// APP INITIALIZATION CORNER
session_manager::destroy_user_session();
header("Location: login.php");
exit();
?>
