<?php

namespace App\Http\Controllers;

class LarasellStarterController extends Controller
{
    public function __invoke(): string
    {
        return 'Hello from the Larasell starter kit.';
    }
}
