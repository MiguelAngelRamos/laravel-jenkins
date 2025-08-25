<?php
namespace App\Http\Controllers;
class HealthController extends Controller {
    public function ping() {
        return response()->json(['pong' => now()->toISOString()]);
    }
}
