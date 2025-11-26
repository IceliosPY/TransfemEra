<?php
session_start();        // on récupère la session
session_unset();        // on vide les variables de session
session_destroy();      // on détruit la session

// on renvoie vers la page d'accueil
header("Location: index.php");
exit;
?>