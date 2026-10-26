<?php

namespace App\Http\Controllers\Ranger;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('ranger.home');
    }
}
