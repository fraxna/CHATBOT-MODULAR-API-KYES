<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ManageRoleplayController extends Controller
{
    public function index()
    {
        return view('pages.manageRoleplay');
    }
}
