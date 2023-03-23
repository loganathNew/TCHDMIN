<?php
# File: app\Http\Middleware\CORS.php
# Create file with below code in above location. And at the end of the file there are other instructions also. 
# Please check. 

namespace App\Http\Middleware;

use Closure;

class CORS
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //       return $next($request);
        //         //header("Access-Control-Allow-Origin: *");

        //         // ALLOW OPTIONS METHOD
        //         $headers = [
        //             "Content-type"=>"application/json",
        //             // 'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
        //             // 'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization'
        //             "Access-Control-Allow-Origin"=>"*",
        //             // "Access-Control-Allow-Credentials"=>"true",
        //             // "Access-Control-Allow-Methods"=>"GET,HEAD,OPTIONS,POST,PUT",
        //             // "Access-Control-Allow-Headers"=>"Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers"
        //         ];

        //         if ($request->getMethod() == "OPTIONS") {
        //             // The client-side application can set only headers allowed in Access-Control-Allow-Headers
        //             return \Response::make('OK', 200, $headers);
        //         }

        //         $response = $next($request);
        //         foreach ($headers as $key => $value)
        //             $response->header($key, $value);
        //         return $response;

        // return $next($request)
        //     ->header('Access-Control-Allow-Origin', '*')
        //     ->header('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS')
        //     ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token, Authorization');

        return $next($request)
        ->header('Access-Control-Allow-Origin', '*')
        ->header( 'Access-Control-Allow-Headers','*' )
        ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS,POST,PUT,DELETE');

            // ->header('Access-Control-Allow-Origin', '*')
            // ->header('Access-Control-Allow-Credentials', 'true')
            // ->header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS, POST, PUT')
            // ->header('Access-Control-Max-Age', '3600')
            // ->header('Access-Control-Allow-Headers', 'Origin, Accept, Content-Type, X-Requested-With');
    }
}

# File::  app\Http\Kernel.php
# Add following line in `protected $middleware` Array.
# \App\Http\Middleware\CORS::class

# And following in `protected $routeMiddleware` Array
# 'cors' => \App\Http\Middleware\CORS::class
