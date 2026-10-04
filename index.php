<?php

require_once __DIR__ . '/includes/init.php';

rediriger(utilisateurConnecte() ? 'dashboard.php' : 'login.php');
