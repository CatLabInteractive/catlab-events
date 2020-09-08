<?php

namespace App\Http\Middleware;

use Auth;
use Closure;

class ValidDomain
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
        $validDomains = config('app.valid_domains');
        if ($validDomains === null || count($validDomains) === 0) {
            return $next($request);
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $rootUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

            if (!in_array($rootUrl, $validDomains)) {

                // Is this a https "www" issue?
                $wwwRootUrl = 'https://' . $_SERVER['HTTP_HOST'];
                if (in_array($wwwRootUrl, $validDomains)) {
                    return $this->redirect($wwwRootUrl);
                }

                // Is this a "www" issue?
                $wwwRootUrl = $_SERVER['REQUEST_SCHEME'] . '://www.' . $_SERVER['HTTP_HOST'];
                if (in_array($wwwRootUrl, $validDomains)) {
                    return $this->redirect($wwwRootUrl);
                }

                // Is this a https "www" issue?
                $wwwRootUrl = 'https://www.' . $_SERVER['HTTP_HOST'];
                if (in_array($wwwRootUrl, $validDomains)) {
                    return $this->redirect($wwwRootUrl);
                }

                $redirectUrl = $validDomains[0] . $_SERVER['REQUEST_URI'];
                return $this->redirect($redirectUrl);
            }
        }

        return $next($request);
    }

    /**
     * @param $protocolDomain
     * @return mixed
     */
    protected function redirect($protocolDomain)
    {
        //$protocolDomain .= '/';
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
            $protocolDomain .= $_SERVER['REQUEST_URI'];
        }

        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $protocolDomain .= '?' . $_SERVER['QUERY_STRING'];
        }

        return redirect($protocolDomain);
    }
}
