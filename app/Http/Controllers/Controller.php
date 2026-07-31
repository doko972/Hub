<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Rend $this->authorize() disponible aux contrôleurs. Absent du squelette
    // Laravel 11+, où l'on est censé l'ajouter au besoin.
    use AuthorizesRequests;
}
