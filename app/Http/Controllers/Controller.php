<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

abstract class Controller
{
    public abstract function create(Request $request);
    public abstract function update(Request $request);
    public abstract function delete(Request $request);
    public abstract function get(Request $request);
    public abstract function query(Request $request);
     
}
