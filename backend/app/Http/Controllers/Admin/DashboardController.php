<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard'); 
    }
}
